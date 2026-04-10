-- ============================================================================
-- Migration 010: Création de la structure location de salle
-- ============================================================================
-- Date: 2026-03-07 (révisé 2026-03-09)
-- Objectif: Créer les tables de locations de salle selon une architecture
--           maître / détail :
--
--   location_salle_contrat  — une ligne par (client, annee)
--                             persiste la présence du client dans le tableau
--                             même sans aucune location saisie
--
--   location_salle_detail   — une ligne par (contrat, mois, salle, type_location)
--                             contient les données réelles : quantité, notes
--
-- Salles configurables via la table parametres :
--   groupe_parametre      = 'LocationSalle'
--   sous_groupe_parametre = 'Salles'
--
-- Types de location : désormais pilotés par les unités tarifaires (table unites)
--   via la table services_unites et les services 'Location Salle' / 'Location Cabinet'
--   Le champ type_location stocke l'id de l'unité tarifaire (VARCHAR, non ENUM).
--
-- Modifications incluses (2026-03-09) :
--   - Table unites          : ajout colonne abreviation VARCHAR(2)
--   - Table location_salle_detail : type_location ENUM → VARCHAR(50)
--     (idempotent : ne modifie que si le type actuel est encore ENUM)
--
-- Dépend de: Table client, table parametres, table unites
-- Remplace : table location_salle (architecture plate précédente)
-- ============================================================================

SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

SELECT '============================================================================' AS '';
SELECT 'Migration 010: Création structure location de salle (maître / détail)' AS '';
SELECT '============================================================================' AS '';

-- ============================================================================
-- ÉTAPE 0 : Vérification des prérequis
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 0: Vérification des prérequis ===' AS '';

SELECT
    CASE
        WHEN COUNT(*) = 1
        THEN '✅ Table client présente'
        ELSE '❌ ERREUR: Table client introuvable — structure de base manquante'
    END AS prerequis_client
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'client';

SELECT
    CASE
        WHEN COUNT(*) = 1
        THEN '✅ Table parametres présente'
        ELSE '❌ ERREUR: Table parametres introuvable — lancez d''abord les migrations initiales'
    END AS prerequis_parametres
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'parametres';

SELECT
    CASE
        WHEN COUNT(*) = 1
        THEN '✅ Table unites présente'
        ELSE '❌ ERREUR: Table unites introuvable — lancez d''abord les migrations de tarification'
    END AS prerequis_unites
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'unites';

-- ============================================================================
-- ÉTAPE 1: Suppression de l'ancienne table plate (si elle existe)
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 1: Nettoyage ancienne structure ===' AS '';

-- Suppression sécurisée : on vérifie l'existence avant de tenter le DROP
SET @old_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'location_salle'
);

SET @sql_drop = IF(@old_exists > 0,
    'DROP TABLE location_salle',
    'SELECT ''Table location_salle absente — rien à supprimer'' AS info'
);
PREPARE stmt FROM @sql_drop;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT
    CASE
        WHEN COUNT(*) = 0 THEN '✅ Ancienne table location_salle absente ou supprimée'
        ELSE '❌ Échec de la suppression de location_salle'
    END AS check_drop
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'location_salle';

-- ============================================================================
-- ÉTAPE 2: Création de la table maître — location_salle_contrat
-- ============================================================================
-- Un enregistrement = un client affiché dans le tableau pour une année donnée.
-- Sa présence persiste même si aucun détail n'a encore été saisi.
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 2: Création table location_salle_contrat ===' AS '';

CREATE TABLE IF NOT EXISTS `location_salle_contrat` (

    -- Identifiant
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,

    -- Relation client
    `id_client`     INT             NOT NULL
                    COMMENT 'Client locataire — référence client.id',

    -- Année de référence
    `annee`         SMALLINT        NOT NULL
                    COMMENT 'Année du tableau (ex: 2025)',

    -- Métadonnées de suivi
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                    COMMENT 'Date de création',
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP
                    COMMENT 'Date de dernière modification',
    `created_by`    INT UNSIGNED    NULL
                    COMMENT 'Identifiant de l''utilisateur créateur',
    `updated_by`    INT UNSIGNED    NULL
                    COMMENT 'Identifiant du dernier utilisateur modificateur',

    -- Clé primaire
    PRIMARY KEY (`id`),

    -- Un client ne peut apparaître qu'une fois par année
    UNIQUE KEY `uk_contrat_client_annee`
        (`id_client`, `annee`)
        COMMENT 'Un seul contrat par client et par année',

    -- Index de recherche
    INDEX `idx_contrat_annee`    (`annee`),
    INDEX `idx_contrat_client`   (`id_client`),

    -- Clé étrangère vers client
    CONSTRAINT `fk_contrat_client`
        FOREIGN KEY (`id_client`)
        REFERENCES `client`(`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tableau de présence : un client affiché par année dans le module location de salle';

SELECT
    CASE
        WHEN COUNT(*) = 1 THEN '✅ Table location_salle_contrat créée'
        ELSE '❌ Échec de la création de location_salle_contrat'
    END AS check_contrat
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'location_salle_contrat';

-- ============================================================================
-- ÉTAPE 3: Création de la table détail — location_salle_detail
-- ============================================================================
-- Un enregistrement = une location effective sur un mois donné.
-- Plusieurs lignes possibles par mois si salles ou types différents.
--
-- type_location : stocke l'id (sous forme texte) de l'unité tarifaire issue
--                de la table unites, via services_unites.
--                VARCHAR(50) au lieu de ENUM pour permettre toute valeur.
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 3: Création table location_salle_detail ===' AS '';

CREATE TABLE IF NOT EXISTS `location_salle_detail` (

    -- Identifiant
    `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,

    -- Relation vers le contrat (maître)
    `id_contrat`        INT UNSIGNED    NOT NULL
                        COMMENT 'Référence vers location_salle_contrat.id',

    -- Période
    `mois`              TINYINT         NOT NULL
                        COMMENT 'Mois de la location (1=Janvier … 12=Décembre)',

    -- Salle et type
    `salle`             VARCHAR(100)    NOT NULL
                        COMMENT 'Nom de la salle louée (valeur issue de la table parametres)',
    `type_location`     VARCHAR(50)     NOT NULL
                        COMMENT 'ID de l''unité tarifaire (unites.id) — ex: "3", "7"',

    -- Quantité
    `quantite`          DECIMAL(5,1)    NOT NULL DEFAULT 1.0
                        COMMENT 'Nombre d''unités louées (ex: 3.5 heures, 2 journées)',

    -- Commentaire libre
    `note`              TEXT            NULL
                        COMMENT 'Note ou commentaire libre sur cette location',

    -- Métadonnées de suivi
    `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                        COMMENT 'Date de création',
    `updated_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP
                        COMMENT 'Date de dernière modification',
    `created_by`        INT UNSIGNED    NULL
                        COMMENT 'Identifiant de l''utilisateur créateur',
    `updated_by`        INT UNSIGNED    NULL
                        COMMENT 'Identifiant du dernier utilisateur modificateur',

    -- Clé primaire
    PRIMARY KEY (`id`),

    -- Un contrat ne peut avoir qu'une entrée par combinaison mois/salle/type.
    -- Permet plusieurs salles différentes ou plusieurs types sur le même mois.
    UNIQUE KEY `uk_detail_contrat_mois_salle_type`
        (`id_contrat`, `mois`, `salle`, `type_location`)
        COMMENT 'Unicité par contrat / mois / salle / type de location',

    -- Index de recherche
    INDEX `idx_detail_contrat`   (`id_contrat`),
    INDEX `idx_detail_mois`      (`mois`),
    INDEX `idx_detail_salle`     (`salle`),
    INDEX `idx_detail_type`      (`type_location`),

    -- Clé étrangère vers le contrat maître
    CONSTRAINT `fk_detail_contrat`
        FOREIGN KEY (`id_contrat`)
        REFERENCES `location_salle_contrat`(`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Détail des locations de salle par mois : quantité, salle, type (id unité tarifaire), notes';

SELECT
    CASE
        WHEN COUNT(*) = 1 THEN '✅ Table location_salle_detail créée'
        ELSE '❌ Échec de la création de location_salle_detail'
    END AS check_detail
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'location_salle_detail';

-- ============================================================================
-- ÉTAPE 3b: Migration type_location ENUM → VARCHAR (si déjà déployée en ENUM)
-- ============================================================================
-- Cas où la migration 010 a déjà été exécutée avec l'ancien ENUM.
-- On détecte le type de colonne et on modifie uniquement si nécessaire.
-- Le UNIQUE KEY portant sur type_location est recréé automatiquement
-- car la contrainte est définie dans la table — aucune action supplémentaire.
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 3b: Migration type_location ENUM → VARCHAR (idempotent) ===' AS '';

-- Détecter si type_location est encore un ENUM
SET @type_location_data_type = (
    SELECT DATA_TYPE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA  = DATABASE()
    AND   TABLE_NAME    = 'location_salle_detail'
    AND   COLUMN_NAME   = 'type_location'
);

SELECT
    CASE @type_location_data_type
        WHEN 'enum'    THEN '⚠️  type_location est encore ENUM — migration vers VARCHAR en cours…'
        WHEN 'varchar' THEN '✅ type_location est déjà VARCHAR — aucune action requise'
        ELSE CONCAT('ℹ️  type_location : ', IFNULL(@type_location_data_type, 'colonne introuvable'))
    END AS check_type_location;

-- Modifier le type uniquement si c'est encore un ENUM
SET @sql_alter_type = IF(
    @type_location_data_type = 'enum',
    'ALTER TABLE `location_salle_detail`
         MODIFY COLUMN `type_location` VARCHAR(50) NOT NULL
         COMMENT ''ID de l''''unité tarifaire (unites.id) — ex: "3", "7"''',
    'SELECT ''type_location déjà VARCHAR — ALTER ignoré'' AS info'
);
PREPARE stmt FROM @sql_alter_type;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Vérification post-modification
SELECT
    CASE
        WHEN DATA_TYPE = 'varchar'
        THEN '✅ type_location confirmé VARCHAR(50)'
        ELSE CONCAT('❌ type_location inattendu : ', DATA_TYPE)
    END AS check_alter_result
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND   TABLE_NAME   = 'location_salle_detail'
AND   COLUMN_NAME  = 'type_location';

-- ============================================================================
-- ÉTAPE 3c: Vérification de l'index IDX_DETAIL_TYPE après modification
-- ============================================================================
-- Un ALTER COLUMN sur une colonne indexée conserve les index existants en MySQL.
-- On vérifie néanmoins que idx_detail_type est toujours présent.
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 3c: Vérification index idx_detail_type ===' AS '';

SELECT
    CASE
        WHEN COUNT(*) >= 1
        THEN '✅ Index idx_detail_type présent sur type_location'
        ELSE '⚠️  Index idx_detail_type absent — recréation…'
    END AS check_idx_type
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
AND   TABLE_NAME   = 'location_salle_detail'
AND   INDEX_NAME   = 'idx_detail_type';

-- Recréer l'index s'il a disparu (cas exceptionnel)
SET @idx_type_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND   TABLE_NAME   = 'location_salle_detail'
    AND   INDEX_NAME   = 'idx_detail_type'
);

SET @sql_idx = IF(
    @idx_type_exists = 0,
    'ALTER TABLE `location_salle_detail` ADD INDEX `idx_detail_type` (`type_location`)',
    'SELECT ''Index idx_detail_type déjà présent'' AS info'
);
PREPARE stmt FROM @sql_idx;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- ÉTAPE 3d: Ajout colonnes id_unite / id_service dans location_salle_detail
--           (idempotent — pour bases existantes)
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 3d: Ajout colonnes id_unite / id_service dans location_salle_detail ===' AS '';

SET @col_unite = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'location_salle_detail' AND COLUMN_NAME = 'id_unite');
SET @sql = IF(@col_unite = 0, 'ALTER TABLE `location_salle_detail` ADD COLUMN `id_unite` INT NULL COMMENT "Ref unites.id" AFTER `type_location`', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_service = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'location_salle_detail' AND COLUMN_NAME = 'id_service');
SET @sql = IF(@col_service = 0, 'ALTER TABLE `location_salle_detail` ADD COLUMN `id_service` INT NULL COMMENT "Ref services.id" AFTER `id_unite`', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_unite = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'location_salle_detail' AND INDEX_NAME = 'idx_detail_unite');
SET @sql = IF(@idx_unite = 0, 'ALTER TABLE `location_salle_detail` ADD INDEX `idx_detail_unite` (`id_unite`)', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_service = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'location_salle_detail' AND INDEX_NAME = 'idx_detail_service');
SET @sql = IF(@idx_service = 0, 'ALTER TABLE `location_salle_detail` ADD INDEX `idx_detail_service` (`id_service`)', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_unite = (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_detail_unite');
SET @sql = IF(@fk_unite = 0, 'ALTER TABLE `location_salle_detail` ADD CONSTRAINT `fk_detail_unite` FOREIGN KEY (`id_unite`) REFERENCES `unites`(`id`) ON DELETE SET NULL ON UPDATE CASCADE', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_service = (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_detail_service');
SET @sql = IF(@fk_service = 0, 'ALTER TABLE `location_salle_detail` ADD CONSTRAINT `fk_detail_service` FOREIGN KEY (`id_service`) REFERENCES `services`(`id`) ON DELETE SET NULL ON UPDATE CASCADE', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Rétro-remplissage : id_unite depuis type_location (si valeur numérique)
UPDATE `location_salle_detail`
SET `id_unite` = CAST(`type_location` AS UNSIGNED)
WHERE `id_unite` IS NULL
AND `type_location` REGEXP '^[0-9]+$';

-- Rétro-remplissage : id_service depuis services_unites
UPDATE `location_salle_detail` lsd
JOIN `services_unites` su ON su.unite_id = lsd.id_unite
SET lsd.`id_service` = su.service_id
WHERE lsd.`id_service` IS NULL
AND lsd.`id_unite` IS NOT NULL;

SELECT
    (SELECT CASE WHEN COUNT(*) = 1 THEN '✅ id_unite présente'   ELSE '❌ id_unite manquante'   END FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'location_salle_detail' AND COLUMN_NAME = 'id_unite')   AS check_id_unite,
    (SELECT CASE WHEN COUNT(*) = 1 THEN '✅ id_service présente' ELSE '❌ id_service manquante' END FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'location_salle_detail' AND COLUMN_NAME = 'id_service') AS check_id_service,
    (SELECT COUNT(*) FROM location_salle_detail) AS total_details,
    (SELECT SUM(CASE WHEN id_unite   IS NOT NULL THEN 1 ELSE 0 END) FROM location_salle_detail) AS avec_id_unite,
    (SELECT SUM(CASE WHEN id_service IS NOT NULL THEN 1 ELSE 0 END) FROM location_salle_detail) AS avec_id_service;

-- ============================================================================
-- ÉTAPE 4: Ajout de la colonne abreviation dans la table unites
-- ============================================================================
-- Abréviation courte (max 2 caractères, ex: h, DJ, J) saisie par l'utilisateur.
-- Utilisée dans l'affichage des badges du tableau de location de salle.
-- Optionnelle : NULL si non renseignée.
-- Idempotent : on vérifie l'existence de la colonne avant tout ALTER.
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 4: Ajout colonne abreviation dans la table unites ===' AS '';

-- Détecter si la colonne existe déjà
SET @abrev_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND   TABLE_NAME   = 'unites'
    AND   COLUMN_NAME  = 'abreviation'
);

SELECT
    CASE @abrev_exists
        WHEN 0 THEN '⚠️  Colonne abreviation absente de unites — ajout en cours…'
        ELSE        '✅ Colonne abreviation déjà présente dans unites — aucune action'
    END AS check_abrev_avant;

-- Ajouter uniquement si absente
SET @sql_abrev = IF(
    @abrev_exists = 0,
    'ALTER TABLE `unites`
         ADD COLUMN `abreviation` VARCHAR(2) NULL DEFAULT NULL
         COMMENT ''Abréviation affichage (max 2 car., ex: h, DJ, J) — optionnelle''
         AFTER `nom`',
    'SELECT ''Colonne abreviation déjà présente — ALTER ignoré'' AS info'
);
PREPARE stmt FROM @sql_abrev;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Vérification post-ajout
SELECT
    CASE
        WHEN COUNT(*) = 1
        THEN CONCAT('✅ Colonne abreviation confirmée : ', COLUMN_TYPE,
                    ' — NULL=', IS_NULLABLE,
                    ' — après colonne : ', IFNULL(
                        (SELECT COLUMN_NAME FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'unites'
                         AND ORDINAL_POSITION = (
                             SELECT ORDINAL_POSITION - 1
                             FROM information_schema.COLUMNS
                             WHERE TABLE_SCHEMA = DATABASE()
                             AND TABLE_NAME   = 'unites'
                             AND COLUMN_NAME  = 'abreviation'
                         )), '?'
                    ))
        ELSE '❌ Colonne abreviation introuvable après ALTER'
    END AS check_abrev_apres
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND   TABLE_NAME   = 'unites'
AND   COLUMN_NAME  = 'abreviation';

-- Afficher la position exacte de la colonne dans la table
SELECT
    ORDINAL_POSITION    AS position,
    COLUMN_NAME         AS colonne,
    COLUMN_TYPE         AS type,
    IS_NULLABLE         AS nullable,
    COLUMN_DEFAULT      AS defaut,
    COLUMN_COMMENT      AS commentaire
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND   TABLE_NAME   = 'unites'
ORDER BY ORDINAL_POSITION;

-- ============================================================================
-- ÉTAPE 5: Vérification des prérequis tarifaires
-- ============================================================================
-- On s'assure que la table services_unites et les services de location
-- existent, pour que le mécanisme de sélection des unités soit opérationnel.
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 5: Vérification des prérequis tarifaires ===' AS '';

SELECT
    CASE
        WHEN COUNT(*) = 1
        THEN '✅ Table services_unites présente'
        ELSE '⚠️  Table services_unites absente — les unités de location ne seront pas disponibles'
    END AS check_services_unites
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
AND   TABLE_NAME   = 'services_unites';

SELECT
    CASE
        WHEN COUNT(*) = 1
        THEN '✅ Table services présente'
        ELSE '⚠️  Table services absente — configurez d''abord les services tarifaires'
    END AS check_services
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
AND   TABLE_NAME   = 'services';

-- Lister les services de location existants (informatif, pas bloquant)
SELECT
    CONCAT('ℹ️  Service trouvé : id=', id, ' | nom="', nom, '" | actif=', IF(actif, 'oui', 'non')) AS services_location
FROM services
WHERE nom LIKE 'Location%'
ORDER BY nom;

-- Lister les unités liées aux services de location (informatif)
SELECT
    CONCAT('ℹ️  Unité : id=', u.id,
           ' | code=', u.code,
           ' | nom="', u.nom, '"',
           ' | abrev=', IFNULL(CONCAT('"', u.abreviation, '"'), 'NULL'),
           ' | service="', s.nom, '"',
           ' | actif=', IF(su.actif, 'oui', 'non')) AS unites_location
FROM services_unites su
JOIN unites  u ON u.id = su.unite_id
JOIN services s ON s.id = su.service_id
WHERE s.nom LIKE 'Location%'
ORDER BY s.nom, u.nom;

-- ============================================================================
-- ÉTAPE 6: Insertion des salles disponibles dans la table parametres
-- ============================================================================
-- Nouvelle structure : une catégorie par salle, deux paramètres par catégorie :
--   nom_parametre=label       → nom affiché de la salle
--   nom_parametre=nom_service → nom du service tarifaire associé (choisi par l'admin)
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 5: Insertion des salles dans parametres ===' AS '';

-- Salle Cabinet — paramètre label
SET @p1 = (SELECT COUNT(*) FROM parametres WHERE groupe_parametre = 'LocationSalle' AND sous_groupe_parametre = 'Salles' AND categorie = 'Cabinet' AND nom_parametre = 'label');
SET @sql = IF(@p1 = 0, 'INSERT INTO `parametres` (`nom_parametre`, `valeur_parametre`, `groupe_parametre`, `sous_groupe_parametre`, `categorie`) VALUES (''label'', ''Cabinet'', ''LocationSalle'', ''Salles'', ''Cabinet'')', 'SELECT "Cabinet label existe deja" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Salle Cabinet — paramètre nom_service
SET @p2 = (SELECT COUNT(*) FROM parametres WHERE groupe_parametre = 'LocationSalle' AND sous_groupe_parametre = 'Salles' AND categorie = 'Cabinet' AND nom_parametre = 'nom_service');
SET @sql = IF(@p2 = 0, 'INSERT INTO `parametres` (`nom_parametre`, `valeur_parametre`, `groupe_parametre`, `sous_groupe_parametre`, `categorie`) VALUES (''nom_service'', ''Location Cabinet'', ''LocationSalle'', ''Salles'', ''Cabinet'')', 'SELECT "Cabinet nom_service existe deja" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Salle Salle — paramètre label
SET @p3 = (SELECT COUNT(*) FROM parametres WHERE groupe_parametre = 'LocationSalle' AND sous_groupe_parametre = 'Salles' AND categorie = 'Salle' AND nom_parametre = 'label');
SET @sql = IF(@p3 = 0, 'INSERT INTO `parametres` (`nom_parametre`, `valeur_parametre`, `groupe_parametre`, `sous_groupe_parametre`, `categorie`) VALUES (''label'', ''Salle'', ''LocationSalle'', ''Salles'', ''Salle'')', 'SELECT "Salle label existe deja" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Salle Salle — paramètre nom_service
SET @p4 = (SELECT COUNT(*) FROM parametres WHERE groupe_parametre = 'LocationSalle' AND sous_groupe_parametre = 'Salles' AND categorie = 'Salle' AND nom_parametre = 'nom_service');
SET @sql = IF(@p4 = 0, 'INSERT INTO `parametres` (`nom_parametre`, `valeur_parametre`, `groupe_parametre`, `sous_groupe_parametre`, `categorie`) VALUES (''nom_service'', ''Location Salle'', ''LocationSalle'', ''Salles'', ''Salle'')', 'SELECT "Salle nom_service existe deja" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT
    categorie,
    MAX(CASE WHEN nom_parametre = 'label'       THEN valeur_parametre END) AS label,
    MAX(CASE WHEN nom_parametre = 'nom_service' THEN valeur_parametre END) AS service_tarifaire,
    COUNT(*) AS nb_params,
    CASE WHEN COUNT(*) = 2 THEN '✅ OK' ELSE '❌ Incomplet' END AS statut
FROM parametres
WHERE groupe_parametre      = 'LocationSalle'
AND   sous_groupe_parametre = 'Salles'
GROUP BY categorie
ORDER BY categorie;


-- ============================================================================
-- ÉTAPE 7: Vérification des index et contraintes
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 7: Vérification des index ===' AS '';

SELECT
    TABLE_NAME      AS `table`,
    INDEX_NAME      AS index_name,
    COLUMN_NAME     AS colonne,
    NON_UNIQUE      AS non_unique,
    INDEX_TYPE      AS type
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME IN ('location_salle_contrat', 'location_salle_detail')
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

SELECT '' AS '';
SELECT '=== ÉTAPE 7b: Vérification des clés étrangères ===' AS '';

SELECT
    kcu.TABLE_NAME              AS `table`,
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
WHERE kcu.TABLE_SCHEMA = DATABASE()
AND kcu.TABLE_NAME IN ('location_salle_contrat', 'location_salle_detail')
AND kcu.REFERENCED_TABLE_NAME IS NOT NULL;

-- ============================================================================
-- ÉTAPE 8: Vérification de la structure complète
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 8: Structure de location_salle_contrat ===' AS '';

SELECT
    COLUMN_NAME     AS colonne,
    COLUMN_TYPE     AS type,
    IS_NULLABLE     AS nullable,
    COLUMN_DEFAULT  AS defaut,
    COLUMN_KEY      AS cle,
    EXTRA           AS extra,
    COLUMN_COMMENT  AS commentaire
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME     = 'location_salle_contrat'
ORDER BY ORDINAL_POSITION;

SELECT '' AS '';
SELECT '=== ÉTAPE 8b: Structure de location_salle_detail ===' AS '';

SELECT
    COLUMN_NAME     AS colonne,
    COLUMN_TYPE     AS type,
    IS_NULLABLE     AS nullable,
    COLUMN_DEFAULT  AS defaut,
    COLUMN_KEY      AS cle,
    EXTRA           AS extra,
    COLUMN_COMMENT  AS commentaire
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME     = 'location_salle_detail'
ORDER BY ORDINAL_POSITION;

SELECT '' AS '';
SELECT '=== ÉTAPE 8c: Structure de la table unites (colonne abreviation) ===' AS '';

SELECT
    COLUMN_NAME     AS colonne,
    COLUMN_TYPE     AS type,
    IS_NULLABLE     AS nullable,
    COLUMN_DEFAULT  AS defaut,
    COLUMN_KEY      AS cle,
    COLUMN_COMMENT  AS commentaire
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME     = 'unites'
ORDER BY ORDINAL_POSITION;

-- ============================================================================
-- ÉTAPE 9: Statistiques initiales
-- ============================================================================

SELECT '' AS '';
SELECT '=== STATISTIQUES INITIALES ===' AS '';

SELECT 'location_salle_contrat' AS entite, COUNT(*) AS nombre
FROM location_salle_contrat
UNION ALL
SELECT 'location_salle_detail', COUNT(*)
FROM location_salle_detail
UNION ALL
SELECT 'salles configurées', COUNT(*)
FROM parametres
WHERE groupe_parametre      = 'LocationSalle'
AND   sous_groupe_parametre = 'Salles'
UNION ALL
SELECT 'unités avec abréviation', COUNT(*)
FROM unites
WHERE abreviation IS NOT NULL AND abreviation != '';

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;

-- ============================================================================
-- NOTES IMPORTANTES
-- ============================================================================

SELECT '' AS '';
SELECT '=== NOTES IMPORTANTES ===' AS '';
SELECT '📋 Table maître  : location_salle_contrat — (id_client, annee) — persiste le client dans le tableau' AS info;
SELECT '📋 Table détail  : location_salle_detail  — (id_contrat, mois, salle, type_location) — données réelles' AS info;
SELECT '🔗 FK contrat→client : suppression client bloquée si contrats existent (RESTRICT)' AS info;
SELECT '🔗 FK detail→contrat : suppression contrat CASCADE vers les détails' AS info;
SELECT '🔑 UK contrat    : (id_client, annee) — un client une fois par année' AS info;
SELECT '🔑 UK detail     : (id_contrat, mois, salle, type_location) — plusieurs salles/mois possibles' AS info;
SELECT '📦 Salles gérées dans parametres (groupe=LocationSalle, sous_groupe=Salles)' AS info;
SELECT '🔢 type_location VARCHAR(50) : stocke l''id de l''unité tarifaire (unites.id)' AS info;
SELECT '    ⚠️  RUPTURE vs ancien ENUM(heure, demi_journee, journee) : les anciennes lignes' AS info;
SELECT '        afficheront le code brut si la migration de données n''est pas faite.' AS info;
SELECT '🔤 unites.abreviation VARCHAR(2) NULL : abréviation optionnelle (ex: h, DJ, J)' AS info;
SELECT '    → Saisie dans la gestion des unités tarifaires, affichée dans les badges location.' AS info;
SELECT '📐 quantite DECIMAL(5,1): supporte les demi-unités (ex: 1.5 heure)' AS info;

SELECT '' AS '';
SELECT '============================================================================' AS '';
SELECT '✅ MIGRATION 010 TERMINÉE AVEC SUCCÈS' AS '';
SELECT '============================================================================' AS '';