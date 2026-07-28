-- ============================================================================
-- Migration 033 : Ajout colonne facturation_utilisation dans table salle
--
-- Sémantique :
--   facturation_utilisation = 1 → facturation à l'utilisation (heures, journées…)
--                                  → génère une FACTURE
--   facturation_utilisation = 0 → loyer mensuel fixe
--                                  → génère une CONFIRMATION DE PAIEMENT
--
-- Cette colonne rend type_document dérivable mais on la conserve pour
-- compatibilité avec le code existant — elle est synchronisée ci-dessous.
-- ============================================================================

SET NAMES utf8mb4;

SELECT '=== Migration 033 : facturation_utilisation dans salle ===' AS '';

-- ── ÉTAPE 1 : Ajouter la colonne ─────────────────────────────────────────────
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='salle'
    AND COLUMN_NAME='facturation_utilisation');
SET @sql = IF(@col=0,
    'ALTER TABLE `salle`
     ADD COLUMN `facturation_utilisation` TINYINT(1) NOT NULL DEFAULT 0
     COMMENT "1 = facturation à l\'utilisation (facture) / 0 = loyer fixe (confirmation)"
     AFTER `type_document`',
    'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT CASE WHEN COUNT(*)=1
    THEN '✅ Colonne facturation_utilisation ajoutée'
    ELSE '❌ Échec ajout colonne'
END AS check_col
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='salle'
AND COLUMN_NAME='facturation_utilisation';

-- ── ÉTAPE 2 : Dériver la valeur depuis type_document existant ─────────────────
UPDATE salle
SET facturation_utilisation = CASE
    WHEN type_document = 'facture' THEN 1
    ELSE 0
END;

SELECT CONCAT('✅ ', ROW_COUNT(), ' salles mises à jour') AS migration_data;

-- ── ÉTAPE 3 : Vérification ───────────────────────────────────────────────────
SELECT id, nom, type_document, facturation_utilisation
FROM salle
ORDER BY id;

SELECT '✅ MIGRATION 033 TERMINÉE' AS '';