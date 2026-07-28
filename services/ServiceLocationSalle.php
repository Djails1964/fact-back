<?php
/**
 * ServiceLocationSalle.php
 * Service métier pour la gestion des locations de salle.
 * Architecture maître/détail :
 *   location_salle_contrat  — (id_client, annee)
 *   location_salle_detail   — (id_contrat, mois, salle, id_unite)
 */

require_once realpath(__DIR__ . '/../controllers/LocationSalleControleur.php');
require_once realpath(__DIR__ . '/../controllers/SalleControleur.php');
require_once realpath(__DIR__ . '/../controllers/FactureControleur.php');
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

    /**
     * Vérifie que la facture éventuellement liée directement à ce contrat
     * de location (facture.id_contrat_location) peut encore être considérée
     * comme liée à une location modifiable : son état doit permettre la
     * modification. Le vocabulaire d'état diffère selon le type de contrat :
     *   - À l'utilisation (facture standard) : En attente / Éditée
     *   - Au forfait (confirmation de paiement) : Non payé uniquement
     * Sinon, un paiement ou un envoi a déjà eu lieu et la location source
     * ne doit plus pouvoir changer.
     * Lève une Exception si la location ne doit pas être modifiable.
     *
     * @param int $idContrat
     * @return void
     */
    private function verifierFactureModifiable(int $idContrat): void
    {
        $stmt = $this->conn->prepare(
            "SELECT f.id_facture, f.numero_facture, f.etat, tcl.est_forfait
             FROM facture f
             JOIN location_salle_contrat lsc ON lsc.id = f.id_contrat_location
             LEFT JOIN type_contrat_location tcl ON tcl.id = lsc.id_type_contrat
             WHERE f.id_contrat_location = ?"
        );
        $stmt->execute([$idContrat]);
        $facture = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$facture) {
            return; // Pas de facture générée pour ce contrat : rien à vérifier
        }

        $etatsAutorises = !empty($facture['est_forfait'])
            ? ['Non payé']                 // Confirmation de paiement
            : ['En attente', 'Éditée'];    // Facture standard

        if (!in_array($facture['etat'], $etatsAutorises, true)) {
            throw new Exception(
                "Impossible de modifier cette location : la facture {$facture['numero_facture']} " .
                "est en état \"{$facture['etat']}\"."
            );
        }
    }

    // ── LECTURE ───────────────────────────────────────────────────────────────

    public function listerTypesContrat(): array
    {
        try {
            $stmt = $this->conn->prepare(
                "SELECT id, nom, est_forfait, type_client_requis, categorie_motifs
                 FROM type_contrat_location
                 WHERE actif = 1
                 ORDER BY id ASC"
            );
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("ServiceLocationSalle::listerTypesContrat - " . $e->getMessage());
            throw $e;
        }
    }

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

    public function creerContrat(int $id_client, int $annee, ?int $id_salle = null, ?int $id_type_contrat = null): array
    {
        $user = $this->getCurrentUser();
        try {
            $this->conn->beginTransaction();

            $resultat = LocationSalleControleur::creerContrat(
                $this->conn, $id_client, $annee, $user['id'], $id_salle, $id_type_contrat
            );

            if ($resultat['created']) {
                $nomClient = $this->nomClientPourLog($id_client);
                $nomSalle  = $id_salle ? SalleControleur::getNomSalle($this->conn, $id_salle) : null;

                $nomType = null;
                if ($id_type_contrat) {
                    $stmtT = $this->conn->prepare("SELECT nom FROM type_contrat_location WHERE id = ?");
                    $stmtT->execute([$id_type_contrat]);
                    $nomType = $stmtT->fetchColumn() ?: null;
                }

                $desc = "Nouveau contrat pour {$nomClient}";
                if ($nomSalle) $desc .= " — {$nomSalle}";
                if ($nomType)  $desc .= " ({$nomType})";
                $desc .= " {$annee}";

                $this->logger->log([
                    'user_id'     => $user['id'],
                    'user_name'   => $user['name'],
                    'action_type' => 'location_salle_contrat_create',
                    'entity_type' => 'location_salle_contrat',
                    'entity_id'   => $resultat['id'],
                    'description' => $desc,
                    'details'     => [
                        'id_client'       => $id_client,
                        'client_nom'      => $nomClient,
                        'annee'           => $annee,
                        'id_salle'        => $id_salle,
                        'salle_nom'       => $nomSalle,
                        'id_type_contrat' => $id_type_contrat,
                        'type_nom'        => $nomType,
                    ],
                    'severity' => 'info',
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

            // ✅ Si une facture est liée directement à ce contrat, la traiter
            // selon son état avant de supprimer le contrat lui-même : même
            // logique que l'ancien LoyerControleur::supprimerLoyer, mais
            // appliquée directement sur facture.id_contrat_location. Une
            // exception ici fait échouer/rollback toute la transaction, donc
            // la location n'est PAS supprimée tant que la facture liée ne
            // peut pas être traitée. Le vocabulaire d'état diffère selon le
            // type de contrat (voir verifierFactureModifiable).
            $stmtFacture = $this->conn->prepare(
                "SELECT f.id_facture, f.numero_facture, f.etat, tcl.est_forfait
                 FROM facture f
                 JOIN location_salle_contrat lsc ON lsc.id = f.id_contrat_location
                 LEFT JOIN type_contrat_location tcl ON tcl.id = lsc.id_type_contrat
                 WHERE f.id_contrat_location = ?"
            );
            $stmtFacture->execute([$idContrat]);
            $factureLiee = $stmtFacture->fetch(PDO::FETCH_ASSOC);

            if ($factureLiee) {
                $idFacture   = (int) $factureLiee['id_facture'];
                $etatFacture = $factureLiee['etat'] ?? '';
                $estForfait  = !empty($factureLiee['est_forfait']);

                if ($estForfait) {
                    // Confirmation de paiement : seul 'Non payé' permet la
                    // suppression directe (pas d'état intermédiaire à annuler).
                    if ($etatFacture === 'Non payé') {
                        FactureControleur::supprimerFacture($this->conn, $idFacture);
                        $descLog = "Suppression automatique de la confirmation {$factureLiee['numero_facture']} suite au retrait de la location";
                    } else {
                        throw new Exception(
                            "Ce contrat est lié à une confirmation de paiement en état \"$etatFacture\". " .
                            "La suppression n'est autorisée que si elle est \"Non payé\"."
                        );
                    }
                } else {
                    if ($etatFacture === 'En attente') {
                        FactureControleur::supprimerFacture($this->conn, $idFacture);
                        $descLog = "Suppression automatique de la facture {$factureLiee['numero_facture']} suite au retrait de la location";
                    } elseif ($etatFacture === 'Éditée') {
                        $this->conn->prepare(
                            "UPDATE facture SET etat = 'Annulée', date_annulation = CURDATE() WHERE id_facture = ?"
                        )->execute([$idFacture]);
                        $descLog = "Annulation automatique de la facture {$factureLiee['numero_facture']} suite au retrait de la location";
                    } else {
                        throw new Exception(
                            "Ce contrat est lié à une facture en état \"$etatFacture\". " .
                            "La suppression n'est autorisée que si la facture est \"En attente\" ou \"Éditée\"."
                        );
                    }
                }

                $this->logger->log([
                    'user_id'     => $user['id'],
                    'user_name'   => $user['name'],
                    'action_type' => 'facture_delete',
                    'entity_type' => 'facture',
                    'entity_id'   => $idFacture,
                    'description' => $descLog,
                    'details'     => [
                        'id_facture' => $idFacture,
                        'id_contrat' => $idContrat,
                    ],
                    'severity' => 'warning',
                ]);
            }

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
            // sinon chercher par (client, annee, id_salle) ou créer
            if (empty($data['id_contrat'])) {
                $idSalle        = isset($data['id_salle']) && $data['id_salle'] !== '' ? (int)$data['id_salle'] : null;
                $idTypeContrat  = isset($data['id_type_contrat']) && $data['id_type_contrat'] !== '' ? (int)$data['id_type_contrat'] : null;

                $stmtFind = $this->conn->prepare(
                    'SELECT id FROM location_salle_contrat
                     WHERE id_client = ? AND annee = ? AND id_salle <=> ?
                     ORDER BY id ASC LIMIT 1'
                );
                $stmtFind->execute([(int)$data['id_client'], (int)$data['annee'], $idSalle]);
                $existingId = $stmtFind->fetchColumn();

                if ($existingId) {
                    $data['id_contrat'] = (int)$existingId;
                } else {
                    $contrat = LocationSalleControleur::creerContrat(
                        $this->conn,
                        (int) $data['id_client'],
                        (int) $data['annee'],
                        $user['id'],
                        $idSalle,
                        $idTypeContrat
                    );
                    $data['id_contrat'] = $contrat['id'];
                }
            }

            // ✅ Bloquer si la facture liée à ce contrat n'est plus modifiable
            // (état autre que En attente/Éditée).
            $this->verifierFactureModifiable((int) $data['id_contrat']);

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

            // ✅ Bloquer si la facture liée au contrat de cette location
            // n'est plus modifiable (état autre que En attente/Éditée).
            $this->verifierFactureModifiable((int) $avant['id_contrat']);

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

            // ✅ Bloquer si la facture liée au contrat de cette location
            // n'est plus modifiable (état autre que En attente/Éditée).
            $this->verifierFactureModifiable((int) $detail['id_contrat']);

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