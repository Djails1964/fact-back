-- ============================================================================
-- Migration 041 : Création table motif_location
--
-- Les motifs de location sont maintenant liés directement au type de contrat
-- de location, remplaçant le stockage dans la table parametre.
--
-- Dépend de : 039_est_forfait_type_contrat.sql
-- ============================================================================

SET NAMES utf8mb4;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

SELECT '============================================================================' AS '';
SELECT 'Migration 041 : Création table motif_location' AS '';
SELECT '============================================================================' AS '';

-- ============================================================================
-- ÉTAPE 1 : Création de la table motif_location
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 1 : Création motif_location ===' AS '';

CREATE TABLE IF NOT EXISTS `motif_location` (
    `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `id_type_contrat`  INT UNSIGNED    NOT NULL COMMENT 'FK → type_contrat_location.id',
    `libelle`          VARCHAR(150)    NOT NULL COMMENT 'Texte du motif',
    `est_defaut`       TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '1 = motif par défaut pour ce type',
    `actif`            TINYINT(1)      NOT NULL DEFAULT 1,
    `ordre`            SMALLINT        NOT NULL DEFAULT 0 COMMENT 'Ordre d\'affichage',
    `created_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_motif_type_contrat` (`id_type_contrat`),
    INDEX `idx_motif_actif`        (`id_type_contrat`, `actif`),
    CONSTRAINT `fk_motif_type_contrat`
        FOREIGN KEY (`id_type_contrat`)
        REFERENCES `type_contrat_location`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Motifs de location par type de contrat';

SELECT '✅ Table motif_location créée' AS info;

-- ============================================================================
-- ÉTAPE 2 : Migration des motifs depuis parametre
--   Logique :
--   - Récupérer les paramètres groupe=LocationSalle, sous_groupe=Motifs
--   - Pour chaque type de contrat, lire sa categorie_motifs
--   - Insérer les motifs de cette catégorie dans motif_location
--   - Le motif_defaut devient est_defaut=1
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 2 : Migration depuis parametre ===' AS '';

-- Insérer les motifs en lisant directement la table parametre
-- Structure : un enregistrement 'motifs' avec valeur séparée par |
--             un enregistrement 'motif_defaut' avec la valeur par défaut

-- Pour chaque type de contrat avec une categorie_motifs configurée,
-- récupérer les motifs de cette catégorie

-- ============================================================================
-- ÉTAPE 2 : Migration des motifs depuis parametre
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 2 : Migration depuis parametre ===' AS '';

-- Approche compatible MySQL 5.7+ : éclater les valeurs séparées par |
-- en utilisant une table de nombres et SUBSTRING_INDEX

INSERT INTO `motif_location` (`id_type_contrat`, `libelle`, `est_defaut`, `ordre`)
SELECT
    tcl.id                                                                          AS id_type_contrat,
    TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(p_motifs.valeur_parametre, '|', nums.n), '|', -1)) AS libelle,
    CASE
        WHEN p_defaut.valeur_parametre IS NOT NULL
         AND TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(p_motifs.valeur_parametre, '|', nums.n), '|', -1))
             = TRIM(p_defaut.valeur_parametre)
        THEN 1 ELSE 0
    END                                                                             AS est_defaut,
    nums.n                                                                          AS ordre
FROM type_contrat_location tcl

JOIN parametres p_motifs
  ON p_motifs.groupe_parametre      = 'LocationSalle'
 AND p_motifs.sous_groupe_parametre = 'Motifs'
 AND p_motifs.nom_parametre         = 'motifs'
 AND p_motifs.categorie   = tcl.categorie_motifs

JOIN (
    SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5
    UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10
    UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15
) nums
  ON nums.n <= 1 + LENGTH(p_motifs.valeur_parametre)
                 - LENGTH(REPLACE(p_motifs.valeur_parametre, '|', ''))

LEFT JOIN parametres p_defaut
  ON p_defaut.groupe_parametre      = 'LocationSalle'
 AND p_defaut.sous_groupe_parametre = 'Motifs'
 AND p_defaut.nom_parametre         = 'motif_defaut'
 AND p_defaut.categorie   = tcl.categorie_motifs

WHERE tcl.categorie_motifs IS NOT NULL
  AND tcl.categorie_motifs != ''
  AND p_motifs.valeur_parametre IS NOT NULL
  AND p_motifs.valeur_parametre != ''
  AND TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(p_motifs.valeur_parametre, '|', nums.n), '|', -1)) != '';

SELECT CONCAT('✅ ', ROW_COUNT(), ' motif(s) migré(s)') AS migration;

-- Vérification
SELECT
    tcl.nom                AS type_contrat,
    ml.libelle,
    ml.est_defaut,
    ml.ordre
FROM motif_location ml
JOIN type_contrat_location tcl ON tcl.id = ml.id_type_contrat
ORDER BY tcl.id, ml.ordre;

-- ============================================================================
-- ÉTAPE 3 : Si LATERAL n'est pas supporté (MySQL < 8.0.14), fallback manuel
-- ============================================================================

-- Note : si l'étape 2 échoue, insérer manuellement :
-- INSERT INTO motif_location (id_type_contrat, libelle, est_defaut, ordre) VALUES
-- (1, 'Location d''un cabinet', 0, 1),
-- (1, 'Location d''un cabinet de consultation', 1, 2),
-- (1, 'Sous-location cabinet', 0, 3),
-- (2, 'Location d''une salle', 1, 1),
-- (2, 'Location d''une salle de thérapie', 0, 2);

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;

SELECT '' AS '';
SELECT '============================================================================' AS '';
SELECT '✅ MIGRATION 041 TERMINÉE' AS '';
SELECT '============================================================================' AS '';