-- Drop tables if they exist to prevent errors on re-running the script.
DROP TABLE IF EXISTS `event_permissions`;
DROP TABLE IF EXISTS `event_organizers`;

-- Table to store organizers assigned to an event and their role.
CREATE TABLE `event_organizers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `role` ENUM('organizer', 'co-owner', 'volunteer') NOT NULL DEFAULT 'organizer',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_organizer` (`event_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table to store specific permissions for each organizer.
CREATE TABLE `event_permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `permission_name` VARCHAR(255) NOT NULL,
  `is_allowed` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_permission` (`event_id`, `user_id`, `permission_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- You can add some initial data for testing purposes
-- Example: Add an organizer to event with ID 1
-- INSERT INTO `event_organizers` (`event_id`, `user_id`, `role`) VALUES (1, 2, 'organizer');

-- Example: Grant a permission to that organizer
-- INSERT INTO `event_permissions` (`event_id`, `user_id`, `permission_name`, `is_allowed`) VALUES (1, 2, 'can_edit_budget', 1);
