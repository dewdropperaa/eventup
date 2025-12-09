-- Add category and created_at columns to events table if they don't exist
ALTER TABLE events ADD COLUMN category VARCHAR(100) DEFAULT 'General' AFTER nb_max_participants;
ALTER TABLE events ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER category;
