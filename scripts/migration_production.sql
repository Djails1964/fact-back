-- ============================================================================
-- SCRIPT DE MISE A JOUR: Production vers nouvelle structure
-- Date: 03/01/2026
-- ============================================================================
-- 
-- Ce script met a jour la structure de la base de production
-- et adapte les donnees existantes a la nouvelle structure.
--
-- INSTRUCTIONS:
-- 1. SAUVEGARDER la base de production AVANT execution
--    mysqldump -u root -p factlagrange_pp > backup_prod_avant_migration.sql
-- 2. Executer ce script sur la base de PRODUCTION
-- 3. Verifier avec les requetes de validation en fin de script
--
-- ============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

START TRANSACTION;

-- ============================================================================
-- PARTIE 1: MISE A JOUR DE LA STRUCTURE
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1.1 Table activity_logs
-- ----------------------------------------------------------------------------

-- Modifier action_type: ENUM -> VARCHAR(100)
ALTER TABLE `activity_logs` 
    MODIFY COLUMN `action_type` VARCHAR(100) NOT NULL;

-- Modifier severity: ENUM -> VARCHAR(20)
ALTER TABLE `activity_logs` 
    MODIFY COLUMN `severity` VARCHAR(20) NOT NULL DEFAULT 'info';

-- Modifier user_name: VARCHAR(100) -> VARCHAR(255)
ALTER TABLE `activity_logs` 
    MODIFY COLUMN `user_name` VARCHAR(255) DEFAULT NULL;

-- Ajouter nouveaux champs
ALTER TABLE `activity_logs` 
    ADD COLUMN IF NOT EXISTS `resolved_at` TIMESTAMP NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `resolved_by` INT(11) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `archived_at` TIMESTAMP NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `archived_by` INT(11) DEFAULT NULL;

SELECT 'Table activity_logs mise a jour' AS status;

-- ----------------------------------------------------------------------------
-- 1.2 Table facture - Ajout nouveaux champs
-- ----------------------------------------------------------------------------

ALTER TABLE `facture`
    ADD COLUMN IF NOT EXISTS `montant_brut` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `date_facture`,
    ADD COLUMN IF NOT EXISTS `date_dernier_paiement` DATETIME DEFAULT NULL AFTER `date_paiement`,
    ADD COLUMN IF NOT EXISTS `montant_paye_total` DECIMAL(10,2) DEFAULT 0.00 AFTER `montant_paye`,
    ADD COLUMN IF NOT EXISTS `montant_restant` DECIMAL(10,2) DEFAULT 0.00 AFTER `montant_paye_total`,
    ADD COLUMN IF NOT EXISTS `nb_paiements` INT(3) DEFAULT 0 AFTER `montant_restant`;

SELECT 'Table facture mise a jour' AS status;

-- ----------------------------------------------------------------------------
-- 1.3 Table parametres - Renommage des colonnes
-- ----------------------------------------------------------------------------

ALTER TABLE `parametres` 
    CHANGE COLUMN `Nom_parametre` `nom_parametre` VARCHAR(50) NOT NULL,
    CHANGE COLUMN `Valeur_parametre` `valeur_parametre` VARCHAR(255) DEFAULT NULL,
    CHANGE COLUMN `Annee_parametre` `annee_parametre` INT(11) DEFAULT NULL,
    CHANGE COLUMN `Groupe_parametre` `groupe_parametre` VARCHAR(50) NOT NULL,
    CHANGE COLUMN `sGroupe_parametre` `sous_groupe_parametre` VARCHAR(50) DEFAULT NULL,
    CHANGE COLUMN `Categorie` `categorie` VARCHAR(50) DEFAULT NULL;

SELECT 'Table parametres mise a jour' AS status;

-- ----------------------------------------------------------------------------
-- 1.4 Creation table paiement
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `paiement` (
    `id_paiement` INT(11) NOT NULL AUTO_INCREMENT,
    `id_facture` INT(11) NOT NULL,
    `date_paiement` DATE NOT NULL,
    `montant_paye` DECIMAL(10,2) NOT NULL,
    `methode_paiement` VARCHAR(50) NOT NULL DEFAULT 'virement',
    `commentaire` TEXT DEFAULT NULL,
    `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
    `date_annulation` TIMESTAMP NULL DEFAULT NULL,
    `motif_annulation` TEXT DEFAULT NULL,
    `date_modification` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(),
    `numero_paiement` INT(3) NOT NULL DEFAULT 1,
    `statut` ENUM('en_attente','confirme','annule') NOT NULL DEFAULT 'confirme',
    PRIMARY KEY (`id_paiement`),
    KEY `idx_facture` (`id_facture`),
    KEY `idx_date_paiement` (`date_paiement`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajouter la contrainte FK (ignore si existe deja)
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_NAME = 'fk_paiement_facture' 
    AND TABLE_NAME = 'paiement'
);

-- La FK sera ajoutee apres le commit si elle n'existe pas

SELECT 'Table paiement creee' AS status;

-- ============================================================================
-- PARTIE 2: MIGRATION DES DONNEES EXISTANTES
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 2.1 Calculer montant_brut pour toutes les factures
-- ----------------------------------------------------------------------------

UPDATE `facture` 
SET `montant_brut` = COALESCE(`montant_total`, 0) + COALESCE(`ristourne`, 0)
WHERE `montant_brut` = 0 OR `montant_brut` IS NULL;

SELECT CONCAT('montant_brut calcule pour ', ROW_COUNT(), ' factures') AS status;

-- ----------------------------------------------------------------------------
-- 2.2 Creer les paiements a partir des factures existantes
-- ----------------------------------------------------------------------------

-- Inserer un paiement pour chaque facture qui a un montant_paye > 0
INSERT INTO `paiement` (
    `id_facture`,
    `date_paiement`,
    `montant_paye`,
    `methode_paiement`,
    `commentaire`,
    `date_creation`,
    `numero_paiement`,
    `statut`
)
SELECT 
    `id_facture`,
    COALESCE(DATE(`date_paiement`), DATE(`date_facture`)) AS date_paiement,
    `montant_paye`,
    'virement' AS methode_paiement,
    'Paiement migre depuis ancienne structure' AS commentaire,
    COALESCE(`date_paiement`, NOW()) AS date_creation,
    1 AS numero_paiement,
    'confirme' AS statut
FROM `facture`
WHERE `montant_paye` > 0 
  AND `montant_paye` IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM `paiement` p WHERE p.id_facture = `facture`.id_facture
  );

SELECT CONCAT('Paiements crees: ', ROW_COUNT()) AS status;

-- ----------------------------------------------------------------------------
-- 2.3 Mettre a jour les champs calcules de facture
-- ----------------------------------------------------------------------------

-- Mettre a jour montant_paye_total, nb_paiements, date_dernier_paiement
UPDATE `facture` f
SET 
    `montant_paye_total` = (
        SELECT COALESCE(SUM(p.montant_paye), 0) 
        FROM `paiement` p 
        WHERE p.id_facture = f.id_facture 
        AND p.statut = 'confirme'
    ),
    `nb_paiements` = (
        SELECT COUNT(*) 
        FROM `paiement` p 
        WHERE p.id_facture = f.id_facture 
        AND p.statut = 'confirme'
    ),
    `date_dernier_paiement` = (
        SELECT MAX(p.date_creation) 
        FROM `paiement` p 
        WHERE p.id_facture = f.id_facture 
        AND p.statut = 'confirme'
    );

SELECT CONCAT('Totaux paiements mis a jour pour ', ROW_COUNT(), ' factures') AS status;

-- Mettre a jour montant_restant
UPDATE `facture` 
SET `montant_restant` = GREATEST(0, 
    COALESCE(`montant_total`, 0) - COALESCE(`ristourne`, 0) - COALESCE(`montant_paye_total`, 0)
);

SELECT CONCAT('montant_restant calcule pour ', ROW_COUNT(), ' factures') AS status;

-- Mettre a jour l'etat si necessaire (coherence)
UPDATE `facture`
SET `etat` = CASE 
    WHEN `date_annulation` IS NOT NULL THEN 'Annulée'
    WHEN `montant_restant` <= 0 AND `montant_paye_total` > 0 THEN 'Payée'
    WHEN `montant_paye_total` > 0 AND `montant_restant` > 0 THEN 'Partiellement payée'
    WHEN `date_envoi` IS NOT NULL THEN 'Envoyée'
    ELSE 'En attente'
END
WHERE `etat` NOT IN ('Annulée');

SELECT CONCAT('Etats factures mis a jour pour ', ROW_COUNT(), ' factures') AS status;

COMMIT;

-- ============================================================================
-- PARTIE 3: CREATION DES TRIGGERS
-- ============================================================================

-- Supprimer les anciens triggers si existants
DROP TRIGGER IF EXISTS `tr_paiement_after_insert`;
DROP TRIGGER IF EXISTS `tr_paiement_after_update`;
DROP TRIGGER IF EXISTS `tr_paiement_after_delete`;

DELIMITER $$

-- Trigger AFTER INSERT
CREATE TRIGGER `tr_paiement_after_insert` AFTER INSERT ON `paiement` FOR EACH ROW 
BEGIN
    UPDATE `facture` 
    SET 
        `montant_paye_total` = (
            SELECT COALESCE(SUM(montant_paye), 0) 
            FROM `paiement` 
            WHERE `id_facture` = NEW.id_facture 
            AND `statut` = 'confirme'
        ),
        `nb_paiements` = (
            SELECT COUNT(*) 
            FROM `paiement` 
            WHERE `id_facture` = NEW.id_facture 
            AND `statut` = 'confirme'
        ),
        `date_dernier_paiement` = NEW.date_creation,
        `montant_paye` = NEW.montant_paye
    WHERE `id_facture` = NEW.id_facture;
    
    UPDATE `facture` 
    SET 
        `montant_restant` = GREATEST(0, `montant_total` - COALESCE(`ristourne`, 0) - COALESCE(`montant_paye_total`, 0)),
        `etat` = CASE 
            WHEN GREATEST(0, `montant_total` - COALESCE(`ristourne`, 0) - COALESCE(`montant_paye_total`, 0)) <= 0 THEN 'Payée'
            WHEN COALESCE(`montant_paye_total`, 0) > 0 THEN 'Partiellement payée'
            ELSE `etat`
        END
    WHERE `id_facture` = NEW.id_facture;
END$$

-- Trigger AFTER UPDATE
CREATE TRIGGER `tr_paiement_after_update` AFTER UPDATE ON `paiement` FOR EACH ROW 
BEGIN
    UPDATE `facture` 
    SET 
        `montant_paye_total` = (
            SELECT COALESCE(SUM(montant_paye), 0) 
            FROM `paiement` 
            WHERE `id_facture` = NEW.id_facture 
            AND `statut` = 'confirme'
        ),
        `nb_paiements` = (
            SELECT COUNT(*) 
            FROM `paiement` 
            WHERE `id_facture` = NEW.id_facture 
            AND `statut` = 'confirme'
        ),
        `date_dernier_paiement` = (
            SELECT MAX(date_creation) 
            FROM `paiement` 
            WHERE `id_facture` = NEW.id_facture 
            AND `statut` = 'confirme'
        )
    WHERE `id_facture` = NEW.id_facture;
    
    UPDATE `facture` 
    SET 
        `montant_restant` = GREATEST(0, `montant_total` - COALESCE(`ristourne`, 0) - COALESCE(`montant_paye_total`, 0)),
        `etat` = CASE 
            WHEN GREATEST(0, `montant_total` - COALESCE(`ristourne`, 0) - COALESCE(`montant_paye_total`, 0)) <= 0 
                 AND COALESCE(`montant_paye_total`, 0) > 0 THEN 'Payée'
            WHEN COALESCE(`montant_paye_total`, 0) > 0 THEN 'Partiellement payée'
            WHEN COALESCE(`montant_paye_total`, 0) = 0 AND `date_envoi` IS NOT NULL THEN 'Envoyée'
            ELSE `etat`
        END
    WHERE `id_facture` = NEW.id_facture;
END$$

-- Trigger AFTER DELETE
CREATE TRIGGER `tr_paiement_after_delete` AFTER DELETE ON `paiement` FOR EACH ROW 
BEGIN
    UPDATE `facture` 
    SET 
        `montant_paye_total` = (
            SELECT COALESCE(SUM(montant_paye), 0) 
            FROM `paiement` 
            WHERE `id_facture` = OLD.id_facture 
            AND `statut` = 'confirme'
        ),
        `nb_paiements` = (
            SELECT COUNT(*) 
            FROM `paiement` 
            WHERE `id_facture` = OLD.id_facture 
            AND `statut` = 'confirme'
        ),
        `date_dernier_paiement` = (
            SELECT MAX(date_creation) 
            FROM `paiement` 
            WHERE `id_facture` = OLD.id_facture 
            AND `statut` = 'confirme'
        ),
        `montant_paye` = (
            SELECT montant_paye 
            FROM `paiement` 
            WHERE `id_facture` = OLD.id_facture 
            AND `statut` = 'confirme'
            ORDER BY date_creation DESC 
            LIMIT 1
        )
    WHERE `id_facture` = OLD.id_facture;
    
    UPDATE `facture` 
    SET 
        `montant_restant` = GREATEST(0, `montant_total` - COALESCE(`ristourne`, 0) - COALESCE(`montant_paye_total`, 0)),
        `etat` = CASE 
            WHEN GREATEST(0, `montant_total` - COALESCE(`montant_paye_total`, 0)) <= 0 
                 AND COALESCE(`montant_paye_total`, 0) > 0 THEN 'Payée'
            WHEN COALESCE(`montant_paye_total`, 0) > 0 THEN 'Partiellement payée'
            WHEN COALESCE(`montant_paye_total`, 0) = 0 AND `date_envoi` IS NOT NULL THEN 'Envoyée'
            ELSE `etat`
        END
    WHERE `id_facture` = OLD.id_facture;
END$$

DELIMITER ;

SELECT 'Triggers crees' AS status;

-- Ajouter la FK si elle n'existe pas
ALTER TABLE `paiement` 
    ADD CONSTRAINT `fk_paiement_facture` 
    FOREIGN KEY (`id_facture`) REFERENCES `facture` (`id_facture`) 
    ON DELETE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'Contrainte FK ajoutee' AS status;

-- ============================================================================
-- PARTIE 4: VALIDATION
-- ============================================================================

SELECT '=== VALIDATION ===' AS info;

-- Comptage des enregistrements
SELECT 'client' AS tbl, COUNT(*) AS nb FROM `client`
UNION ALL SELECT 'utilisateurs', COUNT(*) FROM `utilisateurs`
UNION ALL SELECT 'facture', COUNT(*) FROM `facture`
UNION ALL SELECT 'lignesfacture', COUNT(*) FROM `lignesfacture`
UNION ALL SELECT 'paiement', COUNT(*) FROM `paiement`
UNION ALL SELECT 'services', COUNT(*) FROM `services`
UNION ALL SELECT 'parametres', COUNT(*) FROM `parametres`;

-- Verification coherence paiements
SELECT '=== COHERENCE PAIEMENTS ===' AS info;

SELECT 
    COUNT(*) AS nb_factures_coherentes
FROM `facture` f
WHERE ABS(
    COALESCE(f.montant_paye_total, 0) - 
    COALESCE((SELECT SUM(p.montant_paye) FROM `paiement` p WHERE p.id_facture = f.id_facture AND p.statut = 'confirme'), 0)
) < 0.01;

-- Verification montant_restant
SELECT '=== COHERENCE MONTANT RESTANT ===' AS info;

SELECT 
    COUNT(*) AS nb_factures_ok
FROM `facture`
WHERE ABS(
    `montant_restant` - 
    GREATEST(0, COALESCE(`montant_total`, 0) - COALESCE(`ristourne`, 0) - COALESCE(`montant_paye_total`, 0))
) < 0.01;

SELECT '=== MIGRATION TERMINEE ===' AS info;
