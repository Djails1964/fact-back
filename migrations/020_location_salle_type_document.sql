-- ============================================================================
-- Migration 020 : Paramètre type_document par salle de location
-- ============================================================================
-- Date: 2026-03-19
-- Contexte:
--   Chaque salle de location peut générer soit une facture, soit une
--   confirmation de paiement. Ce comportement est désormais configurable
--   par salle dans les paramètres (LocationSalle > Salles).
--
-- Architecture:
--   groupe_parametre      = 'LocationSalle'
--   sous_groupe_parametre = 'Salles'
--   categorie             = <nom de la salle> (ex: 'Cabinet', 'Salle')
--   nom_parametre         = 'type_document'
--   valeur_parametre      = 'facture' | 'confirmation'
--
-- Valeur par défaut : 'facture' (comportement existant préservé)
--
-- Dépend de: migration 010 (structure location_salle), 015 (type_client_requis)
-- ============================================================================

SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

SELECT '============================================================================' AS '';
SELECT 'Migration 020: Paramètre type_document par salle de location' AS '';
SELECT '============================================================================' AS '';

-- ============================================================================
-- ÉTAPE 1 : Nettoyage idempotent
-- Supprime uniquement le paramètre type_document pour éviter les doublons
-- en cas de ré-exécution. Les autres paramètres des salles sont préservés.
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 1: Nettoyage idempotent ===' AS '';

DELETE FROM parametres
WHERE groupe_parametre      = 'LocationSalle'
  AND sous_groupe_parametre = 'Salles'
  AND nom_parametre         = 'type_document';

SELECT ROW_COUNT() AS lignes_supprimees;

-- ============================================================================
-- ÉTAPE 2 : Récupération des catégories (salles) existantes
-- On insère type_document pour chaque salle déjà configurée en base.
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 2: Salles existantes ===' AS '';

SELECT DISTINCT categorie AS salle_existante
FROM parametres
WHERE groupe_parametre      = 'LocationSalle'
  AND sous_groupe_parametre = 'Salles'
ORDER BY categorie;

-- ============================================================================
-- ÉTAPE 3 : Insertion de type_document pour chaque salle
-- Valeur par défaut : 'facture' (comportement historique préservé)
--
-- ⚠️  Si tu as des salles avec des noms différents de 'Cabinet' et 'Salle',
--     ajoute une ligne INSERT supplémentaire par salle.
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 3: Insertion type_document ===' AS '';

INSERT INTO parametres (nom_parametre, valeur_parametre, groupe_parametre, sous_groupe_parametre, categorie)
SELECT
    'type_document'  AS nom_parametre,
    'facture'        AS valeur_parametre,
    'LocationSalle'  AS groupe_parametre,
    'Salles'         AS sous_groupe_parametre,
    categorie
FROM (
    -- Récupère dynamiquement toutes les salles existantes
    SELECT DISTINCT categorie
    FROM parametres
    WHERE groupe_parametre      = 'LocationSalle'
      AND sous_groupe_parametre = 'Salles'
      AND categorie IS NOT NULL
      AND categorie != ''
) AS salles_existantes;

SELECT ROW_COUNT() AS lignes_inserees;

-- ============================================================================
-- ÉTAPE 4 : Vérification
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 4: Vérification ===' AS '';

SELECT
    categorie,
    nom_parametre,
    valeur_parametre
FROM parametres
WHERE groupe_parametre      = 'LocationSalle'
  AND sous_groupe_parametre = 'Salles'
  AND nom_parametre         = 'type_document'
ORDER BY categorie;

-- Contrôle : autant de lignes type_document que de salles distinctes
SELECT
    CASE
        WHEN COUNT(*) = (
            SELECT COUNT(DISTINCT categorie)
            FROM parametres
            WHERE groupe_parametre      = 'LocationSalle'
              AND sous_groupe_parametre = 'Salles'
              AND categorie IS NOT NULL
        )
        THEN CONCAT('✅ ', COUNT(*), ' paramètre(s) type_document insérés — une entrée par salle')
        ELSE CONCAT('❌ Incohérence : ', COUNT(*), ' type_document vs ',
            (SELECT COUNT(DISTINCT categorie) FROM parametres
             WHERE groupe_parametre = 'LocationSalle' AND sous_groupe_parametre = 'Salles'),
            ' salles')
    END AS resultat
FROM parametres
WHERE groupe_parametre      = 'LocationSalle'
  AND sous_groupe_parametre = 'Salles'
  AND nom_parametre         = 'type_document';

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;

SELECT '' AS '';
SELECT '============================================================================' AS '';
SELECT '✅ MIGRATION 020 TERMINÉE — type_document ajouté pour chaque salle' AS '';
SELECT '============================================================================' AS '';