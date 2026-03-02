ALTER TABLE `events_event`
  ADD COLUMN `event_updated` datetime DEFAULT NULL AFTER `event_created`;
