// ============================================================================
//  wezen.js  -  Detailpagina van 1 mythologisch wezen
// ----------------------------------------------------------------------------
//  - Toont naam, afbeelding, beschrijving, categorieen en de verhalen waarin
//    het wezen voorkomt.
//  - Voor goedgekeurde gebruikers: naam en beschrijving inline bewerken,
//    afbeelding toevoegen/vervangen/verwijderen (max 1), categorieen
//    koppelen/ontkoppelen of nieuwe maken, en het wezen verwijderen.
// ============================================================================

const WEZEN_ID = parseInt(new URLSearchParams(location.search).get('id'), 10);

let wezenData = null;        // { wezen, categorieen, verhalen }
let alleCategorieen = [];    // voor de keuzelijst
let editorsKlaar = false;


document.addEventListener('DOMContentLoaded', async () => {
    await Auth.init();

    if (!WEZEN_ID) {
        UI.fout(document.getElementById('melding'), 'Geen geldig wezen gekozen.');
        return;
    }

    document.addEventListener('authveranderd', () => {
        if (wezenData) { renderAfbeelding(); renderCategorieen(); }
    });

    document.getElementById('btnVerwijder').addEventListener('click', verwijderWezen);

    await laad();
});


// --- Alles (her)laden -------------------------------------------------------
async function laad() {
    try {
        wezenData = await Api.get('wezens.php?action=detail&id=' + WEZEN_ID);
        alleCategorieen = await Api.get('categorieen.php');

        renderBasis();
        renderAfbeelding();
        renderCategorieen();
        renderVerhalen();
        koppelEditors();
    } catch (err) {
        UI.fout(document.getElementById('melding'), err.message);
    }
}


// --- Naam en beschrijving ---------------------------------------------------
function renderBasis() {
    const w = wezenData.wezen;
    document.getElementById('naam').textContent = w.naam;
    document.getElementById('beschrijving').innerHTML = w.beschrijving;
    document.title = w.naam + ' - Mythologie Databank';
}


// --- Afbeelding (max 1) + beheer-knoppen ------------------------------------
function renderAfbeelding() {
    const zone = document.getElementById('afbeeldingZone');
    zone.innerHTML = '';
    const w = wezenData.wezen;

    // De afbeelding zelf (of een leeg vak).
    if (w.afbeelding) {
        const img = document.createElement('img');
        img.src = 'uploads/' + w.afbeelding;
        img.alt = w.naam;
        img.className = 'img-fluid rounded mb-2 wezen-afbeelding';
        zone.append(img);
    } else {
        const leeg = document.createElement('div');
        leeg.className = 'wezen-afbeelding-leeg mb-2';
        leeg.innerHTML = '<i class="bi bi-image"></i><span>Geen afbeelding</span>';
        zone.append(leeg);
    }

    // Beheer-knoppen enkel voor goedgekeurde gebruikers.
    if (!Auth.magBewerken()) return;

    const knoppen = document.createElement('div');
    knoppen.className = 'd-flex gap-2 flex-wrap';

    // "Kies afbeelding" -> verborgen bestand-kiezer.
    const kiezer = document.createElement('input');
    kiezer.type = 'file';
    kiezer.accept = 'image/*';
    kiezer.className = 'd-none';
    kiezer.addEventListener('change', () => uploadAfbeelding(kiezer));

    const kiesKnop = UI.knop(w.afbeelding ? 'Afbeelding vervangen' : 'Afbeelding toevoegen',
                             'btn btn-sm btn-outline-primary');
    kiesKnop.addEventListener('click', () => kiezer.click());
    knoppen.append(kiesKnop, kiezer);

    // "Verwijder afbeelding" enkel als er een is.
    if (w.afbeelding) {
        const wisKnop = UI.knop('Afbeelding verwijderen', 'btn btn-sm btn-outline-danger');
        wisKnop.addEventListener('click', verwijderAfbeelding);
        knoppen.append(wisKnop);
    }
    zone.append(knoppen);
}


// --- Afbeelding uploaden (via FormData) -------------------------------------
async function uploadAfbeelding(kiezer) {
    if (!kiezer.files.length) return;
    const formData = new FormData();
    formData.append('action', 'wezenAfbeelding');
    formData.append('wezen_id', WEZEN_ID);
    formData.append('bestand', kiezer.files[0]);
    try {
        await Api.upload('upload.php', formData);
        await laad();
    } catch (err) {
        UI.fout(document.getElementById('melding'), err.message);
    }
}


// --- Afbeelding verwijderen -------------------------------------------------
async function verwijderAfbeelding() {
    const formData = new FormData();
    formData.append('action', 'verwijderWezenAfbeelding');
    formData.append('wezen_id', WEZEN_ID);
    try {
        await Api.upload('upload.php', formData);
        await laad();
    } catch (err) {
        UI.fout(document.getElementById('melding'), err.message);
    }
}


// --- Categorieen tonen + koppelen -------------------------------------------
function renderCategorieen() {
    const zone = document.getElementById('categorieen');
    zone.innerHTML = '';

    wezenData.categorieen.forEach((c) => {
        const badge = document.createElement('span');
        badge.className = 'badge text-bg-secondary me-2 mb-2 fs-6';
        badge.textContent = c.naam + ' (' + c.type + ')';

        if (Auth.magBewerken()) {
            const x = UI.knop('×', 'btn btn-sm btn-link text-light p-0 ms-2');
            x.title = 'Ontkoppel deze categorie';
            x.addEventListener('click', async () => {
                await voerUit(() => Api.post('wezens.php', {
                    action: 'ontkoppelCategorie', wezen_id: WEZEN_ID, categorie_id: c.id
                }));
            });
            badge.append(x);
        }
        zone.append(badge);
    });

    if (Auth.magBewerken()) {
        zone.append(maakKoppelCategorieFormulier());
        zone.append(maakNieuweCategorieFormulier());
    }
}


// --- Formulier: bestaande categorie koppelen --------------------------------
function maakKoppelCategorieFormulier() {
    const wrap = document.createElement('div');
    wrap.className = 'input-group input-group-sm mt-2 koppel-form';

    const select = document.createElement('select');
    select.className = 'form-select';

    const gekoppeldeIds = wezenData.categorieen.map((c) => Number(c.id));
    const beschikbaar = alleCategorieen.filter((c) => !gekoppeldeIds.includes(Number(c.id)));

    const standaard = document.createElement('option');
    standaard.value = '';
    standaard.textContent = beschikbaar.length ? 'Bestaande categorie...' : 'Geen categorieen meer';
    select.append(standaard);

    beschikbaar.forEach((c) => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.naam + ' (' + c.type + ')';
        select.append(opt);
    });

    const knop = UI.knop('Koppel', 'btn btn-outline-primary');
    knop.addEventListener('click', async () => {
        if (!select.value) return;
        await voerUit(() => Api.post('wezens.php', {
            action: 'koppelCategorie', wezen_id: WEZEN_ID, categorie_id: select.value
        }));
    });

    wrap.append(select, knop);
    return wrap;
}


// --- Formulier: nieuwe categorie maken en meteen koppelen -------------------
function maakNieuweCategorieFormulier() {
    const wrap = document.createElement('div');
    wrap.className = 'input-group input-group-sm mt-2 koppel-form';

    const naam = document.createElement('input');
    naam.className = 'form-control';
    naam.placeholder = 'Nieuwe categorie...';

    const type = document.createElement('select');
    type.className = 'form-select';
    ['soort', 'genre', 'afkomst'].forEach((t) => {
        const opt = document.createElement('option');
        opt.value = t;
        opt.textContent = t;
        type.append(opt);
    });

    const knop = UI.knop('Maak', 'btn btn-outline-success');
    knop.addEventListener('click', async () => {
        if (!naam.value.trim()) return;
        await voerUit(() => Api.post('wezens.php', {
            action: 'koppelCategorie', wezen_id: WEZEN_ID,
            naam: naam.value.trim(), type: type.value
        }));
    });

    wrap.append(naam, type, knop);
    return wrap;
}


// --- De verhalen tonen waarin dit wezen voorkomt ----------------------------
function renderVerhalen() {
    const zone = document.getElementById('verhalen');
    if (wezenData.verhalen.length === 0) {
        zone.innerHTML = '<p class="text-muted">Dit wezen komt (nog) in geen enkel verhaal voor.</p>';
        return;
    }
    zone.innerHTML = wezenData.verhalen.map((v) => `
        <a href="verhaal.html?id=${v.id}" class="list-group-item list-group-item-action">
          <h3 class="h6 mb-1">${UI.escape(v.titel)}</h3>
          <p class="mb-0 small text-secondary">${UI.escape(v.synopsis)}</p>
        </a>`).join('');
}


// --- Inline-editors (1x koppelen) -------------------------------------------
function koppelEditors() {
    if (editorsKlaar) return;
    editorsKlaar = true;

    // Naam.
    Editor.inlineTekst({
        toonEl: document.getElementById('naam'),
        penEl: document.getElementById('penNaam'),
        haalWaarde: () => wezenData.wezen.naam,
        bewaar: async (waarde) => {
            await Api.post('wezens.php', { action: 'update', id: WEZEN_ID, naam: waarde });
            wezenData.wezen.naam = waarde;
        }
    });

    // Beschrijving (rijke tekst, ZONDER losse afbeeldingen -> die zit apart).
    Editor.inlineRijk({
        toonEl: document.getElementById('beschrijving'),
        penEl: document.getElementById('penBeschrijving'),
        metAfbeelding: false,
        haalHtml: () => wezenData.wezen.beschrijving,
        bewaar: async (html) => {
            await Api.post('wezens.php', { action: 'update', id: WEZEN_ID, beschrijving: html });
            wezenData.wezen.beschrijving = html;
        }
    });
}


// --- Wezen verwijderen ------------------------------------------------------
async function verwijderWezen() {
    if (!confirm('Dit wezen definitief verwijderen?')) return;
    try {
        await Api.post('wezens.php', { action: 'delete', id: WEZEN_ID });
        window.location.href = 'index.html';
    } catch (err) {
        UI.fout(document.getElementById('melding'), err.message);
    }
}


// --- Actie uitvoeren + herladen ---------------------------------------------
async function voerUit(actie) {
    try {
        await actie();
        await laad();
    } catch (err) {
        UI.fout(document.getElementById('melding'), err.message);
    }
}
