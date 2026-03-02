-- Event address text + birthday greeting support
ALTER TABLE `events_event`
  ADD COLUMN `event_location_text` varchar(255) DEFAULT NULL AFTER `event_send_reminders`;

CREATE TABLE IF NOT EXISTS `birthday_email_log` (
  `bel_id` int(11) NOT NULL auto_increment,
  `bel_person_id` int(11) NOT NULL,
  `bel_year` int(4) NOT NULL,
  `bel_status` varchar(16) NOT NULL default 'pending',
  `bel_error` text,
  `bel_sent_at` datetime default NULL,
  `bel_created_at` datetime NOT NULL,
  PRIMARY KEY (`bel_id`),
  UNIQUE KEY `uniq_person_year` (`bel_person_id`,`bel_year`),
  KEY `bel_person_id_idx` (`bel_person_id`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1;

INSERT IGNORE INTO config_cfg (cfg_id, cfg_name, cfg_value)
VALUES
  (2091, 'bEnableBirthdayGreetings', '0'),
  (2092, 'sBirthdayGreetingSubject', '{{ churchName }}: Happy Birthday, {{ firstName }}!'),
  (2093, 'sBirthdayGreetingTemplateHtml', '<p>{{ dear }} {{ firstName }},</p><p>{{ birthdayMessage }}</p><p>{{ confirmSincerely }},<br>{{ churchName }}</p>'),
  (2094, 'sBirthdayGreetingTemplateText', '{{ dear }} {{ firstName }},\n\n{{ birthdayMessage }}\n\n{{ confirmSincerely }},\n{{ churchName }}'),
  (2095, 'sBirthdayGreetingMessage', 'Wishing you a joyful birthday and a blessed year ahead!');
