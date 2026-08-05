<?php

namespace App\Support;

final class Config
{
    private static ?array $values = null;

    public static function get(): array
    {
        if (self::$values === null) {
            $storageDir = __DIR__ . '/../../storage';

            $defaults = [
                'storage_dir' => $storageDir,

                // Legacy pad: waar de (oude, single-tenant) database stond vóór de
                // huishouden-opsplitsing. Wordt alleen nog gelezen door LegacyImporter
                // om bestaande data eenmalig over te zetten.
                'db_path' => $storageDir . '/database.sqlite',
                'app_db_path' => $storageDir . '/app.sqlite',
                'households_dir' => $storageDir . '/households',
                'session_name' => 'budgetapp_session',
                'debug' => false,

                // Absolute basis-URL voor links in e-mails (verificatie/uitnodigingen).
                // Leeg = wordt afgeleid uit de huidige request (prima voor lokaal
                // draaien, maar vertrouw dat niet in productie i.v.m. Host-header-
                // vervalsing) — zet 'm hier expliciet op de live server.
                'app_url' => null,

                // SMTP-instellingen voor uitnodigings- en verificatiemails. Leeg
                // ('host' => null) betekent: verzenden staat uit, de app toont dan
                // altijd de link zelf i.p.v. te mailen.
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

            $configFile = __DIR__ . '/../../config/config.php';
            $overrides = file_exists($configFile) ? require $configFile : [];

            if (isset($overrides['mail'])) {
                $overrides['mail'] = array_merge($defaults['mail'], $overrides['mail']);
            }

            self::$values = array_merge($defaults, $overrides);
        }

        return self::$values;
    }
}
