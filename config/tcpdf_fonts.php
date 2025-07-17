<?php
// config/tcpdf_fonts.php

// Chemins des polices personnalisées
// define('K_PATH_FONTS', __DIR__ . '/vendor/tecnickcom/tcpdf/fonts/');
error_log("dans config/tcpdf_fonts.php - K_PATH_FONTS: " . K_PATH_FONTS);

class CustomFontLoader {
    /**
     * Charger une police personnalisée
     * @param string $fontName Nom de la police
     * @return string|false Nom de la police TCPDF ou false si échec
     */
    public static function loadFont($fontName) {
        // Chemins des fichiers de police
        $fontPaths = [
            'liberationserif' => [
                'php' => K_PATH_FONTS . 'liberationserif.php',
                'ttf' => __DIR__ . '/../assets/fonts/LiberationSerif-Regular.ttf'
            ],
            'liberationserifb' => [
                'php' => K_PATH_FONTS . 'liberationserifb.php',
                'ttf' => __DIR__ . '/../assets/fonts/LiberationSerif-Bold.ttf'
            ],
            'liberationserifi' => [
                'php' => K_PATH_FONTS . 'liberationserifi.php',
                'ttf' => __DIR__ . '/../assets/fonts/LiberationSerif-Italic.ttf'
            ]
        ];

        // Vérifier si le fichier PHP de police existe
        if (isset($fontPaths[$fontName]) && file_exists($fontPaths[$fontName]['php'])) {
            return $fontName;
        }

        // Si le fichier PHP n'existe pas, essayer de convertir le TTF
        if (isset($fontPaths[$fontName]) && file_exists($fontPaths[$fontName]['ttf'])) {
            try {
                // Convertir le fichier TTF en police TCPDF
                $convertedFont = TCPDF_FONTS::addTTFfont(
                    $fontPaths[$fontName]['ttf'], 
                    'TrueTypeUnicode', 
                    '', 
                    96, 
                    K_PATH_FONTS . $fontName
                );
                
                error_log("Police convertie : {$fontName}");
                return $fontName;
            } catch (Exception $e) {
                error_log("Erreur de conversion de police : " . $e->getMessage());
            }
        }

        // Utiliser une police par défaut si la conversion échoue
        error_log("Police non trouvée : {$fontName}. Utilisation de la police par défaut.");
        return 'helvetica';
    }
}

// Charger TCPDF
require_once 'vendor/tecnickcom/tcpdf/tcpdf.php';