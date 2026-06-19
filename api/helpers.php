<?php
// ============================================================================
//  helpers.php  -  Herbruikbare functies voor ALLE endpoints
// ----------------------------------------------------------------------------
//  Door deze functies 1x te schrijven en overal te hergebruiken, blijft de
//  code van de endpoints kort en consistent. Elk endpoint begint met:
//      require_once 'helpers.php';
//  waardoor automatisch ook db.php (en dus $pdo) geladen wordt en de sessie
//  gestart is.
// ============================================================================

// De sessie moet starten VOOR er output is, anders werken cookies niet.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Elk endpoint stuurt JSON terug -> we zetten de header hier centraal.
header('Content-Type: application/json; charset=utf-8');

// Maak de databankverbinding ($pdo) beschikbaar.
require_once __DIR__ . '/db.php';


// ----------------------------------------------------------------------------
//  stuurJson()  -  Stuur een JSON-antwoord en stop het script.
//  We gebruiken altijd dezelfde structuur, zodat JavaScript nooit moet raden:
//     succes -> { "ok": true,  "data":  ... }
//     fout   -> { "ok": false, "error": "..." }
// ----------------------------------------------------------------------------
function stuurJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

// Korte hulpjes bovenop stuurJson() (zodat endpoints leesbaar blijven).
function stuurOk($data = null): void
{
    stuurJson(['ok' => true, 'data' => $data]);
}

function stuurFout(string $bericht, int $status = 400): void
{
    stuurJson(['ok' => false, 'error' => $bericht], $status);
}


// ----------------------------------------------------------------------------
//  leesInput()  -  Verzamel alle parameters in 1 array.
//  GET-parameters (filters in de URL) en een JSON-body (bij POST) worden
//  samengevoegd. Daardoor maakt het voor de rest van de code niet uit
//  HOE de data binnenkwam; we werken altijd met dezelfde $params.
// ----------------------------------------------------------------------------
function leesInput(): array
{
    $params = $_GET;                                   // filters uit de URL

    $ruweBody = file_get_contents('php://input');      // ruwe inhoud van de request
    if ($ruweBody !== '' && $ruweBody !== false) {
        $jsonBody = json_decode($ruweBody, true);      // JSON -> PHP-array
        if (is_array($jsonBody)) {
            $params = array_merge($params, $jsonBody);
        }
    }
    return $params;
}


// ----------------------------------------------------------------------------
//  Sessie-helpers (authenticatie & autorisatie)
// ----------------------------------------------------------------------------

// Geeft de ingelogde gebruiker terug (als array), of null als niemand inlogt.
function huidigeGebruiker(): ?array
{
    return $_SESSION['gebruiker'] ?? null;
}

// Stopt met een 401-fout als er niemand is ingelogd.
function vereisLogin(): array
{
    $gebruiker = huidigeGebruiker();
    if ($gebruiker === null) {
        stuurFout('Je moet aangemeld zijn.', 401);
    }
    return $gebruiker;
}

// Stopt met een 403-fout als de gebruiker niet door een admin is goedgekeurd.
// Dit beschermt elke schrijf-actie (create/update/delete).
function vereisGoedgekeurd(): array
{
    $gebruiker = vereisLogin();
    if (empty($gebruiker['is_goedgekeurd'])) {
        stuurFout('Je account is nog niet goedgekeurd door een administrator.', 403);
    }
    return $gebruiker;
}

// Stopt met een 403-fout als de gebruiker geen administrator is.
function vereisAdmin(): array
{
    $gebruiker = vereisLogin();
    if (empty($gebruiker['is_admin'])) {
        stuurFout('Enkel een administrator mag dit doen.', 403);
    }
    return $gebruiker;
}


// ----------------------------------------------------------------------------
//  schoonHtml()  -  Heel eenvoudige opschoning van rijke tekst.
//  De leestekst/beschrijving mag HTML bevatten (bold, italic, h1..h3, img),
//  maar <script>-tags willen we NOOIT bewaren (XSS-bescherming).
// ----------------------------------------------------------------------------
function schoonHtml(string $html): string
{
    // Verwijder volledige <script>...</script> blokken.
    $html = preg_replace('#<script\b[^>]*>(.*?)</script>#is', '', $html);
    return trim($html);
}
