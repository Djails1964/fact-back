<?php
// ServiceFacture.php

require_once 'FactureControleur.php';
require_once realpath(__DIR__ . '/controllers/ParametreControleur.php');
require_once realpath(__DIR__ . '/services/ServiceParametre.php');
require_once realpath(__DIR__ . '/controllers/PaiementControleur.php');
require_once 'PDFGeneratorFactory.php';
require_once 'ServiceTarif.php';
require_once 'EmailService.php';
require_once __DIR__ . '/utils/helpers.php';

class ServiceFacture {
    private $conn;
    private $serviceParametre;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->serviceParametre = new ServiceParametre($conn);
    }
    
    /**
     * Création d'une nouvelle facture avec mise à jour du numéro de facture
     * @param array $data Les données de la facture
     * @return array Résultat de l'opération
     */
    public function creerFacture($data) {
        try {
            // Démarrer une transaction globale
            $this->conn->beginTransaction();

            // Étape 1: Créer la facture
            $resultatFacture = FactureControleur::ajouterFacture($this->conn, $data);
            
            if (!$resultatFacture['success']) {
               throw new Exception($resultatFacture['message']);
            }
            
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
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la création de la facture: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Modification d'une facture existante
     * @param int $id ID de la facture
     * @param array $data Les données de la facture
     * @return array Résultat de l'opération
     */
    public function modifierFacture($id, $data) {
        try {
            // Démarrer une transaction
            $this->conn->beginTransaction();
            
            // Appeler la méthode statique du contrôleur
            $resultat = FactureControleur::modifierFacture($this->conn, $id, $data);
            
            if (!$resultat['success']) {
                throw new Exception($resultat['message']);
            }
            
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
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la modification de la facture: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Change l'état d'une facture (avec protection contre l'état "Retard")
     * @param int $id ID de la facture
     * @param string $nouvelEtat Nouvel état de la facture
     * @param string|null $datePaiement Date de paiement (optionnel, pour l'état 'Payée')
     * @return array Résultat de l'opération
     */
    public function changerEtatFacture($id, $nouvelEtat) {
        try {
            // ✅ PROTECTION: Empêcher la persistance de l'état "Retard" au niveau service
            if ($nouvelEtat === 'Retard') {
                error_log("⚠️ ServiceFacture - Tentative de persistance de l'état 'Retard' bloquée pour facture ID: $id");
                return [
                    'success' => false,
                    'message' => 'L\'état "Retard" ne peut pas être persisté. Il est calculé automatiquement côté client.',
                    'code' => 'RETARD_NOT_PERSISTABLE'
                ];
            }
            
            // Démarrer une transaction
            $this->conn->beginTransaction();
            
            // Vérifier que l'état est valide (sans "Retard")
            $etatsValides = ['Payée', 'Éditée', 'En attente', 'Annulée', 'Envoyée'];
            if (!in_array($nouvelEtat, $etatsValides)) {
                throw new Exception('État non valide');
            }
            
            // Récupérer la date de paiement depuis POST si non fournie en paramètre
            if ($nouvelEtat === 'Payée' && !isset($datePaiement) && isset($_POST['datePaiement'])) {
                $datePaiement = $_POST['datePaiement'];
            }
            
            // Appeler la méthode du contrôleur avec les paramètres appropriés
            $resultat = FactureControleur::changerEtatFacture($this->conn, $id, $nouvelEtat);
            
            // Si l'état est passé à "Payée", mettre à jour le montant payé si nécessaire
            if ($nouvelEtat === 'Payée') {
                // Vérifier si le montant payé est déjà défini
                $facture = FactureControleur::getFactureParId($this->conn, $id);
                
                if (empty($facture['montant_paye']) || $facture['montant_paye'] == 0) {
                    // Si non, définir le montant payé au montant total
                    $montantPaye = $facture['montant_total'];
                    
                    FactureControleur::enregistrerPaiement($this->conn, $id, [
                        'datePaiement' => $datePaiement ?? date('Y-m-d'),
                        'montantPaye' => $montantPaye
                    ]);
                }
            }
            
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

            return [
                'success' => false,
                'message' => 'Erreur lors de la modification de l\'état de la facture: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Récupération d'une facture avec toutes ses informations associées
     * @param int $id ID de la facture
     * @return array Informations de la facture
     */
    public function getFactureComplete($id) {
        try {
            $facture = FactureControleur::getFactureParId($this->conn, $id);

            error_log("Facture-api - GetFactureComplete - Facture: " . json_encode($facture)); // Log pour le débogage  
            
            return [
                'success' => true,
                'facture' => $facture
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération de la facture: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Suppression d'une facture
     * @param int $id ID de la facture
     * @return array Résultat de l'opération
     */
    public function supprimerFacture($id) {
        return FactureControleur::supprimerFacture($this->conn, $id);
    }

    /**
     * Génère un PDF pour une facture spécifique
     * 
     * @param int $factureId ID de la facture
     * @param array $options Options d'impression
     * @return array Résultat de l'opération
     */
    public function imprimerFacture($factureId, $options = []) {
        try {
            
            // Récupérer la facture complète
            error_log("Début de l'impression de la facture ID: $factureId"); // Log pour le débogage
            $resultat = $this->getFactureComplete($factureId);
            error_log("Facture récupérée: " . json_encode($resultat)); // Log pour le débogage
            
            if (!$resultat['success']) {
                throw new Exception($resultat['message']);
            }
            
            $facture = $resultat['facture'];
            error_log("Facture complète: " . json_encode($facture)); // Log pour le débogage

            // Enrichir les données de chaque ligne avec les informations de l'unité correspondante
            if (isset($facture['lignes']) && is_array($facture['lignes'])) {
                // Instancier le ServiceTarif pour accéder aux données des unités
                $serviceTarif = new ServiceTarif($this->conn);
                
                // Récupérer toutes les unités en une seule requête
                error_log("Récupération des unités pour enrichissement des lignes de facture"); // Log pour le débogage
                $unitesResult = $serviceTarif->getUnites();
                error_log("Résultat de la récupération des unités: " . json_encode($unitesResult)); // Log pour le débogage
                $unites = [];
                
                if ($unitesResult['success']) {
                    // Créer un tableau indexé par ID pour faciliter la recherche
                    foreach ($unitesResult['unites'] as $unite) {
                        $unites[$unite['id']] = $unite;
                    }
                    
                    // Pour chaque ligne de la facture
                    foreach ($facture['lignes'] as $key => $ligne) {
                        // Vérifier si l'ID de l'unité est disponible et existe dans notre liste d'unités
                        if (isset($ligne['unite_id']) && isset($unites[$ligne['unite_id']])) {
                            // Ajouter les informations de l'unité à la ligne de facture
                            $facture['lignes'][$key]['unite_code'] = $unites[$ligne['unite_id']]['code'];
                            $facture['lignes'][$key]['unite_nom'] = $unites[$ligne['unite_id']]['nom'];
                        }
                    }
                }
            }

            error_log("Facture après enrichissement des lignes: " . json_encode($facture)); // Log pour le débogage
            
            // Récupérer l'année de la facture
            $annee = date('Y', strtotime($facture['date_facture']));
            
            // Récupérer le délai de paiement
            $delaiPaiement = isset($options['delaiPaiement']) ? $options['delaiPaiement'] : null;
            error_log("Délai de paiement spécifié dans les options: " . ($delaiPaiement ? $delaiPaiement : 'Aucun')); // Log pour le débogage
            // Si le délai de paiement n'est pas spécifié dans les options, le récupérer des paramètres
            if (!$delaiPaiement) {
                error_log("Récupération du délai de paiement via le service Parametre"); // Log pour le débogage
                $delaiPaiement = $this->serviceParametre->getParametre('Delai Paiement', 'Facture', 'Paiement')['parametre']['Valeur_parametre'] ?? 30;
            }
            error_log("Délai de paiement récupéré: $delaiPaiement jours"); // Log pour le débogage

            // Récupérer le flag pour imprimer ou non la ristourne.
            $printRistourne = isset($options['printRistourne']) ? $options['printRistourne'] : null;
            // Si l'indication d'imprimer la ristourne n'est pas spécifiée dans les options, le récupérer des paramètres
            error_log("Vérification du flag pour imprimer la ristourne"); // Log pour le débogage
            if (!$printRistourne) {
                error_log("Récupération du paramètre 'Imprimer ristourne' via le service Parametre"); // Log pour le débogage
                $printRistourne = (mb_strtoupper($this->serviceParametre->getParametre('Imprimer ristourne', 'Facture', 'Ristourne')['parametre']['Valeur_parametre']) === 'O');
            }
            error_log("Flag pour imprimer la ristourne: " . ($printRistourne ? 'Oui' : 'Non')); // Log pour le débogage

            // Récupérer les paramètres bancaires
            $relationsBancaires = [];
            
            // Vérifier si les relations bancaires sont passées dans les options
            if (isset($options['relationsBancaires'])) {
                $relationsBancaires = $options['relationsBancaires'];
            } else {
                // Utiliser le nouveau système de paramètres avec groupe
                error_log("Récupération des paramètres bancaires via le service Parametre"); // Log pour le débogage
                $relationsBancairesResult = $this->serviceParametre->getParametresParGroupe('Relations Bancaires');
                error_log("Résultat de la récupération des paramètres bancaires: " . json_encode($relationsBancairesResult)); // Log pour le débogage

                if ($relationsBancairesResult['success'] && isset($relationsBancairesResult['parametres']['Relations Bancaires'])) {
                    // Transformer les résultats pour faciliter l'accès
                    foreach ($relationsBancairesResult['parametres']['Relations Bancaires'] as $sousGroupe => $categories) {
                        foreach ($categories as $categorie => $params) {
                            foreach ($params as $param) {
                                $relationsBancaires[$param['Nom_parametre']] = $param['Valeur_parametre'];
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
                error_log("Récupération des paramètres de signature via le service Parametre"); // Log pour le débogage
                $signatureResult = $this->serviceParametre->getParametres('Facture','Signature');
                error_log("Résultat de la récupération des paramètres de signature: " . json_encode($signatureResult)); // Log pour le débogage
                
                if ($signatureResult['success'] && isset($signatureResult['parametres']['Facture'])) {
                    // Transformer les résultats pour faciliter l'accès
                    foreach ($signatureResult['parametres']['Facture'] as $sousGroupe => $categories) {
                        foreach ($categories as $categorie => $params) {
                            foreach ($params as $param) {
                                $signature[$param['Nom_parametre']] = $param['Valeur_parametre'];
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
            
            // Récupérer le paramètre outputDir depuis les options ou les paramètres système
            $outputDir = isset($options['outputDir']) ? $options['outputDir'] : null;

            // Si le paramètre outputDir n'est pas spécifié dans les options, utiliser la valeur par défaut           
            if (!$outputDir) {
                // Utiliser le nouveau système de paramètres avec groupe
                // $outputDir = $this->serviceParametre->getParametre('outputDir', 'Facture')['parametre']['Valeur_parametre'] ?? 'storage/invoices';
                error_log("Récupération du dossier de sortie pour le PDF via le service Parametre"); // Log pour le débogage
                $outputDir = factures_path(null, $this->serviceParametre);
            }
            error_log("Dossier de sortie pour le PDF: $outputDir"); // Log pour le débogage
            
            // Créer le dossier de sortie s'il n'existe pas
            if (!is_dir($outputDir)) {
                if (!mkdir($outputDir, 0755, true)) {
                    throw new Exception('Impossible de créer le dossier de sortie: ' . $outputDir);
                }
            }
            
            // Créer le nom du fichier PDF
            // ✅ Utilisation de la fonction globale
            $prenomSafe = normalizeForFilename($facture['prenom']);
            $nomSafe = normalizeForFilename($facture['nom']);
            $pdfFilename = 'facture_' . $facture['numero_facture'] . '_' . $prenomSafe . '_' . $nomSafe . '_' . date('Ymd_His') . '.pdf';
            $pdfPath = $outputDir . '/' . $pdfFilename;
            
            // Utiliser le générateur de PDF
            require_once 'PDFGeneratorFactory.php';
        
            // Récupérer le type de générateur depuis la configuration ou un paramètre
            $pdfEngine = 'fpdi'; // ou 'tcpdf', selon votre préférence ou configuration
        
            // Debugging information
            
            try {
                // Créer l'instance appropriée
                error_log("Création du générateur PDF avec le moteur: " . $pdfEngine); // Log pour le débogage
                if (!class_exists('PDFGeneratorFactory')) {
                    throw new Exception('La classe PDFGeneratorFactory n\'existe pas');
                }
                $pdfGenerator = PDFGeneratorFactory::create($pdfEngine);
                error_log("Générateur PDF créé avec succès: " . get_class($pdfGenerator)); // Log pour le débogage
                
                // Utiliser le générateur
                error_log("Génération du PDF pour la facture ID: $factureId"); // Log pour le débogage
                $result = $pdfGenerator->genererPDF($facture, $pdfFilename, null, $relationsBancaires, $delaiPaiement, $signature, $printRistourne);
                error_log("Résultat de la génération du PDF: " . json_encode($result)); // Log pour le débogage
                if (!$result) {
                    throw new Exception('Erreur lors de la génération du PDF');
                }
            } catch (Exception $e) {
                // Gérer l'erreur
                error_log("Erreur lors de la création du générateur PDF: " . $e->getMessage());
                echo "Erreur lors de la création du générateur PDF: " . $e->getMessage();
            }            

            error_log("Résultat de la génération du PDF: " . json_encode($result)); // Log pour le débogage
            if (!$result) {
                throw new Exception('Erreur lors de la génération du PDF');
            }

            // Mettre à jour les informations d'édition de la facture
            $dateEdition = date('Y-m-d H:i:s');
            $updateResult = FactureControleur::mettreAJourEditionFacture($this->conn, $factureId, $dateEdition, $pdfFilename);
        
            if (!$updateResult['success']) {
                throw new Exception('Erreur lors de la mise à jour des informations d\'édition');
            }

            // Construire l'URL du PDF
            $pdfUrl = factures_url($pdfFilename, $this->serviceParametre);

            // Vérifier l'état actuel de la facture
            if ($facture['etat'] === 'En attente') {
                // Changer l'état à "Éditée"
                
                try {
                    $resultatEtat = $this->changerEtatFacture($factureId, 'Éditée');
                    
                    if (!$resultatEtat['success']) {
                        // L'état n'a pas pu être mis à jour mais l'email a été envoyé
                        return [
                            'success' => true, // L'opération principale (envoi email) a réussi
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
                        'success' => true, // L'opération principale (envoi email) a réussi
                        'message' => 'PDF généré avec succès, mais l\'état n\'a pas pu être mis à jour: ' . $resultatEtat['message'],
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
     * @param int $factureId ID de la facture
     * @param array $emailData Données pour l'email
     * @return array Résultat de l'opération
     */
    public function envoyerFactureParEmail($factureId, $emailData) {
        try {
            // Récupérer les détails de la facture
            $facture = $this->getFactureComplete($factureId);
            
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
                // Récupérer le répertoire de sortie des factures
                $outputDirResult = $this->serviceParametre->getParametre('OutputDir', 'Facture', 'Chemin');
                $outputDir = $outputDirResult['success'] ? $outputDirResult['parametre']['Valeur_parametre'] : 'storage/factures';
                
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
                        $resultatEtat = $this->changerEtatFacture($factureId, 'Envoyée');
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
                    $this->changerEtatFacture($factureId, 'Envoyée');
                    $etatMisAJour = true;
                } catch (Exception $e) {
                    error_log("Erreur mise à jour état: " . $e->getMessage());
                    $etatMisAJour = false;
                }
                
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
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Récupère l'URL de visualisation d'une facture
     * 
     * @param int $factureId ID de la facture
     * @return array Résultat avec l'URL du PDF
     */
    public function getFactureUrl($factureId) {
        try {
            // Récupérer les informations de la facture
            $resultat = $this->getFactureComplete($factureId);
            
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
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Enregistre un paiement avec le nouveau système
     * @param int $id ID de la facture
     * @param array $data Données du paiement
     * @return array Résultat de l'opération
     */
    public function enregistrerPaiement($id, $data) {
        try {
            // Démarrer une transaction
            $this->conn->beginTransaction();
            
            // Utiliser le nouveau contrôleur
            $resultat = PaiementControleur::enregistrerPaiement($this->conn, $id, $data);
            
            if (!$resultat['success']) {
                throw new Exception($resultat['message']);
            }
            
            // Valider la transaction
            $this->conn->commit();
            
            return $resultat;
            
        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement du paiement: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Récupère l'historique des paiements d'une facture
     * @param int $factureId ID de la facture
     * @return array Historique des paiements
     */
    public function getHistoriquePaiements($factureId) {
        try {
            return PaiementControleur::getHistoriquePaiements($this->conn, $factureId);
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'historique: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Supprime un paiement
     * @param int $paiementId ID du paiement
     * @return array Résultat de l'opération
     */
    public function supprimerPaiement($paiementId) {
        try {
            // Démarrer une transaction
            $this->conn->beginTransaction();
            
            $resultat = PaiementControleur::supprimerPaiement($this->conn, $paiementId);
            
            if (!$resultat['success']) {
                throw new Exception($resultat['message']);
            }
            
            // Valider la transaction
            $this->conn->commit();
            
            return $resultat;
            
        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la suppression du paiement: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Récupère les statistiques de paiement d'une facture
     * @param int $factureId ID de la facture
     * @return array Statistiques
     */
    public function getStatistiquesPaiement($factureId) {
        try {
            return PaiementControleur::getStatistiquesPaiement($this->conn, $factureId);
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Modifie un paiement existant
     * @param int $paiementId ID du paiement
     * @param array $data Nouvelles données
     * @return array Résultat de l'opération
     */
    public function modifierPaiement($paiementId, $data) {
        try {
            // Démarrer une transaction
            $this->conn->beginTransaction();
            
            $resultat = PaiementControleur::modifierPaiement($this->conn, $paiementId, $data);
            
            if (!$resultat['success']) {
                throw new Exception($resultat['message']);
            }
            
            // Valider la transaction
            $this->conn->commit();
            
            return $resultat;
            
        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la modification du paiement: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Récupère toutes les factures avec pagination et filtrage optionnel
     * @param array $options Options de filtrage et pagination
     * @return array Liste des factures
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
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des factures: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Récupère les statistiques complètes des factures (avec données mensuelles et distribution)
     * @param int|null $annee Année pour filtrer les statistiques
     * @return array Statistiques complètes
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
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ✅ MÉTHODE OBSOLÈTE: Met à jour les factures en retard de paiement
     * Cette méthode n'est plus utilisée car l'état "Retard" est calculé dynamiquement côté client
     * 
     * @return array Résultat avec un message d'information
     * @deprecated L'état "Retard" est maintenant calculé dynamiquement côté client
     */
    public function mettreAJourFacturesEnRetard() {
        error_log("⚠️ ServiceFacture::mettreAJourFacturesEnRetard() appelée - Cette méthode est obsolète (état Retard calculé côté client)");
        
        return [
            'success' => true,
            'message' => 'Les retards sont calculés automatiquement côté client, aucune mise à jour nécessaire',
            'facturesModifiees' => 0,
            'listeFactures' => []
        ];
    }

    /**
     * Récupère les paramètres pour une année donnée
     * @param int $annee Année pour laquelle récupérer les paramètres
     * @return array Paramètres
     */
    public function getProchainNumeroFacture($annee) {
        try {
            // Utiliser le système de paramètres avec groupes
            return $this->serviceParametre->getParametre('Prochain Numéro Facture', 'Facture', 'Numéro', null, $annee) ?? null;

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des paramètres: ' . $e->getMessage()
            ];
        }
    }

}
?>