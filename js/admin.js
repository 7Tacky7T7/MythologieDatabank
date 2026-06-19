// ============================================================================
//  admin.js  -  Gebruikersbeheer (enkel zichtbaar/bruikbaar voor admins)
// ----------------------------------------------------------------------------
//  Een administrator ziet hier alle accounts en kan ze goedkeuren ("registreren")
//  of de goedkeuring intrekken. Pas na goedkeuring zien gebruikers de edit-knoppen.
// ============================================================================

document.addEventListener('DOMContentLoaded', async () => {
    await Auth.init();

    // Niet-admins mogen hier niets zien.
    if (!Auth.isAdmin()) {
        document.getElementById('inhoud').innerHTML =
            '<div class="alert alert-warning">Deze pagina is enkel voor administrators.</div>';
        return;
    }

    await laadGebruikers();
});


// --- Alle gebruikers ophalen en in een tabel zetten -------------------------
async function laadGebruikers() {
    const body = document.getElementById('gebruikersBody');
    try {
        const gebruikers = await Api.get('gebruikers.php');
        body.innerHTML = '';

        gebruikers.forEach((g) => {
            const tr = document.createElement('tr');

            // Statusbadge.
            const status = Number(g.is_goedgekeurd) === 1
                ? '<span class="badge text-bg-success">goedgekeurd</span>'
                : '<span class="badge text-bg-warning">wacht op goedkeuring</span>';
            const adminBadge = Number(g.is_admin) === 1
                ? ' <span class="badge text-bg-dark">admin</span>' : '';

            tr.innerHTML = `
                <td>${UI.escape(g.gebruikersnaam)}${adminBadge}</td>
                <td>${UI.escape(g.email)}</td>
                <td>${status}</td>
                <td class="text-end"></td>`;

            // Actieknop in de laatste cel.
            const cel = tr.querySelector('td:last-child');
            if (Number(g.is_goedgekeurd) === 1) {
                const knop = UI.knop('Goedkeuring intrekken', 'btn btn-sm btn-outline-danger');
                knop.addEventListener('click', () => wijzigStatus(g.id, 'intrekken'));
                cel.append(knop);
            } else {
                const knop = UI.knop('Goedkeuren', 'btn btn-sm btn-success');
                knop.addEventListener('click', () => wijzigStatus(g.id, 'goedkeuren'));
                cel.append(knop);
            }

            body.append(tr);
        });
    } catch (err) {
        UI.fout(document.getElementById('melding'), err.message);
    }
}


// --- Een gebruiker goedkeuren of intrekken ----------------------------------
async function wijzigStatus(id, actie) {
    try {
        await Api.post('gebruikers.php', { action: actie, id: id });
        await laadGebruikers();
    } catch (err) {
        UI.fout(document.getElementById('melding'), err.message);
    }
}
