-- ============================================================================
-- Migration 037 : Transfert de categorie_motifs de salle vers type_contrat_location
--
-- Objectif :
--   La catégorie de motifs est désormais liée au type de contrat de location
--   plutôt qu'à la salle. Cela permet d'avoir des motifs différents selon
--   le type de contrat (forfait vs utilisation) indépendamment de la salle.
--
-- Changements :
--   1. Ajout de categorie_motifs sur type_contrat_location
--   2. Migration automatique depuis salle.categorie_motifs
--      (via location_salle_contrat → id_type_contrat → salle)
--   3. Suppression de categorie_motifs sur salle
--
-- Dépend de : 036_type_contrat_location.sql
-- ============================================================================

SET NAMES utf8mb4;

SELECT '============================================================================' AS '';
SELECT 'Migration 037 : categorie_motifs salle → type_contrat_location' AS '';
SELECT '============================================================================' AS '';

-- ============================================================================
-- ÉTAPE 1 : Ajout de categorie_motifs sur type_contrat_location
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 1 : Ajout categorie_motifs sur type_contrat_location ===' AS '';

SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'type_contrat_location'
      AND COLUMN_NAME  = 'categorie_motifs'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `type_contrat_location`
     ADD COLUMN `categorie_motifs` VARCHAR(100) NULL DEFAULT NULL
     COMMENT "Catégorie de motifs proposée lors de la saisie d\'une location"
     AFTER `type_client_requis`',
    'DO 0'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT CASE WHEN COUNT(*) = 1
    THEN '✅ Colonne categorie_motifs ajoutée sur type_contrat_location'
    ELSE '❌ Échec ajout colonne'
END AS check_col
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME   = 'type_contrat_location'
  AND COLUMN_NAME  = 'categorie_motifs';

-- ============================================================================
-- ÉTAPE 2 : Migration depuis salle.categorie_motifs
--   Logique : pour chaque type de contrat, prendre la categorie_motifs
--   de la salle la plus souvent associée à ce type de contrat.
--   Fallback : prendre la première valeur non-nulle trouvée dans salle.
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 2 : Migration des catégories de motifs ===' AS '';

-- Vérifier que salle.categorie_motifs existe encore
SET @salle_col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'salle'
      AND COLUMN_NAME  = 'categorie_motifs'
);

-- Migration : type 1 (forfait) → categorie_motifs des salles liées aux contrats de type 1
-- Migration : type 2 (utilisation) → idem pour type 2
-- Fallback direct : prendre la premiere valeur non-nulle de salle

SET @sql_migrate = IF(@salle_col_exists > 0,
    'UPDATE type_contrat_location tcl
     JOIN (
         SELECT
             lsc.id_type_contrat,
             s.categorie_motifs,
             COUNT(*) AS nb
         FROM location_salle_contrat lsc
         JOIN salle s ON s.id = lsc.id_salle
         WHERE s.categorie_motifs IS NOT NULL
           AND s.categorie_motifs != \'\'
         GROUP BY lsc.id_type_contrat, s.categorie_motifs
         ORDER BY nb DESC
     ) ranked ON ranked.id_type_contrat = tcl.id
     SET tcl.categorie_motifs = ranked.categorie_motifs
     WHERE tcl.categorie_motifs IS NULL',
    'DO 0'
);
PREPARE s FROM @sql_migrate; EXECUTE s; DEALLOCATE PREPARE s;

SELECT CONCAT('✅ ', ROW_COUNT(), ' type(s) de contrat mis à jour') AS migration_motifs;

-- Afficher le résultat
SELECT id, nom, categorie_motifs FROM type_contrat_location ORDER BY id;

-- ============================================================================
-- ÉTAPE 3 : Suppression de categorie_motifs sur salle
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 3 : Suppression categorie_motifs sur salle ===' AS '';

SET @col = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'salle'
      AND COLUMN_NAME  = 'categorie_motifs'
);
SET @sql = IF(@col > 0,
    'ALTER TABLE `salle` DROP COLUMN `categorie_motifs`',
    'DO 0'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT CASE WHEN COUNT(*) = 0
    THEN '✅ Colonne categorie_motifs supprimée de salle'
    ELSE '⚠️  Colonne categorie_motifs encore présente sur salle'
END AS check_drop
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME   = 'salle'
  AND COLUMN_NAME  = 'categorie_motifs';

-- ============================================================================
-- ÉTAPE 4 : Structure finale
-- ============================================================================

SELECT '' AS '';
SELECT '=== Structure finale type_contrat_location ===' AS '';

SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'type_contrat_location'
ORDER BY ORDINAL_POSITION;

SELECT '' AS '';
SELECT '=== Structure finale salle ===' AS '';

SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salle'
ORDER BY ORDINAL_POSITION;

SELECT '' AS '';
SELECT '============================================================================' AS '';
SELECT '✅ MIGRATION 037 TERMINÉE' AS '';
SELECT '============================================================================' AS '';