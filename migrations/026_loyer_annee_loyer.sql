-- ============================================================================
-- Migration 026 : Ajout colonne annee_loyer dans loyer
--
-- Contexte : LoyerControleur::genererNumeroLoyer utilise annee_loyer pour
--            la numérotation par client ET par année (LOY-{client}-{annee}-{seq}).
--            Cette colonne doit exister avant toute création de loyer.
--
-- Dépend de : migrations 022, 023, 024, 025
-- ============================================================================

SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

SELECT '============================================================================' AS '';
SELECT 'Migration 026: Ajout annee_loyer dans loyer' AS '';
SELECT '============================================================================' AS '';

-- ── ÉTAPE 1 : Ajouter la colonne annee_loyer ─────────────────────────────────
SET @col = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND   TABLE_NAME   = 'loyer'
    AND   COLUMN_NAME  = 'annee_loyer'
);
SET @sql = IF(@col = 0,
    'ALTER TABLE `loyer`
     ADD COLUMN `annee_loyer` SMALLINT NULL DEFAULT NULL
     COMMENT "Année du loyer — extraite de periode_debut, utilisée pour la numérotation"
     AFTER `numero_sequence`',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT CASE WHEN COUNT(*) = 1 THEN '✅ Colonne annee_loyer ajoutée'
            ELSE '❌ Colonne annee_loyer manquante' END AS check_col
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loyer' AND COLUMN_NAME = 'annee_loyer';

-- ── ÉTAPE 2 : Rétro-remplissage depuis periode_debut ─────────────────────────
UPDATE `loyer`
SET    `annee_loyer` = YEAR(`periode_debut`)
WHERE  `annee_loyer` IS NULL
AND    `periode_debut` IS NOT NULL;

SELECT CONCAT('✅ ', ROW_COUNT(), ' loyer(s) mis à jour avec annee_loyer') AS check_update;

-- ── ÉTAPE 3 : Index pour la numérotation ─────────────────────────────────────
SET @idx = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND   TABLE_NAME   = 'loyer'
    AND   INDEX_NAME   = 'idx_loyer_client_annee'
);
SET @sql = IF(@idx = 0,
    'ALTER TABLE `loyer`
     ADD INDEX `idx_loyer_client_annee` (`id_client`, `annee_loyer`)',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT '✅ Index idx_loyer_client_annee présent' AS '';

-- ── ÉTAPE 4 : Contrainte unique (id_client, annee_loyer, numero_sequence) ─────
-- Remplace l'ancienne uk_loyer_client_sequence (sans annee)
SET @old_uk = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND   TABLE_NAME   = 'loyer'
    AND   INDEX_NAME   = 'uk_loyer_client_sequence'
);
SET @sql = IF(@old_uk > 0,
    'ALTER TABLE `loyer` DROP INDEX `uk_loyer_client_sequence`',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT IF(@old_uk > 0, '✅ Ancienne contrainte uk_loyer_client_sequence supprimée', 'ℹ️ Déjà absente') AS check_old_uk;

SET @new_uk = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND   TABLE_NAME   = 'loyer'
    AND   INDEX_NAME   = 'uk_loyer_client_annee_sequence'
);
SET @sql = IF(@new_uk = 0,
    'ALTER TABLE `loyer`
     ADD UNIQUE KEY `uk_loyer_client_annee_sequence`
     (`id_client`, `annee_loyer`, `numero_sequence`)
     COMMENT "Séquence unique par client et par année"',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT '✅ Contrainte uk_loyer_client_annee_sequence présente' AS '';

-- ── ÉTAPE 5 : Recréer v_loyers_complets avec annee_loyer ─────────────────────
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
    l.description,
    l.afficher_dates_paiement,
    l.id_facture,
    l.id_contrat_location,
    f.etat                              AS facture_etat,
    l.montant_total,
    l.montant_mensuel_moyen,
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

    -- Montants : depuis paiements facture si liée, sinon depuis loyer_detail
    CASE
        WHEN l.id_facture IS NOT NULL
        THEN COALESCE(
            (SELECT SUM(p.montant_paye) FROM paiement p
             WHERE p.id_facture = l.id_facture AND p.statut = 'Confirme'), 0)
        ELSE COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0)
    END AS montant_paye,

    CASE
        WHEN l.id_facture IS NOT NULL
        THEN l.montant_total - COALESCE(
            (SELECT SUM(p.montant_paye) FROM paiement p
             WHERE p.id_facture = l.id_facture AND p.statut = 'Confirme'), 0)
        ELSE l.montant_total - COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0)
    END AS montant_restant,

    ROUND((
        CASE
            WHEN l.id_facture IS NOT NULL
            THEN COALESCE(
                (SELECT SUM(p.montant_paye) FROM paiement p
                 WHERE p.id_facture = l.id_facture AND p.statut = 'Confirme'), 0)
            ELSE COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0)
        END
        / NULLIF(l.montant_total, 0)
    ) * 100, 2)                         AS pourcentage_paye,

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
INNER JOIN client c        ON l.id_client   = c.id
LEFT JOIN  loyer_detail ld  ON l.id_loyer   = ld.id_loyer
LEFT JOIN  facture f        ON l.id_facture  = f.id_facture
GROUP BY
    l.id_loyer, l.numero_loyer, l.numero_sequence, l.annee_loyer, l.id_client,
    c.prenom, c.nom, c.email, c.telephone, c.rue, c.numero, c.code_postal, c.localite,
    l.date_creation_loyer, l.periode_debut, l.periode_fin, l.duree_mois,
    l.motif, l.id_service, l.description, l.afficher_dates_paiement,
    l.id_facture, l.id_contrat_location, f.etat,
    l.montant_total, l.montant_mensuel_moyen, l.statut, l.etat_paiement,
    l.date_creation, l.date_modification, l.createur_id, l.modificateur_id;

SELECT CASE WHEN COUNT(*) = 1
    THEN '✅ Vue v_loyers_complets recréée avec annee_loyer + id_contrat_location'
    ELSE '❌ Échec recréation vue'
END AS check_vue
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'v_loyers_complets';

-- ── VÉRIFICATION FINALE ───────────────────────────────────────────────────────
SELECT
    ORDINAL_POSITION AS pos,
    COLUMN_NAME      AS col,
    COLUMN_TYPE      AS type,
    IS_NULLABLE      AS nullable
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loyer'
AND   COLUMN_NAME IN ('numero_sequence', 'annee_loyer', 'id_facture', 'id_contrat_location')
ORDER BY ORDINAL_POSITION;

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SELECT '✅ MIGRATION 026 TERMINÉE' AS '';