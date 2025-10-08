<?php
// ServiceTarif.php - Version refactorisée avec nouveaux contrôleurs

require_once __DIR__ . '/../controllers/TarifControleur.php'; // Ancien
require_once __DIR__ . '/../controllers/ServiceControleur.php'; // Nouveau
require_once __DIR__ . '/../controllers/UniteControleur.php'; // Nouveau

class ServiceTarif {
    private $conn;
    private $serviceControleur; // Nouveau contrôleur
    private $uniteControleur; // Nouveau contrôleur
    private $tarifControleur; // Nouveau contrôleur
    
    // Flags pour activer progressivement les nouveaux contrôleurs
    private $useNewServiceController = true;
    private $useNewUniteController = true;
    private $useNewTarifController = true; // NOUVEAU
    
    public function __construct($conn) {
        $this->conn = $conn;
        
        // Initialiser les nouveaux contrôleurs
        if ($this->useNewServiceController) {
            $this->serviceControleur = new ServiceControleur($conn);
        }
        if ($this->useNewUniteController) {
            $this->uniteControleur = new UniteControleur($conn);
        }
        if ($this->useNewTarifController) {
            $this->tarifControleur = new TarifControleur($conn);
        }
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
            $this->conn->beginTransaction();
            $result = $operation();
            
            if ($result['success']) {
                $this->conn->commit();
            } else {
                $this->conn->rollBack();
            }
            
            return $result;
            
        } catch (Exception $e) {
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
     * ===============================
     * GESTION DES SERVICES
     * ===============================
     */
    
    public function getServices($actif = false) {
        if ($this->useNewServiceController) {
            try {
                return [
                    'success' => true,
                    'services' => $this->serviceControleur->getAll($actif)
                ];
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Erreur lors de la récupération des services: ' . $e->getMessage()
                ];
            }

        }
    }
    
    public function getServiceById($id) {
        if ($this->useNewServiceController) {
            try {
                $service = $this->serviceControleur->getById($id);
                return $service 
                    ? ['success' => true, 'service' => $service]
                    : ['success' => false, 'message' => 'Service non trouvé'];
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Erreur lors de la récupération du service: ' . $e->getMessage()
                ];
            }

        }
    }
    
    public function createService($data, $useTransaction = true) {
        if ($this->useNewServiceController) {
            return $this->executeWithTransaction(function() use ($data) {
                try {
                    $id = $this->serviceControleur->create($data);
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
    }
    
    public function updateService($id, $data, $useTransaction = true) {
        if ($this->useNewServiceController) {
            return $this->executeWithTransaction(function() use ($id, $data) {
                try {
                    $result = $this->serviceControleur->update($id, $data);
                    return $result 
                        ? ['success' => true, 'message' => 'Service mis à jour avec succès']
                        : ['success' => false, 'message' => 'Aucune modification effectuée ou service non trouvé'];
                } catch (Exception $e) {
                    return [
                        'success' => false,
                        'message' => 'Erreur lors de la mise à jour du service: ' . $e->getMessage()
                    ];
                }
            }, $useTransaction);

        }
    }

    public function deleteService($id, $useTransaction = true) {
        if ($this->useNewServiceController) {
            return $this->executeWithTransaction(function() use ($id) {
                try {
                    return $this->serviceControleur->delete($id);
                } catch (Exception $e) {
                    return [
                        'success' => false,
                        'message' => 'Erreur lors de la suppression du service: ' . $e->getMessage()
                    ];
                }
            }, $useTransaction);

        }
    }

    public function checkServiceUsage($id) {
        if ($this->useNewServiceController) {
            try {
                return $this->serviceControleur->checkUsage($id);
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Erreur lors de la vérification de l\'utilisation du service: ' . $e->getMessage()
                ];
            }

        }
    }
    
    /**
     * ===============================
     * GESTION DES UNITÉS
     * ===============================
     */
    
    public function getUnites($id_service = null) {
        if ($this->useNewUniteController) {
            try {
                $unites = $id_service !== null
                    ? $this->uniteControleur->getByService($id_service)
                    : $this->uniteControleur->getAll();
                
                return ['success' => true, 'unites' => $unites];
            } catch (Exception $e) {
                return ['success' => false, 'message' => 'Erreur lors de la récupération des unités: ' . $e->getMessage()];
            }

        } 
    }
    
    public function getServicesUnites() {
        if ($this->useNewUniteController) {
            try {
                $relations = $this->uniteControleur->getServicesUnites();
                return ['success' => true, 'servicesUnites' => $relations];
            } catch (Exception $e) {
                return ['success' => false, 'message' => 'Erreur lors de la récupération des relations services-unités: ' . $e->getMessage()];
            }

        }
    }

    public function createUnite($data, $useTransaction = true) {
        if ($this->useNewUniteController) {
            return $this->executeWithTransaction(function() use ($data) {
                try {
                    $id = $this->uniteControleur->create($data);
                    return ['success' => true, 'message' => 'Unité créée avec succès', 'id' => $id];
                } catch (Exception $e) {
                    return ['success' => false, 'message' => 'Erreur lors de la création de l\'unité: ' . $e->getMessage()];
                }
            }, $useTransaction);

        }
    }
    
    public function updateUnite($id, $data, $useTransaction = true) {
        if ($this->useNewUniteController) {
            return $this->executeWithTransaction(function() use ($id, $data) {
                try {
                    $result = $this->uniteControleur->update($id, $data);
                    return $result 
                        ? ['success' => true, 'message' => 'Unité mise à jour avec succès']
                        : ['success' => false, 'message' => 'Aucune modification effectuée ou unité non trouvée'];
                } catch (Exception $e) {
                    return ['success' => false, 'message' => 'Erreur lors de la mise à jour de l\'unité: ' . $e->getMessage()];
                }
            }, $useTransaction);

        }
    }
    
    public function deleteUnite($id, $useTransaction = true) {
        if ($this->useNewUniteController) {
            return $this->executeWithTransaction(function() use ($id) {
                try {
                    return $this->uniteControleur->delete($id);
                } catch (Exception $e) {
                    return ['success' => false, 'message' => 'Erreur lors de la suppression de l\'unité: ' . $e->getMessage()];
                }
            }, $useTransaction);

        }
    }
    
    public function linkServiceUnite($id_service, $id_unite, $useTransaction = true) {
        if ($this->useNewUniteController) {
            return $this->executeWithTransaction(function() use ($id_service, $id_unite) {
                try {
                    $this->uniteControleur->linkToService($id_unite, $id_service);
                    return ['success' => true, 'message' => 'Association service-unité créée avec succès'];
                } catch (Exception $e) {
                    return ['success' => false, 'message' => 'Erreur lors de l\'association service-unité: ' . $e->getMessage()];
                }
            }, $useTransaction);

        }
    }
    
    public function unlinkServiceUnite($id_service, $id_unite, $useTransaction = true) {
        if ($this->useNewUniteController) {
            return $this->executeWithTransaction(function() use ($id_service, $id_unite) {
                try {
                    return $this->uniteControleur->unlinkFromService($id_unite, $id_service);
                } catch (Exception $e) {
                    return ['success' => false, 'message' => 'Erreur lors de la dissociation: ' . $e->getMessage()];
                }
            }, $useTransaction);

        }
    }

    public function updateServiceUniteDefault($id_service, $id_unite, $useTransaction = true) {
        if ($this->useNewUniteController) {
            return $this->executeWithTransaction(function() use ($id_service, $id_unite) {
                try {
                    return $this->uniteControleur->updateServiceUniteDefault($id_service, $id_unite);
                } catch (Exception $e) {
                    return ['success' => false, 'message' => 'Erreur lors de la mise à jour de l\'unité par défaut: ' . $e->getMessage()];
                }
            }, $useTransaction);

        }
    }

    public function getUniteDefault($id_service) {
        if ($this->useNewUniteController) {
            try {
                $id_unite = $this->uniteControleur->getUniteDefaultForService($id_service);
                return [
                    'success' => true,
                    'id_unite' => $id_unite
                ];
            } catch (Exception $e) {
                return ['success' => false, 'message' => 'Erreur lors de la récupération de l\'unité par défaut: ' . $e->getMessage()];
            }

        }
    }

    public function checkUniteUsage($id) {
        if ($this->useNewUniteController) {
            try {
                return $this->uniteControleur->checkUsage($id);
            } catch (Exception $e) {
                return ['success' => false, 'message' => 'Erreur lors de la vérification de l\'utilisation de l\'unité: ' . $e->getMessage()];
            }

        }
    }

    public function checkServiceUniteUsageInFacture($id_service, $id_unite) {
        if ($this->useNewUniteController) {
            try {
                return $this->uniteControleur->checkServiceUniteUsageInFacture($id_service, $id_unite);
            } catch (Exception $e) {
                return ['success' => false, 'message' => 'Erreur lors de la vérification de l\'utilisation de la liaison dans les factures: ' . $e->getMessage()];
            }

        }
    }
    
    /**
     * ===============================
     * GESTION DES TYPES DE TARIFS
     * ===============================
     */
    
    public function getTypesTarifs() {
        if ($this->useNewTarifController) {
            try {
                $typesTarifs = $this->tarifControleur->getAllTypesTarifs();
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
    }
    
    public function createTypeTarif($data, $useTransaction = true) {
        if ($this->useNewTarifController) {
            return $this->executeWithTransaction(function() use ($data) {
                try {
                    $id = $this->tarifControleur->createTypeTarif($data);
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
    }
    
    public function updateTypeTarif($id, $data, $useTransaction = true) {
        if ($this->useNewTarifController) {
            return $this->executeWithTransaction(function() use ($id, $data) {
                try {
                    $result = $this->tarifControleur->updateTypeTarif($id, $data);
                    return $result 
                        ? ['success' => true, 'message' => 'Type de tarif mis à jour avec succès']
                        : ['success' => false, 'message' => 'Aucune modification effectuée ou type de tarif non trouvé'];
                } catch (Exception $e) {
                    return [
                        'success' => false,
                        'message' => 'Erreur lors de la mise à jour du type de tarif: ' . $e->getMessage()
                    ];
                }
            }, $useTransaction);

        }
    }
    
    public function deleteTypeTarif($id, $useTransaction = true) {
        if ($this->useNewTarifController) {
            return $this->executeWithTransaction(function() use ($id) {
                try {
                    return $this->tarifControleur->deleteTypeTarif($id);
                } catch (Exception $e) {
                    return [
                        'success' => false,
                        'message' => 'Erreur lors de la suppression du type de tarif: ' . $e->getMessage()
                    ];
                }
            }, $useTransaction);

        }
    }

    public function checkTypeTarifUsage($id) {
        if ($this->useNewTarifController) {
            try {
                return $this->tarifControleur->checkTypeTarifUsage($id);
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Erreur lors de la vérification de l\'utilisation du type de tarif: ' . $e->getMessage()
                ];
            }

        }
    }
    
    /**
     * ===============================
     * GESTION DES TARIFS STANDARDS
     * ===============================
     */
    
    public function getTarifs($id_service = null, $id_unite = null, $type_tarif_id = null, $date = null) {
        if ($this->useNewTarifController) {
            try {
                $tarifs = $this->tarifControleur->getTarifsStandards($id_service, $id_unite, $type_tarif_id, $date);
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
    }

    public function getAllTarifs($id_service = null, $id_unite = null, $type_tarif_id = null) {
        if ($this->useNewTarifController) {
            try {
                $tarifs = $this->tarifControleur->getAllTarifsStandards($id_service, $id_unite, $type_tarif_id);
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
    }
    
    public function createTarif($data, $useTransaction = true) {
        if ($this->useNewTarifController) {
            return $this->executeWithTransaction(function() use ($data) {
                try {
                    // Valider les données
                    if (!isset($data['id_service']) || !isset($data['id_unite']) || 
                        !isset($data['type_tarif_id']) || !isset($data['prix'])) {
                        return [
                            'success' => false,
                            'message' => 'Données de tarif incomplètes'
                        ];
                    }

                    $id = $this->tarifControleur->createTarifStandard($data);
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
    }
    
    public function updateTarif($id, $data, $useTransaction = true) {
        if ($this->useNewTarifController) {
            return $this->executeWithTransaction(function() use ($id, $data) {
                try {
                    $result = $this->tarifControleur->updateTarifStandard($id, $data);
                    return $result 
                        ? ['success' => true, 'message' => 'Tarif mis à jour avec succès']
                        : ['success' => false, 'message' => 'Aucune modification effectuée ou tarif non trouvé'];
                } catch (Exception $e) {
                    return [
                        'success' => false,
                        'message' => 'Erreur lors de la mise à jour du tarif: ' . $e->getMessage()
                    ];
                }
            }, $useTransaction);

        }
    }
    
    public function deleteTarif($id, $useTransaction = true) {
        if ($this->useNewTarifController) {
            return $this->executeWithTransaction(function() use ($id) {
                try {
                    $result = $this->tarifControleur->deleteTarifStandard($id);
                    return $result 
                        ? ['success' => true, 'message' => 'Tarif supprimé avec succès']
                        : ['success' => false, 'message' => 'Tarif non trouvé'];
                } catch (Exception $e) {
                    return [
                        'success' => false,
                        'message' => 'Erreur lors de la suppression du tarif: ' . $e->getMessage()
                    ];
                }
            }, $useTransaction);

        }
    }

    public function checkTarifUsage($id) {
        if ($this->useNewTarifController) {
            try {
                return $this->tarifControleur->checkTarifStandardUsage($id);
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Erreur lors de la vérification de l\'utilisation du tarif: ' . $e->getMessage()
                ];
            }

        }
    }
    
    /**
     * ===============================
     * GESTION DES TARIFS SPÉCIAUX
     * ===============================
     */
    
    public function getTarifsSpeciaux($id_client = null, $id_service = null, $id_unite = null, $date = null) {
        if ($this->useNewTarifController) {
            try {
                $tarifs = $this->tarifControleur->getTarifsSpeciaux($id_client, $id_service, $id_unite, $date);
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
    }

    public function getAllTarifsSpeciaux($id_client = null, $id_service = null, $id_unite = null) {
        if ($this->useNewTarifController) {
            try {
                $tarifs = $this->tarifControleur->getAllTarifsSpeciaux($id_client, $id_service, $id_unite);
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
    }
    
    public function createTarifSpecial($data, $useTransaction = true) {
        if ($this->useNewTarifController) {
            return $this->executeWithTransaction(function() use ($data) {
                try {
                    // Valider les données
                    if (!isset($data['id_client']) || !isset($data['id_service']) || 
                        !isset($data['id_unite']) || !isset($data['prix'])) {
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
                    
                    $id = $this->tarifControleur->createTarifSpecial($data);
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
    }
    
    public function updateTarifSpecial($id, $data, $useTransaction = true) {
        if ($this->useNewTarifController) {
            return $this->executeWithTransaction(function() use ($id, $data) {
                try {
                    // Vérifier que la note n'est pas vide si elle est fournie
                    if (isset($data['note']) && trim($data['note']) === '') {
                        return [
                            'success' => false,
                            'message' => 'La note est obligatoire pour un tarif spécial'
                        ];
                    }
                    
                    $result = $this->tarifControleur->updateTarifSpecial($id, $data);
                    return $result 
                        ? ['success' => true, 'message' => 'Tarif spécial mis à jour avec succès']
                        : ['success' => false, 'message' => 'Aucune modification effectuée ou tarif spécial non trouvé'];
                } catch (Exception $e) {
                    return [
                        'success' => false,
                        'message' => 'Erreur lors de la mise à jour du tarif spécial: ' . $e->getMessage()
                    ];
                }
            }, $useTransaction);

        }
    }
    
    public function deleteTarifSpecial($id, $useTransaction = true) {
        if ($this->useNewTarifController) {
            return $this->executeWithTransaction(function() use ($id) {
                try {
                    $result = $this->tarifControleur->deleteTarifSpecial($id);
                    return $result 
                        ? ['success' => true, 'message' => 'Tarif spécial supprimé avec succès']
                        : ['success' => false, 'message' => 'Tarif spécial non trouvé'];
                } catch (Exception $e) {
                    return [
                        'success' => false,
                        'message' => 'Erreur lors de la suppression du tarif spécial: ' . $e->getMessage()
                    ];
                }
            }, $useTransaction);

        }
    }

    public function checkTarifSpecialUsage($id) {
        if ($this->useNewTarifController) {
            try {
                return $this->tarifControleur->checkTarifSpecialUsage($id);
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Erreur lors de la vérification de l\'utilisation du tarif spécial: ' . $e->getMessage()
                ];
            }

        }
    }
    
    /**
     * ===============================
     * CALCULS DE TARIFS POUR CLIENTS
     * ===============================
     */
    
    public function getTarifClient($id_client, $id_service, $id_unite, $date = null) {
        if ($this->useNewTarifController) {
            try {
                $date = $date ?: date('Y-m-d');
                
                error_log("ServiceTarif::getTarifClient - Recherche tarif pour client: $id_client, service: $id_service, unite: $id_unite, date: $date");
                
                // 1. PRIORITÉ 1: Chercher un tarif spécial pour ce client
                error_log("ServiceTarif::getTarifClient - Étape 1: Recherche tarif spécial");
                $tarifsSpeciaux = $this->tarifControleur->getTarifsSpeciaux($id_client, $id_service, $id_unite, $date);
                
                if (!empty($tarifsSpeciaux)) {
                    $tarifSpecial = $tarifsSpeciaux[0]; // Prendre le premier tarif trouvé
                    error_log("ServiceTarif::getTarifClient - Tarif spécial trouvé: " . $tarifSpecial['prix_tarif_special']);
                    
                    return [
                        'success' => true,
                        'tarif' => [
                            'prix' => $tarifSpecial['prix_tarif_special'],
                            'type' => 'special',
                            'details' => $tarifSpecial
                        ],
                        'type' => 'special'
                    ];
                }
                
                error_log("ServiceTarif::getTarifClient - Aucun tarif spécial trouvé");
                
                // 2. PRIORITÉ 2: Vérifier si le client est thérapeute
                $estTherapeute = $this->tarifControleur->isClientTherapeute($id_client);
                error_log("ServiceTarif::getTarifClient - Client est thérapeute: " . ($estTherapeute ? 'OUI' : 'NON'));
                
                if ($estTherapeute) {
                    // Chercher un tarif thérapeute
                    error_log("ServiceTarif::getTarifClient - Étape 2: Recherche tarif thérapeute");
                    $tarifsTherapeuteResult = $this->tarifControleur->getTarifsStandards($id_service, $id_unite, null, $date);
                    error_log("ServiceTarif::getTarifClient - Tarifs standards trouvés: " . count($tarifsTherapeuteResult));
                    error_log("ServiceTarif::getTarifClient - Détails des tarifs standards: " . print_r($tarifsTherapeuteResult, true));
                    
                    // Filtrer pour ne garder que les tarifs thérapeute
                    $tarifsTherapeuteFiltered = array_filter($tarifsTherapeuteResult, function($tarif) {
                        return isset($tarif['code_type_tarif']) && strtolower($tarif['code_type_tarif']) === 'therapeute';
                    });
                    
                    if (!empty($tarifsTherapeuteFiltered)) {
                        $tarifTherapeuteArray = array_values($tarifsTherapeuteFiltered);
                        $tarifTherapeute = $tarifTherapeuteArray[0];
                        error_log("ServiceTarif::getTarifClient - Tarif thérapeute trouvé: " . $tarifTherapeute['prix_tarif_standard']);
                        
                        return [
                            'success' => true,
                            'tarif' => [
                                'prix' => $tarifTherapeute['prix_tarif_standard'],
                                'type' => 'therapeute',
                                'details' => $tarifTherapeute
                            ],
                            'type' => 'therapeute'
                        ];
                    }
                    
                    error_log("ServiceTarif::getTarifClient - Aucun tarif thérapeute trouvé");
                }
                
                // 3. PRIORITÉ 3: Chercher un tarif standard (normal)
                error_log("ServiceTarif::getTarifClient - Étape 3: Recherche tarif standard");
                $tarifsStandardResult = $this->tarifControleur->getTarifsStandards($id_service, $id_unite, null, $date);
                
                // Filtrer pour ne garder que les tarifs standard (normal)

                $tarifsStandardFiltered = array_filter($tarifsStandardResult, function($tarif) {
                    return isset($tarif['code_type_tarif']) && strtolower($tarif['code_type_tarif']) === 'normal';
                });
                
                if (!empty($tarifsStandardFiltered)) {
                    $tarifStandardArray = array_values($tarifsStandardFiltered);
                    $tarifStandard = $tarifStandardArray[0];
                    error_log("ServiceTarif::getTarifClient - Tarif standard trouvé: " . $tarifStandard['prix_tarif_standard']);
                    
                    return [
                        'success' => true,
                        'tarif' => [
                            'prix' => $tarifStandard['prix_tarif_standard'],
                            'type' => 'normal',
                            'details' => $tarifStandard
                        ],
                        'type' => 'normal'
                    ];
                }
                
                error_log("ServiceTarif::getTarifClient - Aucun tarif standard trouvé");
                
                // 4. FALLBACK: Si aucun tarif avec date trouvé, chercher sans contrainte de date
                error_log("ServiceTarif::getTarifClient - Étape 4: Recherche tous tarifs sans contrainte de date");
                $tousLesTarifs = $this->tarifControleur->getAllTarifsStandards($id_service, $id_unite, null);
                
                if (!empty($tousLesTarifs)) {
                    // Prioriser thérapeute si client est thérapeute, sinon normal
                    $typePreferered = $estTherapeute ? 'therapeute' : 'normal';
                    
                    $tarifPrefered = null;
                    $tarifFallback = null;
                    
                    foreach ($tousLesTarifs as $tarif) {
                        if ($tarif['code_type_tarif'] === $typePreferered) {
                            $tarifPrefered = $tarif;
                            break;
                        } elseif ($tarif['code_type_tarif'] === 'normal') {
                            $tarifFallback = $tarif; // Garder un fallback vers normal
                        }
                    }
                    
                    $tarifToUse = $tarifPrefered ?: $tarifFallback;
                    
                    if ($tarifToUse) {
                        error_log("ServiceTarif::getTarifClient - Tarif fallback trouvé: " . $tarifToUse['prix_tarif_standard'] . " (type: " . $tarifToUse['code_type_tarif'] . ")");
                        
                        return [
                            'success' => true,
                            'tarif' => [
                                'prix' => $tarifToUse['prix_tarif_standard'],
                                'type' => $tarifToUse['code_type_tarif'],
                                'details' => $tarifToUse
                            ],
                            'type' => $tarifToUse['code_type_tarif']
                        ];
                    }
                }
                
                // 5. Aucun tarif trouvé
                error_log("ServiceTarif::getTarifClient - AUCUN TARIF TROUVÉ");
                return [
                    'success' => false,
                    'message' => "Aucun tarif trouvé pour le service $id_service, l'unité $id_unite et le client $id_client"
                ];
                
            } catch (Exception $e) {
                error_log("ServiceTarif::getTarifClient - ERREUR: " . $e->getMessage());
                return [
                    'success' => false,
                    'message' => 'Erreur lors de la récupération du tarif client: ' . $e->getMessage()
                ];
            }
        }
    }

    public function estTherapeute($id_client) {
        if ($this->useNewTarifController) {
            try {
                $result = $this->tarifControleur->isClientTherapeute($id_client);
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
    }

    public function possedeTarifSpecialDefini($id_client, $date = null) {
        if ($this->useNewTarifController) {
            try {
                $result = $this->tarifControleur->clientPossedeTarifSpecial($id_client, $date);
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
    }

    public function getUnitesApplicablesPourClient($id_client, $date = null) {
        if ($this->useNewTarifController) {
            try {
                $date = $date ?: date('Y-m-d');
                $unites = $this->tarifControleur->getUnitesApplicablesPourClient($id_client, $date);
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
                $id_service = $serviceResult['id'];

                // 2. Associer les unités
                foreach ($unitesIds as $index => $id_unite) {
                    $linkResult = $this->linkServiceUnite($id_service, $id_unite, false);
                    if (!$linkResult['success']) {
                        return $linkResult;
                    }
                    
                    // Définir la première unité comme défaut
                    if ($index === 0) {
                        $defaultResult = $this->updateServiceUniteDefault($id_service, $id_unite, false);
                        if (!$defaultResult['success']) {
                            return $defaultResult;
                        }
                    }
                }

                // 3. Créer les tarifs
                foreach ($tarifsData as $tarifData) {
                    $tarifData['id_service'] = $id_service;
                    $tarifResult = $this->createTarif($tarifData, false);
                    if (!$tarifResult['success']) {
                        return $tarifResult;
                    }
                }

                return [
                    'success' => true,
                    'message' => 'Service complet créé avec succès',
                    'id_service' => $id_service
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
     * @param int $id_service ID du service
     * @param array $nouveauxTarifs Nouveaux tarifs à appliquer
     * @param bool $supprimerAnciens Supprimer les anciens tarifs
     * @return array Résultat de l'opération
     */
    public function updateTarifsService($id_service, $nouveauxTarifs, $supprimerAnciens = false) {
        return $this->executeWithTransaction(function() use ($id_service, $nouveauxTarifs, $supprimerAnciens) {
            try {
                // 1. Supprimer les anciens tarifs si demandé
                if ($supprimerAnciens) {
                    $anciensResult = $this->getAllTarifs($id_service);
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
                    $tarifData['id_service'] = $id_service;
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
     * Applique des tarifs spéciaux en masse pour un client
     * @param int $id_client ID du client
     * @param array $tarifsSpeciaux Tarifs spéciaux à appliquer
     * @return array Résultat de l'opération
     */
    public function appliquerTarifsSpeciauxMasse($id_client, $tarifsSpeciaux) {
        return $this->executeWithTransaction(function() use ($id_client, $tarifsSpeciaux) {
            try {
                $tarifsAppliques = 0;
                $erreurs = [];
                
                foreach ($tarifsSpeciaux as $index => $tarifData) {
                    $tarifData['id_client'] = $id_client;
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
                        $erreurs[] = "Service '{$service['nom_service']}' n'a aucune unité associée";
                    }
                }
            }

            // Vérifier les tarifs orphelins
            $tarifsResult = $this->getAllTarifs();
            if ($tarifsResult['success']) {
                foreach ($tarifsResult['tarifs'] as $tarif) {
                    $serviceResult = $this->getServiceById($tarif['id_service']);
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

    /**
     * ==========================================
     * MÉTHODES DE MIGRATION ET COMPATIBILITÉ
     * ==========================================
     */

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
        // Cette méthode permet de migrer progressivement l'ancien système
        
        return $tarifs;
    }

    /**
     * Active ou désactive l'utilisation des nouveaux contrôleurs
     * Utile pour la migration progressive
     */
    public function setControllerFlags($service = null, $unite = null, $tarif = null) {
        if ($service !== null) {
            $this->useNewServiceController = $service;
        }
        if ($unite !== null) {
            $this->useNewUniteController = $unite;
        }
        if ($tarif !== null) {
            $this->useNewTarifController = $tarif;
        }
    }

    /**
     * Retourne l'état des flags des contrôleurs
     */
    public function getControllerFlags() {
        return [
            'useNewServiceController' => $this->useNewServiceController,
            'useNewUniteController' => $this->useNewUniteController,
            'useNewTarifController' => $this->useNewTarifController
        ];
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
}
?>