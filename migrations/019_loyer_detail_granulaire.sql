-- ============================================================================
-- Migration 019 : loyer_detail (id_unite, quantite, description) +
--                 suppression note dans location_salle_detail
-- ============================================================================
-- Date: 2026-03-17
-- Contexte:
--   Option A : loyer_detail devient granulaire (mois + type de location).
--   Pour un même mois on peut avoir plusieurs lignes, une par id_unite.
--   Clé unique : (id_loyer, numero_mois, id_unite).
--
--   note dans location_salle_detail est remplacé par description (déjà présent).
-- ============================================================================

SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

SELECT '============================================================================' AS '';
SELECT 'Migration 019' AS '';
SELECT '============================================================================' AS '';

-- ============================================================================
-- PARTIE 1 : loyer_detail — id_unite, quantite, description
-- ============================================================================

SELECT '' AS '';
SELECT '=== 1.1 : Ajout id_unite dans loyer_detail ===' AS '';

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loyer_detail'
          AND COLUMN_NAME = 'id_unite');
SET @sql = IF(@c = 0,
    'ALTER TABLE `loyer_detail`
         ADD COLUMN `id_unite` INT NULL DEFAULT NULL
         COMMENT "Type de location (FK unites.id)"
         AFTER `id_loyer`',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT IF(@c = 0, '✅ id_unite ajouté', 'ℹ️  déjà présent') AS check_id_unite;

SET @fk = (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
           WHERE CONSTRAINT_SCHEMA = DATABASE()
           AND CONSTRAINT_NAME = 'fk_loyer_detail_unite');
SET @sql = IF(@fk = 0,
    'ALTER TABLE `loyer_detail`
         ADD CONSTRAINT `fk_loyer_detail_unite`
         FOREIGN KEY (`id_unite`) REFERENCES `unites`(`id`)
         ON DELETE SET NULL ON UPDATE CASCADE',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT IF(@fk = 0, '✅ FK fk_loyer_detail_unite ajoutée', 'ℹ️  déjà présente') AS check_fk;

-- ──────────────────────────────────────────────────────────────────────────────

SELECT '' AS '';
SELECT '=== 1.2 : Ajout quantite dans loyer_detail ===' AS '';

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loyer_detail'
          AND COLUMN_NAME = 'quantite');
SET @sql = IF(@c = 0,
    'ALTER TABLE `loyer_detail`
         ADD COLUMN `quantite` DECIMAL(8,2) NULL DEFAULT NULL
         COMMENT "Quantite pour ce type de location ce mois"
         AFTER `id_unite`',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT IF(@c = 0, '✅ quantite ajoutée', 'ℹ️  déjà présente') AS check_quantite;

-- ──────────────────────────────────────────────────────────────────────────────

SELECT '' AS '';
SELECT '=== 1.3 : Ajout description dans loyer_detail ===' AS '';

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loyer_detail'
          AND COLUMN_NAME = 'description');
SET @sql = IF(@c = 0,
    'ALTER TABLE `loyer_detail`
         ADD COLUMN `description` VARCHAR(500) NULL DEFAULT NULL
         COMMENT "Description du type de location (propagee identique sur toute annee)"
         AFTER `quantite`',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT IF(@c = 0, '✅ description ajoutée', 'ℹ️  déjà présente') AS check_description;

-- ──────────────────────────────────────────────────────────────────────────────

SELECT '' AS '';
SELECT '=== 1.4 : Contrainte unique (id_loyer, numero_mois, id_unite) ===' AS '';

-- Supprimer ancienne contrainte unique sur (id_loyer, numero_mois) si existante
-- Plusieurs noms possibles selon la version de création
SET @old_uk1 = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loyer_detail'
               AND CONSTRAINT_NAME = 'uk_loyer_detail_mois');
SET @sql = IF(@old_uk1 > 0,
    'ALTER TABLE `loyer_detail` DROP INDEX `uk_loyer_detail_mois`', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @old_uk2 = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loyer_detail'
               AND CONSTRAINT_NAME = 'uk_detail_loyer_mois');
SET @sql = IF(@old_uk2 > 0,
    'ALTER TABLE `loyer_detail` DROP INDEX `uk_detail_loyer_mois`', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT IF(@old_uk1 > 0 OR @old_uk2 > 0, '✅ Ancienne contrainte supprimée', 'ℹ️  absente') AS check_old_uk;

SET @new_uk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loyer_detail'
               AND CONSTRAINT_NAME = 'uk_loyer_detail_mois_unite');
SET @sql = IF(@new_uk = 0,
    'ALTER TABLE `loyer_detail`
         ADD CONSTRAINT `uk_loyer_detail_mois_unite`
         UNIQUE (`id_loyer`, `numero_mois`, `id_unite`)',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT IF(@new_uk = 0,
    '✅ Contrainte unique (id_loyer, numero_mois, id_unite) créée',
    'ℹ️  déjà présente') AS check_new_uk;

-- ============================================================================
-- PARTIE 2 : location_salle_detail — suppression note
-- ============================================================================

SELECT '' AS '';
SELECT '=== 2.1 : Suppression note dans location_salle_detail ===' AS '';

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'location_salle_detail'
          AND COLUMN_NAME = 'note');
SET @sql = IF(@c > 0,
    'ALTER TABLE `location_salle_detail` DROP COLUMN `note`', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT IF(@c > 0, '✅ note supprimé de location_salle_detail', 'ℹ️  absent') AS check_note;

-- ============================================================================
-- PARTIE 3 : Vérification structure finale
-- ============================================================================

SELECT '' AS '';
SELECT '=== Structure loyer_detail ===' AS '';
SELECT ORDINAL_POSITION AS pos, COLUMN_NAME AS colonne, COLUMN_TYPE AS type, IS_NULLABLE AS nullable
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loyer_detail'
ORDER BY ORDINAL_POSITION;

SELECT '' AS '';
SELECT '=== Structure location_salle_detail ===' AS '';
SELECT ORDINAL_POSITION AS pos, COLUMN_NAME AS colonne, COLUMN_TYPE AS type, IS_NULLABLE AS nullable
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'location_salle_detail'
ORDER BY ORDINAL_POSITION;

-- ============================================================================
-- PARTIE 4 : Recréation vue v_loyers_complets (inchangée — SUM reste correct)
-- ============================================================================

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
    COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0)         AS montant_paye,
    l.montant_total - COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0) AS montant_restant,
    ROUND((COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0)
           / NULLIF(l.montant_total, 0)) * 100, 2)                             AS pourcentage_paye,
    COUNT(CASE WHEN ld.est_paye THEN 1 END)                                    AS mois_payes,
    COUNT(ld.id)                                                               AS total_mois,
    COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0)         AS montant_paye_total,
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

SELECT CASE WHEN COUNT(*) = 1 THEN '✅ Vue v_loyers_complets recréée'
            ELSE '❌ Échec' END AS check_vue
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'v_loyers_complets';

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;

SELECT '' AS '';
SELECT '✅ MIGRATION 019 TERMINÉE' AS '';