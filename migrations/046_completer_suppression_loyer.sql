-- ============================================================================
-- Migration 046 : Compléter la suppression du loyer intermédiaire
--
-- Contexte : la migration précédemment exécutée sous le nom "043" n'a en
--            réalité appliqué que l'ajout de facture.id_contrat_location
--            (avec sa contrainte FK) — les autres parties de la migration
--            originale (création de facture_detail_mensuel, nettoyage de
--            paiement, suppression de loyer/loyer_detail) n'ont jamais été
--            exécutées. Cette migration les applique, idempotente : sans
--            risque si certaines étapes ont déjà été faites par ailleurs.
--
-- ⚠️  DESTRUCTIF : les tables loyer / loyer_detail sont supprimées à
--     l'étape 4. Migration volontairement non conservatrice (pas de reprise
--     de données) — décision projet : la gestion des locations n'est pas
--     encore en production.
-- ============================================================================

SET NAMES utf8mb4;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

SELECT '============================================================================' AS '';
SELECT 'Migration 046: Compléter la suppression du loyer intermédiaire' AS '';
SELECT '============================================================================' AS '';

-- ============================================================================
-- ÉTAPE 0 : Vérification préalable — facture.id_contrat_location présente
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 0 : Vérification préalable ===' AS '';

SELECT CASE WHEN COUNT(*) = 1
    THEN '✅ facture.id_contrat_location déjà présente (migration 043 OK)'
    ELSE '⚠️  facture.id_contrat_location absente — exécuter d''abord la migration 043'
END AS check_prealable
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'facture' AND COLUMN_NAME = 'id_contrat_location';

-- ============================================================================
-- ÉTAPE 1 : Création de facture_detail_mensuel
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 1 : Table facture_detail_mensuel ===' AS '';

CREATE TABLE IF NOT EXISTS `facture_detail_mensuel` (
    `id_detail`      INT AUTO_INCREMENT PRIMARY KEY,
    `id_facture`     INT NOT NULL COMMENT 'Ref vers facture.id_facture',
    `mois`           TINYINT NOT NULL COMMENT '1=Janvier ... 12=Décembre',
    `annee`          SMALLINT NOT NULL,
    `id_unite`       INT NULL COMMENT 'Ref vers unites.id',
    `id_service`     INT NULL COMMENT 'Ref vers services.id',
    `quantite`       DECIMAL(10,2) NULL COMMENT 'Nombre d''unités louées (ex: 3.5 heures, 2 journées)',
    `description`    VARCHAR(255) NULL COMMENT 'Description libre du mois',
    `montant`        DECIMAL(10,2) NOT NULL COMMENT 'Montant figé au moment de la génération',
    `dates`          VARCHAR(500) NULL COMMENT 'Dates sélectionnées pour la location (tableau JSON)',
    `duree`          VARCHAR(5) NULL COMMENT 'Durée hh:mm saisie pour unités horaires (ex: 1:15) — NULL si non applicable',
    `nb_seances`     SMALLINT UNSIGNED NULL COMMENT 'Nombre brut de séances avant application du multiplicateur de durée — NULL si non applicable',
    `est_paye`       TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Calculé par cascade FIFO des paiements, mis en cache',
    `date_paiement`  DATE NULL COMMENT 'Date du paiement ayant soldé ce mois (cascade), mis en cache',
    `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_fdm_facture` (`id_facture`),
    KEY `idx_fdm_unite` (`id_unite`),
    KEY `idx_fdm_service` (`id_service`),
    CONSTRAINT `fk_fdm_facture` FOREIGN KEY (`id_facture`) REFERENCES `facture`(`id_facture`) ON DELETE CASCADE,
    CONSTRAINT `fk_fdm_unite` FOREIGN KEY (`id_unite`) REFERENCES `unites`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_fdm_service` FOREIGN KEY (`id_service`) REFERENCES `services`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Détail mensuel figé des factures de type confirmation de paiement (contrats au forfait)';

SELECT CASE WHEN COUNT(*) = 1 THEN '✅ Table facture_detail_mensuel créée/présente' ELSE '❌ Échec' END AS check_etape1
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'facture_detail_mensuel';

-- ============================================================================
-- ÉTAPE 2 : Nettoyage de paiement (retrait id_loyer / id_loyer_detail)
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 2 : Nettoyage de paiement ===' AS '';

SET @fk_loyer = (
    SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'paiement' AND COLUMN_NAME = 'id_loyer'
    AND REFERENCED_TABLE_NAME = 'loyer' LIMIT 1
);
SET @sql = IF(@fk_loyer IS NOT NULL,
    CONCAT('ALTER TABLE `paiement` DROP FOREIGN KEY `', @fk_loyer, '`'),
    'SELECT ''Pas de FK paiement->loyer à supprimer'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_loyer_detail = (
    SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'paiement' AND COLUMN_NAME = 'id_loyer_detail'
    AND REFERENCED_TABLE_NAME = 'loyer_detail' LIMIT 1
);
SET @sql = IF(@fk_loyer_detail IS NOT NULL,
    CONCAT('ALTER TABLE `paiement` DROP FOREIGN KEY `', @fk_loyer_detail, '`'),
    'SELECT ''Pas de FK paiement->loyer_detail à supprimer'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_id_loyer = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'paiement' AND COLUMN_NAME = 'id_loyer'
);
SET @sql = IF(@col_id_loyer > 0,
    'ALTER TABLE `paiement` DROP COLUMN `id_loyer`',
    'SELECT ''paiement.id_loyer déjà absente'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_id_loyer_detail = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'paiement' AND COLUMN_NAME = 'id_loyer_detail'
);
SET @sql = IF(@col_id_loyer_detail > 0,
    'ALTER TABLE `paiement` DROP COLUMN `id_loyer_detail`',
    'SELECT ''paiement.id_loyer_detail déjà absente'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT CASE WHEN COUNT(*) = 0
    THEN '✅ paiement nettoyée (id_loyer / id_loyer_detail absentes)'
    ELSE '❌ Colonnes encore présentes'
END AS check_etape2
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'paiement'
AND COLUMN_NAME IN ('id_loyer', 'id_loyer_detail');

-- ============================================================================
-- ÉTAPE 3 : Suppression de loyer_detail puis loyer
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 3 : Suppression loyer_detail / loyer ===' AS '';

DROP TABLE IF EXISTS `loyer_detail`;
DROP TABLE IF EXISTS `loyer`;

SELECT CASE WHEN COUNT(*) = 0
    THEN '✅ Tables loyer / loyer_detail supprimées'
    ELSE '❌ Une des deux tables existe encore'
END AS check_etape3
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('loyer', 'loyer_detail');

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;

SELECT '' AS '';
SELECT '============================================================================' AS '';
SELECT '✅ MIGRATION 046 TERMINÉE' AS '';
SELECT '============================================================================' AS '';