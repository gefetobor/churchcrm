<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Emails\notifications\FirstTimerBulkEmail;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\FirstTimer;
use ChurchCRM\model\ChurchCRM\FirstTimerQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/first-timers', function (RouteCollectorProxy $group): void {
    $group->get('', 'listFirstTimers');
    $group->get('/', 'listFirstTimers');
    $group->post('', 'createFirstTimerAdmin');
    $group->post('/', 'createFirstTimerAdmin');
    $group->patch('/{id:[0-9]+}', 'updateFirstTimerAdmin');
    $group->delete('/{id:[0-9]+}', 'deleteFirstTimerAdmin');
    $group->post('/{id:[0-9]+}/promote', 'promoteFirstTimer');
    $group->post('/email', 'emailFirstTimers');
})->add(AdminRoleAuthMiddleware::class);

function listFirstTimers(Request $request, Response $response, array $args): Response
{
    $queryParams = $request->getQueryParams();
    $query = buildFirstTimerFilteredQuery($queryParams);

    $firstTimers = $query->find();

    return SlimUtils::renderJSON($response, ['firstTimers' => $firstTimers->toArray()]);
}

function createFirstTimerAdmin(Request $request, Response $response, array $args): Response
{
    $data = $request->getParsedBody() ?? [];

    $firstName = trim((string) ($data['firstName'] ?? ''));
    $lastName = trim((string) ($data['lastName'] ?? ''));
    $email = trim((string) ($data['email'] ?? ''));
    $phone = trim((string) ($data['phone'] ?? ''));
    $address = trim((string) ($data['address'] ?? ''));
    $postcode = trim((string) ($data['postcode'] ?? ''));
    $birthDate = parseFirstTimerBirthDate($data['birthDate'] ?? null);

    if ($firstName === '' || $lastName === '' || $email === '' || $phone === '' || $address === '' || $postcode === '') {
        return SlimUtils::renderJSON($response, ['error' => gettext('Please fill the required fields.')], 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return SlimUtils::renderJSON($response, ['error' => gettext('Invalid email address')], 400);
    }

    $firstTimer = new FirstTimer();
    $firstTimer->setFirstName(InputUtils::sanitizeText($firstName));
    $firstTimer->setLastName(InputUtils::sanitizeText($lastName));
    $firstTimer->setEmail(InputUtils::sanitizeText($email));
    $firstTimer->setPhone(InputUtils::sanitizeText($phone));
    $firstTimer->setAddress(InputUtils::sanitizeText($address));
    if (method_exists($firstTimer, 'setPostcode')) {
        $firstTimer->setPostcode(InputUtils::sanitizeText($postcode));
    }
    $firstTimer->setCreatedAt(new DateTime());
    if ($birthDate !== null) {
        $firstTimer->setBirthDate($birthDate);
    }
    $firstTimer->save();

    return SlimUtils::renderJSON($response, $firstTimer->toArray());
}

function updateFirstTimerAdmin(Request $request, Response $response, array $args): Response
{
    $firstTimer = FirstTimerQuery::create()->findOneById((int) $args['id']);
    if ($firstTimer === null) {
        return SlimUtils::renderJSON($response, ['error' => gettext('First timer not found')], 404);
    }

    $data = $request->getParsedBody() ?? [];
    $firstName = trim((string) ($data['firstName'] ?? $firstTimer->getFirstName()));
    $lastName = trim((string) ($data['lastName'] ?? $firstTimer->getLastName()));
    $email = trim((string) ($data['email'] ?? $firstTimer->getEmail()));
    $phone = trim((string) ($data['phone'] ?? $firstTimer->getPhone()));
    $address = trim((string) ($data['address'] ?? $firstTimer->getAddress()));
    $existingPostcode = method_exists($firstTimer, 'getPostcode') ? (string) ($firstTimer->getPostcode() ?? '') : '';
    $postcode = trim((string) ($data['postcode'] ?? $existingPostcode));
    $birthDate = parseFirstTimerBirthDate($data['birthDate'] ?? $firstTimer->getBirthDate());

    if ($firstName === '' || $lastName === '' || $email === '' || $phone === '' || $address === '' || $postcode === '') {
        return SlimUtils::renderJSON($response, ['error' => gettext('Please fill the required fields.')], 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return SlimUtils::renderJSON($response, ['error' => gettext('Invalid email address')], 400);
    }

    $firstTimer->setFirstName(InputUtils::sanitizeText($firstName));
    $firstTimer->setLastName(InputUtils::sanitizeText($lastName));
    $firstTimer->setEmail(InputUtils::sanitizeText($email));
    $firstTimer->setPhone(InputUtils::sanitizeText($phone));
    $firstTimer->setAddress(InputUtils::sanitizeText($address));
    if (method_exists($firstTimer, 'setPostcode')) {
        $firstTimer->setPostcode(InputUtils::sanitizeText($postcode));
    }
    $firstTimer->setUpdatedAt(new DateTime());
    if ($birthDate !== null) {
        $firstTimer->setBirthDate($birthDate);
    } else {
        $firstTimer->setBirthDate(null);
    }
    $firstTimer->save();

    return SlimUtils::renderJSON($response, $firstTimer->toArray());
}

function deleteFirstTimerAdmin(Request $request, Response $response, array $args): Response
{
    $firstTimer = FirstTimerQuery::create()->findOneById((int) $args['id']);
    if ($firstTimer === null) {
        return SlimUtils::renderJSON($response, ['error' => gettext('First timer not found')], 404);
    }
    if ($firstTimer->getPromotedPersonId() !== null) {
        return SlimUtils::renderJSON($response, ['error' => gettext('Promoted first timers cannot be deleted')], 400);
    }

    $firstTimer->delete();

    return SlimUtils::renderJSON($response, ['status' => 'deleted']);
}

function promoteFirstTimer(Request $request, Response $response, array $args): Response
{
    $firstTimer = FirstTimerQuery::create()->findOneById((int) $args['id']);
    if ($firstTimer === null) {
        return SlimUtils::renderJSON($response, ['error' => gettext('First timer not found')], 404);
    }
    if ($firstTimer->getPromotedPersonId() !== null) {
        return SlimUtils::renderJSON($response, [
            'status' => 'already_promoted',
            'personId' => $firstTimer->getPromotedPersonId(),
        ]);
    }

    $currentUser = AuthenticationManager::getCurrentUser();
    $firstName = (string) $firstTimer->getFirstName();
    $lastName = (string) $firstTimer->getLastName();

    $family = new Family();
    $family->setName(trim($lastName . ' ' . gettext('Family')));
    $family->setAddress1((string) $firstTimer->getAddress());
    $firstTimerPostcode = method_exists($firstTimer, 'getPostcode') ? (string) ($firstTimer->getPostcode() ?? '') : '';
    $family->setZip($firstTimerPostcode);
    $family->setHomePhone((string) $firstTimer->getPhone());
    $family->setEmail((string) $firstTimer->getEmail());
    $family->setEnteredBy($currentUser ? $currentUser->getId() : 0);
    $family->setDateEntered(new DateTime());
    $family->save();

    $person = new Person();
    $person->setFirstName($firstName);
    $person->setLastName($lastName);
    $person->setEmail((string) $firstTimer->getEmail());
    $person->setCellPhone((string) $firstTimer->getPhone());
    $person->setAddress1((string) $firstTimer->getAddress());
    $person->setZip($firstTimerPostcode);
    $person->setFamily($family);
    $person->setFmrId(1);
    $person->setEnteredBy($currentUser ? $currentUser->getId() : 0);
    $person->setDateEntered(new DateTime());

    $birthDate = $firstTimer->getBirthDate();
    if ($birthDate instanceof DateTimeInterface) {
        $person->setBirthDay((int) $birthDate->format('d'));
        $person->setBirthMonth((int) $birthDate->format('m'));
        $person->setBirthYear((int) $birthDate->format('Y'));
    }

    $person->save();

    $firstTimer->setPromotedPersonId($person->getId());
    $firstTimer->setPromotedAt(new DateTime());
    $firstTimer->save();

    return SlimUtils::renderJSON($response, [
        'status' => 'promoted',
        'personId' => $person->getId(),
        'familyId' => $family->getId(),
    ]);
}

function emailFirstTimers(Request $request, Response $response, array $args): Response
{
    if (!SystemConfig::getBooleanValue('bEnabledEmail')) {
        return SlimUtils::renderJSON($response, ['error' => gettext('Email is disabled')], 400);
    }
    if (!SystemConfig::hasValidMailServerSettings()) {
        return SlimUtils::renderJSON($response, ['error' => gettext('Email is not configured')], 400);
    }

    $data = $request->getParsedBody() ?? [];
    $subject = trim((string) ($data['subject'] ?? ''));
    $body = (string) ($data['body'] ?? '');
    $copyToSender = !empty($data['copyToSender']) && (string) $data['copyToSender'] !== '0';

    if ($subject === '' || trim($body) === '') {
        return SlimUtils::renderJSON($response, ['error' => gettext('Subject and message are required')], 400);
    }

    $query = buildFirstTimerFilteredQuery($data);
    $query
        ->filterByEmail(null, Criteria::ISNOTNULL)
        ->filterByEmail('', Criteria::NOT_EQUAL);

    $firstTimers = $query->find();
    $emails = [];
    foreach ($firstTimers as $firstTimer) {
        $email = trim((string) $firstTimer->getEmail());
        if ($email !== '') {
            $emails[] = $email;
        }
    }
    $emails = array_values(array_unique($emails));
    if (count($emails) === 0) {
        return SlimUtils::renderJSON($response, ['error' => gettext('No first timer email addresses matched the selected filters')], 400);
    }

    $bodyHtml = InputUtils::sanitizeHTML($body);
    $bodyText = InputUtils::sanitizeText(strip_tags($body));

    $sent = 0;
    $failed = 0;
    $errors = [];
    $copyRecipient = null;
    $copySent = null;
    $copyError = null;

    foreach ($emails as $email) {
        try {
            $emailMessage = new FirstTimerBulkEmail([
                $email,
            ], $subject, $bodyHtml, $bodyText, [
                'toName' => $email,
            ]);
            if ($emailMessage->send()) {
                $sent++;
            } else {
                $failed++;
                $errors[] = $email . ': ' . ($emailMessage->getError() ?: gettext('Unknown SMTP error'));
                LoggerUtils::getAppLogger()->warning('Failed to send first timer email', [
                    'email' => $email,
                    'error' => $emailMessage->getError(),
                ]);
            }
        } catch (Throwable $e) {
            $failed++;
            $errors[] = $email . ': ' . $e->getMessage();
            LoggerUtils::getAppLogger()->error('Exception sending first timer email', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    if ($copyToSender) {
        $currentUser = AuthenticationManager::getCurrentUser();
        $senderEmail = $currentUser ? trim((string) $currentUser->getEmail()) : '';
        if (!filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            $smtpUser = trim((string) SystemConfig::getValue('sSMTPUser'));
            if (filter_var($smtpUser, FILTER_VALIDATE_EMAIL)) {
                $senderEmail = $smtpUser;
            }
        }
        if ($senderEmail !== '' && filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            $copyRecipient = $senderEmail;
            try {
                $copyEmail = new FirstTimerBulkEmail([
                    $senderEmail,
                ], $subject, $bodyHtml, $bodyText, [
                    'toName' => gettext('Sender copy'),
                ]);
                if ($copyEmail->send()) {
                    $copySent = true;
                } else {
                    $copySent = false;
                    $copyError = $copyEmail->getError() ?: gettext('Unknown SMTP error');
                    LoggerUtils::getAppLogger()->warning('Failed to send sender copy for first timer email', [
                        'email' => $senderEmail,
                        'error' => $copyEmail->getError(),
                    ]);
                }
            } catch (Throwable $e) {
                $copySent = false;
                $copyError = $e->getMessage();
                LoggerUtils::getAppLogger()->error('Exception sending sender copy for first timer email', [
                    'email' => $senderEmail,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            $copySent = false;
            $copyError = gettext('No valid sender email configured on your user account');
        }
    }

    return SlimUtils::renderJSON($response, [
        'status' => $failed > 0 ? 'completed_with_failures' : 'completed',
        'sent' => $sent,
        'failed' => $failed,
        'total' => count($emails),
        'recipients' => $emails,
        'copyRecipient' => $copyRecipient,
        'copySent' => $copySent,
        'copyError' => $copyError,
        'errors' => array_slice($errors, 0, 5),
    ]);
}

function buildFirstTimerFilteredQuery(array $params): FirstTimerQuery
{
    $includePromoted = !empty($params['includePromoted']) && (string) $params['includePromoted'] !== '0';
    $allDates = !empty($params['allDates']) && (string) $params['allDates'] !== '0';
    $createdFrom = trim((string) ($params['createdFrom'] ?? ''));
    $createdTo = trim((string) ($params['createdTo'] ?? ''));

    $query = FirstTimerQuery::create()->orderByCreatedAt(Criteria::DESC);
    if (!$includePromoted) {
        $query->filterByPromotedPersonId(null, Criteria::ISNULL);
    }
    if (!$allDates) {
        applyFirstTimerCreatedDateFilter($query, $createdFrom, $createdTo);
    }

    return $query;
}

function applyFirstTimerCreatedDateFilter(FirstTimerQuery $query, string $createdFrom, string $createdTo): void
{
    $fromDate = parseFirstTimerFilterDate($createdFrom);
    $toDate = parseFirstTimerFilterDate($createdTo);

    if ($fromDate === null && $toDate === null) {
        return;
    }

    if ($fromDate !== null && $toDate !== null && $toDate < $fromDate) {
        [$fromDate, $toDate] = [$toDate, $fromDate];
    }

    if ($fromDate !== null) {
        $query->filterByCreatedAt($fromDate->setTime(0, 0, 0)->format('Y-m-d H:i:s'), Criteria::GREATER_EQUAL);
    }
    if ($toDate !== null) {
        $query->filterByCreatedAt($toDate->setTime(23, 59, 59)->format('Y-m-d H:i:s'), Criteria::LESS_EQUAL);
    }
}

function parseFirstTimerFilterDate(string $date): ?DateTimeImmutable
{
    if ($date === '') {
        return null;
    }

    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    if ($parsed instanceof DateTimeImmutable) {
        return $parsed;
    }

    return null;
}

function parseFirstTimerBirthDate($value): ?DateTimeInterface
{
    if ($value instanceof DateTimeInterface) {
        return $value;
    }

    $dateString = trim((string) $value);
    if ($dateString === '') {
        return null;
    }

    $date = DateTime::createFromFormat('Y-m-d', $dateString);
    if ($date instanceof DateTime) {
        return $date;
    }

    $date = DateTime::createFromFormat('m/d/Y', $dateString);
    if ($date instanceof DateTime) {
        return $date;
    }

    return null;
}
