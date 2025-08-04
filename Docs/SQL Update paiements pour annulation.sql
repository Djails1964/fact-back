-- ✅ Ajout des colonnes pour l'annulation
ALTER TABLE paiement 
ADD COLUMN date_annulation TIMESTAMP NULL DEFAULT NULL AFTER date_creation,
ADD COLUMN motif_annulation TEXT NULL DEFAULT NULL AFTER date_annulation,
ADD COLUMN date_modification TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER motif_annulation;

-- ✅ Mise à jour du trigger pour ne compter que les paiements confirmés
DELIMITER //

CREATE OR REPLACE TRIGGER update_facture_totals_after_paiement_insert
AFTER INSERT ON paiement
FOR EACH ROW
BEGIN
    IF NEW.statut = 'confirme' THEN
        UPDATE facture 
        SET 
            montant_paye_total = (
                SELECT COALESCE(SUM(montant_paye), 0) 
                FROM paiement 
                WHERE id_facture = NEW.id_facture AND statut = 'confirme'
            ),
            nb_paiements = (
                SELECT COUNT(*) 
                FROM paiement 
                WHERE id_facture = NEW.id_facture AND statut = 'confirme'
            ),
            date_dernier_paiement = (
                SELECT MAX(date_paiement) 
                FROM paiement 
                WHERE id_facture = NEW.id_facture AND statut = 'confirme'
            )
        WHERE id_facture = NEW.id_facture;
        
        -- Recalculer montant_restant et etat
        UPDATE facture 
        SET 
            montant_restant = montant_total - montant_paye_total,
            etat = CASE 
                WHEN montant_paye_total >= montant_total THEN 'Payée'
                WHEN montant_paye_total > 0 THEN 'Partiellement payée'
                ELSE etat
            END
        WHERE id_facture = NEW.id_facture;
    END IF;
END//

CREATE OR REPLACE TRIGGER update_facture_totals_after_paiement_update
AFTER UPDATE ON paiement
FOR EACH ROW
BEGIN
    -- Recalculer les totaux (que le paiement soit confirmé ou annulé)
    UPDATE facture 
    SET 
        montant_paye_total = (
            SELECT COALESCE(SUM(montant_paye), 0) 
            FROM paiement 
            WHERE id_facture = NEW.id_facture AND statut = 'confirme'
        ),
        nb_paiements = (
            SELECT COUNT(*) 
            FROM paiement 
            WHERE id_facture = NEW.id_facture AND statut = 'confirme'
        ),
        date_dernier_paiement = (
            SELECT MAX(date_paiement) 
            FROM paiement 
            WHERE id_facture = NEW.id_facture AND statut = 'confirme'
        )
    WHERE id_facture = NEW.id_facture;
    
    -- Recalculer montant_restant et etat
    UPDATE facture 
    SET 
        montant_restant = montant_total - montant_paye_total,
        etat = CASE 
            WHEN montant_paye_total >= montant_total THEN 'Payée'
            WHEN montant_paye_total > 0 THEN 'Partiellement payée'
            WHEN etat = 'Payée' OR etat = 'Partiellement payée' THEN 'Envoyée'
            ELSE etat
        END
    WHERE id_facture = NEW.id_facture;
END//

DELIMITER ;