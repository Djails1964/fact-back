-- ============================================================================
-- Migration 040 : Ajout est_forfait dans type_contrat_location
--                 Suppression type_document (dérivé de est_forfait)
--
-- Règle : est_forfait = 1 → confirmation de paiement
--         est_forfait = 0 → facture
--
-- Dépend de : 039_loyer_id_type_contrat.sql
-- ============================================================================

SET NAMES utf8mb4;

SELECT '============================================================================' AS '';
SELECT 'Migration 040 : est_forfait dans type_contrat_location' AS '';
SELECT '============================================================================' AS '';

-- ============================================================================
-- ÉTAPE 1 : Ajout est_forfait sur type_contrat_location
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 1 : Ajout est_forfait ===' AS '';

SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'type_contrat_location'
    AND COLUMN_NAME = 'est_forfait');

SET @sql = IF(@col = 0,
    'ALTER TABLE `type_contrat_location`
     ADD COLUMN `est_forfait` TINYINT(1) NOT NULL DEFAULT 0
     COMMENT "1 = forfait (confirmation), 0 = utilisation (facture)"
     AFTER `type_client_requis`',
    'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT CASE WHEN COUNT(*) = 1 THEN '✅ Colonne est_forfait ajoutée' ELSE '❌ Échec' END AS check_col
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'type_contrat_location' AND COLUMN_NAME = 'est_forfait';

-- ============================================================================
-- ÉTAPE 2 : Migration depuis type_document
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 2 : Migration depuis type_document ===' AS '';

-- Si type_document existe encore, dériver est_forfait depuis sa valeur
SET @has_type_doc = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'type_contrat_location'
    AND COLUMN_NAME = 'type_document');

SET @sql = IF(@has_type_doc > 0,
    'UPDATE type_contrat_location
     SET est_forfait = CASE WHEN type_document = ''confirmation'' THEN 1 ELSE 0 END',
    -- Sinon initialiser par convention : id=1 (Location au forfait) → 1, reste → 0
    'UPDATE type_contrat_location
     SET est_forfait = CASE WHEN id = 1 THEN 1 ELSE 0 END');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT CONCAT('✅ ', ROW_COUNT(), ' type(s) migré(s)') AS migration;

SELECT id, nom, est_forfait FROM type_contrat_location ORDER BY id;

-- ============================================================================
-- ÉTAPE 3 : Suppression type_document de type_contrat_location
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 3 : Suppression type_document ===' AS '';

SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'type_contrat_location'
    AND COLUMN_NAME = 'type_document');

SET @sql = IF(@col > 0,
    'ALTER TABLE `type_contrat_location` DROP COLUMN `type_document`',
    'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT '✅ Colonne type_document supprimée de type_contrat_location' AS info;

-- ============================================================================
-- ÉTAPE 4 : Mise à jour v_loyers_complets
--           type_document dérivé de est_forfait via CASE
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 4 : Mise à jour v_loyers_complets ===' AS '';

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
    l.id_type_contrat,
    tcl.nom                             AS nom_type_contrat,
    tcl.est_forfait,
    -- type_document dérivé de est_forfait (compatibilité)
    CASE WHEN tcl.est_forfait = 1 THEN 'confirmation' ELSE 'facture' END
                                        AS type_document,
    tcl.categorie_motifs                AS categorie_motifs_contrat,
    f.etat                              AS facture_etat,
    l.montant_total,
    l.montant_mensuel_moyen,
    l.statut,
    CASE
        WHEN l.id_facture IS NOT NULL THEN
            CASE f.etat
                WHEN 'Payée'               THEN 'paye'
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
JOIN    client  c    ON c.id           = l.id_client
LEFT JOIN salle sal  ON sal.id         = l.id_salle
LEFT JOIN type_contrat_location tcl ON tcl.id = l.id_type_contrat
LEFT JOIN location_salle_contrat lsc ON lsc.id = l.id_contrat_location
LEFT JOIN facture f   ON f.id_facture  = l.id_facture
LEFT JOIN loyer_detail ld ON ld.id_loyer = l.id_loyer
GROUP BY
    l.id_loyer, l.numero_loyer, l.numero_sequence, l.annee_loyer,
    l.id_client, c.prenom, c.nom, c.email, c.telephone,
    c.rue, c.numero, c.code_postal, c.localite,
    l.date_creation_loyer, l.periode_debut, l.periode_fin, l.duree_mois,
    l.motif, l.id_service, l.description, l.afficher_dates_paiement,
    l.id_facture, l.id_contrat_location, l.id_salle, sal.nom,
    l.id_type_contrat, tcl.nom, tcl.est_forfait, tcl.categorie_motifs,
    f.etat, l.montant_total, l.montant_mensuel_moyen,
    l.statut, l.etat_paiement, l.date_creation, l.date_modification,
    l.createur_id, l.modificateur_id;

SELECT '✅ Vue v_loyers_complets mise à jour' AS resultat;

-- ============================================================================
-- ÉTAPE 5 : Vérification
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 5 : Types de contrat ===' AS '';

SELECT id, nom, est_forfait,
       CASE WHEN est_forfait = 1 THEN 'confirmation' ELSE 'facture' END AS type_document_derive,
       categorie_motifs, type_client_requis, actif
FROM type_contrat_location ORDER BY id;

SELECT '' AS '';
SELECT '============================================================================' AS '';
SELECT '✅ MIGRATION 040 TERMINÉE' AS '';
SELECT '============================================================================' AS '';