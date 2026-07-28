<?php
/**
 * ServiceMotifLocation.php
 * Couche métier pour la gestion des motifs de location.
 *
 * Architecture :
 *   motif-location-api.php → ServiceMotifLocation → MotifLocationControleur (SQL)
 */

require_once realpath(__DIR__ . '/../controllers/MotifLocationControleur.php');
require_once realpath(__DIR__ . '/ActivityLogger.php');

class ServiceMotifLocation
{
    private PDO    $conn;
    private object $logger;

    public function __construct(PDO $conn)
    {
        $this->conn   = $conn;
        $this->logger = new ActivityLogger($conn);
    }

    private function getUser(): array
    {
        return [
            'id'   => $_SESSION['user_id']   ?? null,
            'name' => $_SESSION['user_name'] ?? 'Système',
        ];
    }

    // ── LECTURE ───────────────────────────────────────────────────────────────

    public function listerParTypeContrat(int $idTypeContrat, bool $actifSeulement = true): array
    {
        return MotifLocationControleur::listerParTypeContrat($this->conn, $idTypeContrat, $actifSeulement);
    }

    public function listerTous(bool $actifSeulement = true): array
    {
        return MotifLocationControleur::listerTous($this->conn, $actifSeulement);
    }

    // ── ÉCRITURE ──────────────────────────────────────────────────────────────

    public function creer(array $data): array
    {
        $user = $this->getUser();
        try {
            MotifLocationControleur::valider($data, true);
            $this->conn->beginTransaction();
            $result = MotifLocationControleur::creer($this->conn, $data);
            $this->logger->log([
                'user_id'     => $user['id'],
                'user_name'   => $user['name'],
                'action_type' => 'motif_location_create',
                'entity_type' => 'motif_location',
                'entity_id'   => $result['id'],
                'description' => "Création motif \"{$data['libelle']}\" (type contrat #{$data['id_type_contrat']})",
                'severity'    => 'info',
            ]);
            $this->conn->commit();
            return $result;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("ServiceMotifLocation::creer - " . $e->getMessage());
            throw $e;
        }
    }

    public function modifier(int $id, array $data): array
    {
        $user = $this->getUser();
        try {
            MotifLocationControleur::valider($data, false);
            $this->conn->beginTransaction();
            $avant  = MotifLocationControleur::getById($this->conn, $id);
            $result = MotifLocationControleur::modifier($this->conn, $id, $data);
            if ($result['modifie']) {
                $this->logger->log([
                    'user_id'     => $user['id'],
                    'user_name'   => $user['name'],
                    'action_type' => 'motif_location_update',
                    'entity_type' => 'motif_location',
                    'entity_id'   => $id,
                    'description' => "Modification motif \"{$avant['libelle']}\" → \"{$data['libelle']}\"",
                    'severity'    => 'info',
                ]);
            }
            $this->conn->commit();
            return $result;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("ServiceMotifLocation::modifier - " . $e->getMessage());
            throw $e;
        }
    }

    public function supprimer(int $id): array
    {
        $user = $this->getUser();
        try {
            $motif = MotifLocationControleur::getById($this->conn, $id);
            if (!$motif) throw new Exception("Motif introuvable (id={$id})");
            $this->conn->beginTransaction();
            $result = MotifLocationControleur::supprimer($this->conn, $id);
            $this->logger->log([
                'user_id'     => $user['id'],
                'user_name'   => $user['name'],
                'action_type' => 'motif_location_delete',
                'entity_type' => 'motif_location',
                'entity_id'   => $id,
                'description' => "Suppression motif \"{$motif['libelle']}\"",
                'severity'    => 'warning',
            ]);
            $this->conn->commit();
            return $result;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("ServiceMotifLocation::supprimer - " . $e->getMessage());
            throw $e;
        }
    }
}
?>