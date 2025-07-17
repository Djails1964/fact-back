<?php
// config/CurlCompatibility.php

class CurlCompatibility {
    /**
     * Obtenir la valeur de constante cURL avec un fallback
     */
    public static function getConstant($constantName, $fallbackValue) {
        return defined($constantName) ? constant($constantName) : $fallbackValue;
    }

    /**
     * Obtenir les options cURL par défaut
     */
    public static function getCurlOptions() {
        return [
            self::getConstant('CURLOPT_CONNECTTIMEOUT', 78) => 5,
            self::getConstant('CURLOPT_MAXREDIRS', 68) => 5,
            self::getConstant('CURLOPT_PROTOCOLS', 181) => 3443,
            self::getConstant('CURLOPT_SSL_VERIFYHOST', 81) => 2,
            self::getConstant('CURLOPT_SSL_VERIFYPEER', 64) => true,
            self::getConstant('CURLOPT_TIMEOUT', 13) => 30,
            self::getConstant('CURLOPT_USERAGENT', 10209) => 'TCPDF',
            self::getConstant('CURLOPT_FAILONERROR', 45) => true,
            self::getConstant('CURLOPT_RETURNTRANSFER', 19) => true
        ];
    }

    /**
     * Méthode utilitaire pour effectuer des requêtes cURL sécurisées
     */
    public static function getUrlContent($url) {
        // Vérifier si cURL est disponible
        if (!function_exists('curl_init')) {
            error_log("cURL non disponible. Utilisation de file_get_contents.");
            return @file_get_contents($url);
        }

        // Initialiser cURL
        $ch = curl_init();

        // Obtenir les options cURL
        $options = self::getCurlOptions();

        // Configurer les options
        $options[CURLOPT_URL] = $url;
        $options[CURLOPT_RETURNTRANSFER] = true;

        // Appliquer les options
        curl_setopt_array($ch, $options);

        // Exécuter la requête
        $content = curl_exec($ch);

        // Gestion des erreurs
        if ($content === false) {
            $error = curl_error($ch);
            error_log("Erreur cURL : " . $error);
            curl_close($ch);
            return false;
        }

        // Fermer la connexion
        curl_close($ch);

        return $content;
    }
}