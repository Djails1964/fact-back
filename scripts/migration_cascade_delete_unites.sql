-- ============================================================================
-- MIGRATION: Ajout des contraintes FK complètes avec CASCADE DELETE
-- ============================================================================
-- Date: 2025-01-13
-- Description: Ajoute toutes les clés étrangères manquantes pour garantir
--              l'intégrité référentielle de la base de données
-- 
-- TABLES CONCERNÉES:
-- - tarifs (service_id, unite_id, type_tarif_id)
-- - tarifs_speciaux (client_id, service_id, unite_id)
-- - services_unites (service_id, unite_id)
-- - lignesfacture (service_id, unite_id)
--
-- IMPORTANT: Faire une sauvegarde avant d'exécuter ce script
-- ============================================================================

-- Désactiver temporairement les vérifications de clés étrangères
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- ÉTAPE 0: Vérifier les contraintes existantes
-- ============================================================================
SELECT '=== CONTRAINTES FK EXISTANTES ===' as info;
SELECT 
    TABLE_NAME,
    CONSTRAINT_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE REFERENCED_TABLE_NAME IS NOT NULL
AND TABLE_SCHEMA = DATABASE()
AND TABLE_NAME IN ('tarifs', 'tarifs_speciaux', 'services_unites', 'lignesfacture')
ORDER BY TABLE_NAME, COLUMN_NAME;

-- ============================================================================
-- ÉTAPE 1: Vérifier les données orphelines
-- ============================================================================

-- Tarifs standards orphelins
SELECT '=== TARIFS STANDARDS - Vérification orphelins ===' as info;

SELECT 'Tarifs avec service inexistant:' as verification;
SELECT t.id, t.service_id FROM tarifs t 
LEFT JOIN services s ON t.service_id = s.id WHERE s.id IS NULL;

SELECT 'Tarifs avec unité inexistante:' as verification;
SELECT t.id, t.unite_id FROM tarifs t 
LEFT JOIN unites u ON t.unite_id = u.id WHERE u.id IS NULL;

SELECT 'Tarifs avec type_tarif inexistant:' as verification;
SELECT t.id, t.type_tarif_id FROM tarifs t 
LEFT JOIN types_tarifs tt ON t.type_tarif_id = tt.id WHERE tt.id IS NULL;

-- Tarifs spéciaux orphelins
SELECT '=== TARIFS SPÉCIAUX - Vérification orphelins ===' as info;

SELECT 'Tarifs spéciaux avec client inexistant:' as verification;
SELECT ts.id, ts.client_id FROM tarifs_speciaux ts 
LEFT JOIN client c ON ts.client_id = c.id WHERE c.id IS NULL;

SELECT 'Tarifs spéciaux avec service inexistant:' as verification;
SELECT ts.id, ts.service_id FROM tarifs_speciaux ts 
LEFT JOIN services s ON ts.service_id = s.id WHERE s.id IS NULL;

SELECT 'Tarifs spéciaux avec unité inexistante:' as verification;
SELECT ts.id, ts.unite_id FROM tarifs_speciaux ts 
LEFT JOIN unites u ON ts.unite_id = u.id WHERE u.id IS NULL;

-- Services_unites orphelins
SELECT '=== SERVICES_UNITES - Vérification orphelins ===' as info;

SELECT 'Liaisons avec service inexistant:' as verification;
SELECT su.* FROM services_unites su 
LEFT JOIN services s ON su.service_id = s.id WHERE s.id IS NULL;

SELECT 'Liaisons avec unité inexistante:' as verification;
SELECT su.* FROM services_unites su 
LEFT JOIN unites u ON su.unite_id = u.id WHERE u.id IS NULL;

-- Lignes facture orphelines
SELECT '=== LIGNESFACTURE - Vérification orphelins ===' as info;

SELECT 'Lignes avec service inexistant:' as verification;
SELECT lf.id_ligne, lf.service_id FROM lignesfacture lf 
LEFT JOIN services s ON lf.service_id = s.id WHERE s.id IS NULL;

SELECT 'Lignes avec unité inexistante:' as verification;
SELECT lf.id_ligne, lf.unite_id FROM lignesfacture lf 
LEFT JOIN unites u ON lf.unite_id = u.id WHERE u.id IS NULL;

-- ============================================================================
-- ÉTAPE 2: Nettoyer les données orphelines (DÉCOMMENTER SI NÉCESSAIRE)
-- ============================================================================

-- ATTENTION: Vérifiez les résultats de l'étape 1 avant de décommenter !

-- DELETE FROM tarifs WHERE service_id NOT IN (SELECT id FROM services);
-- DELETE FROM tarifs WHERE unite_id NOT IN (SELECT id FROM unites);
-- DELETE FROM tarifs WHERE type_tarif_id NOT IN (SELECT id FROM types_tarifs);

-- DELETE FROM tarifs_speciaux WHERE client_id NOT IN (SELECT id FROM client);
-- DELETE FROM tarifs_speciaux WHERE service_id NOT IN (SELECT id FROM services);
-- DELETE FROM tarifs_speciaux WHERE unite_id NOT IN (SELECT id FROM unites);

-- DELETE FROM services_unites WHERE service_id NOT IN (SELECT id FROM services);
-- DELETE FROM services_unites WHERE unite_id NOT IN (SELECT id FROM unites);

-- Pour lignesfacture, on ne supprime PAS automatiquement (données comptables)
-- Il faut traiter manuellement les cas problématiques

-- ============================================================================
-- ÉTAPE 3: Créer les contraintes FK pour la table TARIFS
-- ============================================================================
SELECT '=== CRÉATION FK POUR TARIFS ===' as info;

-- FK vers services (CASCADE: si service supprimé, tarifs supprimés)
ALTER TABLE tarifs
ADD CONSTRAINT fk_tarifs_service
    FOREIGN KEY (service_id) REFERENCES services(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE;

-- FK vers unites (CASCADE: si unité supprimée, tarifs supprimés)
ALTER TABLE tarifs
ADD CONSTRAINT fk_tarifs_unite
    FOREIGN KEY (unite_id) REFERENCES unites(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE;

-- FK vers types_tarifs (RESTRICT: empêche suppression type si utilisé)
ALTER TABLE tarifs
ADD CONSTRAINT fk_tarifs_type_tarif
    FOREIGN KEY (type_tarif_id) REFERENCES types_tarifs(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

-- ============================================================================
-- ÉTAPE 4: Créer les contraintes FK pour la table TARIFS_SPECIAUX
-- ============================================================================
SELECT '=== CRÉATION FK POUR TARIFS_SPECIAUX ===' as info;

-- FK vers client (CASCADE: si client supprimé, ses tarifs spéciaux supprimés)
ALTER TABLE tarifs_speciaux
ADD CONSTRAINT fk_tarifs_speciaux_client
    FOREIGN KEY (client_id) REFERENCES client(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE;

-- FK vers services (CASCADE: si service supprimé, tarifs spéciaux supprimés)
ALTER TABLE tarifs_speciaux
ADD CONSTRAINT fk_tarifs_speciaux_service
    FOREIGN KEY (service_id) REFERENCES services(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE;

-- FK vers unites (CASCADE: si unité supprimée, tarifs spéciaux supprimés)
ALTER TABLE tarifs_speciaux
ADD CONSTRAINT fk_tarifs_speciaux_unite
    FOREIGN KEY (unite_id) REFERENCES unites(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE;

-- ============================================================================
-- ÉTAPE 5: Créer les contraintes FK pour la table SERVICES_UNITES
-- ============================================================================
SELECT '=== CRÉATION FK POUR SERVICES_UNITES ===' as info;

-- FK vers services (CASCADE: si service supprimé, liaisons supprimées)
ALTER TABLE services_unites
ADD CONSTRAINT fk_services_unites_service
    FOREIGN KEY (service_id) REFERENCES services(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE;

-- FK vers unites (RESTRICT: empêche suppression unité si encore liée à un service)
ALTER TABLE services_unites
ADD CONSTRAINT fk_services_unites_unite
    FOREIGN KEY (unite_id) REFERENCES unites(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

-- ============================================================================
-- ÉTAPE 6: Créer les contraintes FK pour la table LIGNESFACTURE
-- ============================================================================
SELECT '=== CRÉATION FK POUR LIGNESFACTURE ===' as info;

-- FK vers services (RESTRICT: empêche suppression service si utilisé dans factures)
ALTER TABLE lignesfacture
ADD CONSTRAINT fk_lignesfacture_service
    FOREIGN KEY (service_id) REFERENCES services(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

-- FK vers unites (RESTRICT: empêche suppression unité si utilisée dans factures)
ALTER TABLE lignesfacture
ADD CONSTRAINT fk_lignesfacture_unite
    FOREIGN KEY (unite_id) REFERENCES unites(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

-- Réactiver les vérifications de clés étrangères
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- ÉTAPE 7: Vérification finale - Afficher toutes les nouvelles contraintes
-- ============================================================================
SELECT '=== CONTRAINTES FK CRÉÉES ===' as info;
SELECT 
    TABLE_NAME,
    CONSTRAINT_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE REFERENCED_TABLE_NAME IS NOT NULL
AND TABLE_SCHEMA = DATABASE()
AND TABLE_NAME IN ('tarifs', 'tarifs_speciaux', 'services_unites', 'lignesfacture')
ORDER BY TABLE_NAME, COLUMN_NAME;

-- ============================================================================
-- RÉSUMÉ DES COMPORTEMENTS
-- ============================================================================
-- 
-- SUPPRESSION D'UNE UNITÉ:
--   ✅ Supprime automatiquement: tarifs, tarifs_speciaux
--   ❌ Bloqué si: liée à un service (services_unites) ou utilisée dans lignesfacture
--
-- SUPPRESSION D'UN SERVICE:
--   ✅ Supprime automatiquement: tarifs, tarifs_speciaux, services_unites
--   ❌ Bloqué si: utilisé dans lignesfacture
--
-- SUPPRESSION D'UN TYPE DE TARIF:
--   ❌ Bloqué si: utilisé dans tarifs
--
-- SUPPRESSION D'UN CLIENT:
--   ✅ Supprime automatiquement: tarifs_speciaux
--
-- ============================================================================
-- FIN DE LA MIGRATION
-- ============================================================================