<?php
// convert_fonts.php - version améliorée
require_once 'vendor/autoload.php';

// S'assurer que nous avons la dernière version de TCPDF
require_once 'vendor/tecnickcom/tcpdf/tcpdf.php';

// Répertoires
$fontDir = __DIR__ . '/assets/fonts/';
$tcpdfFontDir = __DIR__ . '/vendor/tecnickcom/tcpdf/fonts/';

// Nettoyage préalable
$oldFonts = glob($tcpdfFontDir . 'liberationserif*.{php,z}', GLOB_BRACE);
foreach ($oldFonts as $oldFont) {
    unlink($oldFont);
    echo "Suppression du fichier existant : " . basename($oldFont) . "\n";
}

// Définition des polices à convertir
$fonts = [
    'LiberationSerif-Regular.ttf' => 'liberationserif',
    'LiberationSerif-Bold.ttf' => 'liberationserifb',
    'LiberationSerif-Italic.ttf' => 'liberationserifi',
    'LiberationSerif-BoldItalic.ttf' => 'liberationserifbi',
];

// Conversion des polices
foreach ($fonts as $sourceFont => $destFont) {
    $sourcePath = $fontDir . $sourceFont;
    
    if (file_exists($sourcePath)) {
        echo "Conversion de $sourceFont... ";
        try {
            // Utiliser la méthode la plus simple sans spécifier de chemin personnalisé
            $fontName = TCPDF_FONTS::addTTFfont($sourcePath, 'TrueTypeUnicode', '', 96);
            
            // Vérifier si le fichier a été créé correctement
            if (file_exists($tcpdfFontDir . $fontName . '.php')) {
                echo "Réussi ! Fichier créé : " . $fontName . ".php\n";
            } else {
                echo "Échec ! Le fichier n'a pas été créé.\n";
            }
        } catch (Exception $e) {
            echo "Erreur : " . $e->getMessage() . "\n";
        }
    } else {
        echo "Fichier source non trouvé : $sourcePath\n";
    }
}

echo "\nProcessus terminé. Vérifiez les polices dans : $tcpdfFontDir\n";