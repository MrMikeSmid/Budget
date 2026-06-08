# Samen — gedeelde todo-lijstjes

Een mobiele PHP/SQLite-app waarmee mensen lijstjes maken, delen en samen afvinken. De applicatie gebruikt een klein eigen MVC-framework zonder externe PHP-dependencies en is voorbereid voor plaatsing op `mikesmid.nl/development`.

## Functies

- Inloggen en automatisch registreren met alleen een e-mailadres.
- Subtiele beveiligingsmelding zolang het account nog geen wachtwoord heeft.
- Optioneel wachtwoord instellen en wijzigen via Instellingen; daarna is het wachtwoord verplicht bij een nieuwe login.
- Gedeelde todo-lijsten op basis van het e-mailadres van de andere gebruiker.
- Taken toevoegen en door iedere deelnemer afvinken, inclusief registratie wie dat deed.
- Automatische pushmeldingen voor andere deelnemers wanneer een taak wordt toegevoegd, afgerond of opnieuw geopend.
- Responsive, mobile-first community-interface met bottom navigation.
- Installeerbare PWA voor iOS en Android, met app-iconen, standalone-weergave en een offline terugvalscherm.
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

## Installeren als app

Samen is een Progressive Web App (PWA) en kan via HTTPS op een telefoon worden geïnstalleerd:

- **Android (Chrome):** open Instellingen in Samen en tik op **Installeren**, of gebruik **App installeren** in het browsermenu.
- **iPhone/iPad (Safari):** open Instellingen in Samen, tik op **Bekijk stappen**, kies in Safari **Delen** en daarna **Zet op beginscherm**.

Voor installatie buiten `localhost` is een geldige HTTPS-verbinding vereist. De manifest- en service-worker-URL's nemen automatisch het deploymentpad over.

## Deployment in `/development`

Upload de volledige repository naar de map die publiek bereikbaar is als `/development`. Er hoeft geen vaste base URL ingesteld te worden: de app leidt `/development` af uit `SCRIPT_NAME`, waardoor routes en assets automatisch het goede prefix gebruiken. Zorg dat Apache `mod_rewrite` actief is en `AllowOverride All` voor de doelmap toestaat.

De SQLite-database wordt bij het eerste verzoek automatisch aangemaakt in `storage/app.sqlite`. Maak `storage/` schrijfbaar voor de PHP/Apache-gebruiker, bijvoorbeeld met `chmod 775 storage`. De FTP-deployment slaat de volledige map `storage/` bewust over, zodat de database en profielfoto's bij een nieuwe release op de server behouden blijven.

## E-mailuitnodigingen

Bij het delen van een lijst verstuurt Samen een opgemaakte HTML-mail via de PHP `mail()`-functie. Een beheerder kan op `/admin` de afzendernaam, het afzenderadres en de inhoud met een rich-texteditor aanpassen. De branded header, de knop naar het lijstje en de footer met privacy- en voorwaardenlinks worden automatisch toegevoegd. Stel in productie ook de publieke basis-URL en een geldig standaardafzenderadres in:

```bash
export SAMEN_APP_URL=https://mikesmid.nl/development
export SAMEN_MAIL_FROM=noreply@mikesmid.nl
```

De webserver moet daarnaast zijn geconfigureerd om uitgaande e-mail van PHP te bezorgen. Als verzending mislukt, behoudt de genodigde wel toegang tot de lijst en ziet de uitnodiger een melding.

## Tests

```bash
php tests/run.php
```

## Pushnotificaties testen met OneSignal

Samen gebruikt **OneSignal** voor pushmeldingen. Het gratis abonnement ondersteunt webpush en maximaal 10.000 abonnees per verzending. Na de configuratie ziet iedere ingelogde gebruiker één verzoek om notificaties toe te staan. Na toestemming registreert en synchroniseert Samen het apparaat automatisch. Andere deelnemers aan een gedeeld lijstje ontvangen vervolgens een melding wanneer iemand een taak toevoegt, afrondt of opnieuw opent. Een beheerder kan via `/admin/notifications` de configuratie beheren en een handmatige testmelding sturen.

### Eenmalige configuratie

1. Maak via [OneSignal](https://onesignal.com/) een gratis account en app aan.
2. Open **Settings → Push & In-App → Web** en activeer Web Push.
3. Kies **Custom Code** en vul als Site URL de HTTPS-oorsprong in, bijvoorbeeld `https://mikesmid.nl`.
4. Stel het service-workerpad in op het deploymentpad, bijvoorbeeld `/development/sw.js`, met scope `/development/`.
5. Open **Settings → Keys & IDs** en kopieer de **App ID** en **REST API Key**.
6. Log in als beheerder, open `/admin/notifications`, vul beide waarden in en sla ze op.
7. Activeer meldingen op het testapparaat en stuur daarna een handmatige test.

De App ID wordt naar de browser gestuurd. De REST API Key blijft uitsluitend server-side in de SQLite-database. Als alternatief kunnen de waarden via omgevingsvariabelen worden ingesteld:

```bash
export SAMEN_ONESIGNAL_APP_ID=...
export SAMEN_ONESIGNAL_REST_API_KEY=...
```

Webpush vereist in productie HTTPS. Android en ondersteunde desktopbrowsers kunnen rechtstreeks toestemming geven. Op iPhone en iPad is minimaal iOS/iPadOS 16.4 vereist: voeg Samen eerst via Safari aan het beginscherm toe, open vervolgens de geïnstalleerde PWA en activeer daar de meldingen.
