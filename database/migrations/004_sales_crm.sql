-- Migration 004: Sales CRM (Communications and Follow-ups)
-- MariaDB 10.5 compatible

CREATE TABLE IF NOT EXISTS `communications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `type` ENUM(
      'whatsapp',
      'phone',
      'email',
      'instagram',
      'linkedin',
      'meeting',
      'note',
      'other'
  ) NOT NULL,
  `direction` ENUM(
      'outbound',
      'inbound',
      'internal'
  ) NOT NULL DEFAULT 'outbound',
  `subject` VARCHAR(255) NULL,
  `message` TEXT NULL,
  `outcome` ENUM(
      'sent',
      'no_answer',
      'replied',
      'interested',
      'not_interested',
      'callback',
      'proposal_requested',
      'proposal_sent',
      'meeting_scheduled',
      'customer',
      'other'
  ) NULL,
  `contacted_at` DATETIME NOT NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`company_id`),
  INDEX (`type`),
  INDEX (`direction`),
  INDEX (`outcome`),
  INDEX (`contacted_at`),
  CONSTRAINT `fk_communications_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_communications_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `follow_ups` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `notes` TEXT NULL,
  `due_at` DATETIME NOT NULL,
  `status` ENUM(
      'pending',
      'completed',
      'cancelled'
  ) NOT NULL DEFAULT 'pending',
  `priority` ENUM(
      'low',
      'normal',
      'high'
  ) NOT NULL DEFAULT 'normal',
  `communication_id` INT NULL,
  `completed_at` DATETIME NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`company_id`),
  INDEX (`due_at`),
  INDEX (`status`),
  INDEX (`priority`),
  CONSTRAINT `fk_followups_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_followups_communication` FOREIGN KEY (`communication_id`) REFERENCES `communications` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_followups_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
