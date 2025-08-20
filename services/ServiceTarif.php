<?php
// ServiceTarif.php

require_once '../controllers/TarifControleur.php';

class ServiceTarif {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Exécute une opération avec gestion transactionnelle optionnelle
     * @param callable $operation Fonction à exécuter
     * @param bool $useTransaction Utiliser une transaction ou non
     * @return array Résultat de l'opération
     */
    private function executeWithTransaction($operation, $useTransaction = true) {
        if (!$useTransaction) {
            return $operation();
        }
        
        try {
            // Démarrer une transaction
            $this->conn->beginTransaction();
            
            $result = $operation();
            
            // Valider la transaction si succès
            if ($result['success']) {
                $this->conn->commit();
            } else {
                $this->conn->rollBack();
            }
            
            return $result;
            
        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            
            return [
                'success' => false,
                'message' => 'Erreur transactionnelle: ' . $e->getMessage()
            ];
        }
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
     * Crée un nouveau service avec gestion transactionnelle
     * @param array $data Données du service
     * @param bool $useTransaction Utiliser une transaction
     * @return array Résultat de l'opération
     */
    public function createService($data, $useTransaction = true) {
        return $this->executeWithTransaction(function() use ($data) {
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
        }, $useTransaction);
    }
    
    /**
     * Met à jour un service existant avec gestion transactionnelle
     * @param int $id ID du service
     * @param array $data Données mises à jour
     * @param bool $useTransaction Utiliser une transaction
     * @return array Résultat de l'opération
     */
    public function updateService($id, $data, $useTransaction = true) {
        return $this->executeWithTransaction(function() use ($id, $data) {
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
        }, $useTransaction);
    }
    
    /**
     * Supprime un service avec gestion transactionnelle
     * @param int $id ID du service
     * @param bool $useTransaction Utiliser une transaction
     * @return array Résultat de l'opération
     */
    public function deleteService($id, $useTransaction = true) {
        return $this->executeWithTransaction(function() use ($id) {
            try {
                $result = TarifControleur::deleteService($this->conn, $id);
                return $result;
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Erreur lors de la suppression du service: ' . $e->getMessage()
                ];
            }
        }, $useTransaction);
    }
    
    /**
     * Récupère toutes les unités ou celles d'un service spécifique
     * @param int|null $service_id ID du service (optionnel)
     * @return array Liste des unités
     */
    public function getUnites($service_id = null) {
        try {
            if ($service_id !== null) {
                // Récupérer les unités pour un service spécifique
                $unites = TarifControleur::getUnitesByService($this->conn, $service_id);
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
     * Crée une nouvelle unité avec gestion transactionnelle
     * @param array $data Données de l'unité
     * @param bool $useTransaction Utiliser une transaction
     * @return array Résultat de l'opération
     */
    public function createUnite($data, $useTransaction = true) {
        return $this->executeWithTransaction(function() use ($data) {
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
        }, $useTransaction);
    }
    
    /**
     * Met à jour une unité existante avec gestion transactionnelle
     * @param int $id ID de l'unité
     * @param array $data Données mises à jour
     * @param bool $useTransaction Utiliser une transaction
     * @return array Résultat de l'opération
     */
    public function updateUnite($id, $data, $useTransaction = true) {
        return $this->executeWithTransaction(function() use ($id, $data) {
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
        }, $useTransaction);
    }
    
    /**
     * Supprime une unité avec gestion transactionnelle
     * @param int $id ID de l'unité
     * @param bool $useTransaction Utiliser une transaction
     * @return array Résultat de l'opération
     */
    public function deleteUnite($id, $useTransaction = true) {
        return $this->executeWithTransaction(function() use ($id) {
            try {
                $result = TarifControleur::deleteUnite($this->conn, $id);
                return $result;
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Erreur lors de la suppression de l\'unité: ' . $e->getMessage()
                ];
            }
        }, $useTransaction);
    }
    
    /**
     * Associe une unité à un service avec gestion transactionnelle
     * @param int $service_id ID du service
     * @param int $unite_id ID de l'unité
     * @param bool $useTransaction Utiliser une transaction
     * @return array Résultat de l'opération
     */
    public function linkServiceUnite($service_id, $unite_id, $useTransaction = true) {
        return $this->executeWithTransaction(function() use ($service_id, $unite_id) {
            try {
                $result = TarifControleur::linkServiceUnite($this->conn, $service_id, $unite_id);
                
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
        }, $useTransaction);
    }
    
    /**
     * Dissocie une unité d'un service avec gestion transactionnelle
     * @param int $service_id ID du service
     * @param int $unite_id ID de l'unité
     * @param bool $useTransaction Utiliser une transaction
     * @return array Résultat de l'opération
     */
    public function unlinkServiceUnite($service_id, $unite_id, $useTransaction = true) {
        return $this->executeWithTransaction(function() use ($service_id, $unite_id) {
            try {
                $result = TarifControleur::unlinkServiceUnite($this->conn, $service_id, $unite_id);
                return $result;
            } catch (Exception $e) {
                error_log('Erreur dans unlinkServiceUnite: ' . $e->getMessage());
                return [
                    'success' => false,
                    'message' => 'Erreur lors de la dissociation: ' . $e->getMessage()
                ];
            }
        }, $useTransaction);
    }

    /**
     * Met à jour l'unité par défaut d'un service avec gestion transactionnelle
     * @param int $service_id ID du service
     * @param int $unite_id ID de l'unité
     * @param bool $useTransaction Utiliser une transaction
     * @return array Résultat de l'opération
     */
    public function updateServiceUniteDefault($service_id, $unite_id, $useTransaction = true) {
        return $this->executeWithTransaction(function() use ($service_id, $unite_id) {
            try {
                $result = TarifControleur::updateServiceUniteDefault($this->conn, $service_id, $unite_id);
                return $result;
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Erreur lors de la mise à jour de l\'unité par défaut: ' . $e->getMessage()
                ];
            }
        }, $useTransaction);
    }

    /**
     * Obtient l'unité par défaut pour un service
     * @param int $service_id ID du service
     * @return array Résultat contenant l'ID de l'unité par défaut
     */
    public function getUniteDefault($service_id) {
        try {
            $result = TarifControleur::getUniteDefautPourService($this->conn, $service_id);
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
     * @param int $service_id ID du service
     * @param int $unite_id ID de l'unité
     * @return array Résultat de la vérification
     */
    public function checkServiceUniteUsageInFacture($service_id, $unite_id) {
        try {
            $result = TarifControleur::checkServiceUniteUsageInFacture($this->conn, $service_id, $unite_id);
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
     * Crée un nouveau type de tarif avec gestion transactionnelle
     * @param array $data Données du type de tarif
     * @param bool $useTransaction Utiliser une transaction
     * @return array Résultat de l'opération
     */
    public function createTypeTarif($data, $useTransaction = true) {
        return $this->executeWithTransaction(function() use ($data) {
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
        }, $useTransaction);
    }
    
    /**
     * Met à jour un type de tarif existant avec gestion transactionnelle
     * @param int $id ID du type de tarif
     * @param array $data Données mises à jour
     * @param bool $useTransaction Utiliser une transaction
     * @return array Résultat de l'opération
     */
    public function updateTypeTarif($id, $data, $useTransaction = true) {
        return $this->executeWithTransaction(function() use ($id, $data) {
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
        }, $useTransaction);
    }
    
    /**
     * Supprime un type de tarif avec gestion transactionnelle
     * @param int $id ID du type de tarif
     * @param bool $useTransaction Utiliser une transaction
     * @return array Résultat de l'opération
     */
    public function deleteTypeTarif($id, $useTransaction = true) {
        return $this->executeWithTransaction(function() use ($id) {
            try {
                $result = TarifControleur::deleteTypeTarif($this->conn, $id);
                return $result;
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Erreur lors de la suppression du type de tarif: ' . $e->getMessage()
                ];
            }
        }, $useTransaction);
    }
    
    /**
     * Récupère les tarifs standards et thérapeutes
     * @param int|null $service_id Filtre par service
     * @param int|null $unite_id Filtre par unité
     * @param int|null $type_tarif_id Filtre par type de tarif
     * @param string|null $date Date pour laquelle récupérer les tarifs valides
     * @return array Liste des tarifs
     */
    public function getTarifs($service_id = null, $unite_id = null, $type_tarif_id = null, $date = null) {
        try {
            error_log("getTarifs called with service_id: $service_id, unite_id: $unite_id, type_tarif_id: $type_tarif_id, date: $date");
            $tarifs = TarifControleur::getTarifs(
                $this->conn,
                $service_id,
                $unite_id,
                $type_tarif_id,
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
     * @param int|null $service_id Filtre par service
     * @param int|null $unite_id Filtre par unité
     * @param int|null $type_tarif_id Filtre par type de tarif
     * @return array Liste de tous les tarifs standards
     */
    public function getAllTarifs($service_id = null, $unite_id = null, $type_tarif_id = null) {
        try {
            $tarifs = TarifControleur::getAllTarifs(
                $this->conn,
                $service_id,
                $unite_id,
                $type_tarif_id
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
     * @param int|null $client_id Filtre par client
     * @param int|null $service_id Filtre par service
     * @param int|null $unite_id Filtre par unité
     * @return array Liste de tous les tarifs spéciaux
     */
    public function getAllTarifsSpeciaux($client_id = null, $service_id = null, $unite_id = null) {
        try {
            $tarifs = TarifControleur::getAllTarifsSpeciaux(
                $this->conn,
                $client_id,
                $service_id,
                $unite_id
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
     * Crée un nouveau tarif avec gestion transactionnelle
     * @param array $data Données du tarif
     * @param bool $useTransaction Utiliser une transaction
     * @return array Résultat de l'opération
     */
    public function createTarif($data, $useTransaction = true) {
        return $this->executeWithTransaction(function() use ($data) {
            try {
                // Valider les données
                if (!isset($data['service_id']) || !isset($data['unite_id']) || 
                    !isset($data['type_tarif_id']) || !isset($data['prix'])) {
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
        }, $useTransaction);
    }
    
    /**
     * Met à jour un tarif existant avec gestion transactionnelle
     * @param int $id ID du tarif
     * @param array $data Données mises à jour
     * @param bool $useTransaction Utiliser une transaction
     * @return array Résultat de l'opération
     */
    public function updateTarif($id, $data, $useTransaction = true) {
        return $this->executeWithTransaction(function() use ($id, $data) {
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
        }, $useTransaction);
    }
    
    /**
     * Supprime un tarif avec gestion transactionnelle
     * @param int $id ID du tarif
     * @param bool $useTransaction Utiliser une transaction
     * @return array Résultat de l'opération
     */
    public function deleteTarif($id, $useTransaction = true) {
        return $this->executeWithTransaction(function() use ($id) {
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
        }, $useTransaction);
    }
    
    /**
     * Récupère les tarifs spéciaux par client
     * @param int|null $client_id Filtre par client
     * @param int|null $service_id Filtre par service
     * @param int|null $unite_id Filtre par unité
     * @param string|null $date Date pour laquelle récupérer les tarifs valides
     * @return array Liste des tarifs spéciaux
     */
    public function getTarifsSpeciaux($client_id = null, $service_id = null, $unite_id = null, $date = null) {
        try {
            $tarifs = TarifControleur::getTarifsSpeciaux(
                $this->conn,
                $client_id,
                $service_id,
                $unite_id,
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
     * Crée un nouveau tarif spécial pour un client avec gestion transactionnelle
     * @param array $data Données du tarif spécial
     * @param bool $useTransaction Utiliser une transaction
     * @return array Résultat de l'opération
     */
    public function createTarifSpecial($data, $useTransaction = true) {
        return $this->executeWithTransaction(function() use ($data) {
            try {
                // Valider les données
                if (!isset($data['client_id']) || !isset($data['service_id']) || 
                    !isset($data['unite_id']) || !isset($data['prix'])) {
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
        }, $useTransaction);
    }
    
    /**
     * Met à jour un tarif spécial existant avec gestion transactionnelle
     * @param int $id ID du tarif spécial
     * @param array $data Données mises à jour
     * @param bool $useTransaction Utiliser une transaction
     * @return array Résultat de l'opération
     */
    public function updateTarifSpecial($id, $data, $useTransaction = true) {
        return $this->executeWithTransaction(function() use ($id, $data) {
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
        }, $useTransaction);
    }
    
    /**
     * Supprime un tarif spécial avec gestion transactionnelle
     * @param int $id ID du tarif spécial
     * @param bool $useTransaction Utiliser une transaction
     * @return array Résultat de l'opération
     */
    public function deleteTarifSpecial($id, $useTransaction = true) {
        return $this->executeWithTransaction(function() use ($id) {
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
        }, $useTransaction);
    }
    
    /**
     * Récupère le tarif applicable pour un client, un service et une unité spécifiques
     * @param int $client_id ID du client
     * @param int $service_id ID du service
     * @param int $unite_id ID de l'unité
     * @param string|null $date Date pour laquelle récupérer le tarif valide
     * @return array Tarif applicable
     */
    public function getTarifClient($client_id, $service_id, $unite_id, $date = null) {
        try {
            $date = $date ?: date('Y-m-d');
            $result = TarifControleur::getTarifClient($this->conn, $client_id, $service_id, $unite_id, $date);
            
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
     * @param int $client_id ID du client
     * @return array Résultat de la vérification
     */
    public function estTherapeute($client_id) {
        try {
            $result = TarifControleur::estTherapeute($this->conn, $client_id);
            
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
     * @param int $client_id ID du client
     * @param string|null $date Date pour la vérification
     * @return array Résultat de la vérification
     */
    public function possedeTarifSpecialDefini($client_id, $date = null) {
        try {
            $result = TarifControleur::possedeTarifSpecialDefini($this->conn, $client_id, $date);
            
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
     * @param int $client_id ID du client
     * @param string $date Date pour laquelle vérifier les tarifs valides
     * @return array Liste des unités applicables
     */
    public function getUnitesApplicablesPourClient($client_id, $date = null) {
        try {
            $date = $date ?: date('Y-m-d');
            
            $unites = TarifControleur::getUnitesApplicablesPourClient($this->conn, $client_id, $date);
            
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

    /**
     * ==========================================
     * OPÉRATIONS TRANSACTIONNELLES COMPLEXES
     * ==========================================
     */

    /**
     * Crée un service complet avec ses unités et tarifs en une seule transaction
     * @param array $serviceData Données du service
     * @param array $unitesIds IDs des unités à associer
     * @param array $tarifsData Données des tarifs à créer
     * @return array Résultat de l'opération
     */
    public function createServiceComplet($serviceData, $unitesIds = [], $tarifsData = []) {
        return $this->executeWithTransaction(function() use ($serviceData, $unitesIds, $tarifsData) {
            try {
                // 1. Créer le service
                $serviceResult = $this->createService($serviceData, false);
                if (!$serviceResult['success']) {
                    return $serviceResult;
                }
                $service_id = $serviceResult['id'];

                // 2. Associer les unités
                foreach ($unitesIds as $index => $unite_id) {
                    $linkResult = $this->linkServiceUnite($service_id, $unite_id, false);
                    if (!$linkResult['success']) {
                        return $linkResult;
                    }
                    
                    // Définir la première unité comme défaut
                    if ($index === 0) {
                        $defaultResult = $this->updateServiceUniteDefault($service_id, $unite_id, false);
                        if (!$defaultResult['success']) {
                            return $defaultResult;
                        }
                    }
                }

                // 3. Créer les tarifs
                foreach ($tarifsData as $tarifData) {
                    $tarifData['service_id'] = $service_id;
                    $tarifResult = $this->createTarif($tarifData, false);
                    if (!$tarifResult['success']) {
                        return $tarifResult;
                    }
                }

                return [
                    'success' => true,
                    'message' => 'Service complet créé avec succès',
                    'service_id' => $service_id
                ];
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Erreur lors de la création du service complet: ' . $e->getMessage()
                ];
            }
        });
    }

    /**
     * Met à jour les tarifs d'un service en une seule transaction
     * @param int $service_id ID du service
     * @param array $nouveauxTarifs Nouveaux tarifs à appliquer
     * @param bool $supprimerAnciens Supprimer les anciens tarifs
     * @return array Résultat de l'opération
     */
    public function updateTarifsService($service_id, $nouveauxTarifs, $supprimerAnciens = false) {
        return $this->executeWithTransaction(function() use ($service_id, $nouveauxTarifs, $supprimerAnciens) {
            try {
                // 1. Supprimer les anciens tarifs si demandé
                if ($supprimerAnciens) {
                    $anciensResult = $this->getAllTarifs($service_id);
                    if ($anciensResult['success']) {
                        foreach ($anciensResult['tarifs'] as $tarif) {
                            $deleteResult = $this->deleteTarif($tarif['id'], false);
                            if (!$deleteResult['success']) {
                                return $deleteResult;
                            }
                        }
                    }
                }

                // 2. Créer les nouveaux tarifs
                foreach ($nouveauxTarifs as $tarifData) {
                    $tarifData['service_id'] = $service_id;
                    $createResult = $this->createTarif($tarifData, false);
                    if (!$createResult['success']) {
                        return $createResult;
                    }
                }

                return [
                    'success' => true,
                    'message' => 'Tarifs du service mis à jour avec succès'
                ];
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Erreur lors de la mise à jour des tarifs: ' . $e->getMessage()
                ];
            }
        });
    }

    /**
     * Duplique un service avec tous ses tarifs
     * @param int $service_id ID du service à dupliquer
     * @param string $nouveauNom Nom du nouveau service
     * @return array Résultat de l'opération
     */
    public function duplicateService($service_id, $nouveauNom) {
        return $this->executeWithTransaction(function() use ($service_id, $nouveauNom) {
            try {
                // 1. Récupérer le service original
                $serviceResult = $this->getServiceById($service_id);
                if (!$serviceResult['success']) {
                    return $serviceResult;
                }
                
                $serviceOriginal = $serviceResult['service'];
                $serviceOriginal['nom'] = $nouveauNom;
                unset($serviceOriginal['id']);

                // 2. Créer le nouveau service
                $newServiceResult = $this->createService($serviceOriginal, false);
                if (!$newServiceResult['success']) {
                    return $newServiceResult;
                }
                $newservice_id = $newServiceResult['id'];

                // 3. Récupérer et dupliquer les associations unités
                $unitesResult = $this->getUnites($service_id);
                if ($unitesResult['success']) {
                    foreach ($unitesResult['unites'] as $unite) {
                        $this->linkServiceUnite($newservice_id, $unite['id'], false);
                    }
                }

                // 4. Récupérer et dupliquer les tarifs
                $tarifsResult = $this->getAllTarifs($service_id);
                if ($tarifsResult['success']) {
                    foreach ($tarifsResult['tarifs'] as $tarif) {
                        $newTarif = $tarif;
                        $newTarif['service_id'] = $newservice_id;
                        unset($newTarif['id']);
                        $this->createTarif($newTarif, false);
                    }
                }

                return [
                    'success' => true,
                    'message' => 'Service dupliqué avec succès',
                    'newservice_id' => $newservice_id
                ];
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Erreur lors de la duplication du service: ' . $e->getMessage()
                ];
            }
        });
    }

    /**
     * Applique des tarifs spéciaux en masse pour un client
     * @param int $client_id ID du client
     * @param array $tarifsSpeciaux Tarifs spéciaux à appliquer
     * @return array Résultat de l'opération
     */
    public function appliquerTarifsSpeciauxMasse($client_id, $tarifsSpeciaux) {
        return $this->executeWithTransaction(function() use ($client_id, $tarifsSpeciaux) {
            try {
                $tarifsAppliques = 0;
                $erreurs = [];
                
                foreach ($tarifsSpeciaux as $index => $tarifData) {
                    $tarifData['client_id'] = $client_id;
                    $result = $this->createTarifSpecial($tarifData, false);
                    if ($result['success']) {
                        $tarifsAppliques++;
                    } else {
                        $erreurs[] = "Tarif $index: " . $result['message'];
                    }
                }

                if (count($erreurs) > 0 && $tarifsAppliques === 0) {
                    return [
                        'success' => false,
                        'message' => 'Aucun tarif spécial n\'a pu être créé. Erreurs: ' . implode('; ', $erreurs)
                    ];
                }

                $message = "$tarifsAppliques tarifs spéciaux appliqués avec succès";
                if (count($erreurs) > 0) {
                    $message .= ". Erreurs: " . implode('; ', $erreurs);
                }

                return [
                    'success' => true,
                    'message' => $message,
                    'tarifsAppliques' => $tarifsAppliques,
                    'erreurs' => $erreurs
                ];
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Erreur lors de l\'application des tarifs spéciaux: ' . $e->getMessage()
                ];
            }
        });
    }

    /**
     * Migre tous les tarifs d'un type vers un autre type
     * @param int $ancienTypeId ID de l'ancien type de tarif
     * @param int $nouveauTypeId ID du nouveau type de tarif
     * @return array Résultat de l'opération
     */
    public function migrerTarifsVersNouveauType($ancienTypeId, $nouveauTypeId) {
        return $this->executeWithTransaction(function() use ($ancienTypeId, $nouveauTypeId) {
            try {
                // Récupérer tous les tarifs de l'ancien type
                $tarifsResult = $this->getAllTarifs(null, null, $ancienTypeId);
                if (!$tarifsResult['success']) {
                    return $tarifsResult;
                }

                $tarifs = $tarifsResult['tarifs'];
                $tarifsMigres = 0;

                foreach ($tarifs as $tarif) {
                    $updateResult = $this->updateTarif($tarif['id'], ['type_tarif_id' => $nouveauTypeId], false);
                    if ($updateResult['success']) {
                        $tarifsMigres++;
                    }
                }

                return [
                    'success' => true,
                    'message' => "$tarifsMigres tarifs migrés vers le nouveau type",
                    'tarifsMigres' => $tarifsMigres
                ];
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Erreur lors de la migration des tarifs: ' . $e->getMessage()
                ];
            }
        });
    }

    /**
     * ==========================================
     * GESTION TRANSACTIONNELLE EN BATCH
     * ==========================================
     */

    /**
     * Exécute plusieurs opérations en mode batch transactionnel
     * @param array $operations Tableau d'opérations à exécuter
     * @return array Résultat global
     */
    public function executeBatch($operations) {
        return $this->executeWithTransaction(function() use ($operations) {
            $resultats = [];
            
            foreach ($operations as $index => $operation) {
                try {
                    $methode = $operation['methode'];
                    $parametres = $operation['parametres'] ?? [];
                    
                    if (!method_exists($this, $methode)) {
                        return [
                            'success' => false,
                            'message' => "Méthode '$methode' non trouvée à l'opération $index"
                        ];
                    }
                    
                    // Ajouter false comme dernier paramètre pour désactiver les transactions individuelles
                    $parametres[] = false;
                    $resultat = call_user_func_array([$this, $methode], $parametres);
                    $resultats[$index] = $resultat;
                    
                    if (!$resultat['success']) {
                        return [
                            'success' => false,
                            'message' => "Erreur à l'opération $index: " . $resultat['message'],
                            'resultats' => $resultats
                        ];
                    }
                } catch (Exception $e) {
                    return [
                        'success' => false,
                        'message' => "Exception à l'opération $index: " . $e->getMessage(),
                        'resultats' => $resultats
                    ];
                }
            }
            
            return [
                'success' => true,
                'message' => 'Toutes les opérations ont été exécutées avec succès',
                'resultats' => $resultats
            ];
        });
    }

    /**
     * Sauvegarde et restauration de données
     * @param array $config Configuration de sauvegarde
     * @return array Résultat de l'opération
     */
    public function sauvegarderDonneesTarification($config = []) {
        try {
            $backup = [
                'timestamp' => date('Y-m-d H:i:s'),
                'services' => $this->getServices()['services'] ?? [],
                'unites' => $this->getUnites()['unites'] ?? [],
                'typesTarifs' => $this->getTypesTarifs()['typesTarifs'] ?? [],
                'tarifs' => $this->getAllTarifs()['tarifs'] ?? [],
                'tarifsSpeciaux' => $this->getAllTarifsSpeciaux()['tarifsSpeciaux'] ?? [],
                'servicesUnites' => $this->getServicesUnites()['servicesUnites'] ?? []
            ];

            return [
                'success' => true,
                'message' => 'Sauvegarde créée avec succès',
                'backup' => $backup
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la sauvegarde: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ==========================================
     * MÉTHODES UTILITAIRES
     * ==========================================
     */

    /**
     * Vérifie l'état de la connexion et de la transaction
     * @return array État de la connexion
     */
    public function getConnectionStatus() {
        try {
            $inTransaction = $this->conn->inTransaction();
            return [
                'success' => true,
                'inTransaction' => $inTransaction,
                'connectionActive' => true
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur de connexion: ' . $e->getMessage(),
                'connectionActive' => false
            ];
        }
    }

    /**
     * Force le rollback d'une transaction en cours (pour debug)
     * @return array Résultat de l'opération
     */
    public function forceRollback() {
        try {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
                return [
                    'success' => true,
                    'message' => 'Transaction annulée avec succès'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Aucune transaction en cours'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors du rollback: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Valide l'intégrité des données tarifaires
     * @return array Résultat de la validation
     */
    public function validerIntegriteDonnees() {
        try {
            $erreurs = [];
            
            // Vérifier les services sans unités
            $servicesResult = $this->getServices();
            if ($servicesResult['success']) {
                foreach ($servicesResult['services'] as $service) {
                    $unitesResult = $this->getUnites($service['id']);
                    if ($unitesResult['success'] && empty($unitesResult['unites'])) {
                        $erreurs[] = "Service '{$service['nom']}' n'a aucune unité associée";
                    }
                }
            }

            // Vérifier les tarifs orphelins
            $tarifsResult = $this->getAllTarifs();
            if ($tarifsResult['success']) {
                foreach ($tarifsResult['tarifs'] as $tarif) {
                    $serviceResult = $this->getServiceById($tarif['service_id']);
                    if (!$serviceResult['success']) {
                        $erreurs[] = "Tarif ID {$tarif['id']} référence un service inexistant";
                    }
                }
            }

            return [
                'success' => true,
                'valide' => empty($erreurs),
                'erreurs' => $erreurs,
                'message' => empty($erreurs) ? 'Intégrité des données validée' : 'Problèmes d\'intégrité détectés'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la validation: ' . $e->getMessage()
            ];
        }
    }
}
?>