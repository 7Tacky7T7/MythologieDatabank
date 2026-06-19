// ============================================================================
//  api.js  -  Herbruikbare laag om met onze eigen API te praten
// ----------------------------------------------------------------------------
//  Alle communicatie met de server loopt via dit object. Zo staat de fetch-
//  logica (response.ok controleren, JSON lezen, payload.ok controleren) maar
//  op 1 plaats. De rest van de code roept gewoon Api.get(...) of Api.post(...)
//  op en krijgt ofwel de data terug, ofwel een Error die we met try/catch
//  opvangen.
//
//  Onze server antwoordt altijd in de vaste structuur:
//     { "ok": true,  "data":  ... }
//     { "ok": false, "error": "..." }
// ============================================================================

const Api = {

    // Basis-pad naar de map met endpoints (vanaf een pagina in de hoofdmap).
    BASIS: 'api/',

    // --- GET: data ophalen (bv. een lijst of een detail) --------------------
    async get(endpoint) {
        const response = await fetch(this.BASIS + endpoint);
        return await this._verwerk(response);
    },

    // --- POST: data versturen als JSON (create/update/delete/koppelen) ------
    async post(endpoint, body) {
        const response = await fetch(this.BASIS + endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        return await this._verwerk(response);
    },

    // --- UPLOAD: een echt bestand versturen via FormData --------------------
    //  (geen JSON-header zetten: de browser doet dat zelf bij FormData)
    async upload(endpoint, formData) {
        const response = await fetch(this.BASIS + endpoint, {
            method: 'POST',
            body: formData
        });
        return await this._verwerk(response);
    },

    // --- Privé: 1 plaats om elk antwoord op dezelfde manier te controleren --
    async _verwerk(response) {
        // 1) Geldige JSON terugkrijgen? Zo niet: de server stuurde wellicht HTML
        //    (bv. een PHP-foutpagina) en kreeg JavaScript iets onverwachts.
        let payload;
        try {
            payload = await response.json();
        } catch (e) {
            throw new Error('De server stuurde geen geldige JSON terug.');
        }

        // 2) Heeft de applicatielogica zelf "het is niet gelukt" gezegd?
        if (!payload.ok) {
            throw new Error(payload.error || 'Er ging iets mis op de server.');
        }

        // 3) Alles in orde: geef enkel de nuttige data terug.
        return payload.data;
    }
};
