<?php

class ParametreControleur {
    // Fonction pour récupérer les paramètres par groupe_parametre
    public static function getParametresParGroupe($conn, $groupe_parametre = null) {
        try {
            // Requête pour récupérer tous les paramètres (inchangée)
            $sql = $groupe_parametre === null 
                ? "SELECT 
                    nom_parametre,
                    valeur_parametre, 
                    annee_parametre, 
                    groupe_parametre, 
                    sous_groupe_parametre, 
                    categorie 
                FROM parametres 
                ORDER BY groupe_parametre, sous_groupe_parametre, categorie, nom_parametre"
                : "SELECT 
                    nom_parametre, 
                    valeur_parametre, 
                    annee_parametre, 
                    groupe_parametre, 
                    sous_groupe_parametre, 
                    categorie 
                FROM parametres 
                WHERE groupe_parametre = ? 
                ORDER BY sous_groupe_parametre, categorie, nom_parametre";
            
            $stmt = $conn->prepare($sql);
            $groupe_parametre === null ? $stmt->execute() : $stmt->execute([$groupe_parametre]);
            
            $parametres = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Structure hiérarchique : groupe_parametre > Sous-groupe_parametre > Catégorie > Paramètres
            $parametresParGroupe = [];
            
            foreach ($parametres as $parametre) {
                $groupe_parametre = $parametre['groupe_parametre'];
                $sous_groupe_parametre = $parametre['sous_groupe_parametre'] ?: 'Général';
                $categorie = $parametre['categorie'] ?: 'Default';
                
                // Initialiser le groupe_parametre s'il n'existe pas
                if (!isset($parametresParGroupe[$groupe_parametre])) {
                    $parametresParGroupe[$groupe_parametre] = [];
                }
                
                // Ajouter le sous-groupe_parametre s'il n'existe pas
                if (!isset($parametresParGroupe[$groupe_parametre][$sous_groupe_parametre])) {
                    $parametresParGroupe[$groupe_parametre][$sous_groupe_parametre] = [];
                }
                
                // Ajouter la catégorie si elle n'existe pas
                if (!isset($parametresParGroupe[$groupe_parametre][$sous_groupe_parametre][$categorie])) {
                    $parametresParGroupe[$groupe_parametre][$sous_groupe_parametre][$categorie] = [];
                }
                
                // Ajouter le paramètre à la structure
                $parametresParGroupe[$groupe_parametre][$sous_groupe_parametre][$categorie][] = $parametre;
            }
            
            // Normaliser la structure pour garantir une cohérence
            // Assurer que toutes les catégories sont des tableaux
            foreach ($parametresParGroupe as $groupe_parametre => &$sousGroupes) {
                foreach ($sousGroupes as $sous_groupe_parametre => &$categories) {
                    // Vérifier si nous avons des catégories qui ne sont pas des tableaux de paramètres
                    foreach ($categories as $categorie => &$params) {
                        if (!is_array($params)) {
                            // Si ce n'est pas un tableau, le convertir en tableau
                            $params = [$params];
                        }
                    }
                }
            }
            
            return [
                'success' => true,
                'parametres' => $parametresParGroupe
            ];
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des paramètres par groupe_parametre: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération des paramètres par groupe_parametre');
        }
    }

    // Fonction pour récupérer les paramètres par groupe_parametre ET sous-groupe_parametre
    public static function getParametresParSousGroupe($conn, $groupe_parametre, $sous_groupe_parametre) {
        try {
            $sql = "SELECT nom_parametre, valeur_parametre, annee_parametre, groupe_parametre, sous_groupe_parametre, categorie 
                    FROM parametres 
                    WHERE groupe_parametre = ? AND sous_groupe_parametre = ? 
                    ORDER BY nom_parametre";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$groupe_parametre, $sous_groupe_parametre]);
            
            $parametres = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Organiser les paramètres par catégorie
            $parametresParCategorie = [];
            foreach ($parametres as $parametre) {
                $categorie = $parametre['categorie'] ?? 'Default';
                if (!isset($parametresParCategorie[$categorie])) {
                    $parametresParCategorie[$categorie] = [];
                }
                $parametresParCategorie[$categorie][] = $parametre;
            }
            
            return [
                'success' => true,
                'parametres' => $parametresParCategorie
            ];
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des paramètres par sous-groupe_parametre: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération des paramètres par sous-groupe_parametre');
        }
    }

    // Fonction pour récupérer le prochain numéro de facture pour une année donnée
    public static function getProchainNumeroFacture($conn, $annee_parametre) {
        try {
            
            $sql = "SELECT valeur_parametre FROM parametres 
                    WHERE nom_parametre = 'Prochain Numéro Facture' 
                    AND annee_parametre = ? 
                    AND groupe_parametre = 'Facture' 
                    AND sous_groupe_parametre = 'Numéro' 
                    LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$annee_parametre]);
            
            $resultat = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'parametres' => $resultat ? $resultat['valeur_parametre'] : null,
                'message' => $resultat ? 'Prochain numéro de facture récupéré avec succès' : 'Aucun numéro de facture trouvé pour cette année'
            ];
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération du prochain numéro de facture: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération du prochain numéro de facture');
        }
    }    

    /**
     * Récupère un paramètre spécifique avec filtrage par groupe_parametre, sous-groupe_parametre, catégorie
     * 
     * @param PDO $conn La connexion à la base de données
     * @param string $nom_parametre Nom du paramètre à récupérer
     * @param string $groupe_parametre groupe_parametre du paramètre (obligatoire)
     * @param string|null $sous_groupe_parametre Sous-groupe_parametre du paramètre (optionnel)
     * @param string|null $categorie Catégorie du paramètre (optionnel)
     * @param int|null $annee_parametre Année spécifique du paramètre (optionnel)
     * @return array Paramètre trouvé ou null
     * @throws Exception En cas d'erreur
     */
    public static function getParametre($conn, $nom_parametre, $groupe_parametre, $sous_groupe_parametre = null, $categorie = null, $annee_parametre = null) {
        try {
            error_log("Récupération du paramètre: nom_parametre = $nom_parametre, groupe_parametre = $groupe_parametre, sous_groupe_parametre = $sous_groupe_parametre, categorie = $categorie, annee_parametre = $annee_parametre"); // Log pour le débogage
            // Vérification des paramètres obligatoires
            if (!isset($nom_parametre) || !isset($groupe_parametre)) {
                error_log("Erreur : Les paramètres nom_parametre et groupe_parametre sont obligatoires");
                throw new Exception('Erreur : Les paramètres nom_parametre et groupe_parametre sont obligatoires');
            }

            // Construction de la requête SQL de base
            $sql = "SELECT nom_parametre, valeur_parametre, annee_parametre, groupe_parametre, sous_groupe_parametre, categorie 
                    FROM parametres 
                    WHERE nom_parametre = ? AND groupe_parametre = ?";
            $params = [$nom_parametre, $groupe_parametre];
            
            // Ajouter les filtres optionnels
            if ($sous_groupe_parametre !== null) {
                $sql .= " AND sous_groupe_parametre = ?";
                $params[] = $sous_groupe_parametre;
            }
            
            if ($categorie !== null) {
                $sql .= " AND categorie = ?";
                $params[] = $categorie;
            }
            
            if ($annee_parametre !== null) {
                $sql .= " AND annee_parametre = ?";
                $params[] = $annee_parametre;
            }
            
            $sql .= " LIMIT 1";
            
            error_log("Requête SQL : $sql - Paramètres : " . json_encode($params)); // Log pour le débogage
            // Exécuter la requête
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            $parametre = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($parametre) {
                return [
                    'success' => true,
                    'parametre' => $parametre
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Paramètre non trouvé'
                ];
            }
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération du paramètre: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération du paramètre: ' . $e->getMessage());
        }
    }

    /**
     * Récupère les paramètres selon les critères spécifiés
     * 
     * La structure retournée est toujours la même et est décrite dans la fonction
     * Si tous les champs sont null ou absent, la fonction retourne tous les paramètres.
     * Si le groupe_parametre est présent uniquement, la fonction retourne tous les paramètres de ce groupe_parametre.
     * Si un sous-groupe_parametre est présent, alors le groupe_parametre doit être présent obligatoirement.
     * Si une categorie est présente alors le sous-groupe_parametre et le groupe_parametre doivent obligatoirement être présents.
     * 
     * @param PDO $conn La connexion à la base de données
     * @param string|null $groupe_parametre Le groupe_parametre de paramètres (obligatoire si $sous_groupe_parametre est spécifié)
     * @param string|null $sous_groupe_parametre Le sous-groupe_parametre de paramètres (obligatoire si $categorie est spécifiée)
     * @param string|null $categorie La catégorie de paramètres
     * @return array Structure de paramètres selon le format défini
     * @throws Exception Si les contraintes de hiérarchie ne sont pas respectées
     */
    public static function getParametres($conn, $groupe_parametre = null, $sous_groupe_parametre = null, $categorie = null)
    {
        // Validation des contraintes de hiérarchie
        if ($sous_groupe_parametre !== null && $groupe_parametre === null) {
            throw new Exception("Le groupe_parametre doit être spécifié lorsqu'un sous-groupe_parametre est fourni");
        }
        
        if ($categorie !== null && ($groupe_parametre === null || $sous_groupe_parametre === null)) {
            throw new Exception("Le groupe_parametre et le sous-groupe_parametre doivent être spécifiés lorsqu'une catégorie est fournie");
        }
        
        try {
            // Construire la requête SQL de base
            $sql = "SELECT id, nom_parametre, valeur_parametre, 
                    groupe_parametre, sous_groupe_parametre, categorie 
                    FROM parametres 
                    WHERE 1=1";
            
            $params = [];
            
            // Ajouter les filtres selon les paramètres fournis
            if ($groupe_parametre !== null) {
                $sql .= " AND groupe_parametre = ?";
                $params[] = $groupe_parametre;
                
                if ($sous_groupe_parametre !== null) {
                    $sql .= " AND sous_groupe_parametre = ?";
                    $params[] = $sous_groupe_parametre;
                    
                    if ($categorie !== null) {
                        $sql .= " AND categorie = ?";
                        $params[] = $categorie;
                    }
                }
            }
            
            // Ajouter les tris pour garantir un ordre cohérent
            $sql .= " ORDER BY groupe_parametre ASC, sous_groupe_parametre ASC, categorie ASC, nom_parametre ASC";
            
            // Exécuter la requête
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            $parametres = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Transformer les résultats dans la structure attendue
            $result = [];
            
            foreach ($parametres as $parametre) {
                $grp = $parametre['groupe_parametre'];
                $sgrp = $parametre['sous_groupe_parametre'] ?: 'Général';
                $cat = $parametre['categorie'] ?: 'Default';
                
                // Initialiser la structure si nécessaire
                if (!isset($result[$grp])) {
                    $result[$grp] = [];
                }
                
                if (!isset($result[$grp][$sgrp])) {
                    $result[$grp][$sgrp] = [];
                }
                
                if (!isset($result[$grp][$sgrp][$cat])) {
                    $result[$grp][$sgrp][$cat] = [];
                }
                
                // Ajouter le paramètre à la structure
                $result[$grp][$sgrp][$cat][] = $parametre;
            }

            // Normaliser la structure pour garantir une cohérence
            // Assurer que toutes les catégories sont des tableaux
            foreach ($result as $groupe_parametre => &$sousGroupes) {
                foreach ($sousGroupes as $sous_groupe_parametre => &$categories) {
                    // Vérifier si nous avons des catégories qui ne sont pas des tableaux de paramètres
                    foreach ($categories as $categorie => &$params) {
                        if (!is_array($params)) {
                            // Si ce n'est pas un tableau, le convertir en tableau
                            $params = [$params];
                        }
                    }
                }
            }

            return [
                'success' => true,
                'parametres' => $result
            ];
            
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des paramètres: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération des paramètres: ' . $e->getMessage());
        }
    }

    /**
     * Enregistre ou met à jour un paramètre avec support des groupes, sous-groupes et catégories
     * 
     * @param PDO $conn La connexion à la base de données
     * @param array $data Les données du paramètre
     * @return array Résultat de l'opération
     * @throws Exception En cas d'erreur
     */
    public static function enregistrerParametres($conn, $data) {
        try {

            error_log("ParametreControleur - enregistrerParametres - data: ". json_encode($data));
            
            // Validation des données obligatoires
            if (!isset($data['valeur_parametre']) || !isset($data['nom_parametre']) || !isset($data['groupe_parametre'])) {
                throw new Exception('Données de paramètres incomplètes (valeur_parametre, nom_parametre et groupe_parametre sont obligatoires)');
            }
            
            // Validation spécifique pour certains paramètres
            if ($data['nom_parametre'] === 'Prochain Numéro Facture' && !isset($data['annee_parametre'])) {
                throw new Exception('L\'année est requise pour le paramètre Prochain Numéro Facture');
            }
            
            // Convertir et valider les entrées
            $valeur_parametre = strval($data['valeur_parametre']);
            $annee_parametre = isset($data['annee_parametre']) ? intval($data['annee_parametre']) : null;
            $nom_parametre = $data['nom_parametre'];
            $groupe_parametre = $data['groupe_parametre'];
            $sous_groupe_parametre = isset($data['sous_groupe_parametre']) ? $data['sous_groupe_parametre'] : null;
            $categorie = isset($data['categorie']) ? $data['categorie'] : null;
            
            // Construction de la requête de vérification d'existence
            $checkSql = "SELECT id FROM parametres WHERE nom_parametre = ? AND groupe_parametre = ?";
            $checkParams = [$nom_parametre, $groupe_parametre];
            
            // Ajouter les filtres optionnels pour la vérification
            if ($sous_groupe_parametre !== null) {
                $checkSql .= " AND sous_groupe_parametre = ?";
                $checkParams[] = $sous_groupe_parametre;
            } else {
                $checkSql .= " AND (sous_groupe_parametre IS NULL OR sous_groupe_parametre = '')";
            }
            
            if ($categorie !== null) {
                $checkSql .= " AND categorie = ?";
                $checkParams[] = $categorie;
            } else {
                $checkSql .= " AND (categorie IS NULL OR categorie = '')";
            }
            
            if ($annee_parametre !== null) {
                $checkSql .= " AND annee_parametre = ?";
                $checkParams[] = $annee_parametre;
            } else {
                $checkSql .= " AND (annee_parametre IS NULL OR annee_parametre = 0)";
            }
            
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute($checkParams);

            error_log("Requête de vérification d'existence: " . $checkSql . " - Paramètres: " . json_encode($checkParams)); // Log pour le débogage
            
            if ($checkStmt->rowCount() > 0) {
                // Mise à jour d'un paramètre existant
                $sql = "UPDATE parametres SET valeur_parametre = ? WHERE nom_parametre = ? AND groupe_parametre = ?";
                $params = [$valeur_parametre, $nom_parametre, $groupe_parametre];
                
                // Ajouter les filtres optionnels pour l'update
                if ($sous_groupe_parametre !== null) {
                    $sql .= " AND sous_groupe_parametre = ?";
                    $params[] = $sous_groupe_parametre;
                } else {
                    $sql .= " AND (sous_groupe_parametre IS NULL OR sous_groupe_parametre = '')";
                }
                
                if ($categorie !== null) {
                    $sql .= " AND categorie = ?";
                    $params[] = $categorie;
                } else {
                    $sql .= " AND (categorie IS NULL OR categorie = '')";
                }
                
                if ($annee_parametre !== null) {
                    $sql .= " AND annee_parametre = ?";
                    $params[] = $annee_parametre;
                } else {
                    $sql .= " AND (annee_parametre IS NULL OR annee_parametre = 0)";
                }
                
                error_log("Requête de mise à jour: " . $sql . " - Paramètres: " . json_encode($params)); // Log pour le débogage
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                
            } else {
                // Insertion d'un nouveau paramètre
                $sql = "INSERT INTO parametres (nom_parametre, valeur_parametre, annee_parametre, groupe_parametre, sous_groupe_parametre, categorie) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$nom_parametre, $valeur_parametre, $annee_parametre, $groupe_parametre, $sous_groupe_parametre, $categorie]);
            }
            
            
            return [
                'success' => true,
                'message' => 'Paramètre enregistré avec succès'
            ];
            error_log("Paramètre enregistré avec succès: " . json_encode($data)); // Log pour le débogage
            
        } catch (PDOException $e) {
            error_log("Erreur lors de l'enregistrement des paramètres: " . $e->getMessage());
            throw new Exception('Erreur lors de l\'enregistrement des paramètres: ' . $e->getMessage());
        }
    }
}
?>