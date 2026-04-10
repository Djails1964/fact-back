-- ============================================================================
-- Migration 022 : Lien loyer ↔ facture
--
-- Contexte :
--   Certains loyers génèrent une facture (type_document = 'facture' sur la salle).
--   On stocke l'id de cette facture dans le loyer pour :
--     - empêcher le paiement direct du loyer (passer par la facture)
--     - calculer l'état de paiement du loyer depuis les paiements de la facture
--     - exclure ces loyers de la section "Payer un loyer" dans PaiementForm
-- ============================================================================

SELECT '=== Migration 022 : Lien loyer ↔ facture ===' AS '';

-- ----------------------------------------------------------------------------
-- ÉTAPE 1 : Ajouter id_facture dans la table loyer
-- ----------------------------------------------------------------------------
SELECT '=== ÉTAPE 1 : Colonne id_facture dans loyer ===' AS '';

SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'loyer'
      AND COLUMN_NAME  = 'id_facture'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE loyer
       ADD COLUMN id_facture INT NULL DEFAULT NULL
           COMMENT "Facture générée depuis ce loyer (NULL si paiement direct)"
           AFTER id_service,
       ADD CONSTRAINT fk_loyer_facture
           FOREIGN KEY (id_facture) REFERENCES facture(id_facture)
           ON DELETE SET NULL',
    'SELECT "Colonne id_facture existe déjà dans loyer" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT CASE WHEN COUNT(*) = 1 THEN '✅ Colonne id_facture présente dans loyer'
            ELSE '❌ Échec' END AS check_col
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loyer' AND COLUMN_NAME = 'id_facture';

-- ----------------------------------------------------------------------------
-- ÉTAPE 2 : Recréer v_loyers_complets avec id_facture
--           et etat_paiement calculé depuis la facture liée si applicable
-- ----------------------------------------------------------------------------
SELECT '=== ÉTAPE 2 : Recréation vue v_loyers_complets ===' AS '';

DROP VIEW IF EXISTS v_loyers_complets;

CREATE VIEW v_loyers_complets AS
SELECT
    l.id_loyer, l.numero_loyer, l.numero_sequence, l.id_client,
    c.prenom AS prenom_client, c.nom AS nom_client,
    CONCAT(c.prenom, ' ', c.nom) AS nom_complet_client,
    c.email AS email_client, c.telephone AS telephone_client,
    c.rue AS rue_client, c.numero AS numero_client,
    c.code_postal AS code_postal_client, c.localite AS localite_client,
    l.date_creation_loyer, l.periode_debut, l.periode_fin, l.duree_mois,
    l.motif, l.id_service, l.description, l.afficher_dates_paiement,
    l.id_facture,
    f.etat AS facture_etat,
    l.montant_total, l.montant_mensuel_moyen,
    l.statut,

    CASE
        WHEN l.id_facture IS NOT NULL THEN
            CASE f.etat
                WHEN 'Payée'               THEN 'paye'
                WHEN 'Partiellement payée' THEN 'partiel'
                ELSE                            'non_paye'
            END
        ELSE l.etat_paiement
    END AS etat_paiement,

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
    ) * 100, 2) AS pourcentage_paye,

    COUNT(CASE WHEN ld.est_paye THEN 1 END)  AS mois_payes,
    COUNT(ld.id)                              AS total_mois,
    COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0) AS montant_paye_total,
    l.montant_total - COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0) AS solde_restant,
    l.date_creation, l.date_modification, l.createur_id, l.modificateur_id

FROM loyer l
INNER JOIN client c ON l.id_client = c.id
LEFT JOIN loyer_detail ld ON l.id_loyer = ld.id_loyer
LEFT JOIN facture f      ON l.id_facture = f.id_facture
GROUP BY
    l.id_loyer, l.numero_loyer, l.numero_sequence, l.id_client,
    c.prenom, c.nom, c.email, c.telephone, c.rue, c.numero, c.code_postal, c.localite,
    l.date_creation_loyer, l.periode_debut, l.periode_fin, l.duree_mois,
    l.motif, l.id_service, l.description, l.afficher_dates_paiement,
    l.id_facture,
    l.montant_total, l.montant_mensuel_moyen,
    l.statut, l.etat_paiement,
    f.etat,
    l.date_creation, l.date_modification, l.createur_id, l.modificateur_id;

SELECT CASE WHEN COUNT(*) = 1 THEN '✅ Vue v_loyers_complets recréée avec id_facture'
            ELSE '❌ Échec' END AS check_vue
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'v_loyers_complets';

SELECT '=== Migration 022 terminée ===' AS '';