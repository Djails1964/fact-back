-- ============================================================================
-- Migration 018 : Suppression id_unite de la table loyer
-- ============================================================================
-- Date: 2026-03-16
-- Contexte: Un loyer est identifié par (id_client, annee, id_service).
--           id_service est unique par salle (configuré dans les paramètres).
--           id_unite n'a pas sa place dans loyer car un loyer peut couvrir
--           plusieurs types de location (unités) pour une même salle.
-- ============================================================================

SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

SELECT '============================================================================' AS '';
SELECT 'Migration 018: Suppression id_unite de loyer' AS '';
SELECT '============================================================================' AS '';

-- ── FK id_unite ───────────────────────────────────────────────────────────────

SELECT '' AS '';
SELECT '=== Suppression clé étrangère fk_loyer_unite ===' AS '';

SET @fk = (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
           WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_loyer_unite');
SET @sql = IF(@fk > 0, 'ALTER TABLE `loyer` DROP FOREIGN KEY `fk_loyer_unite`', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT IF(@fk > 0, '✅ fk_loyer_unite supprimée', 'ℹ️  absente') AS fk_unite;

-- ── Index lié à id_unite ──────────────────────────────────────────────────────

SET @idx = (SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loyer'
            AND INDEX_NAME = 'idx_loyer_client_tarif');
SET @sql = IF(@idx > 0, 'ALTER TABLE `loyer` DROP INDEX `idx_loyer_client_tarif`', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT IF(@idx > 0, '✅ idx_loyer_client_tarif supprimé', 'ℹ️  absent') AS idx_tarif;

-- ── Colonne id_unite ──────────────────────────────────────────────────────────

SELECT '' AS '';
SELECT '=== Suppression colonne id_unite ===' AS '';

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loyer' AND COLUMN_NAME = 'id_unite');
SET @sql = IF(@c > 0, 'ALTER TABLE `loyer` DROP COLUMN `id_unite`', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT IF(@c > 0, '✅ id_unite supprimée de loyer', 'ℹ️  absente') AS col_unite;

-- ── Recréation vue v_loyers_complets (sans id_unite) ─────────────────────────

SELECT '' AS '';
SELECT '=== Recréation vue v_loyers_complets ===' AS '';

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
    l.montant_total, l.montant_mensuel_moyen,
    l.statut, l.etat_paiement,
    COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0) AS montant_paye,
    l.montant_total - COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0) AS montant_restant,
    ROUND((COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0) / NULLIF(l.montant_total, 0)) * 100, 2) AS pourcentage_paye,
    COUNT(CASE WHEN ld.est_paye THEN 1 END) AS mois_payes,
    COUNT(ld.id) AS total_mois,
    COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0) AS montant_paye_total,
    l.montant_total - COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0) AS solde_restant,
    l.date_creation, l.date_modification, l.createur_id, l.modificateur_id
FROM loyer l
INNER JOIN client c ON l.id_client = c.id
LEFT JOIN loyer_detail ld ON l.id_loyer = ld.id_loyer
GROUP BY
    l.id_loyer, l.numero_loyer, l.numero_sequence, l.id_client,
    c.prenom, c.nom, c.email, c.telephone, c.rue, c.numero, c.code_postal, c.localite,
    l.date_creation_loyer, l.periode_debut, l.periode_fin, l.duree_mois,
    l.motif, l.id_service, l.description, l.afficher_dates_paiement,
    l.montant_total, l.montant_mensuel_moyen,
    l.statut, l.etat_paiement,
    l.date_creation, l.date_modification, l.createur_id, l.modificateur_id;

SELECT CASE WHEN COUNT(*) = 1 THEN '✅ Vue recréée sans id_unite'
            ELSE '❌ Échec recréation vue' END AS check_vue
FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'v_loyers_complets';

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SELECT '✅ MIGRATION 018 TERMINÉE' AS '';