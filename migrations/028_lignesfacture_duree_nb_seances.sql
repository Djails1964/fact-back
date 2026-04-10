-- ============================================================================
-- Migration 028 : Ajout colonnes duree et nb_seances dans lignesfacture
--
-- Contexte : Pour les unités avec permet_multiplicateur = 1 (ex: Heure),
--            le formulaire de facture permet de saisir :
--              - duree    : durée hh:mm par séance (ex: "1:15")
--              - nb_seances : nombre brut de séances (= nb dates sélectionnées)
--            La quantite stockée = nb_seances × multiplicateur(duree).
--            Ces deux colonnes permettent de retrouver le détail du calcul
--            lors de la consultation / réédition d'une facture.
--
-- Exemple : nb_seances=4, duree="1:15" → quantite=5.00, prix=30 → total=150 CHF
--
-- Dépend de : migration 027 (permet_multiplicateur dans unites)
-- ============================================================================

SET NAMES utf8mb4;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

SELECT '============================================================================' AS '';
SELECT 'Migration 028: Ajout duree + nb_seances dans lignesfacture' AS '';
SELECT '============================================================================' AS '';

-- ── ÉTAPE 1 : Vérification préalable — table lignesfacture existe ─────────────
SELECT CASE WHEN COUNT(*) = 1
    THEN '✅ Table lignesfacture présente'
    ELSE '❌ Table lignesfacture introuvable — migration annulée'
END AS check_table
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lignesfacture';

-- ── ÉTAPE 2 : Ajout colonne duree ─────────────────────────────────────────────
SET @col_duree = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND   TABLE_NAME   = 'lignesfacture'
    AND   COLUMN_NAME  = 'duree'
);
SET @sql = IF(@col_duree = 0,
    'ALTER TABLE `lignesfacture`
     ADD COLUMN `duree` VARCHAR(5) NULL DEFAULT NULL
     COMMENT "Durée hh:mm saisie pour unités horaires (ex: 1:15) — NULL si non applicable"
     AFTER `description_dates`',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT CASE WHEN COUNT(*) = 1
    THEN '✅ Colonne duree présente'
    ELSE '❌ Colonne duree manquante'
END AS check_duree
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lignesfacture' AND COLUMN_NAME = 'duree';

-- ── ÉTAPE 3 : Ajout colonne nb_seances ────────────────────────────────────────
SET @col_nb = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND   TABLE_NAME   = 'lignesfacture'
    AND   COLUMN_NAME  = 'nb_seances'
);
SET @sql = IF(@col_nb = 0,
    'ALTER TABLE `lignesfacture`
     ADD COLUMN `nb_seances` SMALLINT UNSIGNED NULL DEFAULT NULL
     COMMENT "Nombre brut de séances avant application du multiplicateur de durée — NULL si non applicable"
     AFTER `duree`',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT CASE WHEN COUNT(*) = 1
    THEN '✅ Colonne nb_seances présente'
    ELSE '❌ Colonne nb_seances manquante'
END AS check_nb_seances
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lignesfacture' AND COLUMN_NAME = 'nb_seances';

-- ── ÉTAPE 4 : Vérification de la définition des deux colonnes ─────────────────
SELECT
    ORDINAL_POSITION  AS pos,
    COLUMN_NAME       AS col,
    COLUMN_TYPE       AS type,
    IS_NULLABLE       AS nullable,
    COLUMN_DEFAULT    AS defaut,
    COLUMN_COMMENT    AS commentaire
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND   TABLE_NAME   = 'lignesfacture'
AND   COLUMN_NAME IN ('duree', 'nb_seances')
ORDER BY ORDINAL_POSITION;

-- ── ÉTAPE 5 : Aperçu des lignes existantes avec les nouvelles colonnes ─────────
-- Montre les 10 lignes les plus récentes pour valider l'état post-migration.
SELECT
    lf.id_ligne,
    lf.id_facture,
    lf.no_ordre,
    lf.description_dates,
    lf.duree,
    lf.nb_seances,
    lf.quantite,
    lf.prix_unitaire,
    lf.total_ligne,
    u.code          AS code_unite,
    u.permet_multiplicateur
FROM lignesfacture lf
LEFT JOIN unites u ON u.id = lf.unite_id
ORDER BY lf.id_ligne DESC
LIMIT 10;

-- ── VÉRIFICATION FINALE ───────────────────────────────────────────────────────
SELECT CASE
    WHEN (
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND   TABLE_NAME   = 'lignesfacture'
        AND   COLUMN_NAME IN ('duree', 'nb_seances')
    ) = 2
    THEN '✅ Les deux colonnes duree + nb_seances sont présentes dans lignesfacture'
    ELSE '❌ Une ou deux colonnes manquantes — vérifier les étapes ci-dessus'
END AS check_final;

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SELECT '✅ MIGRATION 028 TERMINÉE' AS '';