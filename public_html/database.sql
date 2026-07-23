-- ======================================================
-- DATABASE SCHEMA UPDATES - VERSION 2.0
-- Ludo Tournament Platform - Critical Fixes
-- Date: 2026-07-23
-- ======================================================

-- ==============================================
-- 1. CREATE SESSIONS TABLE (Missing Table)
-- ==============================================
CREATE TABLE IF NOT EXISTS `sessions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `session_token` VARCHAR(255) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `device_type` VARCHAR(50) DEFAULT NULL,
    `last_activity` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_session_token` (`session_token`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_expires_at` (`expires_at`),
    KEY `idx_is_active` (`is_active`),
    KEY `idx_last_activity` (`last_activity`),
    CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================
-- 2. STANDARD ALTER TABLE - ADD is_admin COLUMN
-- ==============================================
-- Check if column exists before adding (MySQL 5.7 compatible)
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'users' 
    AND COLUMN_NAME = 'is_admin'
);

-- Add column only if it doesn't exist
SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE `users` ADD COLUMN `is_admin` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_verified`;', 
    'SELECT "Column is_admin already exists" AS message;'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ==============================================
-- 3. ADD MISSING INDEXES FOR PERFORMANCE
-- ==============================================
-- Matches table indexes
CREATE INDEX IF NOT EXISTS idx_matches_status_created ON matches(status, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_matches_player_turn ON matches(player1_id, player2_id, current_turn_id);
CREATE INDEX IF NOT EXISTS idx_matches_room_status ON matches(room_code, status);

-- Transactions table indexes
CREATE INDEX IF NOT EXISTS idx_transactions_user_status ON transactions(user_id, status);
CREATE INDEX IF NOT EXISTS idx_transactions_created ON transactions(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_transactions_source ON transactions(source, status);

-- Withdrawals table indexes
CREATE INDEX IF NOT EXISTS idx_withdrawals_status_created ON withdrawals(status, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_withdrawals_user_status ON withdrawals(user_id, status);

-- KYC documents indexes
CREATE INDEX IF NOT EXISTS idx_kyc_status_created ON kyc_documents(status, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_kyc_user_status ON kyc_documents(user_id, status);

-- Dispute tickets indexes
CREATE INDEX IF NOT EXISTS idx_disputes_status_created ON dispute_tickets(status, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_disputes_priority ON dispute_tickets(priority, status);

-- ==============================================
-- 4. UPDATE SYSTEM SETTINGS - ADD MISSING KEYS
-- ==============================================
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_group`, `setting_type`, `description`, `is_editable`) 
SELECT * FROM (SELECT 'session_timeout' AS setting_key, '1800' AS setting_value, 'system' AS setting_group, 'integer' AS setting_type, 'Session timeout in seconds', 1) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM system_settings WHERE setting_key = 'session_timeout') LIMIT 1;

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_group`, `setting_type`, `description`, `is_editable`) 
SELECT * FROM (SELECT 'max_login_attempts' AS setting_key, '5' AS setting_value, 'system' AS setting_group, 'integer' AS setting_type, 'Maximum failed login attempts before lockout', 1) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM system_settings WHERE setting_key = 'max_login_attempts') LIMIT 1;

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_group`, `setting_type`, `description`, `is_editable`) 
SELECT * FROM (SELECT 'maintenance_mode' AS setting_key, '0' AS setting_value, 'system' AS setting_group, 'boolean' AS setting_type, 'Enable maintenance mode', 1) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM system_settings WHERE setting_key = 'maintenance_mode') LIMIT 1;

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_group`, `setting_type`, `description`, `is_editable`) 
SELECT * FROM (SELECT 'maintenance_message' AS setting_key, 'We are currently performing scheduled maintenance. Please check back later.' AS setting_value, 'system' AS setting_group, 'text' AS setting_type, 'Maintenance mode message', 1) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM system_settings WHERE setting_key = 'maintenance_message') LIMIT 1;

-- ==============================================
-- 5. CREATE FUNCTION FOR SESSION CLEANUP (Optional)
-- ==============================================
DELIMITER $$

CREATE PROCEDURE clean_expired_sessions()
BEGIN
    DELETE FROM sessions 
    WHERE expires_at < NOW() 
    OR is_active = 0;
END$$

DELIMITER ;

-- Schedule cleanup (run daily)
-- Note: In production, use cron job or MySQL Event Scheduler
-- CREATE EVENT IF NOT EXISTS clean_sessions_event
-- ON SCHEDULE EVERY 1 DAY
-- DO CALL clean_expired_sessions();

-- ==============================================
-- 6. VERIFY ALL TABLES EXIST
-- ==============================================
SELECT 
    TABLE_NAME,
    TABLE_ROWS,
    ENGINE,
    TABLE_COLLATION
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME;

-- ==============================================
-- END OF SCHEMA UPDATES
-- ==============================================
