<?php

/**
 * FPDILoyerConfirmationGenerator.php
 *
 * Générateur PDF pour les CONFIRMATIONS DE PAIEMENT (factures générées
 * depuis une location au forfait — voir facture.est_forfait via
 * type_contrat_location, et facture_detail_mensuel pour le détail mensuel).
 * Nom de fichier/classe conservé tel quel (historique "loyer") pour éviter
 * de casser PDFGeneratorFactory::create('fpdi_loyer') — seul le contenu a
 * été adapté ; plus aucune référence aux anciennes tables loyer/loyer_detail
 * (supprimées, migrations 043/046).
 *
 * Étend AbstractPDFGenerator (PDFBase.php) pour réutiliser :
 *   - genererEnTete()      → logo, date, adresse centre + client, titre
 *   - genererPiedPage()    → coordonnées bancaires, remerciements, signature
 *   - fermerCadre()        → double bordure du cadre
 *   - dateEnFrancais()     → formatage des dates
 *   - sauvegarderPages()   → écriture et fusion PDF
 *
 * Données attendues dans $facture (issues de
 * FactureControleur::getFactureParId, voir ServiceFacture::imprimerFacture) :
 *   // Champs client (déjà non-suffixés dans getFactureParId)
 *   titre, prenom, nom, rue, numero, code_postal, localite
 *
 *   // Champs facture
 *   numero_facture, motif, date_facture, ristourne
 *
 *   // Détail mensuel (facture_detail_mensuel, via details_mensuels[])
 *   details_mensuels : array de [
 *     mois (1-12), annee, montant, description, quantite, abreviation_unite,
 *     est_paye (0|1), date_paiement (Y-m-d|null)
 *   ]
 *   Période et durée affichées sont déduites de ce tableau (pas de colonne
 *   dédiée sur facture) ; montant_paye par mois est binaire (montant si
 *   est_paye, sinon 0 — pas de paiement partiel par mois avec la cascade
 *   FIFO, contrairement à l'ancien modèle loyer_detail).
 */

require_once 'PDFBase.php';

class FPDILoyerConfirmationGenerator extends AbstractPDFGenerator {

    // ═══════════════════════════════════════════════════════════════
    //  IMPLÉMENTATION DES MÉTHODES ABSTRAITES
    // ═══════════════════════════════════════════════════════════════

    /**
     * Titre affiché sous les adresses.
     */
    protected function getTitrePrincipal(array $data): string {
        return 'Confirmation de paiement : location d\'un cabinet « La Grange »';
    }

    /**
     * Toujours 1 page (tableau 2 colonnes, max 13 mois).
     */
    protected function calculerNombrePages(array $data, array $params): int {
        return 1;
    }

    /**
     * Génère le contenu spécifique : infos facture + tableau mensuel 2 colonnes.
     */
    protected function genererContenu(MyFPDI $pdf, array $data, array $params, int $page): void {
        $this->frameStartY = $pdf->GetY();
        $this->frameOpen   = true;

        // ✅ Pas de colonne dédiée équivalente à l'ancien
        // loyer.afficher_dates_paiement — toujours affichées.
        $afficherDates = true;

        $this->genererInfosLoyer($pdf, $data);
        $this->genererSeparateur($pdf);
        $this->genererTableau2Colonnes($pdf, $data['details'] ?? [], $afficherDates);

        $this->fermerCadre($pdf, true);

        $banque    = $params['banque']    ?? [];
        $signature = $params['signature'] ?? [];
        // Confirmation : pas de délai de paiement (0)
        $this->genererPiedPage($pdf, $banque, 0, $signature);
    }


    // ═══════════════════════════════════════════════════════════════
    //  POINT D'ENTRÉE PUBLIC  (implémente PDFGeneratorInterface)
    // ═══════════════════════════════════════════════════════════════

    /**
     * @param array  $facture     Données facture + détails mensuels + client
     *                            (FactureControleur::getFactureParId)
     * @param string $pdfFileName Nom du fichier (ex: "ConfirmationPaiement_C-001.2026_....pdf")
     * @param string $outputDir   Dossier de sortie absolu (calculé par
     *                            ServiceFacture::imprimerFacture() via
     *                            confirmations_path()) — si omis, retombe sur
     *                            confirmations_path('') sans accès aux
     *                            paramètres personnalisés (cette classe n'a
     *                            pas de connexion DB).
     * @param array  $banque      Paramètres bancaires (Banque, IBAN, Beneficiaire)
     * @param int    $delai       Ignoré (pas de délai sur une confirmation)
     * @param array  $signature   Paramètres signature (Ligne 1, Ligne 2)
     */
    public function genererPDF(
        $facture,
        $pdfFileName,
        $outputDir          = null,
        $banque             = [],
        $delaiPaiement      = 0,
        $signature          = [],
        $printRistourne     = false
    ) {
        // $delaiPaiement et $printRistourne ignorés pour les confirmations
        error_log("📄 ConfirmationGenerator — génération: $pdfFileName");

        // ── Normaliser les données depuis FactureControleur::getFactureParId ──
        $facture = $this->normaliserDonnees($facture);

        $params = ['banque' => $banque, 'signature' => $signature];
        $this->totalPages  = 1;
        $this->currentPage = 1;

        // Initialiser le PDF
        $titre = $this->getTitrePrincipal($facture) . ' — ' . ($facture['numero_facture'] ?? '');
        $pdf   = $this->initPDF($titre);
        $this->initDimensionsCadre($pdf);

        // En-tête commun
        $this->genererEnTete($pdf, $facture);

        // Contenu spécifique confirmation
        $this->genererContenu($pdf, $facture, $params, 1);

        // Numérotation (1/1)
        $this->ajouterNumerotation($pdf);

        // ✅ Dossier de sortie dédié aux confirmations (distinct des
        // factures standard) — reçu de ServiceFacture::imprimerFacture(),
        // qui l'a calculé via confirmations_path($conn) (paramètre
        // personnalisable). Repli sur la valeur par défaut si jamais appelé
        // sans ce paramètre (cette classe n'a pas de connexion DB).
        return $this->sauvegarderPages([$pdf], $pdfFileName, $outputDir ?? confirmations_path(''));
    }


    // ═══════════════════════════════════════════════════════════════
    //  MÉTHODES PRIVÉES — contenu spécifique confirmation
    // ═══════════════════════════════════════════════════════════════

    /**
     * Normalise les données reçues de FactureControleur::getFactureParId
     * vers le format interne attendu par le générateur.
     *
     * getFactureParId retourne déjà prenom/nom/titre/rue/numero/code_postal/
     * localite non-suffixés (join direct sur client, pas d'ambiguïté) — pas
     * de renommage nécessaire côté client, contrairement à l'ancien
     * getLoyerParId. Le détail mensuel arrive dans details_mensuels[].
     */
    private function normaliserDonnees(array $facture): array {
        // ── Date du document ──────────────────────────────────────────────────
        if (!isset($facture['date_document'])) {
            $facture['date_document'] = $facture['date_facture'] ?? date('Y-m-d');
        }

        // ── Normaliser les détails mensuels ───────────────────────────────────
        $source = $facture['details'] ?? $facture['details_mensuels'] ?? [];

        $nomsMois = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
        ];

        $details = [];
        foreach ($source as $d) {
            $numeroMois = (int) ($d['mois'] ?? 0);
            $annee      = $d['annee'] ?? '';
            $estPaye    = (bool) ($d['est_paye'] ?? false);
            $montant    = floatval($d['montant'] ?? 0);

            $details[] = [
                'id_detail'      => $d['id_detail'] ?? null,
                'mois'           => trim(($nomsMois[$numeroMois] ?? '') . ' ' . $annee),
                'numero_mois'    => $numeroMois,
                'annee'          => $annee,
                'montant'        => $montant,
                'quantite'       => isset($d['quantite']) && $d['quantite'] !== null ? floatval($d['quantite']) : null,
                'description'    => $d['description'] ?? null,
                'est_paye'       => $estPaye,
                'date_paiement'  => $d['date_paiement'] ?? null,
                // ✅ Pas de paiement partiel par mois avec la cascade FIFO
                // (facture_detail_mensuel.est_paye est binaire) : le montant
                // payé d'un mois est soit son montant complet, soit 0.
                'montant_paye'   => $estPaye ? $montant : 0,
            ];
        }
        $facture['details'] = $details;

        // ── Description commune (un seul champ, partagé par tous les mois —
        // voir FactureForm.jsx côté frontend) ─────────────────────────────────
        $facture['detail_description'] = $details[0]['description'] ?? null;

        // ── Tarif unitaire : montant / quantité du premier mois qualifiant ────
        $facture['tarif_unitaire']    = null;
        $facture['tarif_abreviation'] = null;
        foreach ($details as $d) {
            if (!empty($d['quantite']) && $d['quantite'] > 0 && $d['montant'] > 0) {
                $facture['tarif_unitaire'] = round($d['montant'] / $d['quantite'], 2);
                break;
            }
        }
        foreach ($source as $raw) {
            if (!empty($raw['abreviation_unite'])) {
                $facture['tarif_abreviation'] = $raw['abreviation_unite'];
                break;
            }
        }

        // ── Période et durée : déduites du détail mensuel (pas de colonne
        // dédiée sur facture, contrairement à l'ancien loyer) ─────────────────
        if (!empty($details)) {
            $premier = $details[0];
            $dernier = $details[count($details) - 1];
            $facture['periode_debut_label'] = $premier['mois'];
            $facture['periode_fin_label']   = $dernier['mois'];
            $facture['duree_mois']          = count($details);
        } else {
            $facture['periode_debut_label'] = '';
            $facture['periode_fin_label']   = '';
            $facture['duree_mois']          = 0;
        }

        error_log("📊 normaliserDonnees: " . count($details) . " mois, client: {$facture['prenom']} {$facture['nom']}");

        return $facture;
    }

    /**
     * Bloc d'informations générales de la confirmation (4 lignes + optionnel statut).
     */
    private function genererInfosLoyer(MyFPDI $pdf, array $data): void {
        $pdf->SetFont($this->fontRegular, '', 10);
        $startX = $this->textStartX;
        $labelW = 38;
        $valW   = $this->frameWidth - $labelW - 6;
        $lineH  = 6;

        $pdf->SetXY($startX, $this->frameStartY + 3);

        // Responsable
        $pdf->SetFont($this->fontRegular, '', 10);
        $pdf->Cell($labelW, $lineH, 'Responsable :', 0, 0, 'L');
        $pdf->Cell($valW,   $lineH, ($data['titre'] ? $data['titre'] . ' ' : '')
                                     . ($data['prenom'] ?? '') . ' ' . ($data['nom'] ?? ''), 0, 1, 'L');
        $pdf->SetX($startX);

        // Motif
        $pdf->Cell($labelW, $lineH, 'Motif :', 0, 0, 'L');
        $pdf->Cell($valW,   $lineH, $data['motif'] ?? 'location d\'un cabinet', 0, 1, 'L');
        $pdf->SetX($startX);

        // Période (déduite du détail mensuel — voir normaliserDonnees)
        $pdf->Cell($labelW, $lineH, 'Période :', 0, 0, 'L');
        $pdf->Cell($valW,   $lineH, 'Du ' . ($data['periode_debut_label'] ?? '') . ' au ' . ($data['periode_fin_label'] ?? ''), 0, 1, 'L');
        $pdf->SetX($startX);

        // Durée
        $duree = ($data['duree_mois'] ?? 0) . ' mois';
        $pdf->Cell($labelW, $lineH, 'Durée :', 0, 0, 'L');
        $pdf->Cell($valW,   $lineH, $duree, 0, 1, 'L');

        // Détail (conditionnel — si une description existe)
        if (!empty($data['detail_description'])) {
            $pdf->SetX($startX);
            $pdf->Cell($labelW, $lineH, 'Détail :', 0, 0, 'L');
            $pdf->Cell($valW,   $lineH, $data['detail_description'], 0, 1, 'L');
        }

        // Tarif (conditionnel — si un tarif unitaire a pu être calculé)
        if (!empty($data['tarif_unitaire'])) {
            $tarifStr = number_format($data['tarif_unitaire'], 2, '.', "'") . ' CHF';
            if (!empty($data['tarif_abreviation'])) {
                $tarifStr .= ' / ' . $data['tarif_abreviation'];
            }
            $pdf->SetX($startX);
            $pdf->Cell($labelW, $lineH, 'Tarif :', 0, 0, 'L');
            $pdf->Cell($valW,   $lineH, $tarifStr, 0, 1, 'L');
        }
    }

    /**
     * Ligne séparatrice fine entre les infos et le tableau.
     */
    private function genererSeparateur(MyFPDI $pdf): void {
        $pdf->Ln(2);
        $y = $pdf->GetY();
        $pdf->SetLineWidth(0.2);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Line($this->textStartX, $y,
                   $this->frameX + $this->frameWidth - 3, $y);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->Ln(3);
    }

    /**
     * Tableau mensuel sur 2 colonnes côte à côte.
     * Colonne gauche : mois 1 → ceil(n/2)
     * Colonne droite  : mois ceil(n/2)+1 → n
     *
     * Colonnes par demi-tableau : Mois | Date paiement | Montant | État
     */
    private function genererTableau2Colonnes(MyFPDI $pdf, array $details, bool $afficherDates = true): void {
        if (empty($details)) return;

        $n       = count($details);
        $midpoint = (int) ceil($n / 2);
        $leftCol  = array_slice($details, 0, $midpoint);
        $rightCol = array_slice($details, $midpoint);

        // Largeurs
        $sep        = 5;                                         // séparateur central mm
        $colWidth   = ($this->frameWidth - 6 - $sep) / 2;      // largeur de chaque demi-tableau
        $startX     = $this->textStartX;
        $rightStartX = $startX + $colWidth + $sep;

        // Sous-colonnes — largeurs adaptées selon présence de la colonne date
        if ($afficherDates) {
            $moisW    = $colWidth * 0.35;
            $dateW    = $colWidth * 0.29;
            $montantW = $colWidth * 0.20;
            $etatW    = $colWidth - $moisW - $dateW - $montantW;
        } else {
            $moisW    = $colWidth * 0.50;
            $dateW    = 0;
            $montantW = $colWidth * 0.28;
            $etatW    = $colWidth - $moisW - $montantW;
        }

        $rowH     = 6;
        $headerH  = 6;
        $startY   = $pdf->GetY();

        // ── En-têtes des 2 colonnes ────────────────────────────────
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetFont($this->fontBold, 'B', 9);

        // Fond gris pour les 2 en-têtes
        $pdf->Rect($startX,      $startY, $colWidth, $headerH, 'F');
        $pdf->Rect($rightStartX, $startY, $colWidth, $headerH, 'F');

        // En-têtes colonne gauche
        $pdf->SetXY($startX, $startY);
        $pdf->Cell($moisW,    $headerH, 'Mois',          0, 0, 'L');
        if ($afficherDates) {
            $pdf->Cell($dateW, $headerH, 'Date paiement', 0, 0, 'R');
        }
        $pdf->Cell($montantW, $headerH, 'Montant',       0, 0, 'R');
        $pdf->Cell($etatW,    $headerH, 'État',          0, 0, 'R');

        // En-têtes colonne droite
        $pdf->SetXY($rightStartX, $startY);
        $pdf->Cell($moisW,    $headerH, 'Mois',          0, 0, 'L');
        if ($afficherDates) {
            $pdf->Cell($dateW, $headerH, 'Date paiement', 0, 0, 'R');
        }
        $pdf->Cell($montantW, $headerH, 'Montant',       0, 0, 'R');
        $pdf->Cell($etatW,    $headerH, 'État',          0, 0, 'R');

        $pdf->Ln($headerH);

        // ── Ligne séparatrice sous les en-têtes ───────────────────
        $yAfterHeader = $startY + $headerH;
        $pdf->SetLineWidth(0.3);
        $pdf->SetDrawColor(170, 170, 170);
        $pdf->Line($startX,      $yAfterHeader, $startX + $colWidth,      $yAfterHeader);
        $pdf->Line($rightStartX, $yAfterHeader, $rightStartX + $colWidth, $yAfterHeader);
        $pdf->SetLineWidth(0.2);
        $pdf->SetDrawColor(0, 0, 0);

        // ── Lignes de données ─────────────────────────────────────
        $pdf->SetFont($this->fontRegular, '', 9);
        $maxRows = max(count($leftCol), count($rightCol));

        for ($i = 0; $i < $maxRows; $i++) {
            $rowY = $startY + $headerH + ($i * $rowH);

            // ── Colonne gauche ─────────────────────────────────────
            if ($i < count($leftCol)) {
                $d = $leftCol[$i];
                $this->dessinerLigneMois($pdf, $d, $startX, $rowY,
                                          $moisW, $dateW, $montantW, $etatW, $rowH, $afficherDates);
            }

            // ── Séparateur vertical central ────────────────────────
            $pdf->SetLineWidth(0.2);
            $pdf->SetDrawColor(220, 220, 220);
            $sepX = $startX + $colWidth + ($sep / 2);
            $pdf->Line($sepX, $startY, $sepX, $startY + $headerH + ($maxRows * $rowH) + 1);
            $pdf->SetDrawColor(0, 0, 0);

            // ── Colonne droite ─────────────────────────────────────
            if ($i < count($rightCol)) {
                $d = $rightCol[$i];
                $this->dessinerLigneMois($pdf, $d, $rightStartX, $rowY,
                                          $moisW, $dateW, $montantW, $etatW, $rowH, $afficherDates);
            }

            // Ligne séparatrice entre les lignes (légère)
            $pdf->SetLineWidth(0.1);
            $pdf->SetDrawColor(235, 235, 235);
            $lineY = $rowY + $rowH;
            $pdf->Line($startX,      $lineY, $startX + $colWidth,      $lineY);
            $pdf->Line($rightStartX, $lineY, $rightStartX + $colWidth, $lineY);
            $pdf->SetDrawColor(0, 0, 0);
        }

        // ── Ligne totale sur toute la largeur ─────────────────────
        $totalY = $startY + $headerH + ($maxRows * $rowH) + 3;
        $pdf->SetY($totalY);

        // Ligne de séparation avant le total
        $pdf->SetLineWidth(0.4);
        $pdf->SetDrawColor(150, 150, 150);
        $pdf->Line($startX, $totalY, $startX + $this->frameWidth - 6, $totalY);
        $pdf->SetDrawColor(0, 0, 0);

        $pdf->SetY($totalY + 2);

        // Calculer le total et le nombre de mois payés
        $totalPaye  = array_sum(array_column($details, 'montant_paye'));
        $moisPayes  = count(array_filter($details, fn($d) => $d['est_paye']));
        $moisTotal  = count($details);

        $pdf->SetFont($this->fontRegular, '', 9);
        $pdf->SetX($startX);
        $pdf->Cell($this->frameWidth * 0.55, 6,
                   $moisPayes . ' mois payé' . ($moisPayes > 1 ? 's' : '') . ' sur ' . $moisTotal,
                   0, 0, 'L');

        $pdf->SetFont($this->fontBold, 'B', 10);
        $montantFormate = 'Total payé : ' . number_format($totalPaye, 2, '.', "'") . ' CHF';
        $totalW = $pdf->GetStringWidth($montantFormate);
        $pdf->SetXY($this->frameX + $this->frameWidth - 3 - $totalW - 3, $totalY + 2);
        $pdf->Cell($totalW + 3, 6, $montantFormate, 0, 1, 'R');

        $pdf->Ln(2);
    }

    /**
     * Dessine une ligne d'un mois dans le tableau.
     */
    private function dessinerLigneMois(MyFPDI $pdf, array $d,
                                        float $x, float $y,
                                        float $moisW, float $dateW,
                                        float $montantW, float $etatW, float $rowH,
                                        bool $afficherDates = true): void {
        $estPaye    = (bool)($d['est_paye'] ?? false);
        $partiel    = !$estPaye && floatval($d['montant_paye'] ?? 0) > 0;

        // Couleur du texte selon état
        if ($estPaye) {
            $pdf->SetTextColor(20, 120, 20);      // vert
        } elseif ($partiel) {
            $pdf->SetTextColor(180, 100, 0);      // orange
        } else {
            $pdf->SetTextColor(170, 170, 170);    // gris
        }

        $pdf->SetFont($this->fontRegular, '', 9);
        $pdf->SetXY($x, $y);

        // Mois
        $pdf->Cell($moisW, $rowH, $d['mois'] ?? '', 0, 0, 'L');

        // Date paiement (conditionnelle selon afficher_dates_paiement)
        if ($afficherDates) {
            $datePaiement = '';
            if ($estPaye && !empty($d['date_paiement'])) {
                $dt = DateTime::createFromFormat('Y-m-d', $d['date_paiement']);
                $datePaiement = $dt ? $dt->format('d.m.Y') : $d['date_paiement'];
            } elseif ($partiel) {
                $datePaiement = '—';
            }
            $pdf->Cell($dateW, $rowH, $datePaiement, 0, 0, 'R');
        }

        // Montant
        $montantPaye = floatval($d['montant_paye'] ?? 0);
        $montantStr  = $montantPaye > 0 ? number_format($montantPaye, 2, '.', "'") : '—';
        $pdf->Cell($montantW, $rowH, $montantStr, 0, 0, 'R');

        // Badge état (texte compact)
        $etatLabel = $estPaye ? 'Payé' : ($partiel ? 'Partiel' : 'Non payé');
        $pdf->Cell($etatW, $rowH, $etatLabel, 0, 0, 'R');

        // Remettre la couleur normale
        $pdf->SetTextColor(0, 0, 0);
    }
}