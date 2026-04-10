-- ============================================================================
-- Migration 021 : Création de la table salle
-- ============================================================================
-- Date: 2026-03-19
-- Contexte:
--   Les salles étaient stockées de façon non normalisée dans la table
--   `parametres` (groupe=LocationSalle, sous_groupe=Salles, categorie=<nom>)
--   avec plusieurs lignes verticales par salle (label, nom_service,
--   type_client_requis, type_document).
--
--   Ce modèle interdisait toute FK vers `services` et rendait impossible
--   la correspondance entre loyer.id_service et la salle associée.
--
-- Solution : table `salle` normalisée avec FK réelle vers `services`.
--
-- Changements :
--   1. Création de la table `salle`
--   2. Migration des données depuis `parametres`
--   3. Ajout colonne `id_salle` sur `location_salle_detail` (FK → salle)
--   4. Nettoyage de `parametres` (suppression des lignes LocationSalle/Salles)
--
-- Dépend de : migration 010 (location_salle_detail), services (table tarifs)
-- ============================================================================

SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

SELECT '============================================================================' AS '';
SELECT 'Migration 021 : Création table salle + migration depuis parametres' AS '';
SELECT '============================================================================' AS '';

-- ============================================================================
-- ÉTAPE 1 : Création de la table salle
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 1: Création table salle ===' AS '';

CREATE TABLE IF NOT EXISTS `salle` (

    `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,

    `nom`               VARCHAR(100)    NOT NULL
                        COMMENT 'Nom affiché de la salle (ex: Cabinet, Salle)',

    `id_service`        INT             NULL
                        COMMENT 'Service tarifaire associé — référence services(id)',

    `type_client_requis` VARCHAR(50)    NULL
                        COMMENT 'Restriction client (ex: therapeute). NULL = tous.',

    `type_document`     ENUM('facture','confirmation')
                        NOT NULL DEFAULT 'facture'
                        COMMENT 'Document généré lors d''une location de cette salle',

    `actif`             TINYINT(1)      NOT NULL DEFAULT 1
                        COMMENT '1 = salle active, 0 = désactivée',

    `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY  `uk_salle_nom` (`nom`),
    INDEX       `idx_salle_service` (`id_service`),

    CONSTRAINT `fk_salle_service`
        FOREIGN KEY (`id_service`)
        REFERENCES `services`(`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Salles de location — entité propre remplaçant parametres LocationSalle/Salles';

SELECT CASE WHEN COUNT(*) = 1
    THEN '✅ Table salle créée'
    ELSE '❌ Échec création table salle'
END AS check_creation
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salle';

-- ============================================================================
-- ÉTAPE 2 : Migration des données depuis parametres
-- On lit les colonnes nom, nom_service (→ jointure services), type_client_requis,
-- type_document regroupées par categorie.
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 2: État des salles dans parametres ===' AS '';

SELECT
    categorie                                                           AS salle,
    MAX(CASE WHEN nom_parametre = 'label'            THEN valeur_parametre END) AS label,
    MAX(CASE WHEN nom_parametre = 'nom_service'      THEN valeur_parametre END) AS nom_service,
    MAX(CASE WHEN nom_parametre = 'type_client_requis' THEN valeur_parametre END) AS type_client_requis,
    MAX(CASE WHEN nom_parametre = 'type_document'    THEN valeur_parametre END) AS type_document
FROM parametres
WHERE groupe_parametre      = 'LocationSalle'
  AND sous_groupe_parametre = 'Salles'
GROUP BY categorie
ORDER BY categorie;

SELECT '' AS '';
SELECT '=== ÉTAPE 2b: Migration dans salle ===' AS '';

INSERT INTO salle (nom, id_service, type_client_requis, type_document)
SELECT
    COALESCE(
        MAX(CASE WHEN p.nom_parametre = 'label'       THEN p.valeur_parametre END),
        p.categorie
    )                                                                   AS nom,
    s.id                                                                AS id_service,
    MAX(CASE WHEN p.nom_parametre = 'type_client_requis' THEN
        NULLIF(p.valeur_parametre, '')
    END)                                                                AS type_client_requis,
    COALESCE(
        MAX(CASE WHEN p.nom_parametre = 'type_document' THEN
            CASE WHEN p.valeur_parametre IN ('facture','confirmation')
                THEN p.valeur_parametre
            END
        END),
        'facture'
    )                                                                   AS type_document
FROM parametres p
LEFT JOIN services s
    ON s.nom_service = (
        SELECT valeur_parametre
        FROM parametres p2
        WHERE p2.groupe_parametre      = 'LocationSalle'
          AND p2.sous_groupe_parametre = 'Salles'
          AND p2.categorie             = p.categorie
          AND p2.nom_parametre         = 'nom_service'
        LIMIT 1
    )
WHERE p.groupe_parametre      = 'LocationSalle'
  AND p.sous_groupe_parametre = 'Salles'
GROUP BY p.categorie, s.id
ON DUPLICATE KEY UPDATE
    id_service         = VALUES(id_service),
    type_client_requis = VALUES(type_client_requis),
    type_document      = VALUES(type_document);

SELECT ROW_COUNT() AS lignes_migrees;

SELECT '' AS '';
SELECT '=== ÉTAPE 2c: Résultat migration ===' AS '';

SELECT
    id,
    nom,
    id_service,
    type_client_requis,
    type_document,
    actif
FROM salle
ORDER BY id;

-- ============================================================================
-- ÉTAPE 3 : Ajout colonne id_salle sur location_salle_detail
-- Permet de lier chaque ligne de location directement à la salle (FK propre).
-- La colonne salle (VARCHAR) est conservée pour rétrocompatibilité affichage.
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 3: Ajout id_salle sur location_salle_detail ===' AS '';

SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'location_salle_detail'
      AND COLUMN_NAME  = 'id_salle'
);

SELECT CASE @col_exists
    WHEN 0 THEN '⚠️  Colonne id_salle absente — ajout en cours…'
    ELSE        '✅ Colonne id_salle déjà présente'
END AS check_avant;

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE location_salle_detail
         ADD COLUMN id_salle INT UNSIGNED NULL
         COMMENT "FK vers salle(id) — null si salle non encore migrée"
         AFTER salle',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Remplissage rétroactif : faire correspondre le champ VARCHAR salle → salle.id
UPDATE location_salle_detail lsd
JOIN   salle s ON s.nom = lsd.salle
SET    lsd.id_salle = s.id
WHERE  lsd.id_salle IS NULL;

SELECT CONCAT('✅ ', ROW_COUNT(), ' ligne(s) location_salle_detail mises à jour avec id_salle') AS update_result;

-- Ajout FK (idempotent)
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA    = DATABASE()
      AND TABLE_NAME      = 'location_salle_detail'
      AND CONSTRAINT_NAME = 'fk_detail_salle'
);
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE location_salle_detail
         ADD CONSTRAINT fk_detail_salle
         FOREIGN KEY (id_salle) REFERENCES salle(id)
         ON DELETE SET NULL ON UPDATE CASCADE',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT CASE
    WHEN COUNT(*) = 1 THEN '✅ FK fk_detail_salle présente'
    ELSE                   '❌ FK fk_detail_salle absente'
END AS check_fk
FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA    = DATABASE()
  AND TABLE_NAME      = 'location_salle_detail'
  AND CONSTRAINT_NAME = 'fk_detail_salle';

-- ============================================================================
-- ÉTAPE 4 : Nettoyage de parametres
-- Suppression des lignes LocationSalle/Salles désormais gérées dans salle.
-- Le groupe LocationSalle est retiré de GestionParametres.
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 4: Nettoyage parametres ===' AS '';

SELECT COUNT(*) AS lignes_a_supprimer
FROM parametres
WHERE groupe_parametre      = 'LocationSalle'
  AND sous_groupe_parametre = 'Salles';

DELETE FROM parametres
WHERE groupe_parametre      = 'LocationSalle'
  AND sous_groupe_parametre = 'Salles';

SELECT CONCAT('✅ ', ROW_COUNT(), ' ligne(s) supprimées de parametres') AS nettoyage_result;

-- Vérifier s'il reste d'autres lignes LocationSalle (ex: Général)
SELECT
    sous_groupe_parametre,
    COUNT(*) AS nb_restants
FROM parametres
WHERE groupe_parametre = 'LocationSalle'
GROUP BY sous_groupe_parametre;

-- ============================================================================
-- ÉTAPE 5 : Vérification finale
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 5: Vérification finale ===' AS '';

SELECT
    s.id,
    s.nom,
    srv.nom_service,
    s.type_client_requis,
    s.type_document,
    s.actif,
    COUNT(lsd.id) AS nb_locations
FROM salle s
LEFT JOIN services        srv ON srv.id  = s.id_service
LEFT JOIN location_salle_detail lsd ON lsd.id_salle = s.id
GROUP BY s.id, s.nom, srv.nom_service, s.type_client_requis, s.type_document, s.actif
ORDER BY s.id;

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;

SELECT '' AS '';
SELECT '============================================================================' AS '';
SELECT '✅ MIGRATION 021 TERMINÉE — table salle créée, parametres nettoyés' AS '';
SELECT '============================================================================' AS '';