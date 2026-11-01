<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\notifications\BirthdayNotificationEmail;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * Sends one daily summary email listing everyone whose birthday is today
 * to the admin users and to a configurable list of email addresses.
 *
 * Runs from cron (src/cron/send-birthday-notifications.php). Each
 * recipient is logged per day in birthday_notification_log so repeated
 * runs never send duplicates, and failed sends are retried on the next run.
 */
class BirthdayNotificationService
{
    private const STATUS_PENDING = 'pending';
    private const STATUS_SENT = 'sent';
    private const STATUS_FAILED = 'failed';

    private \Psr\Log\LoggerInterface $logger;
    private ConnectionInterface $connection;

    public function __construct()
    {
        $this->logger = LoggerUtils::getAppLogger();
        $this->connection = Propel::getConnection();
    }

    /**
     * @return array<string, mixed>
     */
    public function runTodayNotifications(?\DateTimeImmutable $now = null): array
    {
        if (!SystemConfig::getBooleanValue('bEnableBirthdayNotifications')) {
            return ['status' => 'disabled'];
        }
        if (!SystemConfig::getBooleanValue('bEnabledEmail')) {
            return ['status' => 'email_disabled'];
        }
        if (!SystemConfig::hasValidMailServerSettings()) {
            return ['status' => 'email_not_configured'];
        }

        $template = trim((string) SystemConfig::getValue('sBirthdayNotificationTemplateHtml'));
        if ($template === '') {
            $this->logger->warning('Birthday notifications are enabled but no HTML template is configured; nothing sent.');

            return ['status' => 'template_not_configured'];
        }
        if (!str_contains($template, 'people')) {
            $this->logger->warning('Birthday notification template does not reference {{ people }}; nothing sent.');

            return ['status' => 'template_invalid'];
        }

        $today = ($now ?? new \DateTimeImmutable('now', $this->getSystemTimeZone()))->setTime(0, 0);
        $people = $this->findBirthdayPeople($today);
        if (count($people) === 0) {
            return ['status' => 'no_birthdays', 'date' => $today->format('Y-m-d')];
        }

        $recipients = $this->getRecipients();
        if (count($recipients) === 0) {
            $this->logger->warning('Birthday notifications are enabled but there are no valid recipients; nothing sent.');

            return ['status' => 'no_recipients', 'date' => $today->format('Y-m-d'), 'birthdays' => count($people)];
        }

        $content = $this->buildContent($people, $today, $template);

        $summary = [
            'status' => 'ok',
            'date' => $today->format('Y-m-d'),
            'birthdays' => count($people),
            'recipients' => count($recipients),
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        foreach ($recipients as $recipient) {
            $logId = $this->claimLog($today, $recipient);
            if ($logId === null) {
                $summary['skipped']++;
                continue;
            }

            try {
                $email = new BirthdayNotificationEmail(
                    [$recipient],
                    $content['subject'],
                    $content['html'],
                    $content['text'],
                    ['toName' => gettext('Team')]
                );
                if (!$email->send()) {
                    throw new \RuntimeException($email->getError());
                }
                $this->updateLog($logId, self::STATUS_SENT, null);
                $summary['sent']++;
            } catch (\Throwable $e) {
                $this->updateLog($logId, self::STATUS_FAILED, $e->getMessage());
                $summary['failed']++;
                $this->logger->error('Failed to send birthday notification email', [
                    'recipient' => $recipient,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $summary;
    }

    /**
     * Same selection rules as the dashboard birthday card: anyone with a
     * birthday today whose family (if any) is not deactivated. People born
     * on 29 February are included on 28 February in non-leap years.
     *
     * @return Person[]
     */
    public function findBirthdayPeople(\DateTimeImmutable $today): array
    {
        $month = (int) $today->format('n');
        $day = (int) $today->format('j');

        $query = PersonQuery::create()
            ->leftJoinWithFamily()
            ->where('Family.DateDeactivated is null');

        if ($this->isLeapDayFallback($today)) {
            $query->filterByBirthMonth(2)->filterByBirthDay([28, 29], Criteria::IN);
        } else {
            $query->filterByBirthMonth($month)->filterByBirthDay($day);
        }

        return iterator_to_array($query->orderByLastName()->orderByFirstName()->find());
    }

    /**
     * Admin users' email addresses (when enabled) plus the configured list,
     * validated, lower-cased and de-duplicated.
     *
     * @return string[]
     */
    public function getRecipients(): array
    {
        $candidates = [];

        if (SystemConfig::getBooleanValue('bBirthdayNotificationIncludeAdmins')) {
            $admins = UserQuery::create()->filterByAdmin(true)->find();
            foreach ($admins as $admin) {
                $person = $admin->getPerson();
                $address = $person ? trim((string) $person->getEmail()) : '';
                if ($address === '') {
                    $this->logger->warning('Admin user has no email address; skipped for birthday notifications', [
                        'userName' => $admin->getUserName(),
                    ]);
                    continue;
                }
                $candidates[] = $address;
            }
        }

        $configured = (string) SystemConfig::getValue('sBirthdayNotificationRecipients');
        foreach (self::parseEmailList($configured) as $address) {
            $candidates[] = $address;
        }

        $recipients = [];
        foreach ($candidates as $address) {
            if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
                $this->logger->warning('Invalid birthday notification recipient skipped', ['recipient' => $address]);
                continue;
            }
            $recipients[strtolower($address)] = strtolower($address);
        }

        return array_values($recipients);
    }

    /**
     * Split a free-form list on commas, semicolons or whitespace.
     *
     * @return string[]
     */
    public static function parseEmailList(string $list): array
    {
        $parts = preg_split('/[\s,;]+/', trim($list)) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn (string $p): bool => $p !== ''));
    }

    /**
     * @param Person[] $people
     *
     * @return array{subject: string, html: string, text: string}
     */
    public function buildContent(array $people, \DateTimeImmutable $today, string $template): array
    {
        $baseUrl = rtrim((string) SystemURLs::getURL(), '/');
        $churchName = (string) ChurchMetaData::getChurchName();
        $dateLabel = $today->format('l, j F Y');

        $rows = [];
        foreach ($people as $person) {
            $rows[] = [
                'id' => $person->getId(),
                'fullName' => (string) $person->getFullName(),
                'firstName' => (string) $person->getFirstName(),
                'lastName' => (string) $person->getLastName(),
                'age' => $this->getTurningAge($person, $today),
                'familyName' => (string) $person->getFamilyName(),
                'phone' => $this->getPhone($person),
                'email' => (string) $person->getEmail(),
                'address' => trim((string) $person->getAddress()),
                'profileUrl' => $baseUrl . '/PersonView.php?PersonID=' . $person->getId(),
                'isLeapDayBirthday' => (int) $person->getBirthMonth() === 2 && (int) $person->getBirthDay() === 29,
            ];
        }

        $tokens = [
            'churchName' => $churchName,
            'date' => $dateLabel,
            'count' => count($rows),
            'people' => $rows,
        ];

        $subjectTemplate = trim((string) SystemConfig::getValue('sBirthdayNotificationSubject'));
        if ($subjectTemplate === '') {
            $subjectTemplate = '{{ churchName }}: {{ count }} {{ count == 1 ? \'birthday\' : \'birthdays\' }} today';
        }

        $subject = InputUtils::sanitizeText($this->render($subjectTemplate, $tokens, false));
        $html = InputUtils::sanitizeEmailHTML($this->render($template, $tokens, true));

        return [
            'subject' => preg_replace('/\s+/', ' ', $subject),
            'html' => $html,
            'text' => $this->buildPlainText($rows, $dateLabel, $churchName),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function buildPlainText(array $rows, string $dateLabel, string $churchName): string
    {
        $lines = [sprintf(gettext('Birthdays today (%s):'), $dateLabel), ''];
        foreach ($rows as $row) {
            $line = '- ' . $row['fullName'];
            if ($row['age'] !== '') {
                $line .= ' (' . sprintf(gettext('turning %s'), $row['age']) . ')';
            }
            $lines[] = $line;
            foreach (['familyName' => gettext('Family'), 'phone' => gettext('Phone'), 'email' => gettext('Email'), 'address' => gettext('Address')] as $key => $label) {
                if ($row[$key] !== '') {
                    $lines[] = '  ' . $label . ': ' . $row[$key];
                }
            }
            $lines[] = '  ' . $row['profileUrl'];
        }
        $lines[] = '';
        $lines[] = $churchName;

        return implode("\n", $lines);
    }

    private function render(string $template, array $tokens, bool $escapeHtml): string
    {
        $twig = new Environment(new ArrayLoader(['t' => $template]), [
            'autoescape' => $escapeHtml ? 'html' : false,
        ]);

        return $twig->render('t', $tokens);
    }

    private function getTurningAge(Person $person, \DateTimeImmutable $today): string
    {
        $birthYear = (int) ($person->getBirthYear() ?? 0);
        if ($birthYear <= 0 || $person->hideAge()) {
            return '';
        }
        $age = (int) $today->format('Y') - $birthYear;

        return $age > 0 ? (string) $age : '';
    }

    private function getPhone(Person $person): string
    {
        foreach ([$person->getCellPhone(), $person->getHomePhone(), $person->getWorkPhone()] as $phone) {
            if (trim((string) $phone) !== '') {
                return trim((string) $phone);
            }
        }
        $family = $person->getFamily();
        if ($family !== null) {
            $familyPhone = trim((string) $family->getHomePhone());
            if ($familyPhone !== '') {
                return $familyPhone;
            }
        }

        return '';
    }

    private function isLeapDayFallback(\DateTimeImmutable $today): bool
    {
        return (int) $today->format('n') === 2
            && (int) $today->format('j') === 28
            && $today->format('L') === '0';
    }

    /**
     * Reserve today's log row for a recipient. Returns null when this
     * recipient has already been sent today's notification.
     */
    private function claimLog(\DateTimeImmutable $today, string $recipient): ?int
    {
        $now = new \DateTimeImmutable('now', $this->getSystemTimeZone());
        $insert = $this->connection->prepare(
            'INSERT IGNORE INTO birthday_notification_log (bnl_date, bnl_recipient, bnl_status, bnl_created_at)
             VALUES (:date, :recipient, :status, :createdAt)'
        );
        $insert->bindValue(':date', $today->format('Y-m-d'));
        $insert->bindValue(':recipient', $recipient);
        $insert->bindValue(':status', self::STATUS_PENDING);
        $insert->bindValue(':createdAt', $now->format('Y-m-d H:i:s'));
        $insert->execute();
        if ($insert->rowCount() > 0) {
            return (int) $this->connection->lastInsertId();
        }

        $find = $this->connection->prepare(
            'SELECT bnl_id, bnl_status FROM birthday_notification_log WHERE bnl_date = :date AND bnl_recipient = :recipient LIMIT 1'
        );
        $find->bindValue(':date', $today->format('Y-m-d'));
        $find->bindValue(':recipient', $recipient);
        $find->execute();
        $row = $find->fetch(\PDO::FETCH_ASSOC);
        if (!$row || (string) $row['bnl_status'] === self::STATUS_SENT) {
            return null;
        }

        $this->updateLog((int) $row['bnl_id'], self::STATUS_PENDING, null);

        return (int) $row['bnl_id'];
    }

    private function updateLog(int $id, string $status, ?string $error): void
    {
        $now = new \DateTimeImmutable('now', $this->getSystemTimeZone());
        $stmt = $this->connection->prepare(
            'UPDATE birthday_notification_log SET bnl_status = :status, bnl_error = :error, bnl_sent_at = :sentAt WHERE bnl_id = :id'
        );
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':error', $error === null ? null : mb_substr($error, 0, 2000));
        $stmt->bindValue(':sentAt', $status === self::STATUS_SENT ? $now->format('Y-m-d H:i:s') : null);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
    }

    private function getSystemTimeZone(): \DateTimeZone
    {
        try {
            return new \DateTimeZone((string) SystemConfig::getValue('sTimeZone'));
        } catch (\Throwable) {
            return new \DateTimeZone(date_default_timezone_get());
        }
    }
}
