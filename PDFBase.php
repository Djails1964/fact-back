<?php

require_once 'PDFGeneratorInterface.php';

/**
 * PDFBase.php
 *
 * Classe de base FPDI partagée pour tous les générateurs de PDF du Centre La Grange.
 * Contient :
 *   - MyFPDI      : extension de tFPDF avec helpers graphiques
 *   - AbstractPDFGenerator : classe abstraite avec en-tête, pied de page,
 *                            fermeture de cadre, init PDF et fusion des pages
 *
 * Usage :
 *   class MonGenerateur extends AbstractPDFGenerator {
 *       protected function getTitrePrincipal(array $data): string { ... }
 *       protected function calculerNombrePages(array $data, array $params): int { ... }
 *       protected function genererContenu(MyFPDI $pdf, array $data, array $params, int $page): void { ... }
 *   }
 */

// ═══════════════════════════════════════════════════════════════════
//  MyFPDI — Extension tFPDF avec helpers graphiques et métriques
// ═══════════════════════════════════════════════════════════════════

class MyFPDI extends \tFPDF {

    public function __construct($orientation = 'P', $unit = 'mm', $size = 'A4') {
        parent::__construct($orientation, $unit, $size);
        $this->AddFont('DejaVu', '',  'DejaVuSans.ttf',        true);
        $this->AddFont('DejaVu', 'B', 'DejaVuSans-Bold.ttf',   true);
        $this->AddFont('DejaVu', 'I', 'DejaVuSans-Oblique.ttf', true);
    }

    // ── Compatibilité interne ──────────────────────────────────────
    protected function empty_string($str): bool {
        return is_null($str) || ($str === '');
    }

    // ── Métriques de texte ─────────────────────────────────────────
    public function getNumLines(string $text, float $width): int {
        $cw = $this->CurrentFont['cw'];
        if (!is_array($cw)) {
            $this->SetFont('Arial', '', 10);
            $cw = $this->CurrentFont['cw'];
            if (!is_array($cw)) return 1;
        }
        if ($width == 0) return 1;

        $wmax = $width * 1000 / $this->FontSizePt;
        $s    = str_replace("\r", '', $text);
        $nb   = strlen($s);
        if ($nb > 0 && $s[$nb - 1] == "\n") $nb--;

        $sep = -1; $i = 0; $j = 0; $l = 0.0; $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") { $i++; $sep = -1; $j = $i; $l = 0.0; $nl++; continue; }
            if ($c == ' ')  $sep = $i;
            $l += isset($cw[ord($c)]) ? floatval($cw[ord($c)]) : 0;
            if ($l > $wmax) {
                if ($sep == -1) { if ($i == $j) $i++; }
                else            $i = $sep + 1;
                $sep = -1; $j = $i; $l = 0; $nl++;
            } else {
                $i++;
            }
        }
        return $nl;
    }

    // ── Dessin ─────────────────────────────────────────────────────
    public function RoundedRect(float $x, float $y, float $w, float $h, float $r,
                                 string $corners = '1234', string $style = ''): void {
        $op    = ($style == 'F') ? 'f' : (($style == 'FD' || $style == 'DF') ? 'B' : 'S');
        $a     = 4 / 3 * (sqrt(2) - 1);
        $k     = $this->k;
        $hp    = $this->h;

        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));

        $xc = $x + $w - $r; $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
        if (strpos($corners, '2') === false)
            $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $y) * $k));
        else
            $this->_Arc($xc + $r * $a, $yc - $r, $xc + $r, $yc - $r * $a, $xc + $r, $yc);

        $xc = $x + $w - $r; $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
        if (strpos($corners, '3') === false)
            $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - ($y + $h)) * $k));
        else
            $this->_Arc($xc + $r, $yc + $r * $a, $xc + $r * $a, $yc + $r, $xc, $yc + $r);

        $xc = $x + $r; $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
        if (strpos($corners, '4') === false)
            $this->_out(sprintf('%.2F %.2F l', ($x) * $k, ($hp - ($y + $h)) * $k));
        else
            $this->_Arc($xc - $r * $a, $yc + $r, $xc - $r, $yc + $r * $a, $xc - $r, $yc);

        $xc = $x + $r; $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', ($x) * $k, ($hp - $yc) * $k));
        if (strpos($corners, '1') === false) {
            $this->_out(sprintf('%.2F %.2F l', ($x) * $k, ($hp - $y) * $k));
            $this->_out(sprintf('%.2F %.2F l', ($x + $r) * $k, ($hp - $y) * $k));
        } else {
            $this->_Arc($xc - $r, $yc - $r * $a, $xc - $r * $a, $yc - $r, $xc, $yc - $r);
        }
        $this->_out($op);
    }

    protected function _Arc(float $x1, float $y1, float $x2, float $y2,
                             float $x3, float $y3): void {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ',
            $x1 * $this->k, ($h - $y1) * $this->k,
            $x2 * $this->k, ($h - $y2) * $this->k,
            $x3 * $this->k, ($h - $y3) * $this->k));
    }

    // ── Accesseurs ─────────────────────────────────────────────────
    public function getMargins(): array {
        return ['left' => $this->lMargin, 'right' => $this->rMargin,
                'top'  => $this->tMargin, 'bottom' => $this->bMargin];
    }
    public function getPageWidth():  float { return $this->w; }
    public function getPageHeight(): float { return $this->h; }
    public function getPage():       int   { return $this->page; }
    public function getNumPages():   int   { return $this->page; }
    public function setPage(int $page): void {
        if ($page > 0 && $page <= $this->page) $this->page = $page;
    }

    // ── Placeholders (compatibilité) ───────────────────────────────
    public function SetAlpha(float $alpha, string $mode = 'Normal'): void {}
    public function startTransform(): void {}
    public function stopTransform():  void {}

    public function SetLineStyle(array $style): void {
        if (isset($style['width'])) $this->SetLineWidth($style['width']);
        if (isset($style['color'])) $this->SetDrawColor($style['color'][0], $style['color'][1], $style['color'][2]);
    }

    public function SetFontUtf8(string $family, string $style = '', int $size = 0): void {
        $this->SetFont($family, $style, $size);
    }
}


// ═══════════════════════════════════════════════════════════════════
//  AbstractPDFGenerator — Classe de base pour tous les PDF La Grange
// ═══════════════════════════════════════════════════════════════════

abstract class AbstractPDFGenerator implements PDFGeneratorInterface {

    // ── Polices (communes) ────────────────────────────────────────
    protected string $fontRegular = 'DejaVu';
    protected string $fontBold    = 'DejaVu';
    protected string $fontItalic  = 'DejaVu';

    // ── Dimensions du cadre (calculées dans initDimensionsCadre) ──
    protected float $frameX;
    protected float $frameWidth;
    protected float $textStartX;
    protected float $frameStartY = 0;
    protected bool  $frameOpen   = false;

    // ── Pagination ────────────────────────────────────────────────
    protected int $totalPages  = 1;
    protected int $currentPage = 1;


    // ═══════════════════════════════════════════════════════════════
    //  MÉTHODES ABSTRAITES — à implémenter dans chaque sous-classe
    // ═══════════════════════════════════════════════════════════════

    /**
     * Retourne le titre principal du document (ligne en gras sous les adresses).
     * Ex: "Facture no FAC-001 : Location de salle"
     *     "Confirmation de paiement : location d'un cabinet"
     */
    abstract protected function getTitrePrincipal(array $data): string;

    /**
     * Calcule le nombre total de pages nécessaires.
     * Pour les confirmations de loyer : toujours 1.
     * Pour les factures : dépend du nombre de lignes.
     */
    abstract protected function calculerNombrePages(array $data, array $params): int;

    /**
     * Génère le contenu spécifique au document dans le cadre.
     * Appelée une fois par page après l'en-tête.
     * Doit appeler $this->fermerCadre() et $this->genererPiedPage() sur la dernière page.
     *
     * @param MyFPDI $pdf       Objet PDF courant
     * @param array  $data      Données du document (facture ou loyer)
     * @param array  $params    Paramètres (relations bancaires, délai, signature…)
     * @param int    $page      Numéro de page courant (commence à 1)
     */
    abstract protected function genererContenu(MyFPDI $pdf, array $data, array $params, int $page): void;


    // ═══════════════════════════════════════════════════════════════
    //  MÉTHODES COMMUNES — partagées par toutes les sous-classes
    // ═══════════════════════════════════════════════════════════════

    /**
     * Initialise un objet PDF avec les réglages standards La Grange.
     * Marges 20mm, pas de saut automatique, police par défaut.
     */
    protected function initPDF(string $titre = ''): MyFPDI {
        $pdf = new MyFPDI('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->AddPage();
        $pdf->SetCreator('Centre La Grange', true);
        $pdf->SetAuthor('Centre La Grange', true);
        if ($titre) $pdf->SetTitle($titre, true);
        $pdf->SetMargins(20, 20, 20);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetFont($this->fontRegular, '', 12);
        return $pdf;
    }

    /**
     * Initialise les dimensions du cadre de contenu selon la largeur de page.
     * À appeler après initPDF(), avant tout dessin.
     */
    protected function initDimensionsCadre(MyFPDI $pdf): void {
        $margins          = $pdf->getMargins();
        $available        = $pdf->getPageWidth() - $margins['left'] - $margins['right'];
        $this->frameX     = $margins['left'];
        $this->frameWidth = $available;
        $this->textStartX = $this->frameX + 3;
    }

    /**
     * Génère l'en-tête commun : logo, date, adresse centre, adresse client, titre.
     *
     * @param MyFPDI $pdf
     * @param array  $data   Doit contenir : date_document, titre (Monsieur/Madame),
     *                       prenom, nom, rue, numero, code_postal, localite
     *                       + optionnel etat (pour tampon Payée/Annulée)
     */
    protected function genererEnTete(MyFPDI $pdf, array $data): void {
        // ── Logo ──────────────────────────────────────────────────
        $logoPath = __DIR__ . '/assets/logo.png';
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 20, 20, 30);
        }

        // ── Date (alignée à droite) ───────────────────────────────
        $pdf->SetFont($this->fontRegular, '', 12);
        $pdf->SetY(20);
        $dateText      = 'Corcelles-près-Payerne, le ' . $this->dateEnFrancais($data['date_document']);
        $pageWidth     = $pdf->getPageWidth();
        $rightMargin   = $pdf->getMargins()['right'];
        $dateWidth     = $pdf->GetStringWidth($dateText);
        $dateStartX    = $pageWidth - $rightMargin - $dateWidth;
        $pdf->SetXY($dateStartX, 20);
        $pdf->Cell($dateWidth, 5, $dateText, 0, 1, 'L');

        // ── Adresse du centre ─────────────────────────────────────
        $pdf->SetY(35);
        $pdf->Cell(120, 5, 'Route du Bornalet 16',      0, 1, 'L');
        $pdf->Cell(120, 5, '1562 Corcelles',             0, 1, 'L');
        $pdf->Cell(120, 5, 'centrelagrange@gmail.ch',    0, 1, 'L');
        $pdf->Ln(5);

        // ── Tampon statut (Payée / Annulée) — factures uniquement ─
        if (!empty($data['etat']) && in_array($data['etat'], ['Payée', 'Annulée'])) {
            $couleurs = ['Payée' => [0, 128, 0], 'Annulée' => [255, 0, 0]];
            $couleur  = $couleurs[$data['etat']];
            $pdf->SetFont($this->fontBold, 'B', 12);
            $pdf->SetTextColor($couleur[0], $couleur[1], $couleur[2]);
            $pdf->SetLineStyle(['width' => 0.5, 'color' => $couleur]);
            $pdf->RoundedRect(110, 35, 40, 7, 1, '1111', 'D');
            $text      = $data['etat'];
            $textWidth = $pdf->GetStringWidth($text);
            $pdf->SetXY(110 + (40 - $textWidth) / 2, 35);
            $pdf->Cell($textWidth, 7, $text, 0, 1, 'L');
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont($this->fontRegular, '', 12);
            $pdf->Ln(15);
        }

        // ── Adresse du client (alignée avec la date) ──────────────
        $clientX    = $dateStartX;
        $lineHeight = 5;
        if (!empty(trim($data['titre'] ?? ''))) {
            $pdf->SetX($clientX);
            $pdf->Cell(120, $lineHeight, $data['titre'], 0, 1, 'L');
        }
        $pdf->SetX($clientX);
        $pdf->Cell(120, $lineHeight, ($data['prenom'] ?? '') . ' ' . ($data['nom'] ?? ''), 0, 1, 'L');
        $pdf->SetX($clientX);
        $pdf->Cell(120, $lineHeight, ($data['rue'] ?? '') . ' ' . ($data['numero'] ?? ''), 0, 1, 'L');
        $pdf->SetX($clientX);
        $pdf->Cell(120, $lineHeight, '', 0, 1, 'L');
        $pdf->SetX($clientX);
        $pdf->Cell(120, $lineHeight, ($data['code_postal'] ?? '') . ' ' . ($data['localite'] ?? ''), 0, 1, 'L');

        // ── Titre du document ─────────────────────────────────────
        $pdf->Ln(10);
        $pdf->SetFont($this->fontBold, 'B', 12);
        $pdf->Cell(0, 5, $this->getTitrePrincipal($data), 0, 1, 'L');
        $pdf->Ln(5);
    }

    /**
     * Génère un en-tête allégé pour les pages 2+ (logo + date + titre uniquement).
     */
    protected function genererEnTetePageSuivante(MyFPDI $pdf, array $data): void {
        $logoPath = __DIR__ . '/assets/logo.png';
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 20, 20, 30);
        }
        $pdf->SetY(20);
        $pdf->Cell(0, 5, 'Corcelles-près-Payerne, le ' . $this->dateEnFrancais($data['date_document']), 0, 1, 'R');
        $pdf->SetY(40);
        $pdf->SetFont($this->fontBold, 'B', 12);
        $pdf->Cell(0, 5, $this->getTitrePrincipal($data), 0, 1, 'L');
        $pdf->Ln(5);
    }

    /**
     * Ferme le cadre de contenu avec une double bordure.
     *
     * @param MyFPDI $pdf
     * @param bool   $avecEspacement  Ajoute 5mm après le cadre si true
     */
    protected function fermerCadre(MyFPDI $pdf, bool $avecEspacement = true): void {
        $frameEndY = $pdf->GetY() + 7;
        $pdf->SetLineWidth(0.4);
        $pdf->SetDrawColor(150, 150, 150);
        $pdf->Rect($this->frameX, $this->frameStartY, $this->frameWidth,
                   $frameEndY - $this->frameStartY, 'D');
        $pdf->SetLineWidth(0.2);
        $pdf->SetDrawColor(180, 180, 180);
        $pdf->Rect($this->frameX + 1, $this->frameStartY + 1, $this->frameWidth - 2,
                   ($frameEndY - $this->frameStartY) - 2, 'D');
        if ($avecEspacement) {
            $pdf->SetY($frameEndY + 5);
            $pdf->SetFont($this->fontRegular, '', 12);
        }
    }

    /**
     * Génère le pied de page : coordonnées bancaires, remerciements, signature.
     *
     * @param MyFPDI $pdf
     * @param array  $banque         Clés : Banque, IBAN, Beneficiaire (CSV)
     * @param int    $delaiPaiement  0 = pas de ligne délai (confirmations loyer)
     * @param array  $signature      Clés : 'Ligne 1', 'Ligne 2'
     */
    protected function genererPiedPage(MyFPDI $pdf, array $banque = [],
                                       int $delaiPaiement = 0, array $signature = []): void {
        $beneficiaires = isset($banque['Beneficiaire'])
            ? array_map('trim', explode(',', $banque['Beneficiaire']))
            : ['Johanna Cherbuin', 'Chemin du Châtelard 9', '1562 Corcelles-près-Payerne'];

        // ── Hauteur estimée ───────────────────────────────────────
        $hauteur  = 0;
        if ($delaiPaiement > 0) $hauteur += 7;  // "Paiement net à X jours"
        $hauteur += 7;                            // "Montant total versé à :"
        $hauteur += 5;                            // Banque
        $hauteur += 5;                            // IBAN
        $hauteur += 5;                            // "Pour :"
        $hauteur += count($beneficiaires) * 5;   // lignes bénéficiaire
        $hauteur += 5;                            // espacement
        $hauteur += 7;                            // "Avec nos remerciements."
        $hauteur += 5;                            // espacement
        $hauteur += 7;                            // signature ligne 1
        $hauteur += 7;                            // signature ligne 2

        $startY = $pdf->getPageHeight() - $pdf->getMargins()['bottom'] - 15 - $hauteur;
        $pdf->SetY($startY);
        $pdf->SetFont($this->fontRegular, '', 12);

        // ── Délai (factures uniquement) ───────────────────────────
        if ($delaiPaiement > 0) {
            $pdf->Cell(0, 7, 'Paiement net à ' . $delaiPaiement . ' jours.', 0, 1, 'L');
        }

        // ── Coordonnées bancaires ─────────────────────────────────
        $pdf->Cell(0, 7, 'Montant total versé à :', 0, 1, 'L');
        $pdf->Cell(0, 5, $banque['Banque'] ?? 'BCV 1001 Lausanne',             0, 1, 'L');
        $pdf->Cell(0, 5, $banque['IBAN']   ?? 'CH 88 0076 7000 E536 2645 5',   0, 1, 'L');
        $pdf->Cell(0, 5, 'Pour :',                                              0, 1, 'L');
        foreach ($beneficiaires as $ligne) {
            $pdf->Cell(0, 5, $ligne, 0, 1, 'L');
        }
        $pdf->Ln(5);

        // ── Remerciements ─────────────────────────────────────────
        $pdf->Cell(0, 7, 'Avec nos remerciements.', 0, 1, 'L');
        $pdf->Ln(5);

        // ── Signature (droite) ────────────────────────────────────
        $sigX = 110;
        $pdf->SetX($sigX);
        $pdf->SetTextColor(128, 0, 32);
        $pdf->SetFont($this->fontItalic, 'I', 12);
        $pdf->Cell(0, 7, $signature['Ligne 1'] ?? 'Johanna Cherbuin', 0, 1, 'L');
        $pdf->SetX($sigX);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 7, $signature['Ligne 2'] ?? 'Centre « La Grange »', 0, 1, 'L');
        $pdf->SetFont($this->fontRegular, '', 12);
    }

    /**
     * Sauvegarde un PDF une page et fusionne si multi-pages.
     * Retourne true si succès.
     *
     * @param MyFPDI[] $pages       Tableau d'objets MyFPDI (un par page)
     * @param string   $fileName    Nom du fichier final (ex: "facture_001.pdf")
     */
    protected function sauvegarderPages(array $pages, string $fileName): bool {
        $tempDir   = factures_path('');
        $tempFiles = [];

        if (!is_writable($tempDir)) {
            error_log("❌ Dossier non accessible en écriture: $tempDir");
            return false;
        }

        // ── Enregistrement de chaque page ─────────────────────────
        foreach ($pages as $index => $pdf) {
            $tempFile = $tempDir . '/temp_pdf_page_' . uniqid() . '.pdf';
            try {
                set_error_handler(function($s, $m) { error_log("⚠️ Output: $m"); });
                $pdf->Output('F', $tempFile);
                restore_error_handler();
                if (file_exists($tempFile) && filesize($tempFile) > 0) {
                    $tempFiles[] = $tempFile;
                } else {
                    throw new Exception("Fichier vide ou non créé");
                }
            } catch (Exception $e) {
                error_log("❌ Page " . ($index + 1) . ": " . $e->getMessage());
                try {
                    $content = $pdf->Output('S');
                    if (strlen($content) > 0 && file_put_contents($tempFile, $content) !== false) {
                        $tempFiles[] = $tempFile;
                    } else {
                        $this->nettoyerFichiersTemp($tempFiles);
                        return false;
                    }
                } catch (Exception $e2) {
                    $this->nettoyerFichiersTemp($tempFiles);
                    return false;
                }
            }
        }

        if (empty($tempFiles)) return false;

        $fullPath = factures_path($fileName);

        // ── Une seule page : copie directe ────────────────────────
        if (count($tempFiles) === 1) {
            $ok = copy($tempFiles[0], $fullPath);
            $this->nettoyerFichiersTemp($tempFiles);
            return $ok;
        }

        // ── Multi-pages : fusion FPDI ─────────────────────────────
        try {
            $final = new \setasign\Fpdi\Fpdi();
            foreach ($tempFiles as $file) {
                $count = $final->setSourceFile($file);
                for ($i = 1; $i <= $count; $i++) {
                    $tpl = $final->importPage($i);
                    $final->AddPage();
                    $final->useTemplate($tpl);
                }
            }
            try {
                $final->Output('F', $fullPath);
            } catch (Exception $e) {
                file_put_contents($fullPath, $final->Output('S'));
            }
            $ok = file_exists($fullPath) && filesize($fullPath) > 0;
        } catch (Exception $e) {
            error_log("❌ Fusion FPDI: " . $e->getMessage());
            $ok = false;
        }

        $this->nettoyerFichiersTemp($tempFiles);
        return $ok;
    }

    /**
     * Ajoute la numérotation "Page X/Y" en bas à droite de chaque page.
     */
    protected function ajouterNumerotation(MyFPDI $pdf): void {
        $pdf->SetY(-15);
        $pdf->SetX(-30);
        $pdf->SetFont($this->fontRegular, '', 8);
        $pdf->Cell(20, 10, 'Page ' . $this->currentPage . '/' . $this->totalPages, 0, 0, 'R');
    }

    // ═══════════════════════════════════════════════════════════════
    //  UTILITAIRES
    // ═══════════════════════════════════════════════════════════════

    /**
     * Formate une date SQL (YYYY-MM-DD) en français lisible.
     * Ex: "2025-03-04" → "4 mars 2025"
     */
    protected function dateEnFrancais(string $date): string {
        $mois = [
            1 => 'janvier', 2 => 'février',  3 => 'mars',     4 => 'avril',
            5 => 'mai',     6 => 'juin',      7 => 'juillet',  8 => 'août',
            9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
        ];
        try {
            $d = new DateTime($date);
            return $d->format('j') . ' ' . $mois[(int)$d->format('n')] . ' ' . $d->format('Y');
        } catch (Exception $e) {
            return $date;
        }
    }

    /**
     * Supprime les fichiers temporaires.
     */
    private function nettoyerFichiersTemp(array $files): void {
        foreach ($files as $file) {
            if (file_exists($file)) unlink($file);
        }
    }
}