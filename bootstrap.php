<?php
// bootstrap.php - Version améliorée avec gestion du frontend React ET session centralisée

// Vérifier si le bootstrap est déjà chargé (éviter les inclusions multiples)
if (!defined('APP_INITIALIZED')) {
    define('APP_INITIALIZED', true);
    define('APP_ROOT', __DIR__);

    // ============================================
    // GESTION SESSION CENTRALISÉE UNIVERSELLE
    // ============================================

    /**
     * Gère la session de manière centralisée pour toutes les APIs
     * Prend en charge la transmission explicite de PHPSESSID via URL (React/axios)
     */
    function initializeSessionForApi() {
        // Si la session est déjà démarrée, ne rien faire
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        
        // Forcer l'ID de session si passé en paramètre (pour compatibility React/axios)
        if (isset($_GET['PHPSESSID']) && !empty($_GET['PHPSESSID'])) {
            // Valider le format de l'ID de session (sécurité)
            if (preg_match('/^[a-zA-Z0-9,-]{20,40}$/', $_GET['PHPSESSID'])) {
                session_id($_GET['PHPSESSID']);
                
                // Log seulement en mode développement
                if (function_exists('is_dev_mode') && is_dev_mode()) {
                    error_log("🔑 " . basename($_SERVER['PHP_SELF']) . " - Session ID forcé depuis URL: " . $_GET['PHPSESSID']);
                }
            } else {
                // ID de session invalide - log d'alerte sécuritaire
                error_log("⚠️ SÉCURITÉ - Tentative d'injection d'ID de session invalide depuis " . 
                         ($_SERVER['REMOTE_ADDR'] ?? 'IP_INCONNUE') . ": " . $_GET['PHPSESSID']);
            }
        }
        
        // La configuration et le démarrage se feront dans configure_session_for_cross_window()
    }

    // Vérifier si l'autoloader de Composer existe
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
    } else {
        die('Erreur: Composer autoloader non trouvé. Veuillez exécuter "composer install".');
    }

    // Charger les variables d'environnement depuis .env
    try {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->load();

        // Définir les variables obligatoires
        $dotenv->required([
            'APP_ENV', 
            'CORS_ORIGIN', 
            'DB_HOST', 
            'DB_USER', 
            'DB_PASS', 
            'DB_NAME',
            'SESSION_LIFETIME'
        ]);
    } catch (Exception $e) {
        die('Erreur de configuration: ' . $e->getMessage());
    }

    require_once __DIR__ . '/utils/helpers.php';
    
    // Fonction helper pour obtenir une variable d'environnement
    function env($key, $default = null) {
        return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?? $default;
    }

    // Configurer les erreurs en fonction de l'environnement
    $debug_mode = env('APP_ENV') === 'development';
    if ($debug_mode) {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
    } else {
        ini_set('display_errors', 0);
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    }

    // Définir et créer le dossier de logs
    $logDir = realpath(__DIR__ . '/' . env('LOG_DIR', 'logs'));
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }

    error_log('Bootstrap - Chargement de la configuration');
    error_log('Bootstrap - dotEnv: ' . json_encode($dotenv));
    error_log('Bootstrap - Variables d\'environnement: ' . json_encode($_ENV));

    // Configurer la journalisation PHP
    ini_set('log_errors', 1);
    ini_set('error_log', $logDir . '/php-errors.log');

    // Définir le fuseau horaire par défaut
    date_default_timezone_set(env('TIMEZONE', 'Europe/Zurich'));

    // Fonction pour générer des URL sécurisées
    function secure_url($path) {
        $base_url = env('APP_URL_BACK', '');
        return rtrim($base_url, '/') . '/' . ltrim($path, '/');
    }

    // Fonction pour vérifier si l'utilisateur est en mode développement
    function is_dev_mode() {
        return env('APP_ENV') === 'development';
    }

    // Fonction pour obtenir l'URL du frontend React
    function react_url() {
        $isDev = is_dev_mode();
        if ($isDev) {
            $host = env('REACT_HOST', 'localhost');
            $port = env('REACT_PORT', '3007');
            return "http://{$host}:{$port}";
        } else {
            return env('APP_URL_FRONT', 'https://facturation');
        }
    }

    /**
    * Configure les sessions pour un meilleur partage entre fenêtres
    * Améliore la compatibilité cross-window pour les sessions
    * CORRIGÉ: Compatible avec React/JavaScript
    */
    function configure_session_for_cross_window() {
        // Initialiser la session pour les APIs
        initializeSessionForApi();
        
        // Configuration cross-domain spécifique
        if (session_status() === PHP_SESSION_NONE) {
            // SOLUTION: Détecter le contexte cross-domain
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            $isCrossDomainRequest = !empty($origin) && $origin !== 'https://' . ($_SERVER['HTTP_HOST'] ?? '');
            
            if (is_dev_mode()) {
                error_log("🔍 Session cross-domain analysis :");
                error_log("- HTTP_ORIGIN: " . $origin);
                error_log("- HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'undefined'));
                error_log("- Is cross-domain: " . ($isCrossDomainRequest ? 'YES' : 'NO'));
            }
            
            // CORRECTION: Configuration spéciale pour cross-domain DYNAMIQUE
            $allowedOrigins = explode(',', env('CORS_ORIGIN', 'https://dev-facturation'));
            $isAllowedOrigin = in_array($origin, $allowedOrigins);
            
            if ($isCrossDomainRequest && $isAllowedOrigin) {
                // Déterminer le domaine cookie dynamiquement
                $cookieDomain = '';
                
                // Extraire le domaine de base depuis CORS_ORIGIN ou origin
                if (!empty($origin)) {
                    $parsedOrigin = parse_url($origin);
                    $originHost = $parsedOrigin['host'] ?? '';
                    
                    // Si c'est un sous-domaine (ex: dev-facturation-api), utiliser le domaine parent
                    $hostParts = explode('.', $originHost);
                    if (count($hostParts) >= 2) {
                        // Prendre les 2 dernières parties pour le domaine parent
                        $cookieDomain = '.' . implode('.', array_slice($hostParts, -2));
                        
                        // Cas spécial pour les domaines de développement
                        if (strpos($originHost, 'dev-facturation') !== false) {
                            $cookieDomain = '.dev-facturation';
                        } elseif (strpos($originHost, 'localhost') !== false) {
                            $cookieDomain = ''; // Pas de domaine pour localhost
                        }
                    }
                }
                
                // Mode cross-domain: Configuration dynamique
                session_set_cookie_params([
                    'lifetime' => (int)env('SESSION_LIFETIME', 3600),
                    'path' => '/',
                    'domain' => $cookieDomain,     // ← DYNAMIQUE selon l'origin
                    'secure' => true,              // ← OBLIGATOIRE pour cross-domain
                    'httponly' => false,           // ← false pour React
                    'samesite' => 'None'          // ← CRITIQUE pour cross-domain
                ]);
                
                if (is_dev_mode()) {
                    error_log("🌐 Configuration cross-domain DYNAMIQUE appliquée");
                    error_log("- Origin: " . $origin);
                    error_log("- Cookie Domain: " . $cookieDomain);
                    error_log("- SameSite: None");
                    error_log("- Secure: true");
                }
            } else {
                // Mode same-domain: Configuration standard
                session_set_cookie_params([
                    'lifetime' => (int)env('SESSION_LIFETIME', 3600),
                    'path' => '/',
                    'domain' => '',
                    'secure' => is_https(),
                    'httponly' => false,
                    'samesite' => 'Lax'
                ]);
                
                if (is_dev_mode()) {
                    error_log("🏠 Configuration same-domain appliquée");
                }
            }
            
            // Paramètres ini_set de backup
            ini_set('session.cookie_httponly', '0');
            ini_set('session.cookie_samesite', $isCrossDomainRequest ? 'None' : 'Lax');
            ini_set('session.cookie_secure', $isCrossDomainRequest ? '1' : (is_https() ? '1' : '0'));
            ini_set('session.use_strict_mode', '1');
            ini_set('session.gc_maxlifetime', env('SESSION_LIFETIME', 3600));
            
            // DÉMARRER LA SESSION
            session_start();
            
            if (is_dev_mode()) {
                error_log("🔧 Session cross-domain configurée - ID: " . session_id());
                error_log("🍪 Cookie params: " . json_encode(session_get_cookie_params()));
                error_log("🔍 User en session: " . ($_SESSION['user_id'] ?? 'NON CONNECTÉ'));
                
                // IMPORTANT: Forcer l'envoi du cookie avec les bons paramètres
                if ($isCrossDomainRequest && $isAllowedOrigin) {
                    $cookieValue = session_id();
                    $cookieExpires = time() + (int)env('SESSION_LIFETIME', 3600);
                    
                    // Header Set-Cookie manuel pour garantir les bons paramètres
                    $cookieHeader = "PHPSESSID={$cookieValue}; " .
                                "Path=/; " .
                                ($cookieDomain ? "Domain={$cookieDomain}; " : '') .
                                "Secure; " .
                                "SameSite=None; " .
                                "Max-Age=" . env('SESSION_LIFETIME', 3600);
                    
                    header("Set-Cookie: {$cookieHeader}", false);
                    error_log("🍪 Cookie cross-domain DYNAMIQUE forcé: {$cookieHeader}");
                }
            }
        }
    }

    /**
     * Détecte si la connexion est en HTTPS
     */
    function is_https() {
        return (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            $_SERVER['SERVER_PORT'] == 443 ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        );
    }

    // Appliquer la configuration cross-window (qui inclut maintenant la session centralisée)
    configure_session_for_cross_window();

    // Configuration CORS centralisée
    function setup_cors_headers() {
        $isDev = is_dev_mode();
        
        // Logging pour diagnostic
        if ($isDev) {
            error_log("🔧 Setup CORS - Début");
            error_log("- Mode: " . ($isDev ? 'DÉVELOPPEMENT' : 'PRODUCTION'));
            error_log("- Origin reçu: " . ($_SERVER['HTTP_ORIGIN'] ?? 'AUCUN'));
        }
        
        // Origines autorisées selon l'environnement
        $allowedOrigins = [];
        
        if ($isDev) {
            // En développement, autoriser React dev server
            $reactUrl = react_url();
            $allowedOrigins = [
                $reactUrl,
                'http://localhost:3000',
                'http://127.0.0.1:3000',
                'http://localhost:3007',
                'https://dev-facturation' // Ajout pour votre cas
            ];
        } else {
            // CORRECTION: En production, toujours traiter la configuration .env
            $corsOrigin = env('CORS_ORIGIN', '*');
            if ($corsOrigin !== '*') {
                $allowedOrigins = array_map('trim', explode(',', $corsOrigin));
                if ($isDev) {
                    error_log("📋 Origins autorisées depuis .env: " . json_encode($allowedOrigins));
                }
            }
        }
        
        // Vérifier l'origine de la requête
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $originAllowed = false;
        
        if ($isDev) {
            error_log("🔍 Test d'autorisation pour origin: '$origin'");
            error_log("📝 Origins autorisées: " . json_encode($allowedOrigins));
        }
        
        // CORRECTION: Améliorer la logique d'autorisation
        if (empty($origin)) {
            // Pas d'origin = requête directe, autoriser
            if ($isDev) {
                header('Access-Control-Allow-Origin: *');
                error_log("✅ Pas d'origin - Autorisation universelle (dev)");
            }
            $originAllowed = true;
        } elseif (in_array($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: $origin");
            $originAllowed = true;
            if ($isDev) {
                error_log("✅ Origin autorisé: $origin");
            }
        } elseif (!$isDev && env('CORS_ORIGIN') === '*') {
            header('Access-Control-Allow-Origin: *');
            $originAllowed = true;
            if ($isDev) {
                error_log("✅ Origin autorisé via wildcard");
            }
        } else {
            if ($isDev) {
                error_log("❌ Origin NON autorisé: $origin");
                // En dev, on peut être permissif pour le debug
                header("Access-Control-Allow-Origin: $origin");
                $originAllowed = true;
            }
        }
        
        // Headers CORS standard - TOUJOURS les envoyer
        header('Access-Control-Allow-Methods: ' . env('CORS_METHODS', 'GET, POST, PUT, DELETE, OPTIONS'));
        header('Access-Control-Allow-Headers: ' . env('CORS_HEADERS', 'Content-Type, Authorization, X-Requested-With, Origin, Accept'));
        header('Access-Control-Allow-Credentials: ' . env('CORS_CREDENTIALS', 'true'));
        header('Access-Control-Max-Age: 86400');
        
        if ($isDev) {
            error_log("📤 En-têtes CORS envoyés:");
            error_log("- Methods: " . env('CORS_METHODS', 'GET, POST, PUT, DELETE, OPTIONS'));
            error_log("- Headers: " . env('CORS_HEADERS', 'Content-Type, Authorization, X-Requested-With, Origin, Accept'));
            error_log("- Credentials: " . env('CORS_CREDENTIALS', 'true'));
        }
        
        // CRITIQUE: Gérer OPTIONS AVANT tout autre traitement
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            if ($isDev) {
                error_log("🔄 Requête OPTIONS (preflight) - Réponse immédiate");
            }
            http_response_code(200);
            exit();
        }
        
        return $originAllowed;
    }

    // Initialiser l'API (à appeler dans chaque fichier API)
    function init_api_response() {
        // Configurer CORS
        setup_cors_headers();
        
        // Headers API standard
        header('Content-Type: application/json; charset=UTF-8');
        
        // Headers de sécurité
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        
        // MODIFIÉ: La session est déjà démarrée dans configure_session_for_cross_window()
        // Pas besoin de ensure_session_started() ici
    }

    // Fonction pour récupérer les assets générés par CRA en production
    function get_cra_assets($type) {
        $buildDir = env('REACT_BUILD_DIR', 'build');
        $frontendPath = env('FRONTEND_ASSETS_PATH', '../DEV-facturation');
        $staticDir = "$frontendPath/$buildDir/$type";
        
        error_log("Recherche des assets dans : $staticDir");
        
        $assets = [];
        if (is_dir($staticDir)) {
            $files = scandir($staticDir);
            foreach ($files as $file) {
                if (preg_match("/^main\.[a-f0-9]+\.$type$/", $file)) {
                    // Retourner l'URL complète utilisable dans HTML
                    $assetUrl = env('FRONTEND_ASSETS_URL') . "/$buildDir/$type/$file";
                    $assets[] = $assetUrl;
                }
            }
        }
        
        return $assets;
    }

    // Configuration de base de l'application
    $config = [
        'app_name' => env('APP_NAME', 'Centre La Grange'),
        'debug_mode' => $debug_mode,
        'version' => env('APP_VERSION', '1.0.0'),
        'session_lifetime' => (int)env('SESSION_LIFETIME', 1800),
        'database' => [
            'host' => env('DB_HOST', 'localhost'),
            'name' => env('DB_NAME', 'facturation'),
            'user' => env('DB_USER', 'root'),
            'pass' => env('DB_PASS', '')
        ],
        'react' => [
            'host' => env('REACT_HOST', 'localhost'),
            'port' => env('REACT_PORT', '3007'),
            'url' => react_url(),
            'build_dir' => env('REACT_BUILD_DIR', 'build')
        ],
        'cors' => [
            'origin' => env('CORS_ORIGIN', '*'),
            'methods' => env('CORS_METHODS', 'GET, POST, PUT, DELETE, OPTIONS'),
            'headers' => env('CORS_HEADERS', 'Content-Type, Authorization, X-Requested-With'),
            'credentials' => env('CORS_CREDENTIALS', 'true')
        ],
        'logs' => [
            'dir' => $logDir,
            'enabled' => true,
            'level' => env('LOG_LEVEL', 'error')
        ]
    ];
}

// SUPPRIMÉ: La section "Configuration des sessions pour l'envoi d'emails" 
// car elle est maintenant intégrée dans configure_session_for_cross_window()

// Fonction helper pour s'assurer que la session est active (SIMPLIFIÉE)
function ensure_session_started() {
    // La session est normalement déjà démarrée par configure_session_for_cross_window()
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
        if (is_dev_mode()) {
            error_log("⚠️ Session redémarrée - ID: " . session_id());
        }
    }
    return session_id();
}

// Fonction pour nettoyer les anciennes données de session
function cleanup_session_data() {
    // La session est déjà démarrée, pas besoin de ensure_session_started()
    
    if (isset($_SESSION['pending_emails'])) {
        $cutoff = time() - 3600; // 1 heure
        $cleaned = 0;
        
        foreach ($_SESSION['pending_emails'] as $key => $email) {
            // CORRECTION : Utiliser 'created_at' au lieu de 'timestamp'
            $emailTime = $email['created_at'] ?? $email['timestamp'] ?? time();
            
            // Si c'est un timestamp Unix, l'utiliser directement
            if (is_numeric($emailTime)) {
                $emailTimestamp = $emailTime;
            } else {
                // Si c'est une date formatée, la convertir
                $emailTimestamp = strtotime($emailTime);
            }
            
            // Nettoyer seulement si vraiment ancien
            if ($emailTimestamp && $emailTimestamp < $cutoff) {
                unset($_SESSION['pending_emails'][$key]);
                $cleaned++;
                if (is_dev_mode()) {
                    error_log("🧹 Bootstrap - Nettoyé ancienne entrée: $key (âge: " . (time() - $emailTimestamp) . "s)");
                }
            }
        }
        
        if ($cleaned > 0 && is_dev_mode()) {
            error_log("Nettoyé $cleaned anciennes requêtes d'email en session");
        }
    }
}

// Nettoyer automatiquement au chargement du bootstrap
cleanup_session_data();

// Retourner la configuration (même si on l'a déjà chargée)
return $config ?? [];