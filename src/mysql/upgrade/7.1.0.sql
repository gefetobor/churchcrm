ALTER TABLE `events_event`
  ADD COLUMN `event_created` datetime DEFAULT NULL AFTER `event_end`,
  ADD COLUMN `event_send_reminders` tinyint(1) NOT NULL DEFAULT '0' AFTER `event_created`;

ALTER TABLE `person_per`
  ADD COLUMN `per_event_reminder_optout` tinyint(1) NOT NULL DEFAULT '0' AFTER `per_Flags`;

CREATE TABLE `event_reminder_log` (
  `erl_id` int(11) NOT NULL auto_increment,
  `erl_event_id` int(11) NOT NULL,
  `erl_person_id` int(11) NOT NULL,
  `erl_type` varchar(32) NOT NULL,
  `erl_trigger_at` datetime DEFAULT NULL,
  `erl_status` varchar(16) NOT NULL DEFAULT 'pending',
  `erl_error` text,
  `erl_sent_at` datetime DEFAULT NULL,
  `erl_created_at` datetime NOT NULL,
  PRIMARY KEY (`erl_id`),
  UNIQUE KEY `uniq_event_person_type` (`erl_event_id`,`erl_person_id`,`erl_type`),
  KEY `erl_event_id_idx` (`erl_event_id`),
  KEY `erl_person_id_idx` (`erl_person_id`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1;
