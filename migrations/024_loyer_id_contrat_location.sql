-- ============================================================================
-- Migration 024 : Lier un loyer à son contrat de location de salle source
--
-- Permet de savoir si un contrat location_salle a déjà généré un loyer
-- et de mettre à jour ce loyer lors d'une re-génération.
-- ============================================================================

SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

SELECT '============================================================================' AS '';
SELECT 'Migration 024: id_contrat_location dans la table loyer' AS '';
SELECT '============================================================================' AS '';

-- ── ÉTAPE 1 : Ajouter la colonne ─────────────────────────────────────────────
SET @col = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND   TABLE_NAME   = 'loyer'
    AND   COLUMN_NAME  = 'id_contrat_location'
);
SET @sql = IF(@col = 0,
    'ALTER TABLE `loyer`
     ADD COLUMN `id_contrat_location` INT UNSIGNED NULL DEFAULT NULL
     COMMENT "ID du contrat location_salle_contrat ayant généré ce loyer (NULL si loyer manuel)"
     AFTER `id_facture`',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT CASE WHEN COUNT(*) = 1 THEN '✅ Colonne id_contrat_location ajoutée'
            ELSE '❌ Colonne id_contrat_location manquante' END AS check_col
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loyer' AND COLUMN_NAME = 'id_contrat_location';

-- ── ÉTAPE 2 : Clé étrangère (optionnelle, ON DELETE SET NULL) ─────────────────
SET @fk = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA  = DATABASE()
    AND   TABLE_NAME    = 'loyer'
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

SELECT '✅ Contrainte FK fk_loyer_contrat_location présente' AS '';

-- ── ÉTAPE 3 : Recréer v_loyers_complets avec id_contrat_location ─────────────
DROP VIEW IF EXISTS v_loyers_complets;

CREATE VIEW v_loyers_complets AS
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
    l.id_unite,
    l.description,
    l.afficher_dates_paiement,
    l.id_facture,
    l.id_contrat_location,
    f.etat                              AS facture_etat,
    l.montant_total,
    l.montant_mensuel_moyen,
    l.statut,
    l.etat_paiement,
    COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0)
                                        AS montant_paye,
    l.montant_total - COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0)
                                        AS montant_restant,
    ROUND(
        (COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0)
         / NULLIF(l.montant_total, 0)) * 100, 2
    )                                   AS pourcentage_paye,
    COUNT(CASE WHEN ld.est_paye THEN 1 END) AS mois_payes,
    COUNT(ld.id)                            AS total_mois,
    COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0)
                                        AS montant_paye_total,
    l.montant_total - COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0)
                                        AS solde_restant,
    l.date_creation,
    l.date_modification,
    l.createur_id,
    l.modificateur_id
FROM loyer l
INNER JOIN client c ON l.id_client = c.id
LEFT JOIN loyer_detail ld ON l.id_loyer = ld.id_loyer
LEFT JOIN facture f ON f.id = l.id_facture
GROUP BY
    l.id_loyer, l.numero_loyer, l.numero_sequence, l.annee_loyer, l.id_client,
    c.prenom, c.nom, c.email, c.telephone, c.rue, c.numero, c.code_postal, c.localite,
    l.date_creation_loyer, l.periode_debut, l.periode_fin, l.duree_mois,
    l.motif, l.id_service, l.id_unite, l.description, l.afficher_dates_paiement,
    l.id_facture, l.id_contrat_location, f.etat,
    l.montant_total, l.montant_mensuel_moyen, l.statut, l.etat_paiement,
    l.date_creation, l.date_modification, l.createur_id, l.modificateur_id;

SELECT '✅ Vue v_loyers_complets recréée avec id_contrat_location' AS '';

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SELECT '✅ MIGRATION 024 TERMINÉE' AS '';