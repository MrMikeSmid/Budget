# Samen — gedeelde todo-lijstjes

Een mobiele PHP/SQLite-app waarmee mensen lijstjes maken, delen en samen afvinken. De applicatie gebruikt een klein eigen MVC-framework zonder externe PHP-dependencies en is voorbereid voor plaatsing op `mikesmid.nl/development`.

## Functies

- Inloggen en automatisch registreren met alleen een e-mailadres.
- Subtiele beveiligingsmelding zolang het account nog geen wachtwoord heeft.
- Optioneel wachtwoord instellen en wijzigen via Instellingen; daarna is het wachtwoord verplicht bij een nieuwe login.
- Gedeelde todo-lijsten op basis van het e-mailadres van de andere gebruiker.
- Taken toevoegen en door iedere deelnemer afvinken, inclusief registratie wie dat deed.
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

## Pushnotificaties op iOS en Android

Samen gebruikt **OneSignal Web Push**. Daardoor blijft Samen één PWA en zijn er geen aparte native iOS- en Android-projecten, APNs-code of Firebase-code in deze repository nodig. Meldingen worden verstuurd wanneer een andere deelnemer:

- een taak toevoegt;
- een taak afvinkt of opnieuw opent;
- een lijst met iemand deelt.

De gebruiker kan pushnotificaties zelf aan- of uitzetten onder **Instellingen**. Bij uitloggen wordt het apparaat uitgeschreven, zodat meldingen niet bij een volgende gebruiker van hetzelfde apparaat terechtkomen.

### Eenmalige configuratie

1. Maak in [OneSignal](https://onesignal.com) een app aan en voeg het platform **Web** toe.
2. Kies de configuratie voor een normale website/custom code en vul de publieke HTTPS-URL van Samen in, bijvoorbeeld `https://mikesmid.nl/development`.
3. Gebruik bij een deployment onder `/development` deze service-workerinstellingen:
   - pad: `/development/push/onesignal/`
   - bestandsnaam: `OneSignalSDKWorker.js`
   - scope: `/development/push/onesignal/`
4. Log in met het adminaccount, open `/admin` en sla daar de OneSignal App ID en REST API Key op. Deze waarden worden in de SQLite-database bewaard; de API key wordt nooit naar de browser gestuurd.

Het oudste bestaande account wordt bij de database-upgrade eenmalig admin. Bij een nieuwe installatie wordt het eerste account admin. Stel voor voorspelbaar beheer bij voorkeur `SAMEN_ADMIN_EMAIL` in; een account met dat e-mailadres krijgt automatisch adminrechten. Een admin moet een wachtwoord hebben voordat `/admin` toegankelijk is.

De eerdere omgevingsvariabelen `SAMEN_ONESIGNAL_APP_ID` en `SAMEN_ONESIGNAL_API_KEY` blijven als fallback werken zolang er nog geen waarden via `/admin` zijn opgeslagen.

### Gedrag per platform

- **Android:** meldingen werken in ondersteunde browsers; installatie van de PWA geeft de prettigste app-ervaring.
- **iPhone/iPad:** vereist iOS/iPadOS 16.4 of hoger. De gebruiker moet Samen eerst via Safari met **Zet op beginscherm** installeren, de geïnstalleerde app openen en daar meldingen aanzetten.
- **Alle platformen:** productie vereist een geldige HTTPS-verbinding. Push werkt niet in een privé-/incognitovenster.
