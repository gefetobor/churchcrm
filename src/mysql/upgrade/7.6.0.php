<?php

// First timer postcode support. Idempotent.

use ChurchCRM\Utils\SchemaUtils;

SchemaUtils::addColumnIfMissing('first_timer_ft', 'ft_Postcode', 'varchar(50) DEFAULT NULL AFTER `ft_Address`');
