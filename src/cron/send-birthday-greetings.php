<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Service\BirthdayGreetingService;

$service = new BirthdayGreetingService();
$result = $service->runTodayGreetings();

echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
