<?php
/**
 * PDFGeneratorInterface.php
 * 
 * Interface pour standardiser les générateurs de PDF pour les factures
 * Compatible avec les implémentations TCPDF et FPDI
 */

interface PDFGeneratorInterface {
    /**
     * Génère un fichier PDF pour une facture
     * 
     * @param array $facture Données de la facture et ses lignes
     * @param string $pdfFileName Nom du fichier PDF à générer
     * @param string $outputDir Répertoire de sortie pour le PDF
     * @param array $relationsBancaires Paramètres des coordonnées bancaires
     * @param int $delaiPaiement Délai de paiement en jours
     * @param array $signature Informations de signature
     * @param bool $printRistourne Indique s'il faut imprimer la ristourne
     * @return bool Retourne true si la génération a réussi
     */
    public function genererPDF($facture, $pdfFileName, $outputDir, $relationsBancaires = [], $delaiPaiement = 30, $signature = [], $printRistourne = false);
}