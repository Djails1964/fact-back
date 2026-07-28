-- Migration 047 : Numérotation séparée pour les confirmations de paiement
--
-- Jusqu'ici, factures standard ET confirmations de paiement (contrats au
-- forfait) partageaient la même séquence 'Prochain Numéro Facture'.
-- Cette migration crée la séquence indépendante 'Prochain Numéro
-- Confirmation' (même groupe_parametre/sous_groupe_parametre 'Facture' /
-- 'Numéro', nom_parametre distinct) — voir
-- FactureControleur::allouerNumeroFacture($conn, $annee, $estConfirmation).
--
-- Démarre à 1 pour l'année courante. Une nouvelle ligne devra être créée
-- chaque année (via l'écran Paramètres, comme pour 'Prochain Numéro
-- Facture' — pas de rollover automatique).

INSERT INTO parametres (nom_parametre, valeur_parametre, annee_parametre, groupe_parametre, sous_groupe_parametre, categorie)
SELECT 'Prochain Numéro Confirmation', '1', YEAR(CURDATE()), 'Facture', 'Numéro', NULL
WHERE NOT EXISTS (
    SELECT 1 FROM parametres
    WHERE nom_parametre = 'Prochain Numéro Confirmation'
      AND groupe_parametre = 'Facture'
      AND sous_groupe_parametre = 'Numéro'
      AND annee_parametre = YEAR(CURDATE())
);