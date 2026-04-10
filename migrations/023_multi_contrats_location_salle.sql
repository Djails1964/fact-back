-- ============================================================================
-- Migration 023 : Plusieurs contrats de location par client et par année
--
-- Changements :
--   1. Supprimer UNIQUE KEY uk_contrat_client_annee sur location_salle_contrat
--   2. Ajouter colonne libelle (optionnel, pour distinguer les contrats)
--   3. Mettre à jour les index
-- ============================================================================

SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

SELECT '============================================================================' AS '';
SELECT 'Migration 023: Multi-contrats location salle par client/année' AS '';
SELECT '============================================================================' AS '';

-- ── ÉTAPE 1 : Supprimer la contrainte unique (id_client, annee) ──────────────
SET @idx = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND   TABLE_NAME   = 'location_salle_contrat'
    AND   INDEX_NAME   = 'uk_contrat_client_annee'
);
SET @sql = IF(@idx > 0,
    'ALTER TABLE `location_salle_contrat` DROP INDEX `uk_contrat_client_annee`',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT CASE WHEN COUNT(*) = 0 THEN '✅ Contrainte uk_contrat_client_annee supprimée'
            ELSE '⚠️ Contrainte uk_contrat_client_annee encore présente' END AS check_unique
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
AND   TABLE_NAME   = 'location_salle_contrat'
AND   INDEX_NAME   = 'uk_contrat_client_annee';

-- ── ÉTAPE 2 : Ajouter colonne libelle pour distinguer les contrats ────────────
SET @col = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND   TABLE_NAME   = 'location_salle_contrat'
    AND   COLUMN_NAME  = 'libelle'
);
SET @sql = IF(@col = 0,
    'ALTER TABLE `location_salle_contrat`
     ADD COLUMN `libelle` VARCHAR(100) NULL DEFAULT NULL
     COMMENT "Libellé optionnel pour distinguer plusieurs contrats (ex: Contrat 1, Contrat 2)"
     AFTER `annee`',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT CASE WHEN COUNT(*) = 1 THEN '✅ Colonne libelle ajoutée'
            ELSE '❌ Colonne libelle manquante' END AS check_libelle
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND   TABLE_NAME   = 'location_salle_contrat'
AND   COLUMN_NAME  = 'libelle';

-- ── ÉTAPE 3 : Ajouter index (non-unique) sur (id_client, annee) ──────────────
SET @idx2 = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND   TABLE_NAME   = 'location_salle_contrat'
    AND   INDEX_NAME   = 'idx_contrat_client_annee'
);
SET @sql = IF(@idx2 = 0,
    'ALTER TABLE `location_salle_contrat`
     ADD INDEX `idx_contrat_client_annee` (`id_client`, `annee`)',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT '✅ Index idx_contrat_client_annee présent' AS '';

-- ── VÉRIFICATION FINALE ───────────────────────────────────────────────────────
SELECT
    INDEX_NAME,
    NON_UNIQUE,
    COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
AND   TABLE_NAME   = 'location_salle_contrat'
ORDER BY INDEX_NAME;

SELECT ORDINAL_POSITION AS pos, COLUMN_NAME AS col, COLUMN_TYPE AS type, IS_NULLABLE AS nullable
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'location_salle_contrat'
ORDER BY ORDINAL_POSITION;

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;

SELECT '✅ MIGRATION 023 TERMINÉE' AS '';