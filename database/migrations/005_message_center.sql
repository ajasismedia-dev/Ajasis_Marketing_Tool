-- Migration 005: Message Center (Templates, Outbound Messages, and Idempotency)
-- MariaDB 10.5 compatible

-- 1. Message Templates Table
CREATE TABLE IF NOT EXISTS `message_templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `channel` ENUM('whatsapp', 'email') NOT NULL,
  `subject` VARCHAR(255) NULL,
  `body` TEXT NOT NULL,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_templates_channel` (`channel`),
  INDEX `idx_templates_is_active` (`is_active`),
  INDEX `idx_templates_is_default` (`is_default`),
  CONSTRAINT `fk_templates_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Add client_message_id to communications for idempotency
ALTER TABLE `communications` ADD COLUMN IF NOT EXISTS `client_message_id` VARCHAR(64) NULL;
CREATE UNIQUE INDEX IF NOT EXISTS `uq_communications_client_message_id` ON `communications` (`client_message_id`);

-- 3. Outbound Messages Table (Atomic dispatch and retry guard)
CREATE TABLE IF NOT EXISTS `outbound_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `channel` ENUM('whatsapp', 'email') NOT NULL,
  `client_message_id` VARCHAR(64) NOT NULL UNIQUE,
  `status` ENUM('pending', 'sent', 'failed', 'logged') NOT NULL,
  `subject` VARCHAR(255) NULL,
  `body_hash` CHAR(64) NOT NULL,
  `communication_id` INT NULL,
  `error_message` VARCHAR(500) NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `sent_at` DATETIME NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_outbound_company` (`company_id`),
  INDEX `idx_outbound_status` (`status`),
  INDEX `idx_outbound_channel` (`channel`),
  CONSTRAINT `fk_outbound_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_outbound_communication` FOREIGN KEY (`communication_id`) REFERENCES `communications` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_outbound_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
