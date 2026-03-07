-- ---------------------------------------------------------
-- MIGRATION : 001_initial_schema.sql
-- DESCRIPTION : Création de la table de versioning et structure de base
-- ---------------------------------------------------------

SET NAMES utf8mb4;

-- 1. Création de la table qui suivra les migrations futures
CREATE TABLE IF NOT EXISTS `sys_migrations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `migration_name` VARCHAR(255) NOT NULL,
    `executed_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. On enregistre que la migration 001 est "déjà faite" 
-- car elle représente l'état actuel de votre DB.
INSERT INTO `sys_migrations` (`migration_name`) VALUES ('001_initial_schema.sql');

-- 3. (Optionnel) Copiez ici le résultat d'un export "Structure uniquement" 
-- de vos tables actuelles (factures, clients, etc.) pour avoir un backup complet.