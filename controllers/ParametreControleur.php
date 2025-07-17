<?php

class ParametreControleur {
    // Fonction pour récupérer les paramètres par groupe
    public static function getParametresParGroupe($conn, $groupeParametre = null) {
        try {
            // Requête pour récupérer tous les paramètres (inchangée)
            $sql = $groupeParametre === null 
                ? "SELECT 
                    Nom_parametre, 
                    Valeur_parametre, 
                    Annee_parametre, 
                    Groupe_parametre, 
                    sGroupe_parametre, 
                    Categorie 
                FROM parametres 
                ORDER BY Groupe_parametre, sGroupe_parametre, Categorie, Nom_parametre"
                : "SELECT 
                    Nom_parametre, 
                    Valeur_parametre, 
                    Annee_parametre, 
                    Groupe_parametre, 
                    sGroupe_parametre, 
                    Categorie 
                FROM parametres 
                WHERE Groupe_parametre = ? 
                ORDER BY sGroupe_parametre, Categorie, Nom_parametre";
            
            $stmt = $conn->prepare($sql);
            $groupeParametre === null ? $stmt->execute() : $stmt->execute([$groupeParametre]);
            
            $parametres = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Structure hiérarchique : Groupe > Sous-groupe > Catégorie > Paramètres
            $parametresParGroupe = [];
            
            foreach ($parametres as $parametre) {
                $groupe = $parametre['Groupe_parametre'];
                $sousGroupe = $parametre['sGroupe_parametre'] ?: 'Général';
                $categorie = $parametre['Categorie'] ?: 'Default';
                
                // Initialiser le groupe s'il n'existe pas
                if (!isset($parametresParGroupe[$groupe])) {
                    $parametresParGroupe[$groupe] = [];
                }
                
                // Ajouter le sous-groupe s'il n'existe pas
                if (!isset($parametresParGroupe[$groupe][$sousGroupe])) {
                    $parametresParGroupe[$groupe][$sousGroupe] = [];
                }
                
                // Ajouter la catégorie si elle n'existe pas
                if (!isset($parametresParGroupe[$groupe][$sousGroupe][$categorie])) {
                    $parametresParGroupe[$groupe][$sousGroupe][$categorie] = [];
                }
                
                // Ajouter le paramètre à la structure
                $parametresParGroupe[$groupe][$sousGroupe][$categorie][] = $parametre;
            }
            
            // Normaliser la structure pour garantir une cohérence
            // Assurer que toutes les catégories sont des tableaux
            foreach ($parametresParGroupe as $groupe => &$sousGroupes) {
                foreach ($sousGroupes as $sousGroupe => &$categories) {
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
            error_log("Erreur lors de la récupération des paramètres par groupe: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération des paramètres par groupe');
        }
    }

    // Fonction pour récupérer les paramètres par groupe ET sous-groupe
    public static function getParametresParSousGroupe($conn, $groupeParametre, $sGroupeParametre) {
        try {
            $sql = "SELECT Nom_parametre, Valeur_parametre, Annee_parametre, Groupe_parametre, sGroupe_parametre, Categorie 
                    FROM parametres 
                    WHERE Groupe_parametre = ? AND sGroupe_parametre = ? 
                    ORDER BY Nom_parametre";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$groupeParametre, $sGroupeParametre]);
            
            $parametres = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Organiser les paramètres par catégorie
            $parametresParCategorie = [];
            foreach ($parametres as $parametre) {
                $categorie = $parametre['Categorie'] ?? 'Default';
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
            error_log("Erreur lors de la récupération des paramètres par sous-groupe: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération des paramètres par sous-groupe');
        }
    }

    // Fonction pour récupérer le prochain numéro de facture pour une année donnée
    public static function getProchainNumeroFacture($conn, $annee) {
        try {
            
            $sql = "SELECT Valeur_parametre FROM parametres 
                    WHERE Nom_parametre = 'Prochain Numéro Facture' 
                    AND Annee_parametre = ? 
                    AND Groupe_parametre = 'Facture' 
                    AND sGroupe_parametre = 'Numéro' 
                    LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$annee]);
            
            $resultat = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'parametres' => $resultat ? $resultat['Valeur_parametre'] : null,
                'message' => $resultat ? 'Prochain numéro de facture récupéré avec succès' : 'Aucun numéro de facture trouvé pour cette année'
            ];
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération du prochain numéro de facture: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération du prochain numéro de facture');
        }
    }    

    /**
     * Récupère un paramètre spécifique avec filtrage par groupe, sous-groupe, catégorie
     * 
     * @param PDO $conn La connexion à la base de données
     * @param string $nomParametre Nom du paramètre à récupérer
     * @param string $groupeParametre Groupe du paramètre (obligatoire)
     * @param string|null $sGroupeParametre Sous-groupe du paramètre (optionnel)
     * @param string|null $categorie Catégorie du paramètre (optionnel)
     * @param int|null $annee Année spécifique du paramètre (optionnel)
     * @return array Paramètre trouvé ou null
     * @throws Exception En cas d'erreur
     */
    public static function getParametre($conn, $nomParametre, $groupeParametre, $sGroupeParametre = null, $categorie = null, $annee = null) {
        try {
            error_log("Récupération du paramètre: nomParametre = $nomParametre, groupeParametre = $groupeParametre, sGroupeParametre = $sGroupeParametre, categorie = $categorie, annee = $annee"); // Log pour le débogage
            // Vérification des paramètres obligatoires
            if (!isset($nomParametre) || !isset($groupeParametre)) {
                error_log("Erreur : Les paramètres nomParametre et groupeParametre sont obligatoires");
                throw new Exception('Erreur : Les paramètres nomParametre et groupeParametre sont obligatoires');
            }

            // Construction de la requête SQL de base
            $sql = "SELECT Nom_parametre, Valeur_parametre, Annee_parametre, Groupe_parametre, sGroupe_parametre, Categorie 
                    FROM parametres 
                    WHERE Nom_parametre = ? AND Groupe_parametre = ?";
            $params = [$nomParametre, $groupeParametre];
            
            // Ajouter les filtres optionnels
            if ($sGroupeParametre !== null) {
                $sql .= " AND sGroupe_parametre = ?";
                $params[] = $sGroupeParametre;
            }
            
            if ($categorie !== null) {
                $sql .= " AND Categorie = ?";
                $params[] = $categorie;
            }
            
            if ($annee !== null) {
                $sql .= " AND Annee_parametre = ?";
                $params[] = $annee;
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
     * Si le groupe est présent uniquement, la fonction retourne tous les paramètres de ce groupe.
     * Si un sous-groupe est présent, alors le groupe doit être présent obligatoirement.
     * Si une categorie est présente alors le sous-groupe et le groupe doivent obligatoirement être présents.
     * 
     * @param PDO $conn La connexion à la base de données
     * @param string|null $groupe Le groupe de paramètres (obligatoire si $sousGroupe est spécifié)
     * @param string|null $sousGroupe Le sous-groupe de paramètres (obligatoire si $categorie est spécifiée)
     * @param string|null $categorie La catégorie de paramètres
     * @return array Structure de paramètres selon le format défini
     * @throws Exception Si les contraintes de hiérarchie ne sont pas respectées
     */
    public static function getParametres($conn, $groupe = null, $sousGroupe = null, $categorie = null)
    {
        // Validation des contraintes de hiérarchie
        if ($sousGroupe !== null && $groupe === null) {
            throw new Exception("Le groupe doit être spécifié lorsqu'un sous-groupe est fourni");
        }
        
        if ($categorie !== null && ($groupe === null || $sousGroupe === null)) {
            throw new Exception("Le groupe et le sous-groupe doivent être spécifiés lorsqu'une catégorie est fournie");
        }
        
        try {
            // Construire la requête SQL de base
            $sql = "SELECT id, Nom_parametre, Valeur_parametre, 
                    Groupe_parametre, sGroupe_parametre, Categorie 
                    FROM parametres 
                    WHERE 1=1";
            
            $params = [];
            
            // Ajouter les filtres selon les paramètres fournis
            if ($groupe !== null) {
                $sql .= " AND Groupe_parametre = ?";
                $params[] = $groupe;
                
                if ($sousGroupe !== null) {
                    $sql .= " AND sGroupe_parametre = ?";
                    $params[] = $sousGroupe;
                    
                    if ($categorie !== null) {
                        $sql .= " AND Categorie = ?";
                        $params[] = $categorie;
                    }
                }
            }
            
            // Ajouter les tris pour garantir un ordre cohérent
            $sql .= " ORDER BY Groupe_parametre ASC, sGroupe_parametre ASC, Categorie ASC, Nom_parametre ASC";
            
            // Exécuter la requête
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            $parametres = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Transformer les résultats dans la structure attendue
            $result = [];
            
            foreach ($parametres as $parametre) {
                $grp = $parametre['Groupe_parametre'];
                $sgrp = $parametre['sGroupe_parametre'] ?: 'Général';
                $cat = $parametre['Categorie'] ?: 'Default';
                
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
            foreach ($result as $groupe => &$sousGroupes) {
                foreach ($sousGroupes as $sousGroupe => &$categories) {
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
            
            // Validation des données obligatoires
            if (!isset($data['valeurParametre']) || !isset($data['nomParametre']) || !isset($data['groupeParametre'])) {
                throw new Exception('Données de paramètres incomplètes (valeurParametre, nomParametre et groupeParametre sont obligatoires)');
            }
            
            // Validation spécifique pour certains paramètres
            if ($data['nomParametre'] === 'Prochain Numéro Facture' && !isset($data['annee'])) {
                throw new Exception('L\'année est requise pour le paramètre Prochain Numéro Facture');
            }
            
            // Convertir et valider les entrées
            $valeurParametre = strval($data['valeurParametre']);
            $annee = isset($data['annee']) ? intval($data['annee']) : null;
            $nomParametre = $data['nomParametre'];
            $groupeParametre = $data['groupeParametre'];
            $sGroupeParametre = isset($data['sGroupeParametre']) ? $data['sGroupeParametre'] : null;
            $categorie = isset($data['categorie']) ? $data['categorie'] : null;
            
            // Construction de la requête de vérification d'existence
            $checkSql = "SELECT id FROM parametres WHERE Nom_parametre = ? AND Groupe_parametre = ?";
            $checkParams = [$nomParametre, $groupeParametre];
            
            // Ajouter les filtres optionnels pour la vérification
            if ($sGroupeParametre !== null) {
                $checkSql .= " AND sGroupe_parametre = ?";
                $checkParams[] = $sGroupeParametre;
            } else {
                $checkSql .= " AND (sGroupe_parametre IS NULL OR sGroupe_parametre = '')";
            }
            
            if ($categorie !== null) {
                $checkSql .= " AND Categorie = ?";
                $checkParams[] = $categorie;
            } else {
                $checkSql .= " AND (Categorie IS NULL OR Categorie = '')";
            }
            
            if ($annee !== null) {
                $checkSql .= " AND Annee_parametre = ?";
                $checkParams[] = $annee;
            } else {
                $checkSql .= " AND (Annee_parametre IS NULL OR Annee_parametre = 0)";
            }
            
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute($checkParams);

            error_log("Requête de vérification d'existence: " . $checkSql . " - Paramètres: " . json_encode($checkParams)); // Log pour le débogage
            
            if ($checkStmt->rowCount() > 0) {
                // Mise à jour d'un paramètre existant
                $sql = "UPDATE parametres SET Valeur_parametre = ? WHERE Nom_parametre = ? AND Groupe_parametre = ?";
                $params = [$valeurParametre, $nomParametre, $groupeParametre];
                
                // Ajouter les filtres optionnels pour l'update
                if ($sGroupeParametre !== null) {
                    $sql .= " AND sGroupe_parametre = ?";
                    $params[] = $sGroupeParametre;
                } else {
                    $sql .= " AND (sGroupe_parametre IS NULL OR sGroupe_parametre = '')";
                }
                
                if ($categorie !== null) {
                    $sql .= " AND Categorie = ?";
                    $params[] = $categorie;
                } else {
                    $sql .= " AND (Categorie IS NULL OR Categorie = '')";
                }
                
                if ($annee !== null) {
                    $sql .= " AND Annee_parametre = ?";
                    $params[] = $annee;
                } else {
                    $sql .= " AND (Annee_parametre IS NULL OR Annee_parametre = 0)";
                }
                
                error_log("Requête de mise à jour: " . $sql . " - Paramètres: " . json_encode($params)); // Log pour le débogage
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                
            } else {
                // Insertion d'un nouveau paramètre
                $sql = "INSERT INTO parametres (Nom_parametre, Valeur_parametre, Annee_parametre, Groupe_parametre, sGroupe_parametre, Categorie) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$nomParametre, $valeurParametre, $annee, $groupeParametre, $sGroupeParametre, $categorie]);
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