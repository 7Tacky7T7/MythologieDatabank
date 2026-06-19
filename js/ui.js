// ============================================================================
//  ui.js  -  Kleine herbruikbare hulpjes voor de interface
// ----------------------------------------------------------------------------
//  Bevat functies die op veel pagina's terugkomen: tekst veilig tonen,
//  meldingen tonen, een eeuw netjes formatteren, enz.
// ============================================================================

const UI = {

    // --- Tekst veilig maken tegen HTML-injectie (XSS) -----------------------
    //  We gebruiken dit telkens we tekst van de databank in de pagina zetten
    //  via innerHTML. De browser ziet < en > dan als gewone tekst.
    escape(tekst) {
        if (tekst === null || tekst === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(tekst);
        return div.innerHTML;
    },

    // --- Een Bootstrap-alert tonen in een gekozen container -----------------
    melding(container, type, tekst) {
        // type: 'success' | 'danger' | 'warning' | 'info'
        container.innerHTML =
            '<div class="alert alert-' + type + '" role="alert">' +
            this.escape(tekst) +
            '</div>';
    },

    // Kort: een foutmelding tonen (rood).
    fout(container, tekst) {
        this.melding(container, 'danger', tekst);
    },

    // De melding-container leegmaken.
    wis(container) {
        container.innerHTML = '';
    },

    // --- Een eeuw leesbaar maken --------------------------------------------
    //  In de databank is "eeuw" een getal (negatief = voor Christus).
    //  -8  ->  "8e eeuw v.Chr."      13 -> "13e eeuw n.Chr."
    eeuwTekst(eeuw) {
        eeuw = parseInt(eeuw, 10);
        if (isNaN(eeuw) || eeuw === 0) return 'onbekende periode';
        const absoluut = Math.abs(eeuw);
        const tijdperk = eeuw < 0 ? 'v.Chr.' : 'n.Chr.';
        return absoluut + 'e eeuw ' + tijdperk;
    },

    // --- De juiste URL van een wezen-afbeelding bepalen ---------------------
    //  Bevat de waarde een '/', dan is het een meegeleverde afbeelding
    //  (bv. 'assets/wezens/medusa.svg'). Anders is het een bestand dat de
    //  gebruiker zelf uploadde en in de map 'uploads' staat.
    afbeeldingUrl(afbeelding) {
        if (!afbeelding) return null;
        return afbeelding.indexOf('/') !== -1 ? afbeelding : 'uploads/' + afbeelding;
    },

    // --- Een knop (Bootstrap) maken in code ---------------------------------
    knop(tekst, klasse) {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = klasse;
        b.textContent = tekst;
        return b;
    }
};
