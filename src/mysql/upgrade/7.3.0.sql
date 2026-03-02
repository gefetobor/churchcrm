-- Add first timer registrations table
CREATE TABLE `first_timer_ft` (
  `ft_ID` int(11) NOT NULL auto_increment,
  `ft_FirstName` varchar(50) NOT NULL,
  `ft_LastName` varchar(50) NOT NULL,
  `ft_Email` varchar(100) NOT NULL,
  `ft_Phone` varchar(30) default NULL,
  `ft_Address` varchar(255) default NULL,
  `ft_BirthDate` date default NULL,
  `ft_CreatedAt` datetime NOT NULL,
  `ft_UpdatedAt` datetime default NULL,
  `ft_PromotedAt` datetime default NULL,
  `ft_PromotedPersonId` int(11) default NULL,
  PRIMARY KEY (`ft_ID`),
  KEY `ft_email_idx` (`ft_Email`),
  KEY `ft_promoted_idx` (`ft_PromotedPersonId`)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci AUTO_INCREMENT=1;

-- Ensure new config exists
INSERT IGNORE INTO config_cfg (cfg_id, cfg_name, cfg_value)
VALUES (2086, 'bEventReminderOnUpdate', '0');
