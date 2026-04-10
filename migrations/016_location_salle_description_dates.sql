-- ============================================================================
-- Migration 016 : Ajout colonnes description et dates dans location_salle_detail
-- ============================================================================
-- Date: 2026-03-16
--
--   description  VARCHAR(500) NULL  — texte libre décrivant la location
--   dates        JSON         NULL  — tableau JSON de dates ISO sélectionnées
--                                     ex: ["2025-01-05","2025-01-12"]
--
-- La quantité reste libre mais est pré-remplie côté frontend par le nombre
-- de dates sélectionnées.
-- ============================================================================

SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

SELECT '============================================================================' AS '';
SELECT 'Migration 016: description + dates dans location_salle_detail' AS '';
SELECT '============================================================================' AS '';

-- ============================================================================
-- ÉTAPE 1 : Colonne description
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 1: Ajout colonne description ===' AS '';

SET @col_desc = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND   TABLE_NAME   = 'location_salle_detail'
    AND   COLUMN_NAME  = 'description'
);

SET @sql = IF(@col_desc = 0,
    'ALTER TABLE `location_salle_detail`
         ADD COLUMN `description` VARCHAR(500) NULL DEFAULT NULL
         COMMENT "Description libre de la location"
         AFTER `motif`',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT CASE WHEN COUNT(*) = 1 THEN '✅ Colonne description ajoutée'
            ELSE '❌ Colonne description manquante' END AS check_description
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'location_salle_detail' AND COLUMN_NAME = 'description';

-- ============================================================================
-- ÉTAPE 2 : Colonne dates (JSON)
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 2: Ajout colonne dates ===' AS '';

SET @col_dates = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND   TABLE_NAME   = 'location_salle_detail'
    AND   COLUMN_NAME  = 'dates'
);

SET @sql = IF(@col_dates = 0,
    'ALTER TABLE `location_salle_detail`
         ADD COLUMN `dates` JSON NULL DEFAULT NULL
         COMMENT "Dates selectionnees pour la location (tableau JSON de dates ISO)"
         AFTER `description`',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT CASE WHEN COUNT(*) = 1 THEN '✅ Colonne dates ajoutée'
            ELSE '❌ Colonne dates manquante' END AS check_dates
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'location_salle_detail' AND COLUMN_NAME = 'dates';

-- ============================================================================
-- ÉTAPE 3 : Vérification structure finale
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 3: Structure location_salle_detail ===' AS '';

SELECT
    ORDINAL_POSITION AS pos,
    COLUMN_NAME      AS colonne,
    COLUMN_TYPE      AS type,
    IS_NULLABLE      AS nullable,
    COLUMN_COMMENT   AS commentaire
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND   TABLE_NAME   = 'location_salle_detail'
ORDER BY ORDINAL_POSITION;

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;

SELECT '' AS '';
SELECT '============================================================================' AS '';
SELECT '✅ MIGRATION 016 TERMINÉE' AS '';
SELECT '============================================================================' AS '';