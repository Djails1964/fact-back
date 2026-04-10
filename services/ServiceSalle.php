<?php
/**
 * ServiceSalle.php
 * Couche métier pour la gestion des salles de location.
 *
 * Architecture :
 *   salle-api.php → ServiceSalle → SalleControleur (SQL)
 *
 * Responsabilités :
 *   - Transactions PDO (beginTransaction / commit / rollBack)
 *   - Journalisation via ActivityLogger
 *   - Validation métier (règles au-delà du simple typage)
 *   - Isolation : ne contient aucune requête SQL directe
 */

require_once realpath(__DIR__ . '/../controllers/SalleControleur.php');
require_once realpath(__DIR__ . '/ActivityLogger.php');

class ServiceSalle
{
    private PDO    $conn;
    private object $logger;

    public function __construct(PDO $conn)
    {
        $this->conn   = $conn;
        $this->logger = new ActivityLogger($conn);
    }

    // ── Helpers privés ────────────────────────────────────────────────────────

    private function getCurrentUser(): array
    {
        return [
            'id'   => $_SESSION['user_id']   ?? null,
            'name' => $_SESSION['user_name'] ?? 'Système',
        ];
    }

    // ── LECTURE ───────────────────────────────────────────────────────────────

    /**
     * Retourne toutes les salles (actives par défaut).
     * Aucune transaction nécessaire pour une lecture.
     */
    public function lister(bool $actifSeulement = true): array
    {
        try {
            return SalleControleur::lister($this->conn, $actifSeulement);
        } catch (Exception $e) {
            error_log("ServiceSalle::lister - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Retourne une salle par son id.
     */
    public function getById(int $id): ?array
    {
        try {
            return SalleControleur::getById($this->conn, $id);
        } catch (Exception $e) {
            error_log("ServiceSalle::getById({$id}) - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Retourne le type_document d'une salle à partir de l'id_service du loyer.
     * Appelé par salle-api.php?type_document=1&id_service=X
     * Utilisé par LoyerGestion pour router vers facture ou confirmation PDF.
     */
    public function getTypeDocumentByService(int $idService): string
    {
        try {
            return SalleControleur::getTypeDocumentByService($this->conn, $idService);
        } catch (Exception $e) {
            error_log("ServiceSalle::getTypeDocumentByService({$idService}) - " . $e->getMessage());
            return 'facture'; // valeur sûre par défaut — ne pas propager l'erreur
        }
    }

    // ── ÉCRITURE ──────────────────────────────────────────────────────────────

    /**
     * Crée une nouvelle salle.
     * Transaction + log d'activité.
     *
     * @param array $data ['nom', 'id_service'?, 'type_client_requis'?, 'type_document'?]
     * @return array ['success' => true, 'id' => int, 'message' => string]
     */
    public function creer(array $data): array
    {
        $user = $this->getCurrentUser();

        try {
            $this->conn->beginTransaction();

            $resultat = SalleControleur::creer($this->conn, $data);

            $this->logger->log([
                'user_id'     => $user['id'],
                'user_name'   => $user['name'],
                'action_type' => 'salle_create',
                'entity_type' => 'salle',
                'entity_id'   => $resultat['id'],
                'description' => "Création de la salle \"{$data['nom']}\"",
                'details'     => [
                    'nom'               => $data['nom'],
                    'id_service'        => $data['id_service']        ?? null,
                    'type_client_requis'=> $data['type_client_requis'] ?? null,
                    'type_document'     => $data['type_document']      ?? 'facture',
                ],
                'severity'    => 'info',
            ]);

            $this->conn->commit();

            return [
                'success' => true,
                'id'      => $resultat['id'],
                'message' => "Salle \"{$data['nom']}\" créée avec succès",
            ];

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("ServiceSalle::creer - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Modifie une salle existante.
     * Transaction + log d'activité avec snapshot avant/après.
     *
     * @param int   $id
     * @param array $data ['nom', 'id_service'?, 'type_client_requis'?, 'type_document'?, 'actif'?]
     * @return array ['success' => true, 'modifie' => bool, 'message' => string]
     */
    public function modifier(int $id, array $data): array
    {
        $user = $this->getCurrentUser();

        try {
            $this->conn->beginTransaction();

            // Snapshot avant modification pour le log
            $avant = SalleControleur::getById($this->conn, $id);
            if (!$avant) {
                throw new Exception("Salle introuvable (id={$id})");
            }

            $resultat = SalleControleur::modifier($this->conn, $id, $data);

            if ($resultat['modifie']) {
                $this->logger->log([
                    'user_id'     => $user['id'],
                    'user_name'   => $user['name'],
                    'action_type' => 'salle_update',
                    'entity_type' => 'salle',
                    'entity_id'   => $id,
                    'description' => "Modification de la salle \"{$avant['nom']}\"",
                    'details'     => [
                        'avant' => [
                            'nom'               => $avant['nom'],
                            'id_service'        => $avant['id_service'],
                            'type_client_requis'=> $avant['type_client_requis'],
                            'type_document'     => $avant['type_document'],
                            'actif'             => $avant['actif'],
                        ],
                        'apres' => [
                            'nom'               => $data['nom']               ?? $avant['nom'],
                            'id_service'        => $data['id_service']        ?? $avant['id_service'],
                            'type_client_requis'=> $data['type_client_requis'] ?? $avant['type_client_requis'],
                            'type_document'     => $data['type_document']      ?? $avant['type_document'],
                            'actif'             => $data['actif']              ?? $avant['actif'],
                        ],
                    ],
                    'severity' => 'info',
                ]);
            }

            $this->conn->commit();

            return [
                'success' => true,
                'modifie' => $resultat['modifie'],
                'message' => "Salle \"{$data['nom']}\" modifiée avec succès",
            ];

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("ServiceSalle::modifier({$id}) - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Supprime une salle.
     * Transaction + log d'activité avec snapshot avant suppression.
     * Bloqué par SalleControleur si des locations y font référence.
     *
     * @param int $id
     * @return array ['success' => true, 'message' => string]
     */
    public function supprimer(int $id): array
    {
        $user = $this->getCurrentUser();

        try {
            $this->conn->beginTransaction();

            // Snapshot avant suppression pour le log
            $salle = SalleControleur::getById($this->conn, $id);
            if (!$salle) {
                throw new Exception("Salle introuvable (id={$id})");
            }

            $resultat = SalleControleur::supprimer($this->conn, $id);

            $this->logger->log([
                'user_id'     => $user['id'],
                'user_name'   => $user['name'],
                'action_type' => 'salle_delete',
                'entity_type' => 'salle',
                'entity_id'   => $id,
                'description' => "Suppression de la salle \"{$salle['nom']}\"",
                'details'     => [
                    'nom'               => $salle['nom'],
                    'id_service'        => $salle['id_service'],
                    'type_client_requis'=> $salle['type_client_requis'],
                    'type_document'     => $salle['type_document'],
                ],
                'severity' => 'warning',
            ]);

            $this->conn->commit();

            return [
                'success'  => true,
                'supprime' => $resultat['supprime'],
                'message'  => "Salle \"{$salle['nom']}\" supprimée avec succès",
            ];

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("ServiceSalle::supprimer({$id}) - " . $e->getMessage());
            throw $e;
        }
    }
}
?>