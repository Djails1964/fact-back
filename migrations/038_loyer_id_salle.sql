-- ============================================================================
-- Migration 038 : Ajout id_salle dans la table loyer
--
-- Objectif :
--   Lier directement un loyer à une salle, que ce loyer soit créé manuellement
--   ou généré depuis un contrat de location.
--
-- Changements :
--   1. Ajout colonne id_salle (FK vers salle.id) sur loyer
--   2. Migration automatique depuis location_salle_contrat pour les loyers
--      déjà liés à un contrat (id_contrat_location NOT NULL)
--   3. Ajout de nom_salle dans la vue v_loyers_complets
--
-- Dépend de : 037_categorie_motifs_type_contrat.sql
-- ============================================================================

SET NAMES utf8mb4;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

SELECT '============================================================================' AS '';
SELECT 'Migration 038 : Ajout id_salle dans loyer' AS '';
SELECT '============================================================================' AS '';

-- ============================================================================
-- ÉTAPE 1 : Ajout colonne id_salle sur loyer
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 1 : Ajout id_salle ===' AS '';

SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'loyer'
      AND COLUMN_NAME  = 'id_salle'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `loyer`
     ADD COLUMN `id_salle` INT UNSIGNED NULL DEFAULT NULL
     COMMENT "Salle concernée par ce loyer (FK salle.id)"
     AFTER `id_contrat_location`',
    'DO 0'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Index
SET @idx = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loyer' AND INDEX_NAME = 'idx_loyer_salle');
SET @sql = IF(@idx = 0,
    'ALTER TABLE `loyer` ADD INDEX `idx_loyer_salle` (`id_salle`)',
    'DO 0'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- FK
SET @fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loyer'
    AND CONSTRAINT_NAME = 'fk_loyer_salle' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @sql = IF(@fk = 0,
    'ALTER TABLE `loyer`
     ADD CONSTRAINT `fk_loyer_salle`
     FOREIGN KEY (`id_salle`) REFERENCES `salle`(`id`)
     ON DELETE SET NULL ON UPDATE CASCADE',
    'DO 0'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT CASE WHEN COUNT(*) = 1
    THEN '✅ Colonne id_salle ajoutée avec FK'
    ELSE '❌ Échec'
END AS check_col
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loyer' AND COLUMN_NAME = 'id_salle';

-- ============================================================================
-- ÉTAPE 2 : Migration des loyers existants liés à un contrat
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 2 : Migration des loyers existants ===' AS '';

UPDATE loyer l
JOIN location_salle_contrat lsc ON lsc.id = l.id_contrat_location
SET l.id_salle = lsc.id_salle
WHERE l.id_salle IS NULL
  AND lsc.id_salle IS NOT NULL;

SELECT CONCAT('✅ ', ROW_COUNT(), ' loyer(s) migré(s)') AS migration_loyers;

-- ============================================================================
-- ÉTAPE 3 : Mise à jour de la vue v_loyers_complets
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 3 : Mise à jour vue v_loyers_complets ===' AS '';

DROP VIEW IF EXISTS `v_loyers_complets`;
 
CREATE VIEW `v_loyers_complets` AS
SELECT
    l.id_loyer,
    l.numero_loyer,
    l.numero_sequence,
    l.annee_loyer,
    l.id_client,
    c.prenom                            AS prenom_client,
    c.nom                               AS nom_client,
    CONCAT(c.prenom, ' ', c.nom)        AS nom_complet_client,
    c.email                             AS email_client,
    c.telephone                         AS telephone_client,
    c.rue                               AS rue_client,
    c.numero                            AS numero_client,
    c.code_postal                       AS code_postal_client,
    c.localite                          AS localite_client,
    l.date_creation_loyer,
    l.periode_debut,
    l.periode_fin,
    l.duree_mois,
    l.motif,
    l.id_service,
    l.description,
    l.afficher_dates_paiement,
    l.id_facture,
    l.id_contrat_location,
    l.id_salle,
    sal.nom                             AS nom_salle,
    f.etat                              AS facture_etat,
    l.montant_total,
    l.montant_mensuel_moyen,
    l.statut,
    CASE
        WHEN l.id_facture IS NOT NULL THEN
            CASE f.etat
                WHEN 'Payée'              THEN 'paye'
                WHEN 'Partiellement payée' THEN 'partiel'
                ELSE 'non_paye'
            END
        ELSE l.etat_paiement
    END                                 AS etat_paiement,
    COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0)
                                        AS montant_paye,
    l.montant_total - COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0)
                                        AS montant_restant,
    CASE
        WHEN l.montant_total > 0
        THEN ROUND(COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0)
             / l.montant_total * 100, 2)
        ELSE 0
    END                                 AS pourcentage_paye,
    COALESCE(SUM(CASE WHEN ld.est_paye THEN 1 ELSE 0 END), 0)
                                        AS mois_payes,
    COALESCE(COUNT(ld.id), 0)           AS total_mois,
    COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0)
                                        AS montant_paye_total,
    l.montant_total - COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0)
                                        AS solde_restant,
    l.date_creation,
    l.date_modification,
    l.createur_id,
    l.modificateur_id
FROM loyer l
JOIN    client  c   ON c.id   = l.id_client
LEFT JOIN services svc ON svc.id = l.id_service
LEFT JOIN salle    sal ON sal.id = l.id_salle
LEFT JOIN facture  f   ON f.id_facture   = l.id_facture
LEFT JOIN loyer_detail ld ON ld.id_loyer = l.id_loyer
GROUP BY
    l.id_loyer, l.numero_loyer, l.numero_sequence, l.annee_loyer,
    l.id_client, c.prenom, c.nom, c.email, c.telephone,
    c.rue, c.numero, c.code_postal, c.localite,
    l.date_creation_loyer, l.periode_debut, l.periode_fin, l.duree_mois,
    l.motif, l.id_service, l.description, l.afficher_dates_paiement,
    l.id_facture, l.id_contrat_location, l.id_salle, sal.nom,
    f.etat, l.montant_total, l.montant_mensuel_moyen, l.statut,
    l.etat_paiement, l.date_creation, l.date_modification,
    l.createur_id, l.modificateur_id;

SELECT '✅ Vue v_loyers_complets mise à jour' AS check_view;

-- ============================================================================
-- ÉTAPE 4 : Vérification finale
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 4 : Loyers avec leur salle ===' AS '';

SELECT
    l.numero_loyer,
    l.annee_loyer,
    sal.nom         AS salle,
    lsc.id          AS id_contrat
FROM loyer l
LEFT JOIN salle sal ON sal.id = l.id_salle
LEFT JOIN location_salle_contrat lsc ON lsc.id = l.id_contrat_location
ORDER BY l.annee_loyer DESC, l.numero_loyer
LIMIT 20;

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;

SELECT '' AS '';
SELECT '============================================================================' AS '';
SELECT '✅ MIGRATION 038 TERMINÉE' AS '';
SELECT '============================================================================' AS '';