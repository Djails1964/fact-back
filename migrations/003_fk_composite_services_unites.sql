-- ============================================================================
-- MIGRATION: FK composite pour cascade delete sur dissociation service-unite
-- ============================================================================
-- Date: 2025-01-17
-- 
-- OBJECTIF:
-- Quand on dissocie une unite d'un service (DELETE FROM services_unites),
-- les tarifs (standards et speciaux) bases sur cette combinaison doivent
-- etre automatiquement supprimes.
--
-- STRATEGIE:
-- 1. Ajouter une contrainte UNIQUE sur services_unites(service_id, unite_id)
-- 2. Creer des FK composites sur tarifs et tarifs_speciaux qui referencent
--    cette combinaison unique
-- 3. Modifier les FK existantes sur tarifs/tarifs_speciaux vers unites
--    (retirer le CASCADE direct vers unites)
--
-- IMPORTANT: 
-- - Faire une sauvegarde avant d'executer ce script
-- - Ce script REMPLACE les FK creees par migration_cascade_delete_unites.sql
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- ============================================================================
-- ETAPE 0: Verifier l'etat actuel
-- ============================================================================
SELECT '=== ETAT ACTUEL DES CONTRAINTES ===' as info;

SELECT 
    TABLE_NAME,
    CONSTRAINT_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE REFERENCED_TABLE_NAME IS NOT NULL
AND TABLE_SCHEMA = DATABASE()
AND TABLE_NAME IN ('tarifs', 'tarifs_speciaux', 'services_unites')
ORDER BY TABLE_NAME, CONSTRAINT_NAME;

-- ============================================================================
-- ETAPE 1: Nettoyer les tarifs orphelins (sans liaison service-unite valide)
-- ============================================================================
SELECT '=== VERIFICATION TARIFS ORPHELINS ===' as info;

-- Tarifs standards sans liaison service-unite correspondante
SELECT 'Tarifs standards orphelins (sans liaison service-unite):' as verification;
SELECT t.id, t.service_id, t.unite_id 
FROM tarifs t
LEFT JOIN services_unites su ON t.service_id = su.service_id AND t.unite_id = su.unite_id
WHERE su.service_id IS NULL;

-- Tarifs speciaux sans liaison service-unite correspondante
SELECT 'Tarifs speciaux orphelins (sans liaison service-unite):' as verification;
SELECT ts.id, ts.service_id, ts.unite_id, ts.client_id
FROM tarifs_speciaux ts
LEFT JOIN services_unites su ON ts.service_id = su.service_id AND ts.unite_id = su.unite_id
WHERE su.service_id IS NULL;

-- ============================================================================
-- ETAPE 2: Supprimer les tarifs orphelins (DECOMMENTER APRES VERIFICATION)
-- ============================================================================
-- ATTENTION: Verifiez les resultats de l'etape 1 avant de decommenter !

-- DELETE t FROM tarifs t
-- LEFT JOIN services_unites su ON t.service_id = su.service_id AND t.unite_id = su.unite_id
-- WHERE su.service_id IS NULL;

-- DELETE ts FROM tarifs_speciaux ts
-- LEFT JOIN services_unites su ON ts.service_id = su.service_id AND ts.unite_id = su.unite_id
-- WHERE su.service_id IS NULL;

-- ============================================================================
-- ETAPE 3: Ajouter contrainte UNIQUE sur services_unites
-- ============================================================================
SELECT '=== AJOUT CONTRAINTE UNIQUE SUR SERVICES_UNITES ===' as info;

-- Verifier si la contrainte existe deja
SELECT COUNT(*) INTO @uk_exists
FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'services_unites'
AND CONSTRAINT_TYPE = 'UNIQUE'
AND CONSTRAINT_NAME = 'uk_service_unite';

-- Ajouter uniquement si elle n'existe pas
SET @sql = IF(@uk_exists = 0,
    'ALTER TABLE services_unites ADD CONSTRAINT uk_service_unite UNIQUE (service_id, unite_id)',
    'SELECT "Contrainte uk_service_unite existe deja" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- ETAPE 4: Supprimer les anciennes FK sur tarifs
-- ============================================================================
SELECT '=== SUPPRESSION ANCIENNES FK SUR TARIFS ===' as info;

-- Supprimer FK vers unites si elle existe
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'tarifs' 
    AND CONSTRAINT_NAME = 'fk_tarifs_unite'
);
SET @sql = IF(@fk_exists > 0,
    'ALTER TABLE tarifs DROP FOREIGN KEY fk_tarifs_unite',
    'SELECT "FK fk_tarifs_unite n existe pas" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Supprimer FK vers services si elle existe
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'tarifs' 
    AND CONSTRAINT_NAME = 'fk_tarifs_service'
);
SET @sql = IF(@fk_exists > 0,
    'ALTER TABLE tarifs DROP FOREIGN KEY fk_tarifs_service',
    'SELECT "FK fk_tarifs_service n existe pas" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- ETAPE 5: Supprimer les anciennes FK sur tarifs_speciaux
-- ============================================================================
SELECT '=== SUPPRESSION ANCIENNES FK SUR TARIFS_SPECIAUX ===' as info;

-- Supprimer FK vers unites si elle existe
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'tarifs_speciaux' 
    AND CONSTRAINT_NAME = 'fk_tarifs_speciaux_unite'
);
SET @sql = IF(@fk_exists > 0,
    'ALTER TABLE tarifs_speciaux DROP FOREIGN KEY fk_tarifs_speciaux_unite',
    'SELECT "FK fk_tarifs_speciaux_unite n existe pas" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Supprimer FK vers services si elle existe
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'tarifs_speciaux' 
    AND CONSTRAINT_NAME = 'fk_tarifs_speciaux_service'
);
SET @sql = IF(@fk_exists > 0,
    'ALTER TABLE tarifs_speciaux DROP FOREIGN KEY fk_tarifs_speciaux_service',
    'SELECT "FK fk_tarifs_speciaux_service n existe pas" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- ETAPE 6: Creer les nouvelles FK composites
-- ============================================================================
SELECT '=== CREATION FK COMPOSITES ===' as info;

-- FK composite sur tarifs -> services_unites
-- Quand on supprime une liaison service-unite, les tarifs correspondants sont supprimes
ALTER TABLE tarifs
ADD CONSTRAINT fk_tarifs_service_unite
    FOREIGN KEY (service_id, unite_id) 
    REFERENCES services_unites(service_id, unite_id)
    ON DELETE CASCADE
    ON UPDATE CASCADE;

SELECT 'FK fk_tarifs_service_unite creee' as status;

-- FK composite sur tarifs_speciaux -> services_unites
ALTER TABLE tarifs_speciaux
ADD CONSTRAINT fk_tarifs_speciaux_service_unite
    FOREIGN KEY (service_id, unite_id) 
    REFERENCES services_unites(service_id, unite_id)
    ON DELETE CASCADE
    ON UPDATE CASCADE;

SELECT 'FK fk_tarifs_speciaux_service_unite creee' as status;

-- ============================================================================
-- ETAPE 7: Conserver les FK simples necessaires
-- ============================================================================
SELECT '=== AJOUT FK SIMPLES RESTANTES ===' as info;

-- FK tarifs -> types_tarifs (RESTRICT)
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'tarifs' 
    AND CONSTRAINT_NAME = 'fk_tarifs_type_tarif'
);
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE tarifs ADD CONSTRAINT fk_tarifs_type_tarif FOREIGN KEY (type_tarif_id) REFERENCES types_tarifs(id) ON DELETE RESTRICT ON UPDATE CASCADE',
    'SELECT "FK fk_tarifs_type_tarif existe deja" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- FK tarifs_speciaux -> client (CASCADE)
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'tarifs_speciaux' 
    AND CONSTRAINT_NAME = 'fk_tarifs_speciaux_client'
);
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE tarifs_speciaux ADD CONSTRAINT fk_tarifs_speciaux_client FOREIGN KEY (client_id) REFERENCES client(id) ON DELETE CASCADE ON UPDATE CASCADE',
    'SELECT "FK fk_tarifs_speciaux_client existe deja" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- ETAPE 8: Verifier les FK sur services_unites
-- ============================================================================
SELECT '=== VERIFICATION FK SUR SERVICES_UNITES ===' as info;

-- FK services_unites -> services (doit exister avec CASCADE)
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'services_unites' 
    AND CONSTRAINT_NAME = 'fk_services_unites_service'
);
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE services_unites ADD CONSTRAINT fk_services_unites_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE ON UPDATE CASCADE',
    'SELECT "FK fk_services_unites_service existe deja" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- FK services_unites -> unites (RESTRICT - empeche suppression unite si liee)
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'services_unites' 
    AND CONSTRAINT_NAME = 'fk_services_unites_unite'
);
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE services_unites ADD CONSTRAINT fk_services_unites_unite FOREIGN KEY (unite_id) REFERENCES unites(id) ON DELETE RESTRICT ON UPDATE CASCADE',
    'SELECT "FK fk_services_unites_unite existe deja" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- ETAPE 9: Verifier les FK sur lignesfacture
-- ============================================================================
SELECT '=== VERIFICATION FK SUR LIGNESFACTURE ===' as info;

-- FK lignesfacture -> services (RESTRICT)
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lignesfacture' 
    AND CONSTRAINT_NAME = 'fk_lignesfacture_service'
);
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE lignesfacture ADD CONSTRAINT fk_lignesfacture_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT ON UPDATE CASCADE',
    'SELECT "FK fk_lignesfacture_service existe deja" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- FK lignesfacture -> unites (RESTRICT)
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'lignesfacture' 
    AND CONSTRAINT_NAME = 'fk_lignesfacture_unite'
);
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE lignesfacture ADD CONSTRAINT fk_lignesfacture_unite FOREIGN KEY (unite_id) REFERENCES unites(id) ON DELETE RESTRICT ON UPDATE CASCADE',
    'SELECT "FK fk_lignesfacture_unite existe deja" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- ETAPE 10: Verification finale
-- ============================================================================
SELECT '=== CONTRAINTES FK FINALES ===' as info;

SELECT 
    TABLE_NAME,
    CONSTRAINT_NAME,
    GROUP_CONCAT(COLUMN_NAME ORDER BY ORDINAL_POSITION) as COLUMNS,
    REFERENCED_TABLE_NAME,
    GROUP_CONCAT(REFERENCED_COLUMN_NAME ORDER BY ORDINAL_POSITION) as REF_COLUMNS
FROM information_schema.KEY_COLUMN_USAGE
WHERE REFERENCED_TABLE_NAME IS NOT NULL
AND TABLE_SCHEMA = DATABASE()
AND TABLE_NAME IN ('tarifs', 'tarifs_speciaux', 'services_unites', 'lignesfacture')
GROUP BY TABLE_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME
ORDER BY TABLE_NAME, CONSTRAINT_NAME;

-- ============================================================================
-- RESUME DES COMPORTEMENTS APRES MIGRATION
-- ============================================================================
-- 
-- DISSOCIATION SERVICE-UNITE (DELETE FROM services_unites):
--   ✅ Supprime automatiquement: tarifs, tarifs_speciaux pour cette combinaison
--   
-- SUPPRESSION D'UN SERVICE:
--   ✅ Supprime automatiquement: services_unites (et donc tarifs, tarifs_speciaux)
--   ❌ Bloque si: utilise dans lignesfacture
--
-- SUPPRESSION D'UNE UNITE:
--   ❌ Bloque si: liee a un service (services_unites) ou utilisee dans lignesfacture
--   NOTE: Pour supprimer une unite, il faut d'abord la dissocier de tous les services
--
-- SUPPRESSION D'UN TYPE DE TARIF:
--   ❌ Bloque si: utilise dans tarifs
--
-- SUPPRESSION D'UN CLIENT:
--   ✅ Supprime automatiquement: tarifs_speciaux
--
-- ============================================================================
-- FIN DE LA MIGRATION
-- ============================================================================