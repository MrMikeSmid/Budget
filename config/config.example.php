<?php

/**
 * Kopieer dit bestand naar config.php en pas de waarden aan.
 * config.php wordt NIET meegenomen door git of het deploy-script,
 * zodat instellingen per omgeving (lokaal / server) apart blijven.
 */

return [
    // Naam van de sessie-cookie. Random string maakt sessies uniek per installatie.
    'session_name' => 'budgetapp_session',

    // Zet op true tijdens ontwikkelen voor uitgebreide foutmeldingen.
    'debug' => false,

    // Absolute basis-URL van deze installatie (zonder trailing slash), gebruikt
    // voor links in e-mails (verificatie/uitnodigingen). Zonder deze instelling
    // wordt de URL afgeleid uit de binnenkomende request — prima lokaal, maar
    // vertrouw dat niet op een publieke server (Host-header kan vervalst
    // worden). Zet 'm dus expliciet op de live server, bijv.:
    // 'app_url' => 'https://mikesmid.nl/development',
    'app_url' => null,

    // SMTP-gegevens voor het versturen van verificatie- en uitnodigingsmails.
    // Laat 'host' leeg om verzenden helemaal uit te schakelen — de app toont
    // dan altijd de link zelf (om te kopiëren/delen) in plaats van te mailen,
    // dus niets breekt zolang dit nog niet is ingevuld.
    'mail' => [
        'host' => null,
        'port' => 587,
        'encryption' => 'tls', // 'tls' (STARTTLS), 'ssl' (impliciet) of 'none'
        'username' => null,
        'password' => null,
        'from_address' => 'noreply@example.com',
        'from_name' => 'Budgetapp',
    ],
];
