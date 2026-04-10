-- ============================================================================
-- Migration 017 : Ajout colonne dates dans loyer_detail
-- ============================================================================
-- Date: 2026-03-16
-- Contexte: Les dates de location sont liées à un mois spécifique,
--           elles appartiennent à loyer_detail (une ligne par mois)
--           et non à l'entête loyer (annuelle).
--
--   dates  JSON NULL — tableau JSON de dates ISO du mois
--                      ex: ["2025-01-05","2025-01-12"]
-- ============================================================================

SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

SELECT '============================================================================' AS '';
SELECT 'Migration 017: Ajout colonne dates dans loyer_detail' AS '';
SELECT '============================================================================' AS '';

SET @col_detail = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND   TABLE_NAME   = 'loyer_detail'
    AND   COLUMN_NAME  = 'dates'
);

SET @sql = IF(@col_detail = 0,
    'ALTER TABLE `loyer_detail`
         ADD COLUMN `dates` JSON NULL DEFAULT NULL
         COMMENT "Dates de location du mois (tableau JSON ISO) — pour facturation"
         AFTER `montant`',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT CASE WHEN COUNT(*) = 1 THEN '✅ Colonne dates ajoutée dans loyer_detail'
            ELSE '❌ Colonne dates manquante dans loyer_detail' END AS check_detail
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loyer_detail' AND COLUMN_NAME = 'dates';

SELECT ORDINAL_POSITION AS pos, COLUMN_NAME AS colonne, COLUMN_TYPE AS type, IS_NULLABLE AS nullable
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loyer_detail'
ORDER BY ORDINAL_POSITION;

SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;

SELECT '✅ MIGRATION 018 TERMINÉE' AS '';