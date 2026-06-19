<?php
// ============================================================================
//  api/gebruikers.php  -  Gebruikersbeheer (ENKEL voor administrators)
// ----------------------------------------------------------------------------
//  Een administrator moet nieuwe accounts "registreren" (goedkeuren) voor ze
//  iets mogen wijzigen. Dit endpoint maakt dat mogelijk.
//    (GET)                       -> lijst van alle gebruikers + hun status
//    (POST) action=goedkeuren    -> zet is_goedgekeurd = 1
//    (POST) action=intrekken     -> zet is_goedgekeurd = 0
// ============================================================================

require_once 'helpers.php';

$params  = leesInput();
$action  = $params['action'] ?? null;
$methode = $_SERVER['REQUEST_METHOD'];

try {
    // Alles in dit bestand mag enkel een administrator.
    $admin = vereisAdmin();

    // --- Lijst van alle gebruikers ---------------------------------------
    if ($methode === 'GET') {
        // We sturen bewust NOOIT de wachtwoord-hash mee.
        $stmt = $pdo->query(
            'SELECT id, gebruikersnaam, email, is_goedgekeurd, is_admin, aangemaakt_op
               FROM gebruiker
              ORDER BY is_goedgekeurd ASC, aangemaakt_op DESC'
        );
        stuurOk($stmt->fetchAll());
    }

    // --- Een gebruiker goedkeuren of intrekken ---------------------------
    if ($action === 'goedkeuren' || $action === 'intrekken') {
        $id = filter_var($params['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) {
            stuurFout('Ongeldig gebruiker-id.');
        }

        // Een admin mag zijn eigen status niet per ongeluk intrekken.
        if ($action === 'intrekken' && $id === (int) $admin['id']) {
            stuurFout('Je kan je eigen toegang niet intrekken.');
        }

        $nieuweWaarde = ($action === 'goedkeuren') ? 1 : 0;
        $stmt = $pdo->prepare('UPDATE gebruiker SET is_goedgekeurd = :w WHERE id = :id');
        $stmt->execute([':w' => $nieuweWaarde, ':id' => $id]);

        stuurOk(['aangepast' => $stmt->rowCount(), 'is_goedgekeurd' => $nieuweWaarde]);
    }

    stuurFout('Onbekende actie.', 404);

} catch (Throwable $e) {
    stuurFout('Serverfout bij het gebruikersbeheer.', 500);
}
