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

## Pushnotificaties testen met Firebase

Samen gebruikt voor de nieuwe, eenvoudige testopzet **Firebase Cloud Messaging (FCM)**. Automatische meldingen bij lijstwijzigingen zijn voorlopig uitgeschakeld. Een beheerder kan via `/admin/notifications` één apparaat registreren en daar handmatig een testmelding naartoe sturen.

### Eenmalige configuratie

1. Maak een Firebase-project en voeg daarin een **Web app** toe.
2. Open **Project settings → General** en kopieer Project ID, Web API key, Messaging sender ID en App ID.
3. Open **Project settings → Cloud Messaging → Web Push certificates**, maak een sleutel aan en kopieer de publieke VAPID-key.
4. Maak via **Project settings → Service accounts** een private key en download het JSON-bestand.
5. Log in als beheerder, open `/admin/notifications`, vul de waarden in en plak de volledige serviceaccount-JSON.
6. Open dezelfde pagina via HTTPS op het testapparaat, activeer meldingen en stuur daarna een handmatige test.

De publieke webconfiguratie en VAPID-key worden naar de browser gestuurd. De serviceaccount-JSON blijft uitsluitend server-side in de SQLite-database. Als alternatief kunnen de waarden via deze omgevingsvariabelen worden gezet:

```bash
export SAMEN_FIREBASE_PROJECT_ID=voorbeeld-project
export SAMEN_FIREBASE_API_KEY=...
export SAMEN_FIREBASE_MESSAGING_SENDER_ID=...
export SAMEN_FIREBASE_APP_ID=...
export SAMEN_FIREBASE_VAPID_PUBLIC_KEY=...
export SAMEN_FIREBASE_SERVICE_ACCOUNT_JSON='{"type":"service_account",...}'
```

Web-push vereist in productie HTTPS. Op iPhone en iPad moet Samen eerst via Safari aan het beginscherm worden toegevoegd voordat de geïnstalleerde PWA om notificatietoestemming kan vragen.
