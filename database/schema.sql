CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `companies` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(180) NOT NULL,
  `sector` VARCHAR(120) NULL,
  `phone` VARCHAR(50) NULL,
  `whatsapp` VARCHAR(50) NULL,
  `email` VARCHAR(180) NULL,
  `website` VARCHAR(255) NULL,
  `instagram` VARCHAR(255) NULL,
  `facebook` VARCHAR(255) NULL,
  `linkedin` VARCHAR(255) NULL,
  `address` TEXT NULL,
  `district` VARCHAR(120) NULL,
  `city` VARCHAR(120) DEFAULT 'Konya',
  `source` VARCHAR(100) NULL,
  `notes` TEXT NULL,
  `google_place_id` VARCHAR(255) NULL,
  `enrichment_status` VARCHAR(50) NULL,
  `last_enriched_at` DATETIME NULL,
  `status` ENUM('new', 'contacted', 'replied', 'proposal', 'customer', 'negative') NOT NULL DEFAULT 'new',
  `first_contact_at` DATETIME NULL,
  `last_contact_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX (`name`),
  INDEX (`sector`),
  INDEX (`status`),
  INDEX (`city`),
  INDEX (`district`),
  INDEX (`phone`),
  INDEX (`website`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `client_message_id` VARCHAR(64) NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`company_id`),
  INDEX (`type`),
  INDEX (`direction`),
  INDEX (`outcome`),
  INDEX (`contacted_at`),
  UNIQUE KEY `uq_communications_client_message_id` (`client_message_id`),
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

