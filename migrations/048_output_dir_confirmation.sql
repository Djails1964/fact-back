-- Migration 048 : dossier de sortie dédié pour les confirmations de paiement
--
-- Jusqu'ici, les confirmations (factures générées depuis une location au
-- forfait) étaient stockées dans le même dossier que les factures standard
-- (paramètre 'outputDir', groupe 'Facture', sous-groupe 'Chemin'). Cette
-- migration crée un paramètre séparé 'outputDirConfirmation', même
-- structure, pour que les deux types de documents soient rangés dans des
-- dossiers distincts — voir helpers.php::confirmations_path() et
-- document-api.php.
--
-- Valeur par défaut : 'storage/confirmations' (à ajuster ensuite dans
-- Paramètres si besoin, exactement comme pour 'outputDir').

INSERT INTO parametres (nom_parametre, valeur_parametre, groupe_parametre, sous_groupe_parametre, categorie)
SELECT 'outputDirConfirmation', 'storage/confirmations', 'Facture', 'Chemin', NULL
WHERE NOT EXISTS (
    SELECT 1 FROM parametres
    WHERE nom_parametre = 'outputDirConfirmation'
      AND groupe_parametre = 'Facture'
      AND sous_groupe_parametre = 'Chemin'
);