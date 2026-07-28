-- ============================================================================
-- Migration 043 : Suppression du loyer intermédiaire — facture directe
--
-- Contexte : Les locations de salle génèrent désormais directement une
--            facture (ou confirmation de paiement), sans passer par un
--            loyer intermédiaire. Le type de document (facture vs
--            confirmation) se déduit de type_contrat_location.est_forfait
--            via location_salle_contrat.id_type_contrat, sans colonne
--            dupliquée sur facture.
--
-- ⚠️  DESTRUCTIF : les tables loyer / loyer_detail et les paiements qui leur
--     sont liés sont supprimés. Migration volontairement non conservatrice
--     (pas de reprise de données) — décision projet : la gestion des
--     locations n'est pas encore en production.
--
-- Dépend de : migration 022 (loyer_lien_facture), 028 (duree/nb_seances)
-- ============================================================================

SET NAMES utf8mb4;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
 
SELECT '============================================================================' AS '';
SELECT 'Migration 043: Correction facture.id_contrat_location' AS '';
SELECT '============================================================================' AS '';
 
-- ============================================================================
-- ÉTAPE 1 : Nettoyage d'un éventuel résidu partiel de la migration 042
-- ============================================================================
 
SELECT '' AS '';
SELECT '=== ÉTAPE 1 : Nettoyage résidu éventuel ===' AS '';
 
SET @fk_existe = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'facture'
    AND CONSTRAINT_NAME = 'fk_facture_contrat_location' AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql = IF(@fk_existe > 0,
    'ALTER TABLE `facture` DROP FOREIGN KEY `fk_facture_contrat_location`',
    'SELECT ''Pas de FK résiduelle à supprimer'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
 
SET @col_existe = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'facture' AND COLUMN_NAME = 'id_contrat_location'
);
SET @sql = IF(@col_existe > 0,
    'ALTER TABLE `facture` DROP COLUMN `id_contrat_location`',
    'SELECT ''Pas de colonne résiduelle à supprimer'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
 
-- ============================================================================
-- ÉTAPE 2 : Ajout de la colonne avec le type exact de location_salle_contrat.id
-- ============================================================================
 
SELECT '' AS '';
SELECT '=== ÉTAPE 2 : Ajout id_contrat_location (type détecté dynamiquement) ===' AS '';
 
SET @type_reference = (
    SELECT COLUMN_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'location_salle_contrat' AND COLUMN_NAME = 'id'
);
 
SELECT CONCAT('Type détecté pour location_salle_contrat.id : ', @type_reference) AS info_type;
 
SET @sql = CONCAT(
    'ALTER TABLE `facture` ADD COLUMN `id_contrat_location` ', @type_reference, ' NULL ',
    'COMMENT ''Référence vers location_salle_contrat — facture générée directement depuis une location'' ',
    'AFTER `id_client`'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
 
SELECT CASE WHEN COUNT(*) = 1 THEN '✅ Colonne ajoutée' ELSE '❌ Échec ajout colonne' END AS check_colonne
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'facture' AND COLUMN_NAME = 'id_contrat_location';
 
-- Index (nécessaire avant la FK sur certaines configurations strictes)
SET @idx_existe = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'facture' AND INDEX_NAME = 'idx_facture_contrat_location'
);
SET @sql = IF(@idx_existe = 0,
    'ALTER TABLE `facture` ADD INDEX `idx_facture_contrat_location` (`id_contrat_location`)',
    'SELECT ''Index déjà présent'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
 
-- ============================================================================
-- ÉTAPE 3 : Ajout de la clé étrangère
-- ============================================================================
 
SELECT '' AS '';
SELECT '=== ÉTAPE 3 : Ajout de la contrainte FK ===' AS '';
 
SET @sql = 'ALTER TABLE `facture`
    ADD CONSTRAINT `fk_facture_contrat_location`
    FOREIGN KEY (`id_contrat_location`) REFERENCES `location_salle_contrat`(`id`)
    ON DELETE SET NULL';
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
 
SELECT CASE WHEN COUNT(*) = 1 THEN '✅ Contrainte FK ajoutée avec succès' ELSE '❌ Échec ajout FK' END AS check_fk
FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'facture'
AND CONSTRAINT_NAME = 'fk_facture_contrat_location' AND CONSTRAINT_TYPE = 'FOREIGN KEY';
 
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
 
SELECT '' AS '';
SELECT '============================================================================' AS '';
SELECT '✅ MIGRATION 043 TERMINÉE' AS '';
SELECT '============================================================================' AS '';