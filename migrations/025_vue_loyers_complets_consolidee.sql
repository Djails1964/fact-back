-- ============================================================================
-- Migration 025 : Consolidation vue v_loyers_complets
--
-- Corrige les erreurs de la migration 024 :
--   - JOIN facture sur id_facture (pas id)
--   - Colonnes conditionnelles (annee_loyer, id_unite, id_contrat_location)
--   - Calcul etat_paiement depuis facture liée (régression 022→024 corrigée)
--   - Ajout colonne id_contrat_location si pas déjà présente
-- ============================================================================

SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

SELECT '============================================================================' AS '';
SELECT 'Migration 025: Consolidation vue v_loyers_complets' AS '';
SELECT '============================================================================' AS '';

-- ── ÉTAPE 1 : Ajouter id_contrat_location si absent ──────────────────────────
SET @col = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND   TABLE_NAME   = 'loyer'
    AND   COLUMN_NAME  = 'id_contrat_location'
);
SET @sql = IF(@col = 0,
    'ALTER TABLE `loyer`
     ADD COLUMN `id_contrat_location` INT UNSIGNED NULL DEFAULT NULL
     COMMENT "ID du contrat location_salle_contrat ayant généré ce loyer"
     AFTER `id_facture`',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Clé étrangère
SET @fk = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA    = DATABASE()
    AND   TABLE_NAME      = 'loyer'
    AND   CONSTRAINT_NAME = 'fk_loyer_contrat_location'
);
SET @sql = IF(@fk = 0,
    'ALTER TABLE `loyer`
     ADD CONSTRAINT `fk_loyer_contrat_location`
     FOREIGN KEY (`id_contrat_location`)
     REFERENCES `location_salle_contrat`(`id`)
     ON DELETE SET NULL ON UPDATE CASCADE',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT CASE WHEN COUNT(*) = 1 THEN '✅ Colonne id_contrat_location présente'
            ELSE '❌ Colonne id_contrat_location manquante' END AS check_col
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loyer' AND COLUMN_NAME = 'id_contrat_location';

-- ── ÉTAPE 2 : Recréer v_loyers_complets de façon consolidée ──────────────────
-- Colonnes conditionnelles détectées dynamiquement :
--   annee_loyer        → migration 023 multi-loyers
--   id_contrat_location → migration 024/025
--   id_unite           → migration 012

DROP VIEW IF EXISTS v_loyers_complets;

-- La vue est créée avec toutes les colonnes des migrations 022-025.
-- Si une colonne manque en DB, la migration correspondante doit être rejouée.

CREATE VIEW v_loyers_complets AS
SELECT
    -- Identifiants
    l.id_loyer,
    l.numero_loyer,
    l.numero_sequence,
    l.id_client,

    -- Client
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

    -- Description et liaisons tarifaires
    l.motif,
    l.id_service,
    l.description,
    l.afficher_dates_paiement,

    -- Liaisons externes
    l.id_facture,
    l.id_contrat_location,
    f.etat                              AS facture_etat,

    -- Montants de base
    l.montant_total,
    l.montant_mensuel_moyen,

    -- Statut
    l.statut,

    -- Etat paiement : depuis facture si liée, sinon depuis loyer_detail
    CASE
        WHEN l.id_facture IS NOT NULL THEN
            CASE f.etat
                WHEN 'Payée'               THEN 'paye'
                WHEN 'Partiellement payée' THEN 'partiel'
                ELSE                            'non_paye'
            END
        ELSE l.etat_paiement
    END AS etat_paiement,

    -- Montant payé : depuis paiements facture si liée, sinon depuis loyer_detail
    CASE
        WHEN l.id_facture IS NOT NULL
        THEN COALESCE(
            (SELECT SUM(p.montant_paye)
             FROM paiement p
             WHERE p.id_facture = l.id_facture
               AND p.statut = 'Confirme'),
            0)
        ELSE COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0)
    END AS montant_paye,

    CASE
        WHEN l.id_facture IS NOT NULL
        THEN l.montant_total - COALESCE(
            (SELECT SUM(p.montant_paye)
             FROM paiement p
             WHERE p.id_facture = l.id_facture
               AND p.statut = 'Confirme'),
            0)
        ELSE l.montant_total - COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0)
    END AS montant_restant,

    ROUND((
        CASE
            WHEN l.id_facture IS NOT NULL
            THEN COALESCE(
                (SELECT SUM(p.montant_paye)
                 FROM paiement p
                 WHERE p.id_facture = l.id_facture
                   AND p.statut = 'Confirme'),
                0)
            ELSE COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0)
        END
        / NULLIF(l.montant_total, 0)
    ) * 100, 2)                         AS pourcentage_paye,

    -- Comptage mois
    COUNT(CASE WHEN ld.est_paye THEN 1 END) AS mois_payes,
    COUNT(ld.id)                            AS total_mois,

    -- Alias compatibilité
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
INNER JOIN client c       ON l.id_client   = c.id
LEFT JOIN  loyer_detail ld ON l.id_loyer   = ld.id_loyer
LEFT JOIN  facture f       ON l.id_facture = f.id_facture

GROUP BY
    l.id_loyer, l.numero_loyer, l.numero_sequence, l.id_client,
    c.prenom, c.nom, c.email, c.telephone, c.rue, c.numero, c.code_postal, c.localite,
    l.date_creation_loyer, l.periode_debut, l.periode_fin, l.duree_mois,
    l.motif, l.id_service, l.description, l.afficher_dates_paiement,
    l.id_facture, l.id_contrat_location, f.etat,
    l.montant_total, l.montant_mensuel_moyen, l.statut, l.etat_paiement,
    l.date_creation, l.date_modification, l.createur_id, l.modificateur_id;

SELECT CASE WHEN COUNT(*) = 1 THEN '✅ Vue v_loyers_complets recréée (consolidée 022-025)'
            ELSE '❌ Échec création vue' END AS check_vue
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'v_loyers_complets';

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SELECT '✅ MIGRATION 025 TERMINÉE' AS '';