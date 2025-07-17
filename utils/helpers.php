<?php
/**
 * helpers.php - Fonctions utilitaires globales pour l'application
 */

 if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__)); // Chemin absolu vers la racine du projet
    error_log("APP_ROOT défini dans helpers.php: " . APP_ROOT);
 }

 if (!function_exists('factures_path')) {
    /**
     * Génère un chemin absolu vers le répertoire des factures
     * @param string $filename Nom du fichier de facture
     * @param PDO|ServiceParametre|null $conn Connexion à la base de données ou service paramètre
     * @return string Chemin absolu complet
     */
    function factures_path($filename = '', $conn = null) {
        // Log de débogage pour APP_ROOT
        error_log("factures_path - APP_ROOT est défini à: " . APP_ROOT);
        
        // Si une connexion est fournie, récupérer le paramètre outputDir
        $outputDir = 'storage/factures'; // Valeur par défaut
    
        if ($conn !== null) {
            try {
                // Code existant...
                // Ajouter un log après récupération du paramètre
                // error_log("factures_path - outputDir récupéré des paramètres: " . ($paramResult['success'] ? $paramResult['parametre']['Valeur_parametre'] : 'échec'));
            } catch (Exception $e) {
                error_log('Erreur lors de la récupération du paramètre outputDir: ' . $e->getMessage());
            }
        }
    
        error_log("factures_path - outputDir final: " . $outputDir);
    
        // Déterminer si le chemin est déjà absolu
        $isAbsolutePath = (
            // Windows: commence par lettre suivie de :\
            preg_match('/^[A-Za-z]:\\\\/', $outputDir) || 
            // Unix: commence par /
            preg_match('/^\//', $outputDir)
        );
        
        error_log("factures_path - Le chemin est-il absolu? " . ($isAbsolutePath ? 'oui' : 'non'));
    
        if ($isAbsolutePath) {
            $basePath = $outputDir;
            error_log("factures_path - Utilisation du chemin absolu tel quel: " . $basePath);
        } else {
            $basePath = APP_ROOT . '/' . $outputDir;
            error_log("factures_path - Chemin combiné avec APP_ROOT: " . $basePath);
        }
    
        // Ajouter le nom de fichier si fourni
        $finalPath = $filename ? $basePath . '/' . $filename : $basePath;
        error_log("factures_path - Chemin final retourné: " . $finalPath);
        
        // Vérifier si le chemin existe
        error_log("factures_path - Le chemin existe-t-il? " . (file_exists($finalPath) ? 'oui' : 'non'));
        
        return $finalPath;
    }
}

if (!function_exists('factures_url')) {
    /**
     * Génère une URL accessible pour un fichier de facture
     * @param string $filename Nom du fichier de facture
     * @param PDO|ServiceParametre|null $conn Connexion à la base de données ou service paramètre
     * @return string URL accessible pour le fichier
     */
    function factures_url($filename = '', $conn = null) {
        // Déterminer le chemin relatif (avec la même logique que factures_path)
        $outputDir = 'storage/factures'; // Valeur par défaut
        
        if ($conn !== null) {
            try {
                // Si c'est un objet ServiceParametre
                if ($conn instanceof ServiceParametre) {
                    $paramResult = $conn->getParametre('outputDir', 'Facture');
                    if ($paramResult['success'] && isset($paramResult['parametre']['Valeur_parametre'])) {
                        $outputDir = $paramResult['parametre']['Valeur_parametre'];
                    }
                }
                // Si c'est une connexion PDO
                else if ($conn instanceof PDO) {
                    // Créer temporairement un ServiceParametre
                    require_once APP_ROOT . '/ServiceParametre.php';
                    $serviceParametre = new ServiceParametre($conn);
                    $paramResult = $serviceParametre->getParametre('outputDir', 'Facture');
                    if ($paramResult['success'] && isset($paramResult['parametre']['Valeur_parametre'])) {
                        $outputDir = $paramResult['parametre']['Valeur_parametre'];
                    }
                }
            } catch (Exception $e) {
                error_log('Erreur lors de la récupération du paramètre outputDir: ' . $e->getMessage());
                // En cas d'erreur, on garde la valeur par défaut
            }
        }
        
        // Construire l'URL
        error_log("helpers.php - factures_url - outputDir utilisé: " . $outputDir);
        error_log("helpers.php - factures_url - filename: " . $filename);
        return app_url(trim($outputDir, '/') . ($filename ? '/' . $filename : ''));

    }
}

if (!function_exists('app_url')) {
    /**
     * Génère une URL complète incluant le chemin de base de l'application
     * 
     * @param string $path Chemin relatif (commençant par /)
     * @return string URL complète
     */
    function app_url($path = '') {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        error_log("app_url - Protocole détecté: {$protocol}");
        $host = $_SERVER['HTTP_HOST'];
        error_log("app_url - Host détecté: {$host}");
        
        // Utiliser la configuration ou détecter automatiquement
        error_log("app_url - Chemin de base de l'application: " . env('APP_BASE_PATH'));
        // $basePath = env('APP_BASE_PATH', '');
        if (null !== env('APP_BASE_PATH')) {
            $basePath = env('APP_BASE_PATH');
        } else {
            // Détection automatique
            $scriptName = $_SERVER['SCRIPT_NAME'];
            error_log("app_url - SCRIPT_NAME détecté: {$scriptName}");
            // Pour /fact-back/reset_password.php, on veut /fact-back
            $dirParts = explode('/', dirname($scriptName));
            error_log("app_url - Dossier du script: " . print_r($dirParts, true));
            // Si le script est à la racine d'un projet (ex: /fact-back/script.php)
            $applicationRoot = !empty($dirParts[1]) ? '/' . $dirParts[1] : '';
            error_log("app_url - Application root détecté: {$applicationRoot}");
            $basePath = $applicationRoot;
        }
        error_log("app_url - Base path utilisé: {$basePath}");

        // S'assurer que le chemin commence par '/'
        error_log("app_url - Chemin avant ajustement: {$path}");
        if (!empty($path) && $path[0] !== '/') {
            $path = '/' . $path;
        }
        error_log("app_url - Chemin ajusté: {$path}");
        
        error_log("app_url - Génération de l'URL: {$protocol}://{$host}{$basePath}{$path}");
        return $protocol . '://' . $host . $basePath . $path;
    }
}

if (!function_exists('normalizeForFilename')) {
    /**
     * Normalise une chaîne pour utilisation dans un nom de fichier
     * Supprime les accents et caractères spéciaux
     * 
     * @param string $string Chaîne à normaliser
     * @return string Chaîne normalisée sans accents ni caractères spéciaux
     */
    function normalizeForFilename($string) {
        if (empty($string)) {
            return '';
        }
        
        error_log("normalizeForFilename - Chaîne d'entrée: " . $string);
        
        // Méthode 1 : transliterator (recommandé si disponible)
        if (function_exists('transliterator_transliterate')) {
            $normalized = transliterator_transliterate('Any-Latin; Latin-ASCII', $string);
            if ($normalized !== false) {
                // Nettoyer les caractères restants
                $normalized = preg_replace('/[^a-zA-Z0-9._-]/', '', $normalized);
                error_log("normalizeForFilename - Résultat transliterator: " . $normalized);
                return $normalized;
            }
        }
        
        // Méthode 2 : iconv (fallback)
        if (function_exists('iconv')) {
            $normalized = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
            if ($normalized !== false) {
                // Nettoyer les caractères restants (iconv peut laisser des apostrophes)
                $normalized = preg_replace("/[^a-zA-Z0-9._-]/", '', $normalized);
                error_log("normalizeForFilename - Résultat iconv: " . $normalized);
                return $normalized;
            }
        }
        
        // Méthode 3 : Remplacement manuel (dernière option)
        $accents = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n', 'ÿ' => 'y',
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C', 'Ñ' => 'N', 'Ÿ' => 'Y'
        ];
        
        $normalized = strtr($string, $accents);
        // Supprimer tout caractère non-alphanumérique sauf points, tirets et underscores
        $normalized = preg_replace('/[^a-zA-Z0-9._-]/', '', $normalized);
        
        error_log("normalizeForFilename - Résultat manuel: " . $normalized);
        return $normalized;
    }
}

// Vous pouvez ajouter d'autres fonctions utilitaires ici