-- ============================================================================
--  wezen_afbeeldingen.sql
--  Zet de meegeleverde afbeeldingen op de bestaande wezens, ZONDER de databank
--  opnieuw te importeren (dus zonder je eigen toegevoegde data te wissen).
--  Koppelt op naam, zodat het werkt ongeacht de id's.
-- ============================================================================

UPDATE wezen SET afbeelding = 'assets/wezens/medusa.svg'     WHERE naam = 'Medusa';
UPDATE wezen SET afbeelding = 'assets/wezens/minotaurus.svg' WHERE naam = 'Minotaurus';
UPDATE wezen SET afbeelding = 'assets/wezens/anubis.svg'     WHERE naam = 'Anubis';
UPDATE wezen SET afbeelding = 'assets/wezens/banshee.svg'    WHERE naam = 'Banshee';
UPDATE wezen SET afbeelding = 'assets/wezens/fenrir.svg'     WHERE naam = 'Fenrir';
UPDATE wezen SET afbeelding = 'assets/wezens/djinn.svg'      WHERE naam = 'Djinn';
UPDATE wezen SET afbeelding = 'assets/wezens/mami-wata.svg'  WHERE naam = 'Mami Wata';
UPDATE wezen SET afbeelding = 'assets/wezens/bunyip.svg'     WHERE naam = 'Bunyip';
UPDATE wezen SET afbeelding = 'assets/wezens/theseus.svg'    WHERE naam = 'Theseus';
