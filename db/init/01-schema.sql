-- Schema for NSA project
-- Executed by MySQL entrypoint on db-master first start

CREATE DATABASE IF NOT EXISTS `nsa` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `nsa`;

-- Users (authentication)
CREATE TABLE IF NOT EXISTS `users` (
    `id`                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username`           VARCHAR(50)  NOT NULL,
    `email`              VARCHAR(100) NOT NULL,
    `password_hash`      VARCHAR(255) NOT NULL,
    `confirmed`          TINYINT(1)   NOT NULL DEFAULT 0,
    `confirmation_token` VARCHAR(64)  NULL,
    `created_at`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_username` (`username`),
    UNIQUE KEY `uq_email`    (`email`)
) ENGINE=InnoDB;

-- Items (CRUD demo)
CREATE TABLE IF NOT EXISTS `items` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT UNSIGNED NOT NULL,
    `title`       VARCHAR(200) NOT NULL,
    `description` TEXT         NULL,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;
