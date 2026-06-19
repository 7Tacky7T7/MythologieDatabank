// ============================================================================
//  index.js  -  Logica voor de homepagina
// ----------------------------------------------------------------------------
//  Toont alle verhalen en wezens, laat zoeken, en (voor goedgekeurde
//  gebruikers) een nieuw verhaal of wezen aanmaken.
// ============================================================================

document.addEventListener('DOMContentLoaded', async () => {
    await Auth.init();          // navbar + rechten klaarzetten

    laadVerhalen();
    laadWezens();

    // Live zoeken met een kleine "debounce" (pas zoeken als de gebruiker
    // even stopt met typen), zodat we niet bij elke toets fetchen.
    document.getElementById('zoekVerhaal')
        .addEventListener('input', debounce((e) => laadVerhalen(e.target.value), 300));
    document.getElementById('zoekWezen')
        .addEventListener('input', debounce((e) => laadWezens(e.target.value), 300));

    // Formulieren om iets nieuws aan te maken.
    document.getElementById('nvForm').addEventListener('submit', maakVerhaal);
    document.getElementById('nwForm').addEventListener('submit', maakWezen);
});


// --- Verhalen laden en tonen ------------------------------------------------
async function laadVerhalen(zoek = '') {
    const lijst = document.getElementById('lijstVerhalen');
    try {
        const url = 'verhalen.php' + (zoek ? '?q=' + encodeURIComponent(zoek) : '');
        const verhalen = await Api.get(url);

        if (verhalen.length === 0) {
            lijst.innerHTML = '<p class="text-muted">Geen verhalen gevonden.</p>';
            return;
        }

        lijst.innerHTML = verhalen.map((v) => `
            <div class="col-sm-6 col-lg-4">
              <a class="card h-100 kaart-link" href="verhaal.html?id=${v.id}">
                <div class="card-body">
                  <h3 class="h5 card-title">${UI.escape(v.titel)}</h3>
                  <p class="text-secondary small mb-2">${UI.eeuwTekst(v.eeuw)}</p>
                  <p class="card-text">${UI.escape(v.synopsis)}</p>
                </div>
              </a>
            </div>`).join('');
    } catch (err) {
        UI.fout(lijst, err.message);
    }
}


// --- Wezens laden en tonen --------------------------------------------------
async function laadWezens(zoek = '') {
    const lijst = document.getElementById('lijstWezens');
    try {
        const url = 'wezens.php' + (zoek ? '?q=' + encodeURIComponent(zoek) : '');
        const wezens = await Api.get(url);

        if (wezens.length === 0) {
            lijst.innerHTML = '<p class="text-muted">Geen wezens gevonden.</p>';
            return;
        }

        lijst.innerHTML = wezens.map((w) => {
            const url = UI.afbeeldingUrl(w.afbeelding);
            const afbeelding = url
                ? `<img src="${UI.escape(url)}" class="card-img-top wezen-thumb" alt="${UI.escape(w.naam)}">`
                : `<div class="card-img-top wezen-thumb wezen-thumb-leeg"><i class="bi bi-image"></i></div>`;
            return `
            <div class="col-6 col-md-4 col-lg-3">
              <a class="card h-100 kaart-link" href="wezen.html?id=${w.id}">
                ${afbeelding}
                <div class="card-body py-2">
                  <h3 class="h6 card-title mb-0">${UI.escape(w.naam)}</h3>
                </div>
              </a>
            </div>`;
        }).join('');
    } catch (err) {
        UI.fout(lijst, err.message);
    }
}


// --- Nieuw verhaal aanmaken (uit de modal) ----------------------------------
async function maakVerhaal(e) {
    e.preventDefault();
    const melding = document.getElementById('nvMelding');
    UI.wis(melding);
    try {
        const data = await Api.post('verhalen.php', {
            action: 'create',
            titel: document.getElementById('nvTitel').value.trim(),
            synopsis: document.getElementById('nvSynopsis').value.trim(),
            leestekst: '<p>Nog geen leestekst. Klik op het potlood om te beginnen.</p>',
            eeuw: document.getElementById('nvEeuw').value
        });
        // Meteen naar de detailpagina, waar de gebruiker verder bewerkt.
        window.location.href = 'verhaal.html?id=' + data.id;
    } catch (err) {
        UI.fout(melding, err.message);
    }
}


// --- Nieuw wezen aanmaken (uit de modal) ------------------------------------
async function maakWezen(e) {
    e.preventDefault();
    const melding = document.getElementById('nwMelding');
    UI.wis(melding);
    try {
        const data = await Api.post('wezens.php', {
            action: 'create',
            naam: document.getElementById('nwNaam').value.trim(),
            beschrijving: '<p>Nog geen beschrijving.</p>'
        });
        window.location.href = 'wezen.html?id=' + data.id;
    } catch (err) {
        UI.fout(melding, err.message);
    }
}


// --- Kleine debounce-hulpfunctie --------------------------------------------
function debounce(functie, wachttijd) {
    let timer = null;
    return function (...args) {
        clearTimeout(timer);
        timer = setTimeout(() => functie.apply(this, args), wachttijd);
    };
}
