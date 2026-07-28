<?php
/**
 * ServiceAuthentification.php - Version corrigée avec ActivityLogger
 * Emplacement: fact-back/services/ServiceAuthentification.php
 */

require_once realpath(__DIR__ . '/../controllers/AuthentificationControleur.php');
require_once realpath(__DIR__ . '/ActivityLogger.php');
require_once realpath(__DIR__ . '/../constants/ActivityLogsConstants.php'); // ✅ AJOUT: Constantes

class ServiceAuthentification {
    private $conn;
    private $emailService = null;
    private $activityLogger = null;

    public function __construct($conn, $emailService = null) {
        $this->conn = $conn;
        
        if ($emailService !== null) {
            $this->emailService = $emailService;
        }
        
        // Initialiser l'ActivityLogger
        $this->activityLogger = new ActivityLogger($conn);
    }
    
    /**
     * Authentifie un utilisateur avec son nom d'utilisateur et mot de passe
     */
    public function authentifier($username, $password) {
        try {
            // Vérifier si le compte est bloqué avant de tenter la connexion
            if (AuthentificationControleur::estBloque($this->conn, $username)) {
                // ✅ CORRECTION: Utiliser logAuthFailed avec un seul paramètre
                $this->activityLogger->logAuthFailed($username);
                
                return [
                    'success' => false,
                    'message' => 'Compte temporairement bloqué suite à de nombreuses tentatives infructueuses. Veuillez réessayer plus tard.'
                ];
            }
            
            // Vérifier les identifiants
            $resultat = AuthentificationControleur::verifierIdentifiants($this->conn, $username, $password);
            
            // Logger selon le résultat
            if ($resultat['success']) {
                $this->activityLogger->logLogin(
                    $resultat['utilisateur']['id_utilisateur'], 
                    $resultat['utilisateur']['username']
                );
            } else {
                $this->activityLogger->logAuthFailed($username);
            }
            
            return $resultat;
        } catch (Exception $e) {
            // Logger les erreurs système
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
     * Déconnecte un utilisateur avec logging
     */
    public function deconnecter() {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            $userName = $_SESSION['user_name'] ?? null;
            
            // Logger la déconnexion AVANT de détruire la session
            if ($userId && $userName) {
                $this->activityLogger->logLogout($userId, $userName);
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
     * Vérifie si un utilisateur est connecté et retourne ses informations
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
     * Récupère les données du dashboard admin
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
                'tentatives' => []
            ];
        }
    }
    
    /**
     * Crée un nouvel utilisateur
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
            
            // Logger la création si réussie
            if ($resultat['success']) {
                $currentUser = $this->getCurrentUserFromSession();
                
                // ✅ CORRECTION: Utiliser logEntityCreate
                $this->activityLogger->logEntityCreate(
                    ActivityLogsConstants::ENTITY_USER,
                    $resultat['userId'],
                    $currentUser['id'] ?? null,
                    $currentUser['username'] ?? 'system',
                    array_diff_key($userData, ['password' => '']) // Exclure le mot de passe des détails
                );
            }
            
            return $resultat;
        } catch (Exception $e) {
            // Logger les erreurs
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
            
            // Normaliser compte_actif en entier avant toute mise à jour
            if (isset($userData['compte_actif'])) {
                $userData['compte_actif'] = toTinyInt($userData['compte_actif']);
            }

            // Mise à jour de l'utilisateur
            $resultat = AuthentificationControleur::modifierUtilisateur($this->conn, $id, $userData);
            
            // Logger la modification si réussie
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
                    // ✅ CORRECTION: Utiliser logEntityUpdate
                    $this->activityLogger->logEntityUpdate(
                        ActivityLogsConstants::ENTITY_USER,
                        $id,
                        $currentUser['id'] ?? null,
                        $currentUser['username'] ?? 'system',
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
            
            // Logger le changement si réussi
            if ($resultat['success']) {
                $currentUser = $this->getCurrentUserFromSession();
                $userResult = $this->getUtilisateurParId($id);
                $targetUser = $userResult['success'] ? $userResult['utilisateur']['username'] : 'utilisateur_inconnu';
                
                // ✅ CORRECTION: Utiliser log() directement avec les constantes
                $this->activityLogger->log([
                    'user_id' => $currentUser['id'] ?? $id,
                    'user_name' => $currentUser['username'] ?? $targetUser,
                    'action_type' => ActivityLogsConstants::ACTION_AUTH_PASSWORD_CHANGE,
                    'entity_type' => ActivityLogsConstants::ENTITY_USER,
                    'entity_id' => $id,
                    'description' => "Changement de mot de passe pour l'utilisateur {$targetUser}",
                    'details' => ['password' => ['old' => '[MASQUÉ]', 'new' => '[MASQUÉ]']],
                    'severity' => ActivityLogsConstants::SEVERITY_INFO
                ]);
            } else {
                // Logger l'échec du changement de mot de passe
                $userResult = $this->getUtilisateurParId($id);
                $targetUser = $userResult['success'] ? $userResult['utilisateur']['username'] : 'utilisateur_inconnu';
                $this->activityLogger->logAuthFailed($targetUser);
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
            
            // Logger la réinitialisation si réussie
            if ($resultat['success']) {
                $this->activityLogger->log([
                    'user_id' => $currentUser['id'],
                    'user_name' => $currentUser['username'],
                    'action_type' => ActivityLogsConstants::ACTION_AUTH_PASSWORD_RESET,
                    'entity_type' => ActivityLogsConstants::ENTITY_USER,
                    'entity_id' => $id,
                    'description' => "Réinitialisation du mot de passe de l'utilisateur {$targetUser['username']} par {$currentUser['username']}",
                    'details' => [
                        'target_user' => $targetUser['username'],
                        'reset_by' => $currentUser['username']
                    ],
                    'severity' => ActivityLogsConstants::SEVERITY_WARNING
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
     * Active ou désactive un compte utilisateur
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
                // ✅ CORRECTION: Logger avec les constantes
                $action = $nouveauStatut == 1 ? ActivityLogsConstants::ACTION_USER_ACTIVATE : ActivityLogsConstants::ACTION_USER_DEACTIVATE;
                
                $this->activityLogger->log([
                    'user_id' => $currentUser['id'],
                    'user_name' => $currentUser['username'],
                    'action_type' => $action,
                    'entity_type' => ActivityLogsConstants::ENTITY_USER,
                    'entity_id' => $userId,
                    'description' => ($nouveauStatut == 1 ? 'Activation' : 'Désactivation') . " du compte utilisateur {$utilisateur['username']}",
                    'details' => [
                        'compte_actif' => [
                            'old' => $utilisateur['compte_actif'],
                            'new' => $nouveauStatut
                        ]
                    ],
                    'severity' => ActivityLogsConstants::SEVERITY_INFO
                ]);
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
            
            // Logger la suppression si réussie
            if ($resultat['success']) {
                // ✅ CORRECTION: Utiliser logEntityDelete
                $this->activityLogger->logEntityDelete(
                    ActivityLogsConstants::ENTITY_USER,
                    $id,
                    $currentUser['id'] ?? null,
                    $currentUser['username'] ?? 'system',
                    ['deleted_username' => $userToDelete['username'] ?? 'utilisateur_inconnu']
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

    // ... Le reste des méthodes (demanderResetPassword, verifierTokenResetPassword, etc.) 
    // restent identiques mais utilisent les bonnes méthodes du logger

    /**
     * Méthode utilitaire pour récupérer l'utilisateur actuel depuis la session
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
     * Getter pour accéder au logger depuis l'extérieur si nécessaire
     */
    public function getActivityLogger() {
        return $this->activityLogger;
    }
}
?>