-- Daily birthday notification to admins and configured recipients
CREATE TABLE IF NOT EXISTS `birthday_notification_log` (
  `bnl_id` int(11) NOT NULL auto_increment,
  `bnl_date` date NOT NULL,
  `bnl_recipient` varchar(255) NOT NULL,
  `bnl_status` varchar(16) NOT NULL default 'pending',
  `bnl_error` text,
  `bnl_sent_at` datetime default NULL,
  `bnl_created_at` datetime NOT NULL,
  PRIMARY KEY (`bnl_id`),
  UNIQUE KEY `uniq_date_recipient` (`bnl_date`,`bnl_recipient`)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AUTO_INCREMENT=1;
