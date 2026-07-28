-- ============================================================================
-- Migration 030 : Ajout colonnes duree et nb_seances dans loyer_detail
--
-- Contexte : Pour les unités tarifaires avec permet_multiplicateur = 1 (ex: Heure),
--            le détail mensuel d'un loyer stocke :
--              - duree      : durée hh:mm par séance (ex: "1:15"), défaut "1:00"
--              - nb_seances : nombre brut de séances (= nb dates sélectionnées)
--            La quantite = nb_seances × multiplicateur(duree).
--            Ces colonnes permettent de retrouver le détail du calcul.
--
-- Dépend de : migrations 027, 028, 029
-- ============================================================================

SET NAMES utf8mb4;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

SELECT '============================================================================' AS '';
SELECT 'Migration 030: Ajout duree + nb_seances dans loyer_detail' AS '';
SELECT '============================================================================' AS '';

-- ── ÉTAPE 1 : Vérification préalable ─────────────────────────────────────────
SELECT CASE WHEN COUNT(*) = 1
    THEN '✅ Table loyer_detail présente'
    ELSE '❌ Table loyer_detail introuvable'
END AS check_table
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loyer_detail';

-- ── ÉTAPE 2 : Ajout colonne duree ─────────────────────────────────────────────
SET @col_duree = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loyer_detail' AND COLUMN_NAME = 'duree');
SET @sql = IF(@col_duree = 0,
    'ALTER TABLE `loyer_detail`
     ADD COLUMN `duree` VARCHAR(5) NULL DEFAULT NULL
     COMMENT "Durée hh:mm pour unités horaires (ex: 1:15) — NULL si non applicable"
     AFTER `dates`',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT CASE WHEN COUNT(*) = 1 THEN '✅ Colonne duree présente' ELSE '❌ Colonne duree manquante' END AS check_duree
FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loyer_detail' AND COLUMN_NAME = 'duree';

-- ── ÉTAPE 3 : Ajout colonne nb_seances ────────────────────────────────────────
SET @col_nb = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loyer_detail' AND COLUMN_NAME = 'nb_seances');
SET @sql = IF(@col_nb = 0,
    'ALTER TABLE `loyer_detail`
     ADD COLUMN `nb_seances` SMALLINT UNSIGNED NULL DEFAULT NULL
     COMMENT "Nombre brut de séances avant application du multiplicateur de durée"
     AFTER `duree`',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT CASE WHEN COUNT(*) = 1 THEN '✅ Colonne nb_seances présente' ELSE '❌ Colonne nb_seances manquante' END AS check_nb
FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loyer_detail' AND COLUMN_NAME = 'nb_seances';

-- ── ÉTAPE 4 : Vérification définition ─────────────────────────────────────────
SELECT ORDINAL_POSITION AS pos, COLUMN_NAME AS col, COLUMN_TYPE AS type, IS_NULLABLE AS nullable, COLUMN_COMMENT AS commentaire
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loyer_detail' AND COLUMN_NAME IN ('duree', 'nb_seances')
ORDER BY ORDINAL_POSITION;

-- ── ÉTAPE 5 : Aperçu ──────────────────────────────────────────────────────────
SELECT ld.id, ld.id_loyer, ld.mois, ld.quantite, ld.duree, ld.nb_seances,
       u.code AS code_unite, u.permet_multiplicateur
FROM loyer_detail ld
LEFT JOIN unites u ON u.id = ld.id_unite
ORDER BY ld.id DESC LIMIT 10;

-- ── VÉRIFICATION FINALE ───────────────────────────────────────────────────────
SELECT CASE WHEN (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loyer_detail'
    AND COLUMN_NAME IN ('duree', 'nb_seances')) = 2
    THEN '✅ Les deux colonnes duree + nb_seances sont présentes dans loyer_detail'
    ELSE '❌ Une ou deux colonnes manquantes'
END AS check_final;

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SELECT '✅ MIGRATION 030 TERMINÉE' AS '';