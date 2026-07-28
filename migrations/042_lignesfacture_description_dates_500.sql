-- ============================================================================
-- Migration 042 : Élargissement de lignesfacture.description_dates à 500
--
-- Contexte : La colonne description_dates (VARCHAR(100)) est trop étroite
--            pour les lignes de facture couvrant de nombreuses dates
--            (ex: location à l'utilisation avec de nombreuses séances dans
--            l'année). Erreur observée en production :
--              SQLSTATE[22001]: String data, right truncated:
--              1406 Data too long for column 'description_dates' at row 1
--
-- Dépend de : migration 016 (création de description_dates)
-- ============================================================================

SET NAMES utf8mb4;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

SELECT '============================================================================' AS '';
SELECT 'Migration 042: lignesfacture.description_dates VARCHAR(100) → VARCHAR(500)' AS '';
SELECT '============================================================================' AS '';

-- ============================================================================
-- ÉTAPE 1 : Vérification préalable — table et colonne existent
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 1 : Vérification préalable ===' AS '';

SELECT CASE WHEN COUNT(*) = 1
    THEN '✅ Table lignesfacture présente'
    ELSE '❌ Table lignesfacture introuvable — migration annulée'
END AS check_table
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lignesfacture';

SELECT CASE WHEN COUNT(*) = 1
    THEN '✅ Colonne description_dates présente'
    ELSE '❌ Colonne description_dates introuvable — migration annulée'
END AS check_colonne
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lignesfacture' AND COLUMN_NAME = 'description_dates';

-- ============================================================================
-- ÉTAPE 2 : Élargissement VARCHAR(100) → VARCHAR(500) (idempotent)
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 2 : Élargissement description_dates ===' AS '';

SET @longueur_actuelle = (
    SELECT CHARACTER_MAXIMUM_LENGTH
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND   TABLE_NAME   = 'lignesfacture'
    AND   COLUMN_NAME  = 'description_dates'
);

SELECT
    CASE
        WHEN @longueur_actuelle >= 500 THEN '✅ description_dates déjà ≥ 500 caractères — aucune action requise'
        ELSE CONCAT('⚠️  description_dates actuellement à ', @longueur_actuelle, ' caractères — élargissement en cours…')
    END AS check_avant;

SET @sql = IF(@longueur_actuelle < 500,
    'ALTER TABLE `lignesfacture`
     MODIFY COLUMN `description_dates` VARCHAR(500) NULL DEFAULT NULL',
    'SELECT ''description_dates déjà VARCHAR(500) ou plus — ALTER ignoré'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================================
-- ÉTAPE 3 : Vérification post-modification
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 3 : Vérification finale ===' AS '';

SELECT
    CASE
        WHEN CHARACTER_MAXIMUM_LENGTH >= 500
        THEN CONCAT('✅ description_dates confirmé VARCHAR(', CHARACTER_MAXIMUM_LENGTH, ')')
        ELSE CONCAT('❌ description_dates toujours trop étroit : VARCHAR(', CHARACTER_MAXIMUM_LENGTH, ')')
    END AS check_alter_result
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND   TABLE_NAME   = 'lignesfacture'
AND   COLUMN_NAME  = 'description_dates';

SELECT '' AS '';
SELECT '=== ÉTAPE 3b : Structure finale de lignesfacture ===' AS '';

SELECT
    ORDINAL_POSITION AS pos,
    COLUMN_NAME      AS colonne,
    COLUMN_TYPE      AS type,
    IS_NULLABLE      AS nullable,
    COLUMN_COMMENT   AS commentaire
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND   TABLE_NAME   = 'lignesfacture'
ORDER BY ORDINAL_POSITION;

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;

SELECT '' AS '';
SELECT '============================================================================' AS '';
SELECT '✅ MIGRATION 042 TERMINÉE' AS '';
SELECT '============================================================================' AS '';