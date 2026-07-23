-- ======================================================
-- DATABASE SCHEMA UPDATES - VERSION 2.0
-- Ludo Tournament Platform - Production Upgrade
-- Date: 2026-07-23
-- ======================================================

-- ======================================================
-- 1. KYC VERIFICATION TABLES
-- ======================================================

CREATE TABLE IF NOT EXISTS `kyc_documents` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `document_type` ENUM('pan', 'aadhaar', 'bank_account') NOT NULL,
    `document_number` VARCHAR(50) NOT NULL,
    `document_image_front` VARCHAR(255) NOT NULL,
    `document_image_back` VARCHAR(255) DEFAULT NULL,
    `selfie_image` VARCHAR(255) DEFAULT NULL,
    `bank_account_number` VARCHAR(50) DEFAULT NULL,
    `bank_ifsc` VARCHAR(20) DEFAULT NULL,
    `bank_account_name` VARCHAR(100) DEFAULT NULL,
    `status` ENUM('pending', 'verified', 'rejected', 'expired') NOT NULL DEFAULT 'pending',
    `rejection_reason` TEXT DEFAULT NULL,
    `verified_by` INT(11) DEFAULT NULL,
    `verified_at` TIMESTAMP NULL DEFAULT NULL,
    `expiry_date` DATE DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_document_type` (`user_id`, `document_type`),
    KEY `idx_status` (`status`),
    KEY `idx_verified_by` (`verified_by`),
    CONSTRAINT `fk_kyc_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_kyc_verified_by` FOREIGN KEY (`verified_by`) 
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- 2. WITHDRAWAL MANAGEMENT TABLES
-- ======================================================

CREATE TABLE IF NOT EXISTS `withdrawals` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `bank_account_number` VARCHAR(50) NOT NULL,
    `bank_ifsc` VARCHAR(20) NOT NULL,
    `bank_account_name` VARCHAR(100) NOT NULL,
    `upi_id` VARCHAR(100) DEFAULT NULL,
    `transaction_id` VARCHAR(100) DEFAULT NULL,
    `status` ENUM('pending', 'processing', 'approved', 'rejected', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    `admin_notes` TEXT DEFAULT NULL,
    `processed_by` INT(11) DEFAULT NULL,
    `processed_at` TIMESTAMP NULL DEFAULT NULL,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    `rejection_reason` TEXT DEFAULT NULL,
    `metadata` JSON DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_transaction_id` (`transaction_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at`),
    CONSTRAINT `fk_withdrawals_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_withdrawals_processed_by` FOREIGN KEY (`processed_by`) 
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- 3. DISPUTE & TICKET SYSTEM TABLES
-- ======================================================

CREATE TABLE IF NOT EXISTS `dispute_tickets` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `match_id` INT(11) NOT NULL,
    `user_id` INT(11) NOT NULL,
    `opponent_id` INT(11) DEFAULT NULL,
    `ticket_number` VARCHAR(20) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `priority` ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
    `status` ENUM('open', 'investigating', 'resolved', 'closed', 'cancelled') NOT NULL DEFAULT 'open',
    `resolution_type` ENUM('winner_declared', 'refund', 'cancelled', 'replay', 'no_action') DEFAULT NULL,
    `resolution_notes` TEXT DEFAULT NULL,
    `resolved_by` INT(11) DEFAULT NULL,
    `resolved_at` TIMESTAMP NULL DEFAULT NULL,
    `refund_amount` DECIMAL(12,2) DEFAULT NULL,
    `admin_notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ticket_number` (`ticket_number`),
    KEY `idx_match_id` (`match_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_opponent_id` (`opponent_id`),
    KEY `idx_status` (`status`),
    KEY `idx_priority` (`priority`),
    CONSTRAINT `fk_ticket_match` FOREIGN KEY (`match_id`) 
        REFERENCES `matches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_ticket_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_ticket_opponent` FOREIGN KEY (`opponent_id`) 
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_ticket_resolved_by` FOREIGN KEY (`resolved_by`) 
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ticket_messages` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `ticket_id` INT(11) NOT NULL,
    `user_id` INT(11) NOT NULL,
    `message` TEXT NOT NULL,
    `screenshot_url` VARCHAR(255) DEFAULT NULL,
    `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ticket_id` (`ticket_id`),
    KEY `idx_user_id` (`user_id`),
    CONSTRAINT `fk_ticket_msg_ticket` FOREIGN KEY (`ticket_id`) 
        REFERENCES `dispute_tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_ticket_msg_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- 4. SYSTEM SETTINGS TABLE (Global Config)
-- ======================================================

CREATE TABLE IF NOT EXISTS `system_settings` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT NOT NULL,
    `setting_group` VARCHAR(50) NOT NULL DEFAULT 'general',
    `setting_type` ENUM('string', 'integer', 'decimal', 'boolean', 'json', 'text') NOT NULL DEFAULT 'string',
    `description` VARCHAR(255) DEFAULT NULL,
    `is_editable` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_setting_key` (`setting_key`),
    KEY `idx_setting_group` (`setting_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- 5. FINANCIAL METRICS TABLE (For Dashboard Analytics)
-- ======================================================

CREATE TABLE IF NOT EXISTS `financial_metrics` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `metric_date` DATE NOT NULL,
    `daily_deposits` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `daily_withdrawals` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `daily_platform_revenue` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `daily_matches_played` INT(11) NOT NULL DEFAULT 0,
    `daily_new_users` INT(11) NOT NULL DEFAULT 0,
    `total_platform_liability` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_user_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_tds_deducted` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_metric_date` (`metric_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- 6. UPDATE USERS TABLE (Add New Columns)
-- ======================================================

ALTER TABLE `users` 
ADD COLUMN `pan_number` VARCHAR(20) DEFAULT NULL AFTER `email`,
ADD COLUMN `aadhaar_number` VARCHAR(20) DEFAULT NULL AFTER `pan_number`,
ADD COLUMN `kyc_status` ENUM('pending', 'verified', 'rejected', 'not_submitted') NOT NULL DEFAULT 'not_submitted' AFTER `is_verified`,
ADD COLUMN `kyc_submitted_at` TIMESTAMP NULL DEFAULT NULL AFTER `kyc_status`,
ADD COLUMN `withdrawal_blocked` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`,
ADD COLUMN `withdrawal_block_reason` VARCHAR(255) DEFAULT NULL AFTER `withdrawal_blocked`,
ADD COLUMN `total_withdrawn` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `total_earnings`,
ADD COLUMN `total_tax_deducted` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `total_withdrawn`,
ADD COLUMN `last_withdrawal_date` TIMESTAMP NULL DEFAULT NULL AFTER `total_tax_deducted`,
ADD COLUMN `referral_earnings` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `total_earnings`;

-- ======================================================
-- 7. UPDATE MATCHES TABLE (Add Turn Timer)
-- ======================================================

ALTER TABLE `matches` 
ADD COLUMN `turn_started_at` TIMESTAMP NULL DEFAULT NULL AFTER `current_turn_id`,
ADD COLUMN `turn_timeout_seconds` INT(11) NOT NULL DEFAULT 15 AFTER `turn_started_at`,
ADD COLUMN `consecutive_skips` INT(11) NOT NULL DEFAULT 0 AFTER `turn_number`,
ADD COLUMN `max_skips_allowed` INT(11) NOT NULL DEFAULT 3 AFTER `consecutive_skips`,
ADD COLUMN `last_activity_at` TIMESTAMP NULL DEFAULT NULL AFTER `updated_at`,
ADD COLUMN `tds_deducted` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `winning_amount`;

-- ======================================================
-- 8. CREATE TDS LOG TABLE
-- ======================================================

CREATE TABLE IF NOT EXISTS `tds_transactions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `match_id` INT(11) NOT NULL,
    `winning_amount` DECIMAL(12,2) NOT NULL,
    `tds_rate` DECIMAL(5,2) NOT NULL DEFAULT 30.00,
    `tds_amount` DECIMAL(12,2) NOT NULL,
    `net_amount` DECIMAL(12,2) NOT NULL,
    `financial_year` VARCHAR(20) NOT NULL,
    `threshold_exceeded` TINYINT(1) NOT NULL DEFAULT 0,
    `pan_available` TINYINT(1) NOT NULL DEFAULT 0,
    `tds_deducted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_match_id` (`match_id`),
    KEY `idx_financial_year` (`financial_year`),
    CONSTRAINT `fk_tds_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_tds_match` FOREIGN KEY (`match_id`) 
        REFERENCES `matches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- 9. INSERT DEFAULT SYSTEM SETTINGS
-- ======================================================

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_group`, `setting_type`, `description`) VALUES
('platform_commission', '15', 'financial', 'integer', 'Platform commission percentage on entry fees'),
('min_deposit_amount', '10', 'financial', 'decimal', 'Minimum deposit amount in INR'),
('max_deposit_amount', '100000', 'financial', 'decimal', 'Maximum deposit amount in INR'),
('min_withdrawal_amount', '100', 'financial', 'decimal', 'Minimum withdrawal amount in INR'),
('max_withdrawal_amount', '50000', 'financial', 'decimal', 'Maximum withdrawal amount in INR per day'),
('tds_threshold', '10000', 'financial', 'decimal', 'TDS threshold amount in INR per financial year'),
('tds_rate', '30', 'financial', 'integer', 'TDS rate percentage on net winnings above threshold'),
('maintenance_mode', '0', 'system', 'boolean', 'Enable maintenance mode (0=off, 1=on)'),
('maintenance_message', 'We are currently performing scheduled maintenance. Please check back later.', 'system', 'text', 'Maintenance mode message'),
('turn_timeout_seconds', '15', 'gameplay', 'integer', 'Turn timeout in seconds'),
('max_skips_allowed', '3', 'gameplay', 'integer', 'Maximum consecutive skips before auto-loss'),
('min_players_for_match', '2', 'gameplay', 'integer', 'Minimum players required to start a match'),
('max_players_for_match', '4', 'gameplay', 'integer', 'Maximum players allowed per match'),
('referral_bonus_amount', '50', 'referral', 'decimal', 'Referral bonus amount in INR'),
('max_referral_bonus_per_user', '5000', 'referral', 'decimal', 'Maximum referral bonus per user'),
('allow_withdrawals', '1', 'withdrawal', 'boolean', 'Allow withdrawal requests (0=no, 1=yes)'),
('auto_approve_withdrawals', '0', 'withdrawal', 'boolean', 'Auto-approve withdrawal requests (0=no, 1=yes)'),
('kyc_required_for_withdrawal', '1', 'kyc', 'boolean', 'Require KYC verification before withdrawal'),
('max_withdrawals_per_day', '3', 'withdrawal', 'integer', 'Maximum number of withdrawals per day per user'),
('support_email', 'support@yourdomain.com', 'system', 'string', 'Support email address');

-- ======================================================
-- 10. ALTER TABLE: Add is_admin column if not exists
-- ======================================================

ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `is_admin` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_verified`;

-- ======================================================
-- 11. CREATE MAINTENANCE LOG TABLE
-- ======================================================

CREATE TABLE IF NOT EXISTS `maintenance_logs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `action` VARCHAR(100) NOT NULL,
    `details` TEXT DEFAULT NULL,
    `admin_id` INT(11) DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_action` (`action`),
    KEY `idx_admin_id` (`admin_id`),
    KEY `idx_created_at` (`created_at`),
    CONSTRAINT `fk_maintenance_admin` FOREIGN KEY (`admin_id`) 
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- 12. CREATE MATCH_SNAPSHOTS TABLE (For Dispute Resolution)
-- ======================================================

CREATE TABLE IF NOT EXISTS `match_snapshots` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `match_id` INT(11) NOT NULL,
    `turn_number` INT(11) NOT NULL,
    `snapshot_data` JSON NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_match_id` (`match_id`),
    KEY `idx_turn_number` (`turn_number`),
    CONSTRAINT `fk_snapshot_match` FOREIGN KEY (`match_id`) 
        REFERENCES `matches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- End of Database Schema Updates
-- ======================================================
