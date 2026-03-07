-- ---------------------------------------------------------
-- MIGRATION : 002_test_table.sql
-- ---------------------------------------------------------

CREATE TABLE IF NOT EXISTS `sys_test_migration` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `message` VARCHAR(255) NOT NULL,
    `executed_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Utilisation de CONCAT() au lieu de ||
INSERT INTO `sys_test_migration` (`message`) 
VALUES (CONCAT('Migration 002 executee avec succes sur ', @@hostname));