<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Service\EventReminderService;

$service = new EventReminderService();
$result = $service->runDueReminders();

echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
