-- ======================================================
-- LUDO TOURNAMENT DATABASE SCHEMA
-- Skill-based Gaming Platform
-- Version: 1.0.0
-- ======================================================

-- Set strict mode for production
SET SQL_MODE = "STRICT_ALL_TABLES";
SET FOREIGN_KEY_CHECKS = 1;

-- ======================================================
-- TABLE: users
-- Stores all player accounts and authentication data
-- ======================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `mobile` VARCHAR(15) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `wallet_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `refer_code` VARCHAR(20) NOT NULL,
    `referred_by` INT(11) DEFAULT NULL,
    `total_matches_played` INT(11) NOT NULL DEFAULT 0,
    `total_matches_won` INT(11) NOT NULL DEFAULT 0,
    `total_earnings` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `elo_rating` INT(11) NOT NULL DEFAULT 1200,
    `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_login` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`),
    UNIQUE KEY `uk_mobile` (`mobile`),
    UNIQUE KEY `uk_refer_code` (`refer_code`),
    KEY `idx_referred_by` (`referred_by`),
    KEY `idx_elo_rating` (`elo_rating`),
    KEY `idx_is_active` (`is_active`),
    CONSTRAINT `fk_users_referred_by` FOREIGN KEY (`referred_by`) 
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- TABLE: tournaments
-- Manages tournament instances and their configurations
-- ======================================================
CREATE TABLE IF NOT EXISTS `tournaments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `tournament_code` VARCHAR(20) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `entry_fee` DECIMAL(10,2) NOT NULL,
    `prize_pool` DECIMAL(12,2) NOT NULL,
    `platform_fee` DECIMAL(10,2) NOT NULL,
    `max_players` INT(11) NOT NULL DEFAULT 4,
    `min_players` INT(11) NOT NULL DEFAULT 2,
    `current_players` INT(11) NOT NULL DEFAULT 0,
    `status` ENUM('scheduled', 'active', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled',
    `start_time` TIMESTAMP NULL DEFAULT NULL,
    `end_time` TIMESTAMP NULL DEFAULT NULL,
    `winner_id` INT(11) DEFAULT NULL,
    `winner_amount` DECIMAL(12,2) DEFAULT NULL,
    `created_by` INT(11) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_tournament_code` (`tournament_code`),
    KEY `idx_status` (`status`),
    KEY `idx_start_time` (`start_time`),
    KEY `idx_winner_id` (`winner_id`),
    KEY `idx_created_by` (`created_by`),
    KEY `idx_current_players` (`current_players`),
    CONSTRAINT `fk_tournaments_winner` FOREIGN KEY (`winner_id`) 
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_tournaments_created_by` FOREIGN KEY (`created_by`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- TABLE: matches
-- Individual match instances within tournaments
-- ======================================================
CREATE TABLE IF NOT EXISTS `matches` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `tournament_id` INT(11) NOT NULL,
    `room_code` VARCHAR(6) NOT NULL,
    `entry_fee` DECIMAL(10,2) NOT NULL,
    `prize_pool` DECIMAL(12,2) NOT NULL,
    `platform_fee` DECIMAL(10,2) NOT NULL,
    `player1_id` INT(11) NOT NULL,
    `player2_id` INT(11) DEFAULT NULL,
    `player3_id` INT(11) DEFAULT NULL,
    `player4_id` INT(11) DEFAULT NULL,
    `player1_name` VARCHAR(50) NOT NULL,
    `player2_name` VARCHAR(50) DEFAULT NULL,
    `player3_name` VARCHAR(50) DEFAULT NULL,
    `player4_name` VARCHAR(50) DEFAULT NULL,
    `status` ENUM('waiting', 'ready', 'playing', 'paused', 'completed', 'cancelled') NOT NULL DEFAULT 'waiting',
    `current_turn_id` INT(11) DEFAULT NULL,
    `dice_value` TINYINT(4) NOT NULL DEFAULT 1,
    `dice_rolled_by` INT(11) DEFAULT NULL,
    `last_dice_roll_time` TIMESTAMP NULL DEFAULT NULL,
    `p1_token1` INT(11) NOT NULL DEFAULT 0,
    `p1_token2` INT(11) NOT NULL DEFAULT 0,
    `p1_token3` INT(11) NOT NULL DEFAULT 0,
    `p1_token4` INT(11) NOT NULL DEFAULT 0,
    `p1_home_count` INT(11) NOT NULL DEFAULT 0,
    `p2_token1` INT(11) NOT NULL DEFAULT 0,
    `p2_token2` INT(11) NOT NULL DEFAULT 0,
    `p2_token3` INT(11) NOT NULL DEFAULT 0,
    `p2_token4` INT(11) NOT NULL DEFAULT 0,
    `p2_home_count` INT(11) NOT NULL DEFAULT 0,
    `p3_token1` INT(11) NOT NULL DEFAULT 0,
    `p3_token2` INT(11) NOT NULL DEFAULT 0,
    `p3_token3` INT(11) NOT NULL DEFAULT 0,
    `p3_token4` INT(11) NOT NULL DEFAULT 0,
    `p3_home_count` INT(11) NOT NULL DEFAULT 0,
    `p4_token1` INT(11) NOT NULL DEFAULT 0,
    `p4_token2` INT(11) NOT NULL DEFAULT 0,
    `p4_token3` INT(11) NOT NULL DEFAULT 0,
    `p4_token4` INT(11) NOT NULL DEFAULT 0,
    `p4_home_count` INT(11) NOT NULL DEFAULT 0,
    `winner_id` INT(11) DEFAULT NULL,
    `winner_name` VARCHAR(50) DEFAULT NULL,
    `winning_amount` DECIMAL(12,2) DEFAULT NULL,
    `move_history` TEXT DEFAULT NULL,
    `turn_number` INT(11) NOT NULL DEFAULT 0,
    `max_turns` INT(11) NOT NULL DEFAULT 100,
    `started_at` TIMESTAMP NULL DEFAULT NULL,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_room_code` (`room_code`),
    KEY `idx_tournament_id` (`tournament_id`),
    KEY `idx_status` (`status`),
    KEY `idx_player1_id` (`player1_id`),
    KEY `idx_player2_id` (`player2_id`),
    KEY `idx_player3_id` (`player3_id`),
    KEY `idx_player4_id` (`player4_id`),
    KEY `idx_current_turn_id` (`current_turn_id`),
    KEY `idx_winner_id` (`winner_id`),
    KEY `idx_created_at` (`created_at`),
    CONSTRAINT `fk_matches_tournament` FOREIGN KEY (`tournament_id`) 
        REFERENCES `tournaments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_matches_player1` FOREIGN KEY (`player1_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_matches_player2` FOREIGN KEY (`player2_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_matches_player3` FOREIGN KEY (`player3_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_matches_player4` FOREIGN KEY (`player4_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_matches_current_turn` FOREIGN KEY (`current_turn_id`) 
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_matches_winner` FOREIGN KEY (`winner_id`) 
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- TABLE: transactions
-- All financial transactions (wallet credits/debits)
-- ======================================================
CREATE TABLE IF NOT EXISTS `transactions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `tournament_id` INT(11) DEFAULT NULL,
    `match_id` INT(11) DEFAULT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `type` ENUM('credit', 'debit') NOT NULL,
    `source` ENUM('deposit', 'match_fee', 'match_win', 'withdrawal', 'bonus', 'refund') NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `order_id` VARCHAR(100) NOT NULL,
    `payment_gateway` VARCHAR(50) DEFAULT NULL,
    `gateway_transaction_id` VARCHAR(100) DEFAULT NULL,
    `status` ENUM('pending', 'processing', 'success', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    `balance_before` DECIMAL(12,2) NOT NULL,
    `balance_after` DECIMAL(12,2) NOT NULL,
    `metadata` JSON DEFAULT NULL,
    `processed_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_order_id` (`order_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_tournament_id` (`tournament_id`),
    KEY `idx_match_id` (`match_id`),
    KEY `idx_status` (`status`),
    KEY `idx_type` (`type`),
    KEY `idx_source` (`source`),
    KEY `idx_created_at` (`created_at`),
    CONSTRAINT `fk_transactions_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_transactions_tournament` FOREIGN KEY (`tournament_id`) 
        REFERENCES `tournaments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_transactions_match` FOREIGN KEY (`match_id`) 
        REFERENCES `matches` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- TABLE: game_actions
-- Audit trail for all in-game actions (for dispute resolution)
-- ======================================================
CREATE TABLE IF NOT EXISTS `game_actions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `match_id` INT(11) NOT NULL,
    `user_id` INT(11) NOT NULL,
    `action_type` ENUM('roll_dice', 'move_token', 'capture', 'reach_home', 'win') NOT NULL,
    `dice_value` TINYINT(4) DEFAULT NULL,
    `token_number` TINYINT(4) DEFAULT NULL,
    `from_position` INT(11) DEFAULT NULL,
    `to_position` INT(11) DEFAULT NULL,
    `opponent_captured` INT(11) DEFAULT NULL,
    `game_state_snapshot` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_match_id` (`match_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_action_type` (`action_type`),
    KEY `idx_created_at` (`created_at`),
    CONSTRAINT `fk_game_actions_match` FOREIGN KEY (`match_id`) 
        REFERENCES `matches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_game_actions_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- TABLE: leaderboard
-- ELO-based ranking and performance metrics
-- ======================================================
CREATE TABLE IF NOT EXISTS `leaderboard` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `username` VARCHAR(50) NOT NULL,
    `elo_rating` INT(11) NOT NULL DEFAULT 1200,
    `matches_played` INT(11) NOT NULL DEFAULT 0,
    `matches_won` INT(11) NOT NULL DEFAULT 0,
    `matches_lost` INT(11) NOT NULL DEFAULT 0,
    `win_streak` INT(11) NOT NULL DEFAULT 0,
    `current_streak` INT(11) NOT NULL DEFAULT 0,
    `total_earnings` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `tournaments_won` INT(11) NOT NULL DEFAULT 0,
    `rank_position` INT(11) DEFAULT NULL,
    `last_updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_id` (`user_id`),
    KEY `idx_elo_rating` (`elo_rating`),
    KEY `idx_rank_position` (`rank_position`),
    CONSTRAINT `fk_leaderboard_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- TABLE: sessions
-- Active user sessions with device tracking
-- ======================================================
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
    CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- TABLE: referral_bonuses
-- Track referral bonuses and rewards
-- ======================================================
CREATE TABLE IF NOT EXISTS `referral_bonuses` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `referrer_id` INT(11) NOT NULL,
    `referred_id` INT(11) NOT NULL,
    `bonus_amount` DECIMAL(10,2) NOT NULL,
    `status` ENUM('pending', 'credited', 'failed') NOT NULL DEFAULT 'pending',
    `credited_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_referred_id` (`referred_id`),
    KEY `idx_referrer_id` (`referrer_id`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_referral_bonuses_referrer` FOREIGN KEY (`referrer_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_referral_bonuses_referred` FOREIGN KEY (`referred_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- TABLE: banned_users
-- Responsible gaming and violation management
-- ======================================================
CREATE TABLE IF NOT EXISTS `banned_users` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `banned_by` INT(11) NOT NULL,
    `reason` VARCHAR(255) NOT NULL,
    `ban_type` ENUM('temporary', 'permanent') NOT NULL,
    `duration_hours` INT(11) DEFAULT NULL,
    `banned_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NULL DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `appeal_status` ENUM('none', 'pending', 'approved', 'rejected') NOT NULL DEFAULT 'none',
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_is_active` (`is_active`),
    CONSTRAINT `fk_banned_users_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_banned_users_banned_by` FOREIGN KEY (`banned_by`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- INDEXES for performance optimization
-- ======================================================
CREATE INDEX idx_matches_room_status ON matches(room_code, status);
CREATE INDEX idx_matches_player_turn ON matches(player1_id, player2_id, status);
CREATE INDEX idx_transactions_user_status ON transactions(user_id, status);
CREATE INDEX idx_transactions_date ON transactions(created_at DESC);
CREATE INDEX idx_game_actions_match_time ON game_actions(match_id, created_at DESC);
CREATE INDEX idx_leaderboard_rank ON leaderboard(rank_position);
CREATE INDEX idx_users_mobile_verified ON users(mobile, is_verified);

-- ======================================================
-- TRIGGERS: Auto-update leaderboard on match completion
-- ======================================================
DELIMITER $$

CREATE TRIGGER after_match_complete
AFTER UPDATE ON matches
FOR EACH ROW
BEGIN
    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
        -- Update player statistics
        UPDATE users 
        SET 
            total_matches_played = total_matches_played + 1,
            total_earnings = total_earnings + COALESCE(NEW.winning_amount, 0)
        WHERE id = NEW.winner_id;
        
        -- Update leaderboard
        INSERT INTO leaderboard (user_id, username, elo_rating, matches_played, matches_won, total_earnings)
        VALUES (
            NEW.winner_id, 
            NEW.winner_name,
            1200,
            1,
            1,
            COALESCE(NEW.winning_amount, 0)
        )
        ON DUPLICATE KEY UPDATE
            elo_rating = elo_rating + 10,
            matches_played = matches_played + 1,
            matches_won = matches_won + 1,
            total_earnings = total_earnings + COALESCE(NEW.winning_amount, 0);
    END IF;
END$$

DELIMITER ;

-- ======================================================
-- VIEW: Active matches with player details
-- ======================================================
CREATE OR REPLACE VIEW vw_active_matches AS
SELECT 
    m.id AS match_id,
    m.room_code,
    m.status,
    m.entry_fee,
    m.prize_pool,
    m.current_turn_id,
    m.dice_value,
    m.turn_number,
    m.started_at,
    m.created_at,
    u1.username AS player1_username,
    u2.username AS player2_username,
    u3.username AS player3_username,
    u4.username AS player4_username,
    t.tournament_code,
    t.name AS tournament_name
FROM matches m
LEFT JOIN users u1 ON m.player1_id = u1.id
LEFT JOIN users u2 ON m.player2_id = u2.id
LEFT JOIN users u3 ON m.player3_id = u3.id
LEFT JOIN users u4 ON m.player4_id = u4.id
LEFT JOIN tournaments t ON m.tournament_id = t.id
WHERE m.status IN ('waiting', 'ready', 'playing', 'paused');

-- ======================================================
-- VIEW: Tournament leaderboard
-- ======================================================
CREATE OR REPLACE VIEW vw_tournament_leaderboard AS
SELECT 
    u.id AS user_id,
    u.username,
    u.elo_rating,
    u.total_matches_played,
    u.total_matches_won,
    u.total_earnings,
    ROUND((u.total_matches_won / NULLIF(u.total_matches_played, 0)) * 100, 2) AS win_rate,
    RANK() OVER (ORDER BY u.elo_rating DESC) AS rank_position
FROM users u
WHERE u.is_active = 1
ORDER BY u.elo_rating DESC;

-- ======================================================
-- Initial Admin User (password: Admin@123)
-- Note: Password hash is for demonstration only
-- ======================================================
INSERT INTO `users` (
    `username`, 
    `mobile`, 
    `password_hash`, 
    `email`, 
    `wallet_balance`, 
    `refer_code`, 
    `is_verified`, 
    `is_active`
) VALUES (
    'admin',
    '9999999999',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin@ludo.com',
    10000.00,
    'ADMIN001',
    1,
    1
) ON DUPLICATE KEY UPDATE username = username;

-- ======================================================
-- End of Database Schema
-- ======================================================
