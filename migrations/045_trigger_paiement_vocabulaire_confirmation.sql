-- ============================================================================
-- Migration 045 : Trigger paiement — vocabulaire d'état confirmation/facture
--
-- Contexte : les confirmations de paiement (contrats au forfait) utilisent un
--            vocabulaire d'état différent des factures standard :
--              - Facture standard : En attente → Éditée → Envoyée → (Retard)
--                                   → Payée / Partiellement payée
--              - Confirmation      : Non payé → Partiellement payée → Payée
--
--            Le trigger tr_paiement_after_insert/update/delete (recréé en
--            migration 044) retombe sur 'Envoyée' quand montant_paye_total
--            est à 0 — correct pour une facture standard, mais incorrect
--            pour une confirmation (qui doit retomber sur 'Non payé').
--
--            Cette migration distingue les deux cas via la présence de
--            lignes dans facture_detail_mensuel (confirmation) ou non
--            (facture standard).
-- ============================================================================

SET NAMES utf8mb4;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

SELECT '============================================================================' AS '';
SELECT 'Migration 045: Trigger paiement — vocabulaire confirmation/facture' AS '';
SELECT '============================================================================' AS '';

SELECT '' AS '';
SELECT '=== Recréation des triggers avec état conditionnel ===' AS '';

DROP TRIGGER IF EXISTS `tr_paiement_after_insert`;
DROP TRIGGER IF EXISTS `tr_paiement_after_update`;
DROP TRIGGER IF EXISTS `tr_paiement_after_delete`;

DELIMITER $$
CREATE TRIGGER `tr_paiement_after_insert`
AFTER INSERT ON `paiement`
FOR EACH ROW
BEGIN
    IF NEW.id_facture IS NOT NULL AND NEW.statut = 'confirme' THEN
        UPDATE `facture`
        SET
            `montant_paye_total`    = (SELECT COALESCE(SUM(montant_paye), 0) FROM `paiement` WHERE `id_facture` = NEW.id_facture AND `statut` = 'confirme'),
            `montant_restant`       = `montant_total` - `montant_paye_total`,
            `etat`                  = CASE
                                          WHEN (`montant_total` - `montant_paye_total`) <= 0 THEN 'Payée'
                                          WHEN `montant_paye_total` > 0                       THEN 'Partiellement payée'
                                          WHEN EXISTS (SELECT 1 FROM `facture_detail_mensuel` fdm WHERE fdm.id_facture = NEW.id_facture)
                                                                                              THEN 'Non payé'
                                          ELSE 'Envoyée'
                                      END,
            `nb_paiements`          = (SELECT COUNT(*) FROM `paiement` WHERE `id_facture` = NEW.id_facture AND `statut` = 'confirme'),
            `date_dernier_paiement` = NEW.date_paiement
        WHERE `id_facture` = NEW.id_facture;
    END IF;
END$$
DELIMITER ;

DELIMITER $$
CREATE TRIGGER `tr_paiement_after_update`
AFTER UPDATE ON `paiement`
FOR EACH ROW
BEGIN
    IF OLD.id_facture IS NOT NULL THEN
        UPDATE `facture`
        SET
            `montant_paye_total` = (SELECT COALESCE(SUM(montant_paye), 0) FROM `paiement` WHERE `id_facture` = OLD.id_facture AND `statut` = 'confirme'),
            `montant_restant`    = `montant_total` - `montant_paye_total`,
            `etat`               = CASE
                                        WHEN (`montant_total` - `montant_paye_total`) <= 0 THEN 'Payée'
                                        WHEN `montant_paye_total` > 0                       THEN 'Partiellement payée'
                                        WHEN EXISTS (SELECT 1 FROM `facture_detail_mensuel` fdm WHERE fdm.id_facture = OLD.id_facture)
                                                                                            THEN 'Non payé'
                                        ELSE 'Envoyée'
                                    END,
            `nb_paiements`       = (SELECT COUNT(*) FROM `paiement` WHERE `id_facture` = OLD.id_facture AND `statut` = 'confirme')
        WHERE `id_facture` = OLD.id_facture;
    END IF;
    IF NEW.id_facture IS NOT NULL AND (OLD.id_facture IS NULL OR NEW.id_facture <> OLD.id_facture) THEN
        UPDATE `facture`
        SET
            `montant_paye_total`    = (SELECT COALESCE(SUM(montant_paye), 0) FROM `paiement` WHERE `id_facture` = NEW.id_facture AND `statut` = 'confirme'),
            `montant_restant`       = `montant_total` - `montant_paye_total`,
            `etat`                  = CASE
                                          WHEN (`montant_total` - `montant_paye_total`) <= 0 THEN 'Payée'
                                          WHEN `montant_paye_total` > 0                       THEN 'Partiellement payée'
                                          WHEN EXISTS (SELECT 1 FROM `facture_detail_mensuel` fdm WHERE fdm.id_facture = NEW.id_facture)
                                                                                              THEN 'Non payé'
                                          ELSE 'Envoyée'
                                      END,
            `nb_paiements`          = (SELECT COUNT(*) FROM `paiement` WHERE `id_facture` = NEW.id_facture AND `statut` = 'confirme'),
            `date_dernier_paiement` = NEW.date_paiement
        WHERE `id_facture` = NEW.id_facture;
    END IF;
END$$
DELIMITER ;

DELIMITER $$
CREATE TRIGGER `tr_paiement_after_delete`
AFTER DELETE ON `paiement`
FOR EACH ROW
BEGIN
    IF OLD.id_facture IS NOT NULL THEN
        UPDATE `facture`
        SET
            `montant_paye_total` = (SELECT COALESCE(SUM(montant_paye), 0) FROM `paiement` WHERE `id_facture` = OLD.id_facture AND `statut` = 'confirme'),
            `montant_restant`    = `montant_total` - `montant_paye_total`,
            `etat`               = CASE
                                        WHEN (`montant_total` - `montant_paye_total`) <= 0 THEN 'Payée'
                                        WHEN `montant_paye_total` > 0                       THEN 'Partiellement payée'
                                        WHEN EXISTS (SELECT 1 FROM `facture_detail_mensuel` fdm WHERE fdm.id_facture = OLD.id_facture)
                                                                                            THEN 'Non payé'
                                        ELSE 'Envoyée'
                                    END,
            `nb_paiements`       = (SELECT COUNT(*) FROM `paiement` WHERE `id_facture` = OLD.id_facture AND `statut` = 'confirme')
        WHERE `id_facture` = OLD.id_facture;
    END IF;
END$$
DELIMITER ;

SELECT
    CASE WHEN COUNT(*) = 3
        THEN '✅ Triggers recréés avec vocabulaire d''état conditionnel (confirmation/facture)'
        ELSE CONCAT('❌ Seulement ', COUNT(*), '/3 triggers présents')
    END AS check_triggers
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE()
AND TRIGGER_NAME IN ('tr_paiement_after_insert', 'tr_paiement_after_update', 'tr_paiement_after_delete');

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;

SELECT '' AS '';
SELECT '============================================================================' AS '';
SELECT '✅ MIGRATION 045 TERMINÉE' AS '';
SELECT '============================================================================' AS '';