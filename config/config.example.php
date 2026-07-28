<?php

/**
 * Kopieer dit bestand naar config/config.php en vul de echte waarden in.
 * config/config.php wordt NIET meegecommit (zie .gitignore) en staat
 * bovendien buiten de publieke webroot (public/) plus achter een
 * deny-all .htaccess als extra beveiliging.
 *
 * Op hosting waar environment variables wel goed doorgezet worden naar
 * PHP (getenv()), mag je deze waarden ook via het hostingpaneel instellen
 * in plaats van dit bestand in te vullen - environment variables krijgen
 * altijd voorrang boven dit bestand.
 */
return [
    // Verplicht: bearer token waarmee Claude zich bij deze server authenticeert.
    // Genereer bv. met: openssl rand -hex 32 (of `php -r "echo bin2hex(random_bytes(32));"`)
    'MCP_BEARER_TOKEN' => '',

    // ---- Enkel account (MVP) ----
    'IMAP_HOST' => 'mail.jouwdomein.nl',
    'IMAP_PORT' => 993,
    'IMAP_SECURE' => true,
    'IMAP_USER' => 'jij@jouwdomein.nl',
    'IMAP_PASSWORD' => '',

    'SMTP_HOST' => 'mail.jouwdomein.nl',
    'SMTP_PORT' => 587,
    // SMTP_SECURE=true hoort bij poort 465 (impliciete TLS). Bij 587 (STARTTLS) op false laten.
    'SMTP_SECURE' => false,
    // Optioneel: alleen invullen als SMTP-gebruiker/wachtwoord afwijken van IMAP.
    'SMTP_USER' => '',
    'SMTP_PASSWORD' => '',
    'SMTP_FROM_ADDRESS' => 'jij@jouwdomein.nl',
    'SMTP_FROM_NAME' => 'Jouw Naam',

    // ---- Meerdere accounts (optioneel, i.p.v. de losse waarden hierboven) ----
    // 'accounts' => [
    //     [
    //         'id' => 'werk',
    //         'imapHost' => 'mail.werkdomein.nl',
    //         'imapUser' => 'werk@werkdomein.nl',
    //         'imapPass' => '...',
    //         'smtpHost' => 'mail.werkdomein.nl',
    //         'smtpPort' => 587,
    //         'fromAddress' => 'werk@werkdomein.nl',
    //     ],
    //     [
    //         'id' => 'prive',
    //         'imapHost' => 'mail.privedomein.nl',
    //         'imapUser' => 'ik@privedomein.nl',
    //         'imapPass' => '...',
    //         'smtpHost' => 'mail.privedomein.nl',
    //         'smtpPort' => 465,
    //         'smtpSecure' => true,
    //         'fromAddress' => 'ik@privedomein.nl',
    //     ],
    // ],
];
