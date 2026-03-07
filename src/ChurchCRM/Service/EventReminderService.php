<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\notifications\EventReminderEmail;
use ChurchCRM\model\ChurchCRM\Event;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\LocationQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\Token;
use ChurchCRM\model\ChurchCRM\TokenQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class EventReminderService
{
    private const TYPE_CREATED = 'created';
    private const TYPE_DAYS_BEFORE = 'days_before';
    private const TYPE_24_HOURS = 'hours_before';
    private const TYPE_UPDATED = 'updated';
    private const OPT_OUT_TOKEN_TYPE = 'eventReminderOptOut';

    private const STATUS_PENDING = 'pending';
    private const STATUS_SENT = 'sent';
    private const STATUS_FAILED = 'failed';

    private const DEFAULT_BATCH_SIZE = 500;

    private \Psr\Log\LoggerInterface $logger;
    private ConnectionInterface $connection;
    private array $locationCache = [];
    private array $optOutTokenCache = [];

    public function __construct()
    {
        $this->logger = LoggerUtils::getAppLogger();
        $this->connection = Propel::getConnection();
    }

    public function runDueReminders(): array
    {
        if (!SystemConfig::getBooleanValue('bEnableEventReminders')) {
            return ['status' => 'disabled'];
        }
        if (!SystemConfig::getBooleanValue('bEnabledEvents')) {
            return ['status' => 'events_disabled'];
        }
        if (!SystemConfig::getBooleanValue('bEnabledEmail')) {
            return ['status' => 'email_disabled'];
        }
        if (!SystemConfig::hasValidMailServerSettings()) {
            return ['status' => 'email_not_configured'];
        }

        $timezone = $this->getSystemTimeZone();
        $now = new \DateTimeImmutable('now', $timezone);
        $summary = [
            'status' => 'ok',
            'eventsProcessed' => 0,
            'remindersQueued' => 0,
            'remindersSent' => 0,
            'remindersFailed' => 0,
        ];

        $daysBefore = max(0, SystemConfig::getIntValue('iEventReminderDaysBefore'));

        // Created/updated reminders are sent immediately at event create/update
        // call sites via sendImmediateForEvent(). Keep cron focused on
        // time-based reminders only to avoid cross-triggering behavior.

        if ($daysBefore > 0) {
            $this->processReminderType(self::TYPE_DAYS_BEFORE, $now, $daysBefore, $summary);
        }

        if (SystemConfig::getBooleanValue('bEventReminder24Hours')) {
            $this->processReminderType(self::TYPE_24_HOURS, $now, $daysBefore, $summary);
        }

        return $summary;
    }

    public function sendImmediateForEvent(int $eventId, string $type): array
    {
        if (!SystemConfig::getBooleanValue('bEnableEventReminders')) {
            return ['status' => 'disabled'];
        }
        if (!SystemConfig::getBooleanValue('bEnabledEvents')) {
            return ['status' => 'events_disabled'];
        }
        if (!SystemConfig::getBooleanValue('bEnabledEmail')) {
            return ['status' => 'email_disabled'];
        }
        if (!SystemConfig::hasValidMailServerSettings()) {
            return ['status' => 'email_not_configured'];
        }

        if (!in_array($type, [self::TYPE_CREATED, self::TYPE_UPDATED], true)) {
            return ['status' => 'unsupported_type'];
        }

        $event = EventQuery::create()->findOneById($eventId);
        if ($event === null) {
            return ['status' => 'event_not_found'];
        }
        if ((int) $event->getInActive() !== 0) {
            return ['status' => 'event_inactive'];
        }
        if ((int) $event->getSendReminders() !== 1) {
            return ['status' => 'event_reminders_disabled'];
        }

        $timezone = $this->getSystemTimeZone();
        $now = new \DateTimeImmutable('now', $timezone);
        $eventStart = $this->toDateTimeImmutable($event->getStart(), $timezone);
        if ($eventStart <= $now) {
            return ['status' => 'event_in_past'];
        }

        $daysBefore = max(0, SystemConfig::getIntValue('iEventReminderDaysBefore'));
        $triggerAt = $this->getTriggerAt($event, $type, $daysBefore);
        if ($triggerAt === null) {
            return ['status' => 'missing_trigger_time'];
        }

        $summary = [
            'status' => 'ok',
            'eventsProcessed' => 1,
            'remindersQueued' => 0,
            'remindersSent' => 0,
            'remindersFailed' => 0,
        ];

        $this->sendForEvent($event, $type, $triggerAt, $daysBefore, $now, $summary);

        return $summary;
    }

    private function processReminderType(string $type, \DateTimeImmutable $now, int $daysBefore, array &$summary): void
    {
        $events = $this->getEventsDueForType($type, $now, $daysBefore);
        foreach ($events as $event) {
            $triggerAt = $this->getTriggerAt($event, $type, $daysBefore);
            if ($triggerAt === null || $triggerAt > $now) {
                continue;
            }
            $summary['eventsProcessed']++;
            $this->sendForEvent($event, $type, $triggerAt, $daysBefore, $now, $summary);
        }
    }

    private function getEventsDueForType(string $type, \DateTimeImmutable $now, int $daysBefore)
    {
        $query = EventQuery::create()
            ->filterByInActive(0)
            ->filterBySendReminders(1)
            ->filterByStart($now->format('Y-m-d H:i:s'), Criteria::GREATER_THAN)
            ->orderByStart();

        if ($type === self::TYPE_CREATED) {
            $query->filterByCreated(null, Criteria::ISNOTNULL);
            $query->filterByCreated($now->format('Y-m-d H:i:s'), Criteria::LESS_EQUAL);
        } elseif ($type === self::TYPE_UPDATED) {
            $query->filterByUpdated(null, Criteria::ISNOTNULL);
            $query->filterByUpdated($now->format('Y-m-d H:i:s'), Criteria::LESS_EQUAL);
        } elseif ($type === self::TYPE_DAYS_BEFORE) {
            $cutoff = $now->modify('+' . $daysBefore . ' days');
            $query->filterByStart($cutoff->format('Y-m-d H:i:s'), Criteria::LESS_EQUAL);
        } elseif ($type === self::TYPE_24_HOURS) {
            $cutoff = $now->modify('+24 hours');
            $query->filterByStart($cutoff->format('Y-m-d H:i:s'), Criteria::LESS_EQUAL);
        }

        return $query->find();
    }

    private function sendForEvent(Event $event, string $type, \DateTimeImmutable $triggerAt, int $daysBefore, \DateTimeImmutable $now, array &$summary): void
    {
        $batchSize = self::DEFAULT_BATCH_SIZE;
        $offset = 0;
        $enrolledBefore = null;

        // "Created" and "Updated" notifications are historical event actions.
        // Members added after that action should not receive those notices.
        if (in_array($type, [self::TYPE_CREATED, self::TYPE_UPDATED], true)) {
            $enrolledBefore = $triggerAt;
        }

        while (true) {
            $people = $this->getEligiblePeopleBatch($batchSize, $offset, $enrolledBefore);
            if ($people->isEmpty()) {
                break;
            }

            /** @var Person $person */
            foreach ($people as $person) {
                $logId = $this->createPendingLog($event->getId(), $person->getId(), $type, $triggerAt, $now);
                if ($logId === null) {
                    continue;
                }

                $summary['remindersQueued']++;
                try {
                    $this->sendReminderEmail($event, $person, $type, $daysBefore);
                    $this->markLogSent($logId, $now);
                    $summary['remindersSent']++;
                } catch (\Throwable $e) {
                    $this->markLogFailed($logId, $e->getMessage(), $now);
                    $summary['remindersFailed']++;
                    $this->logger->error('Failed to send event reminder', [
                        'eventId' => $event->getId(),
                        'personId' => $person->getId(),
                        'type' => $type,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $offset += $batchSize;
        }
    }

    private function getEligiblePeopleBatch(int $limit, int $offset, ?\DateTimeImmutable $enrolledBefore = null)
    {
        $inactiveClasses = $this->getInactiveClassificationIds();

        $query = PersonQuery::create()
            ->filterByEmail(null, Criteria::ISNOTNULL)
            ->filterByEmail('', Criteria::NOT_EQUAL)
            ->filterByClsId($inactiveClasses, Criteria::NOT_IN)
            ->filterByEventReminderOptout(0)
            ->orderById()
            ->limit($limit)
            ->offset($offset);

        if ($enrolledBefore !== null) {
            $query->filterByDateEntered($enrolledBefore->format('Y-m-d H:i:s'), Criteria::LESS_EQUAL);
        }

        return $query->find();
    }

    private function sendReminderEmail(Event $event, Person $person, string $type, int $daysBefore): void
    {
        $timezone = $this->getSystemTimeZone();
        $eventStart = $this->toDateTimeImmutable($event->getStart(), $timezone);
        $eventEnd = $this->toDateTimeImmutable($event->getEnd(), $timezone);
        $optOutUrl = $this->getOptOutUrl($person);
        $eventLocation = $this->getEventLocationLine($event);
        $eventDescriptionHtmlRaw = trim((string) $event->getDesc());
        $eventDescriptionText = $this->normalizeDescriptionText($eventDescriptionHtmlRaw);

        $baseTokens = [
            'eventTitle' => InputUtils::sanitizeText((string) $event->getTitle()),
            'eventStart' => $eventStart->format(SystemConfig::getValue('sDateTimeFormat')),
            'eventEnd' => $eventEnd->format(SystemConfig::getValue('sDateTimeFormat')),
            'eventLocation' => InputUtils::sanitizeText($eventLocation),
            'eventUrl' => InputUtils::sanitizeText((string) $event->getURL()),
            'personName' => InputUtils::sanitizeText($person->getFullName()),
            'firstName' => InputUtils::sanitizeText((string) $person->getFirstName()),
            'reminderType' => $this->getReminderTypeLabel($type, $daysBefore),
            'daysBefore' => (string) $daysBefore,
            'optOutUrl' => $optOutUrl,
            'optOutText' => gettext('Unsubscribe from event reminders'),
            'toName' => InputUtils::sanitizeText($person->getFullName()),
            'churchLogoUrl' => InputUtils::sanitizeText(SystemConfig::getValue('sEventReminderLogoUrl')),
            'brandLogoUrl' => InputUtils::sanitizeText(SystemConfig::getValue('sEventReminderLogoUrl')),
            'eventContactEmail' => InputUtils::sanitizeText(SystemConfig::getValue('sEventReminderContactEmail')),
        ];

        $tokensHtml = $baseTokens;
        $tokensHtml['eventDescription'] = $eventDescriptionText === '' ? '' : InputUtils::sanitizeText($eventDescriptionText);

        $tokensText = $baseTokens;
        $tokensText['eventDescription'] = $eventDescriptionText === '' ? '' : InputUtils::sanitizeText($eventDescriptionText);

        $bodyHtml = $this->renderTemplate(SystemConfig::getValue('sEventReminderTemplateHtml'), $tokensHtml, true);
        $bodyHtml = $this->stripInlineImagesFromBody($bodyHtml);
        $bodyText = $this->renderTemplate(SystemConfig::getValue('sEventReminderTemplateText'), $tokensText, false);

        $subject = match ($type) {
            self::TYPE_UPDATED => gettext('Event Update'),
            self::TYPE_CREATED => gettext('Event Notification'),
            default => gettext('Event Reminder'),
        };
        $email = new EventReminderEmail([$person->getEmail()], $subject, $bodyHtml, $bodyText, $baseTokens);
        if (!$email->send()) {
            throw new \RuntimeException($email->getError());
        }
    }

    private function stripInlineImagesFromBody(string $html): string
    {
        return preg_replace('/<img\b[^>]*>/i', '', $html) ?? $html;
    }

    private function normalizeDescriptionText(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = str_replace("\xC2\xA0", ' ', $decoded);
        $text = trim(strip_tags($decoded));
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return trim($text);
    }

    private function renderTemplate(string $template, array $tokens, bool $isHtml): string
    {
        $loader = new ArrayLoader(['template' => $template]);
        $twig = new Environment($loader, ['autoescape' => false]);
        $rendered = $twig->render('template', $tokens);

        if ($isHtml) {
            return InputUtils::sanitizeEmailHTML($rendered);
        }

        return trim($rendered);
    }

    private function getTriggerAt(Event $event, string $type, int $daysBefore): ?\DateTimeImmutable
    {
        $timezone = $this->getSystemTimeZone();
        if ($type === self::TYPE_CREATED) {
            $created = $event->getCreated();
            if ($created === null) {
                return null;
            }
            return $this->toDateTimeImmutable($created, $timezone);
        }
        if ($type === self::TYPE_UPDATED) {
            $updated = $event->getUpdated();
            if ($updated === null) {
                return null;
            }
            return $this->toDateTimeImmutable($updated, $timezone);
        }

        $eventStart = $this->toDateTimeImmutable($event->getStart(), $timezone);
        if ($type === self::TYPE_DAYS_BEFORE) {
            return $eventStart->modify('-' . $daysBefore . ' days');
        }
        if ($type === self::TYPE_24_HOURS) {
            return $eventStart->modify('-24 hours');
        }

        return null;
    }

    private function createPendingLog(int $eventId, int $personId, string $type, \DateTimeImmutable $triggerAt, \DateTimeImmutable $now): ?int
    {
        if ($type === self::TYPE_UPDATED) {
            $existing = $this->findExistingUpdateLog($eventId, $personId, $type);
            if ($existing !== null) {
                if ($existing['trigger_at'] >= $triggerAt->format('Y-m-d H:i:s')) {
                    return null;
                }

                $sql = 'UPDATE event_reminder_log
                        SET erl_trigger_at = :triggerAt, erl_status = :status, erl_error = NULL, erl_sent_at = NULL
                        WHERE erl_id = :id';
                $stmt = $this->connection->prepare($sql);
                $stmt->bindValue(':triggerAt', $triggerAt->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
                $stmt->bindValue(':status', self::STATUS_PENDING, \PDO::PARAM_STR);
                $stmt->bindValue(':id', (int) $existing['id'], \PDO::PARAM_INT);
                $stmt->execute();

                return (int) $existing['id'];
            }
        }

        $sql = 'INSERT IGNORE INTO event_reminder_log (erl_event_id, erl_person_id, erl_type, erl_trigger_at, erl_status, erl_created_at)
                VALUES (:eventId, :personId, :type, :triggerAt, :status, :createdAt)';
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':eventId', $eventId, \PDO::PARAM_INT);
        $stmt->bindValue(':personId', $personId, \PDO::PARAM_INT);
        $stmt->bindValue(':type', $type, \PDO::PARAM_STR);
        $stmt->bindValue(':triggerAt', $triggerAt->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
        $stmt->bindValue(':status', self::STATUS_PENDING, \PDO::PARAM_STR);
        $stmt->bindValue(':createdAt', $now->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            return null;
        }

        return (int) $this->connection->lastInsertId();
    }

    private function findExistingUpdateLog(int $eventId, int $personId, string $type): ?array
    {
        $sql = 'SELECT erl_id, erl_trigger_at
                FROM event_reminder_log
                WHERE erl_event_id = :eventId AND erl_person_id = :personId AND erl_type = :type
                LIMIT 1';
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':eventId', $eventId, \PDO::PARAM_INT);
        $stmt->bindValue(':personId', $personId, \PDO::PARAM_INT);
        $stmt->bindValue(':type', $type, \PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return [
            'id' => (int) $row['erl_id'],
            'trigger_at' => (string) $row['erl_trigger_at'],
        ];
    }

    private function markLogSent(int $logId, \DateTimeImmutable $now): void
    {
        $sql = 'UPDATE event_reminder_log SET erl_status = :status, erl_sent_at = :sentAt WHERE erl_id = :id';
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':status', self::STATUS_SENT, \PDO::PARAM_STR);
        $stmt->bindValue(':sentAt', $now->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
        $stmt->bindValue(':id', $logId, \PDO::PARAM_INT);
        $stmt->execute();
    }

    private function markLogFailed(int $logId, string $error, \DateTimeImmutable $now): void
    {
        $sql = 'UPDATE event_reminder_log SET erl_status = :status, erl_error = :error, erl_sent_at = :sentAt WHERE erl_id = :id';
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':status', self::STATUS_FAILED, \PDO::PARAM_STR);
        $stmt->bindValue(':error', mb_substr($error, 0, 2000), \PDO::PARAM_STR);
        $stmt->bindValue(':sentAt', $now->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
        $stmt->bindValue(':id', $logId, \PDO::PARAM_INT);
        $stmt->execute();
    }

    private function getOptOutUrl(Person $person): string
    {
        $token = $this->getOrCreateOptOutToken($person->getId());
        return SystemURLs::getURL() . '/external/event-reminders/optout/' . $token->getToken();
    }

    private function getOrCreateOptOutToken(int $personId): Token
    {
        if (array_key_exists($personId, $this->optOutTokenCache)) {
            return $this->optOutTokenCache[$personId];
        }

        $token = TokenQuery::create()
            ->filterByType(self::OPT_OUT_TOKEN_TYPE)
            ->filterByReferenceId($personId)
            ->findOne();

        if ($token !== null) {
            $this->optOutTokenCache[$personId] = $token;
            return $token;
        }

        $token = new Token();
        $token->setToken(bin2hex(random_bytes(16)));
        $token->setType(self::OPT_OUT_TOKEN_TYPE);
        $token->setReferenceId($personId);
        $token->setValidUntilDate(null);
        $token->setRemainingUses(null);
        $token->save();

        $this->optOutTokenCache[$personId] = $token;
        return $token;
    }

    private function getSystemTimeZone(): \DateTimeZone
    {
        $tz = SystemConfig::getValue('sTimeZone');
        try {
            return new \DateTimeZone($tz);
        } catch (\Throwable) {
            return new \DateTimeZone(date_default_timezone_get());
        }
    }

    private function toDateTimeImmutable($value, \DateTimeZone $timezone): \DateTimeImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value)->setTimezone($timezone);
        }

        return new \DateTimeImmutable((string) $value, $timezone);
    }

    private function getInactiveClassificationIds(): array
    {
        $inactive = SystemConfig::getValue('sInactiveClassification');
        if ($inactive === '') {
            return [-1];
        }

        $ids = array_filter(explode(',', $inactive), fn ($id): bool => is_numeric($id));
        if (empty($ids)) {
            return [-1];
        }

        return array_map('intval', $ids);
    }

    private function getReminderTypeLabel(string $type, int $daysBefore): string
    {
        if ($type === self::TYPE_CREATED) {
            return gettext('Created');
        }
        if ($type === self::TYPE_24_HOURS) {
            return gettext('24 hours before');
        }
        if ($type === self::TYPE_DAYS_BEFORE) {
            return $daysBefore . ' ' . gettext('days before');
        }
        if ($type === self::TYPE_UPDATED) {
            return gettext('Updated');
        }
        return $type;
    }

    private function getEventLocationLine(Event $event): string
    {
        $locationText = '';
        if (method_exists($event, 'getLocationText')) {
            $locationText = trim((string) $event->getLocationText());
        }
        if ($locationText !== '') {
            return $locationText;
        }

        $locationId = (int) $event->getLocationId();
        if ($locationId <= 0) {
            return '';
        }
        if (array_key_exists($locationId, $this->locationCache)) {
            return $this->locationCache[$locationId];
        }

        $location = LocationQuery::create()->findOneByLocationId($locationId);
        $formatted = '';
        if ($location !== null) {
            $parts = [
                trim((string) $location->getLocationName()),
                trim((string) $location->getLocationAddress()),
                trim((string) $location->getLocationZip()),
                trim((string) $location->getLocationCity()),
                trim((string) $location->getLocationCountry()),
            ];
            $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
            $formatted = implode(', ', $parts);
        }
        $this->locationCache[$locationId] = $formatted;

        return $formatted;
    }

}
