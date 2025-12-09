-- ========================================
-- Communication Hub - Complete MySQL Implementation
-- ========================================
-- EventUp Event Management System
-- Created: December 9, 2024
-- Purpose: Real-time messaging for event organizers

-- ========================================
-- Step 1: Create event_messages table
-- ========================================

-- Drop table if it exists (for fresh installation)
DROP TABLE IF EXISTS event_messages;

-- Create the event_messages table
CREATE TABLE event_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    message_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign key constraints with CASCADE delete
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Indexes for performance optimization
    INDEX idx_event_id (event_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_event_created (event_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Step 2: Verify table structure
-- ========================================

-- Show table structure (uncomment to run)
-- DESCRIBE event_messages;

-- ========================================
-- Step 3: Verify indexes (uncomment to run)
-- ========================================

-- Show indexes (uncomment to run)
-- SHOW INDEX FROM event_messages;

-- ========================================
-- Step 4: Test table creation (uncomment to run)
-- ========================================

-- Test insert (uncomment to run)
-- INSERT INTO event_messages (event_id, user_id, message_text) 
-- VALUES (1, 1, 'Test message');

-- Test select (uncomment to run)
-- SELECT * FROM event_messages;

-- Clean up test data (uncomment to run)
-- DELETE FROM event_messages WHERE message_text = 'Test message';

-- ========================================
-- Step 5: Verify dependencies
-- ========================================

-- Check if events table exists (uncomment to run)
-- SHOW TABLES LIKE 'events';

-- Check if users table exists (uncomment to run)
-- SHOW TABLES LIKE 'users';

-- Check events table structure (uncomment to run)
-- DESCRIBE events;

-- Check users table structure (uncomment to run)
-- DESCRIBE users;

-- ========================================
-- Step 6: Performance optimization
-- ========================================

-- Analyze table for optimal query performance (uncomment to run)
-- ANALYZE TABLE event_messages;

-- ========================================
-- Step 7: Security verification
-- ========================================

-- Verify foreign key constraints are enforced (uncomment to run)
-- SELECT 
--     TABLE_NAME,
--     COLUMN_NAME,
--     CONSTRAINT_NAME,
--     REFERENCED_TABLE_NAME,
--     REFERENCED_COLUMN_NAME
-- FROM 
--     INFORMATION_SCHEMA.KEY_COLUMN_USAGE
-- WHERE 
--     TABLE_SCHEMA = 'event_management' 
--     AND TABLE_NAME = 'event_messages'
--     AND REFERENCED_TABLE_NAME IS NOT NULL;

-- ========================================
-- Step 8: Test queries (for development/testing)
-- ========================================

-- Test query for fetching messages (uncomment to run)
-- SELECT 
--     em.id,
--     em.event_id,
--     em.user_id,
--     em.message_text,
--     em.created_at,
--     u.nom as sender_name,
--     CASE WHEN em.user_id = 1 THEN 1 ELSE 0 END as is_current_user
-- FROM event_messages em
-- JOIN users u ON em.user_id = u.id
-- WHERE em.event_id = 1
-- ORDER BY em.created_at ASC
-- LIMIT 100;

-- Test query for message statistics (uncomment to run)
-- SELECT 
--     COUNT(*) as total_messages,
--     COUNT(DISTINCT user_id) as unique_senders,
--     MIN(created_at) as first_message,
--     MAX(created_at) as last_message
-- FROM event_messages
-- WHERE event_id = 1;

-- ========================================
-- Step 9: Maintenance queries (for future use)
-- ========================================

-- Archive old messages (older than 1 year) - uncomment to run
-- CREATE TABLE event_messages_archive AS
-- SELECT * FROM event_messages 
-- WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);
-- 
-- DELETE FROM event_messages 
-- WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);

-- Check table size (uncomment to run)
-- SELECT 
--     table_name,
--     ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
-- FROM information_schema.TABLES
-- WHERE table_schema = 'event_management'
-- AND table_name = 'event_messages';

-- ========================================
-- Step 10: Sample data (for testing only)
-- ========================================

-- Insert sample messages (uncomment to run)
-- Note: Make sure you have valid event_id and user_id values

-- INSERT INTO event_messages (event_id, user_id, message_text) VALUES
-- (1, 1, 'Hello team! Welcome to the Communication Hub.'),
-- (1, 2, 'Thanks! This looks great.'),
-- (1, 1, 'Let''s coordinate our event planning here.'),
-- (1, 3, 'Perfect! I''ll be active here.'),
-- (1, 2, 'What''s our first task?');

-- ========================================
-- COMPLETION MESSAGE
-- ========================================

-- The Communication Hub database setup is now complete!
-- 
-- Next steps:
-- 1. Verify all files are in place
-- 2. Test the Communication Hub functionality
-- 3. Run the test scenarios from COMMUNICATION_HUB_TESTING.md
-- 
-- For more information, see:
-- - COMMUNICATION_HUB_SETUP.md
-- - COMMUNICATION_HUB_QUICK_START.md
-- - COMMUNICATION_HUB_TESTING.md

SELECT 'Communication Hub database setup completed successfully!' as status;
