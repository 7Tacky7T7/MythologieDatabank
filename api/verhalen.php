<?php
// ============================================================================
//  api/verhalen.php  -  Alles rond verhalen (CRUD + koppelingen)
// ----------------------------------------------------------------------------
//  LEZEN (GET, mag iedereen):
//    (geen action)        -> lijst van verhalen, optioneel gefilterd:
//                              ?q=woord        zoek in titel/synopsis
//                              ?land_id=1      enkel verhalen van dat land
//    ?action=detail&id=1  -> 1 verhaal + zijn landen + zijn wezens
//
//  SCHRIJVEN (POST, enkel goedgekeurde gebruikers):
//    create / update / delete
//    koppelLand / ontkoppelLand        (land_id OF nieuwe naam)
//    koppelWezen / ontkoppelWezen
// ============================================================================

require_once 'helpers.php';

$params = leesInput();
$action = $params['action'] ?? null;
$methode = $_SERVER['REQUEST_METHOD'];

try {

    // =====================================================================
    //  LEESACTIES  (GET)
    // =====================================================================
    if ($methode === 'GET') {

        // --- 1 verhaal volledig ophalen (detailpagina) -------------------
        if ($action === 'detail') {
            $id = filter_var($params['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                stuurFout('Ongeldig verhaal-id.');
            }

            // Het verhaal zelf.
            $stmt = $pdo->prepare('SELECT * FROM verhaal WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $verhaal = $stmt->fetch();
            if (!$verhaal) {
                stuurFout('Verhaal niet gevonden.', 404);
            }

            // De gekoppelde landen (via de koppeltabel).
            $stmtL = $pdo->prepare(
                'SELECT land.id, land.naam
                   FROM land
                   JOIN verhaal_land ON verhaal_land.land_id = land.id
                  WHERE verhaal_land.verhaal_id = :id
                  ORDER BY land.naam'
            );
            $stmtL->execute([':id' => $id]);
            $landen = $stmtL->fetchAll();

            // De gekoppelde wezens (naam is klikbaar op de pagina).
            $stmtW = $pdo->prepare(
                'SELECT wezen.id, wezen.naam
                   FROM wezen
                   JOIN verhaal_wezen ON verhaal_wezen.wezen_id = wezen.id
                  WHERE verhaal_wezen.verhaal_id = :id
                  ORDER BY wezen.naam'
            );
            $stmtW->execute([':id' => $id]);
            $wezens = $stmtW->fetchAll();

            stuurOk(['verhaal' => $verhaal, 'landen' => $landen, 'wezens' => $wezens]);
        }

        // --- Lijst van verhalen (met optionele filters) ------------------
        $q       = trim($params['q'] ?? '');
        $land_id = filter_var($params['land_id'] ?? null, FILTER_VALIDATE_INT);

        // We bouwen de query stap voor stap op, met placeholders voor de waarden.
        $sql = 'SELECT DISTINCT verhaal.id, verhaal.titel, verhaal.synopsis, verhaal.eeuw
                  FROM verhaal';
        $voorwaarden = [];
        $waarden     = [];

        if ($land_id) {
            // Enkel verhalen van een bepaald land -> join met de koppeltabel.
            $sql .= ' JOIN verhaal_land ON verhaal_land.verhaal_id = verhaal.id';
            $voorwaarden[] = 'verhaal_land.land_id = :land_id';
            $waarden[':land_id'] = $land_id;
        }
        if ($q !== '') {
            // Zoeken in titel of synopsis.
            $voorwaarden[] = '(verhaal.titel LIKE :zoek OR verhaal.synopsis LIKE :zoek)';
            $waarden[':zoek'] = '%' . $q . '%';
        }
        if ($voorwaarden) {
            $sql .= ' WHERE ' . implode(' AND ', $voorwaarden);
        }
        $sql .= ' ORDER BY verhaal.titel';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($waarden);
        stuurOk($stmt->fetchAll());
    }

    // =====================================================================
    //  SCHRIJFACTIES  (POST)  -  enkel voor goedgekeurde gebruikers
    // =====================================================================
    vereisGoedgekeurd();

    // --- Nieuw verhaal aanmaken ------------------------------------------
    if ($action === 'create') {
        $titel     = trim($params['titel'] ?? '');
        $synopsis  = trim($params['synopsis'] ?? '');
        $leestekst = schoonHtml($params['leestekst'] ?? '');
        $eeuw      = filter_var($params['eeuw'] ?? null, FILTER_VALIDATE_INT);

        if ($titel === '' || $synopsis === '' || $eeuw === false) {
            stuurFout('Titel, synopsis en eeuw zijn verplicht (eeuw moet een getal zijn).');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO verhaal (titel, synopsis, leestekst, eeuw)
             VALUES (:titel, :synopsis, :leestekst, :eeuw)'
        );
        $stmt->execute([
            ':titel'     => $titel,
            ':synopsis'  => $synopsis,
            ':leestekst' => $leestekst,
            ':eeuw'      => $eeuw,
        ]);

        stuurOk(['id' => (int) $pdo->lastInsertId()]);
    }

    // --- Bestaand verhaal aanpassen --------------------------------------
    if ($action === 'update') {
        $id = filter_var($params['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) {
            stuurFout('Ongeldig verhaal-id.');
        }

        // Enkel de velden die meegestuurd worden, worden aangepast.
        // Zo kan de inline-editor 1 veld tegelijk opslaan.
        $velden = [];
        $waarden = [':id' => $id];

        if (array_key_exists('titel', $params)) {
            $velden[] = 'titel = :titel';
            $waarden[':titel'] = trim($params['titel']);
        }
        if (array_key_exists('synopsis', $params)) {
            $velden[] = 'synopsis = :synopsis';
            $waarden[':synopsis'] = trim($params['synopsis']);
        }
        if (array_key_exists('leestekst', $params)) {
            $velden[] = 'leestekst = :leestekst';
            $waarden[':leestekst'] = schoonHtml($params['leestekst']);
        }
        if (array_key_exists('eeuw', $params)) {
            $eeuw = filter_var($params['eeuw'], FILTER_VALIDATE_INT);
            if ($eeuw === false) {
                stuurFout('De eeuw moet een getal zijn.');
            }
            $velden[] = 'eeuw = :eeuw';
            $waarden[':eeuw'] = $eeuw;
        }

        if (!$velden) {
            stuurFout('Geen velden om aan te passen.');
        }

        $sql = 'UPDATE verhaal SET ' . implode(', ', $velden) . ' WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($waarden);

        stuurOk(['aangepast' => $stmt->rowCount()]);
    }

    // --- Verhaal verwijderen ---------------------------------------------
    // Door ON DELETE CASCADE verdwijnen ook de koppelingen automatisch.
    if ($action === 'delete') {
        $id = filter_var($params['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) {
            stuurFout('Ongeldig verhaal-id.');
        }
        $stmt = $pdo->prepare('DELETE FROM verhaal WHERE id = :id');
        $stmt->execute([':id' => $id]);
        stuurOk(['verwijderd' => $stmt->rowCount()]);
    }

    // --- Een land koppelen (bestaand id OF nieuwe naam) ------------------
    if ($action === 'koppelLand') {
        $verhaal_id = filter_var($params['verhaal_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$verhaal_id) {
            stuurFout('Ongeldig verhaal-id.');
        }

        $land_id = bepaalLandId($pdo, $params);   // helper onderaan dit bestand
        if (!$land_id) {
            stuurFout('Geef een bestaand land of een nieuwe landnaam op.');
        }

        // INSERT IGNORE: bestaat de koppeling al, dan gebeurt er gewoon niets.
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO verhaal_land (verhaal_id, land_id) VALUES (:v, :l)'
        );
        $stmt->execute([':v' => $verhaal_id, ':l' => $land_id]);
        stuurOk(['land_id' => $land_id]);
    }

    // --- Een land ontkoppelen --------------------------------------------
    if ($action === 'ontkoppelLand') {
        $verhaal_id = filter_var($params['verhaal_id'] ?? null, FILTER_VALIDATE_INT);
        $land_id    = filter_var($params['land_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$verhaal_id || !$land_id) {
            stuurFout('Ongeldige parameters.');
        }
        $stmt = $pdo->prepare(
            'DELETE FROM verhaal_land WHERE verhaal_id = :v AND land_id = :l'
        );
        $stmt->execute([':v' => $verhaal_id, ':l' => $land_id]);
        stuurOk(['ontkoppeld' => $stmt->rowCount()]);
    }

    // --- Een wezen koppelen ----------------------------------------------
    if ($action === 'koppelWezen') {
        $verhaal_id = filter_var($params['verhaal_id'] ?? null, FILTER_VALIDATE_INT);
        $wezen_id   = filter_var($params['wezen_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$verhaal_id || !$wezen_id) {
            stuurFout('Ongeldige parameters.');
        }
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO verhaal_wezen (verhaal_id, wezen_id) VALUES (:v, :w)'
        );
        $stmt->execute([':v' => $verhaal_id, ':w' => $wezen_id]);
        stuurOk(['gekoppeld' => true]);
    }

    // --- Een wezen ontkoppelen -------------------------------------------
    if ($action === 'ontkoppelWezen') {
        $verhaal_id = filter_var($params['verhaal_id'] ?? null, FILTER_VALIDATE_INT);
        $wezen_id   = filter_var($params['wezen_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$verhaal_id || !$wezen_id) {
            stuurFout('Ongeldige parameters.');
        }
        $stmt = $pdo->prepare(
            'DELETE FROM verhaal_wezen WHERE verhaal_id = :v AND wezen_id = :w'
        );
        $stmt->execute([':v' => $verhaal_id, ':w' => $wezen_id]);
        stuurOk(['ontkoppeld' => $stmt->rowCount()]);
    }

    stuurFout('Onbekende actie.', 404);

} catch (Throwable $e) {
    stuurFout('Serverfout bij het verwerken van het verhaal.', 500);
}


// ----------------------------------------------------------------------------
//  bepaalLandId()  -  Hulpfunctie: geef het id van een land terug.
//  Komt er een bestaand land_id mee? Gebruik dat. Komt er een nieuwe naam mee?
//  Zoek het land (bestaat het al) of maak het nieuw aan. Zo kan de gebruiker
//  een nieuw land aanmaken EN meteen koppelen, zonder aparte pagina.
// ----------------------------------------------------------------------------
function bepaalLandId(PDO $pdo, array $params): ?int
{
    $land_id = filter_var($params['land_id'] ?? null, FILTER_VALIDATE_INT);
    if ($land_id) {
        return $land_id;
    }

    $naam = trim($params['naam'] ?? '');
    if ($naam === '') {
        return null;
    }

    // Bestaat dit land al? (vermijdt dubbels -> blijft genormaliseerd)
    $zoek = $pdo->prepare('SELECT id FROM land WHERE naam = :n');
    $zoek->execute([':n' => $naam]);
    $rij = $zoek->fetch();
    if ($rij) {
        return (int) $rij['id'];
    }

    // Nieuw land aanmaken.
    $maak = $pdo->prepare('INSERT INTO land (naam) VALUES (:n)');
    $maak->execute([':n' => $naam]);
    return (int) $pdo->lastInsertId();
}
