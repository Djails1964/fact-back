<?php
// config/tcpdf_fonts.php

// Chemins des polices personnalisées
define('K_PATH_FONTS', dirname(__DIR__) . '/vendor/tecnickcom/tcpdf/fonts/');
error_log("dans config/tcpdf_fonts.php - K_PATH_FONTS: " . K_PATH_FONTS);

// Inclure la compatibilité cURL
require_once __DIR__ . '/CurlCompatibility.php';

// Configurer les constantes TCPDF pour le chargement de ressources
define('K_TCPDF_THROWS_EXCEPTION', true);

// Remplacer les méthodes de chargement de fichiers/URL par une version plus résiliente
class CustomTCPDFLoader {
    /**
     * Charger du contenu à partir d'une URL
     */
    public static function loadUrlContent($url) {
        return CurlCompatibility::getUrlContent($url);
    }

    /**
     * Vérifier l'existence d'une URL
     */
    public static function urlExists($url) {
        try {
            $content = self::loadUrlContent($url);
            return $content !== false;
        } catch (Exception $e) {
            error_log("Vérification URL échouée : " . $e->getMessage());
            return false;
        }
    }
}

// Surcharger la méthode statique de TCPDF_STATIC si nécessaire
if (class_exists('TCPDF_STATIC')) {
    class TCPDF_STATIC extends TCPDF_STATIC {
        public static function url_exists($url) {
            return CustomTCPDFLoader::urlExists($url);
        }

        public static function fileGetContents($file) {
            // Méthode personnalisée pour charger des fichiers
            if (preg_match('%^(https?|ftp)://%', $file)) {
                return CustomTCPDFLoader::loadUrlContent($file);
            }
            return @file_get_contents($file);
        }
    }
}

// Fonctions utilitaires pour les polices
class CustomFontLoader {
    /**
     * Charger une police personnalisée
     * @param string $fontName Nom de la police
     * @return string|false Nom de la police TCPDF ou false si échec
     */
    public static function loadFont($fontName) {
        $fontMap = [
            'liberationserif' => 'liberationserif',
            'liberationserifb' => 'liberationserifb',
            'liberationserifi' => 'liberationserifi',
            'liberationserifbi' => 'liberationserifbi'
        ];

        if (!isset($fontMap[$fontName])) {
            error_log("Police non reconnue : $fontName");
            return false;
        }

        $fontPath = K_PATH_FONTS . $fontMap[$fontName] . '.php';
        
        if (!file_exists($fontPath)) {
            error_log("Fichier de police introuvable : $fontPath");
            return false;
        }

        return $fontMap[$fontName];
    }

    /**
     * Vérifier et charger toutes les polices
     * @return array Liste des polices chargées
     */
    public static function initializeFonts() {
        $loadedFonts = [];
        $fonts = [
            'liberationserif',
            'liberationserifb',
            'liberationserifi',
            'liberationserifbi'
        ];

        foreach ($fonts as $font) {
            $loadedFont = self::loadFont($font);
            if ($loadedFont) {
                $loadedFonts[] = $loadedFont;
            }
        }

        return $loadedFonts;
    }
}

// Initialiser les polices au chargement
$loadedFonts = CustomFontLoader::initializeFonts();

// Log des polices chargées
if (!empty($loadedFonts)) {
    error_log("Polices TCPDF chargées : " . implode(', ', $loadedFonts));
} else {
    error_log("Aucune police TCPDF n'a pu être chargée");
}