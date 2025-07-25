-- ===============================================
-- SOLUTION : MIGRATION SANS CONFLIT DE TRIGGERS
-- ===============================================

-- 1. ✅ DÉSACTIVER TEMPORAIREMENT LES TRIGGERS
DROP TRIGGER IF EXISTS `tr_paiement_after_insert`;
DROP TRIGGER IF EXISTS `tr_paiement_after_delete`;

-- 2. ✅ FAIRE LA MIGRATION DES DONNÉES EXISTANTES
INSERT INTO `paiement` (
    `id_facture`, 
    `date_paiement`, 
    `montant_paye`, 
    `methode_paiement`, 
    `commentaire`, 
    `date_creation`,
    `numero_paiement`
)
SELECT 
    `id_facture`,
    COALESCE(DATE(`date_paiement`), DATE(`date_edition`)) as date_paiement,
    `montant_paye`,
    'virement' as methode_paiement,
    'Paiement migré depuis l\'ancien système' as commentaire,
    COALESCE(`date_paiement`, `date_edition`) as date_creation,
    1 as numero_paiement
FROM `facture` 
WHERE `montant_paye` IS NOT NULL AND `montant_paye` > 0;

-- 3. ✅ METTRE À JOUR MANUELLEMENT LES NOUVELLES COLONNES
UPDATE `facture` 
SET 
    `montant_paye_total` = COALESCE(`montant_paye`, 0),
    `nb_paiements` = CASE WHEN `montant_paye` > 0 THEN 1 ELSE 0 END,
    `date_dernier_paiement` = `date_paiement`
WHERE `montant_paye` IS NOT NULL AND `montant_paye` > 0;

-- Calculer le montant restant pour toutes les factures
UPDATE `facture` 
SET `montant_restant` = GREATEST(0, `montant_total` - COALESCE(`montant_paye_total`, 0));

-- 4. ✅ RECRÉER LES TRIGGERS AVEC PROTECTION CONTRE LA RÉCURSION
DELIMITER ;;

-- Trigger après insertion d'un paiement (VERSION CORRIGÉE)
CREATE TRIGGER `tr_paiement_after_insert` 
AFTER INSERT ON `paiement` 
FOR EACH ROW 
BEGIN
    DECLARE facture_count INT DEFAULT 0;
    
    -- Éviter la récursion en vérifiant si on est déjà en train de traiter cette facture
    SELECT COUNT(*) INTO facture_count 
    FROM `facture` 
    WHERE `id_facture` = NEW.id_facture 
    FOR UPDATE;
    
    -- Calculer et mettre à jour les totaux pour la facture
    UPDATE `facture` 
    SET 
        `montant_paye_total` = (
            SELECT COALESCE(SUM(montant_paye), 0) 
            FROM `paiement` 
            WHERE `id_facture` = NEW.id_facture 
            AND `statut` = 'confirme'
        ),
        `nb_paiements` = (
            SELECT COUNT(*) 
            FROM `paiement` 
            WHERE `id_facture` = NEW.id_facture 
            AND `statut` = 'confirme'
        ),
        `date_dernier_paiement` = NEW.date_creation,
        `montant_paye` = NEW.montant_paye  -- Garder le dernier paiement pour compatibilité
    WHERE `id_facture` = NEW.id_facture;
    
    -- Calculer le montant restant et l'état en une seule requête
    UPDATE `facture` 
    SET 
        `montant_restant` = GREATEST(0, `montant_total` - `montant_paye_total`),
        `etat` = CASE 
            WHEN GREATEST(0, `montant_total` - `montant_paye_total`) <= 0 THEN 'Payée'
            WHEN `montant_paye_total` > 0 AND GREATEST(0, `montant_total` - `montant_paye_total`) > 0 THEN 'Partiellement payée'
            ELSE `etat`
        END
    WHERE `id_facture` = NEW.id_facture;
END;;

-- Trigger après suppression d'un paiement (VERSION CORRIGÉE)
CREATE TRIGGER `tr_paiement_after_delete` 
AFTER DELETE ON `paiement` 
FOR EACH ROW 
BEGIN
    DECLARE facture_count INT DEFAULT 0;
    
    -- Éviter la récursion
    SELECT COUNT(*) INTO facture_count 
    FROM `facture` 
    WHERE `id_facture` = OLD.id_facture 
    FOR UPDATE;
    
    -- Recalculer les totaux pour la facture
    UPDATE `facture` 
    SET 
        `montant_paye_total` = (
            SELECT COALESCE(SUM(montant_paye), 0) 
            FROM `paiement` 
            WHERE `id_facture` = OLD.id_facture 
            AND `statut` = 'confirme'
        ),
        `nb_paiements` = (
            SELECT COUNT(*) 
            FROM `paiement` 
            WHERE `id_facture` = OLD.id_facture 
            AND `statut` = 'confirme'
        ),
        `date_dernier_paiement` = (
            SELECT MAX(date_creation) 
            FROM `paiement` 
            WHERE `id_facture` = OLD.id_facture 
            AND `statut` = 'confirme'
        ),
        `montant_paye` = (
            SELECT montant_paye 
            FROM `paiement` 
            WHERE `id_facture` = OLD.id_facture 
            AND `statut` = 'confirme'
            ORDER BY date_creation DESC 
            LIMIT 1
        )
    WHERE `id_facture` = OLD.id_facture;
    
    -- Recalculer le montant restant et l'état
    UPDATE `facture` 
    SET 
        `montant_restant` = GREATEST(0, `montant_total` - COALESCE(`montant_paye_total`, 0)),
        `etat` = CASE 
            WHEN GREATEST(0, `montant_total` - COALESCE(`montant_paye_total`, 0)) <= 0 AND COALESCE(`montant_paye_total`, 0) > 0 THEN 'Payée'
            WHEN COALESCE(`montant_paye_total`, 0) > 0 AND GREATEST(0, `montant_total` - COALESCE(`montant_paye_total`, 0)) > 0 THEN 'Partiellement payée'
            WHEN COALESCE(`montant_paye_total`, 0) = 0 THEN 'Envoyée'
            ELSE `etat`
        END
    WHERE `id_facture` = OLD.id_facture;
END;;

DELIMITER ;

-- ===============================================
-- VALIDATION ET VÉRIFICATION
-- ===============================================

-- Vérifier que la migration s'est bien passée
SELECT 
    f.numero_facture,
    f.montant_total,
    f.ristourne,
    f.montant_paye AS ancien_montant_paye,
    f.montant_paye_total AS nouveau_montant_paye_total,
    f.montant_restant,
    f.nb_paiements,
    f.etat,
    p.date_paiement,
    p.methode_paiement,
    p.commentaire
FROM facture f
LEFT JOIN paiement p ON f.id_facture = p.id_facture
WHERE f.montant_paye > 0 OR p.id_paiement IS NOT NULL
ORDER BY f.id_facture, p.numero_paiement;

-- Vérifier les totaux
SELECT 
    'VÉRIFICATION' as type,
    COUNT(*) as nb_factures_avec_paiements_anciens,
    SUM(montant_paye) as total_ancien_systeme
FROM facture 
WHERE montant_paye > 0

UNION ALL

SELECT 
    'MIGRATION' as type,
    COUNT(DISTINCT id_facture) as nb_factures_avec_paiements_nouveaux,
    SUM(montant_paye) as total_nouveau_systeme
FROM paiement;

-- ===============================================
-- COMMANDES DE NETTOYAGE (OPTIONNEL)
-- ===============================================

-- Si tout fonctionne bien, vous pouvez nettoyer les anciennes données
-- ⚠️ ATTENTION: Faites une sauvegarde avant de lancer ces commandes !

-- Optionnel: Supprimer les anciennes colonnes après validation complète
-- ALTER TABLE `facture` DROP COLUMN `montant_paye`;
-- ALTER TABLE `facture` DROP COLUMN `date_paiement`;