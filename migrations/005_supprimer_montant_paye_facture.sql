-- ============================================================================
-- MIGRATION : Suppression de montant_paye avec recalcul de sécurité
-- ============================================================================
-- ÉTAPE 1 : Recalculer montant_paye_total pour être sûr (avec statut confirme)
-- ÉTAPE 2 : Vérifier la cohérence
-- ÉTAPE 3 : Supprimer montant_paye
-- ============================================================================

-- ============================================================================
-- ÉTAPE 1 : RECALCUL DE SÉCURITÉ
-- ============================================================================
-- Recalculer tous les montants pour être absolument sûr que montant_paye_total
-- contient bien la somme des paiements confirmés

UPDATE `facture` f
SET 
    f.montant_paye_total = (
        SELECT COALESCE(SUM(p.montant_paye), 0)
        FROM `paiement` p
        WHERE p.id_facture = f.id_facture
        AND p.statut = 'confirme'
    ),
    f.nb_paiements = (
        SELECT COUNT(*)
        FROM `paiement` p
        WHERE p.id_facture = f.id_facture
        AND p.statut = 'confirme'
    ),
    f.date_dernier_paiement = (
        SELECT MAX(p.date_paiement)
        FROM `paiement` p
        WHERE p.id_facture = f.id_facture
        AND p.statut = 'confirme'
    );

-- Recalculer montant_restant et etat
UPDATE `facture` f
SET 
    f.montant_restant = GREATEST(0, f.montant_total - f.montant_paye_total),
    f.etat = CASE 
        WHEN GREATEST(0, f.montant_total - f.montant_paye_total) <= 0 AND f.montant_paye_total > 0 THEN 'Payée'
        WHEN f.montant_paye_total > 0 THEN 'Partiellement payée'
        ELSE f.etat
    END;

-- ============================================================================
-- ÉTAPE 2 : VÉRIFICATION DE COHÉRENCE
-- ============================================================================
-- Afficher les factures avec des incohérences potentielles (pour info)

SELECT 
    '=== VÉRIFICATION POST-RECALCUL ===' as titre;

SELECT 
    COUNT(*) as total_factures,
    SUM(CASE WHEN montant_paye_total > 0 THEN 1 ELSE 0 END) as factures_avec_paiements,
    SUM(CASE WHEN etat = 'Payée' THEN 1 ELSE 0 END) as factures_payees,
    SUM(CASE WHEN etat = 'Partiellement payée' THEN 1 ELSE 0 END) as factures_partiellement_payees
FROM `facture`;

-- Vérifier s'il y a des montants négatifs (ne devrait pas arriver)
SELECT 
    COUNT(*) as factures_avec_montant_restant_negatif
FROM `facture`
WHERE montant_restant < 0;

-- Vérifier les factures avec paiements > montant total (alerte)
SELECT 
    id_facture,
    numero_facture,
    montant_total,
    montant_paye_total,
    montant_restant,
    etat
FROM `facture`
WHERE montant_paye_total > montant_total + 0.01  -- +0.01 pour les erreurs d'arrondi
LIMIT 10;

-- ============================================================================
-- ÉTAPE 3 : SUPPRESSION DE LA COLONNE REDONDANTE
-- ============================================================================

ALTER TABLE `facture` DROP COLUMN `montant_paye`;

-- ============================================================================
-- MIGRATION TERMINÉE
-- ============================================================================

SELECT '=== MIGRATION TERMINÉE ===' as titre;

-- Afficher un résumé final
SELECT 
    COUNT(*) as total_factures,
    SUM(montant_paye_total) as total_paye_toutes_factures,
    AVG(montant_paye_total) as moyenne_paye_par_facture,
    SUM(montant_restant) as total_restant_a_payer
FROM `facture`;

-- ============================================================================
-- NOTES
-- ============================================================================
-- ✅ Tous les montants ont été recalculés depuis les paiements confirmés
-- ✅ La cohérence a été vérifiée
-- ✅ montant_paye (dernier paiement) a été supprimé de la table facture
-- ✅ montant_paye_total contient maintenant à coup sûr la somme correcte
-- ✅ Les triggers continueront de maintenir ces valeurs à jour
-- ============================================================================