-- ============================================================================
-- Migration 015 : Ajout paramètre type_client_requis pour les salles
-- ============================================================================
-- Date: 2026-03-14
-- Objectif: Permettre de restreindre une salle à un type de client donné,
--           sans coder la restriction en dur.
--
-- Structure : pour chaque salle configurée dans LocationSalle/Salles,
--   on peut ajouter un 3e paramètre :
--     nom_parametre = 'type_client_requis'
--     valeur_parametre = 'therapeute'   (ou toute autre valeur future)
--
--   Si absent ou vide → aucune restriction.
--   Si présent → seuls les clients correspondant au type peuvent réserver.
--
-- Valeurs reconnues actuellement :
--   'therapeute' → client.est_therapeute = 1
--
-- Dépend de: table parametres, migration 010 (structure LocationSalle/Salles)
-- ============================================================================

SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

SELECT '============================================================================' AS '';
SELECT 'Migration 015: Restriction type_client_requis sur les salles' AS '';
SELECT '============================================================================' AS '';

-- ============================================================================
-- ÉTAPE 1 : Cabinet → réservé aux thérapeutes
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 1: Paramètre type_client_requis pour Cabinet ===' AS '';

SET @p_cab = (
    SELECT COUNT(*) FROM parametres
    WHERE groupe_parametre      = 'LocationSalle'
    AND   sous_groupe_parametre = 'Salles'
    AND   categorie             = 'Cabinet'
    AND   nom_parametre         = 'type_client_requis'
);

SET @sql = IF(@p_cab = 0,
    'INSERT INTO `parametres`
        (`nom_parametre`, `valeur_parametre`, `groupe_parametre`, `sous_groupe_parametre`, `categorie`)
     VALUES
        (''type_client_requis'', ''therapeute'', ''LocationSalle'', ''Salles'', ''Cabinet'')',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT IF(@p_cab = 0, '✅ Paramètre Cabinet.type_client_requis ajouté (therapeute)', 'ℹ️  Déjà présent') AS check_cabinet;

-- ============================================================================
-- ÉTAPE 2 : Salle → aucune restriction (paramètre absent = libre)
-- ============================================================================
-- Pas d'INSERT pour Salle : l'absence du paramètre signifie "pas de restriction".
-- Documenté ici pour clarté.

SELECT '' AS '';
SELECT '=== ÉTAPE 2: Salle → aucune restriction ===' AS '';
SELECT 'ℹ️  Aucun paramètre type_client_requis pour Salle = accès libre à tous les clients' AS info;

-- ============================================================================
-- ÉTAPE 3 : Vérification état final
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 3: État des paramètres LocationSalle/Salles ===' AS '';

SELECT
    categorie,
    MAX(CASE WHEN nom_parametre = 'label'              THEN valeur_parametre END) AS label,
    MAX(CASE WHEN nom_parametre = 'nom_service'        THEN valeur_parametre END) AS service_tarifaire,
    MAX(CASE WHEN nom_parametre = 'type_client_requis' THEN valeur_parametre END) AS restriction_client,
    COUNT(*) AS nb_params,
    CASE
        WHEN MAX(CASE WHEN nom_parametre = 'type_client_requis' THEN valeur_parametre END) IS NOT NULL
        THEN CONCAT('🔒 Réservé aux : ', MAX(CASE WHEN nom_parametre = 'type_client_requis' THEN valeur_parametre END))
        ELSE '🔓 Accès libre'
    END AS statut_restriction
FROM parametres
WHERE groupe_parametre      = 'LocationSalle'
AND   sous_groupe_parametre = 'Salles'
GROUP BY categorie
ORDER BY categorie;

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;

SELECT '' AS '';
SELECT '============================================================================' AS '';
SELECT '✅ MIGRATION 015 TERMINÉE' AS '';
SELECT '============================================================================' AS '';