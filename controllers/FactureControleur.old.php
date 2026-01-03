<?php
/**
 * FactureControleur.php
 * 
 * Contrôleur pour la gestion des factures du Centre La Grange
 * Fonctionnalités : listing, création, modification, suppression, impression, etc.
 */

class FactureControleur {
    // ==================================================================================
    // MÉTHODES PRINCIPALES CRUD
    // ==================================================================================
    
    /**
     * Récupère toutes les factures avec filtrage par année optionnel
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int|null $annee Année pour filtrer les factures (optionnel)
     * @return array Liste des factures
     * @throws Exception En cas d'erreur lors de la récupération
     */
    public static function listerFactures($conn, $annee = null) {
        try {
            $sql = "SELECT f.*, c.nom, c.prenom, c.email
                    FROM facture f 
                    JOIN client c ON f.id_client = c.id";
            
            $params = [];
            
            // Si une année est spécifiée, ajouter le filtre d'année
            if ($annee !== null) {
                $sql .= " WHERE YEAR(f.date_facture) = ?";
                $params[] = $annee;
            }
            
            $sql .= " ORDER BY f.date_facture DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des factures: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération des factures');
        }
    }

    /**
     * Récupère une facture et ses lignes par son ID
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id ID de la facture à récupérer
     * @return array Données de la facture et ses lignes
     * @throws Exception Si la facture n'existe pas ou autre erreur
     */
    public static function getFactureParId($conn, $id) {
        try {
            // Récupérer les informations de la facture
            $sql = "SELECT f.*, c.nom, c.prenom, c.titre, c.rue, c.numero, c.code_postal, c.localite, c.telephone, c.email, c.estTherapeute
                    FROM facture f 
                    JOIN client c ON f.id_client = c.id 
                    WHERE f.id_facture = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id]);
            $facture = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$facture) {
                throw new Exception('Facture non trouvée');
            }
            
            // Récupérer les lignes de la facture
            $sqlLignes = "SELECT 
                id_ligne,
                id_facture,
                no_ordre,
                description,
                description_dates,
                unite,
                quantite,
                prix_unitaire,
                total_ligne,
                service_id AS id_service,
                unite_id AS id_unite
            FROM lignesfacture 
            WHERE id_facture = ? 
            ORDER BY no_ordre ASC";
            $stmtLignes = $conn->prepare($sqlLignes);
            $stmtLignes->execute([$id]);
            $lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);
            
            // Combiner les résultats
            $facture['lignes'] = $lignes;
            
            return $facture;
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération de la facture: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération de la facture');
        }
    }

    /**
     * Ajoute une nouvelle facture et ses lignes
     * 
     * @param PDO $conn La connexion à la base de données
     * @param array $data Les données de la facture
     * @return array Résultat de l'opération
     * @throws Exception En cas de données invalides ou d'erreur
     */
    public static function ajouterFacture($conn, $data) {
        // Validation des données
        if (is_dev_mode()) {
            error_log("Facturecontroleur - ajouterFacture - Démarrage de l'ajout de facture");
            error_log("Facturecontroleur - ajouterFacture - Vérification des données de la facture: " . print_r($data, true));
        }
        if (!isset($data['numero_facture']) || !isset($data['date_facture']) || 
            !isset($data['id_client']) || 
            !isset($data['lignes']) || !is_array($data['lignes'])) {
            throw new Exception('Données de facture incomplètes ou invalides');
        }

        try {
            // Calculer le montant total avec la méthode commune
            $ristourne = isset($data['ristourne']) ? floatval($data['ristourne']) : 0;
            $montantTotal = self::calculerMontantTotal($data['lignes'], $ristourne);
            
            // Insérer la facture
            $dateEdition = date('Y-m-d H:i:s'); // Date actuelle
            
            $stmt = $conn->prepare("INSERT INTO facture 
                (numero_facture, date_facture, montant_total, id_client, date_edition, ristourne) 
                VALUES (?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $data['numero_facture'],
                $data['date_facture'],
                $montantTotal, // Montant recalculé
                $data['id_client'],
                $dateEdition,
                $ristourne
            ]);
            
            // Récupérer l'ID de la facture créée
            $id_facture = $conn->lastInsertId();
            
            // Insérer les lignes de facture
            $stmtLignes = $conn->prepare("INSERT INTO lignesfacture 
                (id_facture, description, quantite, prix_unitaire, total_ligne, service_id, unite_id, no_ordre, description_dates) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            // Parcourir les lignes
            foreach ($data['lignes'] as $index => $ligne) {
                if (!isset($ligne['description']) ||  
                    !isset($ligne['quantite']) || !isset($ligne['prix_unitaire']) || 
                    !isset($ligne['total_ligne']) ||
                    !isset($ligne['id_service']) || !isset($ligne['id_unite'])) {
                    throw new Exception('Données de ligne de facture incomplètes');
                }
                
                // Utiliser l'index comme ordre si non fourni
                $noOrdre = isset($ligne['no_ordre']) ? $ligne['no_ordre'] : $index + 1;

                $stmtLignes->execute([
                    $id_facture,
                    $ligne['description'],
                    // $ligne['unite'] ?? null,
                    $ligne['quantite'],
                    $ligne['prix_unitaire'],
                    $ligne['total_ligne'],
                    $ligne['id_service'],
                    $ligne['id_unite'],
                    $noOrdre,
                    $ligne['description_dates'] ?? null // Nouveau champ
                ]);
            }

            // Mettre à jour le prochain numéro de facture
            self::mettreAJourProchainNumero($conn, $data['numero_facture']);

            return [
                'success' => true,
                'message' => 'Facture créée avec succès',
                'id_facture' => $id_facture,
                'numero_facture' => $data['numero_facture']
            ];
            
        } catch(PDOException $e) {
           error_log("Erreur SQL: " . $e->getMessage());
            throw new Exception('Erreur lors de l\'enregistrement de la facture: ' . $e->getMessage());
        }
    }

    /**
     * Modifie une facture existante
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id ID de la facture à modifier
     * @param array $data Les données de la facture
     * @return array Résultat de l'opération
     * @throws Exception En cas de données invalides ou d'erreur
     */
    public static function modifierFacture($conn, $id, $data) {
        // ✅ DEBUGGING amélioré - formatage sécurisé
        error_log("=== DEBUGGING FACTURE CONTROLEUR ===");
        error_log("Raw data type: " . gettype($data));
        error_log("Raw data content: " . print_r($data, true));
        
        // ✅ CORRECTION: Vérification de la structure des données
        if (!is_array($data)) {
            throw new Exception('Données de facture invalides - tableau attendu');
        }
        
        // ✅ CORRECTION: Gestion sécurisée des différents formats de id_client
        $id_client = null;
        if (isset($data['id_client'])) {
            $id_client = is_array($data['id_client']) ? $data['id_client']['id'] ?? $data['id_client'][0] : $data['id_client'];
        } elseif (isset($data['id_client'])) {
            $id_client = is_array($data['id_client']) ? $data['id_client']['id'] ?? $data['id_client'][0] : $data['id_client'];
        } elseif (isset($data['client'])) {
            $id_client = is_array($data['client']) ? $data['client']['id'] ?? $data['client']['idClient'] : $data['client'];
        }
        
        error_log("Client ID résolu: " . var_export($id_client, true));
        error_log("=== FIN DEBUGGING ===");
        
        // Validation des données avec messages d'erreur spécifiques
        $missingFields = [];
        
        if (!isset($data['numero_facture']) || empty($data['numero_facture'])) {
            $missingFields[] = 'numero_facture';
        }
        if (!isset($data['date_facture']) || empty($data['date_facture'])) {
            $missingFields[] = 'date_facture';
        }
        if (!$id_client) {
            $missingFields[] = 'id_client (id_client, id_client, ou client)';
        }
        if (!isset($data['lignes']) || !is_array($data['lignes'])) {
            $missingFields[] = 'lignes (doit être un tableau)';
        }
        
        if (!empty($missingFields)) {
            throw new Exception('Données de facture incomplètes ou invalides. Champs manquants: ' . implode(', ', $missingFields));
        }

        try {
            // Vérifier si la facture existe
            $checkSql = "SELECT id_facture, numero_facture as oldNumero FROM facture WHERE id_facture = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([$id]);
            
            if ($checkStmt->rowCount() === 0) {
                throw new Exception('Facture non trouvée');
            }

            // Calculer le montant total avec la méthode commune
            $ristourne = isset($data['ristourne']) ? floatval($data['ristourne']) : 0;
            $montantTotal = self::calculerMontantTotal($data['lignes'], $ristourne);
            
            // Mettre à jour la facture - ✅ CORRECTION: Utilisation de id_client résolu
            $stmt = $conn->prepare("UPDATE facture 
                SET numero_facture = ?, date_facture = ?, montant_total = ?, id_client = ?, ristourne = ?
                WHERE id_facture = ?");
            
            $stmt->execute([
                $data['numero_facture'],
                $data['date_facture'],
                $montantTotal,
                $id_client, // ✅ CORRECTION: Utilisation de la variable résolue
                $ristourne,
                $id
            ]);
            
            // Supprimer les anciennes lignes de facture
            $stmtDelete = $conn->prepare("DELETE FROM lignesfacture WHERE id_facture = ?");
            $stmtDelete->execute([$id]);
            
            // Insérer les nouvelles lignes de facture
            $stmtLignes = $conn->prepare("INSERT INTO lignesfacture 
                (id_facture, description, unite, quantite, prix_unitaire, total_ligne, service_id, unite_id, no_ordre, description_dates) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($data['lignes'] as $index => $ligne) {
                // ✅ CORRECTION: Validation plus robuste des lignes
                $requiredFields = ['description', 'unite', 'quantite', 'prix_unitaire', 'total_ligne', 'id_service', 'id_unite'];
                $missingLineFields = [];
                
                foreach ($requiredFields as $field) {
                    if (!isset($ligne[$field])) {
                        $missingLineFields[] = $field;
                    }
                }
                
                if (!empty($missingLineFields)) {
                    throw new Exception("Ligne $index: champs manquants - " . implode(', ', $missingLineFields));
                }
                
                // ✅ CORRECTION: Gestion sécurisée des valeurs de ligne
                $noOrdre = isset($ligne['noOrdre']) ? intval($ligne['noOrdre']) : $index + 1;
                $descriptionDates = isset($ligne['description_dates']) ? $ligne['description_dates'] : null;
                
                $stmtLignes->execute([
                    $id,
                    (string)$ligne['description'], // ✅ Cast explicite en string
                    // (string)$ligne['unite'],       // ✅ Cast explicite en string
                    null, // unité gérée séparément
                    floatval($ligne['quantite']),
                    floatval($ligne['prix_unitaire']),
                    floatval($ligne['total_ligne']),
                    intval($ligne['id_service']),
                    intval($ligne['id_unite']),
                    $noOrdre,
                    $descriptionDates
                ]);
            }

            // Mettre à jour le prochain numéro de facture si le numéro a changé
            $oldData = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if ($data['numero_facture'] !== $oldData['oldNumero']) {
                self::mettreAJourProchainNumero($conn, $data['numero_facture']);
            }

            return [
                'success' => true,
                'message' => 'Facture modifiée avec succès',
                'factureId' => $id
            ];
            
        } catch(PDOException $e) {
            error_log("Erreur SQL dans modifierFacture: " . $e->getMessage());
            error_log("Query info: " . print_r($e->errorInfo, true));
            throw new Exception('Erreur lors de la modification de la facture: ' . $e->getMessage());
        } catch(Exception $e) {
            error_log("Erreur générale dans modifierFacture: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Met à jour les informations d'édition d'une facture
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id ID de la facture
     * @param string $dateEdition Date d'édition
     * @param string $nomFichier Nom du fichier généré
     * @return array Résultat de l'opération
     */
    public static function mettreAJourEditionFacture($conn, $id, $dateEdition, $nomFichier) {
        try {
            $sql = "UPDATE facture SET date_edition = ?, factfilename = ? WHERE id_facture = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$dateEdition, $nomFichier, $id]);
            
            return [
                'success' => true,
                'message' => 'Informations d\'édition mises à jour'
            ];
        } catch (PDOException $e) {
            error_log("Erreur lors de la mise à jour de l'édition de facture: " . $e->getMessage());
            throw new Exception('Erreur lors de la mise à jour de l\'édition de facture: ' . $e->getMessage());
        }
    }

    /**
     * Supprime une facture et ses lignes
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id ID de la facture à supprimer
     * @return array Résultat de l'opération
     * @throws Exception En cas d'erreur
     */
    public static function supprimerFacture($conn, $id) {
        try {
            // Vérifier si la facture existe
            $checkSql = "SELECT id_facture FROM facture WHERE id_facture = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([$id]);
            
            if ($checkStmt->rowCount() === 0) {
                throw new Exception('Facture non trouvée');
            }
            
            // Supprimer d'abord les lignes de facture (contrainte de clé étrangère)
            $sqlLignes = "DELETE FROM lignesfacture WHERE id_facture = ?";
            $stmtLignes = $conn->prepare($sqlLignes);
            $stmtLignes->execute([$id]);
            
            // Puis supprimer la facture
            $sqlFacture = "DELETE FROM facture WHERE id_facture = ?";
            $stmtFacture = $conn->prepare($sqlFacture);
            $stmtFacture->execute([$id]);
            
            return [
                'success' => true,
                'message' => 'Facture supprimée avec succès',
                'factureId' => $id
            ];
            
        } catch(PDOException $e) {
            error_log("Erreur SQL: " . $e->getMessage());
            throw new Exception('Erreur lors de la suppression de la facture: ' . $e->getMessage());
        }
    }

    // ==================================================================================
    // MÉTHODES DE GESTION D'ÉTAT
    // ==================================================================================
    
    /**
     * Change l'état d'une facture (avec protection contre l'état "Retard")
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id ID de la facture à modifier
     * @param string $nouvelEtat Le nouvel état de la facture
     * @return array Résultat de l'opération
     * @throws Exception En cas d'erreur
     */
    public static function changerEtatFacture($conn, $id, $nouvelEtat) {
        try {
            // ✅ PROTECTION: Empêcher la persistance de l'état "Retard"
            if ($nouvelEtat === 'Retard') {
                error_log("⚠️ Tentative de persistance de l'état 'Retard' bloquée pour la facture ID: $id");
                throw new Exception('L\'état "Retard" ne peut pas être persisté. Il est calculé automatiquement côté client.');
            }
            
            // Vérifier si la facture existe
            $checkSql = "SELECT id_facture FROM facture WHERE id_facture = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([$id]);
            
            if ($checkStmt->rowCount() === 0) {
                throw new Exception('Facture non trouvée');
            }
            
            // Date du jour pour les opérations automatiques
            $dateCourante = date('Y-m-d');
            
            // En fonction du nouvel état, mettre à jour les champs appropriés
            switch ($nouvelEtat) {
                case 'Annulée':
                    // Pour l'état "Annulée", on met aussi à jour la date d'annulation
                    $sql = "UPDATE facture SET etat = ?, date_annulation = ? WHERE id_facture = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$nouvelEtat, $dateCourante, $id]);
                    break;
                    
                case 'Envoyée':
                    // Pour l'état "Envoyée", on met aussi à jour la date d'envoi
                    $sql = "UPDATE facture SET etat = ?, date_envoi = ? WHERE id_facture = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$nouvelEtat, $dateCourante, $id]);
                    break;
                    
                case 'Payée':
                case 'Éditée':
                case 'En attente':
                // ✅ SUPPRIMÉ: case 'Retard' (plus autorisé)
                default:
                    // Pour les autres états, simplement mettre à jour le champ d'état
                    $sql = "UPDATE facture SET etat = ? WHERE id_facture = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$nouvelEtat, $id]);
                    break;
            }
            
            return [
                'success' => true,
                'message' => 'État de la facture modifié avec succès',
                'factureId' => $id,
                'nouvelEtat' => $nouvelEtat
            ];
            
        } catch(PDOException $e) {
            error_log("Erreur SQL: " . $e->getMessage());
            throw new Exception('Erreur lors de la modification de l\'état de la facture: ' . $e->getMessage());
        }
    }


    // ==================================================================================
    // MÉTHODES DE STATISTIQUES
    // ==================================================================================

    /**
     * Documentation de la logique business pour les statistiques
     * 
     * États des factures et leur signification pour les statistiques :
     * 
     * - 'En attente' : Facture créée mais pas encore finalisée/envoyée
     * - 'Éditée' : Facture finalisée mais pas encore envoyée au client
     * - 'Envoyée' : Facture envoyée au client, en attente de paiement
     * - 'Payée' : Facture payée par le client
     * - 'Retard' : ✅ CALCULÉ CÔTÉ CLIENT - Facture envoyée dépassant le délai de paiement
     * - 'Annulée' : Facture annulée
     * 
     * Pour les statistiques :
     * - Montant total facturé = Somme des factures 'Envoyée' + 'Payée' + 'Partiellement payée'
     * - Montant payé = Somme des factures 'Payée' + 'Partiellement payée'
     * - Factures impayées = Nombre de factures 'Envoyée' (pas encore payées) + 'Partiellement payée'
     * - Montant restant = Montant total facturé - Montant payé
     * 
     * ✅ NOTE: L'état "Retard" n'est plus persisté en base mais calculé dynamiquement côté client
     */
    private static function getBusinessLogicDocumentation() {
        return [
            'etats_comptabilises_facture' => ['Envoyée', 'Payée'],
            'etats_comptabilises_paiement' => ['Payée'],
            'etats_impayees' => ['Envoyée']
        ];
    }

    /**
     * Récupère les statistiques complètes des factures (avec données mensuelles et distribution des états)
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int|null $annee Année pour filtrer les statistiques
     * @return array Les statistiques complètes
     * @throws Exception En cas d'erreur
     */
    public static function getStatistiquesCompletes($conn, $annee = null) {
        try {
            $whereClause = "";
            $params = [];
            
            if ($annee !== null) {
                $whereClause = " WHERE YEAR(date_facture) = ?";
                $params[] = $annee;
            }
            
            // 1. Statistiques générales (existantes)
            $stats = self::getStatistiques($conn, $annee);
            
            // 2. Ajouter les données mensuelles
            $stats['monthlySales'] = self::getStatistiquesMensuelles($conn, $annee);
            
            // 3. Ajouter la distribution des états
            $stats['statusDistribution'] = self::getDistributionEtats($conn, $annee);
            
            return $stats;
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des statistiques complètes: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération des statistiques complètes');
        }
    }

    /**
     * Récupère les statistiques mensuelles pour une année donnée
     */
    private static function getStatistiquesMensuelles($conn, $annee) {
        $whereClause = "";
        $params = [];
        
        if ($annee !== null) {
            $whereClause = " WHERE YEAR(date_facture) = ?";
            $params[] = $annee;
        }
        
        $query = "
            SELECT 
                MONTH(date_facture) as mois,
                SUM(CASE WHEN etat = 'Envoyée' OR etat = 'Payée' OR etat = 'Partiellement payée' THEN montant_total ELSE 0 END) as montant_facture,
                SUM(CASE WHEN etat = 'Payée' THEN montant_total ELSE 0 END) as montant_paye,
                COUNT(CASE WHEN etat = 'Envoyée' OR etat = 'Payée' OR etat = 'Partiellement payée' THEN 1 END) as nombre_factures
            FROM facture" . $whereClause . "
            GROUP BY MONTH(date_facture)
            ORDER BY MONTH(date_facture)
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $months = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
        ];
        
        // Initialiser tous les mois avec des valeurs zéro
        $data = [];
        $currentYear = date('Y');
        $currentMonth = ($annee == $currentYear) ? date('n') : 12;
        
        for ($i = 1; $i <= $currentMonth; $i++) {
            $data[] = [
                'name' => $months[$i],
                'facturé' => 0,
                'payé' => 0,
                'nombre' => 0
            ];
        }
        
        // Remplir avec les données réelles
        foreach ($result as $row) {
            $index = $row['mois'] - 1;
            if ($index >= 0 && $index < count($data)) {
                $data[$index] = [
                    'name' => $months[$row['mois']],
                    'facturé' => floatval($row['montant_facture']),
                    'payé' => floatval($row['montant_paye']),
                    'nombre' => intval($row['nombre_factures'])
                ];
            }
        }
        
        return $data;
    }

    /**
     * Récupère la distribution des états des factures
     * ✅ NOTE: L'état "Retard" n'apparaîtra plus dans les statistiques DB car calculé côté client
     */
    private static function getDistributionEtats($conn, $annee) {
        $whereClause = "";
        $params = [];
        
        if ($annee !== null) {
            $whereClause = " WHERE YEAR(date_facture) = ?";
            $params[] = $annee;
            $totalParams = $params; // Pour la sous-requête
        } else {
            $totalParams = [];
        }
        
        $query = "
            SELECT 
                COALESCE(etat, 'En attente') as etat,
                COUNT(*) as nombre,
                ROUND((COUNT(*) * 100.0 / total.total_factures), 1) as pourcentage
            FROM facture,
            (SELECT COUNT(*) as total_factures FROM facture" . $whereClause . ") as total" . 
            $whereClause . "
            GROUP BY COALESCE(etat, 'En attente')
            ORDER BY nombre DESC
        ";
        
        // Préparer les paramètres : ceux pour la sous-requête + ceux pour la requête principale
        $allParams = array_merge($totalParams, $params);
        
        $stmt = $conn->prepare($query);
        $stmt->execute($allParams);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $data = [];
        foreach ($result as $row) {
            $data[] = [
                'name' => $row['etat'],
                'value' => floatval($row['pourcentage']),
                'count' => intval($row['nombre'])
            ];
        }
        
        return $data;
    }

    /**
     * Récupère les statistiques des factures
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int|null $annee Année pour filtrer les statistiques
     * @return array Les statistiques
     * @throws Exception En cas d'erreur
     */
    public static function getStatistiques($conn, $annee = null) {
        try {
            $whereClause = "";
            $params = [];
            
            if ($annee !== null) {
                $whereClause = " WHERE YEAR(date_facture) = ?";
                $params[] = $annee;
            }
            
            $stats = [];
            
            // Nombre total de factures
            $sql1 = "SELECT COUNT(*) as total FROM facture" . $whereClause;
            $stmt1 = $conn->prepare($sql1);
            $stmt1->execute($params);
            $stats['totalFactures'] = $stmt1->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Montant total facturé (seulement les factures Envoyée et Payée ou partiellement payée)
            $sql2 = "SELECT SUM(montant_total) as total FROM facture WHERE (etat = 'Envoyée' OR etat = 'Payée' OR etat = 'Partiellement payée')";
            if ($annee !== null) {
                $sql2 .= " AND YEAR(date_facture) = ?";
            }
            $stmt2 = $conn->prepare($sql2);
            $stmt2->execute($params);
            $stats['montantTotal'] = $stmt2->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Montant total payé (seulement les factures à l'état Payée)
            $sql3 = "SELECT SUM(montant_total) as total FROM facture WHERE etat = 'Payée'";
            if ($annee !== null) {
                $sql3 .= " AND YEAR(date_facture) = ?";
            }
            $stmt3 = $conn->prepare($sql3);
            $stmt3->execute($params);
            $stats['montantPaye'] = $stmt3->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Nombre de factures impayées (seulement celles qui sont Envoyée mais pas encore Payée)
            $sql4 = "SELECT COUNT(*) as total FROM facture WHERE etat in ('Envoyée', 'Partiellement payée')";
            if ($annee !== null) {
                $sql4 .= " AND YEAR(date_facture) = ?";
            }
            $stmt4 = $conn->prepare($sql4);
            $stmt4->execute($params);
            $stats['facturesImpayees'] = $stmt4->fetch(PDO::FETCH_ASSOC)['total'];
            
            return $stats;
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des statistiques: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération des statistiques');
        }
    }

    
    // ==================================================================================
    // MÉTHODES UTILITAIRES PRIVÉES
    // ==================================================================================

    /**
     * Calcule le montant total d'une facture (montant brut - ristourne)
     * 
     * @param array $lignes Les lignes de la facture
     * @param float $ristourne Montant de la ristourne
     * @return float Montant total net
     * @throws Exception En cas d'erreur
     */
    private static function calculerMontantTotal($lignes, $ristourne = 0) {
        // Calculer le montant total des lignes (montant brut)
        $montantBrut = 0;
        foreach ($lignes as $ligne) {
            if (!isset($ligne['total_ligne']) && !isset($ligne['total'])) {
                throw new Exception('Total manquant dans une ligne de facture');
            }
            $montantBrut += floatval($ligne['total_ligne'] ?? $ligne['total']);
        }
        
        // Calculer le montant total final (montant brut - ristourne)
        $montantNet = max(0, $montantBrut - floatval($ristourne));
        
        return $montantNet;
    }

    /**
     * Met à jour le prochain numéro de facture dans les paramètres
     * 
     * @param PDO $conn La connexion à la base de données
     * @param string $numeroFacture Numéro de facture actuel au format NNN.YYYY
     * @throws Exception En cas d'erreur
     */
    private static function mettreAJourProchainNumero($conn, $numero_facture) {
        // Extraire l'année et le numéro de facture
        $numero_facture_parts = explode('.', $numero_facture);
        if (count($numero_facture_parts) != 2) {
            throw new Exception('Format de numéro de facture invalide');
        }
        
        $nom_parametre = 'Prochain Numéro Facture';
        $numero_actuel = intval($numero_facture_parts[0]);
        $annee_parametre = intval($numero_facture_parts[1]);
        $prochain_numero_facture = $numero_actuel + 1;
        
        // Créer les données pour le paramètre
        $paramData = [
            'nom_parametre' => $nom_parametre,
            'valeur_parametre' => $prochain_numero_facture,
            'annee_parametre' => $annee_parametre,
            'groupe_parametre' => 'Facture',
            'sous_groupe_parametre' => 'Numéro'
        ];
        
        // Appeler la fonction d'enregistrement des paramètres
        $resultatParams = ParametreControleur::enregistrerParametres($conn, $paramData);
        
        if (!$resultatParams['success']) {
            throw new Exception('Erreur lors de la mise à jour du prochain numéro de facture');
        }
    }
}
?>