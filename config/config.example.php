<?php

/**
 * Kopieer dit bestand naar config.php en pas de waarden aan.
 * config.php wordt NIET meegenomen door git of het deploy-script,
 * zodat instellingen per omgeving (lokaal / server) apart blijven.
 */

return [
    // Pad naar het SQLite-databasebestand. Relatief t.o.v. de projectroot.
    'db_path' => __DIR__ . '/../storage/database.sqlite',

    // Naam van de sessie-cookie. Random string maakt sessies uniek per installatie.
    'session_name' => 'budgetapp_session',

    // Zet op true tijdens ontwikkelen voor uitgebreide foutmeldingen.
    'debug' => false,
];
