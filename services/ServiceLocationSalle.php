<?php
/**
 * ServiceLocationSalle.php
 * Service métier pour la gestion des locations de salle.
 * Architecture maître/détail :
 *   location_salle_contrat  — (id_client, annee)
 *   location_salle_detail   — (id_contrat, mois, salle, id_unite)
 */

require_once realpath(__DIR__ . '/../controllers/LocationSalleControleur.php');
require_once realpath(__DIR__ . '/ActivityLogger.php');

class ServiceLocationSalle
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

    private function nomClientPourLog(int $id_client): string
    {
        try {
            $stmt = $this->conn->prepare(
                "SELECT CONCAT(prenom, ' ', nom) AS nom FROM client WHERE id = ?"
            );
            $stmt->execute([$id_client]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row['nom'] ?? 'Client inconnu';
        } catch (Exception $e) {
            return 'Client inconnu';
        }
    }

    private function labelMois(int $mois): string
    {
        $labels = [
            1=>'Janvier', 2=>'Février', 3=>'Mars', 4=>'Avril',
            5=>'Mai', 6=>'Juin', 7=>'Juillet', 8=>'Août',
            9=>'Septembre', 10=>'Octobre', 11=>'Novembre', 12=>'Décembre',
        ];
        return $labels[$mois] ?? "Mois $mois";
    }

    // ── LECTURE ───────────────────────────────────────────────────────────────

    public function listerContrats(int $annee): array
    {
        try {
            return LocationSalleControleur::listerContrats($this->conn, $annee);
        } catch (Exception $e) {
            error_log("ServiceLocationSalle::listerContrats - " . $e->getMessage());
            throw $e;
        }
    }

    public function listerDetails(int $annee, array $filtres = []): array
    {
        try {
            return LocationSalleControleur::listerDetails($this->conn, $annee, $filtres);
        } catch (Exception $e) {
            error_log("ServiceLocationSalle::listerDetails - " . $e->getMessage());
            throw $e;
        }
    }

    public function getDetailParId(int $id): array
    {
        try {
            return LocationSalleControleur::getDetailParId($this->conn, $id);
        } catch (Exception $e) {
            error_log("ServiceLocationSalle::getDetailParId - " . $e->getMessage());
            throw $e;
        }
    }

    public function getSallesDisponibles(): array
    {
        try {
            return LocationSalleControleur::getSallesDisponibles($this->conn);
        } catch (Exception $e) {
            error_log("ServiceLocationSalle::getSallesDisponibles - " . $e->getMessage());
            throw $e;
        }
    }

    // ── CONTRATS ──────────────────────────────────────────────────────────────

    public function creerContrat(int $id_client, int $annee): array
    {
        $user = $this->getCurrentUser();
        try {
            $this->conn->beginTransaction();

            $resultat = LocationSalleControleur::creerContrat(
                $this->conn, $id_client, $annee, $user['id']
            );

            if ($resultat['created']) {
                $nomClient = $this->nomClientPourLog($id_client);
                $this->logger->log([
                    'user_id'     => $user['id'],
                    'user_name'   => $user['name'],
                    'action_type' => 'location_salle_contrat_create',
                    'entity_type' => 'location_salle_contrat',
                    'entity_id'   => $resultat['id'],
                    'description' => "Nouveau contrat pour {$nomClient} — tableau {$annee}",
                    'details'     => ['id_client' => $id_client, 'client_nom' => $nomClient, 'annee' => $annee],
                    'severity'    => 'info',
                ]);
            }

            $this->conn->commit();
            return $resultat;

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("ServiceLocationSalle::creerContrat - " . $e->getMessage());
            throw $e;
        }
    }

    public function supprimerContrat(int $idContrat): array
    {
        $user = $this->getCurrentUser();
        try {
            $this->conn->beginTransaction();

            // Snapshot avant suppression
            $stmtC = $this->conn->prepare("
                SELECT lsc.id, lsc.id_client, lsc.annee,
                       CONCAT(c.prenom, ' ', c.nom) AS nom_client
                FROM location_salle_contrat lsc
                JOIN client c ON c.id = lsc.id_client
                WHERE lsc.id = ?
            ");
            $stmtC->execute([$idContrat]);
            $contrat = $stmtC->fetch(PDO::FETCH_ASSOC);

            $resultat = LocationSalleControleur::supprimerContrat($this->conn, $idContrat);

            if ($contrat) {
                $this->logger->log([
                    'user_id'     => $user['id'],
                    'user_name'   => $user['name'],
                    'action_type' => 'location_salle_contrat_delete',
                    'entity_type' => 'location_salle_contrat',
                    'entity_id'   => $idContrat,
                    'description' => "Retrait de {$contrat['nom_client']} du tableau des locations {$contrat['annee']}",
                    'details'     => [
                        'id_contrat' => $idContrat,
                        'id_client'  => $contrat['id_client'],
                        'client_nom' => $contrat['nom_client'],
                        'annee'      => $contrat['annee'],
                    ],
                    'severity' => 'warning',
                ]);
            }

            $this->conn->commit();
            return $resultat;

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("ServiceLocationSalle::supprimerContrat - " . $e->getMessage());
            throw $e;
        }
    }

    // ── DÉTAILS ───────────────────────────────────────────────────────────────

    public function creerDetail(array $data): array
    {
        $user = $this->getCurrentUser();
        try {
            $this->conn->beginTransaction();

            // ✅ Utiliser id_contrat fourni par le frontend si présent,
            // sinon fallback : chercher le premier contrat existant (client, annee)
            if (empty($data['id_contrat'])) {
                $stmtFind = $this->conn->prepare(
                    'SELECT id FROM location_salle_contrat
                     WHERE id_client = ? AND annee = ?
                     ORDER BY id ASC LIMIT 1'
                );
                $stmtFind->execute([(int)$data['id_client'], (int)$data['annee']]);
                $existingId = $stmtFind->fetchColumn();

                if ($existingId) {
                    $data['id_contrat'] = (int)$existingId;
                } else {
                    // Aucun contrat — en créer un
                    $contrat = LocationSalleControleur::creerContrat(
                        $this->conn,
                        (int) $data['id_client'],
                        (int) $data['annee'],
                        $user['id']
                    );
                    $data['id_contrat'] = $contrat['id'];
                }
            }

            $data['created_by'] = $user['id'];
            $data['updated_by'] = $user['id'];

            $resultat = LocationSalleControleur::ajouterDetail($this->conn, $data);

            $nomClient = $this->nomClientPourLog((int) $data['id_client']);
            $labelMois = $this->labelMois((int) $data['mois']);

            $this->logger->log([
                'user_id'     => $user['id'],
                'user_name'   => $user['name'],
                'action_type' => 'location_salle_create',
                'entity_type' => 'location_salle_detail',
                'entity_id'   => $resultat['id'],
                'description' => "Création location \"{$data['salle']}\" pour {$nomClient} — {$labelMois} {$data['annee']}",
                'details'     => [
                    'id_client'     => $data['id_client'],
                    'client_nom'    => $nomClient,
                    'annee'         => $data['annee'],
                    'mois'          => $data['mois'],
                    'salle'         => $data['salle'],
                    'quantite'      => $data['quantite'],
                ],
                'severity' => 'info',
            ]);

            $this->conn->commit();
            return $resultat;

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("ServiceLocationSalle::creerDetail - " . $e->getMessage());
            throw $e;
        }
    }

    public function modifierDetail(int $id, array $data): array
    {
        $user = $this->getCurrentUser();
        try {
            $this->conn->beginTransaction();

            $avant = LocationSalleControleur::getDetailParId($this->conn, $id);
            $data['updated_by'] = $user['id'];

            $resultat = LocationSalleControleur::modifierDetail($this->conn, $id, $data);

            $nomClient = $this->nomClientPourLog((int) $avant['id_client']);
            $labelMois = $this->labelMois((int) $data['mois']);

            $this->logger->log([
                'user_id'     => $user['id'],
                'user_name'   => $user['name'],
                'action_type' => 'location_salle_update',
                'entity_type' => 'location_salle_detail',
                'entity_id'   => $id,
                'description' => "Modification location \"{$data['salle']}\" pour {$nomClient} — {$labelMois} {$avant['annee']}",
                'details'     => [
                    'id'         => $id,
                    'client_nom' => $nomClient,
                    'avant'      => ['salle' => $avant['salle'], 'quantite' => $avant['quantite']],
                    'apres'      => ['salle' => $data['salle'],  'quantite' => $data['quantite']],
                ],
                'severity' => 'info',
            ]);

            $this->conn->commit();
            return $resultat;

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("ServiceLocationSalle::modifierDetail - " . $e->getMessage());
            throw $e;
        }
    }

    public function supprimerDetail(int $id): array
    {
        $user = $this->getCurrentUser();
        try {
            $this->conn->beginTransaction();

            $detail    = LocationSalleControleur::getDetailParId($this->conn, $id);
            $nomClient = $this->nomClientPourLog((int) $detail['id_client']);
            $labelMois = $this->labelMois((int) $detail['mois']);

            $resultat = LocationSalleControleur::supprimerDetail($this->conn, $id);

            $this->logger->log([
                'user_id'     => $user['id'],
                'user_name'   => $user['name'],
                'action_type' => 'location_salle_delete',
                'entity_type' => 'location_salle_detail',
                'entity_id'   => $id,
                'description' => "Suppression location \"{$detail['salle']}\" pour {$nomClient} — {$labelMois} {$detail['annee']}",
                'details'     => [
                    'id'            => $id,
                    'id_client'     => $detail['id_client'],
                    'client_nom'    => $nomClient,
                    'annee'         => $detail['annee'],
                    'mois'          => $detail['mois'],
                    'salle'         => $detail['salle'],
                    'quantite'      => $detail['quantite'],
                ],
                'severity' => 'warning',
            ]);

            $this->conn->commit();
            return $resultat;

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("ServiceLocationSalle::supprimerDetail - " . $e->getMessage());
            throw $e;
        }
    }
}