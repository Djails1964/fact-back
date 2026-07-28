-- ============================================================================
-- Migration 035 : Lier un contrat de location à une salle spécifique
--
-- Contexte :
--   Un contrat = un client × une année × une salle.
--   Chaque salle a son propre contrat pour un client donné.
--   Le motif est unique par contrat puisqu'il est lié à la salle.
--
-- Changements :
--   1. Ajouter id_salle (FK) dans location_salle_contrat
--   2. Remplir rétroactivement depuis le premier détail de chaque contrat
--   3. Ajouter contrainte unique (id_client, annee, id_salle)
--   4. Ajouter FK vers salle(id)
-- ============================================================================

SET NAMES utf8mb4;
SET @OLD_FK=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

SELECT '============================================================================' AS '';
SELECT 'Migration 035 : id_salle dans location_salle_contrat' AS '';
SELECT '============================================================================' AS '';

-- ── ÉTAPE 1 : Ajouter colonne id_salle ───────────────────────────────────────
SET @col = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND   TABLE_NAME   = 'location_salle_contrat'
    AND   COLUMN_NAME  = 'id_salle'
);
SET @sql = IF(@col = 0,
    'ALTER TABLE `location_salle_contrat`
     ADD COLUMN `id_salle` INT UNSIGNED NULL DEFAULT NULL
     COMMENT "FK vers salle(id) — salle associée à ce contrat (un contrat = un client × une année × une salle)"
     AFTER `libelle`',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT CASE WHEN COUNT(*) = 1
    THEN '✅ Colonne id_salle ajoutée'
    ELSE '❌ Échec ajout id_salle' END AS check_col
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'location_salle_contrat' AND COLUMN_NAME = 'id_salle';

-- ── ÉTAPE 2 : Remplir rétroactivement depuis les détails existants ────────────
-- Pour chaque contrat, prendre l'id_salle du premier détail (par id ASC)
UPDATE location_salle_contrat lsc
JOIN (
    SELECT id_contrat, MIN(id_salle) AS id_salle_src
    FROM location_salle_detail
    WHERE id_salle IS NOT NULL
    GROUP BY id_contrat
) src ON src.id_contrat = lsc.id
SET lsc.id_salle = src.id_salle_src
WHERE lsc.id_salle IS NULL;

SELECT ROW_COUNT() AS contrats_remplis;

-- Fallback : contrats sans détail avec id_salle — tenter via nom salle
UPDATE location_salle_contrat lsc
JOIN (
    SELECT lsd.id_contrat, s.id AS id_salle_src
    FROM location_salle_detail lsd
    JOIN salle s ON s.nom = lsd.salle
    WHERE lsd.id_salle IS NULL
    GROUP BY lsd.id_contrat, s.id
) src ON src.id_contrat = lsc.id
SET lsc.id_salle = src.id_salle_src
WHERE lsc.id_salle IS NULL;

SELECT ROW_COUNT() AS contrats_remplis_fallback;

-- ── ÉTAPE 3 : FK vers salle(id) ──────────────────────────────────────────────
SET @fk = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA    = DATABASE()
    AND   TABLE_NAME      = 'location_salle_contrat'
    AND   CONSTRAINT_NAME = 'fk_contrat_salle'
);
SET @sql = IF(@fk = 0,
    'ALTER TABLE `location_salle_contrat`
     ADD CONSTRAINT `fk_contrat_salle`
     FOREIGN KEY (`id_salle`) REFERENCES `salle`(`id`)
     ON DELETE RESTRICT ON UPDATE CASCADE',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT CASE WHEN COUNT(*) = 1
    THEN '✅ FK fk_contrat_salle ajoutée'
    ELSE '❌ FK fk_contrat_salle manquante' END AS check_fk
FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'location_salle_contrat' AND CONSTRAINT_NAME = 'fk_contrat_salle';

-- ── ÉTAPE 4 : Contrainte unique (id_client, annee, id_salle) ─────────────────
-- Remplace l'absence de contrainte introduite par migration 023
SET @uk = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND   TABLE_NAME   = 'location_salle_contrat'
    AND   INDEX_NAME   = 'uk_contrat_client_annee_salle'
);
SET @sql = IF(@uk = 0,
    'ALTER TABLE `location_salle_contrat`
     ADD UNIQUE KEY `uk_contrat_client_annee_salle`
     (`id_client`, `annee`, `id_salle`)
     COMMENT "Un seul contrat par client × année × salle"',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT CASE WHEN COUNT(*) >= 1
    THEN '✅ Contrainte uk_contrat_client_annee_salle ajoutée'
    ELSE '❌ Contrainte manquante' END AS check_uk
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
AND   TABLE_NAME   = 'location_salle_contrat'
AND   INDEX_NAME   = 'uk_contrat_client_annee_salle';

-- ── ÉTAPE 5 : Vérification finale ────────────────────────────────────────────
SELECT lsc.id, lsc.id_client, lsc.annee, lsc.id_salle, s.nom AS salle_nom,
       COUNT(lsd.id) AS nb_details
FROM location_salle_contrat lsc
LEFT JOIN salle s ON s.id = lsc.id_salle
LEFT JOIN location_salle_detail lsd ON lsd.id_contrat = lsc.id
GROUP BY lsc.id
ORDER BY lsc.id DESC LIMIT 10;

SET FOREIGN_KEY_CHECKS=@OLD_FK;
SELECT '✅ MIGRATION 035 TERMINÉE' AS '';