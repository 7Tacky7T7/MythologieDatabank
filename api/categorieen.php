<?php
// ============================================================================
//  api/categorieen.php  -  Lijst en aanmaken van categorieen
// ----------------------------------------------------------------------------
//    (GET)  -> alle categorieen (id, naam, type) voor keuzelijsten
//    (POST) action=create -> nieuwe categorie (enkel goedgekeurde gebruikers)
//  Koppelen aan een wezen gebeurt in api/wezens.php.
// ============================================================================

require_once 'helpers.php';

$params  = leesInput();
$action  = $params['action'] ?? null;
$methode = $_SERVER['REQUEST_METHOD'];

try {
    // --- Lezen: alle categorieen -----------------------------------------
    if ($methode === 'GET') {
        $stmt = $pdo->query('SELECT id, naam, type FROM categorie ORDER BY type, naam');
        stuurOk($stmt->fetchAll());
    }

    // --- Schrijven: enkel goedgekeurde gebruikers ------------------------
    vereisGoedgekeurd();

    if ($action === 'create') {
        $naam = trim($params['naam'] ?? '');
        $type = $params['type'] ?? '';
        $toegestaan = ['soort', 'genre', 'afkomst'];

        if ($naam === '') {
            stuurFout('Geef een naam in voor de categorie.');
        }
        if (!in_array($type, $toegestaan, true)) {
            stuurFout('Het type moet soort, genre of afkomst zijn.');
        }

        // Bestaat de categorie al?
        $zoek = $pdo->prepare('SELECT id FROM categorie WHERE naam = :n');
        $zoek->execute([':n' => $naam]);
        $rij = $zoek->fetch();
        if ($rij) {
            stuurOk(['id' => (int) $rij['id'], 'bestond_al' => true]);
        }

        $stmt = $pdo->prepare('INSERT INTO categorie (naam, type) VALUES (:n, :t)');
        $stmt->execute([':n' => $naam, ':t' => $type]);
        stuurOk(['id' => (int) $pdo->lastInsertId(), 'bestond_al' => false]);
    }

    stuurFout('Onbekende actie.', 404);

} catch (Throwable $e) {
    stuurFout('Serverfout bij het verwerken van de categorieen.', 500);
}
