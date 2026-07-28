-- ============================================================================
-- Migration 034 : Ajout colonne categorie_motifs dans la table salle
--
-- Contexte :
--   Plusieurs salles peuvent partager la même liste de motifs (ex: "Salle 1"
--   et "Salle 2" utilisent toutes les deux la catégorie "Salle").
--   Ce champ fait le lien entre une salle et sa catégorie de motifs dans
--   la table parametres (groupe='LocationSalle', sous_groupe='Motifs').
-- ============================================================================

SET NAMES utf8mb4;
SET @OLD_FK=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

SELECT '============================================================================' AS '';
SELECT 'Migration 034 : categorie_motifs dans la table salle' AS '';
SELECT '============================================================================' AS '';

-- ── ÉTAPE 1 : Ajouter la colonne categorie_motifs ────────────────────────────
SET @col = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND   TABLE_NAME   = 'salle'
    AND   COLUMN_NAME  = 'categorie_motifs'
);
SET @sql = IF(@col = 0,
    'ALTER TABLE `salle`
     ADD COLUMN `categorie_motifs` VARCHAR(100) NULL DEFAULT NULL
     COMMENT "Catégorie de motifs associée (clé dans parametres groupe=LocationSalle sous_groupe=Motifs)"
     AFTER `facturation_utilisation`',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT CASE WHEN COUNT(*) = 1
    THEN '✅ Colonne categorie_motifs ajoutée'
    ELSE '❌ Échec ajout categorie_motifs'
END AS check_col
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salle' AND COLUMN_NAME = 'categorie_motifs';

-- ── ÉTAPE 2 : Pré-remplir depuis les salles existantes ───────────────────────
-- Si une salle a un nom correspondant exactement à une catégorie de motifs
-- connue ('Cabinet' ou 'Salle'), on initialise la colonne automatiquement.
-- Les autres salles restent à NULL et doivent être configurées manuellement.

UPDATE `salle`
SET `categorie_motifs` = CASE
    WHEN `nom` = 'Cabinet' THEN 'Cabinet'
    WHEN `nom` LIKE 'Salle%' THEN 'Salle'
    ELSE NULL
END
WHERE `categorie_motifs` IS NULL;

SELECT ROW_COUNT() AS salles_pre_remplies;

-- ── ÉTAPE 3 : Vérification ────────────────────────────────────────────────────
SELECT id, nom, categorie_motifs
FROM salle
ORDER BY nom ASC;

SET FOREIGN_KEY_CHECKS=@OLD_FK;
SELECT '✅ MIGRATION 034 TERMINÉE' AS '';