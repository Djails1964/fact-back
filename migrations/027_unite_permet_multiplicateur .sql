-- ============================================================================
-- Migration 027 : Ajout colonne permet_multiplicateur dans unites
--
-- Contexte : certaines unites (ex: Heure) autorisent la saisie d'une duree
--            au format hh:mm sur les lignes de facture. Le multiplicateur
--            decimal calcule (ex: 1h15 -> 1.25) est applique a la quantite
--            (nb de seances) pour obtenir la quantite reelle facturee.
--
-- Exemple : 4 seances x 1h15 = quantite 5.00 x prix 80.00 = 400.00 CHF
--
-- Depend de : aucune migration precedente specifique
-- ============================================================================

SET NAMES utf8mb4;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

SELECT '============================================================================' AS '';
SELECT 'Migration 027: Ajout permet_multiplicateur dans unites' AS '';
SELECT '============================================================================' AS '';

-- -- ETAPE 1 : Ajouter la colonne permet_multiplicateur -----------------------
SET @col = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND   TABLE_NAME   = 'unites'
    AND   COLUMN_NAME  = 'permet_multiplicateur'
);
SET @sql = IF(@col = 0,
    'ALTER TABLE `unites`
     ADD COLUMN `permet_multiplicateur` TINYINT(1) NOT NULL DEFAULT 0
     COMMENT "Si 1, saisie duree hh:mm autorisee sur lignes de facture (ex: Heure)"
     AFTER `abreviation`',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT CASE WHEN COUNT(*) = 1
            THEN 'OK  - Colonne permet_multiplicateur presente'
            ELSE 'ERR - Colonne permet_multiplicateur manquante' END AS check_col
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'unites' AND COLUMN_NAME = 'permet_multiplicateur';

-- -- ETAPE 2 : Activer le flag pour les unites de type Heure ------------------
-- Cible les unites dont le code ou le nom suggere une mesure horaire.
-- Ajuster la condition si les codes different dans votre base.
UPDATE `unites`
SET    `permet_multiplicateur` = 1
WHERE  `permet_multiplicateur` = 0
AND    (
           LOWER(`code`) IN ('h', 'heure', 'hr', 'hour')
        OR LOWER(`nom`)  LIKE '%heure%'
        OR LOWER(`nom`)  LIKE '%hour%'
       );

SELECT CASE WHEN ROW_COUNT() > 0
    THEN CONCAT('OK  - ', ROW_COUNT(), ' unite(s) activee(s) automatiquement (permet_multiplicateur = 1)')
    ELSE 'INFO - Aucune unite horaire detectee automatiquement -- activation manuelle si necessaire'
END AS check_update;

-- -- ETAPE 3 : Verification de la definition de la colonne --------------------
SELECT
    ORDINAL_POSITION    AS pos,
    COLUMN_NAME         AS col,
    COLUMN_TYPE         AS type,
    IS_NULLABLE         AS nullable,
    COLUMN_DEFAULT      AS defaut,
    COLUMN_COMMENT      AS commentaire
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND   TABLE_NAME   = 'unites'
AND   COLUMN_NAME  = 'permet_multiplicateur';

-- -- ETAPE 4 : Apercu des unites et de leur flag ------------------------------
SELECT
    id                      AS id_unite,
    code                    AS code_unite,
    nom                     AS nom_unite,
    abreviation             AS abreviation_unite,
    permet_multiplicateur
FROM `unites`
ORDER BY permet_multiplicateur DESC, nom;

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SELECT 'MIGRATION 027 TERMINEE' AS '';