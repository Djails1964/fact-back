<?php

/**
 * FPDILoyerConfirmationGenerator.php
 *
 * Générateur PDF pour les CONFIRMATIONS DE PAIEMENT DE LOYER.
 * Étend AbstractPDFGenerator (PDFBase.php) pour réutiliser :
 *   - genererEnTete()      → logo, date, adresse centre + client, titre
 *   - genererPiedPage()    → coordonnées bancaires, remerciements, signature
 *   - fermerCadre()        → double bordure du cadre
 *   - dateEnFrancais()     → formatage des dates
 *   - sauvegarderPages()   → écriture et fusion PDF
 *
 * Données attendues dans $loyer :
 *   // Champs client (pour l'en-tête)
 *   titre, prenom, nom, rue, numero, code_postal, localite
 *
 *   // Champs loyer
 *   numero_loyer, motif, periode_debut, periode_fin, duree_mois
 *   date_confirmation  (date du document, format Y-m-d)
 *
 *   // Détails mensuels
 *   details : array de [
 *     mois       (ex: "Janvier 2025"),
 *     montant    (decimal),
 *     est_paye   (0|1),
 *     date_paiement (Y-m-d|null),
 *     montant_paye  (decimal, somme des paiements confirmés)
 *   ]
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
     * Génère le contenu spécifique : infos loyer + tableau mensuel 2 colonnes.
     */
    protected function genererContenu(MyFPDI $pdf, array $data, array $params, int $page): void {
        $this->frameStartY = $pdf->GetY();
        $this->frameOpen   = true;

        $afficherDates = (bool)($data['afficher_dates_paiement'] ?? true);

        $this->genererInfosLoyer($pdf, $data);
        $this->genererSeparateur($pdf);
        $this->genererTableau2Colonnes($pdf, $data['details'] ?? [], $afficherDates);

        $this->fermerCadre($pdf, true);

        $banque    = $params['banque']    ?? [];
        $signature = $params['signature'] ?? [];
        // Confirmation loyer : pas de délai de paiement (0)
        $this->genererPiedPage($pdf, $banque, 0, $signature);
    }


    // ═══════════════════════════════════════════════════════════════
    //  POINT D'ENTRÉE PUBLIC  (implémente PDFGeneratorInterface)
    // ═══════════════════════════════════════════════════════════════

    /**
     * @param array  $loyer       Données loyer + détails + client
     * @param string $pdfFileName Nom du fichier (ex: "confirmation_LOY-3-001.pdf")
     * @param string $outputDir   Ignoré (conservé pour compatibilité interface)
     * @param array  $banque      Paramètres bancaires (Banque, IBAN, Beneficiaire)
     * @param int    $delai       Ignoré (pas de délai sur une confirmation)
     * @param array  $signature   Paramètres signature (Ligne 1, Ligne 2)
     */
    public function genererPDF(
        $loyer,
        $pdfFileName,
        $outputDir          = null,
        $banque             = [],
        $delaiPaiement      = 0,
        $signature          = [],
        $printRistourne     = false
    ) {
        // $delaiPaiement et $printRistourne ignorés pour les confirmations de loyer
        error_log("📄 LoyerConfirmationGenerator — génération: $pdfFileName");

        // ── Normaliser les données depuis getLoyerParId ───────────────────────
        $loyer = $this->normaliserDonnees($loyer);

        $params = ['banque' => $banque, 'signature' => $signature];
        $this->totalPages  = 1;
        $this->currentPage = 1;

        // Initialiser le PDF
        $titre = $this->getTitrePrincipal($loyer) . ' — ' . ($loyer['numero_loyer'] ?? '');
        $pdf   = $this->initPDF($titre);
        $this->initDimensionsCadre($pdf);

        // En-tête commun
        $this->genererEnTete($pdf, $loyer);

        // Contenu spécifique loyer
        $this->genererContenu($pdf, $loyer, $params, 1);

        // Numérotation (1/1)
        $this->ajouterNumerotation($pdf);

        return $this->sauvegarderPages([$pdf], $pdfFileName);
    }


    // ═══════════════════════════════════════════════════════════════
    //  MÉTHODES PRIVÉES — contenu spécifique loyer
    // ═══════════════════════════════════════════════════════════════

    /**
     * Normalise les données reçues de LoyerControleur::getLoyerParId
     * vers le format interne attendu par le générateur.
     *
     * getLoyerParId retourne :
     *   - prenom_client, nom_client, titre_client (au lieu de prenom, nom, titre)
     *   - rue_client, numero_client, code_postal_client, localite_client
     *   - montants_mensuels[] avec : loyer_mois, loyer_detail_montant, loyer_numero_mois
     *     et paiements[] contenant les paiements effectués
     */
    private function normaliserDonnees(array $loyer): array {
        // ── Champs client ─────────────────────────────────────────────────────
        if (!isset($loyer['prenom'])) {
            $loyer['prenom'] = $loyer['prenom_client'] ?? '';
        }
        if (!isset($loyer['nom'])) {
            $loyer['nom'] = $loyer['nom_client'] ?? '';
        }
        if (!isset($loyer['titre'])) {
            $loyer['titre'] = $loyer['titre_client'] ?? '';
        }
        if (!isset($loyer['rue'])) {
            $loyer['rue'] = $loyer['rue_client'] ?? '';
        }
        if (!isset($loyer['numero'])) {
            $loyer['numero'] = $loyer['numero_client'] ?? '';
        }
        if (!isset($loyer['code_postal'])) {
            $loyer['code_postal'] = $loyer['code_postal_client'] ?? '';
        }
        if (!isset($loyer['localite'])) {
            $loyer['localite'] = $loyer['localite_client'] ?? '';
        }

        // ── Date du document ──────────────────────────────────────────────────
        if (!isset($loyer['date_document'])) {
            $loyer['date_document'] = $loyer['date_confirmation']
                ?? $loyer['date_creation_loyer']
                ?? date('Y-m-d');
        }

        // ── Normaliser les détails mensuels ───────────────────────────────────
        // getLoyerParId stocke sous 'montants_mensuels' avec des alias différents
        $source = $loyer['details'] ?? $loyer['montants_mensuels'] ?? [];

        $details = [];
        foreach ($source as $d) {
            // Calculer montant_paye depuis la liste des paiements associés
            $montantPaye = 0;
            if (!empty($d['paiements']) && is_array($d['paiements'])) {
                foreach ($d['paiements'] as $p) {
                    $montantPaye += floatval($p['montant_paye'] ?? 0);
                }
            } elseif (isset($d['montant_paye'])) {
                $montantPaye = floatval($d['montant_paye']);
            }

            $details[] = [
                'id_loyer_detail' => $d['id_loyer_detail'] ?? null,
                // Nom du mois : loyer_mois (alias getLoyerParId) ou mois
                'mois'           => $d['loyer_mois']         ?? $d['mois']    ?? '',
                'numero_mois'    => $d['loyer_numero_mois']  ?? $d['numero_mois'] ?? 0,
                'annee'          => $d['loyer_annee']         ?? $d['annee']   ?? '',
                // Montant DÛ : loyer_detail_montant (alias getLoyerParId) ou montant
                'montant'        => floatval($d['loyer_detail_montant'] ?? $d['montant'] ?? 0),
                'est_paye'       => (bool)($d['est_paye'] ?? false),
                'date_paiement'  => $d['date_paiement'] ?? null,
                'montant_paye'   => $montantPaye,
            ];
        }
        $loyer['details'] = $details;

        error_log("📊 normaliserDonnees: " . count($details) . " mois, client: {$loyer['prenom']} {$loyer['nom']}");

        return $loyer;
    }

    /**
     * Bloc d'informations générales du loyer (4 lignes + optionnel statut).
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

        // Période
        $debut = isset($data['periode_debut']) ? $this->dateEnFrancais($data['periode_debut']) : '';
        $fin   = isset($data['periode_fin'])   ? $this->dateEnFrancais($data['periode_fin'])   : '';
        $pdf->Cell($labelW, $lineH, 'Période :', 0, 0, 'L');
        $pdf->Cell($valW,   $lineH, 'Du ' . $debut . ' au ' . $fin, 0, 1, 'L');
        $pdf->SetX($startX);

        // Durée
        $duree = ($data['duree_mois'] ?? 12) . ' mois';
        $pdf->Cell($labelW, $lineH, 'Durée :', 0, 0, 'L');
        $pdf->Cell($valW,   $lineH, $duree, 0, 1, 'L');
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