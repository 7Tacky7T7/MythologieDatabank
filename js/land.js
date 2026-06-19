// ============================================================================
//  land.js  -  Pagina met alle verhalen van 1 land
// ----------------------------------------------------------------------------
//  Wordt geopend wanneer de gebruiker op een land klikt: land.html?id=1
//  Toont de landnaam en alle verhalen die aan dat land gekoppeld zijn.
// ============================================================================

const LAND_ID = parseInt(new URLSearchParams(location.search).get('id'), 10);

document.addEventListener('DOMContentLoaded', async () => {
    await Auth.init();

    if (!LAND_ID) {
        UI.fout(document.getElementById('melding'), 'Geen geldig land gekozen.');
        return;
    }

    try {
        // Naam van het land opzoeken in de volledige landenlijst.
        const landen = await Api.get('landen.php');
        const land = landen.find((l) => Number(l.id) === LAND_ID);
        const naam = land ? land.naam : 'Onbekend land';
        document.getElementById('landNaam').textContent = naam;
        document.title = 'Verhalen uit ' + naam + ' - Mythologie Databank';

        // Alle verhalen van dit land ophalen (server filtert via ?land_id=).
        const verhalen = await Api.get('verhalen.php?land_id=' + LAND_ID);
        const lijst = document.getElementById('lijst');

        if (verhalen.length === 0) {
            lijst.innerHTML = '<p class="text-muted">Geen verhalen gekoppeld aan dit land.</p>';
            return;
        }

        lijst.innerHTML = verhalen.map((v) => `
            <div class="col-sm-6 col-lg-4">
              <a class="card h-100 kaart-link" href="verhaal.html?id=${v.id}">
                <div class="card-body">
                  <h2 class="h5 card-title">${UI.escape(v.titel)}</h2>
                  <p class="text-secondary small mb-2">${UI.eeuwTekst(v.eeuw)}</p>
                  <p class="card-text">${UI.escape(v.synopsis)}</p>
                </div>
              </a>
            </div>`).join('');
    } catch (err) {
        UI.fout(document.getElementById('melding'), err.message);
    }
});
