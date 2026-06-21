// ============================================================================
//  editor.js  -  Herbruikbare "inline" bewerking en rijke-tekst editor
// ----------------------------------------------------------------------------
//  Dit is het hart van de bewerk-interface uit de opdracht:
//    * inlineTekst : een statisch stuk tekst (titel, synopsis, eeuw, naam)
//                    verandert in een invoerveld met een vinkje en een kruisje.
//    * inlineRijk  : een stuk rijke tekst (leestekst/beschrijving) verandert in
//                    een bewerkbaar vak met een werkbalk (bold, italic, h1-h3)
//                    en eventueel een knop om een afbeelding in te voegen.
//
//  Geen externe editor-bibliotheek: we gebruiken een "contenteditable" div en
//  document.execCommand(), dat in elke browser werkt. Zo blijft alles
//  uitlegbaar en binnen wat we geleerd hebben.
// ============================================================================

const Editor = {

    bezig: false,   // er mag maar 1 inline-editor tegelijk open staan

    // ========================================================================
    //  1) INLINE TEKST  (korte tekst of getal)
    //  opties = {
    //     toonEl,            element dat de tekst toont
    //     penEl,             het potlood-knopje
    //     type,              'text' | 'number' | 'textarea'  (standaard 'text')
    //     haalWaarde(),      geeft de huidige ruwe waarde terug
    //     bewaar(waarde),    async; slaat op in de databank (mag fout gooien)
    //     naWaarde(waarde)   optioneel; werkt de weergave bij na het opslaan
    //  }
    // ========================================================================
    inlineTekst(opties) {
        opties.penEl.addEventListener('click', () => {
            if (this.bezig) return;          // al iets in bewerking
            this.bezig = true;

            const type = opties.type || 'text';
            opties.toonEl.classList.add('d-none');
            opties.penEl.classList.add('d-none');

            // --- Invoerveld bouwen ---
            const wrap = document.createElement('div');
            wrap.className = 'inline-editor my-2';

            const rij = document.createElement('div');
            rij.className = 'd-flex align-items-start gap-2';

            const invoer = (type === 'textarea')
                ? document.createElement('textarea')
                : document.createElement('input');
            invoer.className = 'form-control';
            if (type !== 'textarea') invoer.type = type;
            if (type === 'textarea') invoer.rows = 3;
            invoer.value = opties.haalWaarde();

            const okBtn  = this._iconKnop('bi-check-lg', 'btn-success', 'Bewaren');
            const anBtn  = this._iconKnop('bi-x-lg', 'btn-outline-secondary', 'Annuleren');

            rij.append(invoer, okBtn, anBtn);
            const foutEl = this._foutRegel();
            wrap.append(rij, foutEl);
            opties.toonEl.after(wrap);
            invoer.focus();

            // --- Sluiten en terug naar weergave-modus ---
            const stop = () => {
                wrap.remove();
                opties.toonEl.classList.remove('d-none');
                opties.penEl.classList.remove('d-none');
                this.bezig = false;
            };

            anBtn.addEventListener('click', stop);

            okBtn.addEventListener('click', async () => {
                const waarde = invoer.value;
                okBtn.disabled = true;
                anBtn.disabled = true;
                foutEl.textContent = '';
                try {
                    await opties.bewaar(waarde);
                    if (opties.naWaarde) opties.naWaarde(waarde);
                    else opties.toonEl.textContent = waarde;
                    stop();
                } catch (err) {
                    foutEl.textContent = err.message;
                    okBtn.disabled = false;
                    anBtn.disabled = false;
                }
            });
        });
    },

    // ========================================================================
    //  2) INLINE RIJKE TEKST  (leestekst / beschrijving)
    //  opties = {
    //     toonEl,           element dat de HTML toont (innerHTML)
    //     penEl,            het potlood-knopje
    //     metAfbeelding,    true -> toon ook "Afbeelding invoegen" (voor verhalen)
    //     haalHtml(),       geeft de huidige HTML terug
    //     bewaar(html),     async; slaat op (mag fout gooien)
    //  }
    // ========================================================================
    inlineRijk(opties) {
        opties.penEl.addEventListener('click', () => {
            if (this.bezig) return;
            this.bezig = true;

            opties.toonEl.classList.add('d-none');
            opties.penEl.classList.add('d-none');

            const wrap = document.createElement('div');
            wrap.className = 'inline-editor my-2';

            // --- Werkbalk ---
            const balk = this._maakWerkbalk(opties.metAfbeelding);

            // --- Bewerkbaar tekstvak ---
            const vak = document.createElement('div');
            vak.className = 'rijke-tekst form-control';
            vak.contentEditable = 'true';
            vak.innerHTML = opties.haalHtml();

            this._koppelWerkbalk(balk, vak);   // knoppen aan het vak koppelen

            // --- Bewaar/annuleer ---
            const knoppen = document.createElement('div');
            knoppen.className = 'd-flex gap-2 mt-2';
            const okBtn = this._iconKnop('bi-check-lg', 'btn-success', 'Bewaren');
            okBtn.append(document.createTextNode(' Bewaren'));
            const anBtn = this._iconKnop('bi-x-lg', 'btn-outline-secondary', 'Annuleren');
            anBtn.append(document.createTextNode(' Annuleren'));
            knoppen.append(okBtn, anBtn);

            const foutEl = this._foutRegel();
            wrap.append(balk, vak, knoppen, foutEl);
            opties.toonEl.after(wrap);
            vak.focus();

            const stop = () => {
                wrap.remove();
                opties.toonEl.classList.remove('d-none');
                opties.penEl.classList.remove('d-none');
                this.bezig = false;
            };

            anBtn.addEventListener('click', stop);

            okBtn.addEventListener('click', async () => {
                const html = vak.innerHTML;
                okBtn.disabled = true;
                anBtn.disabled = true;
                foutEl.textContent = '';
                try {
                    await opties.bewaar(html);
                    opties.toonEl.innerHTML = html;
                    stop();
                } catch (err) {
                    foutEl.textContent = err.message;
                    okBtn.disabled = false;
                    anBtn.disabled = false;
                }
            });
        });
    },

    // ========================================================================
    //  Hulpfuncties (privé)
    // ========================================================================

    // Een klein icoon-knopje (Bootstrap Icons).
    _iconKnop(icoon, klasse, titel) {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'btn btn-sm ' + klasse;
        b.title = titel;
        b.innerHTML = '<i class="bi ' + icoon + '"></i>';
        return b;
    },

    // Een rode regel om fouten in te tonen.
    _foutRegel() {
        const p = document.createElement('div');
        p.className = 'text-danger small mt-1';
        return p;
    },

    // Bouw de werkbalk met opmaakknoppen.
    _maakWerkbalk(metAfbeelding) {
        const balk = document.createElement('div');
        balk.className = 'btn-toolbar mb-2 gap-1 rijke-werkbalk';

        // Elke knop heeft een "commando" (zie _koppelWerkbalk).
        const knoppen = [
            { cmd: 'bold',        icoon: 'bi-type-bold',  titel: 'Vet' },
            { cmd: 'italic',      icoon: 'bi-type-italic', titel: 'Cursief' },
            { cmd: 'h1',          tekst: 'H1',  titel: 'Titel 1' },
            { cmd: 'h2',          tekst: 'H2',  titel: 'Titel 2' },
            { cmd: 'h3',          tekst: 'H3',  titel: 'Titel 3' },
            { cmd: 'p',           tekst: '¶',   titel: 'Gewone paragraaf' },
        ];
        if (metAfbeelding) {
            knoppen.push({ cmd: 'afbeelding', icoon: 'bi-image', titel: 'Afbeelding invoegen' });
        }

        knoppen.forEach((k) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'btn btn-sm btn-outline-secondary';
            b.title = k.titel;
            b.dataset.cmd = k.cmd;
            b.innerHTML = k.icoon ? '<i class="bi ' + k.icoon + '"></i>' : k.tekst;
            balk.append(b);
        });
        return balk;
    },

    // Koppel de werkbalkknoppen aan het bewerkbare vak.
    _koppelWerkbalk(balk, vak) {
        balk.querySelectorAll('button').forEach((b) => {
            // mousedown + preventDefault: zo verliest het tekstvak zijn selectie niet.
            b.addEventListener('mousedown', (e) => e.preventDefault());
            b.addEventListener('click', () => {
                const cmd = b.dataset.cmd;
                vak.focus();
                if (cmd === 'bold' || cmd === 'italic') {
                    document.execCommand(cmd, false, null);
                } else if (cmd === 'h1' || cmd === 'h2' || cmd === 'h3') {
                    document.execCommand('formatBlock', false, cmd.toUpperCase());
                } else if (cmd === 'p') {
                    document.execCommand('formatBlock', false, 'P');
                } else if (cmd === 'afbeelding') {
                    this._voegAfbeeldingIn(vak);
                }
            });
        });
    },

    // Laat de gebruiker een afbeelding kiezen, upload ze en voeg ze in de tekst in.
    _voegAfbeeldingIn(vak) {
        const kiezer = document.createElement('input');
        kiezer.type = 'file';
        kiezer.accept = 'image/*';
        kiezer.addEventListener('change', async () => {
            if (!kiezer.files.length) return;
            const formData = new FormData();
            formData.append('action', 'inlineAfbeelding');
            formData.append('bestand', kiezer.files[0]);
            try {
                const data = await Api.upload('upload.php', formData);
                vak.focus();
                // Plaats de afbeelding op de huidige cursorpositie.
                document.execCommand('insertHTML', false,
                    '<img src="' + data.url + '" class="img-fluid rounded my-2" alt="">');
            } catch (err) {
                alert('Afbeelding uploaden mislukte: ' + err.message);
            }
        });
        kiezer.click();
    }
};
