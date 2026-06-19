-- ============================================================================
--  MYTHOLOGIE DATABANK  -  databankstructuur + voorbeelddata
-- ----------------------------------------------------------------------------
--  Genormaliseerd tot de 3e normaalvorm (3NF).
--  Motor: InnoDB  (nodig voor FOREIGN KEYs / referentiele integriteit)
--  Tekenset: utf8mb4  (volledige Unicode, ook accenten en speciale tekens)
--
--  IMPORTEREN:
--    1) Maak in phpMyAdmin een databank "mythologie" (utf8mb4_general_ci)
--    2) Open het tabblad SQL en plak de inhoud van dit bestand, of gebruik
--       "Import" en kies dit .sql-bestand.
--    3) Maak daarna 1x de admin-gebruiker aan via  api/seed_admin.php
-- ============================================================================

-- We verwijderen oude tabellen eerst, zodat het script herhaaldelijk kan
-- uitgevoerd worden. De koppeltabellen verwijzen naar de hoofdtabellen, dus
-- die zetten we tijdelijk uit om volgorde-problemen te vermijden.
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS wezen_categorie;
DROP TABLE IF EXISTS verhaal_wezen;
DROP TABLE IF EXISTS verhaal_land;
DROP TABLE IF EXISTS categorie;
DROP TABLE IF EXISTS land;
DROP TABLE IF EXISTS wezen;
DROP TABLE IF EXISTS verhaal;
DROP TABLE IF EXISTS gebruiker;

SET FOREIGN_KEY_CHECKS = 1;


-- ============================================================================
--  1. HOOFDTABELLEN (entiteiten)
-- ============================================================================

-- ----------------------------------------------------------------------------
--  gebruiker
--  Iemand die een account aanmaakt. Twee aparte rechten-vlaggen:
--    is_goedgekeurd : pas TRUE nadat een administrator de gebruiker
--                     "registreert". Pas dan ziet die persoon de edit-knoppen.
--    is_admin       : mag andere gebruikers goedkeuren.
--  Het wachtwoord wordt NOOIT in klare tekst bewaard, enkel de hash
--  (password_hash, bcrypt -> daarom VARCHAR(255)).
-- ----------------------------------------------------------------------------
CREATE TABLE gebruiker (
  id              INT          NOT NULL AUTO_INCREMENT,
  gebruikersnaam  VARCHAR(50)  NOT NULL,
  email           VARCHAR(255) NOT NULL,
  wachtwoord      VARCHAR(255) NOT NULL,                 -- bcrypt-hash
  is_goedgekeurd  TINYINT(1)   NOT NULL DEFAULT 0,        -- 0 = nog niet goedgekeurd
  is_admin        TINYINT(1)   NOT NULL DEFAULT 0,
  aangemaakt_op   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_gebruiker_naam  (gebruikersnaam),         -- geen dubbele namen
  UNIQUE KEY uq_gebruiker_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ----------------------------------------------------------------------------
--  verhaal
--  Een mythologisch verhaal. "leestekst" bevat rijke tekst (HTML met bold,
--  italic, h1/h2/h3 en <img>) en kan dus groot zijn -> LONGTEXT.
--  "eeuw" is een geheel getal; een NEGATIEF getal betekent "voor Christus"
--  (bv. -8 = 8e eeuw v.Chr., 13 = 13e eeuw n.Chr.).
-- ----------------------------------------------------------------------------
CREATE TABLE verhaal (
  id             INT          NOT NULL AUTO_INCREMENT,
  titel          VARCHAR(150) NOT NULL,
  synopsis       VARCHAR(500) NOT NULL,                  -- korte samenvatting (in lijsten)
  leestekst      LONGTEXT     NOT NULL,                  -- volledige tekst (rijke HTML)
  eeuw           SMALLINT     NOT NULL,                  -- negatief = v.Chr.
  aangemaakt_op  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ----------------------------------------------------------------------------
--  wezen
--  Een mythologisch wezen. Heeft MAXIMAAL 1 afbeelding: daarom is het
--  gewoon 1 kolom (de bestandsnaam in de map /uploads). NULL = geen afbeelding.
-- ----------------------------------------------------------------------------
CREATE TABLE wezen (
  id             INT          NOT NULL AUTO_INCREMENT,
  naam           VARCHAR(100) NOT NULL,
  beschrijving   LONGTEXT     NOT NULL,                  -- rijke HTML
  afbeelding     VARCHAR(255) NULL,                      -- bestandsnaam, max 1, mag leeg zijn
  aangemaakt_op  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ----------------------------------------------------------------------------
--  land
--  Een land van herkomst (Griekenland, Italie, ...). De naam is uniek,
--  zodat hetzelfde land nooit twee keer in de databank staat (3NF: geen
--  herhaalde gegevens).
-- ----------------------------------------------------------------------------
CREATE TABLE land (
  id    INT          NOT NULL AUTO_INCREMENT,
  naam  VARCHAR(100) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_land_naam (naam)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ----------------------------------------------------------------------------
--  categorie
--  Een categorie van een wezen. Elke categorie heeft een naam EN een "type"
--  (de soort/genre/afkomst-aanduiding uit de opdracht, bv. "Geest (soort)").
--  Het type kan maar 3 vaste waarden hebben -> ENUM.
-- ----------------------------------------------------------------------------
CREATE TABLE categorie (
  id    INT          NOT NULL AUTO_INCREMENT,
  naam  VARCHAR(100) NOT NULL,
  type  ENUM('soort','genre','afkomst') NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categorie_naam (naam)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================================
--  2. KOPPELTABELLEN (veel-op-veel relaties)
--  Een verhaal kan meerdere landen/wezens hebben en omgekeerd. Zulke
--  veel-op-veel relaties los je in een relationele databank op met een
--  aparte koppeltabel. De primaire sleutel is de COMBINATIE van beide
--  vreemde sleutels, zodat dezelfde koppeling nooit dubbel kan bestaan.
--  ON DELETE CASCADE: verwijder je een verhaal, dan verdwijnen automatisch
--  ook zijn koppelingen (maar niet de landen/wezens zelf).
-- ============================================================================

-- verhaal  <-->  land
CREATE TABLE verhaal_land (
  verhaal_id  INT NOT NULL,
  land_id     INT NOT NULL,
  PRIMARY KEY (verhaal_id, land_id),
  KEY idx_vl_land (land_id),
  CONSTRAINT fk_vl_verhaal FOREIGN KEY (verhaal_id) REFERENCES verhaal(id) ON DELETE CASCADE,
  CONSTRAINT fk_vl_land    FOREIGN KEY (land_id)    REFERENCES land(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- verhaal  <-->  wezen
CREATE TABLE verhaal_wezen (
  verhaal_id  INT NOT NULL,
  wezen_id    INT NOT NULL,
  PRIMARY KEY (verhaal_id, wezen_id),
  KEY idx_vw_wezen (wezen_id),
  CONSTRAINT fk_vw_verhaal FOREIGN KEY (verhaal_id) REFERENCES verhaal(id) ON DELETE CASCADE,
  CONSTRAINT fk_vw_wezen   FOREIGN KEY (wezen_id)   REFERENCES wezen(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- wezen  <-->  categorie
CREATE TABLE wezen_categorie (
  wezen_id      INT NOT NULL,
  categorie_id  INT NOT NULL,
  PRIMARY KEY (wezen_id, categorie_id),
  KEY idx_wc_categorie (categorie_id),
  CONSTRAINT fk_wc_wezen     FOREIGN KEY (wezen_id)     REFERENCES wezen(id)     ON DELETE CASCADE,
  CONSTRAINT fk_wc_categorie FOREIGN KEY (categorie_id) REFERENCES categorie(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================================
--  3. VOORBEELDDATA
--  Genoeg gegevens om alle functionaliteit te tonen, verspreid over
--  verschillende mythologieen.
-- ============================================================================

-- --- landen -----------------------------------------------------------------
INSERT INTO land (id, naam) VALUES
  (1, 'Griekenland'),
  (2, 'Italie'),
  (3, 'Egypte'),
  (4, 'Ierland'),
  (5, 'Noorwegen'),
  (6, 'IJsland'),
  (7, 'Congo'),
  (8, 'Saoedi-Arabie'),
  (9, 'Australie');

-- --- categorieen ------------------------------------------------------------
INSERT INTO categorie (id, naam, type) VALUES
  (1,  'Geest',           'soort'),
  (2,  'Demon',           'soort'),
  (3,  'Religieus wezen', 'genre'),
  (4,  'Woestijngeest',   'soort'),
  (5,  'Ierse Fae',       'soort'),
  (6,  'Held',            'soort'),
  (7,  'Sprookjesfiguur', 'genre'),
  (8,  'God',             'soort'),
  (9,  'Congolees',       'afkomst'),
  (10, 'Australisch',     'afkomst'),
  (11, 'Grieks',          'afkomst'),
  (12, 'Monster',         'soort'),
  (13, 'Egyptisch',       'afkomst'),
  (14, 'Noors',           'afkomst');

-- --- wezens -----------------------------------------------------------------
-- (beschrijving bevat bewust eenvoudige HTML om de rijke-tekst weergave te tonen)
INSERT INTO wezen (id, naam, beschrijving, afbeelding) VALUES
  (1, 'Medusa',
      '<p>Een van de drie <strong>Gorgonen</strong>. Haar blik veranderde iedereen die haar aankeek in <em>steen</em>.</p>',
      NULL),
  (2, 'Minotaurus',
      '<p>Een wezen met het lichaam van een man en de kop van een <strong>stier</strong>, opgesloten in het Labyrint van Kreta.</p>',
      NULL),
  (3, 'Anubis',
      '<p>De Egyptische god met de kop van een <strong>jakhals</strong>, beschermer van de doden en het balsemen.</p>',
      NULL),
  (4, 'Banshee',
      '<p>Een vrouwelijke geest uit de Ierse folklore wiens <em>geweeklaag</em> een naderend sterfgeval aankondigt.</p>',
      NULL),
  (5, 'Fenrir',
      '<p>De gigantische <strong>wolf</strong> uit de Noorse mythologie, voorbestemd om Odin te verslinden tijdens Ragnarok.</p>',
      NULL),
  (6, 'Djinn',
      '<p>Een <strong>woestijngeest</strong> uit de Arabische mythologie, gemaakt van rookloos vuur en in staat van gedaante te wisselen.</p>',
      NULL),
  (7, 'Mami Wata',
      '<p>Een water-geest, vereerd in grote delen van <strong>Centraal- en West-Afrika</strong>, vaak afgebeeld als een meermin.</p>',
      NULL),
  (8, 'Bunyip',
      '<p>Een monster uit de Aboriginal-folklore dat zou huizen in <em>moerassen en waterpoelen</em>.</p>',
      NULL),
  (9, 'Theseus',
      '<p>De Atheense <strong>held</strong> die de Minotaurus versloeg en uit het Labyrint ontsnapte met de draad van Ariadne.</p>',
      NULL);

-- --- verhalen ---------------------------------------------------------------
INSERT INTO verhaal (id, titel, synopsis, leestekst, eeuw) VALUES
  (1, 'Theseus en de Minotaurus',
      'De Atheense held Theseus daalt af in het Labyrint om het monster van Kreta te doden.',
      '<h2>Het Labyrint van Kreta</h2><p>Elke negen jaar moest Athene zeven jongens en zeven meisjes sturen naar Kreta, als voedsel voor de <strong>Minotaurus</strong>. Theseus bood zich vrijwillig aan.</p><p>Met de hulp van prinses Ariadne en haar <em>draad</em> vond hij de weg terug uit het Labyrint.</p>',
      -13),
  (2, 'De blik van Medusa',
      'Hoe de held Perseus de Gorgoon Medusa versloeg zonder haar recht aan te kijken.',
      '<h2>Een dodelijke blik</h2><p>Wie <strong>Medusa</strong> recht in de ogen keek, veranderde op slag in steen. Perseus gebruikte zijn <em>schild</em> als spiegel om haar te kunnen naderen.</p>',
      -8),
  (3, 'Anubis weegt het hart',
      'In de Egyptische dodencultus weegt Anubis het hart van de overledene tegen de veer van Maat.',
      '<h2>De weging van het hart</h2><p><strong>Anubis</strong> leidde de ziel naar de hal van het oordeel. Daar werd het hart gewogen tegen de <em>veer van Maat</em>, de godin van waarheid.</p>',
      -14),
  (4, 'De klaagzang van de Banshee',
      'Een Iers gezin hoort s nachts het geween van de Banshee en weet dat het einde nadert.',
      '<h2>Een stem in de nacht</h2><p>De <strong>Banshee</strong> verscheen aan oude Ierse families. Haar <em>geweeklaag</em> over de heuvels kondigde altijd een sterfgeval aan.</p>',
      9),
  (5, 'Fenrir en de goden',
      'De goden ketenen de monsterlijke wolf Fenrir, maar het noodlot van Ragnarok is onafwendbaar.',
      '<h2>De geketende wolf</h2><p>De goden vreesden <strong>Fenrir</strong> en bonden hem met het magische lint Gleipnir. Toch zou hij bij <em>Ragnarok</em> losbreken.</p>',
      11),
  (6, 'De djinn van de woestijn',
      'Een handelaar ontmoet een djinn die hem rijkdom belooft in ruil voor een gevaarlijke afspraak.',
      '<h2>Een afspraak van vuur</h2><p>Diep in de woestijn verscheen een <strong>djinn</strong> uit een zandstorm. Hij bood goud aan, maar elke wens had een verborgen <em>prijs</em>.</p>',
      10);

-- --- koppelingen: verhaal <-> land -----------------------------------------
INSERT INTO verhaal_land (verhaal_id, land_id) VALUES
  (1, 1), (1, 2),   -- Theseus: Griekenland + Italie
  (2, 1),           -- Medusa: Griekenland
  (3, 3),           -- Anubis: Egypte
  (4, 4),           -- Banshee: Ierland
  (5, 5), (5, 6),   -- Fenrir: Noorwegen + IJsland
  (6, 8);           -- Djinn: Saoedi-Arabie

-- --- koppelingen: verhaal <-> wezen ----------------------------------------
INSERT INTO verhaal_wezen (verhaal_id, wezen_id) VALUES
  (1, 2), (1, 9),   -- Theseus-verhaal: Minotaurus + Theseus
  (2, 1),           -- Medusa-verhaal: Medusa
  (3, 3),           -- Anubis-verhaal: Anubis
  (4, 4),           -- Banshee-verhaal: Banshee
  (5, 5),           -- Fenrir-verhaal: Fenrir
  (6, 6);           -- Djinn-verhaal: Djinn

-- --- koppelingen: wezen <-> categorie --------------------------------------
INSERT INTO wezen_categorie (wezen_id, categorie_id) VALUES
  (1, 12), (1, 11),          -- Medusa: Monster + Grieks
  (2, 12), (2, 11),          -- Minotaurus: Monster + Grieks
  (3, 8),  (3, 13),          -- Anubis: God + Egyptisch
  (4, 1),  (4, 5),           -- Banshee: Geest + Ierse Fae
  (5, 12), (5, 14),          -- Fenrir: Monster + Noors
  (6, 4),  (6, 2),           -- Djinn: Woestijngeest + Demon
  (7, 1),  (7, 9),           -- Mami Wata: Geest + Congolees
  (8, 12), (8, 10),          -- Bunyip: Monster + Australisch
  (9, 6),  (9, 11);          -- Theseus: Held + Grieks
