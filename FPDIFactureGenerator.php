<?php

require_once 'PDFGeneratorInterface.php';

/**
 * FacturePDFGenerator.php
 * 
 * Classe responsable de la génération de fichiers PDF pour les factures
 * du Centre La Grange. Cette classe utilise la bibliothèque FPDI.
 * Gestion des sauts de page améliorée pour les factures sur plusieurs pages.
 */

 class MyFPDI extends \tFPDF {
    
    public function __construct($orientation='P', $unit='mm', $size='A4') {
        parent::__construct($orientation, $unit, $size);
        
        // Configuration UTF-8
        $this->AddFont('DejaVu','','DejaVuSans.ttf',true);
        $this->AddFont('DejaVu','B','DejaVuSans-Bold.ttf',true);
        $this->AddFont('DejaVu','I','DejaVuSans-Oblique.ttf',true);
    }
    
    // Ajouter la méthode empty_string manquante
    protected function empty_string($str) {
        return (is_null($str) || ($str === ''));
    }
    
    public function getNumLines($text, $width) {
        // Obtention de la largeur de chaque caractère dans la police actuelle
        $cw = $this->CurrentFont['cw'];
        if ($width == 0) {
            return 1;
        }
        
        $wmax = $width * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $text);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb-1] == "\n") {
            $nb--;
        }
        
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0.0;
        $nl = 1;
        
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0.0;
                $nl++;
                continue;
            }
            if ($c == ' ') {
                $sep = $i;
            }
            $l += isset($cw[ord($c)]) ? floatval($cw[ord($c)]) : 0;
            if ($l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j) {
                        $i++;
                    }
                } else {
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            } else {
                $i++;
            }
        }
        return $nl;
    }

    // Méthode pour dessiner un rectangle aux coins arrondis
    public function RoundedRect($x, $y, $w, $h, $r, $corners='1234', $style='') {
        $k = $this->k;
        $hp = $this->h;
        if($style=='F')
            $op='f';
        elseif($style=='FD' || $style=='DF')
            $op='B';
        else
            $op='S';
        $MyArc = 4/3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m', ($x+$r)*$k, ($hp-$y)*$k));
        
        $xc = $x+$w-$r;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l', $xc*$k, ($hp-$y)*$k));
        
        if (strpos($corners, '2')===false)
            $this->_out(sprintf('%.2F %.2F l', ($x+$w)*$k, ($hp-$y)*$k));
        else
            $this->_Arc($xc + $r*$MyArc, $yc - $r, $xc + $r, $yc - $r*$MyArc, $xc + $r, $yc);
        
        $xc = $x+$w-$r;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l', ($x+$w)*$k, ($hp-$yc)*$k));
        
        if (strpos($corners, '3')===false)
            $this->_out(sprintf('%.2F %.2F l', ($x+$w)*$k, ($hp-($y+$h))*$k));
        else
            $this->_Arc($xc + $r, $yc + $r*$MyArc, $xc + $r*$MyArc, $yc + $r, $xc, $yc + $r);
        
        $xc = $x+$r;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l', $xc*$k, ($hp-($y+$h))*$k));
        
        if (strpos($corners, '4')===false)
            $this->_out(sprintf('%.2F %.2F l', ($x)*$k, ($hp-($y+$h))*$k));
        else
            $this->_Arc($xc - $r*$MyArc, $yc + $r, $xc - $r, $yc + $r*$MyArc, $xc - $r, $yc);
        
        $xc = $x+$r;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l', ($x)*$k, ($hp-$yc)*$k));
        
        if (strpos($corners, '1')===false)
        {
            $this->_out(sprintf('%.2F %.2F l', ($x)*$k, ($hp-$y)*$k));
            $this->_out(sprintf('%.2F %.2F l', ($x+$r)*$k, ($hp-$y)*$k));
        }
        else
            $this->_Arc($xc - $r, $yc - $r*$MyArc, $xc - $r*$MyArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }
    
    protected function _Arc($x1, $y1, $x2, $y2, $x3, $y3) {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', $x1*$this->k, ($h-$y1)*$this->k,
            $x2*$this->k, ($h-$y2)*$this->k, $x3*$this->k, ($h-$y3)*$this->k));
    }
    
    // Méthode pour obtenir les marges
    public function getMargins() {
        return array(
            'left' => $this->lMargin,
            'right' => $this->rMargin,
            'top' => $this->tMargin,
            'bottom' => $this->bMargin
        );
    }
    
    // Méthode pour obtenir la largeur de la page
    public function getPageWidth() {
        return $this->w;
    }
    
    // Méthode pour obtenir la hauteur de la page
    public function getPageHeight() {
        return $this->h;
    }
    
    // Méthode pour obtenir le numéro de page actuel
    public function getPage() {
        return $this->page;
    }
    
    // Méthode pour obtenir le nombre total de pages
    public function getNumPages() {
        return $this->page;
    }
    
    // Méthode pour définir la page actuelle
    public function setPage($page) {
        if ($page > 0 && $page <= $this->page) {
            $this->page = $page;
        }
    }
    
    // Méthode pour simuler l'opacité
    public function SetAlpha($alpha, $mode='Normal') {
        // FPDI n'a pas de support natif pour l'opacité
        // Cette méthode est un placeholder pour compatibilité
    }
    
    // Méthodes pour transformer (non supportées dans FPDI de base)
    public function startTransform() {
        // Placeholder
    }
    
    public function stopTransform() {
        // Placeholder
    }
    
    // Méthode pour définir le style de ligne
    public function SetLineStyle($style) {
        if (isset($style['width'])) {
            $this->SetLineWidth($style['width']);
        }
        if (isset($style['color'])) {
            $this->SetDrawColor($style['color'][0], $style['color'][1], $style['color'][2]);
        }
    }
    
    // FPDI utilise SetFont sans paramètre UTF-8 spécifique
    public function SetFontUtf8($family, $style='', $size=0) {
        $this->SetFont($family, $style, $size);
    }
}

class FPDIFactureGenerator implements PDFGeneratorInterface {
    private $fontRegular;
    private $fontBold;
    private $fontItalic;
    
    // Variables pour la gestion des sauts de page
    private $totalPages = 1;
    private $currentPage = 1;
    private $montantReport = 0;
    private $frameStartY = 0;
    private $frameOpen = false;
    
    // Variables pour les dimensions du tableau de détails
    private $descriptionWidth;
    private $uniteWidth;
    private $quantiteWidth;
    private $prixWidth;
    private $totalWidth;
    private $textStartX;
    private $frameWidth;
    private $frameX;
    
    // Hauteur du pied de page (estimation par défaut, recalculée dynamiquement)
    private $hauteurPiedPage = 100; // en mm
    
    public function __construct() {
        $fontPath = realpath(__DIR__ . '/vendor/setasign/tfpdf/font/unifont');
        if (file_exists($fontPath)) {
            $this->fontRegular = $fontPath;
        } else {
            // Fallback to a default font or log an error
            error_log("DejaVu font not found: " . $fontPath);
        }
        // On peut utiliser des polices standards ou ajouter des polices personnalisées
        // Utiliser DejaVu qui a un bon support UTF-8
        $this->fontRegular = 'DejaVu';
        $this->fontBold = 'DejaVu';
        $this->fontItalic = 'DejaVu';
        
        // Pour utiliser des polices personnalisées comme DejaVu, il faut les ajouter à FPDI
        // Cela nécessite une configuration supplémentaire
    }

    /**
     * Génère un fichier PDF pour une facture
     * 
     * @param array $facture Données de la facture et ses lignes
     * @param string $pdfFileName Nom du fichier PDF à générer
     * @param string $outputDir Répertoire de sortie pour le PDF (obsolète, maintenu pour compatibilité)
     * @param array $relationsBancaires Paramètres des coordonnées bancaires
     * @param int $delaiPaiement Délai de paiement en jours
     * @return bool Retourne true si la génération a réussi
     */
    
    public function genererPDF($facture, $pdfFileName, $outputDir = null, $relationsBancaires = [], $delaiPaiement = 30, $signature = [], $printRistourne = false) {
        
        // Debug : vérifier les classes disponibles
        error_log("🔍 Classes FPDI disponibles:");
        error_log("- class_exists('FPDI'): " . (class_exists('FPDI') ? 'OUI' : 'NON'));
        error_log("- class_exists('setasign\\Fpdi\\Fpdi'): " . (class_exists('setasign\\Fpdi\\Fpdi') ? 'OUI' : 'NON'));
        error_log("- class_exists('Fpdi'): " . (class_exists('Fpdi') ? 'OUI' : 'NON'));
        
        // Obtenir les lignes de la facture
        $lignes = $facture['lignes'];
        $nombreLignes = count($lignes);

        error_log("genererPDF - lignes facture reçues: " . json_encode($lignes));
        
        // Utiliser le calcul simple pour le moment, en attendant l'implémentation des nouvelles méthodes
        $maxLignesParPage = 4; // Valeur de sécurité
        
        // Si les nouvelles méthodes existent, les utiliser
        if (method_exists($this, 'calculerNombreTotalPages')) {
            try {
                $this->totalPages = $this->calculerNombreTotalPages($facture, $relationsBancaires);
                error_log("🔢 Nombre total de pages calculé dynamiquement: " . $this->totalPages);
            } catch (Exception $e) {
                error_log("⚠️ Erreur calcul dynamique, utilisation méthode simple: " . $e->getMessage());
                $this->totalPages = ceil($nombreLignes / $maxLignesParPage);
            }
        } else {
            // Méthode simple de calcul
            $this->totalPages = ceil($nombreLignes / $maxLignesParPage);
            error_log("🔢 Nombre total de pages calculé simplement: " . $this->totalPages);
        }
        
        // Tableau pour stocker les chemins des PDF temporaires
        $tempPDFFiles = [];
        
        // Montant cumulé pour les reports
        $montantReport = 0;
        $lignesTraitees = 0;
        
        // Traiter chaque page
        for ($pageActuelle = 1; $pageActuelle <= $this->totalPages; $pageActuelle++) {
            error_log("🔄 === GÉNÉRATION PAGE $pageActuelle/{$this->totalPages} ===");
            
            // Créer un nouveau PDF pour cette page
            $pdf = new MyFPDI('P', 'mm', 'A4', true, 'UTF-8');
            $pdf->AddPage();
            
            // Configurer le document
            $pdf->SetCreator('Centre La Grange');
            $pdf->SetAuthor('Centre La Grange');
            $pdf->SetTitle('Facture ' . $facture['numero_facture'] . ' - Page ' . $pageActuelle);
            
            // Définir les marges uniformes à 20mm
            $pdf->SetMargins(20, 20, 20);
            $pdf->SetAutoPageBreak(false);
            
            // Calculer les dimensions du cadre
            $pageWidth = $pdf->getPageWidth();
            $leftMargin = $pdf->getMargins()['left'];
            $rightMargin = $pdf->getMargins()['right'];
            $availableWidth = $pageWidth - $leftMargin - $rightMargin;
            
            $this->frameX = $leftMargin;
            $this->frameWidth = $availableWidth;
            $this->textStartX = $this->frameX + 3;
            
            // Définir les largeurs des colonnes
            $this->uniteWidth = $this->frameWidth * 0.12;
            $this->quantiteWidth = $this->frameWidth * 0.06;
            $this->prixWidth = $this->frameWidth * 0.12;
            $this->totalWidth = $this->frameWidth * 0.16;
            $this->descriptionWidth = $this->frameWidth - (
                $this->uniteWidth + 
                $this->quantiteWidth + 
                $this->prixWidth + 
                $this->totalWidth
            );
            
            // Définir la police
            $pdf->SetFont($this->fontRegular, '', 12);
            
            // Générer l'en-tête approprié pour cette page
            error_log("Génération de l'en-tête pour la page $pageActuelle de {$this->totalPages}");
            if ($pageActuelle == 1) {
                $this->genererEnTete($pdf, $facture);
            } else {
                $this->genererEnTetePageSuivante($pdf, $facture);
            }
            error_log("En-tête généré pour la page $pageActuelle");
            
            // Position actuelle après l'en-tête
            $startY = $pdf->GetY();
            
            // Ouvrir le cadre
            $this->frameStartY = $startY;
            $this->frameOpen = true;
            
            // Ajouter la ligne "Responsable"
            $pdf->SetXY($this->textStartX, $this->frameStartY + 2);
            $pdf->SetFont($this->fontRegular, '', 10);
            $pdf->Cell(0, 7, 'Responsable : ' . $facture['prenom'] . ' ' . $facture['nom'], 0, 1, 'L');
            
            // En-têtes des colonnes
            error_log("Génération des en-têtes de colonnes pour la page $pageActuelle");
            $this->genererEnTetesColonnes($pdf);
            error_log("En-têtes de colonnes générés pour la page $pageActuelle");
            
            // Si ce n'est pas la première page, afficher le montant reporté
            if ($pageActuelle > 1) {
                $montantFormate = number_format($montantReport, 2, '.', "'");
                $pdf->SetFont($this->fontRegular, '', 10);
                $pdf->SetX($this->textStartX);
                $pdf->Cell($this->descriptionWidth, 7, 'Montant reporté :', 0, 0, 'R');
                $pdf->Cell($this->uniteWidth, 7, '', 0, 0, 'R');
                $pdf->Cell($this->quantiteWidth, 7, '', 0, 0, 'R');
                $pdf->Cell($this->prixWidth, 7, '', 0, 0, 'R');
                
                $totalXPosition = $this->frameX + $this->frameWidth - 3 - $pdf->GetStringWidth($montantFormate);
                $currentY = $pdf->GetY();
                $pdf->SetXY($totalXPosition, $currentY);
                $pdf->Cell($pdf->GetStringWidth($montantFormate), 7, $montantFormate, 0, 1, 'R');
                $pdf->Ln(2);
            }
            
            // ✅ LOGIQUE CORRIGÉE : Calculer les lignes pour cette page
            $lignesRestantes = array_slice($lignes, $lignesTraitees);
            $nombreLignesRestantes = count($lignesRestantes);
            
            error_log("📋 Page $pageActuelle - Lignes restantes à traiter: $nombreLignesRestantes");
            error_log("📋 Page $pageActuelle - Lignes déjà traitées: $lignesTraitees");
            
            // Déterminer si c'est vraiment la dernière page
            $estVraimentDernierePage = ($lignesTraitees + $nombreLignesRestantes == $nombreLignes);
            
            if ($estVraimentDernierePage) {
                // C'est potentiellement la dernière page, calculer avec les totaux
                if (method_exists($this, 'calculerEspaceDisponible') && method_exists($this, 'calculerNombreLignesQuiTiennent')) {
                    try {
                        $espaceDisponible = $this->calculerEspaceDisponible($pdf, $facture, $relationsBancaires, ($pageActuelle == 1));
                        $espaceRestantApresEnTetes = $espaceDisponible - ($pdf->GetY() - $startY);
                        $lignesQuiTiennent = $this->calculerNombreLignesQuiTiennent($pdf, $lignesRestantes, $espaceRestantApresEnTetes, true);
                        
                        error_log("📋 Page $pageActuelle (FINALE TENTATIVE): {$lignesQuiTiennent} lignes avec totaux");
                        
                        // Vérifier si toutes les lignes tiennent vraiment
                        if ($lignesQuiTiennent < $nombreLignesRestantes) {
                            error_log("⚠️ CORRECTION: Toutes les lignes ne tiennent pas, ce n'est pas la dernière page");
                            $estVraimentDernierePage = false;
                            $lignesQuiTiennent = $this->calculerNombreLignesQuiTiennent($pdf, $lignesRestantes, $espaceRestantApresEnTetes, false);
                            error_log("📋 Page $pageActuelle (INTERMÉDIAIRE): {$lignesQuiTiennent} lignes sans totaux");
                        }
                    } catch (Exception $e) {
                        error_log("⚠️ Erreur calcul dynamique lignes, utilisation méthode simple: " . $e->getMessage());
                        $lignesQuiTiennent = min($maxLignesParPage, $nombreLignesRestantes);
                    }
                } else {
                    // Méthode simple
                    $lignesQuiTiennent = min($maxLignesParPage, $nombreLignesRestantes);
                    error_log("📋 Page $pageActuelle: calcul simple - {$lignesQuiTiennent} lignes");
                }
            } else {
                // Page intermédiaire
                if (method_exists($this, 'calculerEspaceDisponible') && method_exists($this, 'calculerNombreLignesQuiTiennent')) {
                    try {
                        $espaceDisponible = $this->calculerEspaceDisponible($pdf, $facture, $relationsBancaires, ($pageActuelle == 1));
                        $espaceRestantApresEnTetes = $espaceDisponible - ($pdf->GetY() - $startY);
                        $lignesQuiTiennent = $this->calculerNombreLignesQuiTiennent($pdf, $lignesRestantes, $espaceRestantApresEnTetes, false);
                        
                        error_log("📋 Page $pageActuelle (INTERMÉDIAIRE): {$lignesQuiTiennent} lignes sans totaux");
                    } catch (Exception $e) {
                        error_log("⚠️ Erreur calcul dynamique, utilisation méthode simple: " . $e->getMessage());
                        $lignesQuiTiennent = min($maxLignesParPage, $nombreLignesRestantes);
                    }
                } else {
                    // Méthode simple
                    $lignesQuiTiennent = min($maxLignesParPage, $nombreLignesRestantes);
                    error_log("📋 Page $pageActuelle: calcul simple - {$lignesQuiTiennent} lignes");
                }
            }
            
            // Calculer l'index de fin pour les lignes de cette page
            $endIndex = min($lignesTraitees + $lignesQuiTiennent - 1, $nombreLignes - 1);
            
            error_log("📋 Page $pageActuelle - Index fin: $endIndex (ligne " . ($endIndex + 1) . ")");
            
            // Générer les lignes pour cette page
            error_log("Génération des lignes de détail pour la page $pageActuelle (lignes " . ($lignesTraitees + 1) . " à " . ($endIndex + 1) . ")");
            for ($i = $lignesTraitees; $i <= $endIndex; $i++) {
                $this->genererLigneDetail($pdf, $lignes[$i]);
                $montantReport += floatval($lignes[$i]['total_ligne']);
            }
            error_log("Lignes de détail générées pour la page $pageActuelle");
            
            // Mettre à jour le nombre de lignes traitées
            $lignesTraitees = $endIndex + 1;
            
            error_log("📋 Page $pageActuelle - Lignes traitées maintenant: $lignesTraitees");
            error_log("📋 Page $pageActuelle - Est vraiment dernière page: " . ($estVraimentDernierePage ? 'OUI' : 'NON'));
            
            // Gestion de la fin de page
            if ($estVraimentDernierePage && $lignesTraitees >= $nombreLignes) {
                // Générer les totaux finaux
                error_log("✅ Page $pageActuelle - Génération des totaux finaux");
                $this->genererLignesTotal($pdf, $facture, $printRistourne);
                error_log("Totaux générés pour la page $pageActuelle");
                
                // Fermer le cadre
                error_log("Fermeture du cadre pour la page $pageActuelle");
                $this->fermerCadre($pdf, true);
                error_log("Cadre fermé pour la page $pageActuelle");
                
                // Générer le pied de page
                error_log("Génération du pied de page pour la page $pageActuelle");
                $this->genererPiedPage($pdf, $relationsBancaires, $delaiPaiement, $signature);
                error_log("Pied de page généré pour la page $pageActuelle");
            } else {
                // Page intermédiaire - Ajouter "Montant à reporter"
                error_log("➡️ Page $pageActuelle - Génération du report");
                $montantFormate = number_format($montantReport, 2, '.', "'");
                $pdf->SetX($this->textStartX);
                $pdf->SetFont($this->fontRegular, '', 10);
                $pdf->Cell($this->descriptionWidth, 7, 'Montant à reporter :', 0, 0, 'R');
                $pdf->Cell($this->uniteWidth, 7, '', 0, 0, 'R');
                $pdf->Cell($this->quantiteWidth, 7, '', 0, 0, 'R');
                $pdf->Cell($this->prixWidth, 7, '', 0, 0, 'R');
                
                $totalXPosition = $this->frameX + $this->frameWidth - 3 - $pdf->GetStringWidth($montantFormate);
                $currentY = $pdf->GetY();
                $pdf->SetXY($totalXPosition, $currentY);
                $pdf->Cell($pdf->GetStringWidth($montantFormate), 7, $montantFormate, 0, 1, 'R');
                
                // Fermer le cadre
                $this->fermerCadre($pdf, false);
            }
            
            // Ajouter la numérotation des pages
            $pdf->SetY(-15);
            $pdf->SetX(-30);
            $pdf->SetFont($this->fontRegular, '', 8);
            $pdf->Cell(20, 10, 'Page ' . $pageActuelle . '/' . $this->totalPages, 0, 0, 'R');
            
            // Sauvegarder cette page dans un fichier temporaire
            error_log("📄 Enregistrement du PDF temporaire pour la page $pageActuelle");

            $tempDir = factures_path('');
            if (!is_writable($tempDir)) {
                error_log("❌ Dossier non accessible en écriture: " . $tempDir);
                return false;
            }

            $tempFile = $tempDir . '/temp_facture_page_' . uniqid() . '.pdf';

            try {
                set_error_handler(function($severity, $message, $file, $line) {
                    error_log("⚠️ Erreur PHP pendant Output: $message dans $file:$line");
                });
                
                $result = $pdf->Output('F', $tempFile);
                restore_error_handler();
                
                if (file_exists($tempFile) && filesize($tempFile) > 0) {
                    error_log("✅ Output direct réussi, taille: " . filesize($tempFile) . " bytes");
                    $tempPDFFiles[] = $tempFile;
                } else {
                    throw new Exception("Fichier PDF vide ou non créé");
                }
                
            } catch (Exception $e) {
                error_log("❌ Erreur Output direct: " . $e->getMessage());
                
                try {
                    $pdfContent = $pdf->Output('S');
                    if (strlen($pdfContent) > 0) {
                        $bytesWritten = file_put_contents($tempFile, $pdfContent);
                        if ($bytesWritten !== false) {
                            error_log("✅ Sauvegarde alternative réussie, " . $bytesWritten . " bytes écrits");
                            $tempPDFFiles[] = $tempFile;
                        } else {
                            error_log("❌ file_put_contents a échoué");
                            return false;
                        }
                    } else {
                        error_log("❌ PDF string vide généré");
                        return false;
                    }
                } catch (Exception $e2) {
                    error_log("❌ Erreur méthode alternative: " . $e2->getMessage());
                    return false;
                }
            }
            
            error_log("📄 Page $pageActuelle sauvegardée");
        }
        
        // Fusionner tous les PDF en un seul
        error_log("🔄 Début de la fusion des fichiers PDF temporaires");
        error_log("🔄 Nombre de fichiers à fusionner: " . count($tempPDFFiles));

        if (empty($tempPDFFiles)) {
            error_log("❌ Aucun fichier temporaire à fusionner");
            return false;
        }

        foreach ($tempPDFFiles as $index => $file) {
            if (!file_exists($file)) {
                error_log("❌ Fichier temporaire manquant: " . $file);
                return false;
            }
            $size = filesize($file);
            error_log("📄 Fichier " . ($index + 1) . ": " . basename($file) . " (" . $size . " bytes)");
            if ($size == 0) {
                error_log("❌ Fichier temporaire vide détecté: " . $file);
                return false;
            }
        }

        $fullPath = factures_path($pdfFileName);
        error_log("🎯 Chemin du fichier PDF final: " . $fullPath);

        // Si une seule page, copier directement
        if (count($tempPDFFiles) == 1) {
            error_log("📄 Une seule page détectée, copie directe");
            
            if (copy($tempPDFFiles[0], $fullPath)) {
                error_log("✅ Copie directe réussie: " . $fullPath);
                error_log("📊 Taille finale: " . filesize($fullPath) . " bytes");
                
                // Nettoyer
                foreach ($tempPDFFiles as $file) {
                    if (file_exists($file)) {
                        unlink($file);
                        error_log("🗑️ Fichier temporaire supprimé: " . $file);
                    }
                }
                
                return true;
            } else {
                error_log("❌ Échec de la copie directe");
                return false;
            }
        }

        // Pour plusieurs pages, utiliser FPDI avec gestion d'erreur
        try {
            error_log("📚 Plusieurs pages détectées, fusion FPDI");
            $finalPdf = new \setasign\Fpdi\Fpdi();
            error_log("✅ Objet FPDI créé pour fusion");
            
            foreach ($tempPDFFiles as $index => $file) {
                error_log("🔄 Traitement fichier " . ($index + 1) . "/" . count($tempPDFFiles));
                
                try {
                    $pageCount = $finalPdf->setSourceFile($file);
                    error_log("📄 Fichier source défini, pages: " . $pageCount);
                    
                    for ($i = 1; $i <= $pageCount; $i++) {
                        $template = $finalPdf->importPage($i);
                        $finalPdf->AddPage();
                        $finalPdf->useTemplate($template);
                    }
                } catch (Exception $e) {
                    error_log("❌ Erreur fichier " . $file . ": " . $e->getMessage());
                    continue;
                }
            }
            
            // Enregistrer le PDF final avec double méthode
            error_log("💾 Enregistrement PDF final...");
            try {
                $finalPdf->Output('F', $fullPath);
                if (file_exists($fullPath) && filesize($fullPath) > 0) {
                    error_log("✅ Sauvegarde directe réussie, taille: " . filesize($fullPath) . " bytes");
                } else {
                    // Méthode alternative
                    $pdfContent = $finalPdf->Output('S');
                    if (file_put_contents($fullPath, $pdfContent) !== false) {
                        error_log("✅ Sauvegarde alternative réussie, taille: " . filesize($fullPath) . " bytes");
                    } else {
                        throw new Exception("Échec des deux méthodes de sauvegarde finale");
                    }
                }
            } catch (Exception $e) {
                error_log("❌ Erreur sauvegarde finale: " . $e->getMessage());
                return false;
            }
        } catch (Exception $e) {
            error_log("❌ Erreur fusion FPDI: " . $e->getMessage());
            return false;
        }

        // Nettoyer les fichiers temporaires
        error_log("🗑️ Nettoyage fichiers temporaires");
        foreach ($tempPDFFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
                error_log("🗑️ Supprimé: " . basename($file));
            }
        }

        error_log("🎉 Génération PDF terminée avec succès: " . $fullPath);
        return true;
    }

    /**
     * Calcule l'espace nécessaire pour afficher les lignes de détail
     * 
     * @param FPDI $pdf L'objet PDF
     * @param array $lignes Tableau des lignes de détail
     * @return float Espace estimé en mm
     */
    private function calculerEspaceLignes($pdf, $lignes, $printRistourne = false, $montantRistourne = 0) {
        // Hauteur estimée pour la ligne "Responsable", en-têtes de colonnes et espacement
        $espaceTotal = 14; // 7mm (Responsable) + 2mm (espace) + 5mm (en-têtes)
        
        foreach ($lignes as $ligne) {
            // Préparer le texte de description complet (description + description_dates)
            $descriptionComplete = $ligne['description'];
            
            // Si description_dates existe et n'est pas vide, l'ajouter avec un retour à la ligne
            if (isset($ligne['description_dates']) && !empty($ligne['description_dates'])) {
                $descriptionComplete .= "\n" . $ligne['description_dates'];
            }
            
            $nbLines = $pdf->getNumLines($descriptionComplete, $this->descriptionWidth);
            // 7mm pour une ligne simple ou 5mm par ligne de texte pour les descriptions multi-lignes
            // + 2mm d'espace entre les lignes
            $hauteurLigne = ($nbLines > 1) ? (5 * $nbLines + 2) : 9;
            $espaceTotal += $hauteurLigne;
        }
        
        // Ajouter l'espace pour la ligne "Total à payer"
        $espaceTotal += 7;
        
        // Si ristourne est activée et un montant existe, ajouter de l'espace pour les lignes supplémentaires
        if ($printRistourne && $montantRistourne > 0) {
            // Deux lignes supplémentaires : Total brut et Ristourne
            $espaceTotal += (7 * 2);
        }
        
        return $espaceTotal;
    }

    /**
     * Calcule l'espace nécessaire pour une ligne de détail
     * 
     * @param FPDI $pdf L'objet PDF
     * @param array $ligne Données de la ligne
     * @return float Hauteur estimée en mm
     */
    private function calculerHauteurLigne($pdf, $ligne) {
        // Préparer le texte de description complet (description + description_dates)
        $descriptionComplete = $ligne['description'];
        
        // Si description_dates existe et n'est pas vide, l'ajouter avec un retour à la ligne
        if (isset($ligne['description_dates']) && !empty($ligne['description_dates'])) {
            $descriptionComplete .= "\n" . $ligne['description_dates'];
        }
        
        $nbLines = $pdf->getNumLines($descriptionComplete, $this->descriptionWidth);
        $hauteurLigne = ($nbLines > 1) ? ($nbLines * 5 + 4) : 10;
        // Ajouter 2mm d'espacement entre les lignes
        return $hauteurLigne + 2;
    }

    /**
     * Génère une page de détails sans la dernière ligne
     * 
     * @param FPDI $pdf L'objet PDF
     * @param array $facture Données de la facture
     * @param array $lignes Tableau des lignes de détail
     */
    private function genererPageDetailsSansDerniereLigne($pdf, $facture, $lignes) {
        // Ouvrir le cadre initial
        $this->frameStartY = $pdf->GetY();
        $this->frameOpen = true;
        
        // Ajouter la ligne "Responsable"
        $pdf->SetXY($this->textStartX, $this->frameStartY + 2);
        $pdf->SetFont($this->fontRegular, '', 10);
        $pdf->Cell(0, 7, 'Responsable : ' . $facture['prenom'] . ' ' . $facture['nom'], 0, 1, 'L');

        // En-têtes des colonnes
        $this->genererEnTetesColonnes($pdf);
        
        // Initialiser le montant total accumulé
        $totalAccumule = 0;
        
        // Générer toutes les lignes sauf la dernière
        for ($i = 0; $i < count($lignes) - 1; $i++) {
            $ligne = $lignes[$i];
            $this->genererLigneDetail($pdf, $ligne);
            $totalAccumule += floatval($ligne['total_ligne']);
        }
        
        // Stocke le montant du report pour la page suivante
        $this->montantReport = $totalAccumule;
        
        // Ajouter la ligne "Montant à reporter" à la fin de la page
        $montantFormate = number_format($totalAccumule, 2, '.', "'");
        $pdf->SetX($this->textStartX);
        $pdf->SetFont($this->fontRegular, '', 10);
        $pdf->Cell($this->descriptionWidth, 7, 'Montant à reporter :', 0, 0, 'R');
        $pdf->Cell($this->uniteWidth, 7, '', 0, 0, 'R');
        $pdf->Cell($this->quantiteWidth, 7, '', 0, 0, 'R');
        $pdf->Cell($this->prixWidth, 7, '', 0, 0, 'R');
        
        // Position précise pour le montant à reporter
        $totalXPosition = $this->frameX + $this->frameWidth - 3 - $pdf->GetStringWidth($montantFormate);
        $currentY = $pdf->GetY();
        $pdf->SetXY($totalXPosition, $currentY);
        $pdf->Cell($pdf->GetStringWidth($montantFormate), 7, $montantFormate, 0, 1, 'R');
        
        // Fermer le cadre sans les totaux finaux
        $this->fermerCadre($pdf, false);

        // EXPLICITEMENT créer une nouvelle page
        $pdf->AddPage();

        // TRÈS IMPORTANT: Vérifiez que la page a bien été ajoutée
        $pageNumber++;
        $this->totalPages = $pageNumber; // Mettre à jour immédiatement

        // Générer l'en-tête de la nouvelle page
        $this->genererEnTetePageSuivante($pdf, $facture);
    }

    /**
     * Génère une page avec la dernière ligne de détail et le total
     * 
     * @param FPDI $pdf L'objet PDF
     * @param array $facture Données de la facture
     * @param array $derniereLigne Dernière ligne de détail
     */
    private function genererPageAvecDerniereLigne($pdf, $facture, $derniereLigne) {
        // Ouvrir le cadre sur la nouvelle page
        $this->frameStartY = 40; // Laisser de l'espace en haut
        $this->frameOpen = true;
        
        // En-têtes des colonnes sur la nouvelle page
        $pdf->SetY($this->frameStartY);
        $this->genererEnTetesColonnes($pdf);
        
        // Ligne de report
        $montantFormate = number_format($this->montantReport, 2, '.', "'");
        $pdf->SetFont($this->fontRegular, '', 10);
        $pdf->SetX($this->textStartX);
        $pdf->Cell($this->descriptionWidth, 7, 'Montant reporté :', 0, 0, 'R');
        $pdf->Cell($this->uniteWidth, 7, '', 0, 0, 'R');
        $pdf->Cell($this->quantiteWidth, 7, '', 0, 0, 'R');
        $pdf->Cell($this->prixWidth, 7, '', 0, 0, 'R');
        
        // Position précise pour le montant reporté
        $totalXPosition = $this->frameX + $this->frameWidth - 3 - $pdf->GetStringWidth($montantFormate);
        $currentY = $pdf->GetY();
        $pdf->SetXY($totalXPosition, $currentY);
        $pdf->Cell($pdf->GetStringWidth($montantFormate), 7, $montantFormate, 0, 1, 'R');
        
        $pdf->Ln(5);
        
        // Imprimer la dernière ligne de détail
        $this->genererLigneDetail($pdf, $derniereLigne);
        
        // Ajouter le total de la dernière ligne au montant accumulé
        $this->montantReport += floatval($derniereLigne['total_ligne']);
        
        // Lignes des totaux
        $this->genererLignesTotal($pdf, $facture, $printRistourne);
        
        // Fermer le cadre final
        $this->fermerCadre($pdf, true);
        $this->frameOpen = false;
    }

    /**
     * Ajoute la numérotation sur toutes les pages du document
     * Ne doit être appelée que si le document a plus d'une page
     *
     * @param FPDI $pdf L'objet PDF
     */
    private function ajouterNumerotationPages($pdf) {
        // Sauvegarder les valeurs originales
        $startPage = $pdf->getPage();
        
        for ($i = 1; $i <= $pdf->getNumPages(); $i++) {
            $pdf->setPage($i);
            
            // Position en bas à droite
            $pdf->SetY(-15); // 15mm du bas
            $pdf->SetX(-30); // 30mm de la droite
            
            $pdf->SetFont($this->fontRegular, '', 8);
            $pdf->Cell(20, 10, 'Page ' . $i . '/' . $this->totalPages, 0, 0, 'R');
        }
        
        // Restaurer la page active
        $pdf->setPage($startPage);
    }

    /**
     * Génère l'en-tête du PDF facture
     * 
     * @param FPDI $pdf L'objet PDF
     * @param array $facture Données de la facture
     */
    private function genererEnTete($pdf, $facture) {
        // Ajouter le logo en haut à gauche
        $logoPath = __DIR__ . '/assets/logo.png';
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 20, 20, 30);
        }

        // Position pour la date (alignée à droite)
        $pdf->SetY(20); 
        $dateText = 'Corcelles-près-Payerne, le ' . $this->dateEnFrancais($facture['date_facture']);
        
        // Calculer la position exacte pour aligner la date à droite
        $pageWidth = $pdf->getPageWidth();
        $rightMargin = $pdf->getMargins()['right'];
        $dateTextWidth = $pdf->GetStringWidth($dateText);
        $dateStartX = $pageWidth - $rightMargin - $dateTextWidth;
        
        // Positionner et écrire la date
        $pdf->SetXY($dateStartX, 20);
        $pdf->Cell($dateTextWidth, 5, $dateText, 0, 1, 'L');
        
        // Utiliser la même position X pour l'adresse du client
        $clientAddressX = $dateStartX;

        // Positionnement pour l'adresse du centre 
        $pdf->SetY(35); // 5mm sous le logo

        // Adresse du Centre La Grange 
        $pdf->Cell(120, 5, 'Route du Bornalet 16', 0, 1, 'L');
        $pdf->Cell(120, 5, '1562 Corcelles', 0, 1, 'L');
        $pdf->Cell(120, 5, 'centrelagrange@gmail.ch', 0, 1, 'L');
        $pdf->Ln(5); 

        // Si la facture est Payée ou Annulée, ajouter le tampon
        if (isset($facture['etat']) && ($facture['etat'] === 'Payée' || $facture['etat'] === 'Annulée')) {
            $couleurs = [
                'Payée' => [0, 128, 0],   // Vert foncé
                'Annulée' => [255, 0, 0]  // Rouge
            ];
            
            // Position du tampon aligné avec la première ligne de l'adresse
            $pdf->SetFont($this->fontBold, 'B', 12);
            $pdf->SetTextColor($couleurs[$facture['etat']][0], $couleurs[$facture['etat']][1], $couleurs[$facture['etat']][2]);
            
            // Dessiner un rectangle aux coins légèrement arrondis
            $pdf->SetLineStyle(['width' => 0.5, 'color' => $couleurs[$facture['etat']]]);
            // Simulation d'opacité plus claire
            $pdf->RoundedRect(110, 35, 40, 7, 1, '1111', 'D');
            
            // Centrer le texte horizontalement dans le rectangle
            $text = $facture['etat'];
            $textWidth = $pdf->GetStringWidth($text);
            $x = 110 + (40 - $textWidth) / 2;
            $pdf->SetXY($x, 35);
            $pdf->Cell($textWidth, 7, $text, 0, 1, 'L');
            
            // Réinitialiser
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont($this->fontRegular, '', 12);
            
            $pdf->Ln(15); // Petit espace après le tampon
        }

        // Adresse du client (alignée avec le début du texte de la date)
        $lineHeight = 5;
        
        // Positionnement pour l'adresse client
        // Gestion du titre (qui peut être absent)
        if (!empty(trim($facture['titre']))) {
            $pdf->SetX($clientAddressX);
            $pdf->Cell(120, $lineHeight, $facture['titre'], 0, 1, 'L');
        }
        
        $pdf->SetX($clientAddressX);
        $pdf->Cell(120, $lineHeight, $facture['prenom'] . ' ' . $facture['nom'], 0, 1, 'L');
        $pdf->SetX($clientAddressX);
        $pdf->Cell(120, $lineHeight, $facture['rue'] . ' ' . $facture['numero'], 0, 1, 'L');
        $pdf->SetX($clientAddressX);
        $pdf->Cell(120, $lineHeight, '', 0, 1, 'L'); // Ligne vide
        $pdf->SetX($clientAddressX);
        $pdf->Cell(120, $lineHeight, $facture['code_postal'] . ' ' . $facture['localite'], 0, 1, 'L');
        
        // 4 Lignes vides séparant l'adresse du titre de la facture
        $pdf->Ln(20);
        
        // Titre de la facture
        $pdf->SetFont($this->fontBold, 'B', 12);
        $pdf->Cell(0, 5, 'Facture no ' . $facture['numero_facture'] . ' : Location de salle « La Grange »', 0, 1, 'L');
        
        // AJOUT : Ligne vide supplémentaire après le titre de la facture
        $pdf->Ln(5);
    }

    /**
     * Génère un en-tête minimal pour les pages suivantes d'une facture multi-pages
     * 
     * @param FPDI $pdf L'objet PDF
     * @param array $facture Données de la facture
     */
    private function genererEnTetePageSuivante($pdf, $facture) {
        // Ajouter le logo en haut à gauche
        $logoPath = __DIR__ . '/assets/logo.png';
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 20, 20, 30);
        }

        // Position pour la date (alignée à droite)
        $pdf->SetY(20); 
        $pdf->Cell(0, 5, 'Corcelles-près-Payerne, le ' . $this->dateEnFrancais($facture['date_facture']), 0, 1, 'R');

        // Positionnement pour le titre de la facture (sans adresse)
        $pdf->SetY(40); // Position fixe pour l'en-tête des pages suivantes
        
        // Titre de la facture
        $pdf->SetFont($this->fontBold, 'B', 12);
        $pdf->Cell(0, 5, 'Facture no ' . $facture['numero_facture'] . ' : Location de salle « La Grange »', 0, 1, 'L');
        
        // Ajout d'un espacement entre le titre et le début du tableau
        $pdf->Ln(5);
    }
    
    /**
    * Génère les en-têtes de colonnes du tableau de détails
    * 
    * @param FPDI $pdf L'objet PDF
    */
    private function genererEnTetesColonnes($pdf) {
        // En-têtes des colonnes avec fond gris
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetFont($this->fontBold, 'B', 10);
        
        $currentY = $pdf->GetY();
        $pdf->SetXY($this->textStartX, $currentY + 2);
    
        // Dessiner un rectangle de fond gris qui s'arrête exactement aux limites du cadre
        // On soustrait 1mm à droite pour éviter tout dépassement
        $pdf->Rect($this->textStartX, $currentY + 2, $this->frameWidth - 6, 5, 'F');
        
        // Dessiner les textes des en-têtes sans activer le remplissage
        $pdf->Cell($this->descriptionWidth, 5, 'Description', 0, 0, 'L', false);
        $pdf->Cell($this->uniteWidth, 5, 'Unité', 0, 0, 'R', false);
        $pdf->Cell($this->quantiteWidth, 5, 'Qté.', 0, 0, 'R', false);
        $pdf->Cell($this->prixWidth, 5, 'Prix unit.', 0, 0, 'R', false);
        
        // Positionner "Total CHF" avec précision
        $totalLabel = 'Total CHF';
        $totalXPosition = $this->frameX + $this->frameWidth - 3 - $pdf->GetStringWidth($totalLabel);
        $pdf->SetXY($totalXPosition, $currentY + 2);
        $pdf->Cell($pdf->GetStringWidth($totalLabel), 5, $totalLabel, 0, 1, 'R', false);
    }

    /**
    * Génère une ligne de détail de la facture
    * 
    * @param FPDI $pdf L'objet PDF
    * @param array $ligne Données de la ligne
    */
    private function genererLigneDetail($pdf, $ligne) {
        $pdf->SetFont($this->fontRegular, '', 10);
        
        // Position initiale
        $startX = $this->textStartX;
        $startY = $pdf->GetY();
        
        // Formater les valeurs numériques
        $quantiteFormatee = number_format($ligne['quantite'], 0, '.', "'");
        $prixFormate = number_format($ligne['prix_unitaire'], 2, '.', "'");
        $totalFormate = number_format($ligne['total_ligne'], 2, '.', "'");
        
        // Utiliser le code d'unité s'il est disponible, sinon utiliser le champ unite
        $uniteAffichee = isset($ligne['unite_code']) ? $ligne['unite_code'] : $ligne['unite'];
        
        // Stocker la position Y initiale
        $initialY = $startY;
        
        // Préparer le texte de description complet (description + description_dates)
        $descriptionComplete = $ligne['description'];
        
        // Si description_dates existe et n'est pas vide, l'ajouter avec un retour à la ligne
        if (isset($ligne['description_dates']) && !empty($ligne['description_dates'])) {
            $descriptionComplete .= "\n" . $ligne['description_dates'];
        }
        
        // 1. Dessiner la description avec MultiCell
        $pdf->SetXY($startX, $startY);
        $startDescriptionText = $pdf->GetY();
        
        // Utilisez MultiCell avec une hauteur réduite pour plus de contrôle
        $pdf->MultiCell($this->descriptionWidth, 5, $descriptionComplete, 0, 'L');
        
        // Obtenez la position Y après avoir écrit la description
        $endDescriptionText = $pdf->GetY();
        
        // Calculez la hauteur réelle utilisée par la description
        $descriptionHeight = $endDescriptionText - $startDescriptionText;
        
        // 2. Maintenant placez les autres colonnes à côté de la description
        // Unité - Utiliser le code de l'unité plutôt que le champ unite
        $pdf->SetXY($startX + $this->descriptionWidth, $startY);
        $pdf->Cell($this->uniteWidth, 5, $uniteAffichee, 0, 0, 'R');
        
        // Quantité
        $pdf->SetXY($startX + $this->descriptionWidth + $this->uniteWidth, $startY);
        $pdf->Cell($this->quantiteWidth, 5, $quantiteFormatee, 0, 0, 'R');
        
        // Prix unitaire
        $pdf->SetXY($startX + $this->descriptionWidth + $this->uniteWidth + $this->quantiteWidth, $startY);
        $pdf->Cell($this->prixWidth, 5, $prixFormate, 0, 0, 'R');
        
        // Total (aligné à droite de la zone disponible)
        $totalXPosition = $this->frameX + $this->frameWidth - 3 - $pdf->GetStringWidth($totalFormate);
        $pdf->SetXY($totalXPosition, $startY);
        $pdf->Cell($pdf->GetStringWidth($totalFormate), 5, $totalFormate, 0, 0, 'R');
        
        // 3. Déplacer le curseur après la description
        // IMPORTANT: S'assurer que nous nous déplaçons à la fin réelle de la description
        $pdf->SetY($endDescriptionText + 2); // +2 pour l'espacement entre les lignes
    }
    
    /**
     * Génère les lignes de total avec gestion optionnelle de la ristourne
     * 
     * @param FPDI $pdf L'objet PDF
     * @param array $facture Données de la facture
     * @param bool $printRistourne Indique s'il faut imprimer la ristourne
     */
    private function genererLignesTotal($pdf, $facture, $printRistourne = false) {
        // Sauvegarder la position Y avant de commencer
        $startY = $pdf->GetY();
    
        // Dessiner une ligne horizontale fine
        $pdf->SetLineWidth(0.2); // Épaisseur fine de la ligne
        $pdf->SetDrawColor(150, 150, 150); // Gris moyen
        
        // Calculer la position X de fin de ligne (fin de la colonne Total CHF)
        $lineEndX = $this->frameX + $this->frameWidth - 3;
        
        // Dessiner la ligne 1mm au-dessus du début des lignes de total
        $lineY = $startY - 1;
        $pdf->Line($this->textStartX, $lineY, $lineEndX, $lineY);
    
        // Réinitialiser le style de ligne
        $pdf->SetLineWidth(0.2);
        $pdf->SetDrawColor(0, 0, 0);
    
        // Gestion de la ristourne avant la ligne "Total à payer"
        if ($printRistourne && $facture['ristourne'] > 0) {
            // Ligne "Total brut"
            $pdf->SetXY($this->textStartX, $startY);
            $pdf->SetFont($this->fontRegular, '', 10);
            $pdf->Cell($this->descriptionWidth + $this->uniteWidth + $this->quantiteWidth + $this->prixWidth, 7, 'Total brut :', 0, 0, 'L');
    
            // Calculer le total brut (somme des totaux des lignes)
            $totalBrut = array_sum(array_column($facture['lignes'], 'total_ligne'));
            $totalBrutFormate = number_format($totalBrut, 2, '.', "'");
            
            // Position précise pour le total brut
            $totalBrutXPosition = $this->frameX + $this->frameWidth - 3 - $pdf->GetStringWidth($totalBrutFormate);
            $currentY = $pdf->GetY();
            $pdf->SetXY($totalBrutXPosition, $currentY);
            $pdf->Cell($pdf->GetStringWidth($totalBrutFormate), 7, $totalBrutFormate, 0, 1, 'R');
    
            // Ligne "Ristourne"
            $pdf->SetX($this->textStartX);
            $pdf->Cell($this->descriptionWidth + $this->uniteWidth + $this->quantiteWidth + $this->prixWidth, 7, 'Ristourne :', 0, 0, 'L');
    
            // Montant de la ristourne (négatif)
            $ristourneFormatee = number_format(-$facture['ristourne'], 2, '.', "'");
            $ristourneXPosition = $this->frameX + $this->frameWidth - 3 - $pdf->GetStringWidth($ristourneFormatee);
            $currentY = $pdf->GetY();
            $pdf->SetXY($ristourneXPosition, $currentY);
            $pdf->Cell($pdf->GetStringWidth($ristourneFormatee), 7, $ristourneFormatee, 0, 1, 'R');
        }
        
        // Ligne "Total à payer"
        $pdf->SetX($this->textStartX);
        $pdf->SetFont($this->fontBold, 'B', 11);
        $pdf->Cell($this->descriptionWidth + $this->uniteWidth + $this->quantiteWidth + $this->prixWidth, 7, 'Total à payer :', 0, 0, 'L');
        
        // Positionner le montant total avec une marge fixe de 3mm à droite du cadre
        $montantTotal = number_format($facture['montant_total'], 2, '.', "'");
        $totalXPosition = $this->frameX + $this->frameWidth - 3 - $pdf->GetStringWidth($montantTotal);
        $currentY = $pdf->GetY();
        $pdf->SetXY($totalXPosition, $currentY);
        $pdf->Cell($pdf->GetStringWidth($montantTotal), 7, $montantTotal, 0, 1, 'R');
    }

    /**
    * Ferme le cadre de détails actuel
    * 
    * @param FPDI $pdf L'objet PDF
    * @param bool $avecTotal Indique si c'est le dernier cadre avec le total final
    */
    private function fermerCadre($pdf, $avecTotal = true) {
        // Fin du cadre avec une marge supplémentaire pour éviter qu'il soit trop serré
        $frameEndY = $pdf->GetY() + 7; // Augmenter légèrement pour plus d'espace

        // Dessiner le cadre à double bordure
        $pdf->SetLineWidth(0.4);
        $pdf->SetDrawColor(150, 150, 150);
        $pdf->Rect($this->frameX, $this->frameStartY, $this->frameWidth, $frameEndY - $this->frameStartY, 'D');

        // Deuxième bordure (intérieure)
        $pdf->SetLineWidth(0.2);
        $pdf->SetDrawColor(180, 180, 180);
        $pdf->Rect($this->frameX + 1, $this->frameStartY + 1, $this->frameWidth - 2, ($frameEndY - $this->frameStartY) - 2, 'D');

        // Positionner après le cadre
        $pdf->SetY($frameEndY + 5);
        $pdf->SetFont($this->fontRegular, '', 12);
    }
    
	/**
     * Génère le pied de page du PDF facture avec les informations de paiement
     * 
     * @param FPDI $pdf L'objet PDF
     * @param array $relationsBancaires Paramètres des coordonnées bancaires
     * @param int $delaiPaiement Délai de paiement en jours
     */
    private function genererPiedPage($pdf, $relationsBancaires = [], $delaiPaiement = 30, $signature = []) {
        
        // Calculer la hauteur totale estimée du pied de page
        $beneficiaires = isset($relationsBancaires['Beneficiaire']) 
            ? explode(',', $relationsBancaires['Beneficiaire']) 
            : ['Johanna Cherbuin', 'Chemin du Châtelard 9', '1562 Corcelles-près-Payerne'];
        $beneficiaires = array_map('trim', $beneficiaires);
        
        // Estimation de la hauteur totale du pied de page
        $hauteurEstimee = 0;
        $hauteurEstimee += 7; // "Paiement net à X jours"
        $hauteurEstimee += 7; // "Montant total à verser à :"
        $hauteurEstimee += 5; // Banque
        $hauteurEstimee += 5; // IBAN
        $hauteurEstimee += 5; // "Pour :"
        $hauteurEstimee += (count($beneficiaires) * 5); // Lignes bénéficiaire
        $hauteurEstimee += 5; // Espacement
        $hauteurEstimee += 7; // "Avec nos remerciements"
        $hauteurEstimee += 5; // Espacement
        $hauteurEstimee += 7; // Signature ligne 1
        $hauteurEstimee += 7; // Signature ligne 2
        
        // Calculer la position de début pour que la dernière ligne soit à 15mm au-dessus de la marge du bas
        $pageHeight = $pdf->getPageHeight();
        $bottomMargin = $pdf->getMargins()['bottom'];
        $footerStartY = $pageHeight - $bottomMargin - 15 - $hauteurEstimee;
        
        // Positionner le pied de page
        $pdf->SetY($footerStartY);
        
        // Informations de paiement
        $pdf->SetFont($this->fontRegular, '', 12);
        
        // Ajouter la ligne de délai de paiement
        $pdf->Cell(0, 7, 'Paiement net à ' . $delaiPaiement . ' jours.', 0, 1, 'L');
        
        $pdf->Cell(0, 7, 'Montant total à verser à :', 0, 1, 'L');

        // Afficher les informations bancaires depuis les paramètres avec espacement réduit
        $pdf->Cell(0, 5, isset($relationsBancaires['Banque']) ? $relationsBancaires['Banque'] : 'BCV 1001 Lausanne', 0, 1, 'L');
        $pdf->Cell(0, 5, isset($relationsBancaires['IBAN']) ? $relationsBancaires['IBAN'] : 'CH 88 0076 7000 E536 2645 5', 0, 1, 'L');

        // Affichage des lignes du bénéficiaire
        $pdf->Cell(0, 5, 'Pour :', 0, 1, 'L');
        foreach ($beneficiaires as $ligne) {
            $pdf->Cell(0, 5, $ligne, 0, 1, 'L');
        }

        // Une seule ligne d'espace après
        $pdf->Ln(5);

        // "Avec nos remerciements" aligné à gauche
        $pdf->Cell(0, 7, 'Avec nos remerciements.', 0, 1, 'L');
        $pdf->Ln(5);

        // Position pour la signature (alignée avec l'adresse du client, à droite)
        $clientAddressX = 110; // Même position X que pour l'adresse du client

        // Déplacer à la position X alignée avec l'adresse client
        $pdf->SetX($clientAddressX);

        // Signature avec formatage italique et couleur bordeaux
        $pdf->SetTextColor(128, 0, 32); // Couleur bordeaux
        $pdf->SetFont($this->fontItalic, 'I', 12); // Italique - dans FPDI c'est 'I' au lieu de ''
        
        // Signature : première ligne du Beneficiaire ou valeur par défaut
        $signatureLigne1 = !empty($signature['Ligne 1']) ? $signature['Ligne 1'] : 'Johanna_Cherbuin';
        $pdf->Cell(0, 7, $signatureLigne1, 0, 1, 'L');

        // Déplacer à la position X alignée avec l'adresse client pour la deuxième ligne
        $pdf->SetX($clientAddressX);

        // Retour à la couleur noire 
        $pdf->SetTextColor(0, 0, 0); 
        
        // Signature : deuxième ligne ou valeur par défaut
        $signatureLigne2 = !empty($signature['Ligne 2']) ? $signature['Ligne 2'] : 'Centre « La_Grange »';
		$pdf->Cell(0, 7, $signatureLigne2, 0, 1, 'L');

        // Réinitialiser la police pour la suite
        $pdf->SetFont($this->fontRegular, '', 12);
    }

    /**
     * Tampon de statut à 50mm sous la première ligne de la facture
     * 
     * @param FPDI $pdf Objet PDF
     * @param string $text Texte du tampon
     * @param array $couleur Couleur RGB du tampon
     */
    private function ajouterTamponStatut($pdf, $text, $couleur) {
        // Sauvegarder l'état graphique
        $pdf->SetFont($this->fontBold, 'B', 12);
        $pdf->SetTextColor($couleur[0], $couleur[1], $couleur[2]);
    
        // Obtenir les dimensions de la page
        $pageWidth = $pdf->getPageWidth();
        $leftMargin = $pdf->getMargins()['left'];
    
        // Paramètres du tampon
        $largeur = 40;  // Largeur du tampon
        $hauteur = 10;  // Hauteur du tampon
        
        // Position : 50mm en dessus de la première ligne d'adresse, aligné à gauche
        $x = $leftMargin;
        $y = 50;  // 50mm en dessus de la première ligne
        
        // Dessiner un rectangle aux coins arrondis
        $pdf->SetLineWidth(0.5);
        $pdf->SetDrawColor($couleur[0], $couleur[1], $couleur[2]);
        $pdf->RoundedRect($x, $y, $largeur, $hauteur, 2, '1111', 'D');
    
        // Centrer le texte dans le rectangle
        $textWidth = $pdf->GetStringWidth($text);
        $textX = $x + ($largeur - $textWidth) / 2;
        $textY = $y + ($hauteur - 7) / 2;  
    
        // Écrire le texte
        $pdf->SetXY($textX, $textY);
        $pdf->Cell($textWidth, 0, $text, 0, 0, 'C');
    
        // Réinitialiser les paramètres
        $pdf->SetTextColor(0, 0, 0);
    }
    
    /**
    * Formater une date au format français
    * 
    * @param string $date Date au format Y-m-d
    * @return string Date formatée (ex: 26 mars 2025)
    */
    private function dateEnFrancais($date) {
        // FPDI ne gère pas bien l'UTF-8, on utilise une approche plus simple
        $dateObj = new DateTime($date);
        
        // Tableau des mois en français
        $mois = [
            1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
            5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
            9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre'
        ];
        
        $jour = $dateObj->format('j');
        $moisNum = (int)$dateObj->format('n');
        $moisTexte = $mois[$moisNum];
        $annee = $dateObj->format('Y');
        
        return $jour . ' ' . $moisTexte . ' ' . $annee;
    }

    /**
     * Calcule la hauteur totale de l'en-tête de la facture
     * 
     * @param array $facture Données de la facture
     * @return float Hauteur totale en mm
     */
    private function calculerHauteurEnTete($facture) {
        $hauteur = 0;
        
        // Logo et date (20mm depuis le haut)
        $hauteur += 20;
        
        // ✅ CALCUL PLUS PRÉCIS de l'adresse du centre
        $hauteur += 15; // 3 lignes de 5mm chacune
        $hauteur += 5;  // Espacement après l'adresse
        
        // Tampon si présent (pour factures Payées/Annulées)
        if (isset($facture['etat']) && ($facture['etat'] === 'Payée' || $facture['etat'] === 'Annulée')) {
            $hauteur += 10; // Réduit de 15 à 10mm
        }
        
        // ✅ CALCUL PLUS PRÉCIS de l'adresse du client
        $lignesClient = 0;
        if (!empty(trim($facture['titre']))) {
            $lignesClient += 1; // Titre
        }
        $lignesClient += 4; // nom, rue+numéro, ligne vide, code postal+localité
        $hauteur += $lignesClient * 5; // = 20-25mm selon si titre présent
        
        // ✅ RÉDUCTION des espaces
        $hauteur += 10; // Réduit de 20 à 10mm
        
        // Titre de la facture
        $hauteur += 5; // hauteur du titre
        
        // Ligne vide après le titre
        $hauteur += 5; // Ligne vide après le titre
        
        error_log("🏠 Hauteur en-tête calculée: {$hauteur}mm");
        return $hauteur;
    }

    /**
     * Calcule la hauteur totale nécessaire pour le pied de page
     * 
     * @param array $relationsBancaires Paramètres des coordonnées bancaires
     * @return float Hauteur totale en mm (contenu + espacement + marge)
     */
    private function calculerHauteurPiedPage($relationsBancaires = []) {
        // ✅ CALCUL PLUS RÉALISTE du contenu du pied de page
        $beneficiaires = isset($relationsBancaires['Beneficiaire']) 
            ? explode(',', $relationsBancaires['Beneficiaire']) 
            : ['Johanna Cherbuin', 'Chemin du Châtelard 9', '1562 Corcelles-près-Payerne'];
        $beneficiaires = array_map('trim', $beneficiaires);
        
        // Estimation plus précise de la hauteur du contenu
        $hauteurContenu = 0;
        $hauteurContenu += 7; // "Paiement net à X jours"
        $hauteurContenu += 7; // "Montant total à verser à :"
        $hauteurContenu += 5; // Banque
        $hauteurContenu += 5; // IBAN
        $hauteurContenu += 5; // "Pour :"
        $hauteurContenu += (count($beneficiaires) * 5); // Lignes bénéficiaire
        $hauteurContenu += 5; // Espacement
        $hauteurContenu += 7; // "Avec nos remerciements"
        $hauteurContenu += 5; // Espacement
        $hauteurContenu += 7; // Signature ligne 1
        $hauteurContenu += 7; // Signature ligne 2
        
        // ✅ RÉDUCTION : Seulement 10mm d'espacement au lieu de 15mm + marge réduite
        return $hauteurContenu + 10 + 15; // Total ≈ 75mm au lieu de 95mm
    }


    /**
     * Calcule l'espace disponible sur une page pour le contenu
     * 
     * @param FPDI|null $pdf L'objet PDF (peut être null pour estimation)
     * @param array $facture Données de la facture
     * @param array $relationsBancaires Paramètres des coordonnées bancaires
     * @param bool $estPremierePage Indique s'il s'agit de la première page
     * @return float Espace disponible en mm
     */
    private function calculerEspaceDisponible($pdf, $facture, $relationsBancaires, $estPremierePage = true) {
        // Utiliser des valeurs par défaut si pas d'objet PDF
        $pageHeight = $pdf ? $pdf->getPageHeight() : 297; // A4 = 297mm
        $topMargin = $pdf ? $pdf->getMargins()['top'] : 20; // Marge par défaut 20mm
        
        // Calculer la hauteur de l'en-tête
        if ($estPremierePage) {
            $hauteurEnTete = $this->calculerHauteurEnTete($facture);
        } else {
            // En-tête simplifié pour les pages suivantes
            $hauteurEnTete = 20 + 20 + 5 + 5; // logo+date + titre + 2 espacements
        }
        
        // Calculer la hauteur du pied de page
        $hauteurPiedPage = $this->calculerHauteurPiedPage($relationsBancaires);
        
        // Espace disponible = hauteur totale - marge top - en-tête - pied de page
        $espaceDisponible = $pageHeight - $topMargin - $hauteurEnTete - $hauteurPiedPage;
        
        return $espaceDisponible;
    }

    /**
     * Calcule le nombre total de pages nécessaires pour la facture
     * 
     * @param array $facture Données de la facture
     * @param array $relationsBancaires Paramètres des coordonnées bancaires
     * @return int Nombre total de pages
     */
    private function calculerNombreTotalPages($facture, $relationsBancaires) {
        $lignes = $facture['lignes'];
        $pages = 1;
        $lignesTraitees = 0;
        
        error_log("🔢 === CALCUL NOMBRE TOTAL DE PAGES ===");
        error_log("🔢 Nombre de lignes à traiter: " . count($lignes));
        
        // ✅ VÉRIFICATION INITIALE : Si peu de lignes, probablement une seule page
        if (count($lignes) <= 5) {
            error_log("🔢 Peu de lignes détectées (" . count($lignes) . "), test d'une seule page");
            
            // Calculer si tout tient sur une page
            $espaceDisponible = $this->calculerEspaceDisponible(null, $facture, $relationsBancaires, true);
            $espaceContenu = $espaceDisponible - 14; // En-têtes et responsable
            
            $lignesQuiTiennent = $this->calculerNombreLignesQuiTiennent(null, $lignes, $espaceContenu, true);
            
            if ($lignesQuiTiennent >= count($lignes)) {
                error_log("🔢 ✅ Toutes les lignes tiennent sur une seule page");
                return 1;
            } else {
                error_log("🔢 ❌ Les lignes ne tiennent pas sur une seule page, calcul multi-pages");
            }
        }
        
        // Calcul standard pour les factures multi-pages
        while ($lignesTraitees < count($lignes)) {
            $estPremierePage = ($pages == 1);
            $espaceDisponible = $this->calculerEspaceDisponible(null, $facture, $relationsBancaires, $estPremierePage);
            
            // Soustraire l'espace pour les en-têtes et responsable (environ 14mm)
            $espaceContenu = $espaceDisponible - 14;
            
            $lignesRestantes = array_slice($lignes, $lignesTraitees);
            $nombreLignesRestantes = count($lignesRestantes);
            
            error_log("📄 Page $pages - Espace disponible: {$espaceContenu}mm - Lignes restantes: $nombreLignesRestantes");
            
            // Calcul amélioré : Vérifier si on peut tout mettre sur cette page
            $estDernierePage = true; // Assumer que c'est la dernière page pour commencer
            $lignesQuiTiennent = $this->calculerNombreLignesQuiTiennent(null, $lignesRestantes, $espaceContenu, $estDernierePage);
            
            error_log("📋 Tentative avec totaux: {$lignesQuiTiennent} lignes peuvent tenir");
            
            // Si toutes les lignes restantes tiennent avec les totaux, c'est bon
            if ($lignesQuiTiennent >= $nombreLignesRestantes) {
                error_log("✅ Toutes les lignes restantes tiennent sur cette page avec totaux");
                $lignesTraitees = count($lignes); // Terminer
            } else {
                // Sinon, recalculer sans les totaux (page intermédiaire)
                $lignesQuiTiennent = $this->calculerNombreLignesQuiTiennent(null, $lignesRestantes, $espaceContenu, false);
                error_log("📋 Recalcul sans totaux: {$lignesQuiTiennent} lignes peuvent tenir");
                
                $lignesTraitees += $lignesQuiTiennent;
                
                if ($lignesTraitees < count($lignes)) {
                    $pages++;
                    error_log("➡️ Passage à la page suivante - Lignes traitées: $lignesTraitees/" . count($lignes));
                }
            }
        }
        
        error_log("🎯 Nombre total de pages calculé: $pages");
        return $pages;
    }

    /**
     * Calcule le nombre de lignes qui peuvent tenir dans l'espace disponible
     * 
     * @param FPDI|null $pdf L'objet PDF (peut être null pour estimation)
     * @param array $lignes Lignes restantes à traiter
     * @param float $espaceDisponible Espace disponible en mm
     * @param bool $estDernierePage Indique si c'est la dernière page (pour les totaux)
     * @return int Nombre de lignes qui peuvent tenir
     */
    private function calculerNombreLignesQuiTiennent($pdf, $lignes, $espaceDisponible, $estDernierePage = false) {
        $espaceUtilise = 0;
        $nombreLignes = 0;
        
        // ✅ CALCUL PLUS RÉALISTE DE L'ESPACE RÉSERVÉ POUR LES TOTAUX
        if ($estDernierePage) {
            // Espace pour les totaux finaux (sans le pied de page qui est calculé séparément)
            $espaceReserveTotaux = 15; // Réduit de 25 à 15mm
            error_log("📊 Page finale - Espace réservé pour totaux: {$espaceReserveTotaux}mm");
        } else {
            // Espace pour "Montant à reporter"
            $espaceReserveTotaux = 7; // Réduit de 10 à 7mm
            error_log("📊 Page intermédiaire - Espace réservé pour report: {$espaceReserveTotaux}mm");
        }
        
        error_log("📊 Espace total disponible: {$espaceDisponible}mm");
        
        for ($i = 0; $i < count($lignes); $i++) {
            // Calculer la hauteur de cette ligne
            if ($pdf) {
                $hauteurLigne = $this->calculerHauteurLigne($pdf, $lignes[$i]);
            } else {
                // ✅ ESTIMATION PLUS RÉALISTE ET MOINS CONSERVATIVE
                $hauteurEstimee = 10; // Maintenir à 10mm (valeur réaliste)
                if (isset($lignes[$i]['description_dates']) && !empty($lignes[$i]['description_dates'])) {
                    $hauteurEstimee = 15; // Réduit de 18 à 15mm
                }
                $hauteurLigne = $hauteurEstimee;
            }
            
            // Vérifier si cette ligne + les totaux tiennent encore
            $espaceAvecLigne = $espaceUtilise + $hauteurLigne + $espaceReserveTotaux;
            
            if ($espaceAvecLigne <= $espaceDisponible) {
                $espaceUtilise += $hauteurLigne;
                $nombreLignes++;
                error_log("📋 Ligne $i acceptée - Espace utilisé: {$espaceUtilise}mm + réservé: {$espaceReserveTotaux}mm = {$espaceAvecLigne}mm");
            } else {
                error_log("📋 Ligne $i refusée - Dépassement: {$espaceAvecLigne}mm > {$espaceDisponible}mm");
                break;
            }
        }
        
        // ✅ SÉCURITÉ : Au moins 1 ligne par page, mais pas plus que disponible
        $resultat = max(1, min($nombreLignes, count($lignes)));
        error_log("📋 Résultat final: {$resultat} lignes peuvent tenir sur {$espaceDisponible}mm");
        
        return $resultat;
    }
}

?>