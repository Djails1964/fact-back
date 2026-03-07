-- 1. DESACTIVATION DES CONTRAINTES ET SUPPRESSION DES TRIGGERS CONNUS
SET FOREIGN_KEY_CHECKS = 0;

DROP TRIGGER IF EXISTS `tr_paiement_after_insert`;
DROP TRIGGER IF EXISTS `tr_paiement_after_update`;
DROP TRIGGER IF EXISTS `tr_paiement_after_delete`;
DROP TRIGGER IF EXISTS `update_facture_totals_after_paiement_insert`;
DROP TRIGGER IF EXISTS `update_facture_totals_after_paiement_update`;
DROP TRIGGER IF EXISTS `update_facture_totals_after_paiement_delete`;

-- 2. ADAPTATION STRUCTURE PAIEMENT
ALTER TABLE `paiement` DROP COLUMN IF EXISTS `id_client`;
ALTER TABLE `paiement` ADD COLUMN `id_client` INT(11) NULL AFTER `id_paiement`;

-- 3. MIGRATION DES DONNÉES (VIA TABLE TEMPORAIRE POUR ÉVITER 1442)
-- On utilise une table temporaire pour "casser" la dépendance directe entre les tables
CREATE TEMPORARY TABLE tmp_migration AS
SELECT p.id_paiement, f.id_client
FROM `paiement` p
INNER JOIN `facture` f ON p.id_facture = f.id_facture;

UPDATE `paiement` p
INNER JOIN tmp_migration tmp ON p.id_paiement = tmp.id_paiement
SET p.id_client = tmp.id_client;

DROP TEMPORARY TABLE tmp_migration;

-- 4. FINALISATION STRUCTURE
ALTER TABLE `paiement` MODIFY COLUMN `id_client` INT(11) NOT NULL;
ALTER TABLE `paiement` MODIFY COLUMN `id_facture` INT(11) NULL;

-- Ajout de la clé étrangère
ALTER TABLE `paiement` 
ADD CONSTRAINT `fk_paiement_client` 
FOREIGN KEY (`id_client`) REFERENCES `client` (`id`) 
ON DELETE CASCADE ON UPDATE CASCADE;

-- Réactivation des vérifications
SET FOREIGN_KEY_CHECKS = 1;

-- 5. CRÉATION DES NOUVEAUX TRIGGERS (LOGIQUE ÉTAT 'Envoyée' ET 'Confirme')
DELIMITER $$

CREATE TRIGGER `tr_paiement_after_insert` AFTER INSERT ON `paiement` FOR EACH ROW 
BEGIN
    IF NEW.id_facture IS NOT NULL AND NEW.statut = 'Confirme' THEN
        UPDATE `facture` 
        SET 
            `montant_paye_total` = (SELECT COALESCE(SUM(montant_paye), 0) FROM `paiement` WHERE `id_facture` = NEW.id_facture AND `statut` = 'Confirme'),
            `montant_restant` = `montant_total` - `montant_paye_total`,
            `etat` = CASE 
                WHEN (`montant_total` - `montant_paye_total`) <= 0 THEN 'Payée'
                WHEN `montant_paye_total` > 0 THEN 'Partiellement payée'
                ELSE 'Envoyée'
            END,
            `nb_paiements` = (SELECT COUNT(*) FROM `paiement` WHERE `id_facture` = NEW.id_facture AND `statut` = 'Confirme'),
            `date_dernier_paiement` = NEW.date_paiement
        WHERE `id_facture` = NEW.id_facture;
    END IF;
END $$

CREATE TRIGGER `tr_paiement_after_update` AFTER UPDATE ON `paiement` FOR EACH ROW 
BEGIN
    IF OLD.id_facture IS NOT NULL THEN
        UPDATE `facture` SET 
            `montant_paye_total` = (SELECT COALESCE(SUM(montant_paye), 0) FROM `paiement` WHERE `id_facture` = OLD.id_facture AND `statut` = 'Confirme'),
            `montant_restant` = `montant_total` - `montant_paye_total`,
            `etat` = CASE WHEN (`montant_total` - `montant_paye_total`) <= 0 THEN 'Payée' WHEN `montant_paye_total` > 0 THEN 'Partiellement payée' ELSE 'Envoyée' END,
            `nb_paiements` = (SELECT COUNT(*) FROM `paiement` WHERE `id_facture` = OLD.id_facture AND `statut` = 'Confirme')
        WHERE `id_facture` = OLD.id_facture;
    END IF;
    IF NEW.id_facture IS NOT NULL AND (OLD.id_facture IS NULL OR NEW.id_facture <> OLD.id_facture) THEN
        UPDATE `facture` SET 
            `montant_paye_total` = (SELECT COALESCE(SUM(montant_paye), 0) FROM `paiement` WHERE `id_facture` = NEW.id_facture AND `statut` = 'Confirme'),
            `montant_restant` = `montant_total` - `montant_paye_total`,
            `etat` = CASE WHEN (`montant_total` - `montant_paye_total`) <= 0 THEN 'Payée' WHEN `montant_paye_total` > 0 THEN 'Partiellement payée' ELSE 'Envoyée' END,
            `nb_paiements` = (SELECT COUNT(*) FROM `paiement` WHERE `id_facture` = NEW.id_facture AND `statut` = 'Confirme')
        WHERE `id_facture` = NEW.id_facture;
    END IF;
END $$

CREATE TRIGGER `tr_paiement_after_delete` AFTER DELETE ON `paiement` FOR EACH ROW 
BEGIN
    IF OLD.id_facture IS NOT NULL THEN
        UPDATE `facture` SET 
            `montant_paye_total` = (SELECT COALESCE(SUM(montant_paye), 0) FROM `paiement` WHERE `id_facture` = OLD.id_facture AND `statut` = 'Confirme'),
            `montant_restant` = `montant_total` - `montant_paye_total`,
            `etat` = CASE 
                WHEN (`montant_total` - `montant_paye_total`) <= 0 THEN 'Payée'
                WHEN `montant_paye_total` > 0 THEN 'Partiellement payée'
                ELSE 'Envoyée'
            END,
            `nb_paiements` = (SELECT COUNT(*) FROM `paiement` WHERE `id_facture` = OLD.id_facture AND `statut` = 'Confirme')
        WHERE `id_facture` = OLD.id_facture;
    END IF;
END $$

DELIMITER ;