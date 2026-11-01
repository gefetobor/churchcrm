<?php

// Event updated timestamp. Idempotent.

use ChurchCRM\Utils\SchemaUtils;

SchemaUtils::addColumnIfMissing('events_event', 'event_updated', 'datetime DEFAULT NULL AFTER `event_created`');
