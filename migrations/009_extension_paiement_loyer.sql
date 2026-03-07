-- ============================================================================
-- Migration 009: Extension de la table paiement pour les loyers
-- ============================================================================
-- Date: 2026-02-24
-- Objectif: Permettre la création de paiements liés aux loyers mensuels
-- Contexte: Chaque paiement d'un mois de loyer (loyer_detail) crée une
--           ligne dans la table `paiement` avec id_loyer + id_loyer_detail,
--           exactement comme un paiement de facture mais avec id_facture=NULL.
-- Dépend de: Migration 008 (tables loyer, loyer_detail)
-- ============================================================================

SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

SELECT '============================================================================' AS '';
SELECT 'Migration 009: Extension paiement loyers + indicateur afficher_dates_paiement' AS '';
SELECT '============================================================================' AS '';

-- ============================================================================
-- ÉTAPE 0 : Vérification des prérequis
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 0: Vérification des prérequis ===' AS '';

-- Vérifier que la table loyer existe (dépendance migration 008)
SELECT
    CASE
        WHEN COUNT(*) = 2
        THEN '✅ Tables loyer et loyer_detail présentes (migration 008 OK)'
        ELSE '❌ ERREUR: Tables loyer ou loyer_detail manquantes — lancez d\'abord la migration 008'
    END AS prerequis
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME IN ('loyer', 'loyer_detail');

-- Vérifier que la table paiement existe
SELECT
    CASE
        WHEN COUNT(*) = 1
        THEN '✅ Table paiement présente'
        ELSE '❌ ERREUR: Table paiement introuvable'
    END AS prerequis_paiement
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'paiement';

-- ============================================================================
-- ÉTAPE 1: Ajout de la colonne id_loyer dans paiement
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 1: Ajout colonne id_loyer ===' AS '';

SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'paiement'
    AND COLUMN_NAME  = 'id_loyer'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `paiement`
     ADD COLUMN `id_loyer` INT NULL DEFAULT NULL
         COMMENT ''Loyer concerné — NULL si paiement de facture''
     AFTER `id_facture`',
    'SELECT "Colonne id_loyer existe déjà dans paiement" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT
    CASE
        WHEN COUNT(*) = 1 THEN '✅ Colonne id_loyer présente'
        ELSE '❌ Échec ajout colonne id_loyer'
    END AS check_col_loyer
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'paiement' AND COLUMN_NAME = 'id_loyer';

-- ============================================================================
-- ÉTAPE 1bis: Ajout de la colonne afficher_dates_paiement dans loyer
-- ============================================================================
-- Indique si les dates de paiement doivent figurer sur la confirmation PDF.
-- Défaut : 0 (NON). Valeur 1 = OUI.
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 1bis: Ajout colonne afficher_dates_paiement dans loyer ===' AS '';

SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'loyer'
    AND COLUMN_NAME  = 'afficher_dates_paiement'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `loyer`
     ADD COLUMN `afficher_dates_paiement` TINYINT(1) NOT NULL DEFAULT 0
         COMMENT ''Afficher les dates de paiement sur la confirmation PDF (0=non, 1=oui)''
     AFTER `motif`',
    'SELECT "Colonne afficher_dates_paiement existe déjà dans loyer" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT
    CASE
        WHEN COUNT(*) = 1 THEN '✅ Colonne afficher_dates_paiement présente dans loyer'
        ELSE '❌ Échec ajout colonne afficher_dates_paiement'
    END AS check_col_afficher_dates
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'loyer' AND COLUMN_NAME = 'afficher_dates_paiement';

-- ============================================================================
-- ÉTAPE 2: Ajout de la colonne id_loyer_detail dans paiement
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 2: Ajout colonne id_loyer_detail ===' AS '';

SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'paiement'
    AND COLUMN_NAME  = 'id_loyer_detail'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `paiement`
     ADD COLUMN `id_loyer_detail` INT NULL DEFAULT NULL
         COMMENT ''Mois de loyer concerné (loyer_detail.id) — NULL si paiement de facture''
     AFTER `id_loyer`',
    'SELECT "Colonne id_loyer_detail existe déjà dans paiement" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT
    CASE
        WHEN COUNT(*) = 1 THEN '✅ Colonne id_loyer_detail présente'
        ELSE '❌ Échec ajout colonne id_loyer_detail'
    END AS check_col_loyer_detail
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'paiement' AND COLUMN_NAME = 'id_loyer_detail';

-- ============================================================================
-- ÉTAPE 3: Index sur id_loyer
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 3: Création index idx_paiement_loyer ===' AS '';

SET @idx_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME  = 'paiement'
    AND INDEX_NAME  = 'idx_paiement_loyer'
);

SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX `idx_paiement_loyer` ON `paiement`(`id_loyer`)',
    'SELECT "Index idx_paiement_loyer existe déjà" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT
    CASE
        WHEN COUNT(*) = 1 THEN '✅ Index idx_paiement_loyer présent'
        ELSE '❌ Échec création index idx_paiement_loyer'
    END AS check_idx_loyer
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'paiement' AND INDEX_NAME = 'idx_paiement_loyer';

-- ============================================================================
-- ÉTAPE 4: Index sur id_loyer_detail
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 4: Création index idx_paiement_loyer_detail ===' AS '';

SET @idx_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME  = 'paiement'
    AND INDEX_NAME  = 'idx_paiement_loyer_detail'
);

SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX `idx_paiement_loyer_detail` ON `paiement`(`id_loyer_detail`)',
    'SELECT "Index idx_paiement_loyer_detail existe déjà" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT
    CASE
        WHEN COUNT(*) = 1 THEN '✅ Index idx_paiement_loyer_detail présent'
        ELSE '❌ Échec création index idx_paiement_loyer_detail'
    END AS check_idx_detail
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'paiement' AND INDEX_NAME = 'idx_paiement_loyer_detail';

-- ============================================================================
-- ÉTAPE 5: Clé étrangère paiement → loyer
-- ============================================================================
-- Politique DELETE RESTRICT : un loyer ne peut pas être supprimé tant qu'il
-- a des paiements. Même politique que paiement → facture.
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 5: FK paiement → loyer ===' AS '';

SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA           = DATABASE()
    AND TABLE_NAME               = 'paiement'
    AND CONSTRAINT_NAME          = 'fk_paiement_loyer'
    AND REFERENCED_TABLE_NAME    = 'loyer'
);

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE `paiement`
     ADD CONSTRAINT `fk_paiement_loyer`
         FOREIGN KEY (`id_loyer`)
         REFERENCES `loyer`(`id_loyer`)
         ON DELETE RESTRICT
         ON UPDATE CASCADE',
    'SELECT "FK fk_paiement_loyer existe déjà" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT
    CASE
        WHEN COUNT(*) = 1 THEN '✅ FK fk_paiement_loyer (RESTRICT/CASCADE) présente'
        ELSE '❌ Échec création FK fk_paiement_loyer'
    END AS check_fk_loyer
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA        = DATABASE()
AND TABLE_NAME            = 'paiement'
AND CONSTRAINT_NAME       = 'fk_paiement_loyer'
AND REFERENCED_TABLE_NAME = 'loyer';

-- ============================================================================
-- ÉTAPE 6: Clé étrangère paiement → loyer_detail
-- ============================================================================
-- Note: La PK de loyer_detail est `id` (pas id_loyer_detail).
-- Politique DELETE SET NULL : si un détail mensuel est supprimé
-- (ex: recréation lors d'une modification de loyer), le paiement
-- reste dans l'historique avec id_loyer_detail=NULL.
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 6: FK paiement → loyer_detail ===' AS '';

SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA           = DATABASE()
    AND TABLE_NAME               = 'paiement'
    AND CONSTRAINT_NAME          = 'fk_paiement_loyer_detail'
    AND REFERENCED_TABLE_NAME    = 'loyer_detail'
);

-- La PK de loyer_detail est `id` (cf. migration 008 : id INT AUTO_INCREMENT PRIMARY KEY)
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE `paiement`
     ADD CONSTRAINT `fk_paiement_loyer_detail`
         FOREIGN KEY (`id_loyer_detail`)
         REFERENCES `loyer_detail`(`id`)
         ON DELETE SET NULL
         ON UPDATE CASCADE',
    'SELECT "FK fk_paiement_loyer_detail existe déjà" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT
    CASE
        WHEN COUNT(*) = 1 THEN '✅ FK fk_paiement_loyer_detail (SET NULL/CASCADE) présente'
        ELSE '❌ Échec création FK fk_paiement_loyer_detail'
    END AS check_fk_detail
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA        = DATABASE()
AND TABLE_NAME            = 'paiement'
AND CONSTRAINT_NAME       = 'fk_paiement_loyer_detail'
AND REFERENCED_TABLE_NAME = 'loyer_detail';

-- ============================================================================
-- ÉTAPE 7: Cohérence facture XOR loyer — trigger BEFORE INSERT/UPDATE
-- ============================================================================
-- migrate.php gère DELIMITER $$ (lignes 152-156) : il supprime les balises
-- DELIMITER et remplace $$ par ; avant le découpage — les triggers sont donc
-- parfaitement supportés.
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 7: Trigger XOR facture/loyer ===' AS '';

DROP TRIGGER IF EXISTS `trg_paiement_xor_insert`;
DELIMITER $$
CREATE TRIGGER `trg_paiement_xor_insert`
BEFORE INSERT ON `paiement`
FOR EACH ROW
BEGIN
    IF NEW.id_facture IS NOT NULL AND NEW.id_loyer IS NOT NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Un paiement ne peut pas lier à la fois une facture et un loyer';
    END IF;
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `trg_paiement_xor_update`;
DELIMITER $$
CREATE TRIGGER `trg_paiement_xor_update`
BEFORE UPDATE ON `paiement`
FOR EACH ROW
BEGIN
    IF NEW.id_facture IS NOT NULL AND NEW.id_loyer IS NOT NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Un paiement ne peut pas lier à la fois une facture et un loyer';
    END IF;
END$$
DELIMITER ;

SELECT
    CASE
        WHEN COUNT(*) = 2 THEN '✅ Triggers XOR trg_paiement_xor_insert + trg_paiement_xor_update présents'
        ELSE '❌ Échec création triggers XOR'
    END AS check_triggers_xor
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE()
AND TRIGGER_NAME IN ('trg_paiement_xor_insert', 'trg_paiement_xor_update');

-- ============================================================================
-- ÉTAPE 7bis: Triggers AFTER INSERT / UPDATE / DELETE — propagation vers loyer
-- ============================================================================
-- Extension des triggers existants (qui gèrent déjà les factures) pour
-- propager les paiements vers loyer_detail.est_paye et loyer.etat_paiement.
-- Stratégie identique à celle des factures : recalcul depuis la table paiement
-- (source unique de vérité), pas de stockage redondant.
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 7bis: Triggers AFTER INSERT/UPDATE/DELETE (loyer) ===' AS '';

-- ── AFTER INSERT ──────────────────────────────────────────────────────────────
DROP TRIGGER IF EXISTS `tr_paiement_after_insert`;
DELIMITER $$
CREATE TRIGGER `tr_paiement_after_insert`
AFTER INSERT ON `paiement`
FOR EACH ROW
BEGIN
    -- Facture : recalcul montants + état
    IF NEW.id_facture IS NOT NULL AND NEW.statut = 'confirme' THEN
        UPDATE `facture`
        SET
            `montant_paye_total`    = (SELECT COALESCE(SUM(montant_paye), 0) FROM `paiement` WHERE `id_facture` = NEW.id_facture AND `statut` = 'confirme'),
            `montant_restant`       = `montant_total` - `montant_paye_total`,
            `etat`                  = CASE
                                          WHEN (`montant_total` - `montant_paye_total`) <= 0 THEN 'Payée'
                                          WHEN `montant_paye_total` > 0                       THEN 'Partiellement payée'
                                          ELSE 'Envoyée'
                                      END,
            `nb_paiements`          = (SELECT COUNT(*) FROM `paiement` WHERE `id_facture` = NEW.id_facture AND `statut` = 'confirme'),
            `date_dernier_paiement` = NEW.date_paiement
        WHERE `id_facture` = NEW.id_facture;
    END IF;
    -- Loyer détail : marquer mois soldé si total paiements confirmes >= montant dû
    IF NEW.id_loyer_detail IS NOT NULL AND NEW.statut = 'confirme' THEN
        UPDATE `loyer_detail`
        SET
            `est_paye`      = CASE
                                  WHEN (SELECT COALESCE(SUM(p.montant_paye), 0) FROM `paiement` p WHERE p.id_loyer_detail = NEW.id_loyer_detail AND p.statut = 'confirme') >= `montant`
                                  THEN 1 ELSE 0
                              END,
            `date_paiement` = NEW.date_paiement
        WHERE `id` = NEW.id_loyer_detail;
    END IF;
    -- Loyer global : recalcul etat_paiement
    IF NEW.id_loyer IS NOT NULL AND NEW.statut = 'confirme' THEN
        UPDATE `loyer`
        SET `etat_paiement` = (
            SELECT CASE
                       WHEN SUM(ld.est_paye) >= COUNT(*) THEN 'paye'
                       WHEN SUM(ld.est_paye) > 0         THEN 'partiellement_paye'
                       ELSE                                   'non_paye'
                   END
            FROM `loyer_detail` ld WHERE ld.id_loyer = NEW.id_loyer
        )
        WHERE `id_loyer` = NEW.id_loyer;
    END IF;
END$$
DELIMITER ;

-- ── AFTER UPDATE ─────────────────────────────────────────────────────────────
DROP TRIGGER IF EXISTS `tr_paiement_after_update`;
DELIMITER $$
CREATE TRIGGER `tr_paiement_after_update`
AFTER UPDATE ON `paiement`
FOR EACH ROW
BEGIN
    -- Facture ancienne
    IF OLD.id_facture IS NOT NULL THEN
        UPDATE `facture`
        SET
            `montant_paye_total` = (SELECT COALESCE(SUM(montant_paye), 0) FROM `paiement` WHERE `id_facture` = OLD.id_facture AND `statut` = 'confirme'),
            `montant_restant`    = `montant_total` - `montant_paye_total`,
            `etat`               = CASE WHEN (`montant_total` - `montant_paye_total`) <= 0 THEN 'Payée' WHEN `montant_paye_total` > 0 THEN 'Partiellement payée' ELSE 'Envoyée' END,
            `nb_paiements`       = (SELECT COUNT(*) FROM `paiement` WHERE `id_facture` = OLD.id_facture AND `statut` = 'confirme')
        WHERE `id_facture` = OLD.id_facture;
    END IF;
    -- Facture nouvelle (si changement)
    IF NEW.id_facture IS NOT NULL AND (OLD.id_facture IS NULL OR NEW.id_facture <> OLD.id_facture) THEN
        UPDATE `facture`
        SET
            `montant_paye_total` = (SELECT COALESCE(SUM(montant_paye), 0) FROM `paiement` WHERE `id_facture` = NEW.id_facture AND `statut` = 'confirme'),
            `montant_restant`    = `montant_total` - `montant_paye_total`,
            `etat`               = CASE WHEN (`montant_total` - `montant_paye_total`) <= 0 THEN 'Payée' WHEN `montant_paye_total` > 0 THEN 'Partiellement payée' ELSE 'Envoyée' END,
            `nb_paiements`       = (SELECT COUNT(*) FROM `paiement` WHERE `id_facture` = NEW.id_facture AND `statut` = 'confirme')
        WHERE `id_facture` = NEW.id_facture;
    END IF;
    -- Loyer détail ancien (couvre annulation : statut confirme → annule)
    IF OLD.id_loyer_detail IS NOT NULL THEN
        UPDATE `loyer_detail`
        SET
            `est_paye`      = CASE
                                  WHEN (SELECT COALESCE(SUM(p.montant_paye), 0) FROM `paiement` p WHERE p.id_loyer_detail = OLD.id_loyer_detail AND p.statut = 'confirme') >= `montant`
                                  THEN 1 ELSE 0
                              END,
            `date_paiement` = (SELECT MAX(p.date_paiement) FROM `paiement` p WHERE p.id_loyer_detail = OLD.id_loyer_detail AND p.statut = 'confirme')
        WHERE `id` = OLD.id_loyer_detail;
    END IF;
    -- Loyer détail nouveau (si changement de détail)
    IF NEW.id_loyer_detail IS NOT NULL AND (OLD.id_loyer_detail IS NULL OR NEW.id_loyer_detail <> OLD.id_loyer_detail) THEN
        UPDATE `loyer_detail`
        SET
            `est_paye`      = CASE
                                  WHEN (SELECT COALESCE(SUM(p.montant_paye), 0) FROM `paiement` p WHERE p.id_loyer_detail = NEW.id_loyer_detail AND p.statut = 'confirme') >= `montant`
                                  THEN 1 ELSE 0
                              END,
            `date_paiement` = NEW.date_paiement
        WHERE `id` = NEW.id_loyer_detail;
    END IF;
    -- Loyer global ancien
    IF OLD.id_loyer IS NOT NULL THEN
        UPDATE `loyer`
        SET `etat_paiement` = (
            SELECT CASE WHEN SUM(ld.est_paye) >= COUNT(*) THEN 'paye' WHEN SUM(ld.est_paye) > 0 THEN 'partiellement_paye' ELSE 'non_paye' END
            FROM `loyer_detail` ld WHERE ld.id_loyer = OLD.id_loyer
        )
        WHERE `id_loyer` = OLD.id_loyer;
    END IF;
    -- Loyer global nouveau (si changement de loyer)
    IF NEW.id_loyer IS NOT NULL AND (OLD.id_loyer IS NULL OR NEW.id_loyer <> OLD.id_loyer) THEN
        UPDATE `loyer`
        SET `etat_paiement` = (
            SELECT CASE WHEN SUM(ld.est_paye) >= COUNT(*) THEN 'paye' WHEN SUM(ld.est_paye) > 0 THEN 'partiellement_paye' ELSE 'non_paye' END
            FROM `loyer_detail` ld WHERE ld.id_loyer = NEW.id_loyer
        )
        WHERE `id_loyer` = NEW.id_loyer;
    END IF;
END$$
DELIMITER ;

-- ── AFTER DELETE ─────────────────────────────────────────────────────────────
DROP TRIGGER IF EXISTS `tr_paiement_after_delete`;
DELIMITER $$
CREATE TRIGGER `tr_paiement_after_delete`
AFTER DELETE ON `paiement`
FOR EACH ROW
BEGIN
    -- Facture
    IF OLD.id_facture IS NOT NULL THEN
        UPDATE `facture`
        SET
            `montant_paye_total` = (SELECT COALESCE(SUM(montant_paye), 0) FROM `paiement` WHERE `id_facture` = OLD.id_facture AND `statut` = 'confirme'),
            `montant_restant`    = `montant_total` - `montant_paye_total`,
            `etat`               = CASE WHEN (`montant_total` - `montant_paye_total`) <= 0 THEN 'Payée' WHEN `montant_paye_total` > 0 THEN 'Partiellement payée' ELSE 'Envoyée' END,
            `nb_paiements`       = (SELECT COUNT(*) FROM `paiement` WHERE `id_facture` = OLD.id_facture AND `statut` = 'confirme')
        WHERE `id_facture` = OLD.id_facture;
    END IF;
    -- Loyer détail
    IF OLD.id_loyer_detail IS NOT NULL THEN
        UPDATE `loyer_detail`
        SET
            `est_paye`      = CASE
                                  WHEN (SELECT COALESCE(SUM(p.montant_paye), 0) FROM `paiement` p WHERE p.id_loyer_detail = OLD.id_loyer_detail AND p.statut = 'confirme') >= `montant`
                                  THEN 1 ELSE 0
                              END,
            `date_paiement` = (SELECT MAX(p.date_paiement) FROM `paiement` p WHERE p.id_loyer_detail = OLD.id_loyer_detail AND p.statut = 'confirme')
        WHERE `id` = OLD.id_loyer_detail;
    END IF;
    -- Loyer global
    IF OLD.id_loyer IS NOT NULL THEN
        UPDATE `loyer`
        SET `etat_paiement` = (
            SELECT CASE WHEN SUM(ld.est_paye) >= COUNT(*) THEN 'paye' WHEN SUM(ld.est_paye) > 0 THEN 'partiellement_paye' ELSE 'non_paye' END
            FROM `loyer_detail` ld WHERE ld.id_loyer = OLD.id_loyer
        )
        WHERE `id_loyer` = OLD.id_loyer;
    END IF;
END$$
DELIMITER ;

SELECT
    CASE
        WHEN COUNT(*) = 3 THEN '✅ Triggers tr_paiement_after_insert/update/delete présents'
        ELSE CONCAT('❌ Seulement ', COUNT(*), '/3 triggers after présents')
    END AS check_triggers_after
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE()
AND TRIGGER_NAME IN ('tr_paiement_after_insert', 'tr_paiement_after_update', 'tr_paiement_after_delete');

-- ============================================================================
-- ÉTAPE 8: Mise à jour de la vue v_loyers_complets
-- ============================================================================
-- Ajoute montant_paye_total calculé depuis la table paiement (source unique
-- de vérité), en plus du calcul depuis loyer_detail.est_paye.
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 8: Mise à jour vue v_loyers_complets ===' AS '';

DROP VIEW IF EXISTS v_loyers_complets;

CREATE VIEW v_loyers_complets AS
SELECT
    -- Identifiants
    l.id_loyer,
    l.numero_loyer,
    l.numero_sequence,
    l.id_client,

    -- Client
    c.prenom                            AS prenom_client,
    c.nom                               AS nom_client,
    CONCAT(c.prenom, ' ', c.nom)        AS nom_complet_client,
    c.email                             AS email_client,
    c.telephone                         AS telephone_client,
    c.rue                               AS rue_client,
    c.numero                            AS numero_client,
    c.code_postal                       AS code_postal_client,
    c.localite                          AS localite_client,

    -- Période
    l.date_creation_loyer,
    l.periode_debut,
    l.periode_fin,
    l.duree_mois,

    -- Description
    l.motif,
    l.afficher_dates_paiement,
    l.description,

    -- Montants de base
    l.montant_total,
    l.montant_mensuel_moyen,

    -- États
    l.statut,
    l.etat_paiement,

    -- ── Calculs depuis loyer_detail (état des flags) ──────────────────────
    COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0)
        AS montant_paye,
    l.montant_total
        - COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0)
        AS montant_restant,
    ROUND(
        COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0)
        / NULLIF(l.montant_total, 0) * 100,
        2
    ) AS pourcentage_paye,

    -- Comptage des mois
    COUNT(CASE WHEN ld.est_paye THEN 1 END) AS mois_payes,
    COUNT(ld.id)                             AS total_mois,

    -- ── Montant réellement encaissé (depuis table paiement, statut='confirme') ──
    COALESCE(pmt.montant_paye_total, 0)      AS montant_paye_total,
    l.montant_total - COALESCE(pmt.montant_paye_total, 0)
                                             AS solde_restant,

    -- Métadonnées
    l.date_creation,
    l.date_modification,
    l.createur_id,
    l.modificateur_id

FROM loyer l
INNER JOIN client c ON l.id_client = c.id
LEFT JOIN loyer_detail ld ON l.id_loyer = ld.id_loyer
LEFT JOIN (
    -- Somme des paiements confirmés liés à ce loyer
    SELECT id_loyer, SUM(montant_paye) AS montant_paye_total
    FROM paiement
    WHERE id_loyer IS NOT NULL
      AND statut = 'confirme'
    GROUP BY id_loyer
) pmt ON pmt.id_loyer = l.id_loyer
GROUP BY
    l.id_loyer, l.numero_loyer, l.numero_sequence, l.id_client,
    c.prenom, c.nom, c.email, c.telephone,
    c.rue, c.numero, c.code_postal, c.localite,
    l.date_creation_loyer, l.periode_debut, l.periode_fin, l.duree_mois,
    l.motif, l.afficher_dates_paiement, l.description, l.montant_total, l.montant_mensuel_moyen,
    l.statut, l.etat_paiement,
    l.date_creation, l.date_modification,
    l.createur_id, l.modificateur_id,
    pmt.montant_paye_total;

SELECT
    CASE
        WHEN COUNT(*) = 1 THEN '✅ Vue v_loyers_complets mise à jour (montant_paye_total depuis paiement)'
        ELSE '❌ Échec mise à jour vue v_loyers_complets'
    END AS check_vue
FROM information_schema.VIEWS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'v_loyers_complets';

-- ============================================================================
-- ÉTAPE 9: Vérifications finales
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 9: Vérifications finales ===' AS '';

-- Structure de la table paiement (nouvelles colonnes)
SELECT
    COLUMN_NAME         AS colonne,
    COLUMN_TYPE         AS type,
    IS_NULLABLE         AS nullable,
    COLUMN_DEFAULT      AS defaut,
    COLUMN_COMMENT      AS commentaire
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME     = 'paiement'
AND COLUMN_NAME    IN ('id_facture', 'id_loyer', 'id_loyer_detail')
UNION ALL
SELECT
    COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME     = 'loyer'
AND COLUMN_NAME    = 'afficher_dates_paiement';

-- Toutes les FK de la table paiement
SELECT
    kcu.CONSTRAINT_NAME         AS contrainte,
    kcu.COLUMN_NAME             AS colonne,
    kcu.REFERENCED_TABLE_NAME   AS table_cible,
    kcu.REFERENCED_COLUMN_NAME  AS colonne_cible,
    rc.DELETE_RULE              AS sur_suppression,
    rc.UPDATE_RULE              AS sur_mise_a_jour
FROM information_schema.KEY_COLUMN_USAGE kcu
JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
     ON rc.CONSTRAINT_NAME   = kcu.CONSTRAINT_NAME
    AND rc.CONSTRAINT_SCHEMA = kcu.TABLE_SCHEMA
WHERE kcu.TABLE_SCHEMA  = DATABASE()
AND kcu.TABLE_NAME      = 'paiement'
AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY kcu.CONSTRAINT_NAME;

-- Tous les index de la table paiement (nouveaux + existants)
SELECT
    INDEX_NAME      AS index_name,
    COLUMN_NAME     AS colonne,
    NON_UNIQUE      AS non_unique,
    INDEX_TYPE      AS type
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME     = 'paiement'
AND INDEX_NAME     IN ('idx_paiement_loyer', 'idx_paiement_loyer_detail')
ORDER BY INDEX_NAME;

-- ============================================================================
-- ÉTAPE 10: Statistiques
-- ============================================================================

SELECT '' AS '';
SELECT '=== STATISTIQUES ===' AS '';

SELECT
    'paiement (total)'          AS entite,
    COUNT(*)                    AS nombre
FROM paiement
UNION ALL
SELECT
    'paiement liés à facture',
    COUNT(*)
FROM paiement WHERE id_facture IS NOT NULL
UNION ALL
SELECT
    'paiement liés à loyer',
    COUNT(*)
FROM paiement WHERE id_loyer IS NOT NULL
UNION ALL
SELECT
    'paiement libres (sans lien)',
    COUNT(*)
FROM paiement WHERE id_facture IS NULL AND id_loyer IS NULL;

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;

-- ============================================================================
-- NOTES IMPORTANTES
-- ============================================================================

SELECT '' AS '';
SELECT '=== NOTES IMPORTANTES ===' AS '';
SELECT '📋 Nouvelles colonnes: id_loyer (FK RESTRICT), id_loyer_detail (FK SET NULL)' AS info;
SELECT '🔗 id_loyer_detail référence loyer_detail.id (PK nommée id dans migration 008)' AS info;
SELECT '⚖️  Cohérence facture XOR loyer: trigger BEFORE INSERT/UPDATE (trg_paiement_xor_*)' AS info;
SELECT '🔄 Propagation loyer: triggers AFTER INSERT/UPDATE/DELETE (tr_paiement_after_*)' AS info;
SELECT '🔒 Suppression loyer bloquée si paiements existent (RESTRICT)' AS info;
SELECT '🗑️  Suppression loyer_detail → id_loyer_detail mis à NULL (SET NULL)' AS info;
SELECT '📊 Vue v_loyers_complets mise à jour: montant_paye_total depuis table paiement' AS info;
SELECT '📋 Colonne loyer.afficher_dates_paiement (TINYINT 0/1, défaut 0) : affichage dates paiement sur PDF' AS info;
SELECT '✅ Paiements partiels supportés: plusieurs paiements par loyer_detail.id' AS info;

SELECT '' AS '';
SELECT '============================================================================' AS '';
SELECT '✅ MIGRATION 009 TERMINÉE AVEC SUCCÈS' AS '';
SELECT '============================================================================' AS '';