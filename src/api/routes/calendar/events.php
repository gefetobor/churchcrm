<?php

use ChurchCRM\model\ChurchCRM\Base\EventQuery;
use ChurchCRM\model\ChurchCRM\Base\EventTypeQuery;
use ChurchCRM\model\ChurchCRM\CalendarQuery;
use ChurchCRM\model\ChurchCRM\Event;
use ChurchCRM\model\ChurchCRM\EventCounts;
use ChurchCRM\model\ChurchCRM\LocationQuery;
use ChurchCRM\Slim\Middleware\EventsMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\AddEventsRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Service\EventReminderService;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;
use Propel\Runtime\Propel;

$app->group('/events', function (RouteCollectorProxy $group): void {
    $group->get('/', 'getAllEvents');
    $group->get('', 'getAllEvents');
    $group->get('/types', 'getEventTypes');
    $group->get('/locations', 'getEventLocations');
    $group->get('/{id}', 'getEvent')->add(new EventsMiddleware());
    $group->get('/{id}/', 'getEvent')->add(new EventsMiddleware());
    $group->get('/{id}/primarycontact', 'getEventPrimaryContact');
    $group->get('/{id}/secondarycontact', 'getEventSecondaryContact');
    $group->get('/{id}/location', 'getEventLocation');
    $group->get('/{id}/audience', 'getEventAudience');

    $group->post('/', 'newEvent')->add(new AddEventsRoleAuthMiddleware());
    $group->post('', 'newEvent')->add(new AddEventsRoleAuthMiddleware());
    $group->post('/{id}', 'updateEvent')->add(new AddEventsRoleAuthMiddleware())->add(new EventsMiddleware());
    $group->post('/{id}/time', 'setEventTime')->add(new AddEventsRoleAuthMiddleware());

    $group->delete('/{id}', 'deleteEvent')->add(new AddEventsRoleAuthMiddleware());
});

function getAllEvents(Request $request, Response $response, array $args): Response
{
    $Events = EventQuery::create()
        ->find();
    if (empty($Events)) {
        throw new HttpNotFoundException($request);
    }

    // Build response with linked groups included
    $eventsArray = [];
    foreach ($Events as $event) {
        $eventData = $event->toArray();
        $imageMeta = getEventImageMetaByEventId((int) $event->getId());
        $eventData['EventImage'] = $imageMeta['imagePath'];
        $eventData['EventImageAlt'] = $imageMeta['imageAlt'];
        // Add linked groups
        $groups = $event->getGroups();
        $groupsArray = [];
        foreach ($groups as $group) {
            $groupsArray[] = [
                'Id' => $group->getId(),
                'Name' => $group->getName()
            ];
        }
        $eventData['Groups'] = $groupsArray;
        $eventsArray[] = $eventData;
    }

    return SlimUtils::renderJSON($response, ['Events' => $eventsArray]);
}

function getEventTypes(Request $request, Response $response, array $args): Response
{
    $EventTypes = EventTypeQuery::create()
        ->orderByName()
        ->find();
    if (empty($EventTypes)) {
        throw new HttpNotFoundException($request);
    }
    return SlimUtils::renderStringJSON($response, $EventTypes->toJSON());
}

function getEventLocations(Request $request, Response $response, array $args): Response
{
    $locations = LocationQuery::create()
        ->orderByLocationName()
        ->find();

    return SlimUtils::renderStringJSON($response, $locations->toJSON());
}

function getEvent(Request $request, Response $response, $args): Response
{
    $Event = $request->getAttribute('event');

    if (empty($Event)) {
        throw new HttpNotFoundException($request);
    }
    $eventData = $Event->toArray();
    $imageMeta = getEventImageMetaByEventId((int) $Event->getId());
    $eventData['EventImage'] = $imageMeta['imagePath'];
    $eventData['EventImageAlt'] = $imageMeta['imageAlt'];

    return SlimUtils::renderJSON($response, $eventData);
}

function getEventPrimaryContact(Request $request, Response $response, array $args): Response
{
    /** @var Event $Event */
    $Event = EventQuery::create()
        ->findOneById($args['id']);
    if (!empty($Event)) {
        $Contact = $Event->getPersonRelatedByPrimaryContactPersonId();
        if ($Contact) {
            return SlimUtils::renderStringJSON($response, $Contact->toJSON());
        }
    }
    throw new HttpNotFoundException($request);
}

function getEventSecondaryContact(Request $request, Response $response, array $args): Response
{
    $Contact = EventQuery::create()
        ->findOneById($args['id'])
        ->getPersonRelatedBySecondaryContactPersonId();
    if (!empty($Contact)) {
        throw new HttpNotFoundException($request);
    }
    return SlimUtils::renderStringJSON($response, $Contact->toJSON());
}

function getEventLocation(Request $request, Response $response, array $args): Response
{
    $Location = EventQuery::create()
        ->findOneById($args['id'])
        ->getLocation();
    if (empty($Location)) {
        throw new HttpNotFoundException($request);
    }

    return SlimUtils::renderStringJSON($response, $Location->toJSON());
}

function getEventAudience(Request $request, Response $response, array $args): Response
{
    $Audience = EventQuery::create()
        ->findOneById($args['id'])
        ->getEventAudiencesJoinGroup();
    if (empty($Audience)) {
        throw new HttpNotFoundException($request);
    }

    return SlimUtils::renderStringJSON($response, $Audience->toJSON());
}

function newEvent(Request $request, Response $response, array $args): Response
{
    $input = $request->getParsedBody();

    //fetch all related event objects before committing this event.
    $type = EventTypeQuery::create()
        ->findOneById($input['Type']);
    if (empty($type)) {
        throw new HttpBadRequestException($request, gettext('invalid event type id'));
    }

    $calendars = CalendarQuery::create()
        ->filterById($input['PinnedCalendars'])
        ->find();
    if (count($calendars) !== count($input['PinnedCalendars'])) {
        throw new HttpBadRequestException($request, gettext('invalid calendar pinning'));
    }

    // we have event type and pined calendars.  now create the event.
    $event = new Event();
    $event->setTitle(InputUtils::sanitizeText($input['Title']));
    $event->setEventType($type);
    $event->setDesc(InputUtils::sanitizeHTML($input['Desc']));
    $event->setStart(str_replace('T', ' ', $input['Start']));
    $event->setEnd(str_replace('T', ' ', $input['End']));
    $event->setText(InputUtils::sanitizeHTML($input['Text']));
    $event->setCreated(new \DateTime());
    $event->setSendReminders(!empty($input['SendReminders']) ? 1 : 0);
    if (hasLocationIdInput($input)) {
        $event->setLocationId(normalizeAndValidateLocationId($request, $input));
    }
    if (hasLocationTextInput($input)) {
        $locationText = normalizeLocationText($input);
        if (method_exists($event, 'setLocationText')) {
            $event->setLocationText($locationText);
        }
    }
    $event->setCalendars($calendars);
    $event->save();
    saveEventImageMetaFromApiInput((int) $event->getId(), $input);

    if ($event->getSendReminders() && SystemConfig::getBooleanValue('bEventReminderOnCreate')) {
        try {
            (new EventReminderService())->sendImmediateForEvent($event->getId(), 'created');
        } catch (Throwable $e) {
            LoggerUtils::getAppLogger()->warning('Failed to send immediate event notification', [
                'eventId' => $event->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    return SlimUtils::renderSuccessJSON($response);
}

function updateEvent(Request $request, Response $response, array $args): Response
{
    $input = $request->getParsedBody();
    /** @var Event $Event */
    $Event = $request->getAttribute('event');
    $id = $Event->getId();

    // Sanitize user-controlled fields before applying to the model
    if (isset($input['Title'])) {
        $input['Title'] = InputUtils::sanitizeText($input['Title']);
    }
    if (isset($input['Desc'])) {
        $input['Desc'] = InputUtils::sanitizeHTML($input['Desc']);
    }
    if (isset($input['Text'])) {
        $input['Text'] = InputUtils::sanitizeHTML($input['Text']);
    }
    if (hasLocationTextInput($input)) {
        $input['LocationText'] = normalizeLocationText($input);
    }

    $Event->fromArray($input);
    $Event->setId($id);
    if (hasLocationIdInput($input)) {
        $Event->setLocationId(normalizeAndValidateLocationId($request, $input));
    }
    if (hasLocationTextInput($input)) {
        $locationText = normalizeLocationText($input);
        if (method_exists($Event, 'setLocationText')) {
            $Event->setLocationText($locationText);
        }
    }
    $Event->setUpdated(new \DateTime());
    $PinnedCalendars = CalendarQuery::create()
        ->filterById($input['PinnedCalendars'], Criteria::IN)
        ->find();
    $Event->setCalendars($PinnedCalendars);

    $Event->save();
    saveEventImageMetaFromApiInput((int) $Event->getId(), $input);

    if ($Event->getSendReminders() && SystemConfig::getBooleanValue('bEventReminderOnUpdate')) {
        try {
            (new EventReminderService())->sendImmediateForEvent($Event->getId(), 'updated');
        } catch (Throwable $e) {
            LoggerUtils::getAppLogger()->warning('Failed to send immediate event update notification', [
                'eventId' => $Event->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    return SlimUtils::renderSuccessJSON($response);
}

function setEventTime(Request $request, Response $response, array $args): Response
{
    $input = $request->getParsedBody();

    $event = EventQuery::create()
        ->findOneById($args['id']);
    if (!$event) {
        throw new HttpNotFoundException($request);
    }
    $event->setStart($input['startTime']);
    $event->setEnd($input['endTime']);
    $event->setUpdated(new \DateTime());
    $event->save();

    if ($event->getSendReminders() && SystemConfig::getBooleanValue('bEventReminderOnUpdate')) {
        try {
            (new EventReminderService())->sendImmediateForEvent($event->getId(), 'updated');
        } catch (Throwable $e) {
            LoggerUtils::getAppLogger()->warning('Failed to send immediate event update notification', [
                'eventId' => $event->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    return SlimUtils::renderSuccessJSON($response);
}

function unusedSetEventAttendance(): void
{
    if ($input->Total > 0 || $input->Visitors || $input->Members) {
        $eventCount = new EventCounts();
        $eventCount->setEvtcntEventid($event->getID());
        $eventCount->setEvtcntCountid(1);
        $eventCount->setEvtcntCountname('Total');
        $eventCount->setEvtcntCountcount($input->Total);
        $eventCount->setEvtcntNotes($input->EventCountNotes);
        $eventCount->save();

        $eventCount = new EventCounts();
        $eventCount->setEvtcntEventid($event->getID());
        $eventCount->setEvtcntCountid(2);
        $eventCount->setEvtcntCountname('Members');
        $eventCount->setEvtcntCountcount($input->Members);
        $eventCount->setEvtcntNotes($input->EventCountNotes);
        $eventCount->save();

        $eventCount = new EventCounts();
        $eventCount->setEvtcntEventid($event->getID());
        $eventCount->setEvtcntCountid(3);
        $eventCount->setEvtcntCountname('Visitors');
        $eventCount->setEvtcntCountcount($input->Visitors);
        $eventCount->setEvtcntNotes($input->EventCountNotes);
        $eventCount->save();
    }
}

function normalizeLocationId(array $input): ?int
{
    $locationId = null;
    if (array_key_exists('LocationId', $input)) {
        $locationId = InputUtils::filterInt($input['LocationId']);
    } elseif (array_key_exists('locationId', $input)) {
        $locationId = InputUtils::filterInt($input['locationId']);
    } elseif (array_key_exists('location_id', $input)) {
        $locationId = InputUtils::filterInt($input['location_id']);
    }

    if ($locationId === null || $locationId <= 0) {
        return null;
    }

    return $locationId;
}

function normalizeAndValidateLocationId(Request $request, array $input): ?int
{
    $locationId = normalizeLocationId($input);

    if ($locationId === null) {
        if (hasLocationIdInput($input)) {
            $rawLocationId = $input['LocationId'] ?? $input['locationId'] ?? $input['location_id'] ?? null;
            if (!isEmptyLocationIdInput($rawLocationId)) {
                throw new HttpBadRequestException($request, gettext('invalid event location id'));
            }
        }

        return null;
    }

    $location = LocationQuery::create()->findOneByLocationId($locationId);
    if ($location === null) {
        throw new HttpBadRequestException($request, gettext('invalid event location id'));
    }

    return $locationId;
}

function hasLocationIdInput(array $input): bool
{
    return array_key_exists('LocationId', $input)
        || array_key_exists('locationId', $input)
        || array_key_exists('location_id', $input);
}

function isEmptyLocationIdInput($value): bool
{
    if ($value === null) {
        return true;
    }

    $normalized = trim(strtolower((string) $value));
    return $normalized === ''
        || $normalized === '0'
        || $normalized === '-1'
        || $normalized === 'null'
        || $normalized === 'undefined'
        || $normalized === 'none'
        || $normalized === 'n/a'
        || $normalized === 'nan';
}

function normalizeLocationText(array $input): ?string
{
    $locationText = null;
    if (array_key_exists('LocationText', $input)) {
        $locationText = (string) $input['LocationText'];
    } elseif (array_key_exists('locationText', $input)) {
        $locationText = (string) $input['locationText'];
    } elseif (array_key_exists('eventLocation', $input)) {
        $locationText = (string) $input['eventLocation'];
    }

    if ($locationText === null) {
        return null;
    }

    $locationText = InputUtils::sanitizeText(trim($locationText));
    if ($locationText === '') {
        return null;
    }

    return mb_substr($locationText, 0, 255);
}

function hasLocationTextInput(array $input): bool
{
    return array_key_exists('LocationText', $input)
        || array_key_exists('locationText', $input)
        || array_key_exists('eventLocation', $input);
}

/**
 * @return array{imagePath:string,imageAlt:string}
 */
function getEventImageMetaByEventId(int $eventId): array
{
    $result = ['imagePath' => '', 'imageAlt' => ''];
    if ($eventId <= 0) {
        return $result;
    }

    try {
        $connection = Propel::getConnection();
        $stmt = $connection->prepare('SELECT eim_image_path, eim_image_alt FROM event_images_eim WHERE eim_event_id = :eventId LIMIT 1');
        $stmt->bindValue(':eventId', $eventId, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row) {
            $result['imagePath'] = (string) ($row['eim_image_path'] ?? '');
            $result['imageAlt'] = (string) ($row['eim_image_alt'] ?? '');
        }
    } catch (Throwable $e) {
        LoggerUtils::getAppLogger()->warning('Unable to read event image metadata', [
            'eventId' => $eventId,
            'error' => $e->getMessage(),
        ]);
    }

    return $result;
}

function saveEventImageMetaFromApiInput(int $eventId, array $input): void
{
    if ($eventId <= 0) {
        return;
    }

    $remove = !empty($input['RemoveEventImage']) || !empty($input['removeEventImage']);
    $imagePathRaw = $input['EventImage'] ?? $input['eventImageUrl'] ?? $input['Image'] ?? null;
    $imageAltRaw = $input['EventImageAlt'] ?? $input['eventImageAlt'] ?? $input['ImageAlt'] ?? null;

    $imagePath = is_string($imagePathRaw) ? trim($imagePathRaw) : '';
    $imageAlt = is_string($imageAltRaw) ? trim($imageAltRaw) : '';

    try {
        $connection = Propel::getConnection();

        if ($remove) {
            $stmt = $connection->prepare('DELETE FROM event_images_eim WHERE eim_event_id = :eventId');
            $stmt->bindValue(':eventId', $eventId, \PDO::PARAM_INT);
            $stmt->execute();
            return;
        }

        if ($imagePath === '' && $imageAltRaw === null) {
            return;
        }

        if ($imagePath !== '' && !str_starts_with($imagePath, '/Images/')) {
            $imagePath = '';
        }

        $stmt = $connection->prepare('INSERT INTO event_images_eim (eim_event_id, eim_image_path, eim_image_alt, eim_updated_at)
            VALUES (:eventId, :imagePath, :imageAlt, NOW())
            ON DUPLICATE KEY UPDATE
                eim_image_path = VALUES(eim_image_path),
                eim_image_alt = VALUES(eim_image_alt),
                eim_updated_at = NOW()');
        $stmt->bindValue(':eventId', $eventId, \PDO::PARAM_INT);
        $stmt->bindValue(':imagePath', InputUtils::sanitizeText($imagePath), \PDO::PARAM_STR);
        $stmt->bindValue(':imageAlt', InputUtils::sanitizeText($imageAlt), \PDO::PARAM_STR);
        $stmt->execute();
    } catch (Throwable $e) {
        LoggerUtils::getAppLogger()->warning('Unable to save event image metadata', [
            'eventId' => $eventId,
            'error' => $e->getMessage(),
        ]);
    }
}

function deleteEvent(Request $request, Response $response, array $args): Response
{
    $event = EventQuery::create()->findOneById($args['id']);
    if (!$event) {
        throw new HttpNotFoundException($request);
    }
    $event->delete();

    return SlimUtils::renderSuccessJSON($response);
}
