<?php
// ServiceTarif.php

require_once 'TarifControleur.php';

class ServiceTarif {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Récupère tous les services
     * @param bool $actif Filtre sur les services actifs uniquement
     * @return array Liste des services
     */
    public function getServices($actif = true) {
        try {
            $services = TarifControleur::getServices($this->conn, $actif);
            
            return [
                'success' => true,
                'services' => $services
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des services: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Récupère un service par son ID
     * @param int $id ID du service
     * @return array Service trouvé ou erreur
     */
    public function getServiceById($id) {
        try {
            $service = TarifControleur::getServiceById($this->conn, $id);
            
            if ($service) {
                return [
                    'success' => true,
                    'service' => $service
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Service non trouvé'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération du service: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Crée un nouveau service
     * @param array $data Données du service
     * @return array Résultat de l'opération
     */
    public function createService($data) {
        try {
            $id = TarifControleur::createService($this->conn, $data);
            
            return [
                'success' => true,
                'message' => 'Service créé avec succès',
                'id' => $id
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la création du service: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Met à jour un service existant
     * @param int $id ID du service
     * @param array $data Données mises à jour
     * @return array Résultat de l'opération
     */
    public function updateService($id, $data) {
        try {
            $result = TarifControleur::updateService($this->conn, $id, $data);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Service mis à jour avec succès'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Aucune modification effectuée ou service non trouvé'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du service: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Supprime un service
     * @param int $id ID du service
     * @return array Résultat de l'opération
     */
    public function deleteService($id) {
        try {
            $result = TarifControleur::deleteService($this->conn, $id);
            
            return $result;
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la suppression du service: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Récupère toutes les unités ou celles d'un service spécifique
     * @param int|null $serviceId ID du service (optionnel)
     * @return array Liste des unités
     */
    public function getUnites($serviceId = null) {
        try {
            if ($serviceId !== null) {
                // Récupérer les unités pour un service spécifique
                $unites = TarifControleur::getUnitesByService($this->conn, $serviceId);
            } else {
                // Récupérer toutes les unités
                $unites = TarifControleur::getUnites($this->conn);
            }
            
            // Retourner les unités sous forme de tableau plat
            return [
                'success' => true,
                'unites' => $unites // Toujours un tableau plat
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des unités: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Récupère toutes les relations entre services et unités
     * @return array Liste des relations services-unités
     */
    public function getServicesUnites() {
        try {
            $relations = TarifControleur::getServicesUnites($this->conn);
            
            return [
                'success' => true,
                'servicesUnites' => $relations
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des relations services-unités: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Crée une nouvelle unité
     * @param array $data Données de l'unité
     * @return array Résultat de l'opération
     */
    public function createUnite($data) {
        try {
            $id = TarifControleur::createUnite($this->conn, $data);
            
            return [
                'success' => true,
                'message' => 'Unité créée avec succès',
                'id' => $id
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la création de l\'unité: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Met à jour une unité existante
     * @param int $id ID de l'unité
     * @param array $data Données mises à jour
     * @return array Résultat de l'opération
     */
    public function updateUnite($id, $data) {
        try {
            $result = TarifControleur::updateUnite($this->conn, $id, $data);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Unité mise à jour avec succès'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Aucune modification effectuée ou unité non trouvée'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de l\'unité: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Supprime une unité
     * @param int $id ID de l'unité
     * @return array Résultat de l'opération
     */
    public function deleteUnite($id) {
        try {
            $result = TarifControleur::deleteUnite($this->conn, $id);
            
            return $result;
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la suppression de l\'unité: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Associe une unité à un service
     * @param int $serviceId ID du service
     * @param int $uniteId ID de l'unité
     * @return array Résultat de l'opération
     */
    public function linkServiceUnite($serviceId, $uniteId) {
        try {
            $result = TarifControleur::linkServiceUnite($this->conn, $serviceId, $uniteId);
            
            return [
                'success' => true,
                'message' => 'Association service-unité créée avec succès'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'association service-unité: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Dissocie une unité d'un service
     * @param int $serviceId ID du service
     * @param int $uniteId ID de l'unité
     * @return array Résultat de l'opération
     */
    public function unlinkServiceUnite($serviceId, $uniteId) {
        try {
            
            $result = TarifControleur::unlinkServiceUnite($this->conn, $serviceId, $uniteId);
            
            
            return $result;
        } catch (Exception $e) {
            error_log('Erreur dans unlinkServiceUnite: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la dissociation: ' . $e->getMessage()
            ];
        }
    }

    public function updateServiceUniteDefault($serviceId, $uniteId) {
        try {
            $result = TarifControleur::updateServiceUniteDefault($this->conn, $serviceId, $uniteId);
            return $result;
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de l\'unité par défaut: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtient l'unité par défaut pour un service
     * @param int $serviceId ID du service
     * @return array Résultat contenant l'ID de l'unité par défaut
     */
    public function getUniteDefault($serviceId) {
        try {
            $result = TarifControleur::getUniteDefautPourService($this->conn, $serviceId);
            return $result;
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'unité par défaut: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Vérifie si un service est utilisé dans des factures ou des tarifs
     * @param int $id ID du service
     * @return array Résultat de la vérification
     */
    public function checkServiceUsage($id) {
        try {
            $result = TarifControleur::checkServiceUsage($this->conn, $id);
            
            return $result;
        } catch (Exception $e) {
            return [
            'success' => false,
            'message' => 'Erreur lors de la vérification de l\'utilisation du service: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Vérifie si une liaison service-unité est utilisée dans des factures
     * @param int $serviceId ID du service
     * @param int $uniteId ID de l'unité
     * @return array Résultat de la vérification
     */
    public function checkServiceUniteUsageInFacture($serviceId, $uniteId) {
        try {
            $result = TarifControleur::checkServiceUniteUsageInFacture($this->conn, $serviceId, $uniteId);
            
            return $result;
        } catch (Exception $e) {
            return [
            'success' => false,
            'message' => 'Erreur lors de la vérification de l\'utilisation de la liaison dans les factures: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Vérifie si un type de tarif est utilisé dans des tarifs
     * @param int $id ID du type de tarif
     * @return array Résultat de la vérification
     */
    public function checkTypeTarifUsage($id) {
    try {
        $result = TarifControleur::checkTypeTarifUsage($this->conn, $id);
        
        return $result;
    } catch (Exception $e) {
        return [
        'success' => false,
        'message' => 'Erreur lors de la vérification de l\'utilisation du type de tarif: ' . $e->getMessage()
        ];
    }
    }

    /**
     * Vérifie si une unité est utilisée dans des factures ou des tarifs
     * @param int $id ID de l'unité
     * @return array Résultat de la vérification
     */
    public function checkUniteUsage($id) {
        try {
            $result = TarifControleur::checkUniteUsage($this->conn, $id);
            
            return $result;
        } catch (Exception $e) {
            return [
            'success' => false,
            'message' => 'Erreur lors de la vérification de l\'utilisation de l\'unité: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Vérifie si un tarif standard est utilisé dans des factures
     * @param int $id ID du tarif standard
     * @return array Résultat de la vérification
     */
    public function checkTarifUsage($id) {
        try {
            $result = TarifControleur::checkTarifUsage($this->conn, $id);
            
            return $result;
        } catch (Exception $e) {
            return [
            'success' => false,
            'message' => 'Erreur lors de la vérification de l\'utilisation du tarif: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Vérifie si un tarif spécial est utilisé dans des factures
     * @param int $id ID du tarif spécial
     * @return array Résultat de la vérification
     */
    public function checkTarifSpecialUsage($id) {
        try {
            $result = TarifControleur::checkTarifSpecialUsage($this->conn, $id);
            
            return $result;
        } catch (Exception $e) {
            return [
            'success' => false,
            'message' => 'Erreur lors de la vérification de l\'utilisation du tarif spécial: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Récupère tous les types de tarifs
     * @return array Liste des types de tarifs
     */
    public function getTypesTarifs() {
        try {
            $typesTarifs = TarifControleur::getTypesTarifs($this->conn);
            
            return [
                'success' => true,
                'typesTarifs' => $typesTarifs
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des types de tarifs: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Crée un nouveau type de tarif
     * @param array $data Données du type de tarif
     * @return array Résultat de l'opération
     */
    public function createTypeTarif($data) {
        try {
            $id = TarifControleur::createTypeTarif($this->conn, $data);
            
            return [
                'success' => true,
                'message' => 'Type de tarif créé avec succès',
                'id' => $id
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la création du type de tarif: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Met à jour un type de tarif existant
     * @param int $id ID du type de tarif
     * @param array $data Données mises à jour
     * @return array Résultat de l'opération
     */
    public function updateTypeTarif($id, $data) {
        try {
            $result = TarifControleur::updateTypeTarif($this->conn, $id, $data);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Type de tarif mis à jour avec succès'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Aucune modification effectuée ou type de tarif non trouvé'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du type de tarif: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Supprime un type de tarif
     * @param int $id ID du type de tarif
     * @return array Résultat de l'opération
     */
    public function deleteTypeTarif($id) {
        try {
            $result = TarifControleur::deleteTypeTarif($this->conn, $id);
            
            return $result;
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la suppression du type de tarif: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Récupère les tarifs standards et thérapeutes
     * @param int|null $serviceId Filtre par service
     * @param int|null $uniteId Filtre par unité
     * @param int|null $typeTarifId Filtre par type de tarif
     * @param string|null $date Date pour laquelle récupérer les tarifs valides
     * @return array Liste des tarifs
     */
    public function getTarifs($serviceId = null, $uniteId = null, $typeTarifId = null, $date = null) {
        try {
            error_log("getTarifs called with serviceId: $serviceId, uniteId: $uniteId, typeTarifId: $typeTarifId, date: $date");
            $tarifs = TarifControleur::getTarifs(
                $this->conn,
                $serviceId,
                $uniteId,
                $typeTarifId,
                $date
            );
            
            return [
                'success' => true,
                'tarifs' => $tarifs
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des tarifs: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Récupère tous les tarifs standards (valides ou non)
     * @param int|null $serviceId Filtre par service
     * @param int|null $uniteId Filtre par unité
     * @param int|null $typeTarifId Filtre par type de tarif
     * @return array Liste de tous les tarifs standards
     */
    public function getAllTarifs($serviceId = null, $uniteId = null, $typeTarifId = null) {
        try {
            $tarifs = TarifControleur::getAllTarifs(
                $this->conn,
                $serviceId,
                $uniteId,
                $typeTarifId
            );
            
            return [
                'success' => true,
                'tarifs' => $tarifs
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération de tous les tarifs: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Récupère tous les tarifs spéciaux (valides ou non)
     * @param int|null $clientId Filtre par client
     * @param int|null $serviceId Filtre par service
     * @param int|null $uniteId Filtre par unité
     * @return array Liste de tous les tarifs spéciaux
     */
    public function getAllTarifsSpeciaux($clientId = null, $serviceId = null, $uniteId = null) {
        try {
            $tarifs = TarifControleur::getAllTarifsSpeciaux(
                $this->conn,
                $clientId,
                $serviceId,
                $uniteId
            );
            
            return [
                'success' => true,
                'tarifsSpeciaux' => $tarifs
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération de tous les tarifs spéciaux: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Crée un nouveau tarif
     * @param array $data Données du tarif
     * @return array Résultat de l'opération
     */
    public function createTarif($data) {
        try {
            // Valider les données
            if (!isset($data['serviceId']) || !isset($data['uniteId']) || 
                !isset($data['typeTarifId']) || !isset($data['prix'])) {
                return [
                    'success' => false,
                    'message' => 'Données de tarif incomplètes'
                ];
            }
            
            $id = TarifControleur::createTarif($this->conn, $data);
            
            return [
                'success' => true,
                'message' => 'Tarif créé avec succès',
                'id' => $id
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la création du tarif: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Met à jour un tarif existant
     * @param int $id ID du tarif
     * @param array $data Données mises à jour
     * @return array Résultat de l'opération
     */
    public function updateTarif($id, $data) {
        try {
            $result = TarifControleur::updateTarif($this->conn, $id, $data);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Tarif mis à jour avec succès'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Aucune modification effectuée ou tarif non trouvé'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du tarif: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Supprime un tarif
     * @param int $id ID du tarif
     * @return array Résultat de l'opération
     */
    public function deleteTarif($id) {
        try {
            $result = TarifControleur::deleteTarif($this->conn, $id);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Tarif supprimé avec succès'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Tarif non trouvé'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la suppression du tarif: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Récupère les tarifs spéciaux par client
     * @param int|null $clientId Filtre par client
     * @param int|null $serviceId Filtre par service
     * @param int|null $uniteId Filtre par unité
     * @param string|null $date Date pour laquelle récupérer les tarifs valides
     * @return array Liste des tarifs spéciaux
     */
    public function getTarifsSpeciaux($clientId = null, $serviceId = null, $uniteId = null, $date = null) {
        try {
            $tarifs = TarifControleur::getTarifsSpeciaux(
                $this->conn,
                $clientId,
                $serviceId,
                $uniteId,
                $date
            );
            
            return [
                'success' => true,
                'tarifsSpeciaux' => $tarifs
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des tarifs spéciaux: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Crée un nouveau tarif spécial pour un client
     * @param array $data Données du tarif spécial
     * @return array Résultat de l'opération
     */
    public function createTarifSpecial($data) {
        try {
            // Valider les données
            if (!isset($data['clientId']) || !isset($data['serviceId']) || 
                !isset($data['uniteId']) || !isset($data['prix'])) {
                return [
                    'success' => false,
                    'message' => 'Données de tarif spécial incomplètes'
                ];
            }
            
            // Vérifier que la note n'est pas vide
            if (!isset($data['note']) || trim($data['note']) === '') {
                return [
                    'success' => false,
                    'message' => 'La note est obligatoire pour un tarif spécial'
                ];
            }
            
            $id = TarifControleur::createTarifSpecial($this->conn, $data);
            
            return [
                'success' => true,
                'message' => 'Tarif spécial créé avec succès',
                'id' => $id
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la création du tarif spécial: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Met à jour un tarif spécial existant
     * @param int $id ID du tarif spécial
     * @param array $data Données mises à jour
     * @return array Résultat de l'opération
     */
    public function updateTarifSpecial($id, $data) {
        try {
            // Vérifier que la note n'est pas vide si elle est fournie
            if (isset($data['note']) && trim($data['note']) === '') {
                return [
                    'success' => false,
                    'message' => 'La note est obligatoire pour un tarif spécial'
                ];
            }
            
            $result = TarifControleur::updateTarifSpecial($this->conn, $id, $data);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Tarif spécial mis à jour avec succès'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Aucune modification effectuée ou tarif spécial non trouvé'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du tarif spécial: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Supprime un tarif spécial
     * @param int $id ID du tarif spécial
     * @return array Résultat de l'opération
     */
    public function deleteTarifSpecial($id) {
        try {
            $result = TarifControleur::deleteTarifSpecial($this->conn, $id);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Tarif spécial supprimé avec succès'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Tarif spécial non trouvé'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la suppression du tarif spécial: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Récupère le tarif applicable pour un client, un service et une unité spécifiques
     * @param int $clientId ID du client
     * @param int $serviceId ID du service
     * @param int $uniteId ID de l'unité
     * @param string|null $date Date pour laquelle récupérer le tarif valide
     * @return array Tarif applicable
     */
    public function getTarifClient($clientId, $serviceId, $uniteId, $date = null) {
        try {
            $date = $date ?: date('Y-m-d');
            $result = TarifControleur::getTarifClient($this->conn, $clientId, $serviceId, $uniteId, $date);
            
            return $result;
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération du tarif client: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Convertit le format des paramètres en structure de tarifs
     * Cette méthode peut être utilisée pour maintenir la compatibilité
     * avec l'ancien système de paramètres
     * 
     * @param array $parametres Paramètres de tarification de l'ancien format
     * @return array Structure de tarifs dans le nouveau format
     */
    public function convertParametresToTarifs($parametres) {
        $tarifs = [];
        
        // Logique de conversion à implémenter selon les besoins spécifiques
        
        return $tarifs;
    }

    /**
     * Vérifie si un client est thérapeute
     * @param int $clientId ID du client
     * @return array Résultat de la vérification
     */
    public function estTherapeute($clientId) {
        try {
            $result = TarifControleur::estTherapeute($this->conn, $clientId);
            
            return [
                'success' => true,
                'estTherapeute' => $result
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la vérification du statut thérapeute: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Vérifie si un client possède au moins un tarif spécial valide
     * @param int $clientId ID du client
     * @param string|null $date Date pour la vérification
     * @return array Résultat de la vérification
     */
    public function possedeTarifSpecialDefini($clientId, $date = null) {
        try {
            $result = TarifControleur::possedeTarifSpecialDefini($this->conn, $clientId, $date);
            
            return [
                'success' => true,
                'possedeTarifSpecial' => $result
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la vérification des tarifs spéciaux: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Récupère toutes les unités applicables pour un client spécifique
     * Cela inclut les unités avec tarifs standards et les unités avec tarifs spéciaux
     * 
     * @param int $clientId ID du client
     * @param string $date Date pour laquelle vérifier les tarifs valides
     * @return array Liste des unités applicables
     */
    public function getUnitesApplicablesPourClient($clientId, $date = null) {
        try {
            $date = $date ?: date('Y-m-d');
            
            $unites = TarifControleur::getUnitesApplicablesPourClient($this->conn, $clientId, $date);
            
            return [
                'success' => true,
                'unites' => $unites
            ];
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération des unités pour le client: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des unités pour le client: ' . $e->getMessage()
            ];
        }
    }

}
?>