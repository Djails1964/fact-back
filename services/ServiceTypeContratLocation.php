<?php
/**
 * ServiceTypeContratLocation.php
 * Couche métier pour la gestion des types de contrat de location.
 *
 * Architecture :
 *   type-contrat-location-api.php → ServiceTypeContratLocation → TypeContratLocationControleur (SQL)
 */

require_once realpath(__DIR__ . '/../controllers/TypeContratLocationControleur.php');
require_once realpath(__DIR__ . '/ActivityLogger.php');

class ServiceTypeContratLocation
{
    private PDO    $conn;
    private object $logger;

    public function __construct(PDO $conn)
    {
        $this->conn   = $conn;
        $this->logger = new ActivityLogger($conn);
    }

    private function getCurrentUser(): array
    {
        return [
            'id'   => $_SESSION['user_id']   ?? null,
            'name' => $_SESSION['user_name'] ?? 'Système',
        ];
    }

    // ── LECTURE ───────────────────────────────────────────────────────────────

    public function lister(bool $actifSeulement = false): array
    {
        try {
            return TypeContratLocationControleur::lister($this->conn, $actifSeulement);
        } catch (Exception $e) {
            error_log("ServiceTypeContratLocation::lister - " . $e->getMessage());
            throw $e;
        }
    }

    // ── ÉCRITURE ──────────────────────────────────────────────────────────────

    /**
     * Modifie les attributs éditables d'un type de contrat.
     *
     * @param int   $id
     * @param array $data ['type_document', 'type_client_requis'?, 'categorie_motifs'?, 'actif'?]
     * @return array ['success' => true, 'modifie' => bool, 'message' => string]
     */
    public function modifier(int $id, array $data): array
    {
        $user = $this->getCurrentUser();

        try {
            $this->conn->beginTransaction();

            $avant = TypeContratLocationControleur::getById($this->conn, $id);
            if (!$avant) {
                throw new Exception("Type de contrat introuvable (id={$id})");
            }

            $resultat = TypeContratLocationControleur::modifier($this->conn, $id, $data);

            if ($resultat['modifie']) {
                $this->logger->log([
                    'user_id'     => $user['id'],
                    'user_name'   => $user['name'],
                    'action_type' => 'type_contrat_location_update',
                    'entity_type' => 'type_contrat_location',
                    'entity_id'   => $id,
                    'description' => "Modification du type de contrat \"{$avant['nom']}\"",
                    'details'     => [
                        'avant' => [
                            'est_forfait'        => $avant['est_forfait'],
                            'type_client_requis' => $avant['type_client_requis'],
                            'categorie_motifs'   => $avant['categorie_motifs'],
                            'actif'              => $avant['actif'],
                        ],
                        'apres' => [
                            'est_forfait'        => $data['est_forfait']        ?? $avant['est_forfait'],
                            'type_client_requis' => $data['type_client_requis'] ?? $avant['type_client_requis'],
                            'categorie_motifs'   => $data['categorie_motifs']   ?? $avant['categorie_motifs'],
                            'actif'              => $data['actif']              ?? $avant['actif'],
                        ],
                    ],
                    'severity' => 'info',
                ]);
            }

            $this->conn->commit();

            return [
                'success' => true,
                'modifie' => $resultat['modifie'],
                'message' => "Type \"{$avant['nom']}\" modifié avec succès",
            ];

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("ServiceTypeContratLocation::modifier({$id}) - " . $e->getMessage());
            throw $e;
        }
    }
}
?>