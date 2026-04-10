-- ============================================================================
-- Migration 013 : Suppression de la colonne type_location
-- ============================================================================
-- Date: 2026-03-14
-- Contexte: type_location stockait l'id de l'unité tarifaire en VARCHAR.
--           Remplacé par id_unite (INT FK) ajouté en migration 010 (étape 6f).
--           La colonne est désormais redondante et supprimée.
--
-- ⚠️  Prérequis : migration 010 (étape 6f) déjà jouée
--                 (colonnes id_unite et id_service présentes)
--
-- Impact :
--   - Contrainte d'unicité uk_detail_contrat_mois_salle_type recréée sur id_unite
--   - Index idx_detail_type supprimé
--   - Colonne type_location supprimée
-- ============================================================================

SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

SELECT '============================================================================' AS '';
SELECT 'Migration 013: Suppression type_location → remplacement par id_unite' AS '';
SELECT '============================================================================' AS '';

-- ============================================================================
-- ÉTAPE 1 : Vérification prérequis — id_unite doit exister
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 1: Vérification prérequis ===' AS '';

SELECT
    CASE
        WHEN COUNT(*) = 1 THEN '✅ Colonne id_unite présente — migration peut continuer'
        ELSE '❌ STOP: Colonne id_unite absente — jouez d''abord la migration 010 (étape 6f)'
    END AS check_id_unite
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND   TABLE_NAME   = 'location_salle_detail'
AND   COLUMN_NAME  = 'id_unite';

-- ============================================================================
-- ÉTAPE 2 : Vérification intégrité — tous les détails ont bien un id_unite
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 2: Intégrité des données ===' AS '';

SELECT
    COUNT(*)                                        AS total_details,
    SUM(CASE WHEN id_unite IS NULL THEN 1 ELSE 0 END) AS sans_id_unite,
    CASE
        WHEN SUM(CASE WHEN id_unite IS NULL THEN 1 ELSE 0 END) = 0
        THEN '✅ Tous les détails ont un id_unite'
        ELSE '⚠️  Des détails sans id_unite existent — à corriger avant suppression'
    END AS statut
FROM location_salle_detail;

-- Rétro-remplissage de sécurité : si type_location contient un entier et id_unite est NULL
UPDATE `location_salle_detail`
SET `id_unite` = CAST(`type_location` AS UNSIGNED)
WHERE `id_unite` IS NULL
AND   `type_location` REGEXP '^[0-9]+$';

SELECT
    SUM(CASE WHEN id_unite IS NULL THEN 1 ELSE 0 END) AS restants_sans_id_unite
FROM location_salle_detail;

-- ============================================================================
-- ÉTAPE 3 : Suppression contrainte unique portant sur type_location
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 3: Suppression contrainte unique uk_detail_contrat_mois_salle_type ===' AS '';

SET @uk_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND   TABLE_NAME   = 'location_salle_detail'
    AND   INDEX_NAME   = 'uk_detail_contrat_mois_salle_type'
    AND   NON_UNIQUE   = 0
);

SET @sql = IF(@uk_exists > 0,
    'ALTER TABLE `location_salle_detail` DROP INDEX `uk_detail_contrat_mois_salle_type`',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT IF(@uk_exists > 0, '✅ Contrainte uk_detail_contrat_mois_salle_type supprimée', 'ℹ️  Contrainte déjà absente') AS check_uk_drop;

-- ============================================================================
-- ÉTAPE 4 : Suppression index idx_detail_type
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 4: Suppression index idx_detail_type ===' AS '';

SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND   TABLE_NAME   = 'location_salle_detail'
    AND   INDEX_NAME   = 'idx_detail_type'
);

SET @sql = IF(@idx_exists > 0,
    'ALTER TABLE `location_salle_detail` DROP INDEX `idx_detail_type`',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT IF(@idx_exists > 0, '✅ Index idx_detail_type supprimé', 'ℹ️  Index déjà absent') AS check_idx_drop;

-- ============================================================================
-- ÉTAPE 5 : Recréation contrainte unique sur (id_contrat, mois, salle, id_unite)
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 5: Recréation contrainte unique sur id_unite ===' AS '';

SET @uk_new = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND   TABLE_NAME   = 'location_salle_detail'
    AND   INDEX_NAME   = 'uk_detail_contrat_mois_salle_unite'
);

SET @sql = IF(@uk_new = 0,
    'ALTER TABLE `location_salle_detail`
         ADD UNIQUE KEY `uk_detail_contrat_mois_salle_unite`
         (`id_contrat`, `mois`, `salle`, `id_unite`)
         COMMENT "Unicité par contrat / mois / salle / unité tarifaire"',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT
    CASE WHEN COUNT(*) >= 1
        THEN '✅ Contrainte uk_detail_contrat_mois_salle_unite créée'
        ELSE '❌ Échec création contrainte uk_detail_contrat_mois_salle_unite'
    END AS check_uk_new
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
AND   TABLE_NAME   = 'location_salle_detail'
AND   INDEX_NAME   = 'uk_detail_contrat_mois_salle_unite';

-- ============================================================================
-- ÉTAPE 6 : Suppression de la colonne type_location
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 6: Suppression colonne type_location ===' AS '';

SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND   TABLE_NAME   = 'location_salle_detail'
    AND   COLUMN_NAME  = 'type_location'
);

SET @sql = IF(@col_exists > 0,
    'ALTER TABLE `location_salle_detail` DROP COLUMN `type_location`',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT
    CASE
        WHEN COUNT(*) = 0 THEN '✅ Colonne type_location supprimée'
        ELSE '❌ Colonne type_location encore présente'
    END AS check_drop_col
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND   TABLE_NAME   = 'location_salle_detail'
AND   COLUMN_NAME  = 'type_location';

-- ============================================================================
-- ÉTAPE 7 : Vérification structure finale
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 7: Structure finale de location_salle_detail ===' AS '';

SELECT
    ORDINAL_POSITION    AS pos,
    COLUMN_NAME         AS colonne,
    COLUMN_TYPE         AS type,
    IS_NULLABLE         AS nullable,
    COLUMN_KEY          AS cle,
    COLUMN_COMMENT      AS commentaire
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND   TABLE_NAME   = 'location_salle_detail'
ORDER BY ORDINAL_POSITION;

SELECT '' AS '';
SELECT '=== ÉTAPE 7b: Index et contraintes ===' AS '';

SELECT
    INDEX_NAME      AS index_name,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS colonnes,
    IF(NON_UNIQUE = 0, 'UNIQUE', 'INDEX') AS type
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
AND   TABLE_NAME   = 'location_salle_detail'
GROUP BY INDEX_NAME, NON_UNIQUE
ORDER BY INDEX_NAME;

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;

SELECT '' AS '';
SELECT '============================================================================' AS '';
SELECT '✅ MIGRATION 013 TERMINÉE — type_location supprimé' AS '';
SELECT '============================================================================' AS '';