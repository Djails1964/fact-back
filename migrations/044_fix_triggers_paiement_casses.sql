-- ============================================================================
-- Migration 044 : Correction urgente des triggers `paiement` cassés
--
-- Contexte : la migration 042 a supprimé les tables loyer/loyer_detail ainsi
--            que les colonnes paiement.id_loyer / paiement.id_loyer_detail.
--            Or plusieurs triggers sur `paiement` référençaient encore ces
--            colonnes/tables (trg_paiement_xor_insert, trg_paiement_xor_update,
--            tr_paiement_after_insert/update/delete) :
--              - trg_paiement_xor_* lisent NEW.id_loyer (colonne inexistante)
--              - tr_paiement_after_* lisent NEW.id_loyer_detail et mettent à
--                jour les tables loyer / loyer_detail (inexistantes)
--
--            ⚠️ Depuis la migration 042, TOUT INSERT/UPDATE/DELETE sur
--            `paiement` échoue avec une erreur "Unknown column 'id_loyer'".
--            Cette migration corrige ça en urgence.
--
-- La cascade mensuelle (facture_detail_mensuel.est_paye / date_paiement pour
-- les confirmations de paiement) N'EST PAS gérée ici par trigger — c'est un
-- algorithme FIFO à deux niveaux (mois × paiements dans l'ordre chronologique)
-- volontairement implémenté en PHP (FactureControleur::recalculerCascadeMensuelle),
-- plus simple à maintenir/tester qu'un curseur SQL. Cette migration se limite
-- à réparer le comportement déjà existant (totaux/état de la facture).
-- ============================================================================

SET NAMES utf8mb4;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

SELECT '============================================================================' AS '';
SELECT 'Migration 044: Correction des triggers paiement cassés' AS '';
SELECT '============================================================================' AS '';

-- ============================================================================
-- ÉTAPE 1 : Suppression des triggers XOR facture/loyer (obsolètes)
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 1 : Suppression triggers XOR ===' AS '';

DROP TRIGGER IF EXISTS `trg_paiement_xor_insert`;
DROP TRIGGER IF EXISTS `trg_paiement_xor_update`;

SELECT CASE WHEN COUNT(*) = 0 THEN '✅ Triggers XOR supprimés' ELSE '❌ Encore présents' END AS check_xor
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE()
AND TRIGGER_NAME IN ('trg_paiement_xor_insert', 'trg_paiement_xor_update');

-- ============================================================================
-- ÉTAPE 2 : Recréation des triggers AFTER INSERT/UPDATE/DELETE (facture seule)
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 2 : Triggers AFTER INSERT/UPDATE/DELETE (facture uniquement) ===' AS '';

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
            `etat`               = CASE WHEN (`montant_total` - `montant_paye_total`) <= 0 THEN 'Payée' WHEN `montant_paye_total` > 0 THEN 'Partiellement payée' ELSE 'Envoyée' END,
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
            `etat`               = CASE WHEN (`montant_total` - `montant_paye_total`) <= 0 THEN 'Payée' WHEN `montant_paye_total` > 0 THEN 'Partiellement payée' ELSE 'Envoyée' END,
            `nb_paiements`       = (SELECT COUNT(*) FROM `paiement` WHERE `id_facture` = OLD.id_facture AND `statut` = 'confirme')
        WHERE `id_facture` = OLD.id_facture;
    END IF;
END$$
DELIMITER ;

SELECT
    CASE WHEN COUNT(*) = 3
        THEN '✅ Triggers tr_paiement_after_insert/update/delete recréés (facture uniquement)'
        ELSE CONCAT('❌ Seulement ', COUNT(*), '/3 triggers présents')
    END AS check_triggers_after
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE()
AND TRIGGER_NAME IN ('tr_paiement_after_insert', 'tr_paiement_after_update', 'tr_paiement_after_delete');

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;

SELECT '' AS '';
SELECT '============================================================================' AS '';
SELECT '✅ MIGRATION 044 TERMINÉE' AS '';
SELECT 'Rappel : la cascade mensuelle (facture_detail_mensuel) est gérée en PHP,' AS '';
SELECT 'via FactureControleur::recalculerCascadeMensuelle(), pas par trigger.' AS '';
SELECT '============================================================================' AS '';