<?php

require_once 'PDFGeneratorInterface.php';

/**
 * FacturePDFGenerator.php
 * 
 * Classe responsable de la génération de fichiers PDF pour les factures
 * du Centre La Grange. Cette classe utilise la bibliothèque TCPDF.
 * Gestion des sauts de page améliorée pour les factures sur plusieurs pages.
 */

class MyTCPDF extends TCPDF {
    // Ajouter la méthode empty_string manquante
    protected function empty_string($str) {
        return (is_null($str) || ($str === ''));
    }
    
    // Remplacer setPageFormat qui est appelée dans le constructeur
    public function setPageFormat($format, $orientation = 'P') {
        if ($format == 'A4') {
            $this->fwPt = 595.276;
            $this->fhPt = 841.89;
        } elseif ($format == 'A3') {
            $this->fwPt = 841.89;
            $this->fhPt = 1190.55;
        } elseif ($format == 'LETTER') {
            $this->fwPt = 612;
            $this->fhPt = 792;
        } else {
            // Format par défaut A4
            $this->fwPt = 595.276;
            $this->fhPt = 841.89;
        }
        
        // Appliquer l'orientation
        if (($orientation == 'P') OR ($orientation == 'PORTRAIT')) {
            $this->CurOrientation = 'P';
            $this->w = $this->fwPt;
            $this->h = $this->fhPt;
        } else {
            $this->CurOrientation = 'L';
            $this->w = $this->fhPt;
            $this->h = $this->fwPt;
        }
        
        // Appliquer les autres paramètres comme dans la méthode originale
        $this->wPt = $this->w;
        $this->hPt = $this->h;
        $this->k = 72 / 25.4;
        $this->w = $this->wPt / $this->k;
        $this->h = $this->hPt / $this->k;
        
        // Définir les marges par défaut
        if ($this->empty_string($this->original_lMargin)) {
            $this->original_lMargin = $this->lMargin;
        }
        if ($this->empty_string($this->original_rMargin)) {
            $this->original_rMargin = $this->rMargin;
        }
        
        // Définir les dimensions de la page
        $this->PageBreakTrigger = $this->h - $this->bMargin;
        $this->CurPageSize = array($this->w, $this->h);
        
        return $this;
    }
}

class TCPDFFactureGenerator implements PDFGeneratorInterface {
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
    
    // Hauteur du pied de page (estimation)
    private $hauteurPiedPage = 130; // en mm
    
    public function __construct() {
        $this->fontRegular = 'dejavuserif';
        $this->fontBold = 'dejavuserifb';
        $this->fontItalic = 'dejavuserifi';
    }

    /**
     * Génère un fichier PDF pour une facture
     * 
     * @param array $facture Données de la facture et ses lignes
     * @param string $pdfFileName Nom du fichier PDF à générer
     * @param string $outputDir Répertoire de sortie pour le PDF
     * @param array $relationsBancaires Paramètres des coordonnées bancaires
     * @param int $delaiPaiement Délai de paiement en jours
     * @return bool Retourne true si la génération a réussi
     */
    public function genererPDF($facture, $pdfFileName, $outputDir, $relationsBancaires = [], $delaiPaiement = 30, $signature = [], $printRistourne = false) {
        // Charger TCPDF
        require_once 'vendor/autoload.php';
        
        // Créer une nouvelle instance de TCPDF
        $pdf = new MyTCPDF('P', 'mm', 'A4', true, 'UTF-8');
        
        // Configurer le document
        $pdf->SetCreator('Centre La Grange');
        $pdf->SetAuthor('Centre La Grange');
        $pdf->SetTitle('Facture ' . $facture['numero_facture']);
    
        // Supprimer les en-têtes et pieds de page par défaut
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
    
        // Définir les marges uniformes à 20mm
        $pdf->SetMargins(20, 20, 20);
        $pdf->SetAutoPageBreak(false); // Désactiver l'auto page break pour gérer manuellement
    
        // Calculer les dimensions du cadre (à faire une seule fois)
        $pageWidth = $pdf->getPageWidth();
        $leftMargin = $pdf->getMargins()['left'];
        $rightMargin = $pdf->getMargins()['right'];
        $availableWidth = $pageWidth - $leftMargin - $rightMargin;
    
        $this->frameX = $leftMargin;
        $this->frameWidth = $availableWidth;
        $this->textStartX = $this->frameX + 3; // Padding
    
        // Définir les largeurs des colonnes
        $this->uniteWidth = $this->frameWidth * 0.12; // Augmenter de 8% à 12%
        $this->quantiteWidth = $this->frameWidth * 0.06;
        $this->prixWidth = $this->frameWidth * 0.12;
        $this->totalWidth = $this->frameWidth * 0.16;

        // Calculer la largeur de description en soustrayant les autres colonnes
        $this->descriptionWidth = $this->frameWidth - (
            $this->uniteWidth + 
            $this->quantiteWidth + 
            $this->prixWidth + 
            $this->totalWidth
        );
    
        // Ajouter une page
        $pdf->AddPage();
    
        // Définir la police
        $pdf->SetFont($this->fontRegular, '', 12);
    
        // Générer l'en-tête du document
        $this->genererEnTete($pdf, $facture);
        
        // IMPORTANT : Variable pour suivre si le pied de page a déjà été généré
        $piedDePageGenere = false;
        
        // Générer les détails et passer l'indicateur pour éviter la duplication
        $detailsRequiredNewPage = $this->genererDetails($pdf, $facture, $delaiPaiement, $printRistourne, $relationsBancaires, $signature, $piedDePageGenere);
        
        // Si les détails n'ont pas nécessité de nouvelle page, et que le pied de page n'a pas été généré,
        // générer le pied de page sur la même page
        if (!$detailsRequiredNewPage && !$piedDePageGenere) {
            $this->genererPiedPage($pdf, $relationsBancaires, $delaiPaiement, $signature);
            $piedDePageGenere = true;
        }
        
        // Ajouter la numérotation des pages uniquement si la facture a plus d'une page
        if ($this->totalPages > 1) {
            $this->ajouterNumerotationPages($pdf);
        }
        
        // Enregistrer le PDF
        $fullPath = __DIR__ . DIRECTORY_SEPARATOR . trim($outputDir, '/\\') . DIRECTORY_SEPARATOR . $pdfFileName;
        $pdf->Output($fullPath, 'F');
        
        return true;
    }


    /**
     * Calcule l'espace nécessaire pour afficher les lignes de détail
     * 
     * @param TCPDF $pdf L'objet PDF
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
     * @param TCPDF $pdf L'objet PDF
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
     * @param TCPDF $pdf L'objet PDF
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
        
        // Fermer le cadre sans afficher le total final
        $this->fermerCadre($pdf, false);
        $this->frameOpen = false;
    }

    /**
     * Génère une page avec la dernière ligne de détail et le total
     * 
     * @param TCPDF $pdf L'objet PDF
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
     * @param TCPDF $pdf L'objet PDF
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
     * @param TCPDF $pdf L'objet PDF
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
        $pdf->Cell(0, 5, 'Corcelles-près-Payerne, le ' . $this->dateEnFrancais($facture['date_facture']), 0, 1, 'R');

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
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetTextColor($couleurs[$facture['etat']][0], $couleurs[$facture['etat']][1], $couleurs[$facture['etat']][2]);
            
            // Dessiner un rectangle aux coins légèrement arrondis
            $pdf->SetLineStyle(['width' => 0.5, 'color' => $couleurs[$facture['etat']]]);
            $pdf->SetAlpha(0.3);
            $pdf->RoundedRect(110, 35, 40, 7, 1, '1111', 'D');
            
            // Centrer le texte horizontalement dans le rectangle
            $text = $facture['etat'];
            $textWidth = $pdf->GetStringWidth($text);
            $x = 110 + (40 - $textWidth) / 2;
            $pdf->SetXY($x, 35);
            $pdf->Cell($textWidth, 7, $text, 0, 1, 'L');
            
            // Réinitialiser
            $pdf->SetAlpha(1);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont($this->fontRegular, '', 12);
            
            $pdf->Ln(15); // Petit espace après le tampon
        }
    
        // Adresse du client (à droite) commençant à la 10ème ligne
        $clientAddressX = 110;
        $lineHeight = 5;
        
        // Positionnement pour l'adresse client (10ème ligne)
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
        
        // Ligne vide séparant l'adresse du titre de la facture
        $pdf->Ln(5);
        
        // Titre de la facture
        $pdf->SetFont($this->fontBold, '', 12);
        $pdf->Cell(0, 5, 'Facture no ' . $facture['numero_facture'] . ' : Location de salle « La Grange »', 0, 1, 'L');

        // Ligne vide supplémentaire après le titre de la facture
        $pdf->Ln(5);
    }

    /**
     * Génère les détails de la facture dans le PDF avec gestion des sauts de page
     * Version complète pour les cas standards
     * 
     * @param TCPDF $pdf L'objet PDF
     * @param array $facture Données de la facture
     * @param int $delaiPaiement Délai de paiement en jours
     * @return bool Indique si une nouvelle page a été créée
     */
    private function genererDetails($pdf, $facture, $delaiPaiement = 30, $printRistourne = false, $relationsBancaires = [], $signature = [], &$piedDePageGenere = false) {
        // Position actuelle avant de commencer
        $startY = $pdf->GetY();
        
        // Hauteur disponible sur la page actuelle (257mm total - espace déjà utilisé)
        $hauteurDisponible = 257 - $startY;
        
        // Hauteur du pied de page calculée selon les nouvelles données
        $this->hauteurPiedPage = 70; 
        
        // Obtenir les lignes de la facture
        $lignes = $facture['lignes'];
        $nombreLignes = count($lignes);
        
        // Estimer la hauteur nécessaire pour chaque ligne
        $hauteursLignes = [];
        $hauteurTotale = 15; // Espace initial pour "Responsable" et en-têtes de colonnes
        
        foreach ($lignes as $ligne) {
            $nbLines = $pdf->getNumLines($ligne['description'], $this->descriptionWidth);
            $hauteurLigne = ($nbLines > 1) ? (5 * $nbLines + 2) : 9;
            $hauteursLignes[] = $hauteurLigne;
            $hauteurTotale += $hauteurLigne;
        }
        
        // Ajouter l'espace pour les totaux
        $hauteurTotaux = $printRistourne && isset($facture['ristourne']) && $facture['ristourne'] > 0 ? 30 : 15;
        $hauteurTotale += $hauteurTotaux;
        
        // Déterminer si tout peut tenir sur une page
        $toutSurUnePage = ($hauteurTotale + $this->hauteurPiedPage <= $hauteurDisponible);
        
        // Si tout peut tenir sur une page, générer normalement
        if ($toutSurUnePage) {
            // Ouvrir le cadre initial
            $this->frameStartY = $startY;
            $this->frameOpen = true;
            
            // Ajouter la ligne "Responsable"
            $pdf->SetXY($this->textStartX, $this->frameStartY + 2);
            $pdf->SetFont($this->fontRegular, '', 10);
            $pdf->Cell(0, 7, 'Responsable : ' . $facture['prenom'] . ' ' . $facture['nom'], 0, 1, 'L');
    
            // En-têtes des colonnes (première page)
            $this->genererEnTetesColonnes($pdf);
            
            // Générer les lignes
            foreach ($lignes as $ligne) {
                $this->genererLigneDetail($pdf, $ligne);
            }
            
            // Lignes des totaux
            $this->genererLignesTotal($pdf, $facture, $printRistourne);
                       
            // Fermer le cadre final
            $this->fermerCadre($pdf, true);
            $this->frameOpen = false;
            
            // Générer le pied de page UNE SEULE FOIS et marquer comme généré
            $this->genererPiedPage($pdf, $relationsBancaires, $delaiPaiement, $signature);
            $piedDePageGenere = true;
            
            // Une seule page utilisée
            $this->totalPages = 1;
            
            // Pas de nouvelle page créée
            return false;
        }
        
        // Sinon, déterminer combien de pages sont nécessaires
        // et répartir les lignes intelligemment
        
        // Déterminer combien de lignes peuvent tenir sur chaque page
        $pageActuelle = 0;
        $lignesParPage = [];
        $lignesParPage[$pageActuelle] = [];
        $espaceUtilise = 15; // Espace pour "Responsable" et en-têtes
        $espaceDisponiblePremierePage = $hauteurDisponible - 15;
        
        foreach ($lignes as $index => $ligne) {
            $estDerniereLigne = ($index == count($lignes) - 1);
            $espaceNeededForLigne = $hauteursLignes[$index];
            $espaceSupplementaire = $estDerniereLigne ? $hauteurTotaux : 0;
            
            // Déterminer l'espace disponible sur la page actuelle
            $espaceDisponible = ($pageActuelle == 0) ? 
                $espaceDisponiblePremierePage : 
                190; // Espace standard pour les pages suivantes
            
            // Vérifier si la ligne peut tenir sur la page actuelle
            if ($espaceUtilise + $espaceNeededForLigne + $espaceSupplementaire > $espaceDisponible) {
                // Passer à la page suivante
                $pageActuelle++;
                $lignesParPage[$pageActuelle] = [];
                $espaceUtilise = 15; // Réinitialiser l'espace utilisé
            }
            
            // Ajouter la ligne à la page actuelle
            $lignesParPage[$pageActuelle][] = $ligne;
            $espaceUtilise += $espaceNeededForLigne;
        }
        
        // Nombre total de pages nécessaires
        $nombrePages = $pageActuelle + 1;
        $this->totalPages = $nombrePages;
        
        // Génération des pages
        $montantReport = 0;
        
        for ($i = 0; $i < $nombrePages; $i++) {
            $estPremierePage = ($i == 0);
            $estDernierePage = ($i == $nombrePages - 1);
            
            // Si ce n'est pas la première page, créer une nouvelle page
            if (!$estPremierePage) {
                $pdf->AddPage();
                $this->currentPage++;
                $this->genererEnTetePageSuivante($pdf, $facture);
            }
            
            // Début du cadre
            if ($estPremierePage) {
                $this->frameStartY = $startY;
            } else {
                $this->frameStartY = 50; // Position pour les pages suivantes
            }
            $this->frameOpen = true;
            
            // Ajouter la ligne "Responsable"
            $pdf->SetXY($this->textStartX, $this->frameStartY + 2);
            $pdf->SetFont($this->fontRegular, '', 10);
            $pdf->Cell(0, 7, 'Responsable : ' . $facture['prenom'] . ' ' . $facture['nom'], 0, 1, 'L');
            
            // En-têtes des colonnes
            $this->genererEnTetesColonnes($pdf);
            
            // Si ce n'est pas la première page, afficher le montant reporté
            if (!$estPremierePage) {
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
            
            // Générer les lignes de la page courante
            foreach ($lignesParPage[$i] as $ligne) {
                $this->genererLigneDetail($pdf, $ligne);
            }
            
            // Calculer le montant à reporter pour la page suivante
            $montantPageCourante = 0;
            foreach ($lignesParPage[$i] as $ligne) {
                $montantPageCourante += floatval($ligne['total_ligne']);
            }
            
            // Si ce n'est pas la dernière page, ajouter "Montant à reporter"
            if (!$estDernierePage) {
                // Mettre à jour le montant cumulatif
                $montantReport += $montantPageCourante;
                
                // Ajouter la ligne "Montant à reporter"
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
            } else {
                // Si c'est la dernière page, ajouter les lignes des totaux
                $this->genererLignesTotal($pdf, $facture, $printRistourne);
            }
            
            // Fermer le cadre
            $this->fermerCadre($pdf, $estDernierePage);
            $this->frameOpen = false;
            
            // Générer le pied de page uniquement sur la dernière page et si pas déjà généré
            if ($estDernierePage && !$piedDePageGenere) {
                $this->genererPiedPage($pdf, $relationsBancaires, $delaiPaiement, $signature);
                $piedDePageGenere = true; // Marquer comme généré
            }
        }
        
        return true;
    }

    /**
     * Génère un en-tête minimal pour les pages suivantes d'une facture multi-pages
     * 
     * @param TCPDF $pdf L'objet PDF
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
        $pdf->SetFont($this->fontBold, '', 12);
        $pdf->Cell(0, 5, 'Facture no ' . $facture['numero_facture'] . ' : Location de salle « La Grange »', 0, 1, 'L');
        
        // Ajout d'un espacement entre le titre et le début du tableau
        $pdf->Ln(5);
    }
    
	/**
     * Génère une page avec un ensemble spécifique de lignes
     * 
     * @param TCPDF $pdf L'objet PDF
     * @param array $facture Données de la facture
     * @param array $lignes Tableau des lignes à afficher
     * @param bool $premiere Indique s'il s'agit de la première page
     * @param bool $derniere Indique s'il s'agit de la dernière page
     */
    private function genererPageAvecLignesSpecifiques($pdf, $facture, $lignes, $premiere = true, $derniere = false, $printRistourne = false) {
        // Position fixe pour le cadre sur les pages suivantes
        if ($premiere) {
            $this->frameStartY = $pdf->GetY();
        } else {
            $this->frameStartY = 50; // Position ajustée pour laisser de l'espace à l'en-tête minimal
        }
        
        $this->frameOpen = true;
        
        // Ajouter la ligne "Responsable" sur toutes les pages
        $pdf->SetXY($this->textStartX, $this->frameStartY + 2);
        $pdf->SetFont($this->fontRegular, '', 10);
        $pdf->Cell(0, 7, 'Responsable : ' . $facture['prenom'] . ' ' . $facture['nom'], 0, 1, 'L');
        
        // En-têtes des colonnes sur toutes les pages (après "Responsable")
        $this->genererEnTetesColonnes($pdf);
        
        // Si ce n'est pas la première page, afficher le montant reporté
        if (!$premiere) {
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
            
            $pdf->Ln(2); // Espacement réduit
        }
        
        // Générer les lignes spécifiées
        foreach ($lignes as $ligne) {
            $this->genererLigneDetail($pdf, $ligne);
        }
        
        // Calculer le montant à reporter
        $montantPageCourante = 0;
        foreach ($lignes as $ligne) {
            $montantPageCourante += floatval($ligne['total_ligne']);
        }
        
        // Si c'est la première page mais pas la dernière, ajouter "Montant à reporter"
        if (!$derniere) {
            // Montant total à reporter (montant déjà reporté + montant de cette page)
            $montantTotalReport = ($premiere) ? $montantPageCourante : $this->montantReport + $montantPageCourante;
            
            // Stocker pour la page suivante
            $this->montantReport = $montantTotalReport;
            
            // Ajouter la ligne "Montant à reporter"
            $montantFormate = number_format($montantTotalReport, 2, '.', "'");
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
        }
    
        // Si c'est la dernière page, ajouter "Total à payer"
        if ($derniere) {
            // Lignes des totaux
            $this->genererLignesTotal($pdf, $facture, $printRistourne);
        }
    
        // Fermer le cadre
        $this->fermerCadre($pdf, $derniere);
        $this->frameOpen = false;
    }

    /**
    * Génère les en-têtes de colonnes du tableau de détails
    * 
    * @param TCPDF $pdf L'objet PDF
    */
    private function genererEnTetesColonnes($pdf) {
        // En-têtes des colonnes avec fond gris
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetFont($this->fontBold, '', 10);
        
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
    * @param TCPDF $pdf L'objet PDF
    * @param array $ligne Données de la ligne
    */
    private function genererLigneDetail($pdf, $ligne) {
        $pdf->SetFont($this->fontRegular, '', 10);

        // Positionner au début de la ligne
        $pdf->SetX($this->textStartX);

        // Sauvegarder la position de départ
        $lineStartX = $pdf->GetX();
        $lineStartY = $pdf->GetY();

        // Préparer le texte de description complet (description + description_dates)
        $descriptionComplete = $ligne['description'];
        
        // Si description_dates existe et n'est pas vide, l'ajouter avec un retour à la ligne
        if (isset($ligne['description_dates']) && !empty($ligne['description_dates'])) {
            $descriptionComplete .= "\n" . $ligne['description_dates'];
        }

        // Pour les descriptions longues, on doit déterminer combien de lignes seront nécessaires
        $nbLines = $pdf->getNumLines($descriptionComplete, $this->descriptionWidth);

        // Formater les valeurs numériques
        $quantiteFormatee = number_format($ligne['quantite'], 0, '.', "'");
        $prixFormate = number_format($ligne['prix_unitaire'], 2, '.', "'");
        $totalFormate = number_format($ligne['total_ligne'], 2, '.', "'");

        // Position X pour le total (aligné avec marge droite de 3mm)
        $totalXPosition = $this->frameX + $this->frameWidth - 3 - $pdf->GetStringWidth($totalFormate);

        // Afficher les informations d'unité, quantité, etc. alignées avec la première ligne
        if ($nbLines > 1) {
            // Cas d'une description multi-lignes

            // 1. Afficher la première ligne de la description (pour aligner les autres colonnes dessus)
            $pdf->Cell($this->descriptionWidth, 7, '', 0, 0, 'L'); // Cellule vide pour réserver l'espace

            // 2. Afficher le reste des colonnes sur la même ligne
            $pdf->Cell($this->uniteWidth, 7, $ligne['unite'], 0, 0, 'R');
            $pdf->Cell($this->quantiteWidth, 7, $quantiteFormatee, 0, 0, 'R');
            $pdf->Cell($this->prixWidth, 7, $prixFormate, 0, 0, 'R');

            // Position précise pour le total 
            $currentX = $pdf->GetX();
            $currentY = $pdf->GetY();
            $pdf->SetXY($totalXPosition, $currentY);
            $pdf->Cell($pdf->GetStringWidth($totalFormate), 7, $totalFormate, 0, 1, 'R');

            // 3. Revenir au début pour afficher la description complète avec espacement réduit
            $pdf->SetXY($lineStartX, $lineStartY);

            // 4. Utiliser MultiCell avec espacement réduit (5mm au lieu de 7mm)
            $pdf->MultiCell($this->descriptionWidth, 5, $descriptionComplete, 0, 'L', false);
        } else {
            // Cas d'une description sur une seule ligne
            $pdf->Cell($this->descriptionWidth, 7, $descriptionComplete, 0, 0, 'L');
            $pdf->Cell($this->uniteWidth, 7, $ligne['unite'], 0, 0, 'R');
            $pdf->Cell($this->quantiteWidth, 7, $quantiteFormatee, 0, 0, 'R');
            $pdf->Cell($this->prixWidth, 7, $prixFormate, 0, 0, 'R');

            // Position précise pour le total
            $currentX = $pdf->GetX();
            $currentY = $pdf->GetY();
            $pdf->SetXY($totalXPosition, $currentY);
            $pdf->Cell($pdf->GetStringWidth($totalFormate), 7, $totalFormate, 0, 1, 'R');
        }

        // Calculer l'espace occupé par la description
        $descriptionHeight = $nbLines > 1 ? 5 * $nbLines : 7;

        // Déplacer le curseur après la description si elle est multi-lignes
        if ($nbLines > 1) {
            $pdf->SetY($lineStartY + $descriptionHeight);
        }

        // Ajouter un espace après chaque ligne (réduit à une seule ligne vide)
        $pdf->Ln(2);
    }

    /**
     * Génère les lignes de total avec gestion optionnelle de la ristourne
     * 
     * @param TCPDF $pdf L'objet PDF
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
        $pdf->SetFont($this->fontBold, '', 11);
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
    * @param TCPDF $pdf L'objet PDF
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
     * @param TCPDF $pdf L'objet PDF
     * @param array $relationsBancaires Paramètres des coordonnées bancaires
     * @param int $delaiPaiement Délai de paiement en jours
     */
    private function genererPiedPage($pdf, $relationsBancaires = [], $delaiPaiement = 30, $signature = []) {
        
        // Positionner le pied de page à 5mm du bas de la page        
        // Informations de paiement
        $pdf->SetFont($this->fontRegular, '', 12);
        
        // Ajouter la ligne de délai de paiement
        $pdf->Cell(0, 7, 'Paiement net à ' . $delaiPaiement . ' jours.', 0, 1, 'L');
        
        $pdf->Cell(0, 7, 'Montant total à verser à :', 0, 1, 'L');

        // Afficher les informations bancaires depuis les paramètres avec espacement réduit
        $pdf->Cell(0, 5, isset($relationsBancaires['Banque']) ? $relationsBancaires['Banque'] : 'BCV 1001 Lausanne', 0, 1, 'L');
        $pdf->Cell(0, 5, isset($relationsBancaires['IBAN']) ? $relationsBancaires['IBAN'] : 'CH 88 0076 7000 E536 2645 5', 0, 1, 'L');

        // Traitement du champ Beneficiaire
        $beneficiaires = isset($relationsBancaires['Beneficiaire']) 
            ? explode(',', $relationsBancaires['Beneficiaire']) 
            : ['Johanna Cherbuin', 'Chemin du Châtelard 9', '1562 Corcelles-près-Payerne'];

        // Nettoyage des lignes (suppression des espaces en début et fin)
        $beneficiaires = array_map('trim', $beneficiaires);

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
        $pdf->SetFont($this->fontItalic, '', 12); // Italique
        
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
     * @param TCPDF $pdf Objet PDF
     * @param string $text Texte du tampon
     * @param array $couleur Couleur RGB du tampon
     */
    private function ajouterTamponStatut($pdf, $text, $couleur) {
        // Sauvegarder l'état graphique
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetTextColor($couleur[0], $couleur[1], $couleur[2]);
    
        // Obtenir les dimensions de la page
        $pageWidth = $pdf->getPageWidth();
        $leftMargin = $pdf->getMargins()['left'];
    
        // Paramètres du tampon
        $largeur = 40;  // Largeur du tampon
        $hauteur = 10;  // Hauteur du tampon
        $opacite = 0.3; // Opacité du tampon
    
        // Position : 50mm en dessus de la première ligne d'adresse, aligné à gauche
        $x = $leftMargin;
        $y = 50;  // 50mm en dessus de la première ligne
    
        // Sauvegarder l'état graphique
        $pdf->startTransform();
    
        // Définir l'opacité
        $pdf->SetAlpha($opacite);
    
        // Dessiner un rectangle aux coins arrondis
        $pdf->SetLineStyle(['width' => 0.5, 'color' => $couleur]);
        $pdf->RoundedRect($x, $y, $largeur, $hauteur, 2, '1111', 'D');
    
        // Centrer le texte dans le rectangle
        $textWidth = $pdf->GetStringWidth($text);
        $textX = $x + ($largeur - $textWidth) / 2;
        $textY = $y + ($hauteur - 7) / 2;  
    
        // Écrire le texte
        $pdf->SetXY($textX, $textY);
        $pdf->Cell($textWidth, 0, $text, 0, 0, 'C');
    
        // Restaurer l'état graphique
        $pdf->stopTransform();
    
        // Réinitialiser les paramètres
        $pdf->SetAlpha(1);
        $pdf->SetTextColor(0, 0, 0);
    }
    
    /**
    * Formater une date au format français
    * 
    * @param string $date Date au format Y-m-d
    * @return string Date formatée (ex: 26 mars 2025)
    */
    private function dateEnFrancais($date) {
        // Convertir la chaîne de date en objet DateTime
        $dateObj = new DateTime($date);

        $formatter = new IntlDateFormatter(
            'fr_FR',                    // Locale française
            IntlDateFormatter::NONE,    // Pas de style pour l'heure
            IntlDateFormatter::NONE,    // Pas de style pour la date
            null,                       // Fuseau horaire par défaut
            null,                       // Calendrier par défaut
            'dd MMMM yyyy'              // Format personnalisé
        );

        return $formatter->format($dateObj);
    }
}
?>