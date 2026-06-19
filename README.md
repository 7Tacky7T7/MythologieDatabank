# Mythologie Databank

Een dynamische webapplicatie: een digitale encyclopedie voor legendes en
mythische wezens uit verschillende culturen (Grieks, Egyptisch, Keltisch,
Noors, Arabisch, Afrikaans, ...).

Examenproject 6AD — gebouwd met **PHP + PDO + MySQL**, een **zelf geschreven
JSON-API** en **vanilla JavaScript** (zonder framework, met Bootstrap 5.3 voor
de opmaak).

---

## Wat kan de applicatie?

- **Verhalen** met titel, synopsis, rijke leestekst (bold/italic/h1–h3 + afbeeldingen),
  een tijdsperiode (eeuw) en één of meerdere landen van herkomst.
- **Wezens** met naam, beschrijving (rijke tekst), max. 1 afbeelding en
  één of meerdere categorieën (type: *soort*, *genre* of *afkomst*).
- **Koppelingen** in twee richtingen: een verhaal toont zijn wezens en landen,
  een wezen toont de verhalen waarin het voorkomt, een land toont al zijn verhalen.
- **Inline bewerken** met een potlood-icoon: statische tekst wordt een invoerveld
  met een vinkje (bewaren) en kruisje (annuleren).
- **Accounts & rechten**: veilig registreren (gehasht wachtwoord), aanmelden,
  en pas bewerken nadat een **administrator** het account heeft goedgekeurd.
- Volledige **CRUD** geïntegreerd in de detailpagina's (geen aparte beheerpagina's).

---

## Installeren (XAMPP / WAMP)

1. **Kopieer** de map `MythologieDatabank` naar je webroot
   (bij XAMPP: `C:\xampp\htdocs\`).
2. Start **Apache** en **MySQL** in het XAMPP-configuratiescherm.
3. Open **phpMyAdmin** (`http://localhost/phpmyadmin`):
   - Maak een databank `mythologie` (tekenset `utf8mb4_general_ci`).
   - Open het tabblad **Import** en kies `database/mythologie.sql`
     (of plak de inhoud in het tabblad **SQL**).
4. Controleer de logingegevens in `api/db.php`
   (standaard bij XAMPP: gebruiker `root`, leeg wachtwoord).
5. Maak **één keer** de administrator aan door in de browser te openen:
   `http://localhost/MythologieDatabank/api/seed_admin.php`
   → daarna kan je inloggen met **admin / admin123**.
   Verwijder dit bestand nadien.
6. Open de site: `http://localhost/MythologieDatabank/index.html`

> Belangrijk: open de site via `http://localhost/...` (Apache), **niet** door
> het HTML-bestand rechtstreeks te dubbelklikken. `fetch()` werkt enkel via http.

---

## Mappenstructuur

```
MythologieDatabank/
├─ index.html / verhaal.html / wezen.html / land.html / admin.html
├─ css/      stijl.css                (eigen vormgeving bovenop Bootstrap)
├─ js/       api, ui, auth, editor    (herbruikbare modules)
│            index, verhaal, wezen, land, admin   (per pagina)
├─ api/      db, helpers              (verbinding + herbruikbare functies)
│            auth, verhalen, wezens, landen, categorieen, gebruikers, upload
│            seed_admin.php           (1x admin aanmaken)
├─ uploads/  (geüploade afbeeldingen)
├─ assets/   logo.svg
├─ database/ mythologie.sql           (structuur 3NF + voorbeelddata)
└─ docs/     documentatie (code-uitleg + databank/3NF-visualisatie)
```

---

## Documentatie

Open `docs/index.html` in de browser voor:
- een **visuele uitleg van de databank** en de normalisatie tot 3NF;
- een **uitleg van de code**, bestand per bestand;
- extra info en mogelijke vragen voor de mondelinge verdediging.

## Code-scheiding

Zoals gevraagd staat elke taal in een eigen bestand: HTML in `.html`,
CSS in `.css`, JavaScript in `.js` en PHP in `.php`. De PHP-bestanden vormen
samen een API die enkel JSON teruggeeft; JavaScript haalt die data op met `fetch()`.
