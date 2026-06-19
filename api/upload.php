<?php
// ============================================================================
//  api/upload.php  -  Afbeeldingen uploaden (en verwijderen)
// ----------------------------------------------------------------------------
//  Werkt met multipart/form-data (FormData in JavaScript), want we sturen een
//  echt bestand mee, geen JSON. We lezen daarom $_POST en $_FILES uit.
//
//  Acties (veld "action" in de FormData):
//    wezenAfbeelding          -> wezen_id + bestand : zet/vervang de afbeelding
//                                van 1 wezen (max 1 afbeelding per wezen)
//    inlineAfbeelding         -> bestand            : afbeelding in de leestekst
//                                van een verhaal; geeft enkel een URL terug
//    verwijderWezenAfbeelding -> wezen_id           : afbeelding weghalen
//
//  Enkel goedgekeurde gebruikers mogen uploaden.
// ============================================================================

require_once 'helpers.php';

vereisGoedgekeurd();

$action = $_POST['action'] ?? $_GET['action'] ?? null;

// Map waar de afbeeldingen op de server bewaard worden.
$UPLOAD_MAP = __DIR__ . '/../uploads/';

try {

    // --- Afbeelding van een wezen instellen/vervangen --------------------
    if ($action === 'wezenAfbeelding') {
        $wezen_id = filter_var($_POST['wezen_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$wezen_id) {
            stuurFout('Ongeldig wezen-id.');
        }

        // Oude afbeelding opzoeken om ze straks te kunnen verwijderen.
        $stmt = $pdo->prepare('SELECT afbeelding FROM wezen WHERE id = :id');
        $stmt->execute([':id' => $wezen_id]);
        $rij = $stmt->fetch();
        if (!$rij) {
            stuurFout('Wezen niet gevonden.', 404);
        }

        // Nieuw bestand bewaren (validatie zit in bewaarAfbeelding).
        $bestandsnaam = bewaarAfbeelding('bestand', $UPLOAD_MAP);

        // Pas nadat het nieuwe bestand veilig opgeslagen is, het oude wissen.
        if (!empty($rij['afbeelding'])) {
            verwijderBestand($UPLOAD_MAP . basename($rij['afbeelding']));
        }

        // Bestandsnaam in de databank zetten.
        $up = $pdo->prepare('UPDATE wezen SET afbeelding = :a WHERE id = :id');
        $up->execute([':a' => $bestandsnaam, ':id' => $wezen_id]);

        stuurOk(['afbeelding' => $bestandsnaam, 'url' => 'uploads/' . $bestandsnaam]);
    }

    // --- Afbeelding in de leestekst van een verhaal ----------------------
    if ($action === 'inlineAfbeelding') {
        $bestandsnaam = bewaarAfbeelding('bestand', $UPLOAD_MAP);
        // We bewaren niets in de databank: de <img> komt rechtstreeks in de
        // leestekst-HTML terecht. We geven enkel de URL terug.
        stuurOk(['url' => 'uploads/' . $bestandsnaam]);
    }

    // --- Afbeelding van een wezen verwijderen ----------------------------
    if ($action === 'verwijderWezenAfbeelding') {
        $wezen_id = filter_var($_POST['wezen_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$wezen_id) {
            stuurFout('Ongeldig wezen-id.');
        }

        $stmt = $pdo->prepare('SELECT afbeelding FROM wezen WHERE id = :id');
        $stmt->execute([':id' => $wezen_id]);
        $rij = $stmt->fetch();
        if ($rij && !empty($rij['afbeelding'])) {
            verwijderBestand($UPLOAD_MAP . basename($rij['afbeelding']));
        }

        $up = $pdo->prepare('UPDATE wezen SET afbeelding = NULL WHERE id = :id');
        $up->execute([':id' => $wezen_id]);

        stuurOk(['afbeelding' => null]);
    }

    stuurFout('Onbekende actie.', 404);

} catch (Throwable $e) {
    stuurFout('Serverfout bij het uploaden.', 500);
}


// ----------------------------------------------------------------------------
//  bewaarAfbeelding()  -  Valideer en bewaar 1 geuploade afbeelding.
//  Geeft de nieuwe (veilige, unieke) bestandsnaam terug.
// ----------------------------------------------------------------------------
function bewaarAfbeelding(string $veld, string $map): string
{
    // 1) Is er wel een bestand correct geupload?
    if (!isset($_FILES[$veld]) || $_FILES[$veld]['error'] !== UPLOAD_ERR_OK) {
        stuurFout('Er is geen geldig bestand geupload.');
    }

    $bestand = $_FILES[$veld];

    // 2) Maximaal 4 MB.
    if ($bestand['size'] > 4 * 1024 * 1024) {
        stuurFout('De afbeelding mag maximaal 4 MB groot zijn.');
    }

    // 3) Is het ECHT een afbeelding? getimagesize() leest het bestand zelf,
    //    dus we vertrouwen niet blind op de extensie of de meegestuurde naam.
    $info = @getimagesize($bestand['tmp_name']);
    if ($info === false) {
        stuurFout('Het bestand is geen geldige afbeelding.');
    }

    // 4) Enkel veilige beeldtypes toelaten; we bepalen zelf de extensie.
    $toegestaan = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];
    $type = $info[2];
    if (!isset($toegestaan[$type])) {
        stuurFout('Enkel JPG, PNG, GIF of WEBP zijn toegelaten.');
    }
    $extensie = $toegestaan[$type];

    // 5) Unieke bestandsnaam maken (nooit de naam van de gebruiker gebruiken).
    $nieuweNaam = 'img_' . bin2hex(random_bytes(8)) . '.' . $extensie;

    // 6) Map bestaat? Anders aanmaken.
    if (!is_dir($map)) {
        mkdir($map, 0775, true);
    }

    // 7) Verplaats het tijdelijke bestand naar de uploads-map.
    if (!move_uploaded_file($bestand['tmp_name'], $map . $nieuweNaam)) {
        stuurFout('Kon het bestand niet opslaan.', 500);
    }

    return $nieuweNaam;
}

// Verwijder een bestand veilig (als het bestaat).
function verwijderBestand(string $pad): void
{
    if (is_file($pad)) {
        @unlink($pad);
    }
}
