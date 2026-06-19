<?php
// ============================================================================
//  db.php  -  Verbinding met de databank via PDO
// ----------------------------------------------------------------------------
//  Dit bestand maakt 1 PDO-object ($pdo) aan dat alle endpoints gebruiken.
//  We werken met PDO omdat het:
//    - veilig is (prepared statements tegen SQL-injectie),
//    - flexibel is (zelfde code voor andere databanken, enkel de DSN wijzigt),
//    - nette foutafhandeling heeft via exceptions.
//  Dit bestand wordt NOOIT rechtstreeks opgevraagd; het wordt geinclude door
//  de andere PHP-bestanden. De guard hieronder blokkeert directe toegang.
// ============================================================================

if (basename($_SERVER['PHP_SELF']) === 'db.php') {
    http_response_code(403);
    exit('Toegang geweigerd');
}

// --- Standaardinstellingen (lokaal, XAMPP/WAMP) -----------------------------
$DB_HOST = 'localhost';
$DB_NAAM = 'mythologie';
$DB_USER = 'root';      // standaard bij XAMPP/WAMP
$DB_PASS = '';          // standaard leeg bij XAMPP/WAMP

// --- Productie-instellingen (cPanel) overschrijven de standaard -------------
// Als het bestand config.local.php bestaat, worden de instellingen hierboven
// overschreven met de echte (geheime) gegevens. Dat bestand staat in .gitignore
// en komt dus NOOIT in versiebeheer/GitHub terecht.
$configLokaal = __DIR__ . '/config.local.php';
if (is_file($configLokaal)) {
    require $configLokaal;
}

// --- Data Source Name: zegt MET WELKE databank en WAAR we verbinden ---------
$dsn = "mysql:host=$DB_HOST;dbname=$DB_NAAM;charset=utf8mb4";

// --- Opties voor het PDO-object --------------------------------------------
$opties = [
    // Fouten worden gegooid als exception, zodat we ze met try/catch opvangen.
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    // Standaard halen we rijen op als associatieve array (handig voor JSON).
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // Echte prepared statements laten uitvoeren door de databank (veiliger).
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// --- De verbinding effectief maken ------------------------------------------
// Lukt dit niet, dan stopt het endpoint met een nette JSON-fout. We tonen
// bewust GEEN technische details aan de gebruiker (dat is een veiligheidsrisico).
try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $opties);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Kan geen verbinding maken met de databank.']);
    exit;
}
