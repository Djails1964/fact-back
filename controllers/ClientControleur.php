<?php

class ClientControleur
{

    // Fonction pour récupérer tous les clients
    public static function listerClients($conn)
    {
        try {
            $sql = "SELECT
                id as id_client,
                titre,
                nom,
                prenom,
                rue,
                numero,
                code_postal,
                localite,
                telephone,
                email,
                est_therapeute
            FROM
                client 
            ORDER BY nom ASC";
            $stmt = $conn->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des clients: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération des clients');
        }
    }

    /**
     * Vérifie si un client a au moins une facture associée
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id_client ID du client à vérifier
     * @return bool TRUE si le client a au moins une facture, FALSE sinon
     * @throws Exception En cas d'erreur
     */
    public static function aUneFacture($conn, $id_client)
    {
        try {
            $sql = "SELECT 1 FROM facture WHERE id_client = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id_client]);

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Erreur lors de la vérification des factures pour le client: " . $e->getMessage());
            throw new Exception('Erreur lors de la vérification des factures');
        }
    }

    /**
     * Récupère les factures associées à un client
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id_client ID du client
     * @return array Les factures associées au client
     * @throws Exception En cas d'erreur
     */
    public static function getFacturesClient($conn, $id_client)
    {
        try {
            $sql = "SELECT f.id_facture, f.numero_facture, f.date_facture, f.montant_total 
                    FROM facture f 
                    WHERE f.id_client = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id_client]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des factures du client: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération des factures');
        }
    }

    // Fonction pour récupérer un client par son ID
    public static function getClientParId($conn, $id_client)
    {
        try {
            $sql = "SELECT
                        id as id_client,
                        titre,
                        nom,
                        prenom,
                        rue,
                        numero,
                        code_postal,
                        localite,
                        telephone,
                        email,
                                est_therapeute
                    FROM
                        client
                    WHERE 
                        id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id_client]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$client) {
                throw new Exception('Client non trouvé');
            }

            return $client;
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération du client: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération du client');
        }
    }

    // Fonction pour ajouter un nouveau client
    public static function ajouterClient($conn, $data)
    {
        error_log("ClientControleur - ajouterClient - Début de l'ajout d'un client");
        error_log("ClientControleur - ajouterClient - Données reçues: " . json_encode($data));

        // DEBUG : Vérifier le type et le contenu de $data
        error_log("ClientControleur - ajouterClient - Type de \$data: " . gettype($data));
        error_log("ClientControleur - ajouterClient - var_dump de \$data: " . var_export($data, true));

        // MÉTHODE ALTERNATIVE : Conversion en tableau associatif pour plus de sécurité
        $dataArray = is_object($data) ? (array)$data : $data;

        // Extraction avec vérification plus robuste
        $titre = isset($dataArray['titre']) ? $dataArray['titre'] : '';
        $nom = isset($dataArray['nom']) ? $dataArray['nom'] : '';
        $prenom = isset($dataArray['prenom']) ? $dataArray['prenom'] : '';
        $rue = isset($dataArray['rue']) ? $dataArray['rue'] : '';
        $numero = isset($dataArray['numero']) ? $dataArray['numero'] : '';

        // CORRECTION SPÉCIALE pour code_postal : traiter les chaînes vides comme NULL
        $code_postal = (isset($dataArray['code_postal']) && $dataArray['code_postal'] !== '') ? $dataArray['code_postal'] : null;

        $localite = isset($dataArray['localite']) ? $dataArray['localite'] : '';
        $telephone = isset($dataArray['telephone']) ? $dataArray['telephone'] : '';
        $email = isset($dataArray['email']) ? $dataArray['email'] : '';
        $est_therapeute = isset($dataArray['est_therapeute']) ? ($dataArray['est_therapeute'] ? 1 : 0) : 0;
        // Validation du code_postal (seulement si pas null)
        error_log("ClientControleur - ajouterClient - Validation du code postal: " . ($code_postal ?? 'NULL'));
        if ($code_postal !== null && (!is_numeric($code_postal) || strlen($code_postal) > 5)) {
            throw new Exception('Code postal invalide');
        }

        try {
            // Préparation de la requête d'insertion
            $sql = "INSERT INTO client (titre, nom, prenom, rue, numero, code_postal, localite, telephone, email, est_therapeute) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);

            error_log("ClientControleur - ajouterClient - Préparation de la requête d'insertion : $sql");
            // Exécution de la requête
            $stmt->execute([$titre, $nom, $prenom, $rue, $numero, $code_postal, $localite, $telephone, $email, $est_therapeute]);

            // Récupérer l'ID du client nouvellement inséré
            $id_client = $conn->lastInsertId();

            return [
                "message" => "Client enregistré avec succès",
                "id_client" => $id_client,
                "status" => "success"
            ];
        } catch (PDOException $e) {
            error_log("Erreur SQL: " . $e->getMessage());
            throw new Exception('Erreur lors de l\'enregistrement du client');
        }
    }

    // Fonction pour mettre à jour un client existant
    public static function modifierClient($conn, $id_client, $data)
    {
        // Vérifier si le client existe
        try {
            $checkSql = "SELECT id as id_client FROM client WHERE id = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([$id_client]);

            if ($checkStmt->rowCount() === 0) {
                throw new Exception('Client non trouvé');
            }

            // CORRECTION : Conversion en tableau associatif pour plus de sécurité
            $dataArray = is_object($data) ? (array)$data : $data;

            // Extraction avec vérification plus robuste
            $titre = isset($dataArray['titre']) ? $dataArray['titre'] : '';
            $nom = isset($dataArray['nom']) ? $dataArray['nom'] : '';
            $prenom = isset($dataArray['prenom']) ? $dataArray['prenom'] : '';
            $rue = isset($dataArray['rue']) ? $dataArray['rue'] : '';
            $numero = isset($dataArray['numero']) ? $dataArray['numero'] : '';

            // CORRECTION SPÉCIALE pour code_postal : traiter les chaînes vides comme NULL
            $code_postal = (isset($dataArray['code_postal']) && $dataArray['code_postal'] !== '') ? $dataArray['code_postal'] : null;

            $localite = isset($dataArray['localite']) ? $dataArray['localite'] : '';
            $telephone = isset($dataArray['telephone']) ? $dataArray['telephone'] : '';
            $email = isset($dataArray['email']) ? $dataArray['email'] : '';
            $est_therapeute = isset($dataArray['est_therapeute']) ? ($dataArray['est_therapeute'] ? 1 : 0) : 0;
            // Validation du code_postal (seulement si pas null)
            if ($code_postal !== null && (!is_numeric($code_postal) || strlen($code_postal) > 5)) {
                throw new Exception('Code postal invalide');
            }

            // Préparation de la requête de mise à jour
            $sql = "UPDATE client 
                    SET titre = ?, nom = ?, prenom = ?, rue = ?, numero = ?, 
                        code_postal = ?, localite = ?, telephone = ?, email = ?, est_therapeute = ? 
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);

            // Exécution de la requête
            $stmt->execute([$titre, $nom, $prenom, $rue, $numero, $code_postal, $localite, $telephone, $email, $est_therapeute, $id_client]);

            return [
                "message" => "Client mis à jour avec succès",
                "id_client" => $id_client,
                "status" => "success"
            ];
        } catch (PDOException $e) {
            error_log("Erreur SQL: " . $e->getMessage());
            throw new Exception('Erreur lors de la mise à jour du client');
        }
    }

    // Fonction pour supprimer un client
    public static function supprimerClient($conn, $id_client)
    {
        try {
            // Vérifier si le client existe
            $checkSql = "SELECT id as id_client FROM client WHERE id = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([$id_client]);

            if ($checkStmt->rowCount() === 0) {
                throw new Exception('Client non trouvé');
            }

            // Préparation de la requête de suppression
            $sql = "DELETE FROM client WHERE id = ?";
            $stmt = $conn->prepare($sql);

            // Exécution de la requête
            $stmt->execute([$id_client]);

            return [
                "message" => "Client supprimé avec succès",
                "id_client" => $id_client,
                "status" => "success"
            ];
        } catch (PDOException $e) {
            error_log("Erreur SQL: " . $e->getMessage());
            throw new Exception('Erreur lors de la suppression du client');
        }
    }
}

// Point d'entrée principal
// Ce bloc sera exécuté uniquement si le fichier est appelé directement et non inclus
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    try {
        // Déterminer l'opération à effectuer en fonction de la méthode HTTP et des paramètres
        $method = $_SERVER['REQUEST_METHOD'];

        switch ($method) {
            case 'GET':
                // Si un ID est fourni, récupérer ce client spécifique
                if (isset($_GET['id_client'])) {
                    $client = ClientControleur::getClientParId($conn, $_GET['id_client']);
                    echo json_encode($client);
                } else {
                    // Sinon, récupérer tous les clients
                    $clients = ClientControleur::listerClients($conn);
                    echo json_encode($clients);
                }
                break;

            case 'POST':
                // Ajouter un nouveau client
                $data = json_decode(file_get_contents("php://input"));
                $resultat = ClientControleur::ajouterClient($conn, $data);
                echo json_encode($resultat);
                break;

            case 'PUT':
                // Mettre à jour un client existant
                $data = json_decode(file_get_contents("php://input"));

                // L'ID peut être fourni dans l'URL ou dans le corps de la requête
                $id_client = $_GET['id_client'] ?? ($data->id_client ?? null);

                if (!$id_client) {
                    throw new Exception('ID client manquant');
                }

                $resultat = ClientControleur::modifierClient($conn, $id_client, $data);
                echo json_encode($resultat);
                break;

            case 'DELETE':
                // Supprimer un client
                if (!isset($_GET['id_client'])) {
                    throw new Exception('ID client manquant');
                }

                $resultat = ClientControleur::supprimerClient($conn, $_GET['id_client']);
                echo json_encode($resultat);
                break;

            default:
                throw new Exception('Méthode HTTP non supportée');
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage(), 'status' => 'error']);
    }
}