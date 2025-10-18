<?php
// ServiceParametre.php

require_once realpath(__DIR__ . '/../controllers/ParametreControleur.php');

class ServiceParametre {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Récupère tous les paramètres organisés par groupe_parametre
     * @param string|null $groupe_parametre groupe_parametre spécifique à récupérer (optionnel)
     * @return array Paramètres organisés par groupe_parametre
     */
    public function getParametresParGroupe($groupe_parametre = null) {
        try {
            $parametres = ParametreControleur::getParametresParGroupe($this->conn, $groupe_parametre);
            
            // Normalisation supplémentaire si nécessaire
            $normalizedParams = $this->normalizeParametersStructure($parametres['parametres']);
            
            return [
                'success' => true,
                'parametres' => $normalizedParams
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des paramètres par groupe_parametre: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Normalise la structure des paramètres pour garantir une cohérence
     * @param array $params Structure de paramètres à normaliser
     * @return array Structure normalisée
     */
    private function normalizeParametersStructure($params) {
        foreach ($params as $groupe_parametre => &$sousGroupes) {
            foreach ($sousGroupes as $sous_groupe_parametre => &$categories) {
                foreach ($categories as $categorie => &$parametres) {
                    // S'assurer que les paramètres sont toujours un tableau
                    if (!is_array($parametres)) {
                        $parametres = [$parametres];
                    }
                }
            }
        }
        return $params;
    }    

    /**
     * Récupère les paramètres par groupe_parametre et sous-groupe_parametre
     * @param string $groupe_parametre groupe_parametre de paramètres
     * @param string $sous_groupe_parametre Sous-groupe_parametre de paramètres
     * @return array Paramètres organisés par catégorie
     */
    public function getParametresParSousGroupe($groupe_parametre, $sous_groupe_parametre) {
        try {
            $parametres = ParametreControleur::getParametresParSousGroupe($this->conn, $groupe_parametre, $sous_groupe_parametre);
            
            return [
                'success' => true,
                'parametres' => $parametres['parametres']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des paramètres par sous-groupe_parametre: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Récupère spécifiquement les tarifs de location de salle
     * @return array Tarifs de location de salle organisés par catégorie
     */
    public function getTarifsLocationSalle() {
        return $this->getParametresParSousGroupe('Tarifs', 'Location Salle');
    }

    /**
     * Récupère un paramètre spécifique
     * 
     * @param string $nom_parametre Nom du paramètre
     * @param string $groupe_parametre groupe_parametre du paramètre (obligatoire)
     * @param string|null $sous_groupe_parametre Sous-groupe_parametre du paramètre (optionnel)
     * @param string|null $categorie Catégorie du paramètre (optionnel)
     * @param int|null $annee_parametre Année du paramètre (optionnel)
     * @return array Paramètre trouvé
     */
    public function getParametre($nom_parametre, $groupe_parametre, $sous_groupe_parametre = null, $categorie = null, $annee_parametre = null) {
        try {
            // Valider les paramètres obligatoires
            if (empty($nom_parametre) || empty($groupe_parametre)) {
                return [
                    'success' => false,
                    'message' => 'Le nom et le groupe_parametre du paramètre sont obligatoires'
                ];
            }

            
            $resultat = ParametreControleur::getParametre(
                $this->conn, 
                $nom_parametre, 
                $groupe_parametre, 
                $sous_groupe_parametre, 
                $categorie, 
                $annee_parametre
            );
            
           return $resultat;
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération du paramètre: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Cette méthode est maintenue pour rétrocompatibilité
     * @deprecated Utiliser getParametre avec le groupe_parametre en paramètre à la place
     */
    public function getParametreSimple($annee_parametre, $nom_parametre) {
        try {
            $parametres = ParametreControleur::getParametres($this->conn, $nom_parametre, $annee_parametre);
            
            return [
                'success' => true,
                'parametres' => $parametres
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des paramètres: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Récupère TOUS les tarifs (pas seulement ceux de location de salle)
     * @return array Tous les tarifs disponibles dans le groupe_parametre "Tarifs"
     */
    public function getAllTarifs() {
        return $this->getParametresParGroupe('Tarifs');
    }
    

    /**
     * Enregistre ou met à jour un paramètre
     * @param array $data Données du paramètre (nom_parametre, valeur_parametre, groupe_parametre obligatoires)
     * @return array Résultat de l'opération
     */
    public function enregistrerParametre($data) {
        try {
            // Vérification des données requises
            if (!isset($data['valeur_parametre']) || !isset($data['nom_parametre']) || !isset($data['groupe_parametre'])) {
                return [
                    'success' => false,
                    'message' => 'Données de paramètres incomplètes (valeur_parametre, nom_parametre et groupe_parametre sont obligatoires)'
                ];
            }
            
            $resultat = ParametreControleur::enregistrerParametres($this->conn, $data);
            
            return $resultat;
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement des paramètres: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Récupère le prochain numéro de facture pour une année donnée
     * @param int $annee_parametre Année pour laquelle récupérer le prochain numéro
     * @return array Résultat de l'opération
     */
    public function getProchainNumeroFacture($annee_parametre) {
        try {
            return ParametreControleur::getProchainNumeroFacture($this->conn, $annee_parametre) ?? null;
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération du prochain numéro de facture: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Récupère les paramètres selon les critères spécifiés
     * 
     * @param string|null $groupe_parametre Le groupe_parametre de paramètres (obligatoire si $sous_groupe_parametre est spécifié)
     * @param string|null $sous_groupe_parametre Le sous-groupe_parametre de paramètres (obligatoire si $categorie est spécifiée)
     * @param string|null $categorie La catégorie de paramètres
     * @return array Paramètres correspondant aux critères spécifiés
     */
    public function getParametres($groupe_parametre = null, $sous_groupe_parametre = null, $categorie = null) {
        try {
            // Validation des contraintes de hiérarchie
            if ($sous_groupe_parametre !== null && $groupe_parametre === null) {
                return [
                    'success' => false,
                    'message' => 'Le groupe_parametre doit être spécifié lorsqu\'un sous-groupe_parametre est fourni'
                ];
            }
            
            if ($categorie !== null && ($groupe_parametre === null || $sous_groupe_parametre === null)) {
                return [
                    'success' => false,
                    'message' => 'Le groupe_parametre et le sous-groupe_parametre doivent être spécifiés lorsqu\'une catégorie est fournie'
                ];
            }
            
            // Appel au contrôleur
            return ParametreControleur::getParametres($this->conn, $groupe_parametre, $sous_groupe_parametre, $categorie);
                                   
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des paramètres: ' . $e->getMessage()
            ];
        }
    }
}
?>