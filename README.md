# MCP Email Connector (IMAP/POP3/SMTP) — PHP-editie

Self-hosted MCP-server in **PHP** die GPT/ChatGPT toegang geeft tot een generieke
IMAP- of POP3-mailbox met SMTP (eigen domein via DirectAdmin/cPanel/Plesk-hosting, geen
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

MCP-tools die deze server aanbiedt:

- `list_emails` — recente e-mails uit een map (standaard `INBOX`), met
  paginering (`limit`) en basismetadata (afzender, onderwerp, datum,
  gelezen/ongelezen).
- `read_email` — volledige inhoud (tekst/HTML/bijlagenamen) van één e-mail
  op basis van UID.
- `search_emails` — zoeken op afzender, onderwerp, inhoud en/of datumrange.
- `send_email` — nieuwe e-mail versturen (`to`, `subject`, `body`/`html`,
  optioneel `cc`/`bcc`).
- `analyze_email(id)` — volledige classificatie met spam-/trustscore,
  categorie, prioriteit, labels, korte samenvatting, advies en uitlegbare signalen.
- `analyze_emails(limit)` — dezelfde analyse voor maximaal 50 recente berichten,
  efficiënt binnen één IMAP-verbinding.
- `analyze_sender(domain)` en `get_sender_reputation(domain)` — lokale,
  cumulatieve reputatie en risico-inschatting van een afzenderdomein.
- `analyze_links(id)` — URL-, HTTPS-, verkorter-, tracking- en risicoanalyse
  zonder links te openen.
- `analyze_attachments(id)` — risicoanalyse op basis van bestandsnaam,
  extensie, MIME-type en grootte, zonder bijlagen uit te voeren.

E-mailinhoud wordt bij elk verzoek live via IMAP opgehaald en de analyse wijzigt
geen flags of mailboxinhoud. Alleen reputatietellers en afzendermetadata worden
in `data/reputation.json` opgeslagen; berichttekst, HTML en credentials nooit.

### Mail Intelligence Engine

De engine is een modulaire, deterministische heuristiek: elke score is terug te
voeren op signalen zoals SPF/DKIM/DMARC, Return-Path/Reply-To, risicovolle TLD's,
Unicode, HTML-only mail, linkvolume, trackingpixels, phishingtaal en gevaarlijke
bijlage-extensies. Scores zijn indicatief en vormen geen antivirus- of externe
domain-intelligence-scan. Links worden bewust niet bezocht en bijlage-inhoud
wordt niet gedownload. Daardoor lekt analyse geen informatie naar afzenders.

`data/reputation.json` wordt automatisch en met een exclusieve file lock
aangemaakt. Maak `data/` schrijfbaar voor de PHP-worker (bijvoorbeeld mode 750
met de webserver als eigenaar). Batchanalyse telt iedere geanalyseerde e-mail als
een waarneming. Verwijder het JSON-bestand om het lokale leergeheugen te resetten.

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
| `DEBUG` | Standaard `false`. Logt alleen de authenticatiemethode en de eerste vier tekens van de token; nooit de volledige token. |
| `MAIL_PROTOCOL` | Protocol voor inkomende mail: `imap` (standaard) of `pop3`. |
| `IMAP_HOST`, `IMAP_PORT`, `IMAP_SECURE`, `IMAP_USER`, `IMAP_PASSWORD` | Inkomende mailverbinding (de sleutelnamen blijven voor achterwaartse compatibiliteit gelijk). Gebruik doorgaans poort 993 voor IMAP of 995 voor POP3 met `IMAP_SECURE=true`. |
| `DEBUG_MAIL` | Standaard `false`. Alleen bij tijdelijk inschakelen worden DNS-, TLS- en certificaatdiagnostiek uitgevoerd en veilige details teruggegeven. |
| `MAIL_NOVALIDATE_CERT` | Verouderde compatibiliteitssleutel. Certificaatvalidatie staat standaard aan. Zet dit alleen voor een aantoonbaar noodzakelijke legacy-server tijdelijk op `true`. |
| `MAIL_CONNECT_IPV4` | Standaard `false`. Verbindt tijdelijk rechtstreeks met het eerste DNS IPv4-adres om IPv6/routingproblemen te isoleren. |
| `MAIL_SOCKET_TIMEOUT` | Timeout in seconden voor mailnetwerkacties (standaard 5, bereik 1-5). |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_SECURE`, `SMTP_USER`, `SMTP_PASSWORD` | SMTP-verbinding. Poort 587 (STARTTLS, `SMTP_SECURE=false`) of 465 (impliciete TLS, `SMTP_SECURE=true`). Leeg laten van `SMTP_USER`/`SMTP_PASSWORD` hergebruikt de IMAP-credentials. |
| `SMTP_FROM_ADDRESS`, `SMTP_FROM_NAME` | Afzenderadres/-naam voor `send_email`. |

**`config/config.php` nooit committen** — staat al in `.gitignore`, en wordt
via de deploy-workflow ook nooit overschreven/verwijderd (zie hieronder).
Op hosting waar environment variables wél goed doorgezet worden naar PHP
(`getenv()`), mogen deze waarden ook via het hostingpaneel als environment
variable gezet worden — die krijgen dan voorrang boven `config/config.php`.

### Inkomende mailverbinding veilig diagnosticeren

Een normale IMAP/POP3-aanroep opent precies één geconfigureerde mailboxstring en
doet geen voorafgaande diagnose. Met `DEBUG_MAIL=true` wordt eerst één DNS-,
TCP- en TLS-test uitgevoerd. Mislukt die sockettest, dan stopt de aanvraag
direct: `imap_open()`, certificaatverwerking en alternatieve mailboxstrings
worden niet meer geprobeerd. SSL-certificaatvalidatie staat standaard aan. Alleen de expliciete legacy-optie `MAIL_NOVALIDATE_CERT=true` schakelt IMAP-validatie uit; dit wordt afgeraden.

De optionele diagnose rapporteert ook PHP-, OpenSSL-, cURL- en streaminformatie, alle
A/AAAA-adressen, socketfouten, de onderhandelde TLS-versie en cipher, het
certificaat (subject, issuer en SAN) en informatieve geldigheids-/hostnaamgegevens,
de volledige OpenSSL-errorqueue en alle door ext-imap gerapporteerde fouten.
Bij een fout bevat de debug-JSON altijd `success`, `phase`, `error_type`,
`message`, `duration_ms` en `diagnostics`. De debugdetails bevatten daarnaast
staptijden voor DNS, socket, SSL-handshake en `imap_open()`.

De waarde van `error_type` lokaliseert de fout: `dns_error` betekent dat er
geen A/AAAA-record bruikbaar is, `socket_unreachable` wijst op poort/firewall/
routing, `ssl_handshake_error` op TLS of het certificaat,
`authentication_error` op geweigerde credentials, `protocol_error` op een
verkeerd of onverwacht POP3/IMAP-antwoord en `unknown_imap_error` op een niet
herkende fout uit `imap_open()`. `missing_imap_extension` en
`missing_openssl_extension` melden ontbrekende PHP-extensies expliciet.

Controleer eerst de serverlog en zet daarna zo nodig tijdelijk `DEBUG_MAIL=true`.
Gebruik `MAIL_CONNECT_IPV4=true` alleen om een verschil tussen IPv6 en IPv4 vast
te stellen. `MAIL_NOVALIDATE_CERT` wordt nog ingelezen voor compatibiliteit met
bestaande configuraties. Laat de waarde `false`: certificaatvalidatie blijft dan
ingeschakeld. Alleen `true` voegt de onveilige `/novalidate-cert`-optie toe.

Werkt de verbinding niet, test
dan achtereenvolgens de onderhandelde TLS-versie, de officiële serverhostnaam
van de hostingprovider, het IPv4-adres (`MAIL_CONNECT_IPV4=true`) en PHP's
OpenSSL/CA-instellingen (`openssl.cafile` en `openssl.capath`).

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

## Verbinden vanuit GPT / ChatGPT

1. Ga in GPT/ChatGPT naar de configuratie voor connectors of MCP-servers.
2. Vul als URL de publieke HTTPS-endpoint in, bv.
   `https://jouw-domein.nl/mcp.php` (of `.../public/mcp.php`, afhankelijk
   van je document-root-configuratie).
3. Wanneer de client headers ondersteunt, kies je authenticatietype **Bearer
   token** en vul je de waarde van `MCP_BEARER_TOKEN` in. Wanneer GPT alleen een
   URL accepteert, gebruik je `https://jouw-domein.nl/mcp.php?token=JOUW_TOKEN`.
4. Sla op — GPT kan nu de tools `list_emails`, `read_email`,
   `search_emails` en `send_email` gebruiken.

### Authenticatieheader

Bij voorkeur wordt `Authorization: Bearer JOUW_BEARER_TOKEN` gebruikt. Voor
GPT-clients die geen headers kunnen instellen, wordt ook `?token=JOUW_BEARER_TOKEN`
geaccepteerd. De header heeft voorrang als beide aanwezig zijn. Vergelijking
gebeurt timing-safe met `hash_equals`; tokens en berichtinhoud worden niet door
de applicatie gelogd. Let op: URL-tokens kunnen wel in webserver-/proxylogs en
browsergeschiedenis verschijnen. Gebruik daarom uitsluitend HTTPS, een apart
lang willekeurig token en roteer dit bij vermoeden van logging of uitlekken. De
endpoint hanteert 60 requests per minuut per client-IP en weigert request bodies
groter dan 1 MiB.

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
    AnalyzeEmailTool.php       -> volledige analyse van één bericht
    AnalyzeEmailsTool.php      -> batchanalyse
    AnalyzeSenderTool.php      -> samenvatting van domeinreputatie
    AnalyzeLinksTool.php
    AnalyzeAttachmentsTool.php
    GetSenderReputationTool.php
    IntelligenceSupport.php    -> gedeelde read-only IMAP-orchestratie
    Support.php        -> gedeelde helpers (resultaten, overview-formatting)
  Intelligence/
    EmailAnalyzer.php          -> classificatie- en score-engine
    LinkAnalyzer.php           -> lokale URL-analyse
    AttachmentAnalyzer.php     -> metadata-analyse van bijlagen
    ReputationStore.php        -> concurrency-safe lokaal leergeheugen
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


## Mailbox security tools

De bestaande endpoints en tools blijven beschikbaar. De uitbreiding voegt toe:

| Tool | Retourneert / bedoeld gebruik |
|---|---|
| `search_emails` | Gepagineerde metadata en snippets; zoek met query, headers, body, datum, flags en bijlagenfilter. |
| `get_email` | Eén volledige mail, veilige HTML, relevante headers, bijlagemetadata en links; geen binaire bijlagen. |
| `get_email_headers` | Ruwe plus gestructureerde route-, SPF-, DKIM-, DMARC- en ARC-headers. |
| `list_attachments` | Bestandsmetadata en eerste classificatie; inhoud wordt niet uitgevoerd en SHA-256 is daarom standaard `null`. |
| `extract_links` | Lokale URL-analyse zonder links, redirects, DNS of trackingpixels te benaderen. |
| `analyze_email_security` | Uitlegbare, deterministische score 0–100; voert nooit mailboxacties uit. |
| `scan_mailbox_security` | Begrensde scan (maximaal 100 per pagina) met tellingen en verdachte resultaten. |
| `list_folders` | Foldernamen, speciale classificatie en waar beschikbaar aantallen. |
| `get_security_summary` | Trends en top-risicomails over een begrensde selectie. |

Scores: 0–24 laag, 25–49 aandacht, 50–74 verdacht en 75–100 hoog risico.
Deze heuristiek is geen antivirusproduct en geen garantie. Een losse term zoals
“laatste herinnering” levert slechts een beperkt gewicht op. Er worden standaard
geen externe phishingdiensten gebruikt en geen mailboxgegevens met derden gedeeld.
Regels en limieten staan in `config/security.php`.

### Voorbeeldtoolcalls

De voorbeelden tonen de `arguments` van een MCP `tools/call`:

```json
{"name":"search_emails","arguments":{"query":"verify account","date_from":"2026-01-01","unread_only":true,"limit":25,"offset":0,"sort":"newest"}}
{"name":"analyze_email_security","arguments":{"uid":1234,"folder":"INBOX"}}
{"name":"scan_mailbox_security","arguments":{"folder":"INBOX","date_from":"2026-05-01","date_to":"2026-07-29","limit":50,"risk_threshold":50}}
{"name":"search_emails","arguments":{"has_attachments":true,"limit":50,"offset":0}}
```

Elke nieuwe toolresponse gebruikt `{"success":true,"data":...,"meta":...}` of
`{"success":false,"error":{"code":"...","message":"..."}}`. Stabiele codes
zijn onder meer `AUTH_REQUIRED`, `AUTH_INVALID`, `INVALID_ARGUMENT`,
`IMAP_CONNECTION_FAILED`, `FOLDER_NOT_FOUND`, `EMAIL_NOT_FOUND`, `SEARCH_FAILED`,
`MESSAGE_TOO_LARGE`, `RATE_LIMITED` en `INTERNAL_ERROR`.

## Privacy, veilige HTML en deployment

Bodies worden uitsluitend door `get_email` volledig teruggegeven. Zoekresultaten
beperken snippets tot 240 tekens. HTML wordt zonder netwerktoegang geparseerd;
scripts, iframes, forms, eventhandlers, actieve URL-schema’s en externe
afbeeldingen worden verwijderd. Bijlagen worden niet uitgevoerd. Zet de webroot
op `public/`, forceer HTTPS, houd `config/config.php` buiten versiebeheer en geef
`data/` alleen schrijfrechten aan de PHP-worker. Productie verbergt interne
exceptions; zet debug uitsluitend tijdelijk aan.

Kopieer `.env.example` naar de configuratie van het hostingplatform (PHP leest
werkelijke process-environmentvariabelen; het bestand wordt niet automatisch
geladen). Gebruik PHP 8.1–8.3 met `imap`, `mbstring`, `iconv`, `openssl` en bij
voorkeur `dom`; Composer installeert PHPMailer:

```bash
composer install --no-dev --optimize-autoloader
find src public config tests -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/run.php
```

Troubleshooting: controleer bij `IMAP_CONNECTION_FAILED` host, poort, TLS,
firewall en credentials; schakel `DEBUG_MAIL` alleen kort in. Een ongeldige UID
geeft `EMAIL_NOT_FOUND`. Bij `RATE_LIMITED` wacht u tot het venster van één minuut
is verlopen. Grote mailboxen moeten altijd met datumfilters, `limit` en `offset`
worden benaderd. De samenvatting detecteert zonder persistente inhoudsindex geen
exacte duplicaten en publieke-suffixbepaling gebruikt een conservatieve lokale
benadering.
