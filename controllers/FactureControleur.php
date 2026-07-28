<?php
/**
 * FactureControleur.php
 * 
 * Contrôleur pour la gestion des factures du Centre La Grange
 * Fonctionnalités : listing, création, modification, suppression, impression, etc.
 */

class FactureControleur {
    // ==================================================================================
    // MÉTHODES PRINCIPALES CRUD
    // ==================================================================================
    
    /**
     * Récupère toutes les factures avec filtrage par année optionnel
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int|null $annee Année pour filtrer les factures (optionnel)
     * @return array Liste des factures
     * @throws Exception En cas d'erreur lors de la récupération
     */
    public static function listerFactures($conn, $annee = null) {
        try {
            // ✅ f.id_contrat_location (colonne directe) indique si cette
            // facture a été générée depuis une location de salle — plus besoin
            // de sous-requête via loyer (supprimé, migration 042). est_forfait
            // (via le type de contrat de location) est la source de vérité
            // pour distinguer facture standard / confirmation de paiement —
            // pas la présence de lignes ou de détail mensuel.
            // ✅ est_imprimee : calculé à partir de date_edition (pas de colonne
            // dédiée) — vrai dès que le PDF a été généré au moins une fois.
            // Utilisé par le workflow des confirmations (FactureActions.jsx) :
            // impression → email, distinct du workflow facture standard.
            $sql = "SELECT f.*, c.nom, c.prenom, c.email, tcl.est_forfait,
                           (f.date_edition IS NOT NULL) AS est_imprimee
                    FROM facture f 
                    JOIN client c ON f.id_client = c.id
                    LEFT JOIN location_salle_contrat lsc ON lsc.id = f.id_contrat_location
                    LEFT JOIN type_contrat_location tcl ON tcl.id = lsc.id_type_contrat";
            
            $params = [];
            
            // Si une année est spécifiée, ajouter le filtre d'année
            if ($annee !== null) {
                $sql .= " WHERE YEAR(f.date_facture) = ?";
                $params[] = $annee;
            }
            
            $sql .= " ORDER BY f.date_facture DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des factures: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération des factures');
        }
    }

    /**
     * Récupère une facture et ses lignes par son ID
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id_facture ID de la facture à récupérer
     * @return array Données de la facture et ses lignes
     * @throws Exception Si la facture n'existe pas ou autre erreur
     */
    public static function getFactureParId($conn, $id_facture) {
        try {
            // Récupérer les informations de la facture
            // ✅ f.id_contrat_location (colonne directe) indique si cette
            // facture a été générée depuis une location de salle. est_forfait
            // (via le type de contrat de location) est la source de vérité
            // pour distinguer facture standard / confirmation de paiement —
            // pas la présence de lignes ou de détail mensuel côté frontend.
            $sql = "SELECT f.*, c.nom, c.prenom, c.titre, c.rue, c.numero, c.code_postal, c.localite, c.telephone, c.email, c.est_therapeute, tcl.est_forfait,
                           (f.date_edition IS NOT NULL) AS est_imprimee
                    FROM facture f 
                    JOIN client c ON f.id_client = c.id 
                    LEFT JOIN location_salle_contrat lsc ON lsc.id = f.id_contrat_location
                    LEFT JOIN type_contrat_location tcl ON tcl.id = lsc.id_type_contrat
                    WHERE f.id_facture = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id_facture]);
            $facture = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$facture) {
                throw new Exception('Facture non trouvée');
            }
            
            // Récupérer les lignes de la facture
            $sqlLignes = "SELECT 
                lf.id_ligne,
                lf.id_facture,
                lf.no_ordre,
                lf.description,
                lf.description_dates,
                lf.unite,
                lf.quantite,
                lf.prix_unitaire,
                lf.total_ligne,
                lf.service_id AS id_service,
                lf.unite_id AS id_unite,
                lf.duree,
                lf.nb_seances,
                u.permet_multiplicateur
            FROM lignesfacture lf
            LEFT JOIN unites u ON u.id = lf.unite_id
            WHERE lf.id_facture = ? 
            ORDER BY lf.no_ordre ASC";
            $stmtLignes = $conn->prepare($sqlLignes);
            $stmtLignes->execute([$id_facture]);
            $lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);
            
            // ✅ Détails mensuels (confirmations de paiement — contrats au
            // forfait). Une facture n'a JAMAIS les deux à la fois : soit des
            // lignesfacture (facture standard), soit un détail mensuel
            // (confirmation) — voir facture_detail_mensuel.
            $sqlDetailsMensuels = "SELECT
                fdm.id_detail,
                fdm.id_facture,
                fdm.mois,
                fdm.annee,
                fdm.id_unite,
                fdm.id_service,
                fdm.quantite,
                fdm.description,
                fdm.montant,
                fdm.dates,
                fdm.duree,
                fdm.nb_seances,
                fdm.est_paye,
                fdm.date_paiement,
                u.abreviation as abreviation_unite,
                u.nom as nom_unite,
                u.permet_multiplicateur
            FROM facture_detail_mensuel fdm
            LEFT JOIN unites u ON u.id = fdm.id_unite
            WHERE fdm.id_facture = ?
            ORDER BY fdm.mois ASC";
            $stmtDetailsMensuels = $conn->prepare($sqlDetailsMensuels);
            $stmtDetailsMensuels->execute([$id_facture]);
            $detailsMensuels = $stmtDetailsMensuels->fetchAll(PDO::FETCH_ASSOC);

            // Combiner les résultats
            $facture['lignes'] = $lignes;
            $facture['details_mensuels'] = $detailsMensuels;
            
            return $facture;
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération de la facture: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération de la facture');
        }
    }

    /**
     * Récupère toutes les factures d'un client spécifique
     * ✅ SQL simplifié : toutes les colonnes de la table facture + infos client de base
     * ❌ SANS calculs, SANS GROUP BY, SANS jointure paiement
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id_client ID du client
     * @param bool $inclureAnnulees Inclure les factures annulées (false par défaut)
     * @return array Liste des factures du client
     * @throws Exception En cas d'erreur lors de la récupération
     */
    public static function getFacturesClient($conn, $id_client, $inclureAnnulees = false) {
        try {
            error_log("📥 FactureControleur::getFacturesClient - Client #$id_client" . 
                      ($inclureAnnulees ? " (avec annulées)" : " (sans annulées)"));
            
            // SQL simplifié : toutes les colonnes de facture + infos client de base
            // SANS calculs, SANS GROUP BY, SANS jointure avec paiement
            $sql = "SELECT 
                    f.*,
                    c.id as id_client,
                    c.nom,
                    c.prenom
                FROM facture f
                INNER JOIN client c ON f.id_client = c.id
                WHERE f.id_client = ?";
            
            // Exclure les factures annulées par défaut
            if (!$inclureAnnulees) {
                $sql .= " AND f.etat != 'Annulée'";
            }
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id_client]);
            $factures = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("✅ FactureControleur::getFacturesClient - " . count($factures) . " factures trouvées");
            
            // ✅ Retourner les données en snake_case (seront converties en camelCase par api.js)
            return $factures;
            
        } catch (PDOException $e) {
            error_log("❌ FactureControleur::getFacturesClient - Erreur: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération des factures du client');
        }
    }
    
    /**
     * Alloue et incrémente atomiquement le prochain numéro de facture.
     *
     * ⚠️  Doit être appelé DANS une transaction ouverte par ServiceFacture.
     *     Le SELECT ... FOR UPDATE garantit qu'aucun processus concurrent
     *     ne peut lire le même numéro avant que la transaction soit commitée.
     *
     * @param  PDO $conn   Connexion PDO (transaction déjà ouverte)
     * @param  int $annee  Année déduite de date_facture
     * @return string      Numéro formaté, ex: "087.2026"
     * @throws Exception   Si le paramètre n'existe pas pour cette année
     */
    /**
     * Alloue atomiquement le prochain numéro pour une année donnée.
     * ✅ Deux séquences indépendantes, mêmes groupe/sous-groupe_parametre,
     * distinguées par nom_parametre : 'Prochain Numéro Facture' pour les
     * factures standard, 'Prochain Numéro Confirmation' pour les
     * confirmations de paiement (contrat au forfait).
     *
     * @param PDO  $conn
     * @param int  $annee
     * @param bool $estConfirmation
     * @return string
     */
    public static function allouerNumeroFacture($conn, $annee, $estConfirmation = false) {
        $nomParametre = $estConfirmation ? 'Prochain Numéro Confirmation' : 'Prochain Numéro Facture';

        // 1. Lire ET verrouiller la ligne pour éviter la concurrence
        $stmt = $conn->prepare("
            SELECT id, valeur_parametre
            FROM   parametres
            WHERE  nom_parametre          = ?
              AND  groupe_parametre       = 'Facture'
              AND  sous_groupe_parametre  = 'Numéro'
              AND  annee_parametre        = ?
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([$nomParametre, $annee]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new Exception(
                "Paramètre '{$nomParametre}' introuvable pour l'année {$annee}. " .
                "Configurez-le dans les paramètres avant de générer des " .
                ($estConfirmation ? "confirmations." : "factures.")
            );
        }

        $sequence = (int) $row['valeur_parametre'];
        $prefixe  = $estConfirmation ? 'C-' : 'F-';
        $numero   = $prefixe . str_pad($sequence, 3, '0', STR_PAD_LEFT) . '.' . $annee;

        // 2. Incrémenter immédiatement dans la même transaction
        $upd = $conn->prepare("UPDATE parametres SET valeur_parametre = ? WHERE id = ?");
        $upd->execute([$sequence + 1, $row['id']]);

        if (is_dev_mode()) {
            error_log("FactureControleur::allouerNumeroFacture [{$nomParametre}] - Numéro alloué: {$numero} (prochain: " . ($sequence + 1) . ")");
        }

        return $numero;
    }

    /**
     * Ajoute une nouvelle facture et ses lignes.
     * Le numero_facture est alloué en amont par ServiceFacture via allouerNumeroFacture().
     *
     * @param PDO   $conn La connexion à la base de données
     * @param array $data Les données de la facture (numero_facture déjà présent)
     * @return array Résultat de l'opération
     * @throws Exception En cas de données invalides ou d'erreur
     */
    public static function ajouterFacture($conn, $data) {
        if (is_dev_mode()) {
            error_log("Facturecontroleur - ajouterFacture - Démarrage de l'ajout de facture");
            error_log("Facturecontroleur - ajouterFacture - Données: " . print_r($data, true));
        }

        // numero_facture est injecté par ServiceFacture::creerFacture via allouerNumeroFacture()
        if (!isset($data['numero_facture']) || !isset($data['date_facture']) ||
            !isset($data['id_client']) ||
            !isset($data['lignes']) || !is_array($data['lignes'])) {
            throw new Exception('Données de facture incomplètes ou invalides');
        }

        try {
            $ristourne    = isset($data['ristourne']) ? floatval($data['ristourne']) : 0;
            $montantTotal = self::calculerMontantTotal($data['lignes'], $ristourne);
            $montantBrut  = $montantTotal + $ristourne;

            // ✅ id_contrat_location : non NULL si cette facture est générée
            // directement depuis une location de salle (remplace l'ancien
            // rattachement via loyer, supprimé en migration 042).
            $idContratLocation = isset($data['id_contrat_location']) && $data['id_contrat_location'] !== ''
                ? (int) $data['id_contrat_location'] : null;

            // ✅ date_edition reste NULL à la création — elle ne doit être
            // renseignée qu'au moment où le PDF est réellement généré (voir
            // FactureControleur::mettreAJourEditionFacture(), appelée depuis
            // ServiceFacture::imprimerFacture()). La renseigner ici la
            // faisait apparaître comme "déjà imprimée" (est_imprimee) dès la
            // création, avant toute impression réelle.
            $stmt = $conn->prepare("INSERT INTO facture
                (numero_facture, date_facture, montant_total, montant_brut, id_client, ristourne, motif, id_contrat_location)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['numero_facture'],
                $data['date_facture'],
                $montantTotal,
                $montantBrut,
                $data['id_client'],
                $ristourne,
                $data['motif'] ?? null,
                $idContratLocation,
            ]);

            $id_facture = $conn->lastInsertId();

            $stmtLignes = $conn->prepare("INSERT INTO lignesfacture
                (id_facture, description, quantite, prix_unitaire, total_ligne, service_id, unite_id, no_ordre, description_dates, duree, nb_seances)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            foreach ($data['lignes'] as $index => $ligne) {
                if (!isset($ligne['description'])  ||
                    !isset($ligne['quantite'])      || !isset($ligne['prix_unitaire']) ||
                    !isset($ligne['total_ligne'])   ||
                    !isset($ligne['id_service'])    || !isset($ligne['id_unite'])) {
                    throw new Exception('Données de ligne de facture incomplètes');
                }
                $noOrdre = $ligne['no_ordre'] ?? $index + 1;
                $stmtLignes->execute([
                    $id_facture,
                    $ligne['description'],
                    $ligne['quantite'],
                    $ligne['prix_unitaire'],
                    $ligne['total_ligne'],
                    $ligne['id_service'],
                    $ligne['id_unite'],
                    $noOrdre,
                    $ligne['description_dates'] ?? null,
                    $ligne['duree'] ?? null,
                    isset($ligne['nb_seances']) ? (int)$ligne['nb_seances'] : null,
                ]);
            }

            return [
                'success'        => true,
                'message'        => 'Facture créée avec succès',
                'id_facture'     => $id_facture,
                'numero_facture' => $data['numero_facture'],
            ];

        } catch (PDOException $e) {
            error_log("Erreur SQL: " . $e->getMessage());
            throw new Exception('Erreur lors de l\'enregistrement de la facture: ' . $e->getMessage());
        }
    } // fin ajouterFacture

    /**
     * Modifie une facture existante
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id_facture ID de la facture à modifier
     * @param array $data Les données de la facture
     * @return array Résultat de l'opération
     * @throws Exception En cas de données invalides ou d'erreur
     */
    public static function modifierFacture($conn, $id_facture, $data) {
        // ✅ DEBUGGING amélioré - formatage sécurisé
        error_log("=== DEBUGGING FACTURE CONTROLEUR ===");
        error_log("Raw data type: " . gettype($data));
        error_log("Raw data content: " . print_r($data, true));
        
        // ✅ CORRECTION: Vérification de la structure des données
        if (!is_array($data)) {
            throw new Exception('Données de facture invalides - tableau attendu');
        }

        // ✅ Modification restreinte (date_facture + ristourne uniquement) —
        // utilisée pour les factures liées à un loyer : seules ces deux
        // informations propres à la facture restent modifiables manuellement
        // (le client et les lignes proviennent du loyer/de la location).
        if (!empty($data['modification_limitee'])) {
            return self::modifierAttributsLimites($conn, $id_facture, $data);
        }

        // ✅ CORRECTION: Gestion sécurisée des différents formats de id_client
        $id_client = null;
        if (isset($data['id_client'])) {
            $id_client = is_array($data['id_client']) ? $data['id_client']['id'] ?? $data['id_client'][0] : $data['id_client'];
        } elseif (isset($data['id_client'])) {
            $id_client = is_array($data['id_client']) ? $data['id_client']['id'] ?? $data['id_client'][0] : $data['id_client'];
        } elseif (isset($data['client'])) {
            $id_client = is_array($data['client']) ? $data['client']['id'] ?? $data['client']['id_client'] : $data['client'];
        }
        
        error_log("Client ID résolu: " . var_export($id_client, true));
        error_log("=== FIN DEBUGGING ===");
        
        // Validation des données avec messages d'erreur spécifiques
        $missingFields = [];
        
        if (!isset($data['numero_facture']) || empty($data['numero_facture'])) {
            $missingFields[] = 'numero_facture';
        }
        if (!isset($data['date_facture']) || empty($data['date_facture'])) {
            $missingFields[] = 'date_facture';
        }
        if (!$id_client) {
            $missingFields[] = 'id_client (id_client, id_client, ou client)';
        }
        if (!isset($data['lignes']) || !is_array($data['lignes'])) {
            $missingFields[] = 'lignes (doit être un tableau)';
        }
        
        if (!empty($missingFields)) {
            throw new Exception('Données de facture incomplètes ou invalides. Champs manquants: ' . implode(', ', $missingFields));
        }

        try {
            // Vérifier si la facture existe
            $checkSql = "SELECT id_facture, numero_facture as oldNumero FROM facture WHERE id_facture = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([$id_facture]);
            
            if ($checkStmt->rowCount() === 0) {
                throw new Exception('Facture non trouvée');
            }

            // ✅ Bloquer la modification directe si la facture est liée à une
            // location de salle (facture.id_contrat_location) — SAUF si
            // l'appel vient explicitement de la régénération depuis cette même
            // location, auquel cas c'est légitime.
            $stmtLien = $conn->prepare("SELECT id_contrat_location, etat FROM facture WHERE id_facture = ?");
            $stmtLien->execute([$id_facture]);
            $lienFacture = $stmtLien->fetch(PDO::FETCH_ASSOC);
            $estLieeAUneLocation = $lienFacture && $lienFacture['id_contrat_location'] !== null;

            if ($estLieeAUneLocation && empty($data['regenere_depuis_location'])) {
                throw new Exception(
                    'Cette facture est liée à une location de salle et ne peut pas être modifiée directement. ' .
                    'Modifiez la location pour mettre à jour la facture.'
                );
            } elseif ($estLieeAUneLocation) {
                // ✅ Même en régénération depuis la location, ne jamais écraser les
                // lignes d'une facture déjà payée (partiellement ou totalement).
                $etatActuel = $lienFacture['etat'];
                if (!in_array($etatActuel, ['En attente', 'Éditée'], true)) {
                    throw new Exception(
                        "Cette facture est en état \"$etatActuel\" et ne peut plus être régénérée depuis la location. " .
                        "Seules les factures \"En attente\" ou \"Éditée\" peuvent être mises à jour ainsi."
                    );
                }
            }

            // Calculer le montant total avec la méthode commune
            $ristourne = isset($data['ristourne']) ? floatval($data['ristourne']) : 0;
            $montantTotal = self::calculerMontantTotal($data['lignes'], $ristourne);
            
            // Calculer le montant brut (montant_total + ristourne)
            $montantBrut = $montantTotal + $ristourne;
            
            // Mettre à jour la facture - ✅ CORRECTION: Utilisation de id_client résolu
            $stmt = $conn->prepare("UPDATE facture 
                SET numero_facture = ?, 
                    date_facture = ?, 
                    montant_total = ?,
                    montant_brut = ?,
                    id_client = ?, 
                    ristourne = ?
                WHERE id_facture = ?");
            
            $stmt->execute([
                $data['numero_facture'],
                $data['date_facture'],
                $montantTotal,
                $montantBrut,
                $id_client, // ✅ CORRECTION: Utilisation de la variable résolue
                $ristourne,
                $id_facture
            ]);
            
            // Supprimer les anciennes lignes de facture
            $stmtDelete = $conn->prepare("DELETE FROM lignesfacture WHERE id_facture = ?");
            $stmtDelete->execute([$id_facture]);
            
            // Insérer les nouvelles lignes de facture
            $stmtLignes = $conn->prepare("INSERT INTO lignesfacture 
                (id_facture, description, unite, quantite, prix_unitaire, total_ligne, service_id, unite_id, no_ordre, description_dates, duree, nb_seances) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($data['lignes'] as $index => $ligne) {
                // ✅ CORRECTION: Validation plus robuste des lignes
                $requiredFields = ['description', 'unite', 'quantite', 'prix_unitaire', 'total_ligne', 'id_service', 'id_unite'];
                $missingLineFields = [];
                
                foreach ($requiredFields as $field) {
                    if (!isset($ligne[$field])) {
                        $missingLineFields[] = $field;
                    }
                }
                
                if (!empty($missingLineFields)) {
                    throw new Exception("Ligne $index: champs manquants - " . implode(', ', $missingLineFields));
                }
                
                // ✅ CORRECTION: Gestion sécurisée des valeurs de ligne
                $noOrdre = isset($ligne['noOrdre']) ? intval($ligne['noOrdre']) : $index + 1;
                $descriptionDates = isset($ligne['description_dates']) ? $ligne['description_dates'] : null;
                
                $stmtLignes->execute([
                    $id_facture,
                    (string)$ligne['description'], // ✅ Cast explicite en string
                    // (string)$ligne['unite'],       // ✅ Cast explicite en string
                    null, // unité gérée séparément
                    floatval($ligne['quantite']),
                    floatval($ligne['prix_unitaire']),
                    floatval($ligne['total_ligne']),
                    intval($ligne['id_service']),
                    intval($ligne['id_unite']),
                    $noOrdre,
                    $descriptionDates,
                    $ligne['duree'] ?? null,
                    isset($ligne['nb_seances']) ? (int)$ligne['nb_seances'] : null,
                ]);
            }

            // ✅ La modification d'une facture ne génère pas de nouveau numéro —
            //    le numéro est immuable une fois créé.

            return [
                'success' => true,
                'message' => 'Facture modifiée avec succès',
                'id_facture' => $id_facture,
            ];
            
        } catch(PDOException $e) {
            error_log("Erreur SQL dans modifierFacture: " . $e->getMessage());
            error_log("Query info: " . print_r($e->errorInfo, true));
            throw new Exception('Erreur lors de la modification de la facture: ' . $e->getMessage());
        } catch(Exception $e) {
            error_log("Erreur générale dans modifierFacture: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * ✅ Modification restreinte d'une facture : uniquement date_facture et
     * ristourne (avec recalcul de montant_total à partir du montant_brut
     * existant). N'importe pas les lignes ni le client — utilisée pour les
     * factures générées depuis un loyer, où client/lignes proviennent de la
     * location et ne doivent pas être modifiés ici.
     *
     * @param PDO   $conn
     * @param int   $id_facture
     * @param array $data ['date_facture' => ..., 'ristourne' => ...]
     * @return array
     */
    private static function modifierAttributsLimites($conn, $id_facture, $data) {
        if (!isset($data['date_facture']) || empty($data['date_facture'])) {
            throw new Exception('Date de facture manquante');
        }

        try {
            // Récupérer l'état actuel et le montant brut existant (les lignes
            // ne changent pas dans ce mode restreint)
            $stmt = $conn->prepare(
                "SELECT etat, montant_brut FROM facture WHERE id_facture = ?"
            );
            $stmt->execute([$id_facture]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                throw new Exception('Facture non trouvée');
            }

            if (!in_array($row['etat'], ['En attente', 'Éditée'], true)) {
                throw new Exception(
                    "Cette facture est en état \"{$row['etat']}\" et ne peut plus être modifiée."
                );
            }

            $ristourne    = isset($data['ristourne']) ? floatval($data['ristourne']) : 0;
            $montantBrut  = floatval($row['montant_brut']);
            $montantTotal = $montantBrut - $ristourne;

            $stmtUpdate = $conn->prepare(
                "UPDATE facture
                 SET date_facture = ?, ristourne = ?, montant_total = ?
                 WHERE id_facture = ?"
            );
            $stmtUpdate->execute([
                $data['date_facture'],
                $ristourne,
                $montantTotal,
                $id_facture,
            ]);

            // ✅ Descriptions des lignes (uniquement ce champ, rien d'autre) —
            // tableau [{ id_ligne, description }, ...]. Scopé sur id_facture
            // par sécurité (empêche de modifier une ligne d'une autre facture).
            if (!empty($data['descriptions_lignes']) && is_array($data['descriptions_lignes'])) {
                $stmtDesc = $conn->prepare(
                    "UPDATE lignesfacture SET description = ? WHERE id_ligne = ? AND id_facture = ?"
                );
                foreach ($data['descriptions_lignes'] as $ligneDesc) {
                    if (!isset($ligneDesc['id_ligne'])) continue;
                    $stmtDesc->execute([
                        (string) ($ligneDesc['description'] ?? ''),
                        (int) $ligneDesc['id_ligne'],
                        $id_facture,
                    ]);
                }
            }

            return [
                'success'    => true,
                'message'    => 'Facture modifiée avec succès',
                'id_facture' => $id_facture,
            ];

        } catch (PDOException $e) {
            error_log("Erreur SQL dans modifierAttributsLimites: " . $e->getMessage());
            throw new Exception('Erreur lors de la modification de la facture: ' . $e->getMessage());
        } catch (Exception $e) {
            error_log("Erreur générale dans modifierAttributsLimites: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Met à jour les informations d'édition d'une facture
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id_facture ID de la facture
     * @param string $dateEdition Date d'édition
     * @param string $nomFichier Nom du fichier généré
     * @return array Résultat de l'opération
     */
    public static function mettreAJourEditionFacture($conn, $id_facture, $dateEdition, $nomFichier) {
        try {
            $sql = "UPDATE facture SET date_edition = ?, factfilename = ? WHERE id_facture = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$dateEdition, $nomFichier, $id_facture]);
            
            return [
                'success' => true,
                'message' => 'Informations d\'édition mises à jour'
            ];
        } catch (PDOException $e) {
            error_log("Erreur lors de la mise à jour de l'édition de facture: " . $e->getMessage());
            throw new Exception('Erreur lors de la mise à jour de l\'édition de facture: ' . $e->getMessage());
        }
    }

    /**
     * Supprime une facture et ses lignes
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id_facture ID de la facture à supprimer
     * @return array Résultat de l'opération
     * @throws Exception En cas d'erreur
     */
    public static function supprimerFacture($conn, $id_facture) {
        try {
            // Vérifier si la facture existe
            $checkSql = "SELECT id_facture FROM facture WHERE id_facture = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([$id_facture]);
            
            if ($checkStmt->rowCount() === 0) {
                throw new Exception('Facture non trouvée');
            }
            
            // Supprimer d'abord les lignes de facture (contrainte de clé étrangère)
            $sqlLignes = "DELETE FROM lignesfacture WHERE id_facture = ?";
            $stmtLignes = $conn->prepare($sqlLignes);
            $stmtLignes->execute([$id_facture]);
            
            // Puis supprimer la facture
            $sqlFacture = "DELETE FROM facture WHERE id_facture = ?";
            $stmtFacture = $conn->prepare($sqlFacture);
            $stmtFacture->execute([$id_facture]);
            
            return [
                'success' => true,
                'message' => 'Facture supprimée avec succès',
                'id_facture' => $id_facture
            ];
            
        } catch(PDOException $e) {
            error_log("Erreur SQL: " . $e->getMessage());
            throw new Exception('Erreur lors de la suppression de la facture: ' . $e->getMessage());
        }
    }

    // ==================================================================================
    // MÉTHODES DE GESTION D'ÉTAT
    // ==================================================================================
    
    /**
     * Change l'état d'une facture (avec protection contre l'état "Retard")
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id_facture ID de la facture à modifier
     * @param string $nouvelEtat Le nouvel état de la facture
     * @return array Résultat de l'opération
     * @throws Exception En cas d'erreur
     */
    public static function changerEtatFacture($conn, $id_facture, $nouvelEtat) {
        try {
            // ✅ PROTECTION: Empêcher la persistance de l'état "Retard"
            if ($nouvelEtat === 'Retard') {
                error_log("⚠️ Tentative de persistance de l'état 'Retard' bloquée pour la facture ID: $id_facture");
                throw new Exception('L\'état "Retard" ne peut pas être persisté. Il est calculé automatiquement côté client.');
            }
            
            // Vérifier si la facture existe
            $checkSql = "SELECT id_facture FROM facture WHERE id_facture = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([$id_facture]);
            
            if ($checkStmt->rowCount() === 0) {
                throw new Exception('Facture non trouvée');
            }
            
            // Date du jour pour les opérations automatiques
            $dateCourante = date('Y-m-d');
            
            // En fonction du nouvel état, mettre à jour les champs appropriés
            switch ($nouvelEtat) {
                case 'Annulée':
                    // Pour l'état "Annulée", on met aussi à jour la date d'annulation
                    $sql = "UPDATE facture SET etat = ?, date_annulation = ? WHERE id_facture = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$nouvelEtat, $dateCourante, $id_facture]);
                    break;
                    
                case 'Envoyée':
                    // Pour l'état "Envoyée", on met aussi à jour la date d'envoi
                    $sql = "UPDATE facture SET etat = ?, date_envoi = ? WHERE id_facture = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$nouvelEtat, $dateCourante, $id_facture]);
                    break;
                    
                case 'Payée':
                case 'Éditée':
                case 'En attente':
                // ✅ SUPPRIMÉ: case 'Retard' (plus autorisé)
                default:
                    // Pour les autres états, simplement mettre à jour le champ d'état
                    $sql = "UPDATE facture SET etat = ? WHERE id_facture = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$nouvelEtat, $id_facture]);
                    break;
            }
            
            return [
                'success' => true,
                'message' => 'État de la facture modifié avec succès',
                'id_facture' => $id_facture,
                'nouvelEtat' => $nouvelEtat
            ];
            
        } catch(PDOException $e) {
            error_log("Erreur SQL: " . $e->getMessage());
            throw new Exception('Erreur lors de la modification de l\'état de la facture: ' . $e->getMessage());
        }
    }


    // ==================================================================================
    // MÉTHODES DE STATISTIQUES
    // ==================================================================================

    /**
     * Documentation de la logique business pour les statistiques
     * 
     * États des factures et leur signification pour les statistiques :
     * 
     * - 'En attente' : Facture créée mais pas encore finalisée/envoyée
     * - 'Éditée' : Facture finalisée mais pas encore envoyée au client
     * - 'Envoyée' : Facture envoyée au client, en attente de paiement
     * - 'Payée' : Facture payée par le client
     * - 'Retard' : ✅ CALCULÉ CÔTÉ CLIENT - Facture envoyée dépassant le délai de paiement
     * - 'Annulée' : Facture annulée
     * 
     * Pour les statistiques :
     * - Montant total facturé = Somme des factures 'Envoyée' + 'Payée' + 'Partiellement payée'
     * - Montant payé = Somme des factures 'Payée' + 'Partiellement payée'
     * - Factures impayées = Nombre de factures 'Envoyée' (pas encore payées) + 'Partiellement payée'
     * - Montant restant = Montant total facturé - Montant payé
     * 
     * ✅ NOTE: L'état "Retard" n'est plus persisté en base mais calculé dynamiquement côté client
     */
    private static function getBusinessLogicDocumentation() {
        return [
            'etats_comptabilises_facture' => ['Envoyée', 'Payée'],
            'etats_comptabilises_paiement' => ['Payée'],
            'etats_impayees' => ['Envoyée']
        ];
    }

    /**
     * Récupère les statistiques complètes des factures (avec données mensuelles et distribution des états)
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int|null $annee Année pour filtrer les statistiques
     * @return array Les statistiques complètes
     * @throws Exception En cas d'erreur
     */
    public static function getStatistiquesCompletes($conn, $annee = null) {
        try {
            $whereClause = "";
            $params = [];
            
            if ($annee !== null) {
                $whereClause = " WHERE YEAR(date_facture) = ?";
                $params[] = $annee;
            }
            
            // 1. Statistiques générales (existantes)
            $stats = self::getStatistiques($conn, $annee);
            
            // 2. Ajouter les données mensuelles
            $stats['monthlySales'] = self::getStatistiquesMensuelles($conn, $annee);
            
            // 3. Ajouter la distribution des états
            $stats['statusDistribution'] = self::getDistributionEtats($conn, $annee);
            
            return $stats;
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des statistiques complètes: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération des statistiques complètes');
        }
    }

    /**
     * Récupère les statistiques mensuelles pour une année donnée
     */
    private static function getStatistiquesMensuelles($conn, $annee) {
        $whereClause = "";
        $params = [];
        
        if ($annee !== null) {
            $whereClause = " WHERE YEAR(date_facture) = ?";
            $params[] = $annee;
        }
        
        $query = "
            SELECT 
                MONTH(date_facture) as mois,
                SUM(CASE WHEN etat = 'Envoyée' OR etat = 'Payée' OR etat = 'Partiellement payée' THEN montant_total ELSE 0 END) as montant_facture,
                SUM(CASE WHEN etat = 'Payée' THEN montant_total ELSE 0 END) as montant_paye,
                COUNT(CASE WHEN etat = 'Envoyée' OR etat = 'Payée' OR etat = 'Partiellement payée' THEN 1 END) as nombre_factures
            FROM facture" . $whereClause . "
            GROUP BY MONTH(date_facture)
            ORDER BY MONTH(date_facture)
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $months = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
        ];
        
        // Initialiser tous les mois avec des valeurs zéro
        $data = [];
        $currentYear = date('Y');
        $currentMonth = ($annee == $currentYear) ? date('n') : 12;
        
        for ($i = 1; $i <= $currentMonth; $i++) {
            $data[] = [
                'name' => $months[$i],
                'facturé' => 0,
                'payé' => 0,
                'nombre' => 0
            ];
        }
        
        // Remplir avec les données réelles
        foreach ($result as $row) {
            $index = $row['mois'] - 1;
            if ($index >= 0 && $index < count($data)) {
                $data[$index] = [
                    'name' => $months[$row['mois']],
                    'facturé' => floatval($row['montant_facture']),
                    'payé' => floatval($row['montant_paye']),
                    'nombre' => intval($row['nombre_factures'])
                ];
            }
        }
        
        return $data;
    }

    /**
     * Récupère la distribution des états des factures
     * ✅ NOTE: L'état "Retard" n'apparaîtra plus dans les statistiques DB car calculé côté client
     */
    private static function getDistributionEtats($conn, $annee) {
        $whereClause = "";
        $params = [];
        
        if ($annee !== null) {
            $whereClause = " WHERE YEAR(date_facture) = ?";
            $params[] = $annee;
            $totalParams = $params; // Pour la sous-requête
        } else {
            $totalParams = [];
        }
        
        $query = "
            SELECT 
                COALESCE(etat, 'En attente') as etat,
                COUNT(*) as nombre,
                ROUND((COUNT(*) * 100.0 / total.total_factures), 1) as pourcentage
            FROM facture,
            (SELECT COUNT(*) as total_factures FROM facture" . $whereClause . ") as total" . 
            $whereClause . "
            GROUP BY COALESCE(etat, 'En attente')
            ORDER BY nombre DESC
        ";
        
        // Préparer les paramètres : ceux pour la sous-requête + ceux pour la requête principale
        $allParams = array_merge($totalParams, $params);
        
        $stmt = $conn->prepare($query);
        $stmt->execute($allParams);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $data = [];
        foreach ($result as $row) {
            $data[] = [
                'name' => $row['etat'],
                'value' => floatval($row['pourcentage']),
                'count' => intval($row['nombre'])
            ];
        }
        
        return $data;
    }

    /**
     * Récupère les statistiques des factures
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int|null $annee Année pour filtrer les statistiques
     * @return array Les statistiques
     * @throws Exception En cas d'erreur
     */
    public static function getStatistiques($conn, $annee = null) {
        try {
            $whereClause = "";
            $params = [];
            
            if ($annee !== null) {
                $whereClause = " WHERE YEAR(date_facture) = ?";
                $params[] = $annee;
            }
            
            $stats = [];
            
            // Nombre total de factures
            $sql1 = "SELECT COUNT(*) as total FROM facture" . $whereClause;
            $stmt1 = $conn->prepare($sql1);
            $stmt1->execute($params);
            $stats['totalFactures'] = $stmt1->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Montant total facturé (seulement les factures Envoyée et Payée ou partiellement payée)
            $sql2 = "SELECT SUM(montant_total) as total FROM facture WHERE (etat = 'Envoyée' OR etat = 'Payée' OR etat = 'Partiellement payée')";
            if ($annee !== null) {
                $sql2 .= " AND YEAR(date_facture) = ?";
            }
            $stmt2 = $conn->prepare($sql2);
            $stmt2->execute($params);
            $stats['montantTotal'] = $stmt2->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Montant total payé (seulement les factures à l'état Payée)
            $sql3 = "SELECT SUM(montant_total) as total FROM facture WHERE etat = 'Payée'";
            if ($annee !== null) {
                $sql3 .= " AND YEAR(date_facture) = ?";
            }
            $stmt3 = $conn->prepare($sql3);
            $stmt3->execute($params);
            $stats['montantPaye'] = $stmt3->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Nombre de factures impayées (seulement celles qui sont Envoyée mais pas encore Payée)
            $sql4 = "SELECT COUNT(*) as total FROM facture WHERE etat in ('Envoyée', 'Partiellement payée')";
            if ($annee !== null) {
                $sql4 .= " AND YEAR(date_facture) = ?";
            }
            $stmt4 = $conn->prepare($sql4);
            $stmt4->execute($params);
            $stats['facturesImpayees'] = $stmt4->fetch(PDO::FETCH_ASSOC)['total'];
            
            return $stats;
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des statistiques: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération des statistiques');
        }
    }

    
    // ==================================================================================
    // MÉTHODES UTILITAIRES PRIVÉES
    // ==================================================================================

    /**
     * Calcule le montant total d'une facture (montant brut - ristourne)
     * 
     * @param array $lignes Les lignes de la facture
     * @param float $ristourne Montant de la ristourne
     * @return float Montant total net
     * @throws Exception En cas d'erreur
     */
    private static function calculerMontantTotal($lignes, $ristourne = 0) {
        // Calculer le montant total des lignes (montant brut)
        $montantBrut = 0;
        foreach ($lignes as $ligne) {
            if (!isset($ligne['total_ligne']) && !isset($ligne['total'])) {
                throw new Exception('Total manquant dans une ligne de facture');
            }
            $montantBrut += floatval($ligne['total_ligne'] ?? $ligne['total']);
        }
        
        // Calculer le montant total final (montant brut - ristourne)
        $montantNet = max(0, $montantBrut - floatval($ristourne));
        
        return $montantNet;
    }

    /**
     * ✅ Cascade FIFO des paiements sur les mois d'une confirmation de paiement.
     *
     * Contexte : pour une facture de type confirmation (contrat au forfait),
     * les paiements sont cumulatifs sur la facture entière (paiement.id_facture),
     * sans lien direct à un mois précis. Cette méthode recalcule, pour
     * l'affichage, quel mois est payé à quelle date, en épuisant les mois
     * dans l'ordre (mois ASC) avec les paiements confirmés dans l'ordre
     * chronologique (date_paiement ASC, puis id_paiement ASC en cas d'égalité).
     *
     * Règles (voir discussion projet) :
     *   - Un paiement unique couvrant plusieurs mois : tous ces mois sont
     *     marqués payés à la date de ce paiement unique.
     *   - Des paiements mensuels successifs : chaque mois est payé à la date
     *     du paiement qui l'a soldé (potentiellement différente pour chacun).
     *   - Si un mois est soldé par la combinaison de deux paiements
     *     successifs, sa date de paiement est celle du second (celui qui a
     *     complété le montant dû).
     *   - Ne fait rien si la facture n'a pas de détail mensuel (facture
     *     standard "à l'utilisation", pas une confirmation).
     *   - La ristourne de la facture (le cas échéant) est déduite du montant
     *     dû du dernier mois — les montants de facture_detail_mensuel sont
     *     bruts, seule cette déduction permet à un paiement du montant net
     *     total de couvrir effectivement tous les mois.
     *
     * À appeler après tout INSERT/UPDATE/DELETE de paiement concernant une
     * facture susceptible d'être une confirmation (voir PaiementControleur /
     * ServicePaiement).
     *
     * @param PDO $conn
     * @param int $id_facture
     * @return void
     */
    public static function recalculerCascadeMensuelle($conn, $id_facture) {
        // 1. Détails mensuels de cette facture, triés par mois
        $stmtDetails = $conn->prepare(
            "SELECT id_detail, mois, montant FROM facture_detail_mensuel
             WHERE id_facture = ? ORDER BY mois ASC"
        );
        $stmtDetails->execute([$id_facture]);
        $details = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);

        if (empty($details)) {
            return; // Facture "à l'utilisation" (pas de détail mensuel) : rien à faire
        }

        // ✅ La ristourne s'applique au niveau de la facture entière, pas
        // mois par mois — mais les montants de facture_detail_mensuel sont
        // bruts (leur somme = montant_brut). Sans en tenir compte ici, la
        // cascade réclamerait toujours le montant total BRUT, laissant le
        // dernier mois "non payé" même facture soldée (montant_total net
        // intégralement payé). On déduit donc la ristourne du dernier mois
        // (le plus simple et le plus courant : une ristourne réduit le solde
        // final).
        $stmtRistourne = $conn->prepare("SELECT ristourne FROM facture WHERE id_facture = ?");
        $stmtRistourne->execute([$id_facture]);
        $ristourne = (float) $stmtRistourne->fetchColumn();

        // 2. Paiements confirmés de cette facture, triés chronologiquement
        $stmtPaiements = $conn->prepare(
            "SELECT montant_paye, date_paiement FROM paiement
             WHERE id_facture = ? AND statut = 'confirme'
             ORDER BY date_paiement ASC, id_paiement ASC"
        );
        $stmtPaiements->execute([$id_facture]);
        $paiements = $stmtPaiements->fetchAll(PDO::FETCH_ASSOC);

        $nbPaiements = count($paiements);

        // 3. Cascade FIFO : consommer les paiements dans l'ordre pour
        // couvrir chaque mois, dans l'ordre.
        $indexPaiement        = 0;
        $resteSurPaiement     = $nbPaiements > 0 ? (float) $paiements[0]['montant_paye'] : 0.0;
        $dateCourantePaiement = $nbPaiements > 0 ? $paiements[0]['date_paiement']        : null;

        $stmtUpdate = $conn->prepare(
            "UPDATE facture_detail_mensuel SET est_paye = ?, date_paiement = ? WHERE id_detail = ?"
        );

        $epsilon = 0.005; // Tolérance flottante, cohérente avec le reste du code
        $dernierIndex = count($details) - 1;

        foreach ($details as $i => $detail) {
            $montantDu     = (float) $detail['montant'];
            // ✅ Ristourne déduite du dernier mois uniquement (voir commentaire plus haut)
            if ($i === $dernierIndex && $ristourne > 0) {
                $montantDu = max(0, $montantDu - $ristourne);
            }
            $montantAlloue = 0.0;
            $dateSoldeur   = null;

            while ($montantAlloue < ($montantDu - $epsilon) && $indexPaiement < $nbPaiements) {
                $aPrendre       = min($montantDu - $montantAlloue, $resteSurPaiement);
                $montantAlloue += $aPrendre;
                $resteSurPaiement -= $aPrendre;
                $dateSoldeur    = $dateCourantePaiement;

                if ($resteSurPaiement <= $epsilon) {
                    $indexPaiement++;
                    if ($indexPaiement < $nbPaiements) {
                        $resteSurPaiement     = (float) $paiements[$indexPaiement]['montant_paye'];
                        $dateCourantePaiement = $paiements[$indexPaiement]['date_paiement'];
                    }
                }
            }

            $estPaye = ($montantAlloue >= ($montantDu - $epsilon)) ? 1 : 0;
            $stmtUpdate->execute([
                $estPaye,
                $estPaye ? $dateSoldeur : null,
                $detail['id_detail'],
            ]);
        }
    }

    /**
     * ✅ Crée une facture de type confirmation de paiement (contrat au
     * forfait), avec son détail mensuel figé (facture_detail_mensuel) —
     * équivalent de ajouterFacture() mais sans lignesfacture, pour les
     * contrats qui ne facturent pas par ligne/unité mais par mois.
     *
     * @param PDO   $conn
     * @param array $data ['numero_facture','date_facture','id_client',
     *                     'ristourne','motif','id_contrat_location',
     *                     'details_mensuels' => [{mois,annee,id_unite,
     *                     id_service,quantite,description,montant,dates,
     *                     duree,nb_seances}, ...]]
     * @return array
     * @throws Exception
     */
    public static function ajouterFactureAvecDetailMensuel($conn, $data) {
        if (!is_array($data)) {
            throw new Exception('Données de facture invalides - tableau attendu');
        }
        foreach (['numero_facture', 'date_facture', 'id_client', 'details_mensuels'] as $champ) {
            if (!isset($data[$champ])) {
                throw new Exception("Champ obligatoire manquant : $champ");
            }
        }
        if (!is_array($data['details_mensuels']) || empty($data['details_mensuels'])) {
            throw new Exception('Aucun détail mensuel fourni');
        }

        try {
            $ristourne   = isset($data['ristourne']) ? floatval($data['ristourne']) : 0;
            $montantBrut = 0;
            foreach ($data['details_mensuels'] as $d) {
                if (!isset($d['montant'])) {
                    throw new Exception('Montant manquant dans un détail mensuel');
                }
                $montantBrut += floatval($d['montant']);
            }
            $montantTotal = max(0, $montantBrut - $ristourne);

            $idContratLocation = isset($data['id_contrat_location']) && $data['id_contrat_location'] !== ''
                ? (int) $data['id_contrat_location'] : null;

            // ✅ date_edition reste NULL à la création — voir le même
            // correctif et la même explication dans ajouterFacture() ci-dessus.

            // ✅ État initial spécifique aux confirmations : 'Non payé', pas
            // 'En attente' (vocabulaire des factures standard "à l'utilisation").
            $stmt = $conn->prepare("INSERT INTO facture
                (numero_facture, date_facture, montant_total, montant_brut, id_client, ristourne, motif, id_contrat_location, etat)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Non payé')");
            $stmt->execute([
                $data['numero_facture'],
                $data['date_facture'],
                $montantTotal,
                $montantBrut,
                $data['id_client'],
                $ristourne,
                $data['motif'] ?? null,
                $idContratLocation,
            ]);

            $idFacture = (int) $conn->lastInsertId();

            self::insererDetailsMensuels($conn, $idFacture, $data['details_mensuels']);

            return [
                'success'       => true,
                'message'       => 'Confirmation de paiement créée avec succès',
                'idFacture'     => $idFacture,
                'id_facture'    => $idFacture,
                'numeroFacture' => $data['numero_facture'],
            ];

        } catch (PDOException $e) {
            error_log("Erreur SQL dans ajouterFactureAvecDetailMensuel: " . $e->getMessage());
            throw new Exception('Erreur lors de la création de la confirmation de paiement: ' . $e->getMessage());
        }
    }

    /**
     * ✅ Modification restreinte d'une confirmation de paiement (contrat au
     * forfait) : uniquement date_facture, et description/montant des lignes
     * de facture_detail_mensuel déjà existantes (identifiées par id_detail,
     * scopées sur id_facture par sécurité). N'importe pas le client ni le
     * nombre de mois — utilisée depuis le bouton Modifier de la liste des
     * factures, uniquement tant qu'aucun paiement n'a été enregistré.
     * Miroir de modifierAttributsLimites() pour les factures standard.
     *
     * @param PDO   $conn
     * @param int   $id_facture
     * @param array $data ['date_facture' => ..., 'ristourne' => ..., 'details_mensuels' => [{id_detail, montant, description}, ...]]
     * @return array
     */
    private static function modifierAttributsLimitesConfirmation($conn, $id_facture, $data) {
        if (!isset($data['date_facture']) || empty($data['date_facture'])) {
            throw new Exception('Date de facture manquante');
        }

        try {
            $stmt = $conn->prepare("SELECT etat, ristourne FROM facture WHERE id_facture = ?");
            $stmt->execute([$id_facture]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                throw new Exception('Facture non trouvée');
            }

            // ✅ Seul l'état 'Non payé' autorise cette modification restreinte
            // — dès qu'un paiement existe (même partiel), on bloque.
            if ($row['etat'] !== 'Non payé') {
                throw new Exception(
                    "Cette confirmation est en état \"{$row['etat']}\" et ne peut plus être modifiée " .
                    "(un paiement a déjà été enregistré)."
                );
            }

            $stmtDate = $conn->prepare("UPDATE facture SET date_facture = ? WHERE id_facture = ?");
            $stmtDate->execute([$data['date_facture'], $id_facture]);

            // ✅ Description + montant de chaque mois (uniquement ces deux
            // champs, rien d'autre) — tableau [{ id_detail, montant, description }, ...].
            // Scopé sur id_facture par sécurité (empêche de modifier une ligne
            // d'une autre facture).
            $montantBrut = 0;
            if (!empty($data['details_mensuels']) && is_array($data['details_mensuels'])) {
                $stmtDetail = $conn->prepare(
                    "UPDATE facture_detail_mensuel SET montant = ?, description = ? WHERE id_detail = ? AND id_facture = ?"
                );
                foreach ($data['details_mensuels'] as $detail) {
                    if (!isset($detail['id_detail'])) continue;
                    $montant = floatval($detail['montant'] ?? 0);
                    $stmtDetail->execute([
                        $montant,
                        (string) ($detail['description'] ?? ''),
                        (int) $detail['id_detail'],
                        $id_facture,
                    ]);
                    $montantBrut += $montant;
                }

                // Recalculer montant_brut / montant_total à partir de la
                // somme des montants mensuels mis à jour et de la ristourne
                // (nouvelle valeur envoyée si présente, sinon celle déjà en
                // base — ristourne modifiable comme pour une facture standard).
                $ristourne    = isset($data['ristourne']) ? floatval($data['ristourne']) : floatval($row['ristourne']);
                $montantTotal = max(0, $montantBrut - $ristourne);
                $stmtMontant = $conn->prepare(
                    "UPDATE facture SET montant_brut = ?, montant_total = ?, ristourne = ? WHERE id_facture = ?"
                );
                $stmtMontant->execute([$montantBrut, $montantTotal, $ristourne, $id_facture]);
            }

            return [
                'success'    => true,
                'message'    => 'Confirmation modifiée avec succès',
                'id_facture' => $id_facture,
            ];

        } catch (PDOException $e) {
            error_log("Erreur SQL dans modifierAttributsLimitesConfirmation: " . $e->getMessage());
            throw new Exception('Erreur lors de la modification de la confirmation: ' . $e->getMessage());
        }
    }

    /**
     * ✅ Modifie une facture de type confirmation de paiement : remplace
     * entièrement son détail mensuel (delete + insert, comme lignesfacture
     * pour modifierFacture) et recalcule les montants. Mêmes garde-fous que
     * modifierFacture/modifierAttributsLimites, mais avec le vocabulaire
     * d'état propre aux confirmations (seul 'Non payé' autorise la
     * régénération — dès qu'un paiement existe, on bloque).
     *
     * @param PDO   $conn
     * @param int   $id_facture
     * @param array $data ['date_facture','ristourne','motif',
     *                     'regenere_depuis_location', 'details_mensuels']
     * @return array
     * @throws Exception
     */
    public static function modifierFactureAvecDetailMensuel($conn, $id_facture, $data) {
        if (!is_array($data)) {
            throw new Exception('Données de facture invalides - tableau attendu');
        }

        // ✅ Modification restreinte (date_facture + description/montant du
        // détail mensuel uniquement) — utilisée depuis le bouton Modifier de
        // la liste des factures pour une confirmation non encore payée.
        // Contrairement à la régénération complète ci-dessous : ne touche ni
        // le client ni le nombre de mois, ne nécessite pas de venir de la
        // location (bypass volontaire du garde-fou id_contrat_location, même
        // principe que modifierAttributsLimites pour les factures standard).
        if (!empty($data['modification_limitee'])) {
            return self::modifierAttributsLimitesConfirmation($conn, $id_facture, $data);
        }

        try {
            $stmtLien = $conn->prepare("SELECT id_contrat_location, etat FROM facture WHERE id_facture = ?");
            $stmtLien->execute([$id_facture]);
            $lienFacture = $stmtLien->fetch(PDO::FETCH_ASSOC);

            if (!$lienFacture) {
                throw new Exception('Facture non trouvée');
            }

            $estLieeAUneLocation = $lienFacture['id_contrat_location'] !== null;

            if ($estLieeAUneLocation && empty($data['regenere_depuis_location'])) {
                throw new Exception(
                    'Cette confirmation est liée à une location de salle et ne peut pas être modifiée directement. ' .
                    'Modifiez la location pour mettre à jour la confirmation.'
                );
            }

            // ✅ Seul l'état 'Non payé' autorise la régénération — équivalent,
            // pour une confirmation, de 'En attente'/'Éditée' pour une facture
            // standard. Dès qu'un paiement existe, on bloque.
            if (!in_array($lienFacture['etat'], ['Non payé'], true)) {
                throw new Exception(
                    "Cette confirmation est en état \"{$lienFacture['etat']}\" et ne peut plus être régénérée."
                );
            }

            if (!isset($data['details_mensuels']) || !is_array($data['details_mensuels']) || empty($data['details_mensuels'])) {
                throw new Exception('Aucun détail mensuel fourni');
            }

            $ristourne   = isset($data['ristourne']) ? floatval($data['ristourne']) : 0;
            $montantBrut = 0;
            foreach ($data['details_mensuels'] as $d) {
                $montantBrut += floatval($d['montant'] ?? 0);
            }
            $montantTotal = max(0, $montantBrut - $ristourne);

            $stmtUpdate = $conn->prepare("UPDATE facture
                SET date_facture = ?, montant_total = ?, montant_brut = ?, ristourne = ?, motif = ?
                WHERE id_facture = ?");
            $stmtUpdate->execute([
                $data['date_facture'] ?? date('Y-m-d'),
                $montantTotal,
                $montantBrut,
                $ristourne,
                $data['motif'] ?? null,
                $id_facture,
            ]);

            // Remplacer les détails mensuels (delete + insert)
            $conn->prepare("DELETE FROM facture_detail_mensuel WHERE id_facture = ?")->execute([$id_facture]);
            self::insererDetailsMensuels($conn, $id_facture, $data['details_mensuels']);

            return [
                'success'    => true,
                'message'    => 'Confirmation de paiement mise à jour avec succès',
                'id_facture' => $id_facture,
            ];

        } catch (PDOException $e) {
            error_log("Erreur SQL dans modifierFactureAvecDetailMensuel: " . $e->getMessage());
            throw new Exception('Erreur lors de la mise à jour de la confirmation de paiement: ' . $e->getMessage());
        }
    }

    /**
     * Insère les lignes de facture_detail_mensuel pour une facture donnée.
     * Helper privé utilisé par ajouterFactureAvecDetailMensuel et
     * modifierFactureAvecDetailMensuel.
     *
     * @param PDO   $conn
     * @param int   $idFacture
     * @param array $details
     * @return void
     */
    private static function insererDetailsMensuels($conn, $idFacture, $details) {
        $stmt = $conn->prepare("INSERT INTO facture_detail_mensuel
            (id_facture, mois, annee, id_unite, id_service, quantite, description, montant, dates, duree, nb_seances)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($details as $d) {
            if (!isset($d['mois'], $d['annee'], $d['montant'])) {
                throw new Exception('Détail mensuel incomplet (mois/annee/montant requis)');
            }
            $stmt->execute([
                $idFacture,
                (int) $d['mois'],
                (int) $d['annee'],
                isset($d['id_unite'])   && $d['id_unite']   !== '' ? (int) $d['id_unite']   : null,
                isset($d['id_service']) && $d['id_service'] !== '' ? (int) $d['id_service'] : null,
                isset($d['quantite']) ? floatval($d['quantite']) : null,
                $d['description'] ?? null,
                floatval($d['montant']),
                isset($d['dates']) ? (is_string($d['dates']) ? $d['dates'] : json_encode($d['dates'])) : null,
                $d['duree'] ?? null,
                isset($d['nb_seances']) ? (int) $d['nb_seances'] : null,
            ]);
        }
    }
}
?>