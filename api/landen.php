<?php
// ============================================================================
//  api/landen.php  -  Lijst en aanmaken van landen
// ----------------------------------------------------------------------------
//    (GET)  -> alle landen (voor keuzelijsten)
//    (POST) action=create -> nieuw land (enkel goedgekeurde gebruikers)
//  Opmerking: koppelen aan een verhaal gebeurt in api/verhalen.php; dat
//  endpoint kan ook meteen een nieuw land aanmaken via een naam.
// ============================================================================

require_once 'helpers.php';

$params  = leesInput();
$action  = $params['action'] ?? null;
$methode = $_SERVER['REQUEST_METHOD'];

try {
    // --- Lezen: alle landen ----------------------------------------------
    if ($methode === 'GET') {
        $stmt = $pdo->query('SELECT id, naam FROM land ORDER BY naam');
        stuurOk($stmt->fetchAll());
    }

    // --- Schrijven: enkel goedgekeurde gebruikers ------------------------
    vereisGoedgekeurd();

    if ($action === 'create') {
        $naam = trim($params['naam'] ?? '');
        if ($naam === '') {
            stuurFout('Geef een landnaam in.');
        }

        // Bestaat het land al? Geef dan gewoon het bestaande id terug.
        $zoek = $pdo->prepare('SELECT id FROM land WHERE naam = :n');
        $zoek->execute([':n' => $naam]);
        $rij = $zoek->fetch();
        if ($rij) {
            stuurOk(['id' => (int) $rij['id'], 'bestond_al' => true]);
        }

        $stmt = $pdo->prepare('INSERT INTO land (naam) VALUES (:n)');
        $stmt->execute([':n' => $naam]);
        stuurOk(['id' => (int) $pdo->lastInsertId(), 'bestond_al' => false]);
    }

    stuurFout('Onbekende actie.', 404);

} catch (Throwable $e) {
    stuurFout('Serverfout bij het verwerken van de landen.', 500);
}
