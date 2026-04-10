<?php
/**
 * ServiceLoyer.php - VERSION TABLE SÉPARÉE
 * Service pour la gestion des loyers avec logging et transactions
 */

require_once realpath(__DIR__ . '/../controllers/LoyerControleur.php');
require_once realpath(__DIR__ . '/ActivityLogger.php');
require_once realpath(__DIR__ . '/../constants/ActivityLogsConstants.php');
require_once realpath(__DIR__ . '/../PDFGeneratorFactory.php');
require_once realpath(__DIR__ . '/ServiceParametre.php');
require_once realpath(__DIR__ . '/../utils/helpers.php');

class ServiceLoyer {
    private $conn;
    private $logger;
    private $serviceParametre;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->logger = new ActivityLogger($conn);
        $this->serviceParametre = new ServiceParametre($conn);
    }

    /**
     * Récupère les informations utilisateur depuis la session
     */
    private function getCurrentUser() {
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'name' => $_SESSION['user_name'] ?? 'Système'
        ];
    }

    /**
     * Génère le prochain numéro de loyer pour un client
     * ✅ Gestion en PHP (pas de fonction SQL)
     * Format: LOY-{id_client}-{seq}
     * 
     * @param int $id_client ID du client
     * @return string Numéro généré (ex: LOY-12-003)
     */
    public function genererNumeroLoyer($id_client) {
        try {
            $this->conn->beginTransaction();
            
            $numeroInfo = LoyerControleur::genererNumeroLoyer($this->conn, $id_client);
            
            $this->conn->commit();
            
            return $numeroInfo['numero'];
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("ServiceLoyer - Erreur génération numéro: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Crée un nouveau loyer
     * ✅ Transaction complète avec numérotation automatique
     * 
     * @param array $data Données du loyer
     * @return array Résultat avec id_loyer et numero_loyer
     */
    public function creerLoyer($data) {
        $user = $this->getCurrentUser();
        
        try {
            $this->conn->beginTransaction();

            error_log ("ServiceLoyer - Création loyer - Données reçues: " . print_r($data, true));
            
            // Validation
            if (!isset($data['id_client']) || !isset($data['periode_debut']) || !isset($data['duree_mois'])) {
                throw new Exception('Données obligatoires manquantes');
            }

            // Récupérer le nom du client pour le log
            $stmtClient = $this->conn->prepare("SELECT CONCAT(prenom, ' ', nom) as nom_client FROM client WHERE id = ?");
            $stmtClient->execute([$data['id_client']]);
            $clientRow = $stmtClient->fetch(PDO::FETCH_ASSOC);
            $nomClient = $clientRow ? $clientRow['nom_client'] : 'Client inconnu';

            // Ajouter l'ID créateur
            $data['createur_id'] = $user['id'];

            // ✅ Créer le loyer (la numérotation est gérée dans le contrôleur)
            $resultat = LoyerControleur::ajouterLoyer($this->conn, $data);
            
            if (!$resultat['success']) {
                throw new Exception($resultat['message']);
            }
            
            // Logging
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => 'loyer_create',
                'entity_type' => 'loyer',
                'entity_id' => $resultat['id_loyer'],
                'description' => "Création du loyer {$resultat['numero_loyer']} pour {$nomClient}",
                'details' => [
                    'id_loyer' => $resultat['id_loyer'],
                    'numero_loyer' => $resultat['numero_loyer'],
                    'numero_sequence' => $resultat['numero_sequence'],
                    'id_client' => $data['id_client'],
                    'client_nom' => $nomClient,
                    'periode_debut' => $data['periode_debut'],
                    'periode_fin' => $data['periode_fin'] ?? null,
                    'duree_mois' => $data['duree_mois'],
                    'loyer_montant_total' => $data['loyer_montant_total']
                ],
                'severity' => ActivityLogsConstants::SEVERITY_INFO
            ]);
            
            $this->conn->commit();
            
            return $resultat;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("ServiceLoyer - Erreur création: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Modifie un loyer existant
     * 
     * @param int $id_loyer ID du loyer
     * @param array $data Nouvelles données
     * @return array Résultat
     */
    public function modifierLoyer($id_loyer, $data) {
        $user = $this->getCurrentUser();
        
        try {
            $this->conn->beginTransaction();
            
            // Récupérer les infos avant modification
            $loyerAvant = LoyerControleur::getLoyerParId($this->conn, $id_loyer);
            
            // Ajouter l'ID modificateur
            $data['modificateur_id'] = $user['id'];
            
            // Modifier
            $resultat = LoyerControleur::modifierLoyer($this->conn, $id_loyer, $data);
            
            if (!$resultat['success']) {
                throw new Exception($resultat['message']);
            }
            
            // Logging
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => 'loyer_update',
                'entity_type' => 'loyer',
                'entity_id' => $id_loyer,
                'description' => "Modification du loyer {$loyerAvant['numero_loyer']}",
                'details' => [
                    'id_loyer' => $id_loyer,
                    'numero_loyer' => $loyerAvant['numero_loyer'],
                    'modifications' => [
                        'avant' => [
                            'periode_debut' => $loyerAvant['periode_debut'],
                            'periode_fin' => $loyerAvant['periode_fin'],
                            'montant_total' => $loyerAvant['montant_total']
                        ],
                        'apres' => [
                            'periode_debut' => $data['periode_debut'],
                            'periode_fin' => $data['periode_fin'],
                            'montant_total' => $data['montant_total']
                        ]
                    ]
                ],
                'severity' => ActivityLogsConstants::SEVERITY_INFO
            ]);
            
            $this->conn->commit();
            
            return $resultat;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("ServiceLoyer - Erreur modification: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Supprime un loyer
     * 
     * @param int $id_loyer ID du loyer
     * @return array Résultat
     */
    public function supprimerLoyer($id_loyer) {
        $user = $this->getCurrentUser();
        
        try {
            $this->conn->beginTransaction();
            
            // Récupérer les infos avant suppression
            $loyer = LoyerControleur::getLoyerParId($this->conn, $id_loyer);
            
            // Supprimer
            $resultat = LoyerControleur::supprimerLoyer($this->conn, $id_loyer);
            
            if (!$resultat['success']) {
                throw new Exception($resultat['message']);
            }
            
            // Logging
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => 'loyer_delete',
                'entity_type' => 'loyer',
                'entity_id' => $id_loyer,
                'description' => "Suppression du loyer {$loyer['numero_loyer']} ({$loyer['nom_complet_client']})",
                'details' => [
                    'id_loyer' => $id_loyer,
                    'numero_loyer' => $loyer['numero_loyer'],
                    'client_nom' => $loyer['nom_complet_client'],
                    'montant_total' => $loyer['montant_total']
                ],
                'severity' => ActivityLogsConstants::SEVERITY_WARNING
            ]);
            
            $this->conn->commit();
            
            return $resultat;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("ServiceLoyer - Erreur suppression: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Lie une facture générée à ce loyer.
     * Met à jour loyer.id_facture — l'état de paiement sera ensuite
     * calculé depuis les paiements de la facture liée (via v_loyers_complets).
     *
     * @param int $id_loyer
     * @param int $id_facture
     * @return array {success, message}
     */
    public function lierFacture($id_loyer, $id_facture) {
        $user = $this->getCurrentUser();
        try {
            $resultat = LoyerControleur::lierFacture($this->conn, $id_loyer, $id_facture);

            $this->logger->log([
                'user_id'     => $user['id'],
                'user_name'   => $user['name'],
                'action_type' => 'loyer_update',
                'entity_type' => 'loyer',
                'entity_id'   => $id_loyer,
                'description' => "Liaison loyer #$id_loyer → facture #$id_facture",
                'details'     => ['id_loyer' => $id_loyer, 'id_facture' => $id_facture],
                'severity'   => ActivityLogsConstants::SEVERITY_INFO,
            ]);

            return $resultat;
        } catch (Exception $e) {
            error_log("ServiceLoyer::lierFacture - Erreur: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Liste tous les loyers
     * 
     * @param array $filtres Filtres optionnels
     * @return array Liste des loyers
     */
    public function listerLoyers($filtres = []) {
        try {
            return LoyerControleur::listerLoyers($this->conn, $filtres);
        } catch (Exception $e) {
            error_log("ServiceLoyer - Erreur listing: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Récupère un loyer par son ID
     * 
     * @param int $id_loyer ID du loyer
     * @return array Données du loyer
     */
    public function getLoyerParId($id_loyer) {
        try {
            return LoyerControleur::getLoyerParId($this->conn, $id_loyer);
        } catch (Exception $e) {
            error_log("ServiceLoyer - Erreur récupération: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Génère le PDF de confirmation de paiement pour un loyer.
     * Suit le même pattern que ServiceFacture::imprimerFacture.
     *
     * @param int   $id_loyer ID du loyer
     * @param array $options  Options optionnelles (outputDir, banque, signature)
     * @return array { success, pdfUrl, message }
     */
    public function genererConfirmationPDF($id_loyer, $options = []) {
        $user = $this->getCurrentUser();

        try {
            // ── 1. Récupérer les données du loyer (via contrôleur) ────────────
            $loyer = LoyerControleur::getLoyerParId($this->conn, $id_loyer);
            if (!$loyer) {
                throw new Exception("Loyer #$id_loyer introuvable");
            }
            error_log("Loyer trouvé pour PDF: " . print_r($loyer, true));

            // ── 2. Dossier de sortie (idem facture) ───────────────────────────
            $outputDir = $options['outputDir'] ?? null;
            if (!$outputDir) {
                $outputDir = factures_path(null, $this->serviceParametre);
            }
            error_log("Dossier de sortie pour le PDF: $outputDir");

            if (!is_dir($outputDir)) {
                if (!mkdir($outputDir, 0755, true)) {
                    throw new Exception('Impossible de créer le dossier de sortie: ' . $outputDir);
                }
            }

            // ── 3. Nom du fichier PDF ─────────────────────────────────────────
            // Format : ConfPmt_<numero_loyer>_<NomPrenom>_jj.mm.aaaa_HH_MM_SS.pdf
            $prenomSafe = normalizeForFilename($loyer['prenom_client']  ?? $loyer['prenom'] ?? '');
            $nomSafe    = normalizeForFilename($loyer['nom_client']     ?? $loyer['nom']    ?? '');
            $dateHeure  = date('d.m.Y_H_i_s');
            $numeroSafe = preg_replace('/[^A-Za-z0-9\-_]/', '_', $loyer['numero_loyer']);
            $pdfFilename = "ConfPmt_{$numeroSafe}_{$nomSafe}_{$prenomSafe}_{$dateHeure}.pdf";

            // ── 4. Paramètres bancaires (idem facture) ────────────────────────
            $banque = $options['banque'] ?? [];
            if (empty($banque)) {
                $bancairesResult = $this->serviceParametre->getParametresParGroupe('Relations Bancaires');
                if ($bancairesResult['success'] && isset($bancairesResult['parametres']['Relations Bancaires'])) {
                    foreach ($bancairesResult['parametres']['Relations Bancaires'] as $sousGroupe => $categories) {
                        foreach ($categories as $categorie => $params) {
                            foreach ($params as $param) {
                                $banque[$param['nom_parametre']] = $param['valeur_parametre'];
                            }
                        }
                    }
                }
            }
            if (empty($banque)) {
                $banque = [
                    'Banque'         => 'BCV 1001 Lausanne',
                    'IBAN'           => 'CH 88 0076 7000 E536 2645 5',
                    'Beneficiaire'   => 'Johanna Cherbuin, Chemin du Châtelard 9, 1562 Corcelles-près-Payerne',
                ];
            }

            // ── 5. Signature (idem facture) ───────────────────────────────────
            $signature = $options['signature'] ?? [];
            if (empty($signature)) {
                $signatureResult = $this->serviceParametre->getParametres('Facture', 'Signature');
                if ($signatureResult['success'] && isset($signatureResult['parametres']['Facture'])) {
                    foreach ($signatureResult['parametres']['Facture'] as $sousGroupe => $categories) {
                        foreach ($categories as $categorie => $params) {
                            foreach ($params as $param) {
                                $signature[$param['nom_parametre']] = $param['valeur_parametre'];
                            }
                        }
                    }
                }
            }
            if (empty($signature)) {
                $signature = [
                    'Ligne 1' => 'Johanna Cherbuin',
                    'Ligne 2' => 'Centre « La Grange »',
                ];
            }

            // ── 6. Moteur PDF ─────────────────────────────────────────────────
            $pdfEngine = 'fpdi_loyer';

            // ── 7. Date d'édition ─────────────────────────────────────────────
            $dateEdition = date('Y-m-d H:i:s');
            $loyer['date_document'] = date('Y-m-d');

            // ── 8. Générer le PDF via la factory ──────────────────────────────
            error_log("Création du générateur PDF avec le moteur: $pdfEngine");
            if (!class_exists('PDFGeneratorFactory')) {
                throw new Exception('La classe PDFGeneratorFactory n\'existe pas');
            }
            $pdfGenerator = PDFGeneratorFactory::create($pdfEngine);
            error_log("Générateur PDF créé avec succès: " . get_class($pdfGenerator));

            $result = $pdfGenerator->genererPDF($loyer, $pdfFilename, null, $banque, 0, $signature, false);
            if (!$result) {
                throw new Exception('Erreur lors de la génération du PDF de confirmation');
            }

            // ── 9. URL du PDF ─────────────────────────────────────────────────
            $pdfUrl = factures_url($pdfFilename, $this->serviceParametre);

            // ── 10. Log activité ──────────────────────────────────────────────
            $nomClient = trim(($loyer['prenom_client'] ?? $loyer['prenom'] ?? '') . ' ' . ($loyer['nom_client'] ?? $loyer['nom'] ?? ''));
            $this->logger->log([
                'user_id'     => $user['id'],
                'user_name'   => $user['name'],
                'action_type' => 'loyer_print',
                'entity_type' => 'loyer',
                'entity_id'   => $id_loyer,
                'description' => "Génération de la confirmation de paiement {$loyer['numero_loyer']} ({$nomClient})",
                'details'     => [
                    'id_loyer'       => $id_loyer,
                    'numero_loyer'   => $loyer['numero_loyer'],
                    'client_nom'     => $nomClient,
                    'pdf_filename'   => $pdfFilename,
                    'date_edition'   => $dateEdition,
                    'etat_paiement'  => $loyer['etat_paiement'] ?? null,
                ],
                'severity' => ActivityLogsConstants::SEVERITY_INFO,
            ]);

            return [
                'success' => true,
                'message' => 'Confirmation de paiement générée avec succès',
                'pdfUrl'  => $pdfUrl,
            ];

        } catch (Exception $e) {
            // Log erreur
            $this->logger->log([
                'user_id'     => $user['id'],
                'user_name'   => $user['name'],
                'action_type' => 'system_error',
                'entity_type' => 'loyer',
                'entity_id'   => $id_loyer,
                'description' => "Échec de génération de la confirmation PDF pour le loyer #$id_loyer",
                'details'     => [
                    'error_message' => $e->getMessage(),
                    'id_loyer'      => $id_loyer,
                ],
                'severity' => ActivityLogsConstants::SEVERITY_ERROR,
            ]);

            error_log("ServiceLoyer - Erreur genererConfirmationPDF: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la génération de la confirmation: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Enregistre le paiement d'un mois de loyer (loyer_detail)
     * Délègue à LoyerControleur::payerDetail
     *
     * @param int   $id_loyer ID du loyer
     * @param array $data     Données du paiement (id_loyer_detail, date_paiement, montant_paye, etc.)
     * @return array          Résultat avec success, id_paiement, loyer_statut, etc.
     */
    public function payerDetail($id_loyer, $data) {
        try {
            return LoyerControleur::payerDetail($this->conn, $id_loyer, $data);
        } catch (Exception $e) {
            error_log("ServiceLoyer - Erreur payerDetail: " . $e->getMessage());
            throw $e;
        }
    }
}