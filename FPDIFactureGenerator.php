<?php

/**
 * FPDIFactureGenerator.php
 *
 * Générateur PDF pour les FACTURES du Centre La Grange.
 * Étend AbstractPDFGenerator (PDFBase.php) pour partager l'en-tête,
 * le pied de page, la fermeture de cadre et les utilitaires communs.
 */

require_once 'PDFBase.php';

class FPDIFactureGenerator extends AbstractPDFGenerator {
    /**
     * Titre principal de la facture (utilisé par AbstractPDFGenerator::genererEnTete)
     */
    protected function getTitrePrincipal(array $data): string {
        $num = $data['numero_facture'] ?? '';
        return 'Facture no ' . $num . ' : Location de salle « La Grange »';
    }

    
    
    private $descriptionWidth;
    private $uniteWidth;
    private $quantiteWidth;
    private $prixWidth;
    private $totalWidth;
    
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
        error_log("genererPDF - Facture reçue: " . json_encode($facture));   
        
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

        error_log("📄 Génération du PDF pour la facture " . $facture['numero_facture'] . " avec " . $nombreLignes . " lignes sur " . $this->totalPages . " pages.");
        
        // Tableau pour stocker les chemins des PDF temporaires
        $tempPDFFiles = [];
        
        // Montant cumulé pour les reports
        $montantReport = 0;
        $lignesTraitees = 0;
        
        // Traiter chaque page
        for ($pageActuelle = 1; $pageActuelle <= $this->totalPages; $pageActuelle++) {
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
            
            // Préparer date_document pour AbstractPDFGenerator::genererEnTete
            if (!isset($facture['date_document'])) {
                $facture['date_document'] = $facture['date_facture'] ?? date('Y-m-d');
            }

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
            
            // Calculer les lignes pour cette page
            // Utiliser la méthode dynamique si disponible, sinon méthode simple
            error_log("Calcul des lignes pour la page $pageActuelle");
            if (method_exists($this, 'calculerNombreLignesQuiTiennent') && method_exists($this, 'calculerEspaceDisponible')) {
                try {
                    $espaceDisponible = $this->calculerEspaceDisponible($pdf, $facture, $relationsBancaires, ($pageActuelle == 1));
                    $lignesRestantes = array_slice($lignes, $lignesTraitees);
                    $espaceRestantApresEnTetes = $espaceDisponible - ($pdf->GetY() - $startY);
                    $lignesQuiTiennent = $this->calculerNombreLignesQuiTiennent($pdf, $lignesRestantes, $espaceRestantApresEnTetes, false);
                    error_log("📋 Page $pageActuelle: calcul dynamique - {$lignesQuiTiennent} lignes peuvent tenir");
                } catch (Exception $e) {
                    error_log("⚠️ Erreur calcul dynamique lignes, utilisation méthode simple: " . $e->getMessage());
                    $lignesQuiTiennent = min($maxLignesParPage, $nombreLignes - $lignesTraitees);
                }
            } else {
                // Méthode simple
                $lignesQuiTiennent = min($maxLignesParPage, $nombreLignes - $lignesTraitees);
                error_log("📋 Page $pageActuelle: calcul simple - {$lignesQuiTiennent} lignes");
            }
            
            // Calculer l'index de fin pour les lignes de cette page
            $endIndex = min($lignesTraitees + $lignesQuiTiennent - 1, $nombreLignes - 1);
            $estDernierePage = ($endIndex == $nombreLignes - 1);
            
            // Générer les lignes pour cette page
            error_log("Génération des lignes de détail pour la page $pageActuelle (lignes " . ($lignesTraitees + 1) . " à " . ($endIndex + 1) . ")");
            for ($i = $lignesTraitees; $i <= $endIndex; $i++) {
                $this->genererLigneDetail($pdf, $lignes[$i]);
                $montantReport += floatval($lignes[$i]['total_ligne']);
            }
            error_log("Lignes de détail générées pour la page $pageActuelle");
            
            // Mettre à jour le nombre de lignes traitées
            $lignesTraitees = $endIndex + 1;
            
            // Si c'est la dernière page
            if ($estDernierePage) {
                // Générer les totaux
                error_log("Génération des totaux pour la page $pageActuelle");
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
                // Sinon, ajouter "Montant à reporter"
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
     * Fonctionne avec ou sans objet PDF
     * 
     * @param FPDI|null $pdf L'objet PDF (peut être null pour estimation)
     * @param array $ligne Données de la ligne
     * @return float Hauteur estimée en mm
     */
    private function calculerHauteurLigne($pdf, $ligne) {
        error_log("=== Calcul de la hauteur de la ligne ===");
        error_log("Ligne reçue: " . json_encode($ligne));
        error_log("PDF disponible: " . ($pdf ? "oui" : "non"));
        error_log("is_array(ligne): " . (is_array($ligne) ? "oui" : "non"));
        
        // Vérification de sécurité
        if (!$ligne || !is_array($ligne)) {
            return 12; // Hauteur par défaut
        }
        
        // Préparer le texte de description complet
        $descriptionComplete = isset($ligne['description']) ? $ligne['description'] : '';
        
        if (isset($ligne['description_dates']) && !empty(trim($ligne['description_dates']))) {
            $descriptionComplete .= "\n" . $ligne['description_dates'];
        }
        
        if (empty(trim($descriptionComplete))) {
            return 12;
        }
        
        // SOLUTION : Utiliser TOUJOURS la simulation qui fonctionne
        $nbLines = $this->simulerGetNumLines($descriptionComplete);
        error_log("Nombre de lignes via simulation: " . $nbLines);
        
        // Appliquer le même algorithme de calcul de hauteur
        $hauteurLigne = ($nbLines > 1) ? ($nbLines * 5 + 4) : 10;
        error_log("Hauteur de ligne calculée: " . $hauteurLigne . " mm");
        error_log("=== Fin du calcul de la hauteur de ligne ===");

        return $hauteurLigne + 2;
    }

    /**
     * Simule le comportement de getNumLines() sans objet PDF
     * 
     * @param string $texte Le texte à analyser
     * @return int Nombre de lignes nécessaires
     */
    private function simulerGetNumLines($texte) {
        error_log("=== Simulation du nombre de lignes (version améliorée) ===");
        error_log("Texte reçu: " . $texte);
        
        if (empty(trim($texte))) {
            return 1;
        }
        
        // Largeur disponible en mm
        $largeurColonne = $this->descriptionWidth ?? 100;
        error_log("Largeur disponible: " . $largeurColonne . "mm");
        
        // Créer un objet PDF temporaire pour les calculs de largeur
        // Utiliser la même configuration que le PDF principal
        $tempPdf = new MyFPDI('P', 'mm', 'A4');
        $tempPdf->AddPage();
        $tempPdf->SetFont($this->fontRegular, '', 10); // Même police que les détails
        
        // Séparer par les retours à la ligne explicites
        $paragraphes = explode("\n", $texte);
        $totalLignes = 0;
        
        foreach ($paragraphes as $paragraphe) {
            if (empty(trim($paragraphe))) {
                $totalLignes += 1; // Ligne vide
                continue;
            }
            
            // Calculer le word wrapping pour ce paragraphe avec mesure précise
            $mots = explode(' ', trim($paragraphe));
            $ligneActuelle = '';
            $lignesParagraphe = 1; // Au moins une ligne
            
            foreach ($mots as $mot) {
                $testLigne = empty($ligneActuelle) ? $mot : $ligneActuelle . ' ' . $mot;
                
                // Mesurer la largeur réelle du texte en mm
                $largeurTexte = $tempPdf->GetStringWidth($testLigne);
                error_log("Test ligne: '$testLigne' -> Largeur: {$largeurTexte}mm");
                
                if ($largeurTexte <= $largeurColonne) {
                    // Le texte tient sur la ligne actuelle
                    $ligneActuelle = $testLigne;
                } else {
                    // Le texte dépasse, passer à la ligne suivante
                    $lignesParagraphe++;
                    $ligneActuelle = $mot; // Commencer une nouvelle ligne avec ce mot
                }
            }
            
            $totalLignes += $lignesParagraphe;
            error_log("Paragraphe '{$paragraphe}' -> {$lignesParagraphe} lignes");
        }
        
        error_log("Total lignes calculées: " . $totalLignes);
        error_log("=== Fin simulation améliorée ===");
        
        return $totalLignes;
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
     * Génère un en-tête minimal pour les pages suivantes d'une facture multi-pages
     * 
     * @param FPDI $pdf L'objet PDF
     * @param array $facture Données de la facture
     */
    protected function genererEnTetePageSuivante(MyFPDI $pdf, array $data): void {
        // Ajouter le logo en haut à gauche
        $logoPath = __DIR__ . '/assets/logo.png';
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 20, 20, 30);
        }

        // Position pour la date (alignée à droite)
        $pdf->SetY(20); 
        $pdf->Cell(0, 5, 'Corcelles-près-Payerne, le ' . $this->dateEnFrancais($data['date_facture']), 0, 1, 'R');

        // Positionnement pour le titre de la facture (sans adresse)
        $pdf->SetY(40); // Position fixe pour l'en-tête des pages suivantes
        
        // Titre de la facture
        $pdf->SetFont($this->fontBold, 'B', 12);
        $pdf->Cell(0, 5, 'Facture no ' . $data['numero_facture'] . ' : Location de salle « La Grange »', 0, 1, 'L');
        
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
        // ✅ Sauf si l'unité autorise la saisie d'une durée (permet_multiplicateur)
        // et qu'une durée a été saisie sur cette ligne : dans ce cas, afficher
        // "durée + abréviation" (ex: "1:30 hr") à la place du nom de l'unité.
        $permetMultiplicateur = !empty($ligne['permet_multiplicateur']);
        $dureeLigne           = $ligne['duree'] ?? null;
        if ($permetMultiplicateur && !empty($dureeLigne)) {
            $abrevUnite    = $ligne['abreviation_unite'] ?? ($ligne['unite_code'] ?? $ligne['unite'] ?? '');
            $uniteAffichee = trim($dureeLigne . ($abrevUnite ? ' ' . $abrevUnite : ''));
        } else {
            $uniteAffichee = isset($ligne['unite_code']) ? $ligne['unite_code'] : $ligne['unite'];
        }
        
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
     * Calcule la hauteur totale de l'en-tête de la facture
     * 
     * @param array $facture Données de la facture
     * @return float Hauteur totale en mm
     */
    private function calculerHauteurEnTete($facture) {
        error_log('=== CALCULER HAUTEUR EN TETE ===');
        $hauteur = 0;
        
        // Logo et date (20mm depuis le haut)
        $hauteur += 20;
        
        // Adresse du centre (3 lignes de 5mm + espacement de 5mm)
        $hauteur += (3 * 5) + 5 + 10; // = 25mm (ajout de 2 lignes vides supplémentaires)
        
        // Tampon si présent (pour factures Payées/Annulées)
        if (isset($facture['etat']) && ($facture['etat'] === 'Payée' || $facture['etat'] === 'Annulée')) {
            $hauteur += 15; // Espacement après le tampon
        }
        
        // Adresse du client (titre + nom + adresse + ligne vide + code postal/localité)
        $lignesClient = 0;
        if (!empty(trim($facture['titre']))) {
            $lignesClient += 1; // Titre
        }
        $lignesClient += 4; // nom, rue+numéro, ligne vide, code postal+localité
        $hauteur += $lignesClient * 5; // = 20-25mm selon si titre présent
        
        // Lignes vides ajoutées
        $hauteur += 10; // 2 lignes vides avant le titre (Ln(10))
        
        // Titre de la facture
        $hauteur += 5; // hauteur du titre
        
        // Ligne vide après le titre
        $hauteur += 5; // Ligne vide après le titre (Ln(5))
        
        return $hauteur;
    }

    /**
     * Calcule la hauteur totale nécessaire pour le pied de page
     * 
     * @param array $relationsBancaires Paramètres des coordonnées bancaires
     * @return float Hauteur totale en mm (contenu + espacement + marge)
     */
    private function calculerHauteurPiedPage($relationsBancaires = []) {
        error_log('=== CALCULER HAUTEUR PIED DE PAGE ===');
        // Calculer la hauteur du contenu du pied de page
        $beneficiaires = isset($relationsBancaires['Beneficiaire']) 
            ? explode(',', $relationsBancaires['Beneficiaire']) 
            : ['Johanna Cherbuin', 'Chemin du Châtelard 9', '1562 Corcelles-près-Payerne'];
        $beneficiaires = array_map('trim', $beneficiaires);
        
        // Estimation de la hauteur du contenu
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
        
        // Ajouter l'espacement de 15mm au-dessus de la marge du bas + la marge du bas (20mm)
        return $hauteurContenu + 15 + 20;
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
        error_log('=== CALCULER ESPACE DISPONIBLE ===');
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
    /**
     * Implémentation de AbstractPDFGenerator::calculerNombrePages
     */
    /**
     * Implémentation de AbstractPDFGenerator::genererContenu
     * Note : la logique multi-pages est gérée dans genererPDF()
     * Cette méthode est un stub requis par l'interface abstraite.
     */
    protected function genererContenu(MyFPDI $pdf, array $data, array $params, int $page): void {
        // Le flux de génération de facture est géré entièrement dans genererPDF()
        // car il nécessite une gestion fine des pages multiples et des reports.
    }

    protected function calculerNombrePages(array $data, array $params): int {
        return $this->calculerNombreTotalPages($data, $params);
    }

    private function calculerNombreTotalPages($facture, $relationsBancaires) {
        error_log('=== CALCULER NOMBRE TOTAL PAGES ===');
        $lignes = $facture['lignes'];
        $pages = 1;
        $lignesTraitees = 0;
        
        while ($lignesTraitees < count($lignes)) {
            // Passer null comme PDF car on n'a pas encore d'objet PDF
            $espaceDisponible = $this->calculerEspaceDisponible(null, $facture, $relationsBancaires, ($pages == 1));
            
            // Soustraire l'espace pour les en-têtes et responsable (environ 14mm)
            $espaceContenu = $espaceDisponible - 14;
            
            $lignesRestantes = array_slice($lignes, $lignesTraitees);
            // Passer null comme PDF car on n'a pas encore d'objet PDF
            $lignesQuiTiennent = $this->calculerNombreLignesQuiTiennent(null, $lignesRestantes, $espaceContenu, false);
            
            $lignesTraitees += $lignesQuiTiennent;
            
            if ($lignesTraitees < count($lignes)) {
                $pages++;
            }
        }
        
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

        error_log('=== CALCULER NOMBRE LIGNES QUI TIENNENT ===');
        error_log('Espace disponible : ' . $espaceDisponible);
        error_log('Lignes à traiter : ' . count($lignes));
        error_log('estDernierePage : ' . ($estDernierePage ? 'oui' : 'non'));

        // Si c'est la dernière page, réserver l'espace pour les totaux (environ 20mm)
        $espaceReserveTotaux = $estDernierePage ? 20 : 7; // 7mm pour "Montant à reporter"
        error_log('Espace réservé pour totaux : ' . $espaceReserveTotaux);

        for ($i = 0; $i < count($lignes); $i++) {
            // Calculer la hauteur de cette ligne
            $hauteurLigne = $this->calculerHauteurLigne($pdf, $lignes[$i]);
            error_log('Hauteur ligne ' . ($i + 1) . ' : ' . $hauteurLigne);
            
            // Vérifier si cette ligne + les totaux tiennent encore
            $espaceAvecLigne = $espaceUtilise + $hauteurLigne + $espaceReserveTotaux;
            error_log('Espace utilisé + ligne + totaux : ' . $espaceAvecLigne);
            
            if ($espaceAvecLigne <= $espaceDisponible) {
                $espaceUtilise += $hauteurLigne;
                $nombreLignes++;
            } else {
                error_log('Nombre de lignes qui tiennent : ' . $nombreLignes);
                error_log('=== FIN CALCULER NOMBRE LIGNES QUI TIENNENT ===');
                break;
            }
        }
        
        // Au moins 1 ligne par page
        error_log('Nombre de lignes qui tiennent : ' . $nombreLignes);
        error_log('=== FIN CALCULER NOMBRE LIGNES QUI TIENNENT ===');
        return max(1, $nombreLignes);
    }
}

?>