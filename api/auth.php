<?php
// ============================================================================
//  api/auth.php  -  Account aanmaken, aanmelden, afmelden en sessie-status
// ----------------------------------------------------------------------------
//  Acties (meegegeven via het veld "action"):
//    register -> nieuw account aanmaken (nog NIET goedgekeurd)
//    login    -> aanmelden, sessie starten
//    logout   -> afmelden, sessie wissen
//    me       -> wie is er ingelogd? (GET) -> gebruikt om edit-knoppen te tonen
// ============================================================================

require_once 'helpers.php';

$params = leesInput();
$action = $params['action'] ?? null;

try {

    // ------------------------------------------------------------------ me ---
    // Geen 'action' of action=me: vertel de client wie er ingelogd is.
    if ($action === 'me' || $action === null) {
        stuurOk(['gebruiker' => huidigeGebruiker()]);
    }

    // ------------------------------------------------------------ register ---
    if ($action === 'register') {
        // 1) Input ophalen en valideren.
        $gebruikersnaam = trim($params['gebruikersnaam'] ?? '');
        $email          = trim($params['email'] ?? '');
        $wachtwoord     = (string) ($params['wachtwoord'] ?? '');

        if ($gebruikersnaam === '' || $email === '' || $wachtwoord === '') {
            stuurFout('Vul alle velden in.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            stuurFout('Geef een geldig e-mailadres in.');
        }
        if (strlen($wachtwoord) < 6) {
            stuurFout('Het wachtwoord moet minstens 6 tekens lang zijn.');
        }

        // 2) Bestaat de gebruikersnaam of e-mail al? (prepared statement)
        $check = $pdo->prepare(
            'SELECT id FROM gebruiker WHERE gebruikersnaam = :n OR email = :e'
        );
        $check->execute([':n' => $gebruikersnaam, ':e' => $email]);
        if ($check->fetch()) {
            stuurFout('Die gebruikersnaam of dat e-mailadres is al in gebruik.');
        }

        // 3) Wachtwoord HASHEN (nooit klare tekst bewaren).
        $hash = password_hash($wachtwoord, PASSWORD_DEFAULT);

        // 4) Account aanmaken. is_goedgekeurd = 0 -> mag inloggen maar niets wijzigen.
        $insert = $pdo->prepare(
            'INSERT INTO gebruiker (gebruikersnaam, email, wachtwoord)
             VALUES (:n, :e, :w)'
        );
        $insert->execute([':n' => $gebruikersnaam, ':e' => $email, ':w' => $hash]);

        stuurOk(['bericht' => 'Account aangemaakt. Een administrator moet je nog goedkeuren voor je kan bewerken.']);
    }

    // --------------------------------------------------------------- login ---
    if ($action === 'login') {
        $gebruikersnaam = trim($params['gebruikersnaam'] ?? '');
        $wachtwoord     = (string) ($params['wachtwoord'] ?? '');

        if ($gebruikersnaam === '' || $wachtwoord === '') {
            stuurFout('Vul je gebruikersnaam en wachtwoord in.');
        }

        // Zoek de gebruiker op via een prepared statement.
        $stmt = $pdo->prepare('SELECT * FROM gebruiker WHERE gebruikersnaam = :n');
        $stmt->execute([':n' => $gebruikersnaam]);
        $rij = $stmt->fetch();

        // Vergelijk het ingegeven wachtwoord met de bewaarde hash.
        // Let op: bewust 1 vage foutmelding, zodat een aanvaller niet weet
        // of de gebruikersnaam dan wel het wachtwoord fout was.
        if (!$rij || !password_verify($wachtwoord, $rij['wachtwoord'])) {
            stuurFout('Onjuiste gebruikersnaam of wachtwoord.', 401);
        }

        // Sla enkel veilige gegevens op in de sessie (NOOIT de hash).
        $_SESSION['gebruiker'] = [
            'id'             => (int) $rij['id'],
            'gebruikersnaam' => $rij['gebruikersnaam'],
            'is_goedgekeurd' => (int) $rij['is_goedgekeurd'],
            'is_admin'       => (int) $rij['is_admin'],
        ];

        stuurOk(['gebruiker' => $_SESSION['gebruiker']]);
    }

    // -------------------------------------------------------------- logout ---
    if ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        stuurOk(['bericht' => 'Afgemeld.']);
    }

    // Onbekende actie.
    stuurFout('Onbekende actie.', 404);

} catch (Throwable $e) {
    // Eender welke onverwachte fout -> nette 500 zonder technische details.
    stuurFout('Serverfout bij de authenticatie.', 500);
}
