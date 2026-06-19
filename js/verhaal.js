// ============================================================================
//  verhaal.js  -  Detailpagina van 1 verhaal
// ----------------------------------------------------------------------------
//  - Toont titel, eeuw, synopsis, gekoppelde landen, gekoppelde wezens en de
//    rijke leestekst.
//  - Voor goedgekeurde gebruikers: alles inline bewerken, landen en wezens
//    koppelen/ontkoppelen, afbeeldingen in de tekst invoegen, verhaal wissen.
// ============================================================================

// Het id van het verhaal staat in de URL: verhaal.html?id=3
const VERHAAL_ID = parseInt(new URLSearchParams(location.search).get('id'), 10);

let verhaalData = null;     // { verhaal, landen, wezens }
let alleLanden  = [];       // voor de keuzelijst bij "land koppelen"
let alleWezens  = [];       // voor de keuzelijst bij "wezen koppelen"
let editorsKlaar = false;   // editors maar 1x koppelen


document.addEventListener('DOMContentLoaded', async () => {
    await Auth.init();

    if (!VERHAAL_ID) {
        UI.fout(document.getElementById('melding'), 'Geen geldig verhaal gekozen.');
        return;
    }

    // De koppel/ontkoppel-knoppen hangen af van de rechten -> bij elke
    // wijziging van de login opnieuw tekenen.
    document.addEventListener('authveranderd', () => {
        if (verhaalData) { renderLanden(); renderWezens(); }
    });

    document.getElementById('btnVerwijder').addEventListener('click', verwijderVerhaal);

    await laad();
});


// --- Alles (her)laden van de server -----------------------------------------
async function laad() {
    try {
        verhaalData = await Api.get('verhalen.php?action=detail&id=' + VERHAAL_ID);
        alleLanden  = await Api.get('landen.php');
        alleWezens  = await Api.get('wezens.php');

        renderBasis();
        renderLanden();
        renderWezens();
        koppelEditors();      // 1x de inline-editors aanzetten
    } catch (err) {
        UI.fout(document.getElementById('melding'), err.message);
    }
}


// --- Titel, eeuw, synopsis en leestekst tonen -------------------------------
function renderBasis() {
    const v = verhaalData.verhaal;
    document.getElementById('titel').textContent = v.titel;
    document.getElementById('eeuw').textContent = UI.eeuwTekst(v.eeuw);
    document.getElementById('synopsis').textContent = v.synopsis;
    // leestekst is rijke HTML (server heeft <script> al verwijderd).
    document.getElementById('leestekst').innerHTML = v.leestekst;
    document.title = v.titel + ' - Mythologie Databank';
}


// --- De gekoppelde landen tonen (met klikbare links) ------------------------
function renderLanden() {
    const zone = document.getElementById('landen');
    zone.innerHTML = '';

    verhaalData.landen.forEach((land) => {
        const badge = document.createElement('span');
        badge.className = 'badge text-bg-light border me-2 mb-2 fs-6';

        // Klik op een land -> alle verhalen van dat land.
        const link = document.createElement('a');
        link.href = 'land.html?id=' + land.id;
        link.className = 'text-decoration-none';
        link.textContent = land.naam;
        badge.append(link);

        // Bewerker mag ontkoppelen (× naast het land).
        if (Auth.magBewerken()) {
            const x = UI.knop('×', 'btn btn-sm btn-link text-danger p-0 ms-2');
            x.title = 'Ontkoppel dit land';
            x.addEventListener('click', async () => {
                await voerUit(() => Api.post('verhalen.php', {
                    action: 'ontkoppelLand', verhaal_id: VERHAAL_ID, land_id: land.id
                }));
            });
            badge.append(x);
        }
        zone.append(badge);
    });

    // Bewerker krijgt een veldje om een land te koppelen of nieuw aan te maken.
    if (Auth.magBewerken()) {
        zone.append(maakKoppelLandFormulier());
    }
}


// --- De gekoppelde wezens tonen (met klikbare links) ------------------------
function renderWezens() {
    const zone = document.getElementById('wezens');
    zone.innerHTML = '';

    if (verhaalData.wezens.length === 0) {
        zone.innerHTML = '<p class="text-muted mb-2">Nog geen wezens gekoppeld.</p>';
    }

    verhaalData.wezens.forEach((w) => {
        const rij = document.createElement('div');
        rij.className = 'list-group-item d-flex justify-content-between align-items-center';

        const link = document.createElement('a');
        link.href = 'wezen.html?id=' + w.id;
        link.textContent = w.naam;
        rij.append(link);

        if (Auth.magBewerken()) {
            const x = UI.knop('Ontkoppel', 'btn btn-sm btn-outline-danger');
            x.addEventListener('click', async () => {
                await voerUit(() => Api.post('verhalen.php', {
                    action: 'ontkoppelWezen', verhaal_id: VERHAAL_ID, wezen_id: w.id
                }));
            });
            rij.append(x);
        }
        zone.append(rij);
    });

    if (Auth.magBewerken()) {
        zone.append(maakKoppelWezenFormulier());
    }
}


// --- Formulier: een land koppelen (bestaand of nieuw) -----------------------
function maakKoppelLandFormulier() {
    const wrap = document.createElement('div');
    wrap.className = 'input-group input-group-sm mt-2 koppel-form';

    const input = document.createElement('input');
    input.className = 'form-control';
    input.placeholder = 'Land koppelen of nieuw aanmaken...';
    input.setAttribute('list', 'landenLijst');

    // datalist met alle bestaande landen (autocomplete).
    const datalist = document.createElement('datalist');
    datalist.id = 'landenLijst';
    alleLanden.forEach((l) => {
        const opt = document.createElement('option');
        opt.value = l.naam;
        datalist.append(opt);
    });

    const knop = UI.knop('Koppel', 'btn btn-outline-primary');
    knop.addEventListener('click', async () => {
        const naam = input.value.trim();
        if (!naam) return;
        await voerUit(() => Api.post('verhalen.php', {
            action: 'koppelLand', verhaal_id: VERHAAL_ID, naam: naam
        }));
    });

    wrap.append(input, knop, datalist);
    return wrap;
}


// --- Formulier: een bestaand wezen koppelen ---------------------------------
function maakKoppelWezenFormulier() {
    const wrap = document.createElement('div');
    wrap.className = 'input-group input-group-sm mt-2 koppel-form';

    const select = document.createElement('select');
    select.className = 'form-select';

    // Enkel wezens tonen die nog NIET gekoppeld zijn.
    // (Number() zodat het vergelijken werkt, ongeacht of een id als getal of
    //  als tekst uit de databank komt.)
    const gekoppeldeIds = verhaalData.wezens.map((w) => Number(w.id));
    const beschikbaar = alleWezens.filter((w) => !gekoppeldeIds.includes(Number(w.id)));

    const standaard = document.createElement('option');
    standaard.value = '';
    standaard.textContent = beschikbaar.length ? 'Kies een wezen...' : 'Geen wezens meer';
    select.append(standaard);

    beschikbaar.forEach((w) => {
        const opt = document.createElement('option');
        opt.value = w.id;
        opt.textContent = w.naam;
        select.append(opt);
    });

    const knop = UI.knop('Koppel', 'btn btn-outline-primary');
    knop.addEventListener('click', async () => {
        if (!select.value) return;
        await voerUit(() => Api.post('verhalen.php', {
            action: 'koppelWezen', verhaal_id: VERHAAL_ID, wezen_id: select.value
        }));
    });

    wrap.append(select, knop);
    return wrap;
}


// --- De inline-editors aan de potloodknopjes koppelen (1x) ------------------
function koppelEditors() {
    if (editorsKlaar) return;
    editorsKlaar = true;

    // Titel (korte tekst).
    Editor.inlineTekst({
        toonEl: document.getElementById('titel'),
        penEl: document.getElementById('penTitel'),
        haalWaarde: () => verhaalData.verhaal.titel,
        bewaar: async (waarde) => {
            await Api.post('verhalen.php', { action: 'update', id: VERHAAL_ID, titel: waarde });
            verhaalData.verhaal.titel = waarde;
        }
    });

    // Eeuw (getal -> weergave geformatteerd).
    Editor.inlineTekst({
        toonEl: document.getElementById('eeuw'),
        penEl: document.getElementById('penEeuw'),
        type: 'number',
        haalWaarde: () => verhaalData.verhaal.eeuw,
        bewaar: async (waarde) => {
            await Api.post('verhalen.php', { action: 'update', id: VERHAAL_ID, eeuw: waarde });
            verhaalData.verhaal.eeuw = waarde;
        },
        naWaarde: (waarde) => {
            document.getElementById('eeuw').textContent = UI.eeuwTekst(waarde);
        }
    });

    // Synopsis (langere tekst -> textarea).
    Editor.inlineTekst({
        toonEl: document.getElementById('synopsis'),
        penEl: document.getElementById('penSynopsis'),
        type: 'textarea',
        haalWaarde: () => verhaalData.verhaal.synopsis,
        bewaar: async (waarde) => {
            await Api.post('verhalen.php', { action: 'update', id: VERHAAL_ID, synopsis: waarde });
            verhaalData.verhaal.synopsis = waarde;
        }
    });

    // Leestekst (rijke tekst MET afbeeldingen invoegen).
    Editor.inlineRijk({
        toonEl: document.getElementById('leestekst'),
        penEl: document.getElementById('penLeestekst'),
        metAfbeelding: true,
        haalHtml: () => verhaalData.verhaal.leestekst,
        bewaar: async (html) => {
            await Api.post('verhalen.php', { action: 'update', id: VERHAAL_ID, leestekst: html });
            verhaalData.verhaal.leestekst = html;
        }
    });
}


// --- Verhaal verwijderen ----------------------------------------------------
async function verwijderVerhaal() {
    if (!confirm('Dit verhaal definitief verwijderen?')) return;
    try {
        await Api.post('verhalen.php', { action: 'delete', id: VERHAAL_ID });
        window.location.href = 'index.html';
    } catch (err) {
        UI.fout(document.getElementById('melding'), err.message);
    }
}


// --- Klein hulpje: voer een actie uit en herlaad de pagina-data -------------
//  Vangt fouten op en toont ze, en herlaadt daarna de detailgegevens.
async function voerUit(actie) {
    try {
        await actie();
        await laad();
    } catch (err) {
        UI.fout(document.getElementById('melding'), err.message);
    }
}
