<?php
/**
 * ServiceFacture.php - Version avec logging intégré et constantes
 */
require_once realpath(__DIR__ . '/../controllers/FactureControleur.php');
require_once realpath(__DIR__ . '/ServiceParametre.php');
require_once realpath(__DIR__ . '/ActivityLogger.php'); // ✅ AJOUT
require_once realpath(__DIR__ . '/../constants/ActivityLogsConstants.php'); // ✅ AJOUT
require_once realpath(__DIR__ . '/../PDFGeneratorFactory.php');
require_once realpath(__DIR__ . '/ServiceTarif.php');
require_once realpath(__DIR__ . '/../EmailService.php');
require_once realpath(__DIR__ . '/../utils/helpers.php');

class ServiceFacture {
    private $conn;
    private $serviceParametre;
    private $logger; // ✅ AJOUT
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->serviceParametre = new ServiceParametre($conn);
        $this->logger = new ActivityLogger($conn); // ✅ AJOUT
    }
    
    /**
     * ✅ NOUVEAU: Récupère les informations utilisateur depuis la session
     */
    private function getCurrentUser() {
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'name' => $_SESSION['user_name'] ?? 'Système'
        ];
    }
    
    /**
     * Création d'une nouvelle facture avec mise à jour du numéro de facture
     * @param array $data Les données de la facture
     * @return array Résultat de l'opération
     */
    public function creerFacture($data) {
        // ✅ Confirmation de paiement (contrat au forfait) : détails
        // mensuels fournis au lieu de lignes — déléguer au flux dédié.
        if (!empty($data['details_mensuels'])) {
            return $this->creerFactureConfirmation($data);
        }

        $user = $this->getCurrentUser();
        
        try {
            // Démarrer une transaction globale
            $this->conn->beginTransaction();

            if (is_dev_mode()) {
                error_log("ServiceFacture - creerFacture - Démarrage de la transaction pour création de facture");
                error_log("ServiceFacture - creerFacture - Données de la facture: " . print_r($data, true));
            }

            // ✅ Allouer le numéro de facture atomiquement (SELECT FOR UPDATE + UPDATE)
            //    L'année est déduite de date_facture — indépendamment de l'année du loyer.
            //    Le frontend ne fournit plus numero_facture ; on l'ignore s'il est présent.
            //    Séquence 'Prochain Numéro Facture' (estConfirmation=false) — distincte
            //    de celle des confirmations, voir creerFactureConfirmation() ci-dessous.
            unset($data['numero_facture']);
            $anneeFacture = (int) date('Y', strtotime($data['date_facture']));
            $numeroFacture = FactureControleur::allouerNumeroFacture($this->conn, $anneeFacture, false);
            $data['numero_facture'] = $numeroFacture;

            if (is_dev_mode()) {
                error_log("ServiceFacture - creerFacture - Numéro alloué: {$numeroFacture} (année {$anneeFacture})");
            }

            // Étape 1: Créer la facture
            $resultatFacture = FactureControleur::ajouterFacture($this->conn, $data);
            if (is_dev_mode()) {
                error_log("ServiceFacture - creerFacture - Résultat de l'ajout de facture: " . print_r($resultatFacture, true));
            }
            
            if (!$resultatFacture['success']) {
               throw new Exception($resultatFacture['message']);
            }
            
            // ✅ LOGGING: Création de facture
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_FACTURE_CREATE,
                'entity_type' => ActivityLogsConstants::ENTITY_FACTURE,
                'entity_id' => $resultatFacture['id_facture'],
                'description' => "Création de la facture #{$resultatFacture['id_facture']} pour le client {$data['client_nom']}",
                'details' => [
                    'id_facture' => $resultatFacture['id_facture'],
                    'numero_facture' => $resultatFacture['numero_facture'],
                    'id_client' => $data['id_client'] ?? null,
                    'client_nom' => $data['client_nom'] ?? null,
                    'montant_total' => $data['montant_total'] ?? null,
                    'date_facture' => $data['date_facture'] ?? null,
                    'nb_lignes' => isset($data['lignes']) ? count($data['lignes']) : 0
                ],
                'severity' => ActivityLogsConstants::SEVERITY_INFO
            ]);
            
            // La mise à jour du paramètre est maintenant gérée directement dans ajouterFacture
            
            // Valider la transaction si elle est toujours active
            if ($this->conn->inTransaction()) {
                $this->conn->commit();
            }
            
            return $resultatFacture;
            
        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            
            // ✅ LOGGING: Erreur de création
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_SYSTEM_ERROR,
                'entity_type' => ActivityLogsConstants::ENTITY_FACTURE,
                'description' => "Échec de création d'une facture",
                'details' => [
                    'error_message' => $e->getMessage(),
                    'id_client' => $data['id_client'] ?? null,
                    'montant_total' => $data['montant_total'] ?? null
                ],
                'severity' => ActivityLogsConstants::SEVERITY_ERROR
            ]);
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la création de la facture: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Modification d'une facture existante
     * @param int $id_facture ID de la facture
     * @param array $data Les données de la facture
     * @return array Résultat de l'opération
     */
    public function modifierFacture($id_facture, $data) {
        // ✅ Confirmation de paiement (contrat au forfait) : détails
        // mensuels fournis au lieu de lignes — déléguer au flux dédié.
        if (!empty($data['details_mensuels'])) {
            return $this->modifierFactureConfirmation($id_facture, $data);
        }

        $user = $this->getCurrentUser();
        
        try {
            // Récupérer les données actuelles pour comparaison
            $factureActuelle = FactureControleur::getFactureParId($this->conn, $id_facture);
            
            // Démarrer une transaction
            $this->conn->beginTransaction();
            
            // Appeler la méthode statique du contrôleur
            $resultat = FactureControleur::modifierFacture($this->conn, $id_facture, $data);
            
            if (!$resultat['success']) {
                throw new Exception($resultat['message']);
            }
            
            // ✅ LOGGING: Modification de facture
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_FACTURE_UPDATE,
                'entity_type' => ActivityLogsConstants::ENTITY_FACTURE,
                'entity_id' => $id_facture,
                'description' => "Modification de la facture #{$factureActuelle['numero_facture']} ({$factureActuelle['prenom']} {$factureActuelle['nom']})",
                'details' => [
                    'id_facture' => $id_facture,
                    'numero_facture' => $factureActuelle['numero_facture'],
                    'client_nom' => "{$factureActuelle['prenom']} {$factureActuelle['nom']}",
                    'modifications' => $this->calculateFactureChanges($factureActuelle, $data)
                ],
                'severity' => ActivityLogsConstants::SEVERITY_INFO
            ]);
            
            // Valider la transaction si elle est toujours active
            if ($this->conn->inTransaction()) {
                $this->conn->commit();
            }
            
            return $resultat;
            
        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            
            // ✅ LOGGING: Erreur de modification
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_SYSTEM_ERROR,
                'entity_type' => ActivityLogsConstants::ENTITY_FACTURE,
                'entity_id' => $id_facture,
                'description' => "Échec de modification de la facture ID {$id_facture}",
                'details' => [
                    'error_message' => $e->getMessage(),
                    'id_facture' => $id_facture,
                    'attempted_changes' => $data
                ],
                'severity' => ActivityLogsConstants::SEVERITY_ERROR
            ]);
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la modification de la facture: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Change l'état d'une facture (avec protection contre l'état "Retard")
     * @param int $id_facture ID de la facture
     * @param string $nouvelEtat Nouvel état de la facture
     * @param string|null $datePaiement Date de paiement (optionnel, pour l'état 'Payée')
     * @return array Résultat de l'opération
     */
    public function changerEtatFacture($id_facture, $nouvelEtat) {
        $user = $this->getCurrentUser();
        
        try {
            // ✅ PROTECTION: Empêcher la persistance de l'état "Retard" au niveau service
            if ($nouvelEtat === 'Retard') {
                error_log("⚠️ ServiceFacture - Tentative de persistance de l'état 'Retard' bloquée pour facture ID: $id_facture");
                return [
                    'success' => false,
                    'message' => 'L\'état "Retard" ne peut pas être persisté. Il est calculé automatiquement côté client.',
                    'code' => 'RETARD_NOT_PERSISTABLE'
                ];
            }
            
            // Récupérer les infos de la facture avant changement
            $factureActuelle = FactureControleur::getFactureParId($this->conn, $id_facture);
            
            // Démarrer une transaction
            $this->conn->beginTransaction();
            
            // Vérifier que l'état est valide (sans "Retard")
            $etatsValides = ['Payée', 'Éditée', 'En attente', 'Annulée', 'Envoyée', 'Partiellement payée'];
            if (!in_array($nouvelEtat, $etatsValides)) {
                throw new Exception('État non valide');
            }
            

            // Appeler la méthode du contrôleur avec les paramètres appropriés
            $resultat = FactureControleur::changerEtatFacture($this->conn, $id_facture, $nouvelEtat);
            
            // ✅ LOGGING: Changement d'état
            $actionType = $this->getStateChangeAction($nouvelEtat);
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => $actionType,
                'entity_type' => ActivityLogsConstants::ENTITY_FACTURE,
                'entity_id' => $id_facture,
                'description' => "Changement d'état de la facture #{$factureActuelle['numero_facture']} : {$factureActuelle['etat']} → {$nouvelEtat}",
                'details' => [
                    'id_facture' => $id_facture,
                    'numero_facture' => $factureActuelle['numero_facture'],
                    'client_nom' => "{$factureActuelle['prenom']} {$factureActuelle['nom']}",
                    'ancien_etat' => $factureActuelle['etat'],
                    'nouvel_etat' => $nouvelEtat,
                    'date_changement' => date('Y-m-d H:i:s')
                ],
                'severity' => $this->getStateChangeSeverity($nouvelEtat)
            ]);
            
            // Valider la transaction si elle est toujours active
            if ($this->conn->inTransaction()) {
                $this->conn->commit();
            }
            
            return $resultat;
            
        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            // ✅ LOGGING: Erreur de changement d'état
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_SYSTEM_ERROR,
                'entity_type' => ActivityLogsConstants::ENTITY_FACTURE,
                'entity_id' => $id_facture,
                'description' => "Échec de changement d'état de la facture ID {$id_facture} vers '{$nouvelEtat}'",
                'details' => [
                    'error_message' => $e->getMessage(),
                    'id_facture' => $id_facture,
                    'nouvel_etat_demande' => $nouvelEtat
                ],
                'severity' => ActivityLogsConstants::SEVERITY_ERROR
            ]);

            return [
                'success' => false,
                'message' => 'Erreur lors de la modification de l\'état de la facture: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Suppression d'une facture
     * @param int $id_facture ID de la facture
     * @return array Résultat de l'opération
     */
    public function supprimerFacture($id_facture) {
        $user = $this->getCurrentUser();
        
        try {
            // Récupérer les infos de la facture avant suppression
            $factureInfo = FactureControleur::getFactureParId($this->conn, $id_facture);
            
            $resultat = FactureControleur::supprimerFacture($this->conn, $id_facture);
            
            if ($resultat['success']) {
                // ✅ LOGGING: Suppression de facture
                $this->logger->log([
                    'user_id' => $user['id'],
                    'user_name' => $user['name'],
                    'action_type' => ActivityLogsConstants::ACTION_FACTURE_DELETE,
                    'entity_type' => ActivityLogsConstants::ENTITY_FACTURE,
                    'entity_id' => $id_facture,
                    'description' => "Suppression de la facture #{$factureInfo['numero_facture']} ({$factureInfo['prenom']} {$factureInfo['nom']})",
                    'details' => [
                        'id_facture' => $id_facture,
                        'numero_facture' => $factureInfo['numero_facture'],
                        'client_nom' => "{$factureInfo['prenom']} {$factureInfo['nom']}",
                        'montant_total' => $factureInfo['montant_total'],
                        'etat' => $factureInfo['etat'],
                        'date_facture' => $factureInfo['date_facture']
                    ],
                    'severity' => ActivityLogsConstants::SEVERITY_CRITICAL
                ]);
            }
            
            return $resultat;
            
        } catch (Exception $e) {
            // ✅ LOGGING: Erreur de suppression
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_SYSTEM_ERROR,
                'entity_type' => ActivityLogsConstants::ENTITY_FACTURE,
                'entity_id' => $id_facture,
                'description' => "Échec de suppression de la facture ID {$id_facture}",
                'details' => [
                    'error_message' => $e->getMessage(),
                    'id_facture' => $id_facture
                ],
                'severity' => ActivityLogsConstants::SEVERITY_ERROR
            ]);
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Génère un PDF pour une facture spécifique
     * 
     * @param int $id_facture ID de la facture
     * @param array $options Options d'impression
     * @return array Résultat de l'opération
     */
    public function imprimerFacture($id_facture, $options = []) {
        $user = $this->getCurrentUser();
        
        try {
            // Récupérer la facture complète
            error_log("Début de l'impression de la facture ID: $id_facture");
            $resultat = $this->getFactureComplete($id_facture);
            error_log("Facture récupérée: " . json_encode($resultat));
            
            if (!$resultat['success']) {
                throw new Exception($resultat['message']);
            }
            
            $facture = $resultat['facture'];
            error_log("Facture complète: " . json_encode($facture));

            // Enrichir les données de chaque ligne avec les informations de l'unité correspondante
            if (isset($facture['lignes']) && is_array($facture['lignes'])) {
                // Instancier le ServiceTarif pour accéder aux données des unités
                $serviceTarif = new ServiceTarif($this->conn);
                
                // Récupérer toutes les unités en une seule requête
                if (is_dev_mode()) {
                    error_log("Récupération des unités pour enrichissement des lignes de facture");
                }
                $unitesResult = $serviceTarif->getUnites();
                if (is_dev_mode()) {
                    error_log("Résultat de la récupération des unités: " . print_r($unitesResult, true));
                }
                $unites = [];
                
                if ($unitesResult['success']) {
                    // Créer un tableau indexé par ID pour faciliter la recherche
                    if (is_dev_mode()) {
                        error_log("Indexation des unités par ID");
                        error_log("Unités trouvées: " . count($unitesResult['unites']));
                        error_log("Unités détails: " . print_r($unitesResult['unites'], true));
                    }
                    foreach ($unitesResult['unites'] as $unite) {
                        $unites[$unite['id_unite']] = $unite;
                    }
                    if (is_dev_mode()) {
                        error_log("Unités indexées: " . print_r($unites, true));
                    }

                    // Pour chaque ligne de la facture
                    foreach ($facture['lignes'] as $key => $ligne) {
                        // Vérifier si l'ID de l'unité est disponible et existe dans notre liste d'unités
                        if (is_dev_mode()) {
                            error_log("Traitement de la ligne de facture: " . json_encode($ligne));
                        }
                        if (isset($ligne['id_unite']) && isset($unites[$ligne['id_unite']])) {
                            // Ajouter les informations de l'unité à la ligne de facture
                            $facture['lignes'][$key]['unite'] = $unites[$ligne['id_unite']]['code_unite'];
                            // ✅ Nécessaire pour afficher "durée + abréviation" à l'impression
                            // quand l'unité autorise la saisie d'une durée hh:mm (ex: Heure)
                            $facture['lignes'][$key]['permet_multiplicateur'] = $unites[$ligne['id_unite']]['permet_multiplicateur'] ?? 0;
                            $facture['lignes'][$key]['abreviation_unite']    = $unites[$ligne['id_unite']]['abreviation_unite'] ?? null;
                        }
                    }
                }
            }

            if (is_dev_mode()) {
                error_log("Facture après enrichissement des lignes: " . json_encode($facture));
            }

            // Récupérer l'année de la facture
            $annee = date('Y', strtotime($facture['date_facture']));
            
            // Récupérer le délai de paiement
            $delaiPaiement = isset($options['delaiPaiement']) ? $options['delaiPaiement'] : null;
            error_log("Délai de paiement spécifié dans les options: " . ($delaiPaiement ? $delaiPaiement : 'Aucun'));
            // Si le délai de paiement n'est pas spécifié dans les options, le récupérer des paramètres
            if (!$delaiPaiement) {
                error_log("Récupération du délai de paiement via le service Parametre");
                $parametreResult = $this->serviceParametre->getParametre('Delai Paiement', 'Facture', 'Paiement');
                error_log("résultat optention délai de paiement dans parametre:". json_encode($parametreResult));
                $delaiPaiement = $this->serviceParametre->getParametre('Delai Paiement', 'Facture', 'Paiement')['parametre']['valeur_parametre'] ?? 30;
            }
            error_log("Délai de paiement récupéré: $delaiPaiement jours");

            // Récupérer le flag pour imprimer ou non la ristourne.
            $printRistourne = isset($options['printRistourne']) ? $options['printRistourne'] : null;
            // Si l'indication d'imprimer la ristourne n'est pas spécifiée dans les options, le récupérer des paramètres
            error_log("Vérification du flag pour imprimer la ristourne");
            if (!$printRistourne) {
                error_log("Récupération du paramètre 'Imprimer ristourne' via le service Parametre");
                $printRistourne = (mb_strtoupper($this->serviceParametre->getParametre('Imprimer ristourne', 'Facture', 'Ristourne')['parametre']['valeur_parametre']) === 'O');
            }
            error_log("Flag pour imprimer la ristourne: " . ($printRistourne ? 'Oui' : 'Non'));

            // Récupérer les paramètres bancaires
            $relationsBancaires = [];
            
            // Vérifier si les relations bancaires sont passées dans les options
            if (isset($options['relationsBancaires'])) {
                $relationsBancaires = $options['relationsBancaires'];
            } else {
                // Utiliser le nouveau système de paramètres avec groupe
                error_log("Récupération des paramètres bancaires via le service Parametre");
                $relationsBancairesResult = $this->serviceParametre->getParametresParGroupe('Relations Bancaires');
                error_log("Résultat de la récupération des paramètres bancaires: " . json_encode($relationsBancairesResult));

                if ($relationsBancairesResult['success'] && isset($relationsBancairesResult['parametres']['Relations Bancaires'])) {
                    // Transformer les résultats pour faciliter l'accès
                    foreach ($relationsBancairesResult['parametres']['Relations Bancaires'] as $sousGroupe => $categories) {
                        foreach ($categories as $categorie => $params) {
                            foreach ($params as $param) {
                                $relationsBancaires[$param['nom_parametre']] = $param['valeur_parametre'];
                            }
                        }
                    }
                }
            }

            // Récupérer les paramètres signature
            $signature = [];
            
            // Vérifier si les lignes de signature sont passées dans les options
            if (isset($options['signature'])) {
                $signature = $options['signature'];
            } else {
                // Utiliser le nouveau système de paramètres avec groupe
                error_log("Récupération des paramètres de signature via le service Parametre");
                $signatureResult = $this->serviceParametre->getParametres('Facture','Signature');
                error_log("Résultat de la récupération des paramètres de signature: " . json_encode($signatureResult));
                
                if ($signatureResult['success'] && isset($signatureResult['parametres']['Facture'])) {
                    // Transformer les résultats pour faciliter l'accès
                    foreach ($signatureResult['parametres']['Facture'] as $sousGroupe => $categories) {
                        foreach ($categories as $categorie => $params) {
                            foreach ($params as $param) {
                                $signature[$param['nom_parametre']] = $param['valeur_parametre'];
                            }
                        }
                    }
                }
            }
            
            // Si les paramètres sont absents, utiliser des valeurs par défaut
            if (empty($relationsBancaires)) {
                $relationsBancaires = [
                    'Banque' => 'XXX 1001 xxxxxxx',
                    'IBAN' => 'CH 88 0076 7000 E536 2645 5',
                    'Beneficiaire_L1' => 'Johanna Cherbuin',
                    'Beneficiaire_L2' => 'Chemin du Châtelard 9',
                    'Beneficiaire_L3' => '1562 Corcelles-près-Payerne',
                    'Signature_L1' => 'Johanna+Cherbuin',
                    'Signature_L2' => 'Centre « La+Grange »'
                ];
            }
            
            // Autres paramètres par défaut
            $includeAnnexes = isset($options['includeAnnexes']) ? (bool)$options['includeAnnexes'] : true;
            $copies = isset($options['copies']) ? (int)$options['copies'] : 1;
            
            // ✅ Confirmation (contrat au forfait) : dossier de sortie et
            // préfixe de nom de fichier dédiés, distincts des factures
            // standard — voir helpers.php::confirmations_path().
            $estConfirmation = !empty($facture['est_forfait']);

            // Récupérer le paramètre outputDir depuis les options ou les paramètres système
            $outputDir = isset($options['outputDir']) ? $options['outputDir'] : null;

            // Si le paramètre outputDir n'est pas spécifié dans les options, utiliser la valeur par défaut           
            if (!$outputDir) {
                // Utiliser le nouveau système de paramètres avec groupe
                error_log("Récupération du dossier de sortie pour le PDF via le service Parametre");
                $outputDir = $estConfirmation
                    ? confirmations_path(null, $this->serviceParametre)
                    : factures_path(null, $this->serviceParametre);
            }
            error_log("Dossier de sortie pour le PDF: $outputDir");
            
            // Créer le dossier de sortie s'il n'existe pas
            if (!is_dir($outputDir)) {
                if (!mkdir($outputDir, 0755, true)) {
                    throw new Exception('Impossible de créer le dossier de sortie: ' . $outputDir);
                }
            }
            
            // Créer le nom du fichier PDF
            // ✅ Préfixe distinct selon le type de document.
            $prenomSafe = normalizeForFilename($facture['prenom']);
            $nomSafe = normalizeForFilename($facture['nom']);
            $prefixeFichier = $estConfirmation ? 'ConfirmationPaiement_' : 'Facture_';
            $pdfFilename = $prefixeFichier . $facture['numero_facture'] . '_' . $prenomSafe . '_' . $nomSafe . '_' . date('Ymd_His') . '.pdf';
            $pdfPath = $outputDir . '/' . $pdfFilename;
            
            // Utiliser le générateur de PDF
            // require_once 'PDFGeneratorFactory.php';
        
            // Récupérer le type de générateur depuis la configuration ou un paramètre
            // ✅ Une confirmation (contrat au forfait) utilise un générateur dédié
            // (tableau mensuel), une facture standard utilise le générateur habituel.
            $pdfEngine = $estConfirmation ? 'fpdi_loyer' : 'fpdi';
        
            // Debugging information
            
            try {
                // Créer l'instance appropriée
                error_log("Création du générateur PDF avec le moteur: " . $pdfEngine);
                if (!class_exists('PDFGeneratorFactory')) {
                    throw new Exception('La classe PDFGeneratorFactory n\'existe pas');
                }
                $pdfGenerator = PDFGeneratorFactory::create($pdfEngine);
                error_log("Générateur PDF créé avec succès: " . get_class($pdfGenerator));
                
                // Utiliser le générateur
                error_log("Génération du PDF pour la facture ID: $id_facture");
                // ✅ $outputDir déjà calculé plus haut (bon dossier selon le
                // type de document) — transmis au générateur au lieu de null,
                // pour que FPDILoyerConfirmationGenerator::genererPDF() sache
                // où sauvegarder réellement le fichier.
                $result = $pdfGenerator->genererPDF($facture, $pdfFilename, $outputDir, $relationsBancaires, $delaiPaiement, $signature, $printRistourne);
                error_log("Résultat de la génération du PDF: " . json_encode($result));
                if (!$result) {
                    throw new Exception('Erreur lors de la génération du PDF');
                }
            } catch (Exception $e) {
                // Gérer l'erreur
                error_log("Erreur lors de la création du générateur PDF: " . $e->getMessage());
                echo "Erreur lors de la création du générateur PDF: " . $e->getMessage();
            }            

            // Mettre à jour les informations d'édition de la facture
            $dateEdition = date('Y-m-d H:i:s');
            $updateResult = FactureControleur::mettreAJourEditionFacture($this->conn, $id_facture, $dateEdition, $pdfFilename);
        
            if (!$updateResult['success']) {
                throw new Exception('Erreur lors de la mise à jour des informations d\'édition');
            }

            // Construire l'URL du PDF (bon dossier selon le type de document)
            $pdfUrl = $estConfirmation
                ? confirmations_url($pdfFilename, $this->serviceParametre)
                : factures_url($pdfFilename, $this->serviceParametre);

            // ✅ LOGGING: Impression de facture
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_FACTURE_PRINT,
                'entity_type' => ActivityLogsConstants::ENTITY_FACTURE,
                'entity_id' => $id_facture,
                'description' => "Impression de la facture #{$facture['numero_facture']} ({$facture['prenom']} {$facture['nom']})",
                'details' => [
                    'id_facture' => $id_facture,
                    'numero_facture' => $facture['numero_facture'],
                    'client_nom' => "{$facture['prenom']} {$facture['nom']}",
                    'pdf_filename' => $pdfFilename,
                    'delai_paiement' => $delaiPaiement,
                    'print_ristourne' => $printRistourne
                ],
                'severity' => ActivityLogsConstants::SEVERITY_INFO
            ]);

            // Vérifier l'état actuel de la facture
            if ($facture['etat'] === 'En attente') {
                // Changer l'état à "Éditée"
                
                try {
                    $resultatEtat = $this->changerEtatFacture($id_facture, 'Éditée');
                    
                   if (!$resultatEtat['success']) {
                        // L'état n'a pas pu être mis à jour mais le PDF a été généré
                        return [
                            'success' => true, // L'opération principale (génération PDF) a réussi
                            'message' => 'PDF généré avec succès, mais l\'état n\'a pas pu être mis à jour: ' . $resultatEtat['message'],
                            'warning' => true, // Indicateur qu'il y a un avertissement
                            'pdfUrl' => $pdfUrl,
                            'etatMisAJour' => false
                        ];
                    }
                    
                    // Tout s'est bien passé
                    return [
                        'success' => true,
                        'message' => 'PDF généré avec succès et facture marquée comme éditée',
                        'pdfUrl' => $pdfUrl,
                        'etatModifie' => true,
                        'ancienEtat' => 'En attente',
                        'nouvelEtat' => 'Éditée'
                    ];
                    
                } catch (Exception $e) {
                    error_log("Exception lors de la mise à jour de l'état: " . $e->getMessage());
                    
                    // Le PDF a été produit mais l'état n'a pas pu être mis à jour
                    return [
                        'success' => true, // L'opération principale (génération PDF) a réussi
                        'message' => 'PDF généré avec succès, mais l\'état n\'a pas pu être mis à jour: ' . $e->getMessage(),
                        'warning' => true, // Indicateur qu'il y a un avertissement
                        'pdfUrl' => $pdfUrl,
                        'etatMisAJour' => false
                    ];
                }
                           
            } else {
                return [
                    'success' => true,
                    'message' => 'PDF généré avec succès',
                    'pdfUrl' => $pdfUrl
                ];
            }
            
        } catch (Exception $e) {
            // ✅ LOGGING: Erreur d'impression
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_SYSTEM_ERROR,
                'entity_type' => ActivityLogsConstants::ENTITY_FACTURE,
                'entity_id' => $id_facture,
                'description' => "Échec d'impression de la facture ID {$id_facture}",
                'details' => [
                    'error_message' => $e->getMessage(),
                    'id_facture' => $id_facture,
                    'options' => $options
                ],
                'severity' => ActivityLogsConstants::SEVERITY_ERROR
            ]);
            
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'impression de la facture: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Envoie une facture par email
     * MODIFICATION: Ajout du support du bypass de capture en développement
     * 
     * @param int $id_facture ID de la facture
     * @param array $emailData Données pour l'email
     * @return array Résultat de l'opération
     */
    public function envoyerFactureParEmail($id_facture, $emailData) {
        $user = $this->getCurrentUser();
        
        try {
            // Récupérer les détails de la facture
            $facture = $this->getFactureComplete($id_facture);
            
            if (!$facture['success']) {
                throw new Exception('Facture non trouvée');
            }
            
            $factureDetails = $facture['facture'];
            
            // Validation des données email
            if (empty($emailData['to']) || empty($emailData['subject']) || empty($emailData['message'])) {
                throw new Exception('Données d\'email incomplètes');
            }
            
            // Préparer le chemin de la pièce jointe
            $attachments = [];
            $pdfAttached = false;
            
            if (!empty($factureDetails['factfilename'])) {
                // ✅ Récupérer le répertoire de sortie adapté au type de document
                // (confirmation vs facture standard) — même distinction que pour
                // la génération du PDF, voir imprimerFacture() ci-dessus.
                $estConfirmationEmail = !empty($factureDetails['est_forfait']);
                $outputDirResult = $estConfirmationEmail
                    ? $this->serviceParametre->getParametre('OutputDirConfirmation', 'Facture', 'Chemin')
                    : $this->serviceParametre->getParametre('OutputDir', 'Facture', 'Chemin');
                $outputDir = $outputDirResult['success']
                    ? $outputDirResult['parametre']['valeur_parametre']
                    : ($estConfirmationEmail ? 'storage/confirmations' : 'storage/factures');
                
                // Construire le chemin complet (solution qui fonctionne)
                $pdfPath = realpath(APP_ROOT . '/' . $outputDir . '/' . $factureDetails['factfilename']);
                
                if ($pdfPath && file_exists($pdfPath)) {
                    $attachments[] = [
                        'path' => $pdfPath,
                        'name' => $factureDetails['factfilename'], // Nom original complet
                        'type' => 'application/pdf'
                    ];
                    $pdfAttached = true;
                    error_log("✅ PDF trouvé et ajouté: " . $pdfPath);
                } else {
                    error_log("❌ PDF non trouvé: " . $outputDir . '/' . $factureDetails['factfilename']);
                    
                    // En mode client, on peut continuer sans PDF mais l'indiquer
                    if (env('MAIL_SEND_MODE') === 'client') {
                        error_log("⚠️ Mode client : continuation sans PDF");
                    } else {
                        throw new Exception('Fichier PDF de la facture non trouvé. Veuillez d\'abord imprimer la facture.');
                    }
                }
            } else {
                error_log("❌ Aucun nom de fichier PDF dans la facture");
            }
            
            // Préparer les options d'envoi
            $emailOptions = [
                'from' => $emailData['from'],
                'replyTo' => env('MAIL_REPLY_TO', 'contact@lagrange.ch'),
                'attachments' => $attachments
            ];
            
            // Transférer le bypass si présent
            if (isset($emailData['bypassCapture'])) {
                $emailOptions['bypassCapture'] = $emailData['bypassCapture'];
            }
            
            // Initialiser le service email et envoyer
            $emailService = new EmailService();
            
            $result = $emailService->envoyerReellement(
                $emailData['to'],
                $emailData['subject'],
                $emailData['message'],
                $emailOptions
            );
            
            // Traiter la réponse selon le type de résultat
            if (is_array($result) && isset($result['method'])) {
                // ✅ CORRECTION: Supporter TOUS les modes client (ancien et moderne)
                if ($result['method'] === 'client_email_js' || 
                    $result['method'] === 'client_email_modern' || 
                    $result['method'] === 'client_email_universal') {
                    
                    // Mode client - RETOURNER DIRECTEMENT LE RÉSULTAT DU SERVICE EMAIL
                    error_log("✅ ServiceFacture - Mode client détecté: " . $result['method']);
                    
                    // Mettre à jour l'état de la facture
                    try {
                        $resultatEtat = $this->changerEtatFacture($id_facture, 'Envoyée');
                        $etatMisAJour = $resultatEtat['success'];
                        
                        if (!$etatMisAJour) {
                            error_log("⚠️ Impossible de mettre à jour l'état vers 'Envoyée': " . $resultatEtat['message']);
                        } else {
                            error_log("✅ État de la facture mis à jour vers 'Envoyée' (mode client)");
                        }
                    } catch (Exception $e) {
                        error_log("❌ Erreur lors de la mise à jour de l'état: " . $e->getMessage());
                        $etatMisAJour = false;
                    }
                    
                    // ✅ LOGGING: Envoi de facture (mode client)
                    $this->logger->log([
                        'user_id' => $user['id'],
                        'user_name' => $user['name'],
                        'action_type' => ActivityLogsConstants::ACTION_FACTURE_SEND,
                        'entity_type' => ActivityLogsConstants::ENTITY_FACTURE,
                        'entity_id' => $id_facture,
                        'description' => "Préparation d'envoi de la facture #{$factureDetails['numero_facture']} à {$emailData['to']} (mode client)",
                        'details' => [
                            'id_facture' => $id_facture,
                            'numero_facture' => $factureDetails['numero_facture'],
                            'client_nom' => "{$factureDetails['prenom']} {$factureDetails['nom']}",
                            'destinataire' => $emailData['to'],
                            'subject' => $emailData['subject'],
                            'method' => $result['method'],
                            'pdf_attached' => $pdfAttached,
                            'etat_mis_a_jour' => $etatMisAJour
                        ],
                        'severity' => ActivityLogsConstants::SEVERITY_INFO
                    ]);
                    
                    // ✅ CORRECTION PRINCIPALE: Retourner TOUTES les données du service EmailService
                    $responseData = [
                        'success' => true,
                        'message' => $result['message'] ?? 'Interface moderne prête - File System Access API + JavaScript',
                        'method' => $result['method'],
                        'pdfAttached' => $pdfAttached,
                        'attachmentCount' => count($attachments),
                        'attachmentName' => count($attachments) > 0 ? $attachments[0]['name'] : null,
                        'etatMisAJour' => $etatMisAJour,
                        'nouvelEtat' => $etatMisAJour ? 'Envoyée' : null,
                        'ancienEtat' => $etatMisAJour ? $factureDetails['etat'] : null
                    ];
                    
                    // ✅ CRUCIAL: Ajouter toutes les propriétés du résultat EmailService
                    if (isset($result['requestId'])) {
                        $responseData['requestId'] = $result['requestId'];
                    }
                    if (isset($result['shouldOpenNewWindow'])) {
                        $responseData['shouldOpenNewWindow'] = $result['shouldOpenNewWindow'];
                    }
                    if (isset($result['newWindowUrl'])) {
                        $responseData['newWindowUrl'] = $result['newWindowUrl'];
                    }
                    if (isset($result['mode'])) {
                        $responseData['mode'] = $result['mode'];
                    }
                    if (isset($result['debug'])) {
                        $responseData['debug'] = $result['debug'];
                    }
                    
                    error_log("✅ ServiceFacture - Retour mode client avec toutes les données: " . json_encode($responseData));
                    
                    return $responseData;
                    
                } else {
                    // Autres modes (envoi direct, SMTP, etc.)
                    return [
                        'success' => $result['success'] ?? true,
                        'message' => $result['message'] ?? 'Email traité',
                        'method' => $result['method'],
                        'pdfAttached' => $pdfAttached
                    ];
                }
            } elseif ($result === true) {
                // Envoi direct réussi - mettre à jour l'état de la facture
                try {
                    $this->changerEtatFacture($id_facture, 'Envoyée');
                    $etatMisAJour = true;
                } catch (Exception $e) {
                    error_log("Erreur mise à jour état: " . $e->getMessage());
                    $etatMisAJour = false;
                }
                
                // ✅ LOGGING: Envoi de facture (direct)
                $this->logger->log([
                    'user_id' => $user['id'],
                    'user_name' => $user['name'],
                    'action_type' => ActivityLogsConstants::ACTION_FACTURE_SEND,
                    'entity_type' => ActivityLogsConstants::ENTITY_FACTURE,
                    'entity_id' => $id_facture,
                    'description' => "Envoi de la facture #{$factureDetails['numero_facture']} à {$emailData['to']}",
                    'details' => [
                        'id_facture' => $id_facture,
                        'numero_facture' => $factureDetails['numero_facture'],
                        'client_nom' => "{$factureDetails['prenom']} {$factureDetails['nom']}",
                        'destinataire' => $emailData['to'],
                        'subject' => $emailData['subject'],
                        'method' => 'direct',
                        'pdf_attached' => $pdfAttached,
                        'etat_mis_a_jour' => $etatMisAJour
                    ],
                    'severity' => ActivityLogsConstants::SEVERITY_INFO
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Facture envoyée par email avec succès',
                    'pdfAttached' => $pdfAttached,
                    'attachmentName' => count($attachments) > 0 ? $attachments[0]['name'] : null,
                    'etatMisAJour' => $etatMisAJour
                ];
            } else {
                throw new Exception('Erreur lors de l\'envoi de la facture par email');
            }
            
        } catch (Exception $e) {
            error_log("❌ Erreur ServiceFacture::envoyerFactureParEmail: " . $e->getMessage());
            
            // ✅ LOGGING: Erreur d'envoi
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_SYSTEM_ERROR,
                'entity_type' => ActivityLogsConstants::ENTITY_FACTURE,
                'entity_id' => $id_facture,
                'description' => "Échec d'envoi de la facture ID {$id_facture} par email",
                'details' => [
                    'error_message' => $e->getMessage(),
                    'id_facture' => $id_facture,
                    'destinataire' => $emailData['to'] ?? null,
                    'subject' => $emailData['subject'] ?? null
                ],
                'severity' => ActivityLogsConstants::SEVERITY_ERROR
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * ✅ PAS DE LOGGING: Récupération d'une facture avec toutes ses informations associées
     * @param int $id_facture ID de la facture
     * @return array Informations de la facture
     */
    public function getFactureComplete($id_facture) {
        try {
            $facture = FactureControleur::getFactureParId($this->conn, $id_facture);

            error_log("Facture-api - GetFactureComplete - Facture: " . json_encode($facture));
            
            return [
                'success' => true,
                'facture' => $facture
            ];
            
        } catch (Exception $e) {
            // ✅ LOGGING: Erreur uniquement
            $user = $this->getCurrentUser();
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_SYSTEM_ERROR,
                'entity_type' => ActivityLogsConstants::ENTITY_FACTURE,
                'entity_id' => $id_facture,
                'description' => "Erreur lors de la récupération de la facture ID {$id_facture}",
                'details' => [
                    'error_message' => $e->getMessage(),
                    'id_facture' => $id_facture
                ],
                'severity' => ActivityLogsConstants::SEVERITY_WARNING
            ]);
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération de la facture: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ✅ PAS DE LOGGING: Récupère l'URL de visualisation d'une facture
     */
    public function getFactureUrl($id_facture) {
        try {
            // Récupérer les informations de la facture
            $resultat = $this->getFactureComplete($id_facture);
            
            if (!$resultat['success']) {
                throw new Exception($resultat['message']);
            }
            
            $facture = $resultat['facture'];
            
            if (!$facture) {
                throw new Exception('Facture non trouvée');
            }
            
            // Vérifier l'existence du fichier PDF
            if (empty($facture['factfilename'])) {
                throw new Exception('Aucun fichier PDF associé à cette facture');
            }
            
            // Récupérer le répertoire de sortie depuis les paramètres
            $parametreService = new ServiceParametre($this->conn);
            $parametres = $parametreService->getParametres();
            
            // Vérifier si le paramètre outputDir existe
            if (!isset($parametres['outputDir']) || empty($parametres['outputDir'])) {
                throw new Exception('Le répertoire de sortie des factures n\'est pas configuré');
            }
            
            // Construire l'URL publique du fichier PDF
            $baseUrl = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
            $baseUrl .= $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/';
            
            // Déterminer le chemin relatif pour l'URL
            // Convertir le chemin du système de fichiers en chemin d'URL
            $outputDirPath = $parametres['outputDir'];
            $webRootPath = $_SERVER['DOCUMENT_ROOT'];
            
            // Si outputDir est un chemin absolu, le convertir en chemin relatif
            if (strpos($outputDirPath, $webRootPath) === 0) {
                $relativePath = substr($outputDirPath, strlen($webRootPath));
                $relativePath = str_replace('\\', '/', $relativePath);
                
                if (substr($relativePath, 0, 1) !== '/') {
                    $relativePath = '/' . $relativePath;
                }
                
                if (substr($relativePath, -1) !== '/') {
                    $relativePath .= '/';
                }
                
                $pdfUrl = $baseUrl . ltrim($relativePath, '/') . $facture['factfilename'];
            } else {
                // Si c'est un chemin relatif, l'utiliser directement
                $pdfUrl = $baseUrl . 'documents/factures/' . $facture['factfilename'];
            }
            
            return [
                'success' => true,
                'pdfUrl' => $pdfUrl
            ];
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération de l'URL de la facture: " . $e->getMessage());
            
            // ✅ LOGGING: Erreur uniquement
            $user = $this->getCurrentUser();
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_SYSTEM_ERROR,
                'entity_type' => ActivityLogsConstants::ENTITY_FACTURE,
                'entity_id' => $id_facture,
                'description' => "Erreur lors de la récupération de l'URL de la facture ID {$id_facture}",
                'details' => [
                    'error_message' => $e->getMessage(),
                    'id_facture' => $id_facture
                ],
                'severity' => ActivityLogsConstants::SEVERITY_WARNING
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    // ✅ PAS DE LOGGING pour les méthodes de consultation :
    // - listerFactures()
    // - getStatistiques()
    // - getProchainNumeroFacture()

    /**
     * ✅ PAS DE LOGGING: Récupère toutes les factures avec pagination et filtrage optionnel
     */
    public function listerFactures($options = []) {
        try {
            // Extraction des options
            $annee = isset($options['annee']) ? $options['annee'] : null;
            
            // On pourrait ajouter d'autres options comme la pagination
            $factures = FactureControleur::listerFactures($this->conn, $annee);
            
            return [
                'success' => true,
                'factures' => $factures
            ];
            
        } catch (Exception $e) {
            // ✅ LOGGING: Erreur uniquement
            $user = $this->getCurrentUser();
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_SYSTEM_ERROR,
                'entity_type' => ActivityLogsConstants::ENTITY_FACTURE,
                'description' => "Erreur lors de la récupération de la liste des factures",
                'details' => [
                    'error_message' => $e->getMessage(),
                    'options' => $options
                ],
                'severity' => ActivityLogsConstants::SEVERITY_WARNING
            ]);
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des factures: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Récupère toutes les factures d'un client spécifique
     * 
     * @param int $id_client ID du client
     * @return array Résultat avec la liste des factures
     */
    public function getFacturesClient($id_client) {
        try {
            error_log("📥 ServiceFacture::getFacturesClient - Client #$id_client");
            
            // Appel du contrôleur pour récupérer les factures
            $factures = FactureControleur::getFacturesClient($this->conn, $id_client);
            
            error_log("✅ ServiceFacture::getFacturesClient - " . count($factures) . " factures récupérées");
            
            return [
                'success' => true,
                'factures' => $factures,
                'count' => count($factures)
            ];
            
        } catch (Exception $e) {
            // Logging de l'erreur
            $user = $this->getCurrentUser();
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_SYSTEM_ERROR,
                'entity_type' => ActivityLogsConstants::ENTITY_FACTURE,
                'description' => "Erreur lors de la récupération des factures du client #$id_client",
                'details' => [
                    'error_message' => $e->getMessage(),
                    'id_client' => $id_client
                ],
                'severity' => ActivityLogsConstants::SEVERITY_WARNING
            ]);
            
            error_log("❌ ServiceFacture::getFacturesClient - Erreur: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des factures: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ✅ PAS DE LOGGING: Récupère les statistiques complètes des factures
     */
    public function getStatistiques($annee = null) {
        try {
            // Utiliser la nouvelle méthode qui récupère toutes les statistiques
            $stats = FactureControleur::getStatistiquesCompletes($this->conn, $annee);
            
            error_log("ServiceFacture - Statistiques complètes récupérées: " . json_encode($stats));
            
            return [
                'success' => true,
                'statistiques' => $stats
            ];
            
        } catch (Exception $e) {
            error_log("Erreur dans ServiceFacture::getStatistiques: " . $e->getMessage());
            
            // ✅ LOGGING: Erreur uniquement
            $user = $this->getCurrentUser();
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_SYSTEM_ERROR,
                'entity_type' => ActivityLogsConstants::ENTITY_FACTURE,
                'description' => "Erreur lors de la récupération des statistiques des factures",
                'details' => [
                    'error_message' => $e->getMessage(),
                    'annee' => $annee
                ],
                'severity' => ActivityLogsConstants::SEVERITY_WARNING
            ]);
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ✅ PAS DE LOGGING: Récupère les paramètres pour une année donnée (consultation)
     */
    public function getProchainNumeroFacture($annee) {
        try {
            // Utiliser le système de paramètres avec groupes
            return $this->serviceParametre->getParametre('Prochain Numéro Facture', 'Facture', 'Numéro', null, $annee) ?? null;

        } catch (Exception $e) {
            // ✅ LOGGING: Erreur uniquement
            $user = $this->getCurrentUser();
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_SYSTEM_ERROR,
                'entity_type' => ActivityLogsConstants::ENTITY_FACTURE,
                'description' => "Erreur lors de la récupération du prochain numéro de facture pour l'année {$annee}",
                'details' => [
                    'error_message' => $e->getMessage(),
                    'annee' => $annee
                ],
                'severity' => ActivityLogsConstants::SEVERITY_WARNING
            ]);
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des paramètres: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ✅ Création d'une confirmation de paiement (contrat au forfait),
     * avec son détail mensuel figé (facture_detail_mensuel). Miroir de
     * creerFacture() mais pour ce type de document — appelée via
     * creerFacture() quand $data['details_mensuels'] est fourni.
     *
     * @param array $data
     * @return array
     */
    private function creerFactureConfirmation($data) {
        $user = $this->getCurrentUser();

        try {
            $this->conn->beginTransaction();

            // ✅ Allouer le numéro de confirmation atomiquement, séquence
            // dédiée ('Prochain Numéro Confirmation') — indépendante de
            // celle des factures standard (estConfirmation=true).
            unset($data['numero_facture']);
            $anneeFacture  = (int) date('Y', strtotime($data['date_facture']));
            $numeroFacture = FactureControleur::allouerNumeroFacture($this->conn, $anneeFacture, true);
            $data['numero_facture'] = $numeroFacture;

            $resultat = FactureControleur::ajouterFactureAvecDetailMensuel($this->conn, $data);

            if (!$resultat['success']) {
                throw new Exception($resultat['message']);
            }

            $this->logger->log([
                'user_id'     => $user['id'],
                'user_name'   => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_FACTURE_CREATE,
                'entity_type' => ActivityLogsConstants::ENTITY_FACTURE,
                'entity_id'   => $resultat['id_facture'],
                'description' => "Création de la confirmation de paiement #{$resultat['id_facture']} pour le client {$data['client_nom']}",
                'details'     => [
                    'id_facture'     => $resultat['id_facture'],
                    'numero_facture' => $resultat['numeroFacture'],
                    'id_client'      => $data['id_client']      ?? null,
                    'client_nom'     => $data['client_nom']     ?? null,
                    'montant_brut'   => $data['montant_brut']   ?? null,
                    'date_facture'   => $data['date_facture']   ?? null,
                    'nb_mois'        => isset($data['details_mensuels']) ? count($data['details_mensuels']) : 0,
                ],
                'severity' => ActivityLogsConstants::SEVERITY_INFO,
            ]);

            if ($this->conn->inTransaction()) {
                $this->conn->commit();
            }

            return $resultat;

        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            $this->logger->log([
                'user_id'     => $user['id'],
                'user_name'   => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_SYSTEM_ERROR,
                'entity_type' => ActivityLogsConstants::ENTITY_FACTURE,
                'description' => "Échec de création d'une confirmation de paiement",
                'details'     => [
                    'error_message' => $e->getMessage(),
                    'id_client'     => $data['id_client'] ?? null,
                ],
                'severity' => ActivityLogsConstants::SEVERITY_ERROR,
            ]);

            return [
                'success' => false,
                'message' => 'Erreur lors de la création de la confirmation de paiement: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * ✅ Modification d'une confirmation de paiement existante (remplace
     * son détail mensuel). Miroir de modifierFacture() — appelée via
     * modifierFacture() quand $data['details_mensuels'] est fourni.
     *
     * @param int   $id_facture
     * @param array $data
     * @return array
     */
    private function modifierFactureConfirmation($id_facture, $data) {
        $user = $this->getCurrentUser();

        try {
            $factureActuelle = FactureControleur::getFactureParId($this->conn, $id_facture);

            $this->conn->beginTransaction();

            $resultat = FactureControleur::modifierFactureAvecDetailMensuel($this->conn, $id_facture, $data);

            if (!$resultat['success']) {
                throw new Exception($resultat['message']);
            }

            $this->logger->log([
                'user_id'     => $user['id'],
                'user_name'   => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_FACTURE_UPDATE,
                'entity_type' => ActivityLogsConstants::ENTITY_FACTURE,
                'entity_id'   => $id_facture,
                'description' => "Mise à jour de la confirmation de paiement #{$factureActuelle['numero_facture']} ({$factureActuelle['prenom']} {$factureActuelle['nom']})",
                'details'     => [
                    'id_facture'     => $id_facture,
                    'numero_facture' => $factureActuelle['numero_facture'],
                    'client_nom'     => "{$factureActuelle['prenom']} {$factureActuelle['nom']}",
                    'nb_mois'        => isset($data['details_mensuels']) ? count($data['details_mensuels']) : 0,
                ],
                'severity' => ActivityLogsConstants::SEVERITY_INFO,
            ]);

            if ($this->conn->inTransaction()) {
                $this->conn->commit();
            }

            return $resultat;

        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            $this->logger->log([
                'user_id'     => $user['id'],
                'user_name'   => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_SYSTEM_ERROR,
                'entity_type' => ActivityLogsConstants::ENTITY_FACTURE,
                'entity_id'   => $id_facture,
                'description' => "Échec de mise à jour de la confirmation de paiement ID {$id_facture}",
                'details'     => [
                    'error_message' => $e->getMessage(),
                ],
                'severity' => ActivityLogsConstants::SEVERITY_ERROR,
            ]);

            return [
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la confirmation de paiement: ' . $e->getMessage(),
            ];
        }
    }

    // ==================== MÉTHODES UTILITAIRES PRIVÉES ====================

    /**
     * ✅ NOUVEAU: Calcule les changements entre l'ancienne et la nouvelle facture
     */
    private function calculateFactureChanges($factureActuelle, $newData) {
        $changes = [];
        
        // Champs à surveiller pour les changements
        $fieldsToCheck = [
            'montantTotal' => 'montant_total',
            'dateFacture' => 'date_facture',
            'dateEcheance' => 'date_echeance',
            'commentaire' => 'commentaire',
            'id_client' => 'id_client'
        ];
        
        foreach ($fieldsToCheck as $newField => $oldField) {
            if (isset($newData[$newField]) && isset($factureActuelle[$oldField])) {
                if ($newData[$newField] != $factureActuelle[$oldField]) {
                    $changes[$newField] = [
                        'ancien' => $factureActuelle[$oldField],
                        'nouveau' => $newData[$newField]
                    ];
                }
            }
        }
        
        // Vérifier les changements de lignes
        if (isset($newData['lignes'])) {
            $changes['lignes_modifiees'] = count($newData['lignes']);
        }
        
        return $changes;
    }

    /**
     * ✅ NOUVEAU: Détermine le type d'action pour un changement d'état
     */
    private function getStateChangeAction($nouvelEtat) {
        switch ($nouvelEtat) {
            case 'Payée':
                return ActivityLogsConstants::ACTION_FACTURE_VALIDATE;
            case 'Annulée':
                return ActivityLogsConstants::ACTION_FACTURE_CANCEL;
            case 'Envoyée':
                return ActivityLogsConstants::ACTION_FACTURE_SEND;
            case 'Éditée':
            case 'En attente':
            default:
                return ActivityLogsConstants::ACTION_FACTURE_UPDATE;
        }
    }

    /**
     * ✅ NOUVEAU: Détermine la sévérité pour un changement d'état
     */
    private function getStateChangeSeverity($nouvelEtat) {
        switch ($nouvelEtat) {
            case 'Annulée':
                return ActivityLogsConstants::SEVERITY_WARNING;
            case 'Payée':
                return ActivityLogsConstants::SEVERITY_INFO;
            case 'Envoyée':
            case 'Éditée':
            case 'En attente':
            default:
                return ActivityLogsConstants::SEVERITY_INFO;
        }
    }
}
?>