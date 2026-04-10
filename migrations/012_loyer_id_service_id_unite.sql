-- ============================================================================
-- Migration 012: Ajout id_service et id_unite à la table loyer
-- ============================================================================
-- Date: 2026-03-13
-- Objectif: Permettre l'identification d'un loyer par la clé tarifaire
--           (id_client, annee, id_service, id_unite) pour la génération
--           automatique depuis le tableau des locations de salle.
--
--   id_service : référence services.id  — service tarifaire (ex: Location Cabinet)
--   id_unite   : référence unites.id    — unité (ex: heure, demi-journée)
--
-- Ces colonnes sont NULL pour les loyers créés manuellement.
-- Dépend de: table loyer, table services, table unites
-- ============================================================================

SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

SELECT '============================================================================' AS '';
SELECT 'Migration 012: id_service + id_unite sur la table loyer' AS '';
SELECT '============================================================================' AS '';

-- ============================================================================
-- ÉTAPE 1: Ajout colonne id_service (idempotent)
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 1: Ajout colonne id_service ===' AS '';

SET @col_service = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND   TABLE_NAME   = 'loyer'
    AND   COLUMN_NAME  = 'id_service'
);

SET @sql = IF(@col_service = 0,
    'ALTER TABLE `loyer`
         ADD COLUMN `id_service` INT NULL DEFAULT NULL
         COMMENT "Service tarifaire associé (ref services.id) — NULL si loyer manuel"
         AFTER `motif`',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT CASE
    WHEN COUNT(*) = 1 THEN '✅ Colonne id_service présente'
    ELSE '❌ Colonne id_service manquante'
END AS check_id_service
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loyer' AND COLUMN_NAME = 'id_service';

-- ============================================================================
-- ÉTAPE 2: Ajout colonne id_unite (idempotent)
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 2: Ajout colonne id_unite ===' AS '';

SET @col_unite = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND   TABLE_NAME   = 'loyer'
    AND   COLUMN_NAME  = 'id_unite'
);

SET @sql = IF(@col_unite = 0,
    'ALTER TABLE `loyer`
         ADD COLUMN `id_unite` INT NULL DEFAULT NULL
         COMMENT "Unité tarifaire associée (ref unites.id) — NULL si loyer manuel"
         AFTER `id_service`',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT CASE
    WHEN COUNT(*) = 1 THEN '✅ Colonne id_unite présente'
    ELSE '❌ Colonne id_unite manquante'
END AS check_id_unite
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loyer' AND COLUMN_NAME = 'id_unite';

-- ============================================================================
-- ÉTAPE 3: Index sur (id_client, id_service, id_unite) pour la recherche rapide
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 3: Index de recherche ===' AS '';

SET @idx = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND   TABLE_NAME   = 'loyer'
    AND   INDEX_NAME   = 'idx_loyer_client_tarif'
);

SET @sql = IF(@idx = 0,
    'ALTER TABLE `loyer`
         ADD INDEX `idx_loyer_client_tarif` (`id_client`, `id_service`, `id_unite`)',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT CASE
    WHEN COUNT(*) >= 1 THEN '✅ Index idx_loyer_client_tarif présent'
    ELSE '❌ Index idx_loyer_client_tarif absent'
END AS check_index
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loyer' AND INDEX_NAME = 'idx_loyer_client_tarif';

-- ============================================================================
-- ÉTAPE 4: Clés étrangères (idempotent)
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 4: Clés étrangères ===' AS '';

SET @fk_service = (
    SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_loyer_service'
);
SET @sql = IF(@fk_service = 0,
    'ALTER TABLE `loyer`
         ADD CONSTRAINT `fk_loyer_service`
         FOREIGN KEY (`id_service`) REFERENCES `services`(`id`)
         ON DELETE SET NULL ON UPDATE CASCADE',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_unite = (
    SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_loyer_unite'
);
SET @sql = IF(@fk_unite = 0,
    'ALTER TABLE `loyer`
         ADD CONSTRAINT `fk_loyer_unite`
         FOREIGN KEY (`id_unite`) REFERENCES `unites`(`id`)
         ON DELETE SET NULL ON UPDATE CASCADE',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT CASE
    WHEN COUNT(*) = 1 THEN '✅ FK fk_loyer_service présente'
    ELSE '⚠️  FK fk_loyer_service absente'
END AS check_fk_service
FROM information_schema.REFERENTIAL_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_loyer_service';

SELECT CASE
    WHEN COUNT(*) = 1 THEN '✅ FK fk_loyer_unite présente'
    ELSE '⚠️  FK fk_loyer_unite absente'
END AS check_fk_unite
FROM information_schema.REFERENTIAL_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_loyer_unite';

-- ============================================================================
-- ÉTAPE 5: Vérification structure finale
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 5: Structure table loyer (colonnes tarifaires) ===' AS '';

SELECT
    ORDINAL_POSITION    AS pos,
    COLUMN_NAME         AS colonne,
    COLUMN_TYPE         AS type,
    IS_NULLABLE         AS nullable,
    COLUMN_COMMENT      AS commentaire
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND   TABLE_NAME   = 'loyer'
AND   COLUMN_NAME IN ('motif', 'id_service', 'id_unite')
ORDER BY ORDINAL_POSITION;

-- ============================================================================
-- ÉTAPE 6: Recréation de la vue v_loyers_complets
-- ============================================================================
-- La vue doit être recréée pour exposer id_service et id_unite.
-- Sans cela, le SELECT dans LoyerControleur retourne une erreur 500
-- car il référence ces colonnes directement dans la vue.
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 6: Recréation vue v_loyers_complets ===' AS '';

DROP VIEW IF EXISTS v_loyers_complets;

CREATE VIEW v_loyers_complets AS
SELECT
    -- Identifiants du loyer
    l.id_loyer,
    l.numero_loyer,
    l.numero_sequence,
    l.id_client,

    -- Informations du client
    c.prenom                            AS prenom_client,
    c.nom                               AS nom_client,
    CONCAT(c.prenom, ' ', c.nom)        AS nom_complet_client,
    c.email                             AS email_client,
    c.telephone                         AS telephone_client,
    c.rue                               AS rue_client,
    c.numero                            AS numero_client,
    c.code_postal                       AS code_postal_client,
    c.localite                          AS localite_client,

    -- Dates et période
    l.date_creation_loyer,
    l.periode_debut,
    l.periode_fin,
    l.duree_mois,

    -- Description et liaison tarifaire
    l.motif,
    l.id_service,
    l.id_unite,
    l.description,
    l.afficher_dates_paiement,

    -- Montants de base
    l.montant_total,
    l.montant_mensuel_moyen,

    -- États
    l.statut,
    l.etat_paiement,

    -- Calculs automatiques des paiements (depuis loyer_detail)
    COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0)
                                        AS montant_paye,
    l.montant_total - COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0)
                                        AS montant_restant,
    ROUND(
        (COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0)
         / NULLIF(l.montant_total, 0)) * 100,
        2
    )                                   AS pourcentage_paye,

    -- Comptage des mois
    COUNT(CASE WHEN ld.est_paye THEN 1 END) AS mois_payes,
    COUNT(ld.id)                            AS total_mois,

    -- Alias compatibilité (montant_paye_total = montant_paye)
    COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0)
                                        AS montant_paye_total,
    l.montant_total - COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0)
                                        AS solde_restant,

    -- Métadonnées
    l.date_creation,
    l.date_modification,
    l.createur_id,
    l.modificateur_id

FROM loyer l
INNER JOIN client c ON l.id_client = c.id
LEFT JOIN loyer_detail ld ON l.id_loyer = ld.id_loyer
GROUP BY
    l.id_loyer, l.numero_loyer, l.numero_sequence, l.id_client,
    c.prenom, c.nom, c.email, c.telephone, c.rue, c.numero, c.code_postal, c.localite,
    l.date_creation_loyer, l.periode_debut, l.periode_fin, l.duree_mois,
    l.motif, l.id_service, l.id_unite, l.description, l.afficher_dates_paiement,
    l.montant_total, l.montant_mensuel_moyen,
    l.statut, l.etat_paiement,
    l.date_creation, l.date_modification, l.createur_id, l.modificateur_id;

SELECT
    CASE
        WHEN COUNT(*) = 1 THEN '✅ Vue v_loyers_complets recréée avec id_service et id_unite'
        ELSE '❌ Échec recréation vue v_loyers_complets'
    END AS check_vue
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
AND   TABLE_NAME   = 'v_loyers_complets';

-- Vérifier que id_service et id_unite sont bien dans la vue
SELECT
    CASE WHEN COUNT(*) = 2
        THEN '✅ id_service et id_unite présents dans la vue'
        ELSE CONCAT('❌ Colonnes manquantes dans la vue (trouvées : ', COUNT(*), '/2)')
    END AS check_colonnes_vue
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND   TABLE_NAME   = 'v_loyers_complets'
AND   COLUMN_NAME IN ('id_service', 'id_unite');

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;

SELECT '' AS '';
SELECT '============================================================================' AS '';
SELECT '✅ MIGRATION 012 TERMINÉE' AS '';
SELECT '============================================================================' AS '';