-- ============================================================================
-- Migration 032 : Déplacer le motif de location_salle_detail vers
--                 location_salle_contrat
--
-- Contexte : Le motif est unique par contrat (client × année × salle).
--            Il était dupliqué dans chaque détail mensuel — incorrect.
-- ============================================================================

SET NAMES utf8mb4;
SET @OLD_FK=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

SELECT '=== Migration 032 : motif vers location_salle_contrat ===' AS '';

-- ── ÉTAPE 1 : Ajouter motif dans location_salle_contrat ──────────────────────
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='location_salle_contrat' AND COLUMN_NAME='motif');
SET @sql = IF(@col=0,
    'ALTER TABLE `location_salle_contrat`
     ADD COLUMN `motif` VARCHAR(255) NULL DEFAULT NULL
     COMMENT "Motif de location (unique par contrat)"
     AFTER `annee`',
    'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT CASE WHEN COUNT(*)=1
    THEN '✅ Colonne motif ajoutée dans location_salle_contrat'
    ELSE '❌ Échec ajout motif'
END AS check_contrat
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='location_salle_contrat' AND COLUMN_NAME='motif';

-- ── ÉTAPE 2 : Migrer les motifs existants depuis les détails ─────────────────
-- Pour chaque contrat, prendre le premier motif non vide trouvé dans ses détails
UPDATE location_salle_contrat lsc
JOIN (
    SELECT id_contrat, MIN(motif) AS motif_source
    FROM location_salle_detail
    WHERE motif IS NOT NULL AND motif != ''
    GROUP BY id_contrat
) src ON src.id_contrat = lsc.id
SET lsc.motif = src.motif_source
WHERE lsc.motif IS NULL;

SELECT ROW_COUNT() AS contrats_motif_migres;

-- ── ÉTAPE 3 : Supprimer la colonne motif de location_salle_detail ────────────
SET @col2 = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='location_salle_detail' AND COLUMN_NAME='motif');
SET @sql2 = IF(@col2=1,
    'ALTER TABLE `location_salle_detail` DROP COLUMN `motif`',
    'DO 0');
PREPARE s FROM @sql2; EXECUTE s; DEALLOCATE PREPARE s;

SELECT CASE WHEN COUNT(*)=0
    THEN '✅ Colonne motif supprimée de location_salle_detail'
    ELSE '⚠️ Colonne motif toujours présente dans location_salle_detail'
END AS check_detail
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='location_salle_detail' AND COLUMN_NAME='motif';

-- ── ÉTAPE 4 : Vérification finale ────────────────────────────────────────────
SELECT lsc.id, lsc.id_client, lsc.annee, lsc.motif,
       COUNT(lsd.id) AS nb_details
FROM location_salle_contrat lsc
LEFT JOIN location_salle_detail lsd ON lsd.id_contrat = lsc.id
GROUP BY lsc.id ORDER BY lsc.id DESC LIMIT 5;

SET FOREIGN_KEY_CHECKS=@OLD_FK;
SELECT '✅ MIGRATION 032 TERMINÉE' AS '';