<?php
// ServiceParametre.php

require_once realpath(__DIR__ . '/../controllers/ParametreControleur.php');

class ServiceParametre {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Récupère tous les paramètres organisés par groupe
     * @param string|null $groupeParametre Groupe spécifique à récupérer (optionnel)
     * @return array Paramètres organisés par groupe
     */
    public function getParametresParGroupe($groupeParametre = null) {
        try {
            $parametres = ParametreControleur::getParametresParGroupe($this->conn, $groupeParametre);
            
            // Normalisation supplémentaire si nécessaire
            $normalizedParams = $this->normalizeParametersStructure($parametres['parametres']);
            
            return [
                'success' => true,
                'parametres' => $normalizedParams
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des paramètres par groupe: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Normalise la structure des paramètres pour garantir une cohérence
     * @param array $params Structure de paramètres à normaliser
     * @return array Structure normalisée
     */
    private function normalizeParametersStructure($params) {
        foreach ($params as $groupe => &$sousGroupes) {
            foreach ($sousGroupes as $sousGroupe => &$categories) {
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
     * Récupère les paramètres par groupe et sous-groupe
     * @param string $groupeParametre Groupe de paramètres
     * @param string $sGroupeParametre Sous-groupe de paramètres
     * @return array Paramètres organisés par catégorie
     */
    public function getParametresParSousGroupe($groupeParametre, $sGroupeParametre) {
        try {
            $parametres = ParametreControleur::getParametresParSousGroupe($this->conn, $groupeParametre, $sGroupeParametre);
            
            return [
                'success' => true,
                'parametres' => $parametres['parametres']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des paramètres par sous-groupe: ' . $e->getMessage()
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
     * @param string $nomParametre Nom du paramètre
     * @param string $groupeParametre Groupe du paramètre (obligatoire)
     * @param string|null $sGroupeParametre Sous-groupe du paramètre (optionnel)
     * @param string|null $categorie Catégorie du paramètre (optionnel)
     * @param int|null $annee Année du paramètre (optionnel)
     * @return array Paramètre trouvé
     */
    public function getParametre($nomParametre, $groupeParametre, $sGroupeParametre = null, $categorie = null, $annee = null) {
        try {
            // Valider les paramètres obligatoires
            if (empty($nomParametre) || empty($groupeParametre)) {
                return [
                    'success' => false,
                    'message' => 'Le nom et le groupe du paramètre sont obligatoires'
                ];
            }

            
            $resultat = ParametreControleur::getParametre(
                $this->conn, 
                $nomParametre, 
                $groupeParametre, 
                $sGroupeParametre, 
                $categorie, 
                $annee
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
     * @deprecated Utiliser getParametre avec le groupe en paramètre à la place
     */
    public function getParametreSimple($annee, $nomParametre) {
        try {
            $parametres = ParametreControleur::getParametres($this->conn, $nomParametre, $annee);
            
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
     * @return array Tous les tarifs disponibles dans le groupe "Tarifs"
     */
    public function getAllTarifs() {
        return $this->getParametresParGroupe('Tarifs');
    }
    

    /**
     * Enregistre ou met à jour un paramètre
     * @param array $data Données du paramètre (nomParametre, valeurParametre, groupeParametre obligatoires)
     * @return array Résultat de l'opération
     */
    public function enregistrerParametre($data) {
        try {
            // Vérification des données requises
            if (!isset($data['valeurParametre']) || !isset($data['nomParametre']) || !isset($data['groupeParametre'])) {
                return [
                    'success' => false,
                    'message' => 'Données de paramètres incomplètes (valeurParametre, nomParametre et groupeParametre sont obligatoires)'
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
     * @param int $annee Année pour laquelle récupérer le prochain numéro
     * @return array Résultat de l'opération
     */
    public function getProchainNumeroFacture($annee) {
        try {
            return ParametreControleur::getProchainNumeroFacture($this->conn, $annee) ?? null;
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
     * @param string|null $groupe Le groupe de paramètres (obligatoire si $sousGroupe est spécifié)
     * @param string|null $sousGroupe Le sous-groupe de paramètres (obligatoire si $categorie est spécifiée)
     * @param string|null $categorie La catégorie de paramètres
     * @return array Paramètres correspondant aux critères spécifiés
     */
    public function getParametres($groupe = null, $sousGroupe = null, $categorie = null) {
        try {
            // Validation des contraintes de hiérarchie
            if ($sousGroupe !== null && $groupe === null) {
                return [
                    'success' => false,
                    'message' => 'Le groupe doit être spécifié lorsqu\'un sous-groupe est fourni'
                ];
            }
            
            if ($categorie !== null && ($groupe === null || $sousGroupe === null)) {
                return [
                    'success' => false,
                    'message' => 'Le groupe et le sous-groupe doivent être spécifiés lorsqu\'une catégorie est fournie'
                ];
            }
            
            // Appel au contrôleur
            return ParametreControleur::getParametres($this->conn, $groupe, $sousGroupe, $categorie);
                                   
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des paramètres: ' . $e->getMessage()
            ];
        }
    }
}
?>