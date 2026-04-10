-- ============================================================================
-- Migration 014 : Ajout colonne motif dans location_salle_detail
-- ============================================================================
-- Date: 2026-03-14
-- Contexte: La colonne motif était prévue mais n'avait jamais été ajoutée.
--           Elle stocke le motif de location saisi par l'utilisateur
--           (ex: "Location d'un cabinet de consultation").
--           Utilisée lors de la génération du loyer comme motif du loyer.
--
-- Dépend de: table location_salle_detail existante (migration 010)
-- ============================================================================

SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

SELECT '============================================================================' AS '';
SELECT 'Migration 014: Ajout colonne motif dans location_salle_detail' AS '';
SELECT '============================================================================' AS '';

-- ============================================================================
-- ÉTAPE 1 : Ajout colonne motif (idempotent)
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 1: Ajout colonne motif ===' AS '';

SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND   TABLE_NAME   = 'location_salle_detail'
    AND   COLUMN_NAME  = 'motif'
);

SELECT CASE @col_exists
    WHEN 0 THEN '⚠️  Colonne motif absente — ajout en cours…'
    ELSE        '✅ Colonne motif déjà présente — aucune action'
END AS check_avant;

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `location_salle_detail`
         ADD COLUMN `motif` VARCHAR(255) NULL DEFAULT NULL
         COMMENT "Motif de la location (ex: Location d''un cabinet de consultation)"
         AFTER `id_service`',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT CASE
    WHEN COUNT(*) = 1
        THEN CONCAT('✅ Colonne motif ajoutée : ', COLUMN_TYPE, ' NULL=', IS_NULLABLE)
    ELSE '❌ Échec ajout colonne motif'
END AS check_apres
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND   TABLE_NAME   = 'location_salle_detail'
AND   COLUMN_NAME  = 'motif';

-- ============================================================================
-- ÉTAPE 2 : Vérification structure finale
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 2: Structure location_salle_detail ===' AS '';

SELECT
    ORDINAL_POSITION AS pos,
    COLUMN_NAME      AS colonne,
    COLUMN_TYPE      AS type,
    IS_NULLABLE      AS nullable,
    COLUMN_KEY       AS cle,
    COLUMN_COMMENT   AS commentaire
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND   TABLE_NAME   = 'location_salle_detail'
ORDER BY ORDINAL_POSITION;

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;

SELECT '' AS '';
SELECT '============================================================================' AS '';
SELECT '✅ MIGRATION 014 TERMINÉE — colonne motif ajoutée' AS '';
SELECT '============================================================================' AS '';