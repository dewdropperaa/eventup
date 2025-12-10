-- Create notifications table
CREATE TABLE IF NOT EXISTS notifications (
  id int(11) AUTO_INCREMENT PRIMARY KEY,
  user_id int(11) NOT NULL,
  event_id int(11) NULL,
  type varchar(50) NOT NULL,
  message text NOT NULL,
  is_read tinyint(1) NOT NULL DEFAULT 0,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notifications_user
    FOREIGN KEY (user_id) REFERENCES users(id)
      ON UPDATE CASCADE
      ON DELETE CASCADE,
  CONSTRAINT fk_notifications_event
    FOREIGN KEY (event_id) REFERENCES events(id)
      ON UPDATE CASCADE
      ON DELETE SET NULL,
  INDEX idx_notifications_user (user_id, is_read),
  INDEX idx_notifications_event (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
