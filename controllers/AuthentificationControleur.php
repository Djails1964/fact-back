<?php
/**
 * AuthentificationControleur.php
 * 
 * Contrôleur pour la gestion de l'authentification des utilisateurs
 */

class AuthentificationControleur {
    /**
     * Récupère tous les utilisateurs
     * 
     * @param PDO $conn La connexion à la base de données
     * @return array Liste des utilisateurs
     */
    public static function getUtilisateurs($conn) {
        try {
            $sql = "SELECT id_utilisateur, username, nom, prenom, email, role, compte_actif, 
                    DATE_FORMAT(derniere_connexion, '%d/%m/%Y %H:%i') as derniere_connexion, 
                    DATE_FORMAT(date_creation, '%d/%m/%Y') as date_creation
                    FROM utilisateurs
                    ORDER BY compte_actif DESC, username ASC";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des utilisateurs: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération des utilisateurs');
        }
    }

    /**
     * Récupère un utilisateur par son ID
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id ID de l'utilisateur
     * @return array|false Informations de l'utilisateur ou false si non trouvé
     */
    public static function getUtilisateurParId($conn, $id) {
        try {
            $sql = "SELECT id_utilisateur, username, nom, prenom, email, role, compte_actif, 
                    DATE_FORMAT(derniere_connexion, '%d/%m/%Y %H:%i') as derniere_connexion, 
                    DATE_FORMAT(date_creation, '%d/%m/%Y') as date_creation
                    FROM utilisateurs 
                    WHERE id_utilisateur = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération de l'utilisateur: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération de l\'utilisateur');
        }
    }

    /**
     * Modifie un utilisateur existant
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id ID de l'utilisateur
     * @param array $data Nouvelles données de l'utilisateur
     * @return array Résultat de l'opération
     */
    public static function modifierUtilisateur($conn, $id, $data) {
        try {
            // Vérifier si l'utilisateur existe
            $checkUserSql = "SELECT COUNT(*) as nb FROM utilisateurs WHERE id_utilisateur = ?";
            $checkUserStmt = $conn->prepare($checkUserSql);
            $checkUserStmt->execute([$id]);
            
            if ($checkUserStmt->fetch(PDO::FETCH_ASSOC)['nb'] == 0) {
                return [
                    'success' => false,
                    'message' => 'Utilisateur non trouvé'
                ];
            }
            
            // Vérifier si l'email est déjà utilisé par un autre utilisateur
            if (isset($data['email']) && !empty($data['email'])) {
                $checkEmailSql = "SELECT COUNT(*) as nb FROM utilisateurs WHERE email = ? AND id_utilisateur != ?";
                $checkEmailStmt = $conn->prepare($checkEmailSql);
                $checkEmailStmt->execute([$data['email'], $id]);
                
                if ($checkEmailStmt->fetch(PDO::FETCH_ASSOC)['nb'] > 0) {
                    return [
                        'success' => false,
                        'message' => 'Cet email est déjà utilisé par un autre utilisateur'
                    ];
                }
            }
            
            // Construire la requête SQL de mise à jour
            $updateFields = [];
            $updateValues = [];
            
            // Mise à jour des champs simples
            $allowedFields = ['nom', 'prenom', 'email', 'role', 'compte_actif'];
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateFields[] = "$field = ?";
                    $updateValues[] = $data[$field];
                }
            }
            
            // Mise à jour du mot de passe si fourni
            if (isset($data['password']) && !empty($data['password'])) {
                $updateFields[] = "password_hash = ?";
                $updateValues[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            
            // Ajouter l'ID à la fin des valeurs pour la clause WHERE
            $updateValues[] = $id;
            
            // Si aucun champ n'est à mettre à jour
            if (empty($updateFields)) {
                return [
                    'success' => false,
                    'message' => 'Aucune modification à effectuer'
                ];
            }
            
            // Exécuter la requête de mise à jour
            $sql = "UPDATE utilisateurs SET " . implode(', ', $updateFields) . ", date_modification = NOW() WHERE id_utilisateur = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute($updateValues);
            
            // Vérifier si la mise à jour a réussi
            if ($stmt->rowCount() > 0) {
                return [
                    'success' => true,
                    'message' => 'Utilisateur mis à jour avec succès'
                ];
            } else {
                return [
                    'success' => true,
                    'message' => 'Aucune modification effectuée'
                ];
            }
        } catch (PDOException $e) {
            error_log("Erreur lors de la modification de l'utilisateur: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la modification de l\'utilisateur'
            ];
        }
    }

    /**
     * Réinitialise le mot de passe d'un utilisateur (par un administrateur)
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id ID de l'utilisateur
     * @param string $newPassword Nouveau mot de passe
     * @return array Résultat de l'opération
     */
    public static function reinitialiserMotDePasse($conn, $id, $newPassword) {
        try {
            // Vérifier si l'utilisateur existe
            $checkUserSql = "SELECT COUNT(*) as nb FROM utilisateurs WHERE id_utilisateur = ?";
            $checkUserStmt = $conn->prepare($checkUserSql);
            $checkUserStmt->execute([$id]);
            
            if ($checkUserStmt->fetch(PDO::FETCH_ASSOC)['nb'] == 0) {
                return [
                    'success' => false,
                    'message' => 'Utilisateur non trouvé'
                ];
            }
            
            // Hacher le nouveau mot de passe
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Mettre à jour le mot de passe
            $sql = "UPDATE utilisateurs SET password_hash = ?, date_modification = NOW() WHERE id_utilisateur = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$newHash, $id]);
            
            return [
                'success' => true,
                'message' => 'Mot de passe réinitialisé avec succès'
            ];
        } catch (PDOException $e) {
            error_log("Erreur lors de la réinitialisation du mot de passe: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la réinitialisation du mot de passe'
            ];
        }
    }

    /**
     * Supprime un utilisateur
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id ID de l'utilisateur
     * @return array Résultat de l'opération
     */
    public static function supprimerUtilisateur($conn, $id) {
        try {
            // Vérifier si l'utilisateur existe
            $checkUserSql = "SELECT COUNT(*) as nb FROM utilisateurs WHERE id_utilisateur = ?";
            $checkUserStmt = $conn->prepare($checkUserSql);
            $checkUserStmt->execute([$id]);
            
            if ($checkUserStmt->fetch(PDO::FETCH_ASSOC)['nb'] == 0) {
                return [
                    'success' => false,
                    'message' => 'Utilisateur non trouvé'
                ];
            }
            
            // Option 1: Suppression complète (Pas recommandé si des relations existent)
            $sql = "DELETE FROM utilisateurs WHERE id_utilisateur = ?";
            
            // Option 2: Désactivation du compte (Préférable dans la plupart des cas)
            // $sql = "UPDATE utilisateurs SET compte_actif = 0, date_modification = NOW() WHERE id_utilisateur = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() > 0) {
                return [
                    'success' => true,
                    'message' => 'Utilisateur supprimé avec succès'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Erreur lors de la suppression de l\'utilisateur'
                ];
            }
        } catch (PDOException $e) {
            error_log("Erreur lors de la suppression de l'utilisateur: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la suppression de l\'utilisateur: ' . $e->getMessage()
            ];
        }
    }
    /**
     * Vérifie les informations d'identifications d'un utilisateur
     * 
     * @param PDO $conn La connexion à la base de données
     * @param string $username Nom d'utilisateur
     * @param string $password Mot de passe en clair
     * @return array Informations sur l'authentification (succès, utilisateur, message)
     */
    public static function verifierIdentifiants($conn, $username, $password) {
        try {
            // Récupérer l'utilisateur par son nom d'utilisateur
            $sql = "SELECT * FROM utilisateurs WHERE username = ? AND compte_actif = 1";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$username]);
            
            $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Enregistrer la tentative de connexion
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            
            // Vérifier si l'utilisateur existe
            if (!$utilisateur) {
                self::enregistrerTentative($conn, $username, $ip, false);
                return [
                    'success' => false,
                    'message' => 'Utilisateur non trouvé ou compte inactif'
                ];
            }
            
            // Vérifier si le mot de passe correspond
            if (password_verify($password, $utilisateur['password_hash'])) {
                // Mise à jour de la dernière connexion
                $sqlUpdate = "UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id_utilisateur = ?";
                $stmtUpdate = $conn->prepare($sqlUpdate);
                $stmtUpdate->execute([$utilisateur['id_utilisateur']]);
                
                // Enregistrer la tentative réussie
                self::enregistrerTentative($conn, $username, $ip, true);
                
                // Vérifier si le hash doit être mis à jour (nouvelles options de hachage)
                if (password_needs_rehash($utilisateur['password_hash'], PASSWORD_DEFAULT)) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $sqlRehash = "UPDATE utilisateurs SET password_hash = ? WHERE id_utilisateur = ?";
                    $stmtRehash = $conn->prepare($sqlRehash);
                    $stmtRehash->execute([$newHash, $utilisateur['id_utilisateur']]);
                }
                
                // Supprimer le mot de passe des informations renvoyées
                unset($utilisateur['password_hash']);
                
                return [
                    'success' => true,
                    'utilisateur' => $utilisateur,
                    'message' => 'Authentification réussie'
                ];
            } else {
                // Enregistrer l'échec
                self::enregistrerTentative($conn, $username, $ip, false);
                
                return [
                    'success' => false,
                    'message' => 'Mot de passe incorrect'
                ];
            }
        } catch (PDOException $e) {
            error_log("Erreur lors de la vérification des identifiants: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la vérification des identifiants'
            ];
        }
    }
    
    /**
     * Enregistre une tentative de connexion
     * 
     * @param PDO $conn La connexion à la base de données
     * @param string $username Nom d'utilisateur
     * @param string $ip Adresse IP
     * @param bool $success Succès de la tentative
     */
    private static function enregistrerTentative($conn, $username, $ip, $success) {
        try {
            $sql = "INSERT INTO tentatives_connexion (username, ip_address, reussite) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$username, $ip, $success ? 1 : 0]);
        } catch (PDOException $e) {
            error_log("Erreur lors de l'enregistrement de la tentative de connexion: " . $e->getMessage());
        }
    }
    
    /**
     * Vérifie si un utilisateur est bloqué après trop de tentatives échouées
     * 
     * @param PDO $conn La connexion à la base de données
     * @param string $username Nom d'utilisateur
     * @param int $maxTentatives Nombre maximal de tentatives autorisées
     * @param int $dureeBloquage Durée du blocage en minutes
     * @return bool True si l'utilisateur est bloqué, false sinon
     */
    public static function estBloque($conn, $username, $maxTentatives = 5, $dureeBloquage = 15) {
        try {
            // Vérifier les tentatives échouées dans les dernières minutes
            $sql = "SELECT COUNT(*) as nb_tentatives 
                    FROM tentatives_connexion 
                    WHERE username = ? 
                    AND reussite = 0 
                    AND date_tentative > DATE_SUB(NOW(), INTERVAL ? MINUTE)";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$username, $dureeBloquage]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['nb_tentatives'] >= $maxTentatives;
        } catch (PDOException $e) {
            error_log("Erreur lors de la vérification du blocage: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère les tentatives de connexion récentes
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $limit Nombre maximum de tentatives à récupérer
     * @return array Liste des tentatives récentes
     */
    public static function getTentativesRecentes($conn, $limit = 10) {
        try {
            $sql = "SELECT username, ip_address, reussite, 
                    DATE_FORMAT(date_tentative, '%d/%m/%Y %H:%i:%s') as date_tentative
                    FROM tentatives_connexion 
                    ORDER BY date_tentative DESC 
                    LIMIT ?";
            
            $stmt = $conn->prepare($sql);
            
            // CORRECTION: Utiliser bindValue avec PDO::PARAM_INT pour le LIMIT
            $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $tentatives = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("✅ getTentativesRecentes - " . count($tentatives) . " tentatives récupérées");
            
            return $tentatives;
        } catch (PDOException $e) {
            error_log("❌ Erreur getTentativesRecentes: " . $e->getMessage());
            error_log("❌ SQL: " . $sql);
            error_log("❌ Limit value: " . $limit);
            throw new Exception('Erreur lors de la récupération des tentatives de connexion: ' . $e->getMessage());
        }
    }
    
    /**
     * Modifie le mot de passe d'un utilisateur
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $userId ID de l'utilisateur
     * @param string $ancienMdp Ancien mot de passe
     * @param string $nouveauMdp Nouveau mot de passe
     * @return array Résultat de l'opération
     */
    public static function changerMotDePasse($conn, $userId, $ancienMdp, $nouveauMdp) {
        try {
            // Vérifier l'ancien mot de passe
            $sql = "SELECT password_hash FROM utilisateurs WHERE id_utilisateur = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$userId]);
            
            $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$utilisateur) {
                return [
                    'success' => false,
                    'message' => 'Utilisateur non trouvé'
                ];
            }
            
            if (!password_verify($ancienMdp, $utilisateur['password_hash'])) {
                return [
                    'success' => false,
                    'message' => 'Ancien mot de passe incorrect'
                ];
            }
            
            // Hacher le nouveau mot de passe
            $newHash = password_hash($nouveauMdp, PASSWORD_DEFAULT);
            
            // Mettre à jour le mot de passe
            $sqlUpdate = "UPDATE utilisateurs SET password_hash = ? WHERE id_utilisateur = ?";
            $stmtUpdate = $conn->prepare($sqlUpdate);
            $stmtUpdate->execute([$newHash, $userId]);
            
            return [
                'success' => true,
                'message' => 'Mot de passe modifié avec succès'
            ];
        } catch (PDOException $e) {
            error_log("Erreur lors du changement de mot de passe: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Erreur lors du changement de mot de passe'
            ];
        }
    }

    /**
     * Crée un nouvel utilisateur
     * 
     * @param PDO $conn La connexion à la base de données
     * @param array $data Les données de l'utilisateur
     * @return array Résultat de l'opération
     */
    public static function creerUtilisateur($conn, $data) {
        try {
            // Vérifier si le nom d'utilisateur existe déjà
            $checkSql = "SELECT COUNT(*) as nb FROM utilisateurs WHERE username = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([$data['username']]);
            
            if ($checkStmt->fetch(PDO::FETCH_ASSOC)['nb'] > 0) {
                return [
                    'success' => false,
                    'message' => 'Ce nom d\'utilisateur existe déjà'
                ];
            }
            
            // Vérifier si l'email existe déjà (si fourni)
            if (isset($data['email']) && !empty($data['email'])) {
                $checkEmailSql = "SELECT COUNT(*) as nb FROM utilisateurs WHERE email = ?";
                $checkEmailStmt = $conn->prepare($checkEmailSql);
                $checkEmailStmt->execute([$data['email']]);
                
                if ($checkEmailStmt->fetch(PDO::FETCH_ASSOC)['nb'] > 0) {
                    return [
                        'success' => false,
                        'message' => 'Cet email est déjà utilisé'
                    ];
                }
            }
            
            // Hacher le mot de passe
            $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
            
            // Insérer le nouvel utilisateur
            $sql = "INSERT INTO utilisateurs (username, password_hash, nom, prenom, email, role) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $data['username'],
                $passwordHash,
                $data['nom'] ?? null,
                $data['prenom'] ?? null,
                $data['email'] ?? null,
                $data['role'] ?? 'standard'
            ]);
            
            return [
                'success' => true,
                'message' => 'Utilisateur créé avec succès',
                'userId' => $conn->lastInsertId()
            ];
        } catch (PDOException $e) {
            error_log("Erreur lors de la création d'un utilisateur: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la création de l\'utilisateur'
            ];
        }
    }

    /**
     * Vérifie si un email existe et crée un token de réinitialisation
     * 
     * @param PDO $conn La connexion à la base de données
     * @param string $email L'email de l'utilisateur
     * @return array Résultat de l'opération et données de l'utilisateur si trouvé
     */
    public static function creerTokenResetPassword($conn, $email) {
        try {
            // Vérifier si l'email existe dans la base de données
            $query = "SELECT id_utilisateur, username, nom, prenom, email 
                    FROM utilisateurs 
                    WHERE email = :email AND compte_actif = 1
                    LIMIT 1";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Utilisateur non trouvé',
                    'userFound' => false
                ];
            }
            
            // Générer un token unique
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Supprimer les anciens tokens pour cet utilisateur
            $deleteOldTokens = "DELETE FROM password_reset_tokens WHERE user_id = :user_id";
            $deleteStmt = $conn->prepare($deleteOldTokens);
            $deleteStmt->bindParam(':user_id', $user['id_utilisateur']);
            $deleteStmt->execute();
            
            // Enregistrer le token dans la base de données
            $insertQuery = "INSERT INTO password_reset_tokens (user_id, token, expires_at, created_at) 
                            VALUES (:user_id, :token, :expires_at, NOW())";
            $insertStmt = $conn->prepare($insertQuery);
            $insertStmt->bindParam(':user_id', $user['id_utilisateur']);
            $insertStmt->bindParam(':token', $token);
            $insertStmt->bindParam(':expires_at', $expires);
            $insertStmt->execute();
            
            return [
                'success' => true,
                'user' => $user,
                'token' => $token,
                'expires' => $expires,
                'userFound' => true
            ];
            
        } catch (PDOException $e) {
            error_log("Erreur lors de la création du token de réinitialisation: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la création du token de réinitialisation',
                'userFound' => false
            ];
        }
    }

    /**
     * Vérifie si un token de réinitialisation est valide
     * 
     * @param PDO $conn La connexion à la base de données
     * @param string $token Le token à vérifier
     * @return array Résultat de l'opération et ID de l'utilisateur si le token est valide
     */
    public static function verifierTokenResetPassword($conn, $token) {
        try {
            // Vérifier si le token est valide et n'a pas expiré
            $query = "SELECT prt.user_id, u.username 
                    FROM password_reset_tokens prt 
                    JOIN utilisateurs u ON u.id_utilisateur = prt.user_id
                    WHERE prt.token = :token 
                    AND prt.expires_at > NOW() 
                    AND prt.used = 0 
                    LIMIT 1";
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':token', $token);
            $stmt->execute();
            
            $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$tokenData) {
                return [
                    'success' => false,
                    'message' => 'Token invalide ou expiré'
                ];
            }
            
            return [
                'success' => true,
                'userId' => $tokenData['user_id'],
                'username' => $tokenData['username']
            ];
            
        } catch (PDOException $e) {
            error_log("Erreur lors de la vérification du token: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la vérification du token'
            ];
        }
    }

    /**
     * Marque un token comme utilisé après une réinitialisation réussie
     * 
     * @param PDO $conn La connexion à la base de données
     * @param string $token Le token à marquer comme utilisé
     * @return array Résultat de l'opération
     */
    public static function marquerTokenUtilise($conn, $token) {
        try {
            $tokenUpdateQuery = "UPDATE password_reset_tokens SET used = 1, used_at = NOW() WHERE token = :token";
            $tokenUpdateStmt = $conn->prepare($tokenUpdateQuery);
            $tokenUpdateStmt->bindParam(':token', $token);
            $tokenUpdateStmt->execute();
            
            return [
                'success' => true,
                'message' => 'Token marqué comme utilisé'
            ];
            
        } catch (PDOException $e) {
            error_log("Erreur lors du marquage du token: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Erreur lors du marquage du token'
            ];
        }
    }
}
?>