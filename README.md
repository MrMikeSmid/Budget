# MCP Email Connector (IMAP/SMTP) — PHP-editie

Self-hosted MCP-server in **PHP** die Claude toegang geeft tot een generieke
IMAP/SMTP-mailbox (eigen domein via DirectAdmin/cPanel/Plesk-hosting, geen
Gmail/Outlook API's). Gebouwd in PHP omdat veel gedeelde hostingpakketten
(zoals DirectAdmin-pakketten zonder "Node.js Selector") geen persistent
Node.js-proces toestaan, maar PHP via de gewone webserver altijd werkt —
elke request draait als een los PHP-script, precies zoals elke andere
pagina op je hosting.

## Waarom PHP in plaats van Node.js

De MCP "Streamable HTTP"-transport mag **stateless** werken: elke aanroep is
een simpele HTTP POST met een JSON-RPC-bericht, en de server antwoordt direct
met JSON — er hoeft geen sessie of achtergrondproces in leven te blijven.
Dat past precies bij hoe gewone PHP-hosting werkt (PHP-FPM/mod_php voert elk
verzoek apart uit) en heeft dus geen "Node.js App"/Passenger-ondersteuning
nodig.

## Functionaliteit

MCP-tools die deze server aanbiedt (ongewijzigd t.o.v. de oorspronkelijke
opzet):

- `list_emails` — recente e-mails uit een map (standaard `INBOX`), met
  paginering (`limit`) en basismetadata (afzender, onderwerp, datum,
  gelezen/ongelezen).
- `read_email` — volledige inhoud (tekst/HTML/bijlagenamen) van één e-mail
  op basis van UID.
- `search_emails` — zoeken op afzender, onderwerp, inhoud en/of datumrange.
- `send_email` — nieuwe e-mail versturen (`to`, `subject`, `body`/`html`,
  optioneel `cc`/`bcc`).

E-mailinhoud wordt bij elk verzoek live via IMAP opgehaald; er wordt niets
persistent op de server opgeslagen, en credentials worden nooit gelogd.

## Vereisten op de hosting

- **PHP 8.1 of hoger** (getest met 8.3) met de **`imap`-extensie** ingeschakeld
  (te vinden in DirectAdmin/cPanel bij "PHP Selector" / "Select PHP Version"
  → lijst met extensies, vink "imap" aan indien nodig).
  > Let op: de PHP `imap`-extensie is als **deprecated** gemarkeerd vanaf
  > PHP 8.4 (nog volledig functioneel, alleen met een waarschuwing). Blijf
  > voorlopig op PHP 8.1–8.3 voor deze functionaliteit, of houd bij een
  > toekomstige upgrade in de gaten of `imap` nog beschikbaar is.
- Uitgaande poorten 993 (IMAPS) en 465/587 (SMTP) open richting je
  mailprovider — controleer dit bij je hoster.
- HTTPS/TLS voor de publieke URL van deze connector (de standaard-SSL van
  je hostingpaneel volstaat meestal).
- **Geen persistent proces nodig** — dit draait via de normale webserver
  (Apache/LiteSpeed) zoals elke andere PHP-pagina.

## Installatie (lokaal / build)

```bash
composer install --no-dev --optimize-autoloader
```

Dit installeert alleen **PHPMailer** (de enige externe dependency, voor
SMTP). Als je hosting geen Composer/SSH heeft: draai `composer install`
lokaal op je eigen computer en upload de `vendor/`-map gewoon mee via
FTP/bestandsbeheer — er hoeft niets op de server zelf gebouwd te worden.

## Configuratie

**Optie A — via het setup-formulier (aanbevolen):** open
`https://jouw-domein.nl/setup.php` in de browser en vul het formulier in.
Dit schrijft `config/config.php` voor je weg. Bij een bestaande configuratie
vraagt de pagina eerst om het huidige bearer token voordat je iets kan
wijzigen. **Verwijder of hernoem `setup.php` na gebruik** — hij blijft
anders (weliswaar achter het bearer token) bereikbaar.

**Optie B — handmatig:** kopieer `config/config.example.php` naar
`config/config.php` en vul de waarden rechtstreeks in:

```bash
cp config/config.example.php config/config.php
```

| Sleutel | Omschrijving |
|---|---|
| `MCP_BEARER_TOKEN` | **Verplicht.** Bearer token voor authenticatie op de HTTP-endpoint. Genereer bv. met `php -r "echo bin2hex(random_bytes(32));"`. |
| `IMAP_HOST`, `IMAP_PORT`, `IMAP_SECURE`, `IMAP_USER`, `IMAP_PASSWORD` | IMAP-verbinding. Poort 993 met `IMAP_SECURE=true` is de standaard (impliciete TLS). |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_SECURE`, `SMTP_USER`, `SMTP_PASSWORD` | SMTP-verbinding. Poort 587 (STARTTLS, `SMTP_SECURE=false`) of 465 (impliciete TLS, `SMTP_SECURE=true`). Leeg laten van `SMTP_USER`/`SMTP_PASSWORD` hergebruikt de IMAP-credentials. |
| `SMTP_FROM_ADDRESS`, `SMTP_FROM_NAME` | Afzenderadres/-naam voor `send_email`. |

**`config/config.php` nooit committen** — staat al in `.gitignore`, en wordt
via de deploy-workflow ook nooit overschreven/verwijderd (zie hieronder).
Op hosting waar environment variables wél goed doorgezet worden naar PHP
(`getenv()`), mogen deze waarden ook via het hostingpaneel als environment
variable gezet worden — die krijgen dan voorrang boven `config/config.php`.

### Meerdere accounts (optioneel)

Vul in `config/config.php` de sleutel `'accounts' => [...]` met een array van
accounts (voorbeeld staat als commentaar in `config.example.php`), of gebruik
environment variable `MAIL_ACCOUNTS_JSON` met dezelfde structuur als JSON-string.
Elke tool accepteert dan een optionele `account`-parameter met het gekozen
`id`; zonder opgave wordt het eerste account gebruikt.

## Draaien op gedeelde PHP-hosting (DirectAdmin/cPanel/Plesk)

1. **Idealiter**: zet het document root van je (sub)domein op de map
   `public/` van dit project. In DirectAdmin kan dit vaak via
   "Domain Setup" → document root aanpassen, of door een subdomein te maken
   dat naar `public/` wijst. Alles buiten `public/` (broncode, config,
   dependencies) is dan sowieso niet via het web bereikbaar.
2. **Kan dat niet** (sommige shared-pakketten staan geen aangepast document
   root toe)? Upload dan gewoon de hele projectmap in `public_html/` (of een
   submap). De meegeleverde `.htaccess`-bestanden zorgen er dan voor dat
   alles behalve de map `public/` met een 403 wordt geblokkeerd. Zorg dat
   Apache/LiteSpeed `.htaccess`-overrides toestaat (`AllowOverride All` — dit
   staat op de meeste shared hosting al standaard aan).
3. Upload/clone de repository, inclusief de lokaal gegenereerde `vendor/`-map
   (zie Installatie hierboven) en je eigen `config/config.php`.
4. Verifieer dat de publieke URL bereikbaar is over HTTPS, bv.:
   ```bash
   curl https://jouw-domein.nl/health.php
   ```
   (of `https://jouw-domein.nl/public/health.php` als je geen aangepast
   document root kon instellen).

### Automatisch deployen via GitHub Actions (optioneel)

Deze repo bevat al `.github/workflows/deploy.yml`, dat bij een push naar
`main` de repository via FTP naar je hosting synchroniseert. Dit is
uitgebreid met een Composer-stap zodat `vendor/` (PHPMailer) automatisch
wordt meegebouwd en geüpload. `config/config.php` staat expliciet op de
uitsluitlijst van de FTP-sync, zodat je handmatig geüploade configuratie
nooit per ongeluk wordt overschreven of verwijderd. Zorg zelf dat de
FTP-secrets (`FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`, `FTP_SERVER_DIR`)
correct staan ingesteld in de repository-instellingen.

## Verbinden vanuit Claude.ai

1. Ga naar **Instellingen → Connectors → Aangepaste connector toevoegen**.
2. Vul als URL de publieke HTTPS-endpoint in, bv.
   `https://jouw-domein.nl/mcp.php` (of `.../public/mcp.php`, afhankelijk
   van je document-root-configuratie).
3. Kies authenticatietype **Bearer token** en vul de waarde van
   `MCP_BEARER_TOKEN` in.
4. Sla op — Claude kan nu de tools `list_emails`, `read_email`,
   `search_emails` en `send_email` gebruiken.

## Projectstructuur

```
composer.json
config/
  config.example.php   -> template, kopieer naar config.php (niet in git)
public/
  mcp.php              -> MCP JSON-RPC endpoint (Streamable HTTP, stateless)
  health.php           -> simpele health-check
  setup.php            -> webformulier om config/config.php in te vullen
  .htaccess            -> heropent toegang binnen public/
src/
  Config.php           -> laden van account-/tokenconfiguratie
  Auth.php             -> bearer token check
  McpServer.php         -> JSON-RPC dispatch (initialize/tools/list/tools/call)
  Tools/
    ListEmailsTool.php
    ReadEmailTool.php
    SearchEmailsTool.php
    SendEmailTool.php
    Support.php        -> gedeelde helpers (resultaten, overview-formatting)
  Mail/
    ImapClient.php     -> wrapper rond ext-imap
    SmtpClient.php     -> wrapper rond PHPMailer
.htaccess              -> blokkeert webtoegang tot alles buiten public/
```

## Niet-functionele eigenschappen

- Credentials worden nooit gelogd.
- E-mailinhoud wordt niet persistent opgeslagen; elke `read_email`/
  `list_emails`/`search_emails`-aanroep haalt live op via IMAP (een verse
  IMAP-verbinding per request, altijd weer afgesloten).
- Verbindingsfouten met de mailserver resulteren in een leesbare MCP-tool-
  error (`isError: true` + boodschap), niet in een gecrashed proces —
  ook onverwachte PHP-warnings/fouten tijdens IMAP-aanroepen worden
  opgevangen en omgezet in nette tool-errors.
- Minimale dependencies: alleen PHPMailer via Composer; IMAP loopt via de
  ingebouwde PHP-extensie, geen extra libraries nodig.
- Stateless ontwerp: geen sessie-state, geen achtergrondproces — past bij
  gedeelde hosting zonder Node.js/Docker/VPS-ondersteuning.

## Nog te verifiëren op de doelhosting

- Of de `imap`-PHP-extensie daadwerkelijk aan staat (bevestigd voor dit
  project: ja, PHP 8.3).
- Of uitgaande poorten 993 en 465/587 daadwerkelijk open staan.
- Of het document root van het domein op `public/` gezet kan worden, of dat
  de `.htaccess`-fallback nodig is.
