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

De SQLite-database wordt bij het eerste verzoek automatisch aangemaakt in `storage/app.sqlite`. Maak `storage/` schrijfbaar voor de PHP/Apache-gebruiker, bijvoorbeeld met `chmod 775 storage`.

## E-mailuitnodigingen

Bij het delen van een lijst verstuurt Samen een tekstmail via de PHP `mail()`-functie. Stel in productie de publieke basis-URL en een geldig afzenderadres in, zodat de link en afzender in de uitnodiging kloppen:

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
4. Neem bij **Settings → Keys & IDs** de App ID en App API Key over naar de serveromgeving:

```bash
export SAMEN_ONESIGNAL_APP_ID=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
export SAMEN_ONESIGNAL_API_KEY=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

De API key is alleen server-side beschikbaar en wordt nooit naar de browser gestuurd. Na het instellen verschijnt de notificatie-optie automatisch in Samen.

### Gedrag per platform

- **Android:** meldingen werken in ondersteunde browsers; installatie van de PWA geeft de prettigste app-ervaring.
- **iPhone/iPad:** vereist iOS/iPadOS 16.4 of hoger. De gebruiker moet Samen eerst via Safari met **Zet op beginscherm** installeren, de geïnstalleerde app openen en daar meldingen aanzetten.
- **Alle platformen:** productie vereist een geldige HTTPS-verbinding. Push werkt niet in een privé-/incognitovenster.
