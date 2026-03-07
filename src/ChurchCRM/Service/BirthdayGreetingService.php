<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Emails\notifications\BirthdayGreetingEmail;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class BirthdayGreetingService
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

    public function runTodayGreetings(): array
    {
        if (!SystemConfig::getBooleanValue('bEnableBirthdayGreetings')) {
            return ['status' => 'disabled'];
        }
        if (!SystemConfig::getBooleanValue('bEnabledEmail')) {
            return ['status' => 'email_disabled'];
        }
        if (!SystemConfig::hasValidMailServerSettings()) {
            return ['status' => 'email_not_configured'];
        }

        $timezone = $this->getSystemTimeZone();
        $today = new \DateTimeImmutable('now', $timezone);
        $year = (int) $today->format('Y');
        $month = (int) $today->format('m');
        $day = (int) $today->format('d');
        $summary = [
            'status' => 'ok',
            'queued' => 0,
            'sent' => 0,
            'failed' => 0,
        ];

        $query = PersonQuery::create()
            ->filterByBirthMonth($month)
            ->filterByBirthDay($day)
            ->filterByEmail(null, Criteria::ISNOTNULL)
            ->filterByEmail('', Criteria::NOT_EQUAL)
            ->filterByClsId($this->getInactiveClassificationIds(), Criteria::NOT_IN)
            ->orderById();

        $people = $query->find();
        /** @var Person $person */
        foreach ($people as $person) {
            $log = $this->createOrReuseLog($person->getId(), $year, $today);
            if ($log === null) {
                continue;
            }

            $summary['queued']++;
            try {
                $this->sendBirthdayEmail($person, $today);
                $this->updateLogStatus($log, self::STATUS_SENT, null, $today);
                $summary['sent']++;
            } catch (\Throwable $e) {
                $this->updateLogStatus($log, self::STATUS_FAILED, $e->getMessage(), $today);
                $summary['failed']++;
                $this->logger->error('Failed to send birthday greeting email', [
                    'personId' => $person->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $summary;
    }

    private function sendBirthdayEmail(Person $person, \DateTimeImmutable $today): void
    {
        $birthYear = (int) ($person->getBirthYear() ?? 0);
        $age = $birthYear > 0 ? (string) max(0, ((int) $today->format('Y') - $birthYear)) : '';
        $tokens = [
            'personName' => InputUtils::sanitizeText($person->getFullName()),
            'firstName' => InputUtils::sanitizeText((string) $person->getFirstName()),
            'lastName' => InputUtils::sanitizeText((string) $person->getLastName()),
            'age' => $age,
            'birthdayMessage' => InputUtils::sanitizeText(SystemConfig::getValue('sBirthdayGreetingMessage')),
            'dear' => InputUtils::sanitizeText(SystemConfig::getValue('sDear')),
            'confirmSincerely' => InputUtils::sanitizeText(SystemConfig::getValue('sConfirmSincerely')),
        ];

        $subject = $this->renderTemplate(SystemConfig::getValue('sBirthdayGreetingSubject'), $tokens, false);
        $birthdayMessage = trim((string) ($tokens['birthdayMessage'] ?? ''));
        $bodyHtml = nl2br(InputUtils::escapeHTML($birthdayMessage));
        $bodyText = $birthdayMessage;

        $email = new BirthdayGreetingEmail([(string) $person->getEmail()], $subject, $bodyHtml, $bodyText, [
            'toName' => InputUtils::sanitizeText($person->getFullName()),
            'firstName' => InputUtils::sanitizeText((string) $person->getFirstName()),
            ...$tokens,
        ]);
        if (!$email->send()) {
            throw new \RuntimeException($email->getError());
        }
    }

    private function createOrReuseLog(int $personId, int $year, \DateTimeImmutable $now): ?array
    {
        $sql = 'INSERT IGNORE INTO birthday_email_log (bel_person_id, bel_year, bel_status, bel_created_at)
                VALUES (:personId, :year, :status, :createdAt)';
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':personId', $personId, \PDO::PARAM_INT);
        $stmt->bindValue(':year', $year, \PDO::PARAM_INT);
        $stmt->bindValue(':status', self::STATUS_PENDING, \PDO::PARAM_STR);
        $stmt->bindValue(':createdAt', $now->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return [
                'id' => (int) $this->connection->lastInsertId(),
            ];
        }

        $findSql = 'SELECT bel_id, bel_status FROM birthday_email_log WHERE bel_person_id = :personId AND bel_year = :year LIMIT 1';
        $findStmt = $this->connection->prepare($findSql);
        $findStmt->bindValue(':personId', $personId, \PDO::PARAM_INT);
        $findStmt->bindValue(':year', $year, \PDO::PARAM_INT);
        $findStmt->execute();
        $row = $findStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        if ((string) $row['bel_status'] === self::STATUS_SENT) {
            return null;
        }

        $resetSql = 'UPDATE birthday_email_log
                     SET bel_status = :status, bel_error = NULL, bel_sent_at = NULL
                     WHERE bel_id = :id';
        $resetStmt = $this->connection->prepare($resetSql);
        $resetStmt->bindValue(':status', self::STATUS_PENDING, \PDO::PARAM_STR);
        $resetStmt->bindValue(':id', (int) $row['bel_id'], \PDO::PARAM_INT);
        $resetStmt->execute();

        return ['id' => (int) $row['bel_id']];
    }

    private function updateLogStatus(array $log, string $status, ?string $error, \DateTimeImmutable $now): void
    {
        $sql = 'UPDATE birthday_email_log
                SET bel_status = :status, bel_error = :error, bel_sent_at = :sentAt
                WHERE bel_id = :id';
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':status', $status, \PDO::PARAM_STR);
        $stmt->bindValue(':error', $error === null ? null : mb_substr($error, 0, 2000), \PDO::PARAM_STR);
        $stmt->bindValue(':sentAt', $now->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
        $stmt->bindValue(':id', (int) $log['id'], \PDO::PARAM_INT);
        $stmt->execute();
    }

    private function renderTemplate(string $template, array $tokens, bool $isHtml): string
    {
        $loader = new ArrayLoader(['template' => $template]);
        $twig = new Environment($loader, ['autoescape' => false]);
        $rendered = $twig->render('template', $tokens);

        if ($isHtml) {
            return InputUtils::sanitizeEmailHTML($rendered);
        }

        return trim(InputUtils::sanitizeText($rendered));
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
}
