<?php
// ============================================================================
//  diag.php  -  TIJDELIJK diagnosebestand. NA GEBRUIK VERWIJDEREN.
//  Toont (zonder het wachtwoord te tonen) of de databankverbinding lukt en
//  zo niet, wat de echte foutmelding is.
// ============================================================================
header('Content-Type: text/plain; charset=utf-8');

$cfg = __DIR__ . '/config.local.php';
echo "config.local.php aanwezig : " . (is_file($cfg) ? 'JA' : 'NEE') . "\n";

// Zelfde standaardwaarden als db.php, eventueel overschreven door config.local.php
$DB_HOST = 'localhost';
$DB_NAAM = 'mythologie';
$DB_USER = 'root';
$DB_PASS = '';
if (is_file($cfg)) {
    require $cfg;
}

echo "DB_HOST                   : $DB_HOST\n";
echo "DB_NAAM                   : $DB_NAAM\n";
echo "DB_USER                   : $DB_USER\n";
echo "DB_PASS lengte            : " . strlen($DB_PASS) . " tekens\n";
echo "PHP-versie                : " . PHP_VERSION . "\n";
echo "PDO mysql-driver          : " . (in_array('mysql', PDO::getAvailableDrivers()) ? 'JA' : 'NEE') . "\n";
echo "--------------------------------------------------\n";

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAAM;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "VERBINDING                : OK\n";
    $aantal = $pdo->query('SELECT COUNT(*) FROM verhaal')->fetchColumn();
    echo "Aantal verhalen           : $aantal\n";
    echo "\n==> Alles werkt. Verwijder dit bestand (diag.php).\n";
} catch (Throwable $e) {
    echo "VERBINDING                : MISLUKT\n";
    echo "Foutmelding               : " . $e->getMessage() . "\n";
}
