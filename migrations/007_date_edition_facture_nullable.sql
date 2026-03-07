-- ============================================================================
-- MIGRATION 007 : Rendre date_edition facultative
-- ============================================================================
-- OBJECTIF : 
--   1. Corriger la logique de date_edition (NULL jusqu'à impression PDF)
--   2. Nettoyer les données incohérentes
--
-- AUTEUR : Migration automatique
-- DATE   : 2026-02-18
-- ============================================================================

SELECT '============================================================================' AS '';
SELECT '   MIGRATION 007 - DÉBUT' AS '';
SELECT '============================================================================' AS '';
SELECT '' AS '';

-- ============================================================================
-- ÉTAPE 1 : MODIFIER LES COLONNES
-- ============================================================================

SELECT '--- ÉTAPE 1 : Modification des colonnes ---' AS '';
SELECT '' AS '';

ALTER TABLE `facture` 
MODIFY COLUMN `date_edition` DATETIME NULL DEFAULT NULL
COMMENT 'Date de génération du PDF (NULL si jamais imprimée)';

SELECT '✅ date_edition : DATETIME NOT NULL → DATETIME NULL' AS 'Modification';
SELECT '' AS '';

-- Vérification structure
SELECT 
    COLUMN_NAME AS 'Colonne',
    DATA_TYPE AS 'Type',
    CHARACTER_MAXIMUM_LENGTH AS 'Longueur',
    IS_NULLABLE AS 'NULL?',
    COLUMN_COMMENT AS 'Commentaire'
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'facture'
  AND COLUMN_NAME IN ('date_edition')
ORDER BY COLUMN_NAME;

SELECT '' AS '';

-- ============================================================================
-- ÉTAPE 2 : NETTOYER LES DONNÉES INCOHÉRENTES
-- ============================================================================

SELECT '--- ÉTAPE 2 : Nettoyage des données ---' AS '';
SELECT '' AS '';

-- Vider date_edition pour les factures "En attente" (jamais éditées)
UPDATE `facture`
SET date_edition = NULL
WHERE etat = 'En attente';

SELECT 
    CONCAT('✅ ', COUNT(*), ' facture(s) "En attente" nettoyée(s) (date_edition = NULL)') AS 'Nettoyage'
FROM `facture`
WHERE etat = 'En attente' AND date_edition IS NULL;

SELECT '' AS '';

-- ============================================================================
-- ÉTAPE 3 : STATISTIQUES GLOBALES
-- ============================================================================

SELECT '--- ÉTAPE 3 : Statistiques globales ---' AS '';
SELECT '' AS '';

SELECT 
    COUNT(*) AS 'Total factures',
    SUM(CASE WHEN date_edition IS NULL THEN 1 ELSE 0 END) AS 'Jamais éditées',
    SUM(CASE WHEN date_edition IS NOT NULL THEN 1 ELSE 0 END) AS 'Éditées'
FROM `facture`;

SELECT '' AS '';

-- ============================================================================
-- ÉTAPE 4 : STATISTIQUES PAR ÉTAT
-- ============================================================================

SELECT '--- ÉTAPE 4 : Statistiques par état ---' AS '';
SELECT '' AS '';

SELECT 
    etat AS 'État',
    COUNT(*) AS 'Total',
    SUM(CASE WHEN date_edition IS NULL THEN 1 ELSE 0 END) AS 'Sans date_edition',
    SUM(CASE WHEN date_edition IS NOT NULL THEN 1 ELSE 0 END) AS 'Avec date_edition'
FROM `facture`
GROUP BY etat
ORDER BY etat;

SELECT '' AS '';

-- ============================================================================
-- ÉTAPE 5 : CONTRÔLE COHÉRENCE date_edition ↔ factfilename
-- ============================================================================

SELECT '--- ÉTAPE 5 : Contrôle cohérence date_edition ↔ factfilename ---' AS '';
SELECT '' AS '';

SELECT 
    CASE 
        WHEN date_edition IS NOT NULL AND (factfilename IS NULL OR factfilename = '') 
            THEN '❌ INCOHÉRENT: date sans PDF'
        WHEN date_edition IS NULL AND factfilename IS NOT NULL AND factfilename != '' 
            THEN '⚠️  MINEUR: PDF sans date'
        WHEN date_edition IS NOT NULL AND factfilename IS NOT NULL 
            THEN '✅ COHÉRENT: date + PDF'
        WHEN date_edition IS NULL AND (factfilename IS NULL OR factfilename = '') 
            THEN '✅ COHÉRENT: jamais imprimée'
        ELSE 'AUTRE'
    END AS 'Cohérence',
    COUNT(*) AS 'Nombre'
FROM `facture`
GROUP BY 
    CASE 
        WHEN date_edition IS NOT NULL AND (factfilename IS NULL OR factfilename = '') 
            THEN '❌ INCOHÉRENT: date sans PDF'
        WHEN date_edition IS NULL AND factfilename IS NOT NULL AND factfilename != '' 
            THEN '⚠️  MINEUR: PDF sans date'
        WHEN date_edition IS NOT NULL AND factfilename IS NOT NULL 
            THEN '✅ COHÉRENT: date + PDF'
        WHEN date_edition IS NULL AND (factfilename IS NULL OR factfilename = '') 
            THEN '✅ COHÉRENT: jamais imprimée'
        ELSE 'AUTRE'
    END
ORDER BY 
    CASE 
        WHEN date_edition IS NOT NULL AND (factfilename IS NULL OR factfilename = '') THEN 1
        WHEN date_edition IS NULL AND factfilename IS NOT NULL AND factfilename != '' THEN 2
        ELSE 3
    END;

SELECT '' AS '';

-- ============================================================================
-- ÉTAPE 6 : LISTE DES ANOMALIES (SI PRÉSENTES)
-- ============================================================================

SELECT '--- ÉTAPE 6 : Anomalies critiques (date_edition sans PDF) ---' AS '';
SELECT '' AS '';

SELECT 
    id_facture AS 'ID',
    numero_facture AS 'Numéro',
    etat AS 'État',
    DATE_FORMAT(date_edition, '%d.%m.%Y %H:%i') AS 'Date édition',
    COALESCE(factfilename, '(NULL)') AS 'Fichier PDF'
FROM `facture`
WHERE date_edition IS NOT NULL 
  AND (factfilename IS NULL OR factfilename = '')
ORDER BY date_edition DESC
LIMIT 10;

SELECT '' AS '';

SELECT '--- ÉTAPE 6.bis : Anomalies mineures (PDF sans date_edition) ---' AS '';
SELECT '' AS '';

SELECT 
    id_facture AS 'ID',
    numero_facture AS 'Numéro',
    etat AS 'État',
    COALESCE(factfilename, '(NULL)') AS 'Fichier PDF'
FROM `facture`
WHERE (factfilename IS NOT NULL AND factfilename != '')
  AND date_edition IS NULL
ORDER BY id_facture DESC
LIMIT 10;

SELECT '' AS '';

-- ============================================================================
-- MIGRATION TERMINÉE
-- ============================================================================

SELECT '============================================================================' AS '';
SELECT '   MIGRATION 007 - TERMINÉE' AS '';
SELECT '============================================================================' AS '';
SELECT '' AS '';

SELECT 
    COUNT(*) AS 'Total factures',
    SUM(CASE WHEN date_edition IS NULL THEN 1 ELSE 0 END) AS 'Factures non éditées',
    SUM(CASE WHEN date_edition IS NOT NULL THEN 1 ELSE 0 END) AS 'Factures éditées',
    SUM(CASE WHEN date_edition IS NOT NULL AND (factfilename IS NULL OR factfilename = '') THEN 1 ELSE 0 END) AS 'Anomalies critiques ❌'
FROM `facture`;

SELECT '' AS '';

-- ⚠️ ALERTE si anomalies critiques détectées
SELECT 
    '⚠️ ATTENTION : Factures avec date_edition mais sans PDF détecté(es)' AS 'Alerte',
    COUNT(*) AS 'Nombre anomalies'
FROM `facture`
WHERE date_edition IS NOT NULL 
  AND (factfilename IS NULL OR factfilename = '')
HAVING COUNT(*) > 0;

SELECT '' AS '';
SELECT '============================================================================' AS '';

-- ============================================================================
-- NOTES
-- ============================================================================
-- MODIFICATION APPLIQUÉE :
--   1. date_edition : NOT NULL → NULL (facultative)
--
-- AVANT :
--   date_edition DATETIME NOT NULL  → Toujours renseignée (incorrectement à la création)
--
-- APRÈS :
--   date_edition DATETIME NULL      → NULL jusqu'à la première impression PDF
--
-- NETTOYAGE EFFECTUÉ :
--   - Toutes les factures "En attente" ont date_edition = NULL
--
-- COMPORTEMENT ATTENDU :
--   - Création facture           → date_edition = NULL, factfilename = NULL
--   - Facture "En attente"       → date_edition = NULL, factfilename = NULL
--   - 1ère impression PDF        → date_edition = NOW(), factfilename = 'xxx.pdf'
--   - Réimpression PDF           → date_edition = NOW(), factfilename = 'xxx.pdf' (mise à jour)
--
-- RÈGLES DE COHÉRENCE :
--   ✅ COHÉRENT :
--      - date_edition IS NULL     ET factfilename IS NULL     → Jamais imprimée
--      - date_edition IS NOT NULL ET factfilename IS NOT NULL → Imprimée
--   
--   ❌ INCOHÉRENT (anomalies détectées par la migration) :
--      - date_edition IS NOT NULL ET factfilename IS NULL     → CRITIQUE : date sans PDF !
--      - date_edition IS NULL     ET factfilename IS NOT NULL → MINEUR : PDF ancien sans date
--
-- COHÉRENCE MÉTIER :
--   etat = 'En attente'    → date_edition = NULL (garanti par ÉTAPE 2)
--   etat = 'Envoyée'       → date_edition PEUT être NULL (envoyée sans impression)
--   etat = 'Payée'         → date_edition PEUT être NULL (paiée sans impression)
--   date_edition NOT NULL  → factfilename DOIT être NOT NULL
--
-- AVANTAGES :
--   - Logique correcte : date_edition = date réelle de génération du PDF
--   - Requêtes utiles : WHERE date_edition IS NULL (factures jamais imprimées)
--   - Cohérence métier : distinction claire entre "créée" et "éditée"
--   - Contrôle qualité : détection des incohérences date_edition ↔ factfilename
--
-- ⚠️ ACTION REQUISE CÔTÉ CODE :
--   Modifier FactureControleur.php::ajouterFacture()
--   → Supprimer date_edition de l'INSERT
--   → Voir PATCH_007_FactureControleur.md
--
-- ⚠️ SI ANOMALIES DÉTECTÉES :
--   Vérifier les factures listées avec date_edition mais sans factfilename
--   → Soit régénérer le PDF
--   → Soit mettre date_edition = NULL si jamais réellement imprimée
-- ============================================================================