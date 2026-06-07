# Samen — gedeelde todo-lijstjes

Een mobiele PHP/SQLite-app waarmee mensen lijstjes maken, delen en samen afvinken. De applicatie gebruikt een klein eigen MVC-framework zonder externe PHP-dependencies en is voorbereid voor plaatsing op `mikesmid.nl/developement`.

## Functies

- Inloggen en automatisch registreren met alleen een e-mailadres.
- Subtiele beveiligingsmelding zolang het account nog geen wachtwoord heeft.
- Optioneel wachtwoord instellen en wijzigen via Instellingen; daarna is het wachtwoord verplicht bij een nieuwe login.
- Gedeelde todo-lijsten op basis van het e-mailadres van de andere gebruiker.
- Taken toevoegen en door iedere deelnemer afvinken, inclusief registratie wie dat deed.
- Responsive, mobile-first community-interface met bottom navigation.
- CSRF-beveiliging, gehashte wachtwoorden, prepared statements en escaped HTML-uitvoer.

> De gevraagde e-mail-only login geeft iedereen die een onbeveiligd e-mailadres kent toegang tot dat account. De interface benoemt dit bewust subtiel maar duidelijk en stuurt aan op het instellen van een wachtwoord.

## Vereisten

- PHP 8.2 of hoger
- PDO SQLite-extensie
- Apache met `mod_rewrite` en toegestane `.htaccess` overrides
- Schrijfrechten voor de map `storage/`

## Lokaal starten

```bash
php -S 127.0.0.1:8080 -t . public/router.php
```

Of gebruik Apache en laat de repository-root als document root dienen. De root-`.htaccess` stuurt applicatieroutes door naar `public/index.php` en beschermt interne directories.

## Deployment in `/developement`

Upload de volledige repository naar de map die publiek bereikbaar is als `/developement`. Er hoeft geen vaste base URL ingesteld te worden: de app leidt `/developement` af uit `SCRIPT_NAME`, waardoor routes en assets automatisch het goede prefix gebruiken. Zorg dat Apache `mod_rewrite` actief is en `AllowOverride All` voor de doelmap toestaat.

De SQLite-database wordt bij het eerste verzoek automatisch aangemaakt in `storage/app.sqlite`. Maak `storage/` schrijfbaar voor de PHP/Apache-gebruiker, bijvoorbeeld met `chmod 775 storage`.

## Tests

```bash
php tests/run.php
```
