<?php
/**
 * ServiceAuthentification.php - Version finale avec ActivityLogger intégré
 * Emplacement: fact-back/services/ServiceAuthentification.php
 */

require_once realpath(__DIR__ . '/../controllers/AuthentificationControleur.php');
require_once realpath(__DIR__ . '/ActivityLogger.php'); // NOUVEAU: ActivityLogger

class ServiceAuthentification {
    private $conn;
    private $emailService = null;
    private $activityLogger = null; // NOUVEAU: ActivityLogger

    public function __construct($conn, $emailService = null) {
        $this->conn = $conn;
        
        // Si un service d'email est fourni, l'utiliser
        if ($emailService !== null) {
            $this->emailService = $emailService;
        }
        // Sinon, on ne crée pas d'instance par défaut
        // L'email sera initialisé à la demande dans les méthodes qui en ont besoin
        
        // NOUVEAU: Initialiser l'ActivityLogger
        $this->activityLogger = new ActivityLogger($conn);
    }
    
    /**
     * Authentifie un utilisateur avec son nom d'utilisateur et mot de passe
     * 
     * @param string $username Nom d'utilisateur
     * @param string $password Mot de passe
     * @return array Résultat de l'authentification
     */
    public function authentifier($username, $password) {
        try {
            // Vérifier si le compte est bloqué avant de tenter la connexion
            if (AuthentificationControleur::estBloque($this->conn, $username)) {
                // NOUVEAU: Logger la tentative bloquée
                $this->activityLogger->logAuthFailed($username, 'account_blocked');
                
                return [
                    'success' => false,
                    'message' => 'Compte temporairement bloqué suite à de nombreuses tentatives infructueuses. Veuillez réessayer plus tard.'
                ];
            }
            
            // Vérifier les identifiants
            $resultat = AuthentificationControleur::verifierIdentifiants($this->conn, $username, $password);
            
            // NOUVEAU: Logger selon le résultat
            if ($resultat['success']) {
                $this->activityLogger->logUserLogin(
                    $resultat['utilisateur']['id_utilisateur'], 
                    $resultat['utilisateur']['username']
                );
            } else {
                $this->activityLogger->logAuthFailed($username, 'invalid_credentials');
            }
            
            return $resultat;
        } catch (Exception $e) {
            // NOUVEAU: Logger les erreurs système
            $this->activityLogger->logSystemError("Erreur authentification: " . $e->getMessage(), [
                'username' => $username,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'authentification: ' . $e->getMessage()
            ];
        }
    }

    /**
     * NOUVEAU: Déconnecte un utilisateur avec logging
     */
    public function deconnecter() {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            $userName = $_SESSION['user_name'] ?? null;
            
            // Logger la déconnexion AVANT de détruire la session
            if ($userId && $userName) {
                $this->activityLogger->logUserLogout($userId, $userName);
            }
            
            // Détruire la session
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            session_destroy();
            
            return [
                'success' => true,
                'message' => 'Déconnexion réussie'
            ];
        } catch (Exception $e) {
            $this->activityLogger->logSystemError("Erreur déconnexion: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la déconnexion: ' . $e->getMessage()
            ];
        }
    }

    /**
     * NOUVEAU: Vérifie si un utilisateur est connecté et retourne ses informations
     */
    public function verifierSession() {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $userId = $_SESSION['user_id'] ?? null;
            
            if (!$userId) {
                return [
                    'success' => false,
                    'message' => 'Session non valide',
                    'authenticated' => false
                ];
            }
            
            // Vérifier que l'utilisateur existe toujours en base
            $utilisateurResult = $this->getUtilisateurParId($userId);
            
            if (!$utilisateurResult['success']) {
                // Session invalide, la détruire
                session_destroy();
                return [
                    'success' => false,
                    'message' => 'Utilisateur introuvable ou compte inactif',
                    'authenticated' => false
                ];
            }
            
            $utilisateur = $utilisateurResult['utilisateur'];
            
            if ($utilisateur['compte_actif'] != 1) {
                // Compte inactif, détruire la session
                session_destroy();
                return [
                    'success' => false,
                    'message' => 'Compte inactif',
                    'authenticated' => false
                ];
            }
            
            return [
                'success' => true,
                'authenticated' => true,
                'user' => [
                    'id' => $utilisateur['id_utilisateur'],
                    'username' => $utilisateur['username'],
                    'nom' => $utilisateur['nom'],
                    'prenom' => $utilisateur['prenom'],
                    'email' => $utilisateur['email'],
                    'role' => $utilisateur['role']
                ]
            ];
        } catch (Exception $e) {
            $this->activityLogger->logSystemError("Erreur vérification session: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la vérification de session',
                'authenticated' => false
            ];
        }
    }

    /**
     * NOUVEAU: Récupère les données du dashboard admin
     */
    public function getDashboardData() {
        try {
            // Récupérer les utilisateurs
            $utilisateursResult = $this->getUtilisateurs();
            
            if (!$utilisateursResult['success']) {
                throw new Exception('Erreur lors de la récupération des utilisateurs: ' . $utilisateursResult['message']);
            }
            
            $utilisateurs = $utilisateursResult['utilisateurs'];
            $totalUtilisateurs = count($utilisateurs);
            $utilisateursActifs = count(array_filter($utilisateurs, function($u) { 
                return $u['compte_actif'] == 1; 
            }));
            
            // Récupérer les tentatives récentes
            $tentativesResult = $this->getTentativesRecentes(10);
            
            $tentativesRecentes = $tentativesResult['success'] ? $tentativesResult['tentatives'] : [];
            
            // Récupérer les informations système
            $systemInfo = [
                'phpVersion' => phpversion(),
                'serverSoftware' => $_SERVER['SERVER_SOFTWARE'] ?? 'Inconnu',
                'sessionLifetime' => ini_get('session.gc_maxlifetime'),
                'memoryLimit' => ini_get('memory_limit'),
                'maxExecutionTime' => ini_get('max_execution_time')
            ];
            
            // Ajouter la version de la base de données
            try {
                if (isset($this->conn) && $this->conn instanceof mysqli) {
                    $systemInfo['dbVersion'] = 'MySQL ' . mysqli_get_server_info($this->conn);
                } elseif (isset($this->conn) && $this->conn instanceof PDO) {
                    $systemInfo['dbVersion'] = 'MySQL ' . $this->conn->getAttribute(PDO::ATTR_SERVER_VERSION);
                } else {
                    $systemInfo['dbVersion'] = 'Base de données connectée';
                }
            } catch (Exception $e) {
                $systemInfo['dbVersion'] = 'Erreur de connexion DB';
            }
            
            $data = [
                'utilisateurs' => [
                    'total' => $totalUtilisateurs,
                    'actifs' => $utilisateursActifs,
                    'inactifs' => $totalUtilisateurs - $utilisateursActifs
                ],
                'derniers_utilisateurs' => array_slice($utilisateurs, 0, 5),
                'tentatives' => $tentativesRecentes,
                'systemInfo' => $systemInfo
            ];
            
            return [
                'success' => true,
                'data' => $data
            ];
        } catch (Exception $e) {
            $this->activityLogger->logSystemError("Erreur récupération dashboard: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des données du dashboard: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Récupère les tentatives de connexion récentes
     * 
     * @param int $limit Nombre maximum de tentatives à récupérer
     * @return array Liste des tentatives récentes
     */
    public function getTentativesRecentes($limit = 10) {
        try {
            error_log("🔍 ServiceAuth - getTentativesRecentes appelée avec limit: " . $limit);
            
            $tentatives = AuthentificationControleur::getTentativesRecentes($this->conn, $limit);
            
            error_log("✅ ServiceAuth - Tentatives récupérées: " . count($tentatives));
            
            return [
                'success' => true,
                'tentatives' => $tentatives
            ];
        } catch (Exception $e) {
            error_log("❌ ServiceAuth - Erreur getTentativesRecentes: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des tentatives: ' . $e->getMessage(),
                'tentatives' => [] // Retourner un tableau vide en cas d'erreur
            ];
        }
    }
    
    /**
     * Crée un nouvel utilisateur
     * 
     * @param array $userData Données de l'utilisateur
     * @return array Résultat de l'opération
     */
    public function creerUtilisateur($userData) {
        try {
            // Validation des données
            if (empty($userData['username']) || empty($userData['password'])) {
                return [
                    'success' => false,
                    'message' => 'Nom d\'utilisateur et mot de passe obligatoires'
                ];
            }
            
            // Validation du format du nom d'utilisateur
            if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $userData['username'])) {
                return [
                    'success' => false,
                    'message' => 'Le nom d\'utilisateur doit contenir entre 3 et 20 caractères alphanumériques'
                ];
            }
            
            // Validation du format de l'email (si fourni)
            if (!empty($userData['email']) && !filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => 'Format d\'email invalide'
                ];
            }
            
            // Validation de la force du mot de passe
            if (strlen($userData['password']) < 8) {
                return [
                    'success' => false,
                    'message' => 'Le mot de passe doit contenir au moins 8 caractères'
                ];
            }
            
            // Création de l'utilisateur
            $resultat = AuthentificationControleur::creerUtilisateur($this->conn, $userData);
            
            // NOUVEAU: Logger la création si réussie
            if ($resultat['success']) {
                $currentUser = $this->getCurrentUserFromSession();
                
                $this->activityLogger->logUserCreate(
                    $currentUser['id'] ?? null,
                    $currentUser['username'] ?? 'system',
                    $resultat['userId'],
                    $userData
                );
            }
            
            return $resultat;
        } catch (Exception $e) {
            // NOUVEAU: Logger les erreurs
            $this->activityLogger->logSystemError("Erreur création utilisateur: " . $e->getMessage(), [
                'userData' => array_diff_key($userData, ['password' => ''])
            ]);
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la création de l\'utilisateur: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Met à jour un utilisateur existant
     * 
     * @param int $id ID de l'utilisateur
     * @param array $userData Données de l'utilisateur
     * @return array Résultat de l'opération
     */
    public function modifierUtilisateur($id, $userData) {
        try {
            // Récupérer l'ancien état pour comparaison
            $oldUserResult = $this->getUtilisateurParId($id);
            
            if (!$oldUserResult['success']) {
                return [
                    'success' => false,
                    'message' => 'Utilisateur introuvable'
                ];
            }
            
            $oldUser = $oldUserResult['utilisateur'];
            
            // Validation de l'email (si fourni)
            if (isset($userData['email']) && !empty($userData['email']) && !filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => 'Format d\'email invalide'
                ];
            }
            
            // Mise à jour de l'utilisateur
            $resultat = AuthentificationControleur::modifierUtilisateur($this->conn, $id, $userData);
            
            // NOUVEAU: Logger la modification si réussie
            if ($resultat['success']) {
                $currentUser = $this->getCurrentUserFromSession();
                
                // Calculer les changements
                $changes = [];
                foreach ($userData as $key => $newValue) {
                    if (isset($oldUser[$key]) && $oldUser[$key] != $newValue) {
                        // Ne pas logger les mots de passe
                        if ($key === 'password' || $key === 'password_hash') {
                            $changes[$key] = [
                                'old' => '[MASQUÉ]',
                                'new' => '[MASQUÉ]'
                            ];
                        } else {
                            $changes[$key] = [
                                'old' => $oldUser[$key],
                                'new' => $newValue
                            ];
                        }
                    }
                }
                
                if (!empty($changes)) {
                    $this->activityLogger->logUserUpdate(
                        $currentUser['id'] ?? null,
                        $currentUser['username'] ?? 'system',
                        $id,
                        $changes
                    );
                }
            }
            
            return $resultat;
        } catch (Exception $e) {
            $this->activityLogger->logSystemError("Erreur modification utilisateur: " . $e->getMessage(), [
                'userId' => $id,
                'userData' => array_diff_key($userData, ['password' => ''])
            ]);
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de l\'utilisateur: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Change le mot de passe d'un utilisateur
     * 
     * @param int $id ID de l'utilisateur
     * @param string $oldPassword Ancien mot de passe
     * @param string $newPassword Nouveau mot de passe
     * @return array Résultat de l'opération
     */
    public function changerMotDePasse($id, $oldPassword, $newPassword) {
        try {
            // Validation de la force du nouveau mot de passe
            if (strlen($newPassword) < 8) {
                return [
                    'success' => false,
                    'message' => 'Le nouveau mot de passe doit contenir au moins 8 caractères'
                ];
            }
            
            // Changement du mot de passe
            $resultat = AuthentificationControleur::changerMotDePasse($this->conn, $id, $oldPassword, $newPassword);
            
            // NOUVEAU: Logger le changement si réussi
            if ($resultat['success']) {
                $currentUser = $this->getCurrentUserFromSession();
                $userResult = $this->getUtilisateurParId($id);
                $targetUser = $userResult['success'] ? $userResult['utilisateur']['username'] : 'utilisateur_inconnu';
                
                $this->activityLogger->logUserUpdate(
                    $currentUser['id'] ?? $id,
                    $currentUser['username'] ?? $targetUser,
                    $id,
                    ['password' => ['old' => '[MASQUÉ]', 'new' => '[MASQUÉ]']]
                );
            } else {
                // Logger l'échec du changement de mot de passe
                $userResult = $this->getUtilisateurParId($id);
                $targetUser = $userResult['success'] ? $userResult['utilisateur']['username'] : 'utilisateur_inconnu';
                $this->activityLogger->logAuthFailed($targetUser, 'wrong_old_password');
            }
            
            return $resultat;
        } catch (Exception $e) {
            $this->activityLogger->logSystemError("Erreur changement mot de passe: " . $e->getMessage(), [
                'userId' => $id
            ]);
            
            return [
                'success' => false,
                'message' => 'Erreur lors du changement de mot de passe: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Réinitialise le mot de passe d'un utilisateur (par un administrateur)
     * 
     * @param int $id ID de l'utilisateur
     * @param string $newPassword Nouveau mot de passe
     * @return array Résultat de l'opération
     */
    public function reinitialiserMotDePasse($id, $newPassword) {
        try {
            $currentUser = $this->getCurrentUserFromSession();
            
            // Vérifier les droits admin
            if ($currentUser['role'] !== 'admin') {
                $this->activityLogger->logAccessDenied(
                    $currentUser['id'],
                    $currentUser['username'],
                    'reset_password'
                );
                
                return [
                    'success' => false,
                    'message' => 'Droits administrateur requis'
                ];
            }
            
            // Validation de la force du nouveau mot de passe
            if (strlen($newPassword) < 8) {
                return [
                    'success' => false,
                    'message' => 'Le nouveau mot de passe doit contenir au moins 8 caractères'
                ];
            }
            
            // Récupérer les infos de l'utilisateur cible
            $userResult = $this->getUtilisateurParId($id);
            if (!$userResult['success']) {
                return [
                    'success' => false,
                    'message' => 'Utilisateur introuvable'
                ];
            }
            
            $targetUser = $userResult['utilisateur'];
            
            // Réinitialisation du mot de passe
            $resultat = AuthentificationControleur::reinitialiserMotDePasse($this->conn, $id, $newPassword);
            
            // NOUVEAU: Logger la réinitialisation si réussie
            if ($resultat['success']) {
                $this->activityLogger->log([
                    'user_id' => $currentUser['id'],
                    'user_name' => $currentUser['username'],
                    'action_type' => 'auth_password_reset',
                    'entity_type' => 'user',
                    'entity_id' => $id,
                    'description' => "Réinitialisation du mot de passe de l'utilisateur {$targetUser['username']} par {$currentUser['username']}",
                    'details' => [
                        'target_user' => $targetUser['username'],
                        'reset_by' => $currentUser['username']
                    ],
                    'severity' => 'warning'
                ]);
            }
            
            return $resultat;
        } catch (Exception $e) {
            $this->activityLogger->logSystemError("Erreur réinitialisation mot de passe: " . $e->getMessage(), [
                'userId' => $id
            ]);
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la réinitialisation du mot de passe: ' . $e->getMessage()
            ];
        }
    }

    /**
     * NOUVEAU: Active ou désactive un compte utilisateur
     */
    public function toggleCompteActif($userId) {
        try {
            $currentUser = $this->getCurrentUserFromSession();
            
            // Vérifier les droits
            if ($currentUser['role'] !== 'admin' && $currentUser['role'] !== 'gestionnaire') {
                $this->activityLogger->logAccessDenied(
                    $currentUser['id'],
                    $currentUser['username'],
                    'toggle_user_account'
                );
                
                return [
                    'success' => false,
                    'message' => 'Droits insuffisants'
                ];
            }
            
            $userResult = $this->getUtilisateurParId($userId);
            
            if (!$userResult['success']) {
                return [
                    'success' => false,
                    'message' => 'Utilisateur introuvable'
                ];
            }
            
            $utilisateur = $userResult['utilisateur'];
            
            // Empêcher de désactiver sa propre session
            if ($currentUser['id'] == $userId && $utilisateur['compte_actif'] == 1) {
                return [
                    'success' => false,
                    'message' => 'Impossible de désactiver votre propre compte'
                ];
            }
            
            $nouveauStatut = $utilisateur['compte_actif'] == 1 ? 0 : 1;
            
            $resultat = AuthentificationControleur::modifierUtilisateur($this->conn, $userId, [
                'compte_actif' => $nouveauStatut
            ]);
            
            if ($resultat['success']) {
                $this->activityLogger->logUserUpdate(
                    $currentUser['id'],
                    $currentUser['username'],
                    $userId,
                    [
                        'compte_actif' => [
                            'old' => $utilisateur['compte_actif'],
                            'new' => $nouveauStatut
                        ]
                    ]
                );
            }
            
            return $resultat;
        } catch (Exception $e) {
            $this->activityLogger->logSystemError("Erreur toggle compte: " . $e->getMessage(), [
                'userId' => $userId
            ]);
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la modification du compte: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Récupère tous les utilisateurs
     * 
     * @return array Liste des utilisateurs
     */
    public function getUtilisateurs() {
        try {
            $utilisateurs = AuthentificationControleur::getUtilisateurs($this->conn);
            
            return [
                'success' => true,
                'utilisateurs' => $utilisateurs
            ];
        } catch (Exception $e) {
            $this->activityLogger->logSystemError("Erreur récupération utilisateurs: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des utilisateurs: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Récupère un utilisateur par son ID
     * 
     * @param int $id ID de l'utilisateur
     * @return array Informations de l'utilisateur
     */
    public function getUtilisateurParId($id) {
        try {
            $utilisateur = AuthentificationControleur::getUtilisateurParId($this->conn, $id);
            
            if ($utilisateur) {
                return [
                    'success' => true,
                    'utilisateur' => $utilisateur
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Utilisateur non trouvé'
                ];
            }
        } catch (Exception $e) {
            $this->activityLogger->logSystemError("Erreur récupération utilisateur: " . $e->getMessage(), [
                'userId' => $id
            ]);
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'utilisateur: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Supprime un utilisateur
     * 
     * @param int $id ID de l'utilisateur
     * @return array Résultat de l'opération
     */
    public function supprimerUtilisateur($id) {
        try {
            // Récupérer les infos utilisateur avant suppression
            $userResult = $this->getUtilisateurParId($id);
            
            if (!$userResult['success']) {
                return [
                    'success' => false,
                    'message' => 'Utilisateur introuvable'
                ];
            }
            
            $userToDelete = $userResult['utilisateur'];
            
            // Vérifier qu'on ne supprime pas sa propre session
            $currentUser = $this->getCurrentUserFromSession();
            if ($currentUser['id'] == $id) {
                return [
                    'success' => false,
                    'message' => 'Impossible de supprimer votre propre compte'
                ];
            }
            
            $resultat = AuthentificationControleur::supprimerUtilisateur($this->conn, $id);
            
            // NOUVEAU: Logger la suppression si réussie
            if ($resultat['success']) {
                $this->activityLogger->logUserDelete(
                    $currentUser['id'] ?? null,
                    $currentUser['username'] ?? 'system',
                    $id,
                    $userToDelete['username'] ?? 'utilisateur_inconnu'
                );
            }
            
            return $resultat;
        } catch (Exception $e) {
            $this->activityLogger->logSystemError("Erreur suppression utilisateur: " . $e->getMessage(), [
                'userId' => $id
            ]);
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la suppression de l\'utilisateur: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Demande une réinitialisation de mot de passe pour un utilisateur via son email
     * 
     * @param string $email Email de l'utilisateur
     * @return array Résultat de l'opération et données nécessaires pour l'envoi d'email
     */
    public function demanderResetPassword($email) {
        try {
            // Validation de l'email
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => 'Adresse email invalide'
                ];
            }
            
            // Créer un token de réinitialisation
            $result = AuthentificationControleur::creerTokenResetPassword($this->conn, $email);
            
            // Si un utilisateur a été trouvé et un token créé, envoyer l'email
            if ($result['success'] && $result['userFound']) {
                // NOUVEAU: Logger la demande de reset
                $this->activityLogger->log([
                    'user_name' => $result['user']['username'] ?? 'utilisateur_inconnu',
                    'action_type' => 'auth_password_reset',
                    'description' => "Demande de réinitialisation de mot de passe pour {$email}",
                    'details' => ['email' => $email],
                    'severity' => 'info'
                ]);
                
                // Créer une instance du service d'email
                if ($this->emailService === null) {
                    require_once realpath(__DIR__ . '/EmailService.php');
                    $this->emailService = new EmailService();
                }
                
                // Déterminer l'URL de réinitialisation
                $resetLink = app_url('/#/public/reset-password?token=' . $result['token']);
                
                // Envoyer l'email de réinitialisation
                $emailSent = $this->emailService->envoyerResetPassword($result['user'], $result['token'], $resetLink);

                // Ajouter le débogage ici
                error_log("Tentative d'envoi d'email à {$email}");
                error_log("Token: {$result['token']}");
                error_log("Reset Link: {$resetLink}");
                error_log("Email envoyé: " . ($emailSent ? "Oui" : "Non"));
                
                if (!$emailSent) {
                    error_log("Échec d'envoi d'email de réinitialisation à " . $email);
                }
            }
            
            // Toujours retourner un message générique pour des raisons de sécurité
            return [
                'success' => true,
                'message' => 'Si cette adresse email est associée à un compte, un lien de réinitialisation vous sera envoyé.'
            ];
            
        } catch (Exception $e) {
            error_log("Erreur lors de la demande de réinitialisation: " . $e->getMessage());
            
            $this->activityLogger->logSystemError("Erreur demande reset password: " . $e->getMessage(), [
                'email' => $email
            ]);
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la demande de réinitialisation'
            ];
        }
    }

    /**
     * Vérifie si un token de réinitialisation est valide
     * 
     * @param string $token Token de réinitialisation
     * @return array Résultat de l'opération
     */
    public function verifierTokenResetPassword($token) {
        try {
            // Valider le token
            if (empty($token)) {
                return [
                    'success' => false,
                    'message' => 'Token non fourni'
                ];
            }
            
            $result = AuthentificationControleur::verifierTokenResetPassword($this->conn, $token);
            
            // NOUVEAU: Logger la vérification de token
            if ($result['success']) {
                $this->activityLogger->log([
                    'action_type' => 'auth_token_verify',
                    'description' => "Vérification de token de réinitialisation réussie",
                    'details' => ['token_valid' => true],
                    'severity' => 'info'
                ]);
            } else {
                $this->activityLogger->log([
                    'action_type' => 'auth_token_verify',
                    'description' => "Tentative de vérification de token invalide",
                    'details' => ['token_valid' => false, 'reason' => $result['message']],
                    'severity' => 'warning'
                ]);
            }
            
            return $result;
        } catch (Exception $e) {
            $this->activityLogger->logSystemError("Erreur vérification token: " . $e->getMessage(), [
                'token_provided' => !empty($token)
            ]);
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la vérification du token: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Réinitialise le mot de passe avec un token valide
     * 
     * @param string $token Token de réinitialisation
     * @param string $newPassword Nouveau mot de passe
     * @return array Résultat de l'opération
     */
    public function resetPasswordAvecToken($token, $newPassword) {
        try {
            // Validation de base
            if (empty($token) || empty($newPassword)) {
                return [
                    'success' => false,
                    'message' => 'Token et nouveau mot de passe requis'
                ];
            }
            
            // Validation de la force du mot de passe
            if (strlen($newPassword) < 8) {
                return [
                    'success' => false,
                    'message' => 'Le mot de passe doit contenir au moins 8 caractères'
                ];
            }
            
            // Vérifier si le token est valide
            $tokenCheck = AuthentificationControleur::verifierTokenResetPassword($this->conn, $token);
            
            if (!$tokenCheck['success']) {
                // NOUVEAU: Logger l'échec de la réinitialisation
                $this->activityLogger->log([
                    'action_type' => 'auth_password_reset_failed',
                    'description' => "Tentative de réinitialisation avec token invalide",
                    'details' => ['reason' => $tokenCheck['message']],
                    'severity' => 'warning'
                ]);
                
                return $tokenCheck;
            }
            
            // Réinitialiser le mot de passe
            $userId = $tokenCheck['userId'];
            $resetResult = AuthentificationControleur::reinitialiserMotDePasse($this->conn, $userId, $newPassword);
            
            if ($resetResult['success']) {
                // Marquer le token comme utilisé
                AuthentificationControleur::marquerTokenUtilise($this->conn, $token);
                
                // NOUVEAU: Logger la réinitialisation réussie
                $userResult = $this->getUtilisateurParId($userId);
                $username = $userResult['success'] ? $userResult['utilisateur']['username'] : 'utilisateur_inconnu';
                
                $this->activityLogger->log([
                    'user_id' => $userId,
                    'user_name' => $username,
                    'action_type' => 'auth_password_reset_success',
                    'description' => "Réinitialisation de mot de passe réussie via token pour {$username}",
                    'details' => ['reset_method' => 'token'],
                    'severity' => 'info'
                ]);
            }
            
            return $resetResult;
        } catch (Exception $e) {
            $this->activityLogger->logSystemError("Erreur reset password avec token: " . $e->getMessage(), [
                'token_provided' => !empty($token)
            ]);
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la réinitialisation du mot de passe: ' . $e->getMessage()
            ];
        }
    }

    /**
     * NOUVEAU: Méthode utilitaire pour récupérer l'utilisateur actuel depuis la session
     */
    private function getCurrentUserFromSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['user_name'] ?? null,
            'role' => $_SESSION['user_role'] ?? null
        ];
    }
    
    /**
     * NOUVEAU: Getter pour accéder au logger depuis l'extérieur si nécessaire
     */
    public function getActivityLogger() {
        return $this->activityLogger;
    }
}
?>