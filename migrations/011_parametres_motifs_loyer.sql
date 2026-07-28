-- ============================================================================
-- Migration 011 : Paramètres motifs de loyer
-- ============================================================================
-- Date: 2026-03-11
-- Objectif: Ajouter les motifs de loyer gérables dans les paramètres
-- Architecture:
--   groupe_parametre  = 'Loyer'
--   sous_groupe       = 'Motifs'
--   categorie         = 'Cabinet' | 'Salle'
--   nom_parametre     = 'motifs'        → liste séparée par | (ex: "Motif A|Motif B")
--   nom_parametre     = 'motif_defaut'  → nom du motif sélectionné par défaut
--
-- NOTE: La contrainte unique_parametre porte sur (nom_parametre, groupe, sous_groupe, categorie).
--       On stocke donc TOUS les motifs d'une catégorie dans une seule valeur séparée par |.
-- ============================================================================

SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

-- ============================================================================
-- ÉTAPE 1 : Nettoyage (idempotent)
-- ============================================================================

DELETE FROM parametres
WHERE groupe_parametre = 'Loyer'
  AND sous_groupe_parametre = 'Motifs';

-- ============================================================================
-- ÉTAPE 2 : Motifs Cabinet
-- ============================================================================

INSERT INTO parametres (nom_parametre, valeur_parametre, groupe_parametre, sous_groupe_parametre, categorie)
VALUES
  ('motifs',       'Location d''un cabinet|Location d''un cabinet de consultation|Sous-location cabinet',
                   'Loyer', 'Motifs', 'Cabinet'),
  ('motifDefaut', 'Location d''un cabinet',
                   'Loyer', 'Motifs', 'Cabinet');

-- ============================================================================
-- ÉTAPE 3 : Motifs Salle
-- ============================================================================

INSERT INTO parametres (nom_parametre, valeur_parametre, groupe_parametre, sous_groupe_parametre, categorie)
VALUES
  ('motifs',       'Location d''une salle|Location d''une salle de thérapie|Location d''une salle de formation',
                   'Loyer', 'Motifs', 'Salle'),
  ('motifDefaut', 'Location d''une salle',
                   'Loyer', 'Motifs', 'Salle');

-- ============================================================================
-- ÉTAPE 4 : Vérification
-- ============================================================================

SELECT categorie, nom_parametre, valeur_parametre
FROM parametres
WHERE groupe_parametre = 'Loyer'
  AND sous_groupe_parametre = 'Motifs'
ORDER BY categorie, nom_parametre;

SELECT
    CASE WHEN COUNT(*) = 4
        THEN '✅ 4 paramètres motifs insérés'
        ELSE CONCAT('❌ Nombre inattendu : ', COUNT(*), ' paramètres')
    END AS resultat
FROM parametres
WHERE groupe_parametre = 'Loyer'
  AND sous_groupe_parametre = 'Motifs';

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;

SELECT '✅ MIGRATION 011 TERMINÉE' AS migration_status;