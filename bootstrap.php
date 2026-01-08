<?php
// bootstrap.php - Version simplifiée pour architecture même domaine (proxy)
// Plus besoin de gestion cross-domain complexe !

if (!defined('APP_INITIALIZED')) {
    define('APP_INITIALIZED', true);
    define('APP_ROOT', __DIR__);

    // ============================================
    // CHARGEMENT CONFIGURATION
    // ============================================

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

    // Mode debug
    function is_dev_mode() {
        return env('APP_ENV') === 'development';
    }

    // Configurer les erreurs
    $debug_mode = is_dev_mode();
    if ($debug_mode) {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
    } else {
        ini_set('display_errors', 0);
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    }

    // Dossier de logs
    $logDir = realpath(__DIR__ . '/' . env('LOG_DIR', 'logs'));
    if (!$logDir) {
        $logDir = __DIR__ . '/' . env('LOG_DIR', 'logs');
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    ini_set('log_errors', 1);
    ini_set('error_log', $logDir . '/php-errors.log');

    // Fuseau horaire
    date_default_timezone_set(env('TIMEZONE', 'Europe/Zurich'));

    // ============================================
    // GESTION SESSION SIMPLIFIÉE (MÊME DOMAINE)
    // ============================================

    function configure_session() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $sessionLifetime = (int)env('SESSION_LIFETIME', 1800);
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                    || ($_SERVER['SERVER_PORT'] ?? 80) == 443;

        // Configuration simple - même domaine = pas de problème !
        session_set_cookie_params([
            'lifetime' => $sessionLifetime,
            'path' => '/',
            'domain' => '',           // Domaine actuel automatiquement
            'secure' => $isSecure,
            'httponly' => true,       // Sécurité renforcée
            'samesite' => 'Lax'       // Suffisant pour même domaine
        ]);

        ini_set('session.gc_maxlifetime', $sessionLifetime);
        ini_set('session.use_strict_mode', '1');

        session_start();

        if (is_dev_mode()) {
            error_log("🔧 Session démarrée - ID: " . session_id());
            error_log("🔍 User en session: " . ($_SESSION['user_id'] ?? 'NON CONNECTÉ'));
        }
    }

    // Démarrer la session
    configure_session();

    // ============================================
    // CORS SIMPLIFIÉ
    // ============================================

    function setup_cors_headers() {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        // En même domaine, CORS est rarement nécessaire
        // Mais on le garde pour compatibilité
        if (!empty($origin)) {
            $allowedOrigins = array_map('trim', explode(',', env('CORS_ORIGIN', '')));
            
            foreach ($allowedOrigins as $allowed) {
                if (strcasecmp($origin, $allowed) === 0) {
                    header("Access-Control-Allow-Origin: $origin");
                    break;
                }
            }
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');

        // Preflight
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }

    // ============================================
    // FONCTIONS UTILITAIRES
    // ============================================

    function init_api_response() {
        setup_cors_headers();
        header('Content-Type: application/json; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
    }

    function secure_url($path) {
        $base_url = env('APP_URL_BACK', '');
        return rtrim($base_url, '/') . '/' . ltrim($path, '/');
    }

    function check_session_validity($publicEndpoints = ['login', 'check_session', 'debug_session']) {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $query_string = $_SERVER['QUERY_STRING'] ?? '';
        
        // Vérifier si endpoint public
        foreach ($publicEndpoints as $endpoint) {
            if (strpos($request_uri, $endpoint) !== false || strpos($query_string, $endpoint) !== false) {
                return true;
            }
        }
        
        // Vérifier session active et user_id présent
        if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['user_id'])) {
            if (is_dev_mode()) {
                error_log("❌ Session invalide - ID: " . session_id() . " - Contenu: " . json_encode($_SESSION));
            }
            sendSessionExpiredResponse();
            return false;
        }
        
        return true;
    }

    function sendSessionExpiredResponse() {
        if (ob_get_level()) {
            ob_clean();
        }
        
        http_response_code(401);
        header('Content-Type: application/json');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        
        echo json_encode([
            'success' => false,
            'session_expired' => true,
            'error' => 'Session expirée',
            'message' => 'Votre session a expiré. Veuillez vous reconnecter.'
        ]);
        
        exit;
    }

    function ensure_session_started() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return session_id();
    }

    // ============================================
    // CONFIGURATION GLOBALE
    // ============================================

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

return $config ?? [];
