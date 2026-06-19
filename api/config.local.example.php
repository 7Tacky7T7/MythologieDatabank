<?php
// ============================================================================
//  config.local.example.php  -  Sjabloon voor de PRODUCTIE-instellingen
// ----------------------------------------------------------------------------
//  Kopieer dit bestand naar  config.local.php  en vul je cPanel-gegevens in.
//  - config.local.php staat in .gitignore en komt dus NOOIT in GitHub terecht.
//  - db.php laadt dit bestand automatisch als het bestaat en gebruikt dan deze
//    waarden in plaats van de lokale XAMPP-standaard.
//
//  De databank, gebruiker en rechten maak je aan in cPanel -> MySQL Databases.
// ============================================================================

$DB_HOST = 'localhost';                       // op cPanel bijna altijd 'localhost'
$DB_NAAM = 'cpanelgebruiker_mythologie';      // bv. abdulkadir_mythologie
$DB_USER = 'cpanelgebruiker_app';             // bv. abdulkadir_app
$DB_PASS = 'VUL_HIER_EEN_STERK_WACHTWOORD_IN';
