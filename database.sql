-- =====================================================================
-- Smart Anti-Theft Application (SATA) Database Schema & Initial Data
-- Engine: MySQL 5.7+ / MySQL 8.0+ / MariaDB 10.3+
-- Character Set: utf8mb4 / Collation: utf8mb4_unicode_ci
-- How to import:
--   mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS sata CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
--   mysql -u root -p sata < database.sql
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- ---------------------------------------------------------------------
-- Table: admins
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `role` enum('SUPER_ADMIN','SECURITY_ANALYST','SUPPORT_AGENT','BILLING_ADMIN') NOT NULL DEFAULT 'SUPER_ADMIN',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `custom_permissions` json DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: admin_audit_logs
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `admin_audit_logs`;
CREATE TABLE `admin_audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` bigint(20) UNSIGNED NOT NULL,
  `action` varchar(255) NOT NULL,
  `module` varchar(100) NOT NULL,
  `target_type` varchar(255) DEFAULT NULL,
  `target_id` bigint(20) UNSIGNED DEFAULT NULL,
  `before_payload` json DEFAULT NULL,
  `after_payload` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `audit_admin_id_idx` (`admin_id`),
  KEY `audit_action_idx` (`action`),
  CONSTRAINT `fk_audit_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: activation_plans
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `activation_plans`;
CREATE TABLE `activation_plans` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(50) NOT NULL UNIQUE,
  `duration_days` int(11) NOT NULL,
  `price` decimal(8,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `description` text DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'ACTIVE',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `plan_code_idx` (`code`),
  KEY `plan_active_idx` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: activation_code_batches
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `activation_code_batches`;
CREATE TABLE `activation_code_batches` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_number` varchar(50) NOT NULL UNIQUE,
  `activation_plan_id` bigint(20) UNSIGNED NOT NULL,
  `created_by_admin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `batch_plan_idx` (`activation_plan_id`),
  CONSTRAINT `fk_batch_plan` FOREIGN KEY (`activation_plan_id`) REFERENCES `activation_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: users
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT 'User',
  `phone_number` varchar(50) NOT NULL UNIQUE,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `status` enum('PENDING','ACTIVE','SUSPENDED','DISABLED') NOT NULL DEFAULT 'ACTIVE',
  `current_subscription_id` bigint(20) UNSIGNED DEFAULT NULL,
  `active_subscription_id` bigint(20) UNSIGNED DEFAULT NULL,
  `current_device_id` bigint(20) UNSIGNED DEFAULT NULL,
  `phone_verified_at` timestamp NULL DEFAULT NULL,
  `mobile_verified_at` timestamp NULL DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `last_active_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `users_phone_idx` (`phone_number`),
  KEY `users_status_idx` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: user_devices
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `user_devices`;
CREATE TABLE `user_devices` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `device_uuid` varchar(255) NOT NULL UNIQUE,
  `device_name` varchar(255) DEFAULT 'Smartphone',
  `device_model` varchar(255) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `platform` varchar(50) NOT NULL DEFAULT 'android',
  `os_version` varchar(50) DEFAULT NULL,
  `app_version` varchar(50) DEFAULT NULL,
  `fcm_token` text DEFAULT NULL,
  `status` enum('ACTIVE','REVOKED','OFFLINE') NOT NULL DEFAULT 'ACTIVE',
  `is_protection_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `device_management_mode` varchar(50) DEFAULT 'DEVICE_ADMIN',
  `battery_level` int(11) DEFAULT 100,
  `is_charging` tinyint(1) DEFAULT 0,
  `network_type` varchar(50) DEFAULT 'Wi-Fi',
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `dev_user_idx` (`user_id`),
  KEY `dev_uuid_idx` (`device_uuid`),
  CONSTRAINT `fk_device_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: activation_codes
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `activation_codes`;
CREATE TABLE `activation_codes` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `serial_number` varchar(50) DEFAULT NULL,
  `code` varchar(255) NOT NULL UNIQUE,
  `code_hash` varchar(255) DEFAULT NULL,
  `code_last4` varchar(10) DEFAULT NULL,
  `activation_plan_id` bigint(20) UNSIGNED NOT NULL,
  `plan_id` bigint(20) UNSIGNED DEFAULT NULL,
  `batch_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('AVAILABLE','ACTIVATED','REVOKED','EXPIRED') NOT NULL DEFAULT 'AVAILABLE',
  `is_redeemed` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `redeemed_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `activated_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `activated_device_id` bigint(20) UNSIGNED DEFAULT NULL,
  `redeemed_at` timestamp NULL DEFAULT NULL,
  `activated_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_by_admin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `generated_by_admin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `codes_code_idx` (`code`),
  KEY `codes_status_idx` (`status`),
  KEY `codes_plan_idx` (`activation_plan_id`),
  CONSTRAINT `fk_code_plan` FOREIGN KEY (`activation_plan_id`) REFERENCES `activation_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: subscriptions
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `subscriptions`;
CREATE TABLE `subscriptions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `activation_plan_id` bigint(20) UNSIGNED NOT NULL,
  `plan_id` bigint(20) UNSIGNED DEFAULT NULL,
  `activation_code_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('ACTIVE','EXPIRED','CANCELLED','SUSPENDED') NOT NULL DEFAULT 'ACTIVE',
  `starts_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `payment_reference` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sub_user_idx` (`user_id`),
  KEY `sub_plan_idx` (`activation_plan_id`),
  KEY `sub_status_idx` (`status`),
  CONSTRAINT `fk_sub_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sub_plan` FOREIGN KEY (`activation_plan_id`) REFERENCES `activation_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: trusted_users
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `trusted_users`;
CREATE TABLE `trusted_users` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `slot_number` int(11) NOT NULL DEFAULT 1,
  `name` varchar(255) NOT NULL,
  `phone_number` varchar(50) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `relationship` varchar(100) DEFAULT 'Contact',
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `trusted_user_idx` (`user_id`),
  CONSTRAINT `fk_trusted_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: trusted_user_verifications
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `trusted_user_verifications`;
CREATE TABLE `trusted_user_verifications` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `trusted_user_id` bigint(20) UNSIGNED NOT NULL,
  `token` varchar(64) DEFAULT NULL UNIQUE,
  `verification_code` varchar(30) DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verified_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tuv_trusted_user_idx` (`trusted_user_id`),
  KEY `tuv_token_idx` (`token`),
  KEY `tuv_verification_code_idx` (`verification_code`),
  KEY `tuv_is_verified_idx` (`is_verified`),
  KEY `tuv_expires_at_idx` (`expires_at`),
  CONSTRAINT `fk_tuv_trusted_user` FOREIGN KEY (`trusted_user_id`) REFERENCES `trusted_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: security_events
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `security_events`;
CREATE TABLE `security_events` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_uuid` varchar(255) NOT NULL UNIQUE,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `device_id` bigint(20) UNSIGNED NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `severity` enum('LOW','MEDIUM','HIGH','CRITICAL') NOT NULL DEFAULT 'HIGH',
  `metadata` json DEFAULT NULL,
  `is_reviewed` tinyint(1) NOT NULL DEFAULT 0,
  `reviewed_by_admin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `evt_user_idx` (`user_id`),
  KEY `evt_device_idx` (`device_id`),
  KEY `evt_type_idx` (`event_type`),
  CONSTRAINT `fk_evt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_evt_dev` FOREIGN KEY (`device_id`) REFERENCES `user_devices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: security_evidence
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `security_evidence`;
CREATE TABLE `security_evidence` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `security_event_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `device_id` bigint(20) UNSIGNED NOT NULL,
  `evidence_type` enum('FRONT_CAMERA_PHOTO','BACK_CAMERA_PHOTO','AUDIO_RECORDING','VIDEO_CLIP') NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size_bytes` bigint(20) DEFAULT 0,
  `mime_type` varchar(100) DEFAULT 'image/jpeg',
  `captured_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `evid_event_idx` (`security_event_id`),
  CONSTRAINT `fk_evid_event` FOREIGN KEY (`security_event_id`) REFERENCES `security_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: security_event_locations
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `security_event_locations`;
CREATE TABLE `security_event_locations` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `security_event_id` bigint(20) UNSIGNED NOT NULL,
  `latitude` decimal(10, 7) NOT NULL,
  `longitude` decimal(10, 7) NOT NULL,
  `accuracy` decimal(8, 2) DEFAULT NULL,
  `altitude` decimal(8, 2) DEFAULT NULL,
  `speed` decimal(8, 2) DEFAULT NULL,
  `heading` decimal(8, 2) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `recorded_at` timestamp NULL DEFAULT NULL,
  `captured_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sel_security_event_idx` (`security_event_id`),
  KEY `sel_latitude_idx` (`latitude`),
  KEY `sel_longitude_idx` (`longitude`),
  KEY `sel_recorded_at_idx` (`recorded_at`),
  KEY `sel_captured_at_idx` (`captured_at`),
  KEY `sel_event_rec_idx` (`security_event_id`, `recorded_at`),
  CONSTRAINT `fk_sel_security_event` FOREIGN KEY (`security_event_id`) REFERENCES `security_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: security_commands
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `security_commands`;
CREATE TABLE `security_commands` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `command_uuid` varchar(255) NOT NULL UNIQUE,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `device_id` bigint(20) UNSIGNED NOT NULL,
  `command_type` varchar(100) NOT NULL,
  `status` enum('PENDING','SENT','ACKNOWLEDGED','EXECUTED','FAILED') NOT NULL DEFAULT 'PENDING',
  `reason` text DEFAULT NULL,
  `created_by_admin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `executed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `cmd_user_idx` (`user_id`),
  KEY `cmd_dev_idx` (`device_id`),
  CONSTRAINT `fk_cmd_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cmd_dev` FOREIGN KEY (`device_id`) REFERENCES `user_devices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: security_command_logs
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `security_command_logs`;
CREATE TABLE `security_command_logs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `security_command_id` bigint(20) UNSIGNED NOT NULL,
  `status_before` varchar(50) DEFAULT NULL,
  `status_after` varchar(50) DEFAULT NULL,
  `status_from` varchar(50) DEFAULT NULL,
  `status_to` varchar(50) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `recorded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `scl_security_command_idx` (`security_command_id`),
  KEY `scl_status_after_idx` (`status_after`),
  KEY `scl_status_to_idx` (`status_to`),
  KEY `scl_recorded_at_idx` (`recorded_at`),
  KEY `scl_cmd_rec_idx` (`security_command_id`, `recorded_at`),
  CONSTRAINT `fk_scl_security_command` FOREIGN KEY (`security_command_id`) REFERENCES `security_commands` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: live_location_sessions
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `live_location_sessions`;
CREATE TABLE `live_location_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `device_id` bigint(20) UNSIGNED NOT NULL,
  `session_id` varchar(64) DEFAULT NULL UNIQUE,
  `session_token` varchar(64) DEFAULT NULL UNIQUE,
  `short_token` varchar(64) DEFAULT NULL UNIQUE,
  `status` varchar(30) NOT NULL DEFAULT 'ACTIVE',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `started_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL DEFAULT NULL,
  `stopped_at` timestamp NULL DEFAULT NULL,
  `ended_at` timestamp NULL DEFAULT NULL,
  `last_latitude` decimal(10, 7) DEFAULT NULL,
  `last_longitude` decimal(10, 7) DEFAULT NULL,
  `last_accuracy` decimal(8, 2) DEFAULT NULL,
  `last_altitude` decimal(8, 2) DEFAULT NULL,
  `last_speed` decimal(8, 2) DEFAULT NULL,
  `last_heading` decimal(8, 2) DEFAULT NULL,
  `last_updated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `lls_user_idx` (`user_id`),
  KEY `lls_device_idx` (`device_id`),
  KEY `lls_session_idx` (`session_id`),
  KEY `lls_token_idx` (`session_token`),
  KEY `lls_short_token_idx` (`short_token`),
  KEY `lls_status_idx` (`status`),
  KEY `lls_active_idx` (`is_active`),
  KEY `lls_started_idx` (`started_at`),
  KEY `lls_user_active_idx` (`user_id`, `is_active`),
  CONSTRAINT `fk_lls_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lls_device` FOREIGN KEY (`device_id`) REFERENCES `user_devices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: notifications
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'ALERT',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: otp_verifications
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `otp_verifications`;
CREATE TABLE `otp_verifications` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `phone_number` varchar(50) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `otp_code` varchar(10) NOT NULL,
  `purpose` varchar(50) NOT NULL DEFAULT 'REGISTRATION',
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `expires_at` timestamp NOT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `otp_phone_idx` (`phone_number`),
  KEY `otp_email_idx` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: personal_access_tokens
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `personal_access_tokens`;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL UNIQUE,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tokenable_idx` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: sessions
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- INITIAL PRODUCTION SEED DATA
-- Default Credentials:
-- Admin Email:    admin@sata.security
-- Admin Password: password123
-- =====================================================================

-- 1. Super Admin (Password hash for "password123")
INSERT INTO `admins` (`id`, `name`, `email`, `password`, `role`, `is_active`, `created_at`, `updated_at`)
VALUES (
  1,
  'SATA Super Admin',
  'admin@sata.security',
  '$2y$12$6/7nF.P8Yp7a7q86G4jM.eP04q7lZqjN5z1zX2t9k8qB5w8h6Q1uG',
  'SUPER_ADMIN',
  1,
  NOW(),
  NOW()
) ON DUPLICATE KEY UPDATE `email` = `email`;

-- 2. Activation Plans
INSERT INTO `activation_plans` (`id`, `name`, `code`, `duration_days`, `price`, `currency`, `description`, `status`, `is_active`, `created_at`, `updated_at`)
VALUES
  (1, 'Monthly Shield', 'MONTHLY', 30, 9.99, 'USD', 'Full security coverage for 30 days.', 'ACTIVE', 1, NOW(), NOW()),
  (2, 'Quarterly Sentinel', 'QUARTERLY', 90, 24.99, 'USD', 'Extended anti-theft protection for 90 days.', 'ACTIVE', 1, NOW(), NOW()),
  (3, 'Half-Yearly Aegis', 'HALF_YEARLY', 180, 44.99, 'USD', 'Comprehensive monitoring for 180 days.', 'ACTIVE', 1, NOW(), NOW()),
  (4, 'Yearly Fortress', 'YEARLY', 365, 79.99, 'USD', 'Maximum continuous protection with annual renewal.', 'ACTIVE', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `code` = `code`;

-- 3. Activation Code Batch
INSERT INTO `activation_code_batches` (`id`, `batch_number`, `activation_plan_id`, `created_by_admin_id`, `quantity`, `notes`, `created_at`, `updated_at`)
VALUES (
  1,
  'BATCH-GYZ3UFGI',
  4,
  1,
  5,
  'Initial production test batch',
  NOW(),
  NOW()
) ON DUPLICATE KEY UPDATE `batch_number` = `batch_number`;

-- 4. Initial Activation Codes
INSERT INTO `activation_codes` (`id`, `serial_number`, `code`, `code_last4`, `activation_plan_id`, `plan_id`, `batch_id`, `status`, `is_redeemed`, `is_active`, `redeemed_by_user_id`, `redeemed_at`, `created_by_admin_id`, `created_at`, `updated_at`)
VALUES
  (1, 'SAT-000001', 'SAT-REK4-YMN6-NVTE', 'NVTE', 4, 4, 1, 'ACTIVATED', 1, 1, 1, NOW(), 1, NOW(), NOW()),
  (2, 'SAT-000002', 'SAT-CTZK-99C3-5XLB', '5XLB', 4, 4, 1, 'AVAILABLE', 0, 1, NULL, NULL, 1, NOW(), NOW()),
  (3, 'SAT-000003', 'SAT-ETTL-7CFP-GS79', 'GS79', 4, 4, 1, 'AVAILABLE', 0, 1, NULL, NULL, 1, NOW(), NOW()),
  (4, 'SAT-000004', 'SAT-9J6E-JDKQ-M6EN', 'M6EN', 4, 4, 1, 'AVAILABLE', 0, 1, NULL, NULL, 1, NOW(), NOW()),
  (5, 'SAT-000005', 'SAT-7CG5-96UQ-V7Z4', 'V7Z4', 4, 4, 1, 'AVAILABLE', 0, 1, NULL, NULL, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `code` = `code`;

-- 5. Complete Protected Users
INSERT INTO `users` (`id`, `name`, `phone_number`, `email`, `status`, `created_at`, `updated_at`)
VALUES
  (1, 'Alex Rivera', '+14155552671', 'alex.rivera@example.com', 'ACTIVE', NOW(), NOW()),
  (2, 'Sarah Connor', '+13105559102', 'sarah.connor@cyberdyne.org', 'ACTIVE', NOW(), NOW()),
  (3, 'Marcus Wright', '+12065558411', 'm.wright@skyline.io', 'ACTIVE', NOW(), NOW()),
  (4, 'David Vance', '+15125554901', 'david.vance@austinsec.com', 'PENDING_OTP', NOW(), NOW()),
  (5, 'Chloe Bennett', '+13125557722', 'chloe.b@chicago-law.com', 'AWAITING_ACTIVATION', NOW(), NOW()),
  (6, 'Victor Vance', '+17865551234', 'victor.vance@vicecity.net', 'DISABLED', NOW(), NOW())
ON DUPLICATE KEY UPDATE `phone_number` = VALUES(`phone_number`);

-- 6. Hardware Protected Devices
INSERT INTO `user_devices` (`id`, `user_id`, `device_uuid`, `device_name`, `device_model`, `model`, `platform`, `os_version`, `app_version`, `status`, `is_protection_enabled`, `last_seen_at`, `created_at`, `updated_at`)
VALUES
  (101, 1, 'b4a1c890-77ef-4d82-9901-d001a45e9981', 'Samsung Galaxy S24 Ultra', 'SM-S928B', 'SM-S928B', 'android', 'Android 14 (OneUI 6.1)', 'v2.4.1', 'ACTIVE', 1, NOW(), NOW(), NOW()),
  (102, 2, 'c881fa20-410a-4fa1-8201-9988220011bb', 'Google Pixel 8 Pro', 'Pixel 8 Pro', 'Pixel 8 Pro', 'android', 'Android 15', 'v2.4.1', 'ACTIVE', 1, NOW(), NOW(), NOW()),
  (103, 3, 'e7104b90-1120-4ee1-b011-aa99881122cc', 'Apple iPhone 15 Pro Max', 'A3106', 'A3106', 'ios', 'iOS 18.0', 'v2.4.0', 'ACTIVE', 1, NOW(), NOW(), NOW()),
  (104, 5, 'fa990011-3322-4411-8899-776655443322', 'Google Pixel 7a', 'Pixel 7a', 'Pixel 7a', 'android', 'Android 14', 'v2.4.1', 'ACTIVE', 0, NOW(), NOW(), NOW()),
  (105, 6, '00112233-4455-6677-8899-aabbccddeeff', 'OnePlus 11 5G', 'CPH2449', 'CPH2449', 'android', 'OxygenOS 14', 'v2.2.0', 'REVOKED', 0, NOW(), NOW(), NOW())
ON DUPLICATE KEY UPDATE `device_uuid` = VALUES(`device_uuid`);

-- Link users to devices
UPDATE `users` SET `current_device_id` = 101 WHERE `id` = 1;
UPDATE `users` SET `current_device_id` = 102 WHERE `id` = 2;
UPDATE `users` SET `current_device_id` = 103 WHERE `id` = 3;
UPDATE `users` SET `current_device_id` = 104 WHERE `id` = 5;
UPDATE `users` SET `current_device_id` = 105 WHERE `id` = 6;

-- 7. Trusted Emergency Users (Name, Mobile Phone, Email)
INSERT INTO `trusted_users` (`id`, `user_id`, `name`, `phone_number`, `email`, `relationship`, `is_verified`, `created_at`, `updated_at`)
VALUES
  (1, 1, 'Elena Rivera', '+14155559812', 'elena.rivera@gmail.com', 'Spouse', 1, NOW(), NOW()),
  (2, 1, 'David Chen', '+14155553344', 'd.chen@baycapital.com', 'Brother', 1, NOW(), NOW()),
  (3, 2, 'John Connor', '+13105559103', 'john.connor@cyberdyne.org', 'Son', 1, NOW(), NOW()),
  (4, 2, 'Kyle Reese', '+13105559800', 'kyle.reese@resistance.net', 'Partner', 1, NOW(), NOW()),
  (5, 3, 'Blair Williams', '+12065558999', 'blair.w@skyline.io', 'Sister', 1, NOW(), NOW()),
  (6, 4, 'Rachel Vance', '+15125554902', 'rachel.vance@gmail.com', 'Spouse', 0, NOW(), NOW()),
  (7, 5, 'Miles Bennett', '+13125557799', 'miles.b@chicago-law.com', 'Father', 1, NOW(), NOW()),
  (8, 6, 'Lance Vance', '+17865551235', 'lance.vance@vicecity.net', 'Brother', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `phone_number` = VALUES(`phone_number`);

-- 8. Subscriptions
INSERT INTO `subscriptions` (`id`, `user_id`, `activation_plan_id`, `plan_id`, `status`, `starts_at`, `expires_at`, `payment_reference`, `created_at`, `updated_at`)
VALUES
  (1, 1, 4, 4, 'ACTIVE', NOW(), DATE_ADD(NOW(), INTERVAL 365 DAY), 'SATA-SUB-2026-8812', NOW(), NOW()),
  (2, 2, 2, 2, 'ACTIVE', NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY), 'SATA-SUB-2026-5401', NOW(), NOW()),
  (3, 3, 1, 1, 'ACTIVE', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 'SATA-SUB-2026-9042', NOW(), NOW()),
  (4, 6, 1, 1, 'EXPIRED', '2026-03-01 08:00:00', '2026-03-31 08:00:00', 'SATA-SUB-2026-1002', NOW(), NOW())
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`);

UPDATE `users` SET `current_subscription_id` = 1, `active_subscription_id` = 1 WHERE `id` = 1;
UPDATE `users` SET `current_subscription_id` = 2, `active_subscription_id` = 2 WHERE `id` = 2;
UPDATE `users` SET `current_subscription_id` = 3, `active_subscription_id` = 3 WHERE `id` = 3;
UPDATE `users` SET `current_subscription_id` = 4, `active_subscription_id` = 4 WHERE `id` = 6;

-- 9. Security Events & Action History (Power button clicks, SIM remove, etc.)
INSERT INTO `security_events` (`id`, `event_uuid`, `user_id`, `device_id`, `event_type`, `severity`, `created_at`, `updated_at`)
VALUES
  (1, 'evt-pwr-001', 1, 101, 'POWER_OFF_ATTEMPT', 'CRITICAL', NOW(), NOW()),
  (2, 'evt-sim-002', 2, 102, 'SIM_CARD_REMOVED', 'CRITICAL', NOW(), NOW()),
  (3, 'evt-pin-003', 2, 102, 'WRONG_PIN_ATTEMPT', 'HIGH', NOW(), NOW()),
  (4, 'evt-chg-004', 3, 103, 'CHARGER_DISCONNECTED', 'MEDIUM', NOW(), NOW())
ON DUPLICATE KEY UPDATE `event_uuid` = VALUES(`event_uuid`);

-- 10. Security Commands
INSERT INTO `security_commands` (`id`, `user_id`, `device_id`, `command_uuid`, `command_type`, `status`, `reason`, `sent_at`, `executed_at`, `created_at`, `updated_at`)
VALUES
  (1, 1, 101, 'cmd-kiosk-001', 'KIOSK_LOCK', 'EXECUTED', 'Admin engaged lockdown after theft alert', NOW(), NOW(), NOW(), NOW()),
  (2, 1, 101, 'cmd-kiosk-002', 'KIOSK_UNLOCK', 'EXECUTED', 'Screen lock released after owner verification', NOW(), NOW(), NOW(), NOW()),
  (3, 6, 105, 'cmd-wipe-003', 'WIPE_DATA', 'EXECUTED', 'Permanent data purge on lost device', NOW(), NOW(), NOW(), NOW())
ON DUPLICATE KEY UPDATE `command_uuid` = VALUES(`command_uuid`);

-- 11. Trusted User Verifications
INSERT INTO `trusted_user_verifications` (`id`, `trusted_user_id`, `token`, `verification_code`, `is_verified`, `verified_at`, `expires_at`, `created_at`, `updated_at`)
VALUES
  (1, 1, 'tok_ver_elena_001', '839210', 1, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), NOW(), NOW()),
  (2, 2, 'tok_ver_david_002', '194820', 1, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), NOW(), NOW()),
  (3, 6, 'tok_ver_rachel_006', '552194', 0, NULL, DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW(), NOW())
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`);

-- 12. Security Event Locations (GPS breadcrumbs)
INSERT INTO `security_event_locations` (`id`, `security_event_id`, `latitude`, `longitude`, `accuracy`, `altitude`, `speed`, `heading`, `address`, `recorded_at`, `captured_at`, `created_at`, `updated_at`)
VALUES
  (1, 1, 37.7749290, -122.4194160, 5.20, 15.00, 0.00, 180.00, 'Market St & 4th St, San Francisco, CA', NOW(), NOW(), NOW(), NOW()),
  (2, 2, 34.0522340, -118.2436850, 4.80, 71.00, 12.50, 90.00, 'Grand Ave & 1st St, Los Angeles, CA', NOW(), NOW(), NOW(), NOW()),
  (3, 3, 34.0531000, -118.2425000, 6.10, 72.00, 0.00, 0.00, 'Hope St, Los Angeles, CA', NOW(), NOW(), NOW(), NOW()),
  (4, 4, 47.6062100, -122.3320710, 8.00, 42.00, 0.00, 0.00, 'Pike Pl, Seattle, WA', NOW(), NOW(), NOW(), NOW())
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`);

-- 13. Security Command Logs
INSERT INTO `security_command_logs` (`id`, `security_command_id`, `status_before`, `status_after`, `status_from`, `status_to`, `message`, `recorded_at`, `created_at`, `updated_at`)
VALUES
  (1, 1, 'PENDING', 'SENT', 'PENDING', 'SENT', 'Command dispatched via FCM to device', NOW(), NOW(), NOW()),
  (2, 1, 'SENT', 'EXECUTED', 'SENT', 'EXECUTED', 'Device confirmed KIOSK lockdown engaged', NOW(), NOW(), NOW()),
  (3, 2, 'PENDING', 'EXECUTED', 'PENDING', 'EXECUTED', 'Lock released by administrator', NOW(), NOW(), NOW()),
  (4, 3, 'PENDING', 'EXECUTED', 'PENDING', 'EXECUTED', 'Remote wipe confirmed', NOW(), NOW(), NOW())
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`);

-- 14. Live Location Sessions
INSERT INTO `live_location_sessions` (`id`, `user_id`, `device_id`, `session_id`, `session_token`, `short_token`, `status`, `is_active`, `started_at`, `expires_at`, `last_latitude`, `last_longitude`, `last_accuracy`, `last_updated_at`, `created_at`, `updated_at`)
VALUES
  (1, 1, 101, 'loc-sess-001', 'tok_live_alex_s24_99a', 'sh_live_01', 'ACTIVE', 1, NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR), 37.7749290, -122.4194160, 5.20, NOW(), NOW(), NOW()),
  (2, 2, 102, 'loc-sess-002', 'tok_live_sarah_p8_88b', 'sh_live_02', 'ACTIVE', 1, NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR), 34.0522340, -118.2436850, 4.80, NOW(), NOW(), NOW())
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`);

SET FOREIGN_KEY_CHECKS = 1;

-- End of database.sql
