<?php
// ============================================================================
//  api/seed_admin.php  -  EENMALIG de eerste administrator aanmaken
// ----------------------------------------------------------------------------
//  Omdat enkel een admin andere gebruikers mag goedkeuren, moet er 1 admin
//  bestaan om mee te starten. Dit scriptje maakt die aan met een gehasht
//  wachtwoord (net zoals het seed_gebruiker.php-voorbeeld uit de cursus).
//
//  GEBRUIK:
//    1) Open in de browser:  http://localhost/MythologieDatabank/api/seed_admin.php
//    2) Log daarna in met:   admin / admin123
//    3) VERWIJDER dit bestand nadien (veiligheid).
// ============================================================================

require_once 'helpers.php';

$gebruikersnaam = 'admin';
$wachtwoord     = 'admin123';            // wijzig dit gerust voor je het uitvoert
$email          = 'admin@mythologie.be';

try {
    // Bestaat de admin al? Dan niet opnieuw aanmaken.
    $check = $pdo->prepare('SELECT id FROM gebruiker WHERE gebruikersnaam = :n');
    $check->execute([':n' => $gebruikersnaam]);
    if ($check->fetch()) {
        stuurOk(['bericht' => 'De admin bestaat al. Je kan inloggen met admin / admin123.']);
    }

    // Wachtwoord hashen en de admin aanmaken: meteen goedgekeurd EN admin.
    $hash = password_hash($wachtwoord, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        'INSERT INTO gebruiker (gebruikersnaam, email, wachtwoord, is_goedgekeurd, is_admin)
         VALUES (:n, :e, :w, 1, 1)'
    );
    $stmt->execute([':n' => $gebruikersnaam, ':e' => $email, ':w' => $hash]);

    stuurOk(['bericht' => 'Administrator aangemaakt. Log in met admin / admin123 en verwijder dit bestand.']);

} catch (Throwable $e) {
    stuurFout('Kon de administrator niet aanmaken.', 500);
}
