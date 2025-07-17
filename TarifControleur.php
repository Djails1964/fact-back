<?php
// TarifControleur.php

class TarifControleur {
    /**
     * Récupère tous les services
     * 
     * @param PDO $conn La connexion à la base de données
     * @param bool $actif Filtre sur les services actifs uniquement
     * @return array Liste des services
     */
    public static function getServices($conn, $actif = true) {
        try {
            $sql = "SELECT id, code, nom, description, actif, isDefault FROM services";
            if ($actif) {
                $sql .= " WHERE actif = 1";
            }
            $sql .= " ORDER BY nom";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des services: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération des services');
        }
    }
    
    /**
     * Récupère un service par son ID
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id ID du service
     * @return array|null Les détails du service ou null si non trouvé
     */
    public static function getServiceById($conn, $id) {
        try {
            $sql = "SELECT id, code, nom, description, actif FROM services WHERE id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération du service: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération du service');
        }
    }
    

    /**
     * Crée un nouveau service
     * 
     * @param PDO $conn La connexion à la base de données
     * @param array $data Les données du service
     * @return int L'ID du service créé
     */
    public static function createService($conn, $data) {
        try {

            // Vérifier si le nouveau service est défini comme par défaut
            $isDefault = isset($data['isDefault']) ? ($data['isDefault'] ? 1 : 0) : 0;

            if ($isDefault) {
                // Désactiver tous les autres services par défaut
                $sqlResetDefault = "UPDATE services SET isDefault = 0 WHERE isDefault = 1";
                $stmtResetDefault = $conn->prepare($sqlResetDefault);
                $stmtResetDefault->execute();
            }

            // Insérer le nouveau service
            $sql = "INSERT INTO services (code, nom, description, actif, isDefault) VALUES (?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $data['code'],
                $data['nom'],
                $data['description'] ?? null,
                isset($data['actif']) ? $data['actif'] : true,
                $isDefault
            ]);
            
            $serviceId = $conn->lastInsertId();
            
            return $serviceId;
        } catch (PDOException $e) {
            // Annuler la transaction en cas d'erreur
            $conn->rollBack();
            error_log("Erreur lors de la création du service: " . $e->getMessage());
            throw new Exception('Erreur lors de la création du service: ' . $e->getMessage());
        }
    }

    
    /**
     * Met à jour un service existant
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id ID du service à mettre à jour
     * @param array $data Les données mises à jour
     * @return bool Succès de l'opération
     */
    public static function updateService($conn, $id, $data) {
        try {

            $setFields = [];
            $params = [];
            
            if (isset($data['code'])) {
                $setFields[] = "code = ?";
                $params[] = $data['code'];
            }
            
            if (isset($data['nom'])) {
                $setFields[] = "nom = ?";
                $params[] = $data['nom'];
            }
            
            if (isset($data['description'])) {
                $setFields[] = "description = ?";
                $params[] = $data['description'];
            }
            
            if (isset($data['actif'])) {
                $setFields[] = "actif = ?";
                $params[] = $data['actif'] ? 1 : 0;
            }
            
            // Gestion du champ isDefault
            if (isset($data['isDefault'])) {
                // Convertir en booléen
                $isDefault = $data['isDefault'] ? 1 : 0;
                
                if ($isDefault) {
                    // Désactiver tous les autres services par défaut
                    $sqlResetDefault = "UPDATE services SET isDefault = 0 WHERE isDefault = 1 AND id != ?";
                    $stmtResetDefault = $conn->prepare($sqlResetDefault);
                    $stmtResetDefault->execute([$id]);
                }
                
                $setFields[] = "isDefault = ?";
                $params[] = $isDefault;
            }
            
            if (empty($setFields)) {
                $conn->rollBack();
                return false;
            }
            
            $sql = "UPDATE services SET " . implode(", ", $setFields) . " WHERE id = ?";
            $params[] = $id;
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
         
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            // Annuler la transaction en cas d'erreur
            $conn->rollBack();
            error_log("Erreur lors de la mise à jour du service: " . $e->getMessage());
            throw new Exception('Erreur lors de la mise à jour du service');
        }
    }
    
    /**
     * Supprime un service
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id ID du service à supprimer
     * @return bool Succès de l'opération
     */
    public static function deleteService($conn, $id) {
        try {
            // Vérifier d'abord si le service est utilisé dans des liaisons ou des tarifs
            $checkSql = "SELECT 1 FROM services_unites WHERE service_id = ? 
                         UNION 
                         SELECT 1 FROM tarifs WHERE service_id = ? 
                         UNION 
                         SELECT 1 FROM tarifs_speciaux WHERE service_id = ? 
                         LIMIT 1";
            
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([$id, $id, $id]);
            
            if ($checkStmt->fetch()) {
                // Si le service est utilisé, on le désactive au lieu de le supprimer
                $sql = "UPDATE services SET actif = 0 WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$id]);
                
                return [
                    'success' => true,
                    'message' => 'Le service a été désactivé car il est utilisé dans des tarifs ou des liaisons',
                    'action' => 'desactive'
                ];
            } else {
                // Si le service n'est pas utilisé, on peut le supprimer
                $sql = "DELETE FROM services WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$id]);
                
                return [
                    'success' => true,
                    'message' => 'Le service a été supprimé avec succès',
                    'action' => 'supprime'
                ];
            }
        } catch (PDOException $e) {
            error_log("Erreur lors de la suppression du service: " . $e->getMessage());
            throw new Exception('Erreur lors de la suppression du service');
        }
    }
    
    /**
     * Récupère toutes les unités
     * 
     * @param PDO $conn La connexion à la base de données
     * @return array Liste des unités
     */
    public static function getUnites($conn) {
        try {
            // Récupérer toutes les unités avec leurs associations de service
            $sql = "SELECT u.id, u.code, u.nom, u.description 
                    FROM unites u
                    ORDER BY u.nom";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            
            // Retourne directement un tableau plat d'unités
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des unités: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération des unités');
        }
    }

    /**
     * Récupère toutes les relations actives entre services et unités
     * 
     * @param PDO $conn La connexion à la base de données
     * @return array Liste des relations services-unités
     */
    public static function getServicesUnites($conn) {
        try {
            $sql = "SELECT service_id, unite_id FROM services_unites WHERE actif = 1";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des relations services-unités: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération des relations services-unités');
        }
    }
    
    /**
     * Récupère les unités disponibles pour un service spécifique
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $serviceId ID du service
     * @return array Liste des unités liées au service
     */
    public static function getUnitesByService($conn, $serviceId) {
        try {
            $sql = "SELECT u.id, u.code, u.nom, u.description 
                    FROM unites u 
                    JOIN services_unites su ON u.id = su.unite_id 
                    WHERE su.service_id = ? AND su.actif = 1 
                    ORDER BY u.nom";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$serviceId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des unités par service: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération des unités par service');
        }
    }
    
    /**
     * Crée une nouvelle unité
     * 
     * @param PDO $conn La connexion à la base de données
     * @param array $data Les données de l'unité
     * @return int L'ID de l'unité créée
     */
    public static function createUnite($conn, $data) {
        try {
            // Insérer la nouvelle unité
            $sql = "INSERT INTO unites (code, nom, description) VALUES (?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $data['code'],
                $data['nom'],
                $data['description'] ?? null
            ]);
            
            $uniteId = $conn->lastInsertId();
    
            // Si un service est spécifié
            if (isset($data['service_id'])) {
                // Utiliser explicitement false par défaut
                $isDefault = isset($data['isDefault']) ? ($data['isDefault'] ? 1 : 0) : 0;
    
                if ($isDefault) {
                    // Désactiver toutes les autres unités par défaut pour ce service
                    $sqlResetDefault = "UPDATE services_unites SET isDefault = 0 
                                        WHERE service_id = ? AND isDefault = 1";
                    $stmtResetDefault = $conn->prepare($sqlResetDefault);
                    $stmtResetDefault->execute([$data['service_id']]);
                }
    
                // Créer la liaison service-unité avec l'état par défaut explicite
                $sqlLink = "INSERT INTO services_unites (service_id, unite_id, actif, isDefault) 
                            VALUES (?, ?, 1, ?)";
                $stmtLink = $conn->prepare($sqlLink);
                $stmtLink->execute([$data['service_id'], $uniteId, $isDefault]);
            }
    
            return $uniteId;
        } catch (PDOException $e) {
            error_log("Erreur lors de la création de l'unité: " . $e->getMessage());
            throw new Exception('Erreur lors de la création de l\'unité: ' . $e->getMessage());
        }
    }
    
    /**
     * Met à jour une unité existante
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id ID de l'unité à mettre à jour
     * @param array $data Les données mises à jour
     * @return bool Succès de l'opération
     */
    public static function updateUnite($conn, $id, $data) {
        try {
            $setFields = [];
            $params = [];
            
            if (isset($data['code'])) {
                $setFields[] = "code = ?";
                $params[] = $data['code'];
            }
            
            if (isset($data['nom'])) {
                $setFields[] = "nom = ?";
                $params[] = $data['nom'];
            }
            
            if (isset($data['description'])) {
                $setFields[] = "description = ?";
                $params[] = $data['description'];
            }
            
            // Vérifier le service lié à cette unité
            $sqlService = "SELECT service_id FROM services_unites WHERE unite_id = ?";
            $stmtService = $conn->prepare($sqlService);
            $stmtService->execute([$id]);
            $serviceId = $stmtService->fetchColumn();
            
            // Gestion du champ isDefault au niveau de la relation service-unité
            if (isset($data['isDefault']) && $serviceId) {
                // Convertir explicitement en booléen, avec false par défaut
                $isDefault = $data['isDefault'] ? 1 : 0;
                
                if ($isDefault) {
                    // Désactiver toutes les autres unités par défaut pour ce service
                    $sqlResetDefault = "UPDATE services_unites 
                                        SET isDefault = 0 
                                        WHERE service_id = ? AND isDefault = 1 AND unite_id != ?";
                    $stmtResetDefault = $conn->prepare($sqlResetDefault);
                    $stmtResetDefault->execute([$serviceId, $id]);
                }
                
                // Mettre à jour l'état par défaut de la relation service-unité
                $sqlUpdateDefault = "UPDATE services_unites 
                                     SET isDefault = ? 
                                     WHERE service_id = ? AND unite_id = ?";
                $stmtUpdateDefault = $conn->prepare($sqlUpdateDefault);
                $stmtUpdateDefault->execute([$isDefault, $serviceId, $id]);
            }
            
            // Mise à jour de l'unité
            if (empty($setFields)) {
                return false;
            }
            
            $sql = "UPDATE unites SET " . implode(", ", $setFields) . " WHERE id = ?";
            $params[] = $id;
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Erreur lors de la mise à jour de l'unité: " . $e->getMessage());
            throw new Exception('Erreur lors de la mise à jour de l\'unité');
        }
    }

    public static function updateServiceUniteDefault($conn, $serviceId, $uniteId) {
        try {
            // Désactiver toutes les autres unités par défaut pour ce service
            $sqlResetDefault = "UPDATE services_unites 
                                SET isDefault = 0 
                                WHERE service_id = ?";
            $stmtResetDefault = $conn->prepare($sqlResetDefault);
            $stmtResetDefault->execute([$serviceId]);
    
            // Définir la nouvelle unité par défaut
            $sqlSetDefault = "UPDATE services_unites 
                              SET isDefault = 1 
                              WHERE service_id = ? AND unite_id = ?";
            $stmtSetDefault = $conn->prepare($sqlSetDefault);
            $stmtSetDefault->execute([$serviceId, $uniteId]);
    
            return [
                'success' => true,
                'message' => 'Unité par défaut mise à jour avec succès'
            ];
        } catch (PDOException $e) {
            error_log("Erreur lors de la mise à jour de l'unité par défaut: " . $e->getMessage());
            throw new Exception('Erreur lors de la mise à jour de l\'unité par défaut');
        }
    }

    /**
     * Récupère l'ID de l'unité par défaut pour un service donné
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $serviceId ID du service
     * @return array Résultat contenant l'ID de l'unité par défaut
     */
    public static function getUniteDefautPourService($conn, $serviceId) {
        try {
            
            $sql = "SELECT unite_id FROM services_unites 
                    WHERE service_id = ? AND isDefault = 1 
                    LIMIT 1";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$serviceId]);
            
            $uniteId = $stmt->fetchColumn();
            
            return [
                'success' => true,
                'uniteId' => $uniteId ? intval($uniteId) : null
            ];
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération de l'unité par défaut: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'unité par défaut'
            ];
        }
    }
    
    /**
     * Supprime une unité
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id ID de l'unité à supprimer
     * @return array Résultat de l'opération
     */
    public static function deleteUnite($conn, $id) {
        try {
            // Vérifier d'abord si l'unité est utilisée dans des liaisons ou des tarifs
            $checkSql = "SELECT 1 FROM services_unites WHERE unite_id = ? 
                         UNION 
                         SELECT 1 FROM tarifs WHERE unite_id = ? 
                         UNION 
                         SELECT 1 FROM tarifs_speciaux WHERE unite_id = ? 
                         LIMIT 1";
            
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([$id, $id, $id]);
            
            if ($checkStmt->fetch()) {
                throw new Exception('Impossible de supprimer cette unité car elle est utilisée dans des tarifs ou des liaisons');
            } else {
                // Si l'unité n'est pas utilisée, on peut la supprimer
                $sql = "DELETE FROM unites WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$id]);
                
                return [
                    'success' => true,
                    'message' => 'L\'unité a été supprimée avec succès'
                ];
            }
        } catch (PDOException $e) {
            error_log("Erreur lors de la suppression de l'unité: " . $e->getMessage());
            throw new Exception('Erreur lors de la suppression de l\'unité');
        }
    }
    
    /**
     * Associe une unité à un service
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $serviceId ID du service
     * @param int $uniteId ID de l'unité
     * @return bool Succès de l'opération
     */
    public static function linkServiceUnite($conn, $serviceId, $uniteId) {
        try {
            // Vérifier si la liaison existe déjà
            $checkSql = "SELECT id, actif FROM services_unites WHERE service_id = ? AND unite_id = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([$serviceId, $uniteId]);
            $existingLink = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingLink) {
                // Si la liaison existe mais est inactive, la réactiver
                if (!$existingLink['actif']) {
                    $sql = "UPDATE services_unites SET actif = 1 WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$existingLink['id']]);
                    return true;
                }
                // Si la liaison existe et est déjà active, rien à faire
                return true;
            }
            
            // Sinon, créer une nouvelle liaison
            $sql = "INSERT INTO services_unites (service_id, unite_id, actif) VALUES (?, ?, 1)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$serviceId, $uniteId]);
            
            return true;
        } catch (PDOException $e) {
            error_log("Erreur lors de la liaison service-unité: " . $e->getMessage());
            throw new Exception('Erreur lors de la liaison service-unité');
        }
    }
    
    /**
     * Dissocie une unité d'un service
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $serviceId ID du service
     * @param int $uniteId ID de l'unité
     * @return array Résultat de l'opération
     */
    public static function unlinkServiceUnite($conn, $serviceId, $uniteId) {
        try {
            // Vérifier d'abord si cette liaison est utilisée dans des factures
            $checkFactureResult = self::checkServiceUniteUsageInFacture($conn, $serviceId, $uniteId);
            
            if ($checkFactureResult['isUsed']) {
            // Si la liaison est utilisée dans des factures, empêcher la dissociation
            return [
                'success' => false,
                'message' => $checkFactureResult['message'],
                'action' => 'impossible'
            ];
            }
            
            // Vérifier si cette liaison est utilisée dans des tarifs
            $checkSql = "SELECT 1 FROM tarifs WHERE service_id = ? AND unite_id = ? 
                        UNION 
                        SELECT 1 FROM tarifs_speciaux WHERE service_id = ? AND unite_id = ? 
                        LIMIT 1";
            
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([$serviceId, $uniteId, $serviceId, $uniteId]);
            
            if ($checkStmt->fetch()) {
            // Si la liaison est utilisée dans des tarifs, on la désactive
            $sql = "UPDATE services_unites SET actif = 0 WHERE service_id = ? AND unite_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$serviceId, $uniteId]);
            
            return [
                'success' => true,
                'message' => 'La liaison a été désactivée car elle est utilisée dans des tarifs',
                'action' => 'desactive'
            ];
            } else {
            // Si la liaison n'est pas utilisée, on peut la supprimer
            $sql = "DELETE FROM services_unites WHERE service_id = ? AND unite_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$serviceId, $uniteId]);
            
            return [
                'success' => true,
                'message' => 'La liaison a été supprimée avec succès',
                'action' => 'supprime'
            ];
            }
        } catch (PDOException $e) {
            error_log("Erreur lors de la dissociation service-unité: " . $e->getMessage());
            throw new Exception('Erreur lors de la dissociation service-unité');
        }
    }
    
    /**
     * Récupère tous les types de tarifs
     * 
     * @param PDO $conn La connexion à la base de données
     * @return array Liste des types de tarifs
     */
    public static function getTypesTarifs($conn) {
        try {
            $sql = "SELECT id, code, nom, description FROM types_tarifs ORDER BY id";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des types de tarifs: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération des types de tarifs');
        }
    }
    
    /**
     * Crée un nouveau type de tarif
     * 
     * @param PDO $conn La connexion à la base de données
     * @param array $data Les données du type de tarif
     * @return int L'ID du type de tarif créé
     */
    public static function createTypeTarif($conn, $data) {
        try {
            $sql = "INSERT INTO types_tarifs (code, nom, description) VALUES (?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $data['code'],
                $data['nom'],
                $data['description'] ?? null
            ]);
            
            return $conn->lastInsertId();
        } catch (PDOException $e) {
            error_log("Erreur lors de la création du type de tarif: " . $e->getMessage());
            throw new Exception('Erreur lors de la création du type de tarif: ' . $e->getMessage());
        }
    }
    
    /**
     * Met à jour un type de tarif existant
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id ID du type de tarif à mettre à jour
     * @param array $data Les données mises à jour
     * @return bool Succès de l'opération
     */
    public static function updateTypeTarif($conn, $id, $data) {
        try {
            $setFields = [];
            $params = [];
            
            if (isset($data['code'])) {
                $setFields[] = "code = ?";
                $params[] = $data['code'];
            }
            
            if (isset($data['nom'])) {
                $setFields[] = "nom = ?";
                $params[] = $data['nom'];
            }
            
            if (isset($data['description'])) {
                $setFields[] = "description = ?";
                $params[] = $data['description'];
            }
            
            if (empty($setFields)) {
                return false;
            }
            
            $sql = "UPDATE types_tarifs SET " . implode(", ", $setFields) . " WHERE id = ?";
            $params[] = $id;
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Erreur lors de la mise à jour du type de tarif: " . $e->getMessage());
            throw new Exception('Erreur lors de la mise à jour du type de tarif');
        }
    }
    
    /**
     * Supprime un type de tarif
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id ID du type de tarif à supprimer
     * @return array Résultat de l'opération
     */
    public static function deleteTypeTarif($conn, $id) {
        try {
            // Vérifier d'abord si le type de tarif est utilisé dans des tarifs
            $checkSql = "SELECT 1 FROM tarifs WHERE type_tarif_id = ? LIMIT 1";
            
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([$id]);
            
            if ($checkStmt->fetch()) {
                throw new Exception('Impossible de supprimer ce type de tarif car il est utilisé dans des tarifs');
            } else {
                // Si le type de tarif n'est pas utilisé, on peut le supprimer
                $sql = "DELETE FROM types_tarifs WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$id]);
                
                return [
                    'success' => true,
                    'message' => 'Le type de tarif a été supprimé avec succès'
                ];
            }
        } catch (PDOException $e) {
            error_log("Erreur lors de la suppression du type de tarif: " . $e->getMessage());
            throw new Exception('Erreur lors de la suppression du type de tarif');
        }
    }
    
    /**
     * Récupère les tarifs (standards et thérapeutes)
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int|null $serviceId Filtre par service
     * @param int|null $uniteId Filtre par unité
     * @param int|null $typeTarifId Filtre par type de tarif
     * @param string $date Date pour laquelle récupérer les tarifs valides
     * @return array Liste des tarifs
     */
    public static function getTarifs($conn, $serviceId = null, $uniteId = null, $typeTarifId = null, $date = null) {
        try {
            $params = [];
            $conditions = [];
            
            $sql = "SELECT t.id, t.service_id, t.unite_id, t.type_tarif_id, t.prix, t.date_debut, t.date_fin,
                    s.code as service_code, s.nom as service_nom,
                    u.code as unite_code, u.nom as unite_nom,
                    tt.code as type_tarif_code, tt.nom as type_tarif_nom
                    FROM tarifs t
                    JOIN services s ON t.service_id = s.id
                    JOIN unites u ON t.unite_id = u.id
                    JOIN types_tarifs tt ON t.type_tarif_id = tt.id
                    WHERE 1=1";
            
            if ($serviceId !== null) {
                $conditions[] = "t.service_id = ?";
                $params[] = $serviceId;
            }
            
            if ($uniteId !== null) {
                $conditions[] = "t.unite_id = ?";
                $params[] = $uniteId;
            }
            
            if ($typeTarifId !== null) {
                $conditions[] = "t.type_tarif_id = ?";
                $params[] = $typeTarifId;
            }
            
            if ($date !== null) {
                $conditions[] = "(t.date_debut <= ? AND (t.date_fin IS NULL OR t.date_fin >= ?))";
                $params[] = $date;
                $params[] = $date;
            }
            
            if (!empty($conditions)) {
                $sql .= " AND " . implode(" AND ", $conditions);
            }
            
            $sql .= " ORDER BY s.nom, u.nom, tt.nom, t.date_debut DESC";
            
            error_log("SQL Tarifs: " . $sql);
            error_log("Params Tarifs: " . json_encode($params));
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des tarifs: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération des tarifs');
        }
    }

    /**
     * Récupère tous les tarifs (standards), valides ou non
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int|null $serviceId Filtre optionnel par service
     * @param int|null $uniteId Filtre optionnel par unité
     * @param int|null $typeTarifId Filtre optionnel par type de tarif
     * @return array Liste de tous les tarifs
     */
    public static function getAllTarifs($conn, $serviceId = null, $uniteId = null, $typeTarifId = null) {
        try {
            $params = [];
            $conditions = [];
            
            $sql = "SELECT t.id, t.service_id, t.unite_id, t.type_tarif_id, t.prix, t.date_debut, t.date_fin,
                    s.code as service_code, s.nom as service_nom,
                    u.code as unite_code, u.nom as unite_nom,
                    tt.code as type_tarif_code, tt.nom as type_tarif_nom
                    FROM tarifs t
                    JOIN services s ON t.service_id = s.id
                    JOIN unites u ON t.unite_id = u.id
                    JOIN types_tarifs tt ON t.type_tarif_id = tt.id
                    WHERE 1=1";
            
            if ($serviceId !== null) {
                $conditions[] = "t.service_id = ?";
                $params[] = $serviceId;
            }
            
            if ($uniteId !== null) {
                $conditions[] = "t.unite_id = ?";
                $params[] = $uniteId;
            }
            
            if ($typeTarifId !== null) {
                $conditions[] = "t.type_tarif_id = ?";
                $params[] = $typeTarifId;
            }
            
            if (!empty($conditions)) {
                $sql .= " AND " . implode(" AND ", $conditions);
            }
            
            $sql .= " ORDER BY s.nom, u.nom, tt.nom, t.date_debut DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération de tous les tarifs: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération de tous les tarifs');
        }
    }

    /**
     * Récupère tous les tarifs spéciaux, valides ou non
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int|null $clientId Filtre optionnel par client
     * @param int|null $serviceId Filtre optionnel par service
     * @param int|null $uniteId Filtre optionnel par unité
     * @return array Liste de tous les tarifs spéciaux
     */
    public static function getAllTarifsSpeciaux($conn, $clientId = null, $serviceId = null, $uniteId = null) {
        try {
            $params = [];
            $conditions = [];
            
            $sql = "SELECT ts.id, ts.client_id, ts.service_id, ts.unite_id, ts.prix, ts.date_debut, ts.date_fin, ts.note,
                    c.nom as client_nom, c.prenom as client_prenom,
                    s.code as service_code, s.nom as service_nom,
                    u.code as unite_code, u.nom as unite_nom
                    FROM tarifs_speciaux ts
                    JOIN client c ON ts.client_id = c.id
                    JOIN services s ON ts.service_id = s.id
                    JOIN unites u ON ts.unite_id = u.id
                    WHERE 1=1";
            
            if ($clientId !== null) {
                $conditions[] = "ts.client_id = ?";
                $params[] = $clientId;
            }
            
            if ($serviceId !== null) {
                $conditions[] = "ts.service_id = ?";
                $params[] = $serviceId;
            }
            
            if ($uniteId !== null) {
                $conditions[] = "ts.unite_id = ?";
                $params[] = $uniteId;
            }
            
            if (!empty($conditions)) {
                $sql .= " AND " . implode(" AND ", $conditions);
            }
            
            $sql .= " ORDER BY c.nom, s.nom, u.nom, ts.date_debut DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération de tous les tarifs spéciaux: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération de tous les tarifs spéciaux');
        }
    }
    
    /**
     * Crée un nouveau tarif
     * 
     * @param PDO $conn La connexion à la base de données
     * @param array $data Les données du tarif
     * @return int L'ID du tarif créé
     */
    public static function createTarif($conn, $data) {
        try {
            // Vérifier si un tarif existe déjà pour ce service, cette unité et ce type de tarif
            $checkSql = "SELECT id FROM tarifs 
                         WHERE service_id = ? AND unite_id = ? AND type_tarif_id = ? 
                         AND date_debut <= ? AND (date_fin IS NULL OR date_fin >= ?)";
                         
            // $dateDebut = isset($data['date_debut']) ? $data['date_debut'] : date('Y-m-d');
            
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([
                $data['serviceId'],
                $data['uniteId'],
                $data['typeTarifId'],
                isset($data['date_debut']) ? $data['date_debut'] : date('Y-m-d'),
                isset($data['date_fin']) ? $data['date_fin'] : null
            ]);
            
            // Si un tarif existe déjà pour cette période, on le met à jour
            if ($existingTarif = $checkStmt->fetch(PDO::FETCH_ASSOC)) {
                $sql = "UPDATE tarifs SET prix = ?, date_fin = ? WHERE id = ?";
                
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $data['prix'],
                    isset($data['date_fin']) ? $data['date_fin'] : null,
                    $existingTarif['id']
                ]);
                
                return $existingTarif['id'];
            }
            
            // Sinon, on crée un nouveau tarif
            $sql = "INSERT INTO tarifs (service_id, unite_id, type_tarif_id, prix, date_debut, date_fin) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $data['serviceId'],
                $data['uniteId'],
                $data['typeTarifId'],
                $data['prix'],
                isset($data['date_debut']) ? $data['date_debut'] : date('Y-m-d'),
                isset($data['date_fin']) ? $data['date_fin'] : null
            ]);
            
            return $conn->lastInsertId();
        } catch (PDOException $e) {
            error_log("Erreur lors de la création du tarif: " . $e->getMessage());
            throw new Exception('Erreur lors de la création du tarif: ' . $e->getMessage());
        }
    }
    
    /**
     * Met à jour un tarif existant
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id ID du tarif à mettre à jour
     * @param array $data Les données mises à jour
     * @return bool Succès de l'opération
     */
    public static function updateTarif($conn, $id, $data) {
        try {
            $setFields = [];
            $params = [];
            
            if (isset($data['prix'])) {
                $setFields[] = "prix = ?";
                $params[] = $data['prix'];
            }
            
            if (isset($data['date_debut'])) {
                $setFields[] = "date_debut = ?";
                $params[] = $data['date_debut'];
            }
            
            if (array_key_exists('date_fin', $data)) {
                $setFields[] = "date_fin = ?";
                
                // Convertir les chaînes vides en NULL
                $params[] = (isset($data['date_fin']) && $data['date_fin'] !== '') ? $data['date_fin'] : null;
            }
            
            if (empty($setFields)) {
                return false;
            }
            
            $sql = "UPDATE tarifs SET " . implode(", ", $setFields) . " WHERE id = ?";
            $params[] = $id;
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Erreur lors de la mise à jour du tarif: " . $e->getMessage());
            throw new Exception('Erreur lors de la mise à jour du tarif');
        }
    }
    
    /**
     * Supprime un tarif
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id ID du tarif à supprimer
     * @return bool Succès de l'opération
     */
    public static function deleteTarif($conn, $id) {
        try {
            $sql = "DELETE FROM tarifs WHERE id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id]);
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Erreur lors de la suppression du tarif: " . $e->getMessage());
            throw new Exception('Erreur lors de la suppression du tarif');
        }
    }
    
        /**
     * Récupère les tarifs spéciaux par client
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int|null $clientId Filtre par client
     * @param int|null $serviceId Filtre par service
     * @param int|null $uniteId Filtre par unité
     * @param string $date Date pour laquelle récupérer les tarifs valides
     * @return array Liste des tarifs spéciaux
     */
    public static function getTarifsSpeciaux($conn, $clientId = null, $serviceId = null, $uniteId = null, $date = null) {
        try {
            $params = [];
            $conditions = [];
            
            $sql = "SELECT ts.id, ts.client_id, ts.service_id, ts.unite_id, ts.prix, ts.date_debut, ts.date_fin, ts.note,
                    c.nom as client_nom, c.prenom as client_prenom,
                    s.code as service_code, s.nom as service_nom,
                    u.code as unite_code, u.nom as unite_nom
                    FROM tarifs_speciaux ts
                    JOIN client c ON ts.client_id = c.id
                    JOIN services s ON ts.service_id = s.id
                    JOIN unites u ON ts.unite_id = u.id
                    WHERE 1=1";
            
            if ($clientId !== null) {
                $conditions[] = "ts.client_id = ?";
                $params[] = $clientId;
            }
            
            if ($serviceId !== null) {
                $conditions[] = "ts.service_id = ?";
                $params[] = $serviceId;
            }
            
            if ($uniteId !== null) {
                $conditions[] = "ts.unite_id = ?";
                $params[] = $uniteId;
            }
            
            if ($date !== null) {
                $conditions[] = "(ts.date_debut <= ? AND (ts.date_fin IS NULL OR ts.date_fin >= ?))";
                $params[] = $date;
                $params[] = $date;
            }
            
            if (!empty($conditions)) {
                $sql .= " AND " . implode(" AND ", $conditions);
            }
            
            $sql .= " ORDER BY c.nom, s.nom, u.nom, ts.date_debut DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des tarifs spéciaux: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération des tarifs spéciaux');
        }
    }

    /**
     * Récupère le tarif applicable pour un client spécifique
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $clientId ID du client
     * @param int $serviceId ID du service
     * @param int $uniteId ID de l'unité
     * @param string $date Date pour laquelle récupérer le tarif valide
     * @return array Résultat contenant le tarif applicable
     */
    public static function getTarifClient($conn, $clientId, $serviceId, $uniteId, $date) {
        try {
            // Récupérer le statut thérapeute du client
            $sqlClientType = "SELECT estTherapeute FROM client WHERE id = ?";
            $stmtClientType = $conn->prepare($sqlClientType);
            $stmtClientType->execute([$clientId]);
            $estTherapeute = $stmtClientType->fetchColumn();
    
            // D'abord, chercher un tarif spécial pour le client
            $sqlSpecial = "SELECT prix, 'special' as type 
                           FROM tarifs_speciaux 
                           WHERE client_id = ? AND service_id = ? AND unite_id = ? 
                           AND date_debut <= ? AND (date_fin IS NULL OR date_fin >= ?)
                           ORDER BY date_debut DESC 
                           LIMIT 1";
            
            $stmtSpecial = $conn->prepare($sqlSpecial);
            $stmtSpecial->execute([$clientId, $serviceId, $uniteId, $date, $date]);
            $tarifSpecial = $stmtSpecial->fetch(PDO::FETCH_ASSOC);
            
            if ($tarifSpecial) {
                return [
                    'success' => true,
                    'tarif' => $tarifSpecial
                ];
            }
            
            // Déterminer le type de tarif à chercher
            $typeTarifId = null;
            $tarifType = null;
            if ($estTherapeute) {
                // Pour un client thérapeute, chercher un tarif thérapeute
                $sqlTypeTarif = "SELECT id, code FROM types_tarifs WHERE code = 'therapeute'";
                $tarifType = 'therapeute';
            } else {
                // Pour un client standard, chercher un tarif standard
                $sqlTypeTarif = "SELECT id, code FROM types_tarifs WHERE code = 'normal'";
                $tarifType = 'standard';
            }
            
            $stmtTypeTarif = $conn->prepare($sqlTypeTarif);
            $stmtTypeTarif->execute();
            $typeTarifInfo = $stmtTypeTarif->fetch(PDO::FETCH_ASSOC);
            
            if (!$typeTarifInfo) {
                return [
                    'success' => false,
                    'message' => 'Type de tarif non trouvé'
                ];
            }
            
            // Chercher un tarif selon le type de client
            $sqlStandard = "SELECT t.prix, ? as type, 
                            s.code as service_code, s.nom as service_nom,
                            u.code as unite_code, u.nom as unite_nom,
                            tt.code as type_tarif_code, tt.nom as type_tarif_nom
                            FROM tarifs t
                            JOIN services s ON t.service_id = s.id
                            JOIN unites u ON t.unite_id = u.id
                            JOIN types_tarifs tt ON t.type_tarif_id = tt.id
                            WHERE t.service_id = ? AND t.unite_id = ? AND t.type_tarif_id = ?
                            AND t.date_debut <= ? AND (t.date_fin IS NULL OR t.date_fin >= ?)
                            ORDER BY t.date_debut DESC 
                            LIMIT 1";
            
            $stmtStandard = $conn->prepare($sqlStandard);
            $stmtStandard->execute([
                $tarifType,  // Passage explicite du type de tarif 
                $serviceId, 
                $uniteId, 
                $typeTarifInfo['id'], 
                $date, 
                $date
            ]);
            $tarifStandard = $stmtStandard->fetch(PDO::FETCH_ASSOC);
            
            if ($tarifStandard) {
                return [
                    'success' => true,
                    'tarif' => $tarifStandard
                ];
            }
            
            // Aucun tarif trouvé
            return [
                'success' => false,
                'message' => 'Aucun tarif trouvé pour le service, l\'unité et la date spécifiés'
            ];
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération du tarif client: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération du tarif client');
        }
    }
    
    /**
     * Crée un tarif spécial pour un client
     * 
     * @param PDO $conn La connexion à la base de données
     * @param array $data Les données du tarif spécial
     * @return int L'ID du tarif spécial créé
     */
    public static function createTarifSpecial($conn, $data) {
        try {
            // Vérifier que la note n'est pas vide
            if (!isset($data['note']) || trim($data['note']) === '') {
                throw new Exception('La note est obligatoire pour un tarif spécial');
            }
            
            // Vérifier si un tarif spécial existe déjà pour ce client, ce service et cette unité
            $checkSql = "SELECT id FROM tarifs_speciaux 
                        WHERE client_id = ? AND service_id = ? AND unite_id = ? 
                        AND date_debut <= ? AND (date_fin IS NULL OR date_fin >= ?)";
                        
            // $dateDebut = isset($data['date_debut']) ? $data['date_debut'] : date('Y-m-d');
            
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([
                $data['clientId'],
                $data['serviceId'],
                $data['uniteId'],
                isset($data['date_debut']) ? $data['date_debut'] : date('Y-m-d'), // Correction ici
                isset($data['date_fin']) ? $data['date_fin'] : null, // Correction ici
            ]);
            
            // Si un tarif spécial existe déjà pour cette période, on le met à jour
            if ($existingTarif = $checkStmt->fetch(PDO::FETCH_ASSOC)) {
                $sql = "UPDATE tarifs_speciaux SET prix = ?, date_fin = ?, note = ? WHERE id = ?";
                
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $data['prix'],
                    isset($data['date_fin']) ? $data['date_fin'] : null,
                    $data['note'],  // La note n'est plus optionnelle
                    $existingTarif['id']
                ]);
                
                return $existingTarif['id'];
            }
            
            // Sinon, on crée un nouveau tarif spécial
            $sql = "INSERT INTO tarifs_speciaux (client_id, service_id, unite_id, prix, date_debut, date_fin, note) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $data['clientId'],
                $data['serviceId'],
                $data['uniteId'],
                $data['prix'],
                isset($data['date_debut']) ? $data['date_debut'] : date('Y-m-d'),
                isset($data['date_fin']) ? $data['date_fin'] : null,
                $data['note']  // La note n'est plus optionnelle
            ]);
            
            return $conn->lastInsertId();
        } catch (PDOException $e) {
            error_log("Erreur lors de la création du tarif spécial: " . $e->getMessage());
            throw new Exception('Erreur lors de la création du tarif spécial: ' . $e->getMessage());
        }
    }

    /**
     * Met à jour un tarif spécial existant
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id ID du tarif spécial à mettre à jour
     * @param array $data Les données mises à jour
     * @return bool Succès de l'opération
     */
    public static function updateTarifSpecial($conn, $id, $data) {
        try {
            $setFields = [];
            $params = [];
            
            if (isset($data['prix'])) {
                $setFields[] = "prix = ?";
                $params[] = $data['prix'];
            }
            
            if (isset($data['date_debut'])) {
                $setFields[] = "date_debut = ?";
                $params[] = $data['date_debut'];
            }
            
            if (array_key_exists('date_fin', $data)) {
                $setFields[] = "date_fin = ?";
                // Convertir les chaînes vides en NULL
                $params[] = (isset($data['date_fin']) && $data['date_fin'] !== '') ? $data['date_fin'] : null;
            }
            
            if (isset($data['note'])) {
                // Vérifier que la note n'est pas vide
                if (trim($data['note']) === '') {
                    throw new Exception('La note est obligatoire pour un tarif spécial');
                }
                
                $setFields[] = "note = ?";
                $params[] = $data['note'];
            }
            
            if (empty($setFields)) {
                return false;
            }
            
            $sql = "UPDATE tarifs_speciaux SET " . implode(", ", $setFields) . " WHERE id = ?";
            $params[] = $id;
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Erreur lors de la mise à jour du tarif spécial: " . $e->getMessage());
            throw new Exception('Erreur lors de la mise à jour du tarif spécial');
        }
    }
    
    /**
     * Supprime un tarif spécial
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id ID du tarif spécial à supprimer
     * @return bool Succès de l'opération
     */
    public static function deleteTarifSpecial($conn, $id) {
        try {
            $sql = "DELETE FROM tarifs_speciaux WHERE id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id]);
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Erreur lors de la suppression du tarif spécial: " . $e->getMessage());
            throw new Exception('Erreur lors de la suppression du tarif spécial');
        }
    }

    /**
     * Vérifie si un service est utilisé dans des factures ou des tarifs
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id ID du service à vérifier
     * @return array Résultat de la vérification
     */
    public static function checkServiceUsage($conn, $id) {
        try {
            // Vérifier d'abord si le service est utilisé dans des liaisons ou des tarifs
            $checkSql = "SELECT 1 FROM services_unites WHERE service_id = ? 
                        UNION 
                        SELECT 1 FROM tarifs WHERE service_id = ? 
                        UNION 
                        SELECT 1 FROM tarifs_speciaux WHERE service_id = ? 
                        LIMIT 1";
            
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([$id, $id, $id]);
            
            $isUsed = (bool)$checkStmt->fetch();
            
            return [
            'success' => true,
            'isUsed' => $isUsed,
            'message' => $isUsed ? 'Ce service est utilisé dans des tarifs ou des liaisons et ne peut pas être supprimé.' : 'Ce service peut être supprimé en toute sécurité.'
            ];
        } catch (PDOException $e) {
            error_log("Erreur lors de la vérification de l'utilisation du service: " . $e->getMessage());
            throw new Exception('Erreur lors de la vérification de l\'utilisation du service');
        }
    }

    /**
     * Vérifie si une liaison service-unité est utilisée dans des factures
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $serviceId ID du service
     * @param int $uniteId ID de l'unité
     * @return array Résultat de la vérification
     */
    public static function checkServiceUniteUsageInFacture($conn, $serviceId, $uniteId) {
        try {
            // Vérifier si la liaison est utilisée dans des lignes de facture
            $checkSql = "SELECT COUNT(*) as total FROM lignesfacture 
                        WHERE service_id = ? AND unite_id = ?";
            
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([$serviceId, $uniteId]);
            
            $count = $checkStmt->fetch(PDO::FETCH_ASSOC)['total'];
            $isUsed = $count > 0;
            
            return [
            'success' => true,
            'isUsed' => $isUsed,
            'count' => $count,
            'message' => $isUsed 
                ? "Cette liaison est utilisée dans $count ligne(s) de facture et ne peut pas être supprimée." 
                : "Cette liaison peut être supprimée en toute sécurité."
            ];
        } catch (PDOException $e) {
            error_log("Erreur lors de la vérification de l'utilisation de la liaison dans les factures: " . $e->getMessage());
            throw new Exception('Erreur lors de la vérification de l\'utilisation de la liaison dans les factures');
        }
    }

    /**
     * Vérifie si un type de tarif est utilisé dans des tarifs
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id ID du type de tarif à vérifier
     * @return array Résultat de la vérification
     */
    public static function checkTypeTarifUsage($conn, $id) {
        try {
            // Vérifier si le type de tarif est utilisé dans des tarifs
            $checkSql = "SELECT COUNT(*) as total FROM tarifs WHERE type_tarif_id = ?";
            
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([$id]);
            
            $count = $checkStmt->fetch(PDO::FETCH_ASSOC)['total'];
            $isUsed = $count > 0;
            
            return [
            'success' => true,
            'isUsed' => $isUsed,
            'count' => $count,
            'message' => $isUsed 
                ? "Ce type de tarif est utilisé dans $count tarif(s) et ne peut pas être supprimé." 
                : "Ce type de tarif peut être supprimé en toute sécurité."
            ];
        } catch (PDOException $e) {
            error_log("Erreur lors de la vérification de l'utilisation du type de tarif: " . $e->getMessage());
            throw new Exception('Erreur lors de la vérification de l\'utilisation du type de tarif');
        }
    }

    /**
     * Vérifie si une unité est utilisée dans des factures ou des tarifs
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id ID de l'unité à vérifier
     * @return array Résultat de la vérification
     */
    public static function checkUniteUsage($conn, $id) {
        try {
            // Vérifier d'abord si l'unité est utilisée dans des liaisons ou des tarifs
            $checkSql = "SELECT 1 FROM services_unites WHERE unite_id = ? 
                        UNION 
                        SELECT 1 FROM tarifs WHERE unite_id = ? 
                        UNION 
                        SELECT 1 FROM tarifs_speciaux WHERE unite_id = ? 
                        LIMIT 1";
            
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([$id, $id, $id]);
            
            $isUsed = (bool)$checkStmt->fetch();
            
            return [
            'success' => true,
            'isUsed' => $isUsed,
            'message' => $isUsed ? 'Cette unité est utilisée dans des tarifs ou des liaisons et ne peut pas être supprimée.' : 'Cette unité peut être supprimée en toute sécurité.'
            ];
        } catch (PDOException $e) {
            error_log("Erreur lors de la vérification de l'utilisation de l'unité: " . $e->getMessage());
            throw new Exception('Erreur lors de la vérification de l\'utilisation de l\'unité');
        }
    }

    /**
     * Vérifie si un tarif standard est utilisé dans des factures
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id ID du tarif standard à vérifier
     * @return array Résultat de la vérification
     */
    public static function checkTarifUsage($conn, $id) {
        try {
            // D'abord récupérer les détails du tarif
            $sqlTarif = "SELECT service_id, unite_id FROM tarifs WHERE id = ?";
            $stmtTarif = $conn->prepare($sqlTarif);
            $stmtTarif->execute([$id]);
            
            $tarif = $stmtTarif->fetch(PDO::FETCH_ASSOC);
            
            if (!$tarif) {
            return [
                'success' => false,
                'message' => 'Tarif non trouvé'
            ];
            }
            
            // Vérifier si la combinaison service_id et unite_id est utilisée dans des factures
            $sqlCheck = "SELECT COUNT(*) as total 
                        FROM lignesfacture 
                        WHERE service_id = ? AND unite_id = ?";
            
            $stmtCheck = $conn->prepare($sqlCheck);
            $stmtCheck->execute([
            $tarif['service_id'], 
            $tarif['unite_id']
            ]);
            
            $count = $stmtCheck->fetch(PDO::FETCH_ASSOC)['total'];
            $isUsed = $count > 0;
            
            return [
            'success' => true,
            'isUsed' => $isUsed,
            'count' => $count,
            'message' => $isUsed 
                ? "Ce tarif est utilisé dans $count ligne(s) de facture et ne peut pas être supprimé." 
                : "Ce tarif peut être supprimé en toute sécurité."
            ];
        } catch (PDOException $e) {
            error_log("Erreur lors de la vérification de l'utilisation du tarif: " . $e->getMessage());
            throw new Exception('Erreur lors de la vérification de l\'utilisation du tarif');
        }
    }

    /**
     * Vérifie si un tarif spécial est utilisé dans des factures
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id ID du tarif spécial à vérifier
     * @return array Résultat de la vérification
     */
    public static function checkTarifSpecialUsage($conn, $id) {
        try {
            // D'abord récupérer les détails du tarif spécial
            $sqlTarifSpecial = "SELECT client_id, service_id, unite_id FROM tarifs_speciaux WHERE id = ?";
            $stmtTarifSpecial = $conn->prepare($sqlTarifSpecial);
            $stmtTarifSpecial->execute([$id]);
            
            $tarifSpecial = $stmtTarifSpecial->fetch(PDO::FETCH_ASSOC);
            
            if (!$tarifSpecial) {
            return [
                'success' => false,
                'message' => 'Tarif spécial non trouvé'
            ];
            }
            
            // Vérifier si la combinaison client_id, service_id et unite_id est utilisée dans des factures
            $sqlCheck = "SELECT COUNT(*) as total 
                        FROM lignesfacture lf 
                        JOIN facture f ON lf.id_facture = f.id_facture 
                        WHERE f.id_client = ? AND lf.service_id = ? AND lf.unite_id = ?";
            
            $stmtCheck = $conn->prepare($sqlCheck);
            $stmtCheck->execute([
            $tarifSpecial['client_id'], 
            $tarifSpecial['service_id'], 
            $tarifSpecial['unite_id']
            ]);
            
            $count = $stmtCheck->fetch(PDO::FETCH_ASSOC)['total'];
            $isUsed = $count > 0;
            
            return [
            'success' => true,
            'isUsed' => $isUsed,
            'count' => $count,
            'message' => $isUsed 
                ? "Ce tarif spécial est utilisé dans $count ligne(s) de facture et ne peut pas être supprimé." 
                : "Ce tarif spécial peut être supprimé en toute sécurité."
            ];
        } catch (PDOException $e) {
            error_log("Erreur lors de la vérification de l'utilisation du tarif spécial: " . $e->getMessage());
            throw new Exception('Erreur lors de la vérification de l\'utilisation du tarif spécial');
        }
    }

    /**
     * Vérifie si un client est thérapeute
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $clientId ID du client
     * @return bool True si le client est thérapeute, false sinon
     */
    public static function estTherapeute($conn, $clientId) {
        try {
            $sql = "SELECT estTherapeute FROM client WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$clientId]);
            
            $result = $stmt->fetchColumn();
            return (bool)$result;
        } catch (PDOException $e) {
            error_log("Erreur lors de la vérification du statut thérapeute: " . $e->getMessage());
            throw new Exception('Erreur lors de la vérification du statut thérapeute');
        }
    }

    /**
     * Vérifie si un client possède au moins un tarif spécial valide à la date spécifiée
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $clientId ID du client
     * @param string|null $date Date pour laquelle vérifier la validité des tarifs spéciaux
     * @return bool True si le client possède au moins un tarif spécial valide, false sinon
     */
    public static function possedeTarifSpecialDefini($conn, $clientId, $date = null) {
        try {
            $date = $date ?: date('Y-m-d');
            
            $sql = "SELECT COUNT(*) FROM tarifs_speciaux 
                    WHERE client_id = ? 
                    AND date_debut <= ? 
                    AND (date_fin IS NULL OR date_fin >= ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$clientId, $date, $date]);
            
            $count = $stmt->fetchColumn();
            return $count > 0;
        } catch (PDOException $e) {
            error_log("Erreur lors de la vérification des tarifs spéciaux: " . $e->getMessage());
            throw new Exception('Erreur lors de la vérification des tarifs spéciaux');
        }
    }

    /**
     * Récupère toutes les unités applicables pour un client spécifique
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $clientId ID du client
     * @param string $date Date pour laquelle récupérer les unités avec tarifs valides
     * @return array Liste des unités avec leurs détails
     */
    public static function getUnitesApplicablesPourClient($conn, $clientId, $date) {
        try {
            // Vérifier si le client est thérapeute pour déterminer le type de tarif approprié
            $estTherapeute = self::estTherapeute($conn, $clientId);
            $typeTarifCode = $estTherapeute ? 'Therapeute' : 'Normal';
            
            // Récupérer l'ID du type de tarif
            $sqlTypeTarif = "SELECT id FROM types_tarifs WHERE code = ?";
            $stmtTypeTarif = $conn->prepare($sqlTypeTarif);
            $stmtTypeTarif->execute([$typeTarifCode]);
            $typeTarifId = $stmtTypeTarif->fetchColumn();
            
            if (!$typeTarifId) {
                // Si le type de tarif n'est pas trouvé, utiliser le tarif normal (id = 1)
                $typeTarifId = 1;
            }
            
            // Requête SQL qui récupère les unités avec tarifs standards applicables au client
            // ainsi que les unités avec tarifs spéciaux pour ce client
            $sql = "
                SELECT DISTINCT u.id, u.code, u.nom, u.description, 
                   s.id as service_id, s.code as service_code, s.nom as service_nom,
                   CASE 
                       WHEN ts.unite_id IS NOT NULL THEN 'special'
                       ELSE 'standard'
                   END as type_tarif
                FROM unites u
                JOIN services_unites su ON u.id = su.unite_id AND su.actif = 1
                JOIN services s ON su.service_id = s.id AND s.actif = 1
                LEFT JOIN (
                    -- Sous-requête pour les tarifs standards
                    SELECT t.unite_id, t.service_id 
                    FROM tarifs t
                    WHERE t.type_tarif_id = ?
                    AND t.date_debut <= ?
                    AND (t.date_fin IS NULL OR t.date_fin >= ?)
                ) t ON u.id = t.unite_id AND t.service_id = s.id
                LEFT JOIN (
                    -- Sous-requête pour les tarifs spéciaux
                    SELECT ts.unite_id, ts.service_id
                    FROM tarifs_speciaux ts
                    WHERE ts.client_id = ?
                    AND ts.date_debut <= ?
                    AND (ts.date_fin IS NULL OR ts.date_fin >= ?)
                ) ts ON u.id = ts.unite_id AND ts.service_id = s.id
                WHERE t.unite_id IS NOT NULL OR ts.unite_id IS NOT NULL
                ORDER BY s.nom, u.nom
            ";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $typeTarifId,
                $date,
                $date,
                $clientId,
                $date,
                $date
            ]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur SQL lors de la récupération des unités pour le client: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération des unités pour le client');
        }
    }

}
?>