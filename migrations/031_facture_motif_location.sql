-- ============================================================================
-- Migration 031 : Ajout motif dans facture + déplacement motifs paramètres
--                 de groupe 'Loyer' vers 'LocationSalle'
--
-- Contexte :
--   - Le motif de location est lié à une salle (service tarifaire)
--   - Il est transmis : contrat location → loyer → facture
--   - Les paramètres de motifs passent de groupe='Loyer' à groupe='LocationSalle'
-- ============================================================================

SET NAMES utf8mb4;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

SELECT '============================================================================' AS '';
SELECT 'Migration 031 : motif dans facture + motifs sous LocationSalle' AS '';
SELECT '============================================================================' AS '';

-- ── ÉTAPE 1 : Ajout colonne motif dans facture ────────────────────────────────
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'facture' AND COLUMN_NAME = 'motif');
SET @sql = IF(@col = 0,
    'ALTER TABLE `facture`
     ADD COLUMN `motif` VARCHAR(255) NULL DEFAULT NULL
     COMMENT "Motif de location transmis depuis le loyer"
     AFTER `ristourne`',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT CASE WHEN COUNT(*) = 1 THEN '✅ Colonne motif présente dans facture' ELSE '❌ Manquante' END AS check_motif
FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'facture' AND COLUMN_NAME = 'motif';

-- ── ÉTAPE 2 : Migrer les paramètres motifs de Loyer vers LocationSalle ────────
-- Les lignes groupe_parametre='Loyer', sous_groupe_parametre='Motifs'
-- deviennent groupe_parametre='LocationSalle', sous_groupe_parametre='Motifs'
-- La categorie (= nom de la salle : 'Cabinet', 'Salle') reste inchangée

UPDATE `parametres`
SET `groupe_parametre` = 'LocationSalle'
WHERE `groupe_parametre` = 'Loyer'
  AND `sous_groupe_parametre` = 'Motifs';

SELECT ROW_COUNT() AS motifs_migres_vers_LocationSalle;

-- ── ÉTAPE 3 : Vérification ────────────────────────────────────────────────────
SELECT groupe_parametre, sous_groupe_parametre, categorie, nom_parametre,
       LEFT(valeur_parametre, 60) AS valeur
FROM parametres
WHERE groupe_parametre = 'LocationSalle' AND sous_groupe_parametre = 'Motifs'
ORDER BY categorie, nom_parametre;

-- S'assurer qu'il ne reste plus de motifs sous Loyer
SELECT COUNT(*) AS reste_sous_loyer
FROM parametres
WHERE groupe_parametre = 'Loyer' AND sous_groupe_parametre = 'Motifs';

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SELECT '✅ MIGRATION 031 TERMINÉE' AS '';