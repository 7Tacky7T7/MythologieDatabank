<?php
// ============================================================================
//  api/wezens.php  -  Alles rond mythologische wezens (CRUD + koppelingen)
// ----------------------------------------------------------------------------
//  LEZEN (GET):
//    (geen action)         -> lijst, optioneel ?q=woord of ?categorie_id=1
//    ?action=detail&id=1   -> 1 wezen + zijn categorieen + de verhalen waarin
//                             het voorkomt (titel + synopsis)
//
//  SCHRIJVEN (POST, enkel goedgekeurde gebruikers):
//    create / update / delete
//    koppelCategorie / ontkoppelCategorie   (categorie_id OF nieuwe naam+type)
//
//  De AFBEELDING van een wezen wordt apart beheerd in api/upload.php.
// ============================================================================

require_once 'helpers.php';

$params  = leesInput();
$action  = $params['action'] ?? null;
$methode = $_SERVER['REQUEST_METHOD'];

try {

    // =====================================================================
    //  LEESACTIES (GET)
    // =====================================================================
    if ($methode === 'GET') {

        // --- 1 wezen volledig ophalen (detailpagina) ---------------------
        if ($action === 'detail') {
            $id = filter_var($params['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                stuurFout('Ongeldig wezen-id.');
            }

            $stmt = $pdo->prepare('SELECT * FROM wezen WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $wezen = $stmt->fetch();
            if (!$wezen) {
                stuurFout('Wezen niet gevonden.', 404);
            }

            // De categorieen van dit wezen.
            $stmtC = $pdo->prepare(
                'SELECT categorie.id, categorie.naam, categorie.type
                   FROM categorie
                   JOIN wezen_categorie ON wezen_categorie.categorie_id = categorie.id
                  WHERE wezen_categorie.wezen_id = :id
                  ORDER BY categorie.naam'
            );
            $stmtC->execute([':id' => $id]);
            $categorieen = $stmtC->fetchAll();

            // De verhalen waarin dit wezen voorkomt (titel + synopsis tonen).
            $stmtV = $pdo->prepare(
                'SELECT verhaal.id, verhaal.titel, verhaal.synopsis
                   FROM verhaal
                   JOIN verhaal_wezen ON verhaal_wezen.verhaal_id = verhaal.id
                  WHERE verhaal_wezen.wezen_id = :id
                  ORDER BY verhaal.titel'
            );
            $stmtV->execute([':id' => $id]);
            $verhalen = $stmtV->fetchAll();

            stuurOk(['wezen' => $wezen, 'categorieen' => $categorieen, 'verhalen' => $verhalen]);
        }

        // --- Lijst van wezens (met optionele filters) --------------------
        $q            = trim($params['q'] ?? '');
        $categorie_id = filter_var($params['categorie_id'] ?? null, FILTER_VALIDATE_INT);

        $sql = 'SELECT DISTINCT wezen.id, wezen.naam, wezen.afbeelding FROM wezen';
        $voorwaarden = [];
        $waarden     = [];

        if ($categorie_id) {
            $sql .= ' JOIN wezen_categorie ON wezen_categorie.wezen_id = wezen.id';
            $voorwaarden[] = 'wezen_categorie.categorie_id = :cat';
            $waarden[':cat'] = $categorie_id;
        }
        if ($q !== '') {
            $voorwaarden[] = 'wezen.naam LIKE :zoek';
            $waarden[':zoek'] = '%' . $q . '%';
        }
        if ($voorwaarden) {
            $sql .= ' WHERE ' . implode(' AND ', $voorwaarden);
        }
        $sql .= ' ORDER BY wezen.naam';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($waarden);
        stuurOk($stmt->fetchAll());
    }

    // =====================================================================
    //  SCHRIJFACTIES (POST) - enkel goedgekeurde gebruikers
    // =====================================================================
    vereisGoedgekeurd();

    // --- Nieuw wezen aanmaken --------------------------------------------
    if ($action === 'create') {
        $naam         = trim($params['naam'] ?? '');
        $beschrijving = schoonHtml($params['beschrijving'] ?? '');

        if ($naam === '') {
            stuurFout('De naam is verplicht.');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO wezen (naam, beschrijving) VALUES (:naam, :beschrijving)'
        );
        $stmt->execute([':naam' => $naam, ':beschrijving' => $beschrijving]);
        stuurOk(['id' => (int) $pdo->lastInsertId()]);
    }

    // --- Bestaand wezen aanpassen (1 of meerdere velden) -----------------
    if ($action === 'update') {
        $id = filter_var($params['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) {
            stuurFout('Ongeldig wezen-id.');
        }

        $velden  = [];
        $waarden = [':id' => $id];

        if (array_key_exists('naam', $params)) {
            $naam = trim($params['naam']);
            if ($naam === '') {
                stuurFout('De naam mag niet leeg zijn.');
            }
            $velden[] = 'naam = :naam';
            $waarden[':naam'] = $naam;
        }
        if (array_key_exists('beschrijving', $params)) {
            $velden[] = 'beschrijving = :beschrijving';
            $waarden[':beschrijving'] = schoonHtml($params['beschrijving']);
        }

        if (!$velden) {
            stuurFout('Geen velden om aan te passen.');
        }

        $sql = 'UPDATE wezen SET ' . implode(', ', $velden) . ' WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($waarden);
        stuurOk(['aangepast' => $stmt->rowCount()]);
    }

    // --- Wezen verwijderen -----------------------------------------------
    if ($action === 'delete') {
        $id = filter_var($params['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) {
            stuurFout('Ongeldig wezen-id.');
        }

        // Eerst de eventuele afbeelding van de schijf halen.
        $stmt = $pdo->prepare('SELECT afbeelding FROM wezen WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $rij = $stmt->fetch();
        if ($rij && !empty($rij['afbeelding'])) {
            $pad = __DIR__ . '/../uploads/' . basename($rij['afbeelding']);
            if (is_file($pad)) {
                @unlink($pad);
            }
        }

        $del = $pdo->prepare('DELETE FROM wezen WHERE id = :id');
        $del->execute([':id' => $id]);
        stuurOk(['verwijderd' => $del->rowCount()]);
    }

    // --- Een categorie koppelen (bestaand id OF nieuwe naam+type) --------
    if ($action === 'koppelCategorie') {
        $wezen_id = filter_var($params['wezen_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$wezen_id) {
            stuurFout('Ongeldig wezen-id.');
        }

        $categorie_id = bepaalCategorieId($pdo, $params);   // helper onderaan
        if (!$categorie_id) {
            stuurFout('Geef een bestaande categorie of een nieuwe naam (+type) op.');
        }

        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO wezen_categorie (wezen_id, categorie_id) VALUES (:w, :c)'
        );
        $stmt->execute([':w' => $wezen_id, ':c' => $categorie_id]);
        stuurOk(['categorie_id' => $categorie_id]);
    }

    // --- Een categorie ontkoppelen ---------------------------------------
    if ($action === 'ontkoppelCategorie') {
        $wezen_id     = filter_var($params['wezen_id'] ?? null, FILTER_VALIDATE_INT);
        $categorie_id = filter_var($params['categorie_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$wezen_id || !$categorie_id) {
            stuurFout('Ongeldige parameters.');
        }
        $stmt = $pdo->prepare(
            'DELETE FROM wezen_categorie WHERE wezen_id = :w AND categorie_id = :c'
        );
        $stmt->execute([':w' => $wezen_id, ':c' => $categorie_id]);
        stuurOk(['ontkoppeld' => $stmt->rowCount()]);
    }

    stuurFout('Onbekende actie.', 404);

} catch (Throwable $e) {
    stuurFout('Serverfout bij het verwerken van het wezen.', 500);
}


// ----------------------------------------------------------------------------
//  bepaalCategorieId()  -  Bestaande categorie gebruiken of nieuwe aanmaken.
// ----------------------------------------------------------------------------
function bepaalCategorieId(PDO $pdo, array $params): ?int
{
    $categorie_id = filter_var($params['categorie_id'] ?? null, FILTER_VALIDATE_INT);
    if ($categorie_id) {
        return $categorie_id;
    }

    $naam = trim($params['naam'] ?? '');
    $type = $params['type'] ?? '';
    $toegestaan = ['soort', 'genre', 'afkomst'];

    if ($naam === '' || !in_array($type, $toegestaan, true)) {
        return null;
    }

    // Bestaat de categorie al?
    $zoek = $pdo->prepare('SELECT id FROM categorie WHERE naam = :n');
    $zoek->execute([':n' => $naam]);
    $rij = $zoek->fetch();
    if ($rij) {
        return (int) $rij['id'];
    }

    // Nieuwe categorie aanmaken.
    $maak = $pdo->prepare('INSERT INTO categorie (naam, type) VALUES (:n, :t)');
    $maak->execute([':n' => $naam, ':t' => $type]);
    return (int) $pdo->lastInsertId();
}
