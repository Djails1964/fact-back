-- Migration de la table activity_logs existante
-- Compatible avec votre structure MariaDB actuelle
-- À exécuter étape par étape dans phpMyAdmin

-- 1. D'abord, vérifier la structure actuelle
DESCRIBE activity_logs;

-- 2. Supprimer les contraintes ENUM et convertir en VARCHAR
ALTER TABLE activity_logs 
MODIFY COLUMN action_type VARCHAR(100) NOT NULL;

-- 3. Supprimer la contrainte ENUM de severity
UPDATE activity_logs SET severity = 'info' WHERE severity IS NULL;
ALTER TABLE activity_logs 
MODIFY COLUMN severity VARCHAR(50) NOT NULL DEFAULT 'info';

-- 4. Agrandir les autres colonnes pour éviter la troncature
ALTER TABLE activity_logs 
MODIFY COLUMN user_name VARCHAR(255) DEFAULT NULL,
MODIFY COLUMN entity_type VARCHAR(100) DEFAULT NULL,
MODIFY COLUMN entity_id VARCHAR(100) DEFAULT NULL;

-- 5. Ajouter les nouvelles colonnes pour les fonctionnalités avancées
ALTER TABLE activity_logs 
ADD COLUMN resolved_at TIMESTAMP NULL DEFAULT NULL AFTER created_at,
ADD COLUMN resolved_by INT DEFAULT NULL AFTER resolved_at,
ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL AFTER resolved_by,
ADD COLUMN archived_by INT DEFAULT NULL AFTER archived_at;

-- 6. Vérifier le résultat final
DESCRIBE activity_logs;

-- 7. Optionnel : Ajouter des clés étrangères pour les nouvelles colonnes
-- (Décommentez si vous voulez les contraintes de clés étrangères)
/*
ALTER TABLE activity_logs 
ADD CONSTRAINT fk_resolved_by FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_archived_by FOREIGN KEY (archived_by) REFERENCES users(id) ON DELETE SET NULL;
*/