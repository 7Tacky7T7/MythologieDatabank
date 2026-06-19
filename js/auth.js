// ============================================================================
//  auth.js  -  Aanmelden, registreren, afmelden en rechten in de interface
// ----------------------------------------------------------------------------
//  Houdt bij WIE er is ingelogd en past de interface daaraan aan:
//    - niet ingelogd            -> enkel lezen, knoppen "Aanmelden/Registreren"
//    - ingelogd, niet goedgekeurd -> kan lezen, maar GEEN edit-knoppen
//    - ingelogd, goedgekeurd    -> edit-knoppen verschijnen
//    - administrator            -> ziet ook de link naar gebruikersbeheer
//
//  Elke pagina luistert naar het event "authveranderd" om zijn eigen
//  edit-knoppen opnieuw te tekenen.
// ============================================================================

const Auth = {

    gebruiker: null,    // null = niemand ingelogd

    // --- Rechten-vragen (gebruikt door de pagina's) -------------------------
    isIngelogd() { return this.gebruiker !== null; },
    magBewerken() { return this.gebruiker !== null && Number(this.gebruiker.is_goedgekeurd) === 1; },
    isAdmin() { return this.gebruiker !== null && Number(this.gebruiker.is_admin) === 1; },

    // --- Opstarten: formulieren koppelen en status ophalen ------------------
    async init() {
        // Aanmeld-formulier.
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', (e) => this._login(e));
        }
        // Registratie-formulier.
        const regForm = document.getElementById('regForm');
        if (regForm) {
            regForm.addEventListener('submit', (e) => this._register(e));
        }
        // Afmeld-knop.
        const uitKnop = document.getElementById('navUitloggen');
        if (uitKnop) {
            uitKnop.addEventListener('click', () => this._logout());
        }
        // Wie is er ingelogd? (vraagt het de server na een refresh)
        await this.laadStatus();
    },

    // --- Huidige sessie ophalen bij de server -------------------------------
    async laadStatus() {
        try {
            const data = await Api.get('auth.php?action=me');
            this.gebruiker = data.gebruiker;   // null of een object
        } catch (e) {
            this.gebruiker = null;
        }
        this.pasUiAan();
    },

    // --- Aanmelden ----------------------------------------------------------
    async _login(e) {
        e.preventDefault();
        const melding = document.getElementById('loginMelding');
        UI.wis(melding);
        try {
            const data = await Api.post('auth.php', {
                action: 'login',
                gebruikersnaam: document.getElementById('loginNaam').value.trim(),
                wachtwoord: document.getElementById('loginWachtwoord').value
            });
            this.gebruiker = data.gebruiker;
            this.pasUiAan();
            this._sluitModal();
            document.getElementById('loginForm').reset();
        } catch (err) {
            UI.fout(melding, err.message);
        }
    },

    // --- Registreren --------------------------------------------------------
    async _register(e) {
        e.preventDefault();
        const melding = document.getElementById('regMelding');
        UI.wis(melding);
        try {
            const data = await Api.post('auth.php', {
                action: 'register',
                gebruikersnaam: document.getElementById('regNaam').value.trim(),
                email: document.getElementById('regEmail').value.trim(),
                wachtwoord: document.getElementById('regWachtwoord').value
            });
            UI.melding(melding, 'success', data.bericht);
            document.getElementById('regForm').reset();
        } catch (err) {
            UI.fout(melding, err.message);
        }
    },

    // --- Afmelden -----------------------------------------------------------
    async _logout() {
        try {
            await Api.post('auth.php', { action: 'logout' });
        } catch (e) { /* zelfs als dit faalt, melden we lokaal af */ }
        this.gebruiker = null;
        this.pasUiAan();
    },

    // --- De interface aanpassen aan de rechten ------------------------------
    pasUiAan() {
        const ingelogd = this.isIngelogd();

        // Toon/verberg elementen op basis van CSS-klassen.
        this._toggle('.js-ingelogd', ingelogd);
        this._toggle('.js-uitgelogd', !ingelogd);
        this._toggle('.alleen-admin', this.isAdmin());
        // De bewerk-knoppen (potloden, "Nieuw", "Verwijder") verschijnen pas
        // wanneer de gebruiker is ingelogd EN door een admin goedgekeurd.
        this._toggle('.alleen-bewerker', this.magBewerken());

        // Naam van de gebruiker in de navigatiebalk.
        const naamEl = document.getElementById('navNaam');
        if (naamEl) {
            naamEl.textContent = ingelogd ? this.gebruiker.gebruikersnaam : '';
        }

        // Een wachtende gebruiker (ingelogd maar niet goedgekeurd) krijgt een hint.
        const wachtBadge = document.getElementById('navWachtBadge');
        if (wachtBadge) {
            const wacht = ingelogd && !this.magBewerken();
            wachtBadge.classList.toggle('d-none', !wacht);
        }

        // Laat de pagina haar eigen edit-knoppen opnieuw tekenen.
        document.dispatchEvent(new CustomEvent('authveranderd'));
    },

    // Hulpje: toon (true) of verberg (false) alle elementen met een selector.
    _toggle(selector, tonen) {
        document.querySelectorAll(selector).forEach((el) => {
            el.classList.toggle('d-none', !tonen);
        });
    },

    // Sluit de aanmeld/registratie-modal (Bootstrap).
    _sluitModal() {
        const modalEl = document.getElementById('authModal');
        if (modalEl && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        }
    }
};
