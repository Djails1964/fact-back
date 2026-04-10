<?php
// migrate.php - VERSION AMÉLIORÉE avec affichage des SELECT
// Détection de l'environnement et récupération des paramètres
$dbName = $argv[1] ?? 'factlagrange';
$configPath = __DIR__ . '/db_config.ini';
$host = 'localhost';
$charset = 'utf8mb4';

$user = null;
$pass = null;

if (file_exists($configPath)) {
    // --- ENVIRONNEMENT WINDOWS (DEV) ---
    $ini = parse_ini_file($configPath, true);
    $user = $ini['client']['user'] ?? 'root';
    $pass = $ini['client']['password'] ?? '';
    echo "[INFO] Configuration chargee depuis db_config.ini (Base: $dbName)\n";
} else {
    // --- ENVIRONNEMENT LINUX (SYNOLOGY) ---
    echo "[INFO] db_config.ini introuvable, lecture manuelle du .my.cnf...\n";

    $userHome = getenv('HOME') ?: '/volume1/homes/gilles';
    $myCnfPath = $userHome . '/.my.cnf';

    if (!file_exists($myCnfPath)) {
        die("ERREUR : Aucun fichier de configuration trouve (~/.my.cnf manquant).\n");
    }

    $lines = file($myCnfPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, 'user=') === 0) $user = trim(explode('=', $line)[1]);
        if (strpos($line, 'password=') === 0) $pass = trim(explode('=', $line)[1], ' "\'');
    }

    if (!$user) {
        die("ERREUR : Impossible d'extraire l'utilisateur de $myCnfPath\n");
    }
    echo "[INFO] Connexion etablie pour $user sur $dbName via .my.cnf\n";
}

// Connexion PDO
$dsn = "mysql:host=$host;dbname=$dbName;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE                  => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE       => PDO::FETCH_ASSOC,
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("ERREUR de connexion : " . $e->getMessage() . "\n");
}

// Fonction pour afficher les résultats d'un SELECT
function displayResults($results) {
    if (empty($results)) return;
    
    $columns = array_keys($results[0]);
    $widths = [];
    
    foreach ($columns as $col) {
        $widths[$col] = strlen($col);
    }
    foreach ($results as $row) {
        foreach ($row as $col => $val) {
            $widths[$col] = max($widths[$col], strlen((string)($val ?? '')));
        }
    }
    
    echo "\n";
    $headerLine = '';
    $separatorLine = '';
    foreach ($columns as $col) {
        $headerLine    .= str_pad($col, $widths[$col] + 2);
        $separatorLine .= str_repeat('-', $widths[$col] + 2);
    }
    echo $headerLine . "\n";
    echo $separatorLine . "\n";
    
    foreach ($results as $row) {
        $dataLine = '';
        foreach ($columns as $col) {
            $dataLine .= str_pad((string)($row[$col] ?? ''), $widths[$col] + 2);
        }
        echo $dataLine . "\n";
    }
    echo "\n";
    
    if (ob_get_level() > 0) ob_flush();
    flush();
}

// Fonction pour exécuter une requête et afficher les résultats si SELECT
function executeStatement($pdo, $query) {
    $query = trim($query);
    if (empty($query)) return;
    
    // Ignorer les commentaires seuls et les lignes vides
    if (preg_match('/^\s*(--|\#)/', $query)) return;
    if (preg_match('/^\s*$/', $query)) return;
    
    try {
        // Détecter si c'est un SELECT (pour affichage)
        $isSelect = preg_match('/^\s*SELECT\s+/i', $query);

        // On passe TOUT par query() — jamais exec() — pour garantir
        // que chaque résultat (y compris ceux produits par EXECUTE stmt)
        // est correctement consommé. exec() ne consomme pas les curseurs
        // laissés par EXECUTE quand le fallback IF() est un SELECT.
        $stmt = $pdo->query($query);

        if ($stmt === false) return;

        if ($isSelect) {
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($results)) {
                displayResults($results);
            }
        } else {
            // Consommer tout résultat résiduel (ex: EXECUTE stmt → SELECT fallback)
            // nextRowset() retourne false quand il n'y a plus rien → boucle propre
            do {
                $stmt->fetchAll();
            } while ($stmt->nextRowset());
        }

        $stmt->closeCursor();

    } catch (Exception $e) {
        echo "ERREUR: " . $e->getMessage() . "\n\n";
    }
}

// Gestion des migrations
$migrationDir = __DIR__ . '/../migrations/';
if (!is_dir($migrationDir)) {
    die("ERREUR : Le dossier des migrations est introuvable.\n");
}

$files = scandir($migrationDir);
$tableCheck = $pdo->query("SHOW TABLES LIKE 'sys_migrations'")->rowCount();
$executed = ($tableCheck > 0) ? $pdo->query("SELECT migration_name FROM sys_migrations")->fetchAll(PDO::FETCH_COLUMN) : [];

$count = 0;
foreach ($files as $file) {
    if (pathinfo($file, PATHINFO_EXTENSION) !== 'sql') continue;

    if (!in_array($file, $executed)) {
        echo "Execution de : $file...\n\n";
        
        $sql = file_get_contents($migrationDir . $file);
        
        try {
            // ── Découpage SQL avec support des blocs BEGIN...END (triggers) ──────
            $statements = [];
            $buffer     = '';
            $inTrigger  = false;
            $lines      = explode("\n", $sql);

            foreach ($lines as $line) {
                $trimmed = trim($line);

                if (!$inTrigger && (empty($trimmed) || preg_match('/^--/', $trimmed))) {
                    continue;
                }

                if (preg_match('/^DELIMITER\s+\$\$/i', $trimmed)) {
                    $inTrigger = true;
                    $buffer    = '';
                    continue;
                }

                if (preg_match('/^DELIMITER\s+;/i', $trimmed)) {
                    $inTrigger = false;
                    if (!empty(trim($buffer))) {
                        $statements[] = rtrim(str_replace('$$', '', $buffer)) . "\n";
                    }
                    $buffer = '';
                    continue;
                }

                if ($inTrigger) {
                    $buffer .= str_replace('$$', ';', $line) . "\n";
                } else {
                    $buffer .= $line . "\n";
                    if (preg_match('/;\s*$/', $trimmed)) {
                        if (!empty(trim($buffer))) {
                            $statements[] = $buffer;
                        }
                        $buffer = '';
                    }
                }
            }

            if (!empty(trim($buffer))) {
                $statements[] = $buffer;
            }
            
            foreach ($statements as $statement) {
                executeStatement($pdo, $statement);
            }
            
            // Enregistrement dans l'historique
            $stmt = $pdo->prepare("INSERT INTO sys_migrations (migration_name) VALUES (?)");
            $stmt->execute([$file]);
            
            echo "✅ Migration $file terminee avec succes\n\n";
            $count++;
        } catch (Exception $e) {
            die("\n❌ ERREUR dans $file : " . $e->getMessage() . "\nMigration stoppee.\n");
        }
    }
}

if ($count === 0) {
    echo "Base de donnees deja a jour.\n";
} else {
    echo "$count migration(s) appliquee(s) avec succes.\n";
}