<?php
/**
 * PDFGeneratorFactory.php
 * 
 * Factory pour créer l'implémentation appropriée du générateur de PDF
 */

require_once 'PDFGeneratorInterface.php';

class PDFGeneratorFactory {
    /**
     * Crée l'instance appropriée du générateur de PDF
     * 
     * @param string $type Type de générateur ('tcpdf' ou 'fpdi')
     * @return PDFGeneratorInterface Instance du générateur de PDF
     * @throws Exception Si le type spécifié n'est pas supporté
     */
    public static function create($type = 'tcpdf') {
        switch (strtolower($type)) {
            case 'tcpdf':
                require_once 'TCPDFFactureGenerator.php';
                return new TCPDFFactureGenerator();
                
            case 'fpdi':
                require_once 'FPDIFactureGenerator.php';
                return new FPDIFactureGenerator();
                
            default:
                throw new Exception("Type de générateur PDF non supporté: {$type}");
        }
    }
}