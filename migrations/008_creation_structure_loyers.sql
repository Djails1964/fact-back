-- ============================================================================
-- Migration 008: Création de la structure loyers (FROM SCRATCH)
-- ============================================================================
-- Date: 2026-02-21
-- Objectif: Créer toute la structure pour gérer les loyers
-- Architecture: Table loyer séparée, numérotation par client en PHP
-- ============================================================================

SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

SELECT '============================================================================' AS '';
SELECT 'Migration 008: Création structure loyers' AS '';
SELECT '============================================================================' AS '';

-- ============================================================================
-- ÉTAPE 0 : Modifier la table CLIENT
-- ============================================================================
SELECT '=== MODIFICATION TABLE CLIENT ===' as info;

-- Ajouter le champ a_loyer si non existant
SET @col_exists = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'client' 
    AND COLUMN_NAME = 'a_loyer'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `client` ADD COLUMN `a_loyer` TINYINT(1) DEFAULT 0 COMMENT ''Client avec loyer actif'' AFTER `email`',
    'SELECT "Colonne a_loyer existe déjà dans client" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Créer l'index si non existant
SET @idx_exists = (
    SELECT COUNT(*) 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'client' 
    AND INDEX_NAME = 'idx_client_loyer'
);

SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX `idx_client_loyer` ON `client`(`a_loyer`)',
    'SELECT "Index idx_client_loyer existe déjà" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Table CLIENT modifiée : champ a_loyer ajouté' as status;

-- ============================================================================
-- ÉTAPE 1: Création de la table loyer
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 1: Création table loyer ===' AS '';

CREATE TABLE IF NOT EXISTS loyer (
    -- Identifiant
    id_loyer INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Numérotation séquentielle par client (gérée en PHP)
    -- Format: LOY-{id_client}-{seq}
    -- Exemple: LOY-12-001, LOY-12-002, LOY-12-003
    numero_loyer VARCHAR(20) NOT NULL COMMENT 'Format: LOY-{id_client}-{seq}',
    numero_sequence INT NOT NULL COMMENT 'Numéro séquentiel par client',
    
    -- Relation client
    id_client INT NOT NULL COMMENT 'Client concerné par ce loyer',
    
    -- Dates et durée
    date_creation_loyer DATE NOT NULL COMMENT 'Date de création du contrat de loyer',
    periode_debut DATE NOT NULL COMMENT 'Date de début de la période de location',
    periode_fin DATE NOT NULL COMMENT 'Date de fin de la période de location',
    duree_mois INT NOT NULL DEFAULT 12 COMMENT 'Durée de la location en mois',
    
    -- Description
    motif VARCHAR(255) NULL COMMENT 'Motif du loyer (ex: Loyer annuel, Sous-location...)',
    description TEXT NULL COMMENT 'Description détaillée ou notes',
    
    -- Montants
    montant_total DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Montant total du loyer sur la période',
    montant_mensuel_moyen DECIMAL(10,2) GENERATED ALWAYS AS (
        montant_total / NULLIF(duree_mois, 0)
    ) STORED COMMENT 'Montant moyen par mois (calculé automatiquement)',
    
    -- État du loyer
    statut ENUM('actif', 'termine', 'suspendu', 'annule') NOT NULL DEFAULT 'actif' 
        COMMENT 'Statut du contrat de loyer',
    
    etat_paiement ENUM('non_paye', 'partiellement_paye', 'paye') NOT NULL DEFAULT 'non_paye' 
        COMMENT 'État global du paiement',
    
    -- Métadonnées de suivi
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de création dans le système',
    date_modification DATETIME NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date de dernière modification',
    createur_id INT NULL COMMENT 'Utilisateur ayant créé ce loyer',
    modificateur_id INT NULL COMMENT 'Utilisateur ayant modifié ce loyer',
    
    -- Index pour performances
    INDEX idx_loyer_client (id_client),
    INDEX idx_loyer_periode (periode_debut, periode_fin),
    INDEX idx_loyer_statut (statut),
    INDEX idx_loyer_etat_paiement (etat_paiement),
    INDEX idx_loyer_numero (numero_loyer),
    
    -- Contraintes d'unicité
    UNIQUE KEY uk_loyer_numero (numero_loyer) COMMENT 'Un numéro de loyer ne peut exister qu\'une seule fois',
    UNIQUE KEY uk_loyer_client_sequence (id_client, numero_sequence) COMMENT 'Séquence unique par client',
    
    -- Clé étrangère vers client
    CONSTRAINT fk_loyer_client FOREIGN KEY (id_client) 
        REFERENCES client(id) 
        ON DELETE RESTRICT 
        ON UPDATE CASCADE
        
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Contrats de loyer (séparés de la facturation)';

SELECT '✅ Table loyer créée avec succès' AS resultat;

-- ============================================================================
-- ÉTAPE 2: Création de la table loyer_detail (détails mensuels)
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 2: Création table loyer_detail ===' AS '';

-- Supprimer l'ancienne table si elle existe (on repart de zéro)
DROP TABLE IF EXISTS loyer_detail;

CREATE TABLE loyer_detail (
    -- Identifiant
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Relation vers le loyer
    id_loyer INT NOT NULL COMMENT 'Loyer concerné',
    
    -- Identification du mois
    mois VARCHAR(20) NOT NULL COMMENT 'Nom du mois (ex: Janvier 2025)',
    numero_mois INT NOT NULL COMMENT 'Numéro du mois (1-12)',
    annee INT NOT NULL COMMENT 'Année',
    
    -- Montant pour ce mois
    montant DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Montant du loyer pour ce mois',
    
    -- État du paiement pour ce mois
    est_paye BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Ce mois est-il payé ?',
    date_paiement DATE NULL COMMENT 'Date de paiement effectif',
    
    -- Métadonnées
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    
    -- Index
    INDEX idx_detail_loyer (id_loyer),
    INDEX idx_detail_mois (numero_mois, annee),
    INDEX idx_detail_paye (est_paye),
    
    -- Un seul détail par mois et par loyer
    UNIQUE KEY uk_detail_loyer_mois (id_loyer, numero_mois, annee),
    
    -- Clé étrangère vers loyer (CASCADE = suppression automatique)
    CONSTRAINT fk_detail_loyer FOREIGN KEY (id_loyer) 
        REFERENCES loyer(id_loyer) 
        ON DELETE CASCADE 
        ON UPDATE CASCADE
        
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Détails mensuels des loyers';

SELECT '✅ Table loyer_detail créée avec succès' AS resultat;

-- ============================================================================
-- ÉTAPE 3: Création de la vue v_loyers_complets
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 3: Création vue v_loyers_complets ===' AS '';

DROP VIEW IF EXISTS v_loyers_complets;

CREATE VIEW v_loyers_complets AS
SELECT 
    -- Informations du loyer
    l.id_loyer,
    l.numero_loyer,
    l.numero_sequence,
    l.id_client,
    
    -- Informations du client
    c.prenom AS prenom_client,
    c.nom AS nom_client,
    CONCAT(c.prenom, ' ', c.nom) AS nom_complet_client,
    c.email AS email_client,
    c.telephone AS telephone_client,
    c.rue AS rue_client,
    c.numero AS numero_client,
    c.code_postal AS code_postal_client,
    c.localite AS localite_client,
    
    -- Dates et période
    l.date_creation_loyer,
    l.periode_debut,
    l.periode_fin,
    l.duree_mois,
    
    -- Description
    l.motif,
    l.description,
    
    -- Montants
    l.montant_total,
    l.montant_mensuel_moyen,
    
    -- États
    l.statut,
    l.etat_paiement,
    
    -- Calculs automatiques des paiements
    COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0) AS montant_paye,
    l.montant_total - COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0) AS montant_restant,
    ROUND(
        (COALESCE(SUM(CASE WHEN ld.est_paye THEN ld.montant ELSE 0 END), 0) / NULLIF(l.montant_total, 0)) * 100, 
        2
    ) AS pourcentage_paye,
    
    -- Comptage des mois
    COUNT(CASE WHEN ld.est_paye THEN 1 END) AS mois_payes,
    COUNT(ld.id) AS total_mois,
    
    -- Métadonnées
    l.date_creation,
    l.date_modification,
    l.createur_id,
    l.modificateur_id
    
FROM loyer l
INNER JOIN client c ON l.id_client = c.id
LEFT JOIN loyer_detail ld ON l.id_loyer = ld.id_loyer
GROUP BY 
    l.id_loyer, l.numero_loyer, l.numero_sequence, l.id_client,
    c.prenom, c.nom, c.email, c.telephone, c.rue, c.numero, c.code_postal, c.localite,
    l.date_creation_loyer, l.periode_debut, l.periode_fin, l.duree_mois,
    l.motif, l.description, l.montant_total, l.montant_mensuel_moyen,
    l.statut, l.etat_paiement, l.date_creation, l.date_modification,
    l.createur_id, l.modificateur_id;

SELECT '✅ Vue v_loyers_complets créée avec succès' AS resultat;

-- ============================================================================
-- ÉTAPE 4: Vérifications de la structure
-- ============================================================================

SELECT '' AS '';
SELECT '=== ÉTAPE 4: Vérifications ===' AS '';

-- Vérifier que les tables existent
SELECT 
    CASE 
        WHEN COUNT(*) = 2 THEN '✅ Tables loyer et loyer_detail créées'
        ELSE '❌ Erreur: tables manquantes'
    END AS check_tables
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME IN ('loyer', 'loyer_detail');

-- Vérifier que la vue existe
SELECT 
    CASE 
        WHEN COUNT(*) = 1 THEN '✅ Vue v_loyers_complets créée'
        ELSE '❌ Erreur: vue manquante'
    END AS check_vue
FROM information_schema.VIEWS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'v_loyers_complets';

-- Vérifier les contraintes FK
SELECT 
    CASE 
        WHEN COUNT(*) >= 2 THEN CONCAT('✅ ', COUNT(*), ' contraintes FK créées')
        ELSE '❌ Erreur: contraintes manquantes'
    END AS check_fk
FROM information_schema.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME IN ('loyer', 'loyer_detail')
AND REFERENCED_TABLE_NAME IS NOT NULL;

-- Vérifier les index uniques
SELECT 
    CASE 
        WHEN COUNT(*) >= 2 THEN CONCAT('✅ ', COUNT(*), ' index uniques créés')
        ELSE '❌ Erreur: index manquants'
    END AS check_unique
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'loyer'
AND NON_UNIQUE = 0
AND INDEX_NAME != 'PRIMARY';

-- ============================================================================
-- ÉTAPE 5: Statistiques initiales
-- ============================================================================

SELECT '' AS '';
SELECT '=== STATISTIQUES INITIALES ===' AS '';

SELECT 'Loyers' AS table_name, COUNT(*) AS nombre_lignes FROM loyer
UNION ALL
SELECT 'Détails mensuels', COUNT(*) FROM loyer_detail
UNION ALL
SELECT 'Vue disponible', 1 FROM information_schema.VIEWS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'v_loyers_complets';

-- ============================================================================
-- ÉTAPE 6: Exemples de structure (commenté car \G ne fonctionne pas via PDO)
-- ============================================================================

-- Pour voir la structure, utiliser :
-- SHOW CREATE TABLE loyer;
-- SHOW CREATE TABLE loyer_detail;

-- ============================================================================
-- NOTES IMPORTANTES
-- ============================================================================

SELECT '' AS '';
SELECT '=== NOTES IMPORTANTES ===' AS '';
SELECT '📋 Numérotation: LOY-{id_client}-{seq} (ex: LOY-12-001)' AS info;
SELECT '🔧 Gestion en PHP (pas de fonction SQL)' AS info;
SELECT '📊 Vue v_loyers_complets avec calculs automatiques' AS info;
SELECT '🔗 loyer_detail CASCADE: suppression auto avec loyer' AS info;
SELECT '✅ Structure prête pour utilisation' AS info;

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;

SELECT '' AS '';
SELECT '============================================================================' AS '';
SELECT '✅ MIGRATION 008 TERMINÉE AVEC SUCCÈS' AS '';
SELECT '============================================================================' AS '';