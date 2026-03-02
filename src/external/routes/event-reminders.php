<?php

use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\TokenQuery;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->group('/event-reminders', function (RouteCollectorProxy $group): void {
    $renderer = new PhpRenderer(__DIR__ . '/../templates/');

    $group->get('/optout/{token}', function (Request $request, Response $response, array $args) use ($renderer): Response {
        $token = TokenQuery::create()->findPk($args['token']);

        if ($token === null || $token->getType() !== 'eventReminderOptOut' || !$token->isValid()) {
            return $renderer->render($response->withStatus(404), 'event-reminders/optout.php', [
                'title' => gettext('Invalid or expired link'),
                'message' => gettext('We could not find a valid opt-out link. Please contact your church administrator.'),
            ]);
        }

        $person = PersonQuery::create()->findPk($token->getReferenceId());
        if ($person === null) {
            return $renderer->render($response->withStatus(404), 'event-reminders/optout.php', [
                'title' => gettext('Person not found'),
                'message' => gettext('We could not find a matching person for this opt-out link.'),
            ]);
        }

        $person->setEventReminderOptout(1);
        $person->save();

        return $renderer->render($response, 'event-reminders/optout.php', [
            'title' => gettext('You are opted out'),
            'message' => gettext('You will no longer receive event reminder emails. You can contact your church administrator to opt back in.'),
        ]);
    });
});
