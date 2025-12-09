ALTER TABLE `events` ADD `created_by` INT NULL AFTER `nb_max_participants`;

UPDATE `events` SET `created_by` = (SELECT user_id FROM event_roles WHERE event_id = events.id AND role = 'admin' LIMIT 1) WHERE `created_by` IS NULL;

ALTER TABLE `events` ADD CONSTRAINT `fk_events_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;
