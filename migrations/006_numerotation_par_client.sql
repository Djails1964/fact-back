-- ============================================================================
-- MIGRATION 006 : Numérotation par CLIENT (au lieu de par facture)
-- ============================================================================
-- La date de paiement est une date SAISIE par l'utilisateur
-- donc on garde le type DATE (pas besoin de DATETIME)
-- On change juste la numérotation : par FACTURE → par CLIENT
-- ============================================================================

-- ============================================================================
-- RENUMÉROTATION PAR CLIENT
-- ============================================================================

-- Créer une table temporaire avec la nouvelle numérotation basée sur CLIENT
CREATE TEMPORARY TABLE tmp_numerotation AS
SELECT 
    p.id_paiement,
    p.id_client,
    ROW_NUMBER() OVER (
        PARTITION BY p.id_client 
        ORDER BY p.date_paiement ASC, p.id_paiement ASC
    ) as nouveau_numero
FROM `paiement` p;

-- Mettre à jour les numéros de paiement
UPDATE `paiement` p
INNER JOIN tmp_numerotation tmp ON p.id_paiement = tmp.id_paiement
SET p.numero_paiement = tmp.nouveau_numero;

-- Nettoyer
DROP TEMPORARY TABLE tmp_numerotation;

-- ============================================================================
-- MIGRATION TERMINÉE
-- ============================================================================
-- ✅ Numérotation changée : par FACTURE → par CLIENT
-- ✅ Chaque client a sa propre séquence : 1, 2, 3, 4...
-- ✅ date_paiement reste en DATE (date saisie par l'utilisateur)
-- 
-- EXEMPLE DE RÉSULTAT :
-- Client 5 :
--   Paiement #1 : 2024-05-15 (facture 101)
--   Paiement #2 : 2024-08-20 (facture 105)
--   Paiement #3 : 2025-01-10 (paiement libre)
--   Paiement #4 : 2025-02-03 (facture 110)
-- 
-- Client 8 :
--   Paiement #1 : 2024-06-12 (facture 102) ← Numérotation indépendante
--   Paiement #2 : 2025-01-25 (facture 108)
-- ============================================================================