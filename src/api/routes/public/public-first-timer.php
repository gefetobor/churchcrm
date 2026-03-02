<?php

use ChurchCRM\model\ChurchCRM\FirstTimer;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\InputUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/public/first-timer', function (RouteCollectorProxy $group): void {
    $group->post('', 'createFirstTimerPublic');
    $group->post('/', 'createFirstTimerPublic');
});

function createFirstTimerPublic(Request $request, Response $response, array $args): Response
{
    $data = $request->getParsedBody() ?? [];

    $firstName = trim((string) ($data['firstName'] ?? ''));
    $lastName = trim((string) ($data['lastName'] ?? ''));
    $email = trim((string) ($data['email'] ?? ''));
    $phone = trim((string) ($data['phone'] ?? ''));
    $address = trim((string) ($data['address'] ?? ''));
    $postcode = trim((string) ($data['postcode'] ?? ''));
    $birthDate = parsePublicFirstTimerBirthDate($data['birthDate'] ?? null);

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

    return SlimUtils::renderJSON($response, ['status' => 'created']);
}

function parsePublicFirstTimerBirthDate($value): ?DateTimeInterface
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
