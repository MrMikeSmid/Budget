# Samen — gedeelde todo-lijstjes

Een mobiele PHP/SQLite-app waarmee mensen lijstjes maken, delen en samen afvinken. De applicatie gebruikt een klein eigen MVC-framework zonder externe PHP-dependencies en is voorbereid voor plaatsing op `mikesmid.nl/development`.

## Functies

- Inloggen en automatisch registreren met alleen een e-mailadres.
- Subtiele beveiligingsmelding zolang het account nog geen wachtwoord heeft.
- Optioneel wachtwoord instellen en wijzigen via Instellingen; daarna is het wachtwoord verplicht bij een nieuwe login.
- Gedeelde todo-lijsten op basis van het e-mailadres van de andere gebruiker.
- Taken toevoegen met een optionele afbeelding, prioriteit en vervaldatum, en door iedere deelnemer afvinken, inclusief registratie wie dat deed.
- Automatische pushmeldingen voor andere deelnemers wanneer een taak wordt toegevoegd, afgerond of opnieuw geopend.
- Persoonlijke notificatievoorkeuren: alles ontvangen, alleen meldingen voor vervallen taken of alle pushnotificaties uitschakelen.
- Responsive, mobile-first community-interface met bottom navigation.
- Installeerbare PWA voor iOS en Android, met app-iconen, standalone-weergave en een offline terugvalscherm.
- Beheerbaar auditlog met gebruiker, gebeurtenis, context, datum, tijd, IP-adres en apparaatinformatie.
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

## E-mailuitnodigingen via SMTP

Bij het delen van een lijst verstuurt Samen een opgemaakte HTML-mail via een configureerbare SMTP-server. Een beheerder kan op `/admin/email` de SMTP-host, poort, verbindingsbeveiliging, inloggegevens, time-out, afzender en uitnodigingstekst beheren en direct een testmail sturen. Het SMTP-wachtwoord blijft server-side en wordt na opslaan niet teruggetoond.

Gebruik bij voorkeur de instellingen van een transactionele mailprovider of de mailserver van het eigen domein, met STARTTLS op poort 587 of TLS op poort 465. Dezelfde waarden kunnen voor deployment via omgevingsvariabelen worden ingesteld:

```bash
export SAMEN_APP_URL=https://mikesmid.nl/development
export SAMEN_MAIL_FROM=uitnodigingen@mikesmid.nl
export SAMEN_SMTP_HOST=smtp.voorbeeld.nl
export SAMEN_SMTP_PORT=587
export SAMEN_SMTP_ENCRYPTION=starttls
export SAMEN_SMTP_USERNAME=uitnodigingen@mikesmid.nl
export SAMEN_SMTP_PASSWORD='app-wachtwoord'
export SAMEN_SMTP_TIMEOUT=15
```

Een geldige SMTP-login alleen garandeert nog niet dat berichten in de inbox belanden. Configureer bij de gekozen provider ook SPF en DKIM voor het afzenderdomein en publiceer een DMARC-record. Gebruik als zichtbare afzender een geverifieerd adres op hetzelfde domein en controleer via de testmail de ontvangen berichtheaders.

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

### Vervaldatummeldingen automatisch versturen

Een vervaldatum loopt tot het einde van de gekozen dag. Samen stuurt om 12:00 uur op die dag een melding dat er nog twaalf uur over is en om 00:00 uur daarna een melding dat de taak is vervallen. Laat hiervoor het idempotente CLI-commando iedere minuut uitvoeren, bijvoorbeeld via cron:

```cron
* * * * * cd /pad/naar/samen && /usr/bin/php bin/send-due-notifications.php >> storage/due-notifications.log 2>&1
```

Het commando bewaart per taak welke meldingen al zijn verstuurd, zodat vaker uitvoeren geen dubbele pushmeldingen veroorzaakt.

## JSON API voor Android, iOS/PWA en web

De webapp blijft de eigenaar van de SQLite-database. Mobiele clients verbinden niet direct met SQLite, maar gebruiken HTTPS-endpoints onder `/api`. Alle endpoints geven JSON terug en vereisen na login een `Authorization: Bearer <token>` header.

### Datamodel-mapping

De API publiceert stabiele servervelden en mappt die op het bestaande SQLite-schema:

| API veld | SQLite-bron |
| --- | --- |
| `users.display_name` | `users.name` |
| `lists` | `todo_lists` |
| `lists.owner_user_id` | `todo_lists.owner_id` |
| `items` | `todo_items` |
| `items.completed` | `todo_items.is_completed` |
| `items.completed_by_user_id` | `todo_items.completed_by` |
| `notification_preferences` | aparte API-compatibiliteitstabel; bestaande profielinstelling blijft ook in `users.notification_preference` staan |

De migratie voegt API-compatibiliteitskolommen toe waar nodig: `todo_lists.deleted_at`, `list_members.role`, en `todo_items.note`, `todo_items.updated_at`, `todo_items.deleted_at`. API-verwijderingen zijn soft deletes zodat synchronisatie wijzigingen kan ophalen.

### Authenticatie

```http
POST /api/auth/login
Content-Type: application/json

{ "email": "owner@example.nl", "password": "veilig-wachtwoord" }
```

Response:

```json
{ "token": "...", "user": { "id": 1, "email": "owner@example.nl", "display_name": "Owner" } }
```

Accounts zonder ingesteld wachtwoord kunnen met een lege of ontbrekende `password` inloggen, gelijk aan de bestaande web-login zonder wachtwoord. Voor productie is het advies om voor mobiele toegang wachtwoorden verplicht te maken. Tokens worden server-side gehasht opgeslagen in `api_tokens` en verlopen standaard na 90 dagen.

### Endpoints

Alle onderstaande voorbeelden gebruiken:

```http
Authorization: Bearer <token>
Content-Type: application/json
```

- `GET /api/lists` — lijstjes waarvan de gebruiker eigenaar of geaccepteerd lid is.
- `POST /api/lists` — body `{ "title": "Boodschappen" }`.
- `PATCH /api/lists/{listId}` — body `{ "title": "Nieuwe titel" }`, eigenaar vereist.
- `DELETE /api/lists/{listId}` — soft delete, eigenaar vereist.
- `GET /api/lists/{listId}/items` — taken in een toegankelijk lijstje.
- `POST /api/lists/{listId}/items` — body `{ "title": "Melk", "note": "Halfvol", "priority": "medium", "due_date": "2026-06-20" }`.
- `PATCH /api/items/{itemId}` — body kan `title`, `note`, `priority`, `due_date` en/of `completed` bevatten. Bij `completed: true` zet de server `completed_by_user_id` op de huidige gebruiker.
- `DELETE /api/items/{itemId}` — soft delete voor deelnemers van het lijstje.
- `POST /api/lists/{listId}/members` — body `{ "email": "lid@example.nl", "role": "member" }`, eigenaar vereist.
- `GET /api/sync?since=2026-06-19%2000:00:00` — gewijzigde/deleted lijstjes en taken sinds timestamp.

### Android base URL en lokale test

Zet de Android base URL op de publieke HTTPS-origin van de webapp, bijvoorbeeld `https://samen.example.nl/api`. Bewaar alleen het bearer token in veilige app storage; databasepad, SMTP-instellingen en andere secrets blijven uitsluitend op de server.

Lokaal testen kan met de PHP development server:

```bash
php -S 127.0.0.1:8000 -t public public/router.php
curl -i -X POST http://127.0.0.1:8000/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"owner@example.nl","password":""}'
```
