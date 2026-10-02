<?php

// Sends today's birthday summary to admins and the configured recipients.
// Schedule daily (e.g. 08:00 church time). Safe to run more than once a day:
// each recipient receives at most one email per day.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.' . PHP_EOL);
}

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Service\BirthdayNotificationService;

$service = new BirthdayNotificationService();
$result = $service->runTodayNotifications();

echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;

exit(($result['failed'] ?? 0) > 0 ? 1 : 0);
