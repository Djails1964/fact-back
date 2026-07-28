-- ============================================================================
-- Migration 036 : Introduction des types de contrat de location
--
-- Objectif :
--   Remplacer les propriétés type_document, type_client_requis et
--   facturation_utilisation portées par la table `salle` par une nouvelle
--   table `type_contrat_location`, liée au contrat de location.
--
-- Changements :
--   1. Création de `type_contrat_location`
--   2. Ajout de `id_type_contrat` sur `location_salle_contrat`
--   3. Migration automatique depuis salle.facturation_utilisation
--   4. Suppression de type_document, type_client_requis, facturation_utilisation
--      sur la table `salle`
--   5. Mise à jour de la contrainte d'unicité sur location_salle_contrat
--      (un client peut avoir plusieurs contrats/année, un par salle)
--
-- Dépend de : 033_salle_facturation_utilisation.sql
-- ============================================================================

SET NAMES utf8mb4;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

SELECT '============================================================================' AS '';
SELECT 'Migration 036 : Types de contrat de location' AS '';
SELECT '============================================================================' AS '';

-- ============================================================================
-- ÉTAPE 1 : Création de la table type_contrat_location
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 1 : Création de type_contrat_location ===' AS '';

CREATE TABLE IF NOT EXISTS `type_contrat_location` (
    `id`                  INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `nom`                 VARCHAR(100)   NOT NULL COMMENT 'Libellé du type (ex: Location au forfait)',
    `type_document`       ENUM('facture','confirmation') NOT NULL DEFAULT 'facture'
                          COMMENT 'Document généré pour ce type de contrat',
    `type_client_requis`  VARCHAR(50)    NULL DEFAULT NULL
                          COMMENT 'Type de client requis (ex: therapeute) ou NULL = tous',
    `actif`               TINYINT(1)     NOT NULL DEFAULT 1
                          COMMENT '1 = type actif',
    `created_at`          DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP
                          ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_type_contrat_nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Types de contrat de location de salle : forfait ou utilisation';

SELECT CASE WHEN COUNT(*) = 1
    THEN '✅ Table type_contrat_location créée'
    ELSE '❌ Échec création'
END AS check_table
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'type_contrat_location';

-- ============================================================================
-- ÉTAPE 2 : Insertion des deux types de base
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 2 : Insertion des types de base ===' AS '';

INSERT IGNORE INTO `type_contrat_location`
    (`id`, `nom`, `type_document`, `type_client_requis`, `actif`)
VALUES
    (1, 'Location au forfait',       'confirmation', 'therapeute', 1),
    (2, 'Location à l''utilisation', 'facture',      NULL,         1);

SELECT CONCAT('✅ ', COUNT(*), ' type(s) présent(s)') AS check_types
FROM type_contrat_location;

-- ============================================================================
-- ÉTAPE 3 : Ajout de id_type_contrat sur location_salle_contrat
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 3 : Ajout id_type_contrat sur location_salle_contrat ===' AS '';

SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'location_salle_contrat'
      AND COLUMN_NAME  = 'id_type_contrat'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `location_salle_contrat`
     ADD COLUMN `id_type_contrat` INT UNSIGNED NULL
     COMMENT "FK vers type_contrat_location.id"
     AFTER `id_salle`',
    'DO 0'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- FK
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA    = DATABASE()
      AND TABLE_NAME      = 'location_salle_contrat'
      AND CONSTRAINT_NAME = 'fk_contrat_type_contrat'
);
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE `location_salle_contrat`
     ADD CONSTRAINT `fk_contrat_type_contrat`
     FOREIGN KEY (`id_type_contrat`)
     REFERENCES `type_contrat_location`(`id`)
     ON DELETE SET NULL ON UPDATE CASCADE',
    'DO 0'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT CASE WHEN COUNT(*) = 1
    THEN '✅ Colonne id_type_contrat ajoutée'
    ELSE '❌ Échec ajout colonne'
END AS check_col
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME   = 'location_salle_contrat'
  AND COLUMN_NAME  = 'id_type_contrat';

-- ============================================================================
-- ÉTAPE 4 : Migration automatique des contrats existants
--   Dériver id_type_contrat depuis salle.facturation_utilisation
--   facturation_utilisation = 1 → type 2 (À l'utilisation → facture)
--   facturation_utilisation = 0 → type 1 (Forfait → confirmation)
--   Contrat sans salle (id_salle NULL) → type 1 par défaut
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 4 : Migration des contrats existants ===' AS '';

UPDATE `location_salle_contrat` lsc
LEFT JOIN `salle` s ON s.id = lsc.id_salle
SET lsc.id_type_contrat = CASE
    WHEN s.facturation_utilisation = 1 THEN 2
    ELSE 1
END
WHERE lsc.id_type_contrat IS NULL;

SELECT CONCAT('✅ ', ROW_COUNT(), ' contrat(s) migré(s)') AS migration_contrats;

-- Vérification
SELECT
    tcl.nom                     AS type_contrat,
    COUNT(lsc.id)               AS nb_contrats
FROM location_salle_contrat lsc
LEFT JOIN type_contrat_location tcl ON tcl.id = lsc.id_type_contrat
GROUP BY tcl.nom
ORDER BY tcl.nom;

-- ============================================================================
-- ÉTAPE 5 : Mise à jour contrainte d'unicité sur location_salle_contrat
--   Ancienne : (id_client, annee) — un seul contrat par client/année
--   Nouvelle : (id_client, annee, id_salle) — un contrat par client/année/salle
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 5 : Mise à jour contrainte unicité ===' AS '';

SET @uk_old = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA    = DATABASE()
      AND TABLE_NAME      = 'location_salle_contrat'
      AND CONSTRAINT_NAME = 'uk_contrat_client_annee'
      AND CONSTRAINT_TYPE = 'UNIQUE'
);
SET @sql = IF(@uk_old > 0,
    'ALTER TABLE `location_salle_contrat` DROP INDEX `uk_contrat_client_annee`',
    'DO 0'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @uk_new = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA    = DATABASE()
      AND TABLE_NAME      = 'location_salle_contrat'
      AND CONSTRAINT_NAME = 'uk_contrat_client_annee_salle'
      AND CONSTRAINT_TYPE = 'UNIQUE'
);
SET @sql = IF(@uk_new = 0,
    'ALTER TABLE `location_salle_contrat`
     ADD UNIQUE KEY `uk_contrat_client_annee_salle` (`id_client`, `annee`, `id_salle`)
     COMMENT "Un seul contrat par client, année et salle"',
    'DO 0'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT CASE WHEN COUNT(*) = 1
    THEN '✅ Contrainte uk_contrat_client_annee_salle présente'
    ELSE '❌ Contrainte manquante'
END AS check_uk
FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA    = DATABASE()
  AND TABLE_NAME      = 'location_salle_contrat'
  AND CONSTRAINT_NAME = 'uk_contrat_client_annee_salle';

-- ============================================================================
-- ÉTAPE 6 : Suppression des colonnes devenues obsolètes sur salle
--   type_document, type_client_requis, facturation_utilisation
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 6 : Suppression colonnes obsolètes sur salle ===' AS '';

-- type_document
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='salle' AND COLUMN_NAME='type_document');
SET @sql = IF(@col > 0, 'ALTER TABLE `salle` DROP COLUMN `type_document`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- type_client_requis
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='salle' AND COLUMN_NAME='type_client_requis');
SET @sql = IF(@col > 0, 'ALTER TABLE `salle` DROP COLUMN `type_client_requis`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- facturation_utilisation
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='salle' AND COLUMN_NAME='facturation_utilisation');
SET @sql = IF(@col > 0, 'ALTER TABLE `salle` DROP COLUMN `facturation_utilisation`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT 'Colonnes supprimées (type_document, type_client_requis, facturation_utilisation)' AS info;

-- Vérification structure finale salle
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salle'
ORDER BY ORDINAL_POSITION;

-- ============================================================================
-- ÉTAPE 7 : Vérification finale
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 7 : Structure finale location_salle_contrat ===' AS '';

SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'location_salle_contrat'
ORDER BY ORDINAL_POSITION;

SELECT '' AS '';
SELECT '=== ÉTAPE 7b : Contrats avec leur type ===' AS '';

SELECT
    lsc.id          AS id_contrat,
    lsc.annee,
    lsc.id_salle,
    s.nom           AS salle,
    tcl.nom         AS type_contrat,
    tcl.type_document,
    tcl.type_client_requis
FROM location_salle_contrat lsc
LEFT JOIN salle s ON s.id = lsc.id_salle
LEFT JOIN type_contrat_location tcl ON tcl.id = lsc.id_type_contrat
ORDER BY lsc.annee, lsc.id;

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;

SELECT '' AS '';
SELECT '============================================================================' AS '';
SELECT '✅ MIGRATION 036 TERMINÉE' AS '';
SELECT '============================================================================' AS '';