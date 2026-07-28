# MCP Email Connector (IMAP/SMTP)

Self-hosted MCP-server in Node.js die Claude toegang geeft tot een generieke
IMAP/SMTP-mailbox (eigen domein via cPanel/Plesk-hosting, geen Gmail/Outlook
API's). Draait als persistent Node.js-proces via Streamable HTTP — geen
Python, Docker of VPS nodig.

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

E-mailinhoud wordt bij elk verzoek live via IMAP opgehaald; er wordt niets
persistent op de server opgeslagen, en credentials worden nooit gelogd.

## Vereisten op de hosting

- Node.js LTS (>= 18.17) via bv. cPanel "Setup Node.js App" / Passenger.
- Uitgaande poorten 993 (IMAPS) en 465/587 (SMTP) open richting je
  mailprovider — controleer dit bij je hoster.
- HTTPS/TLS voor de publieke URL van deze connector (bestaande hosting-SSL
  volstaat meestal; Passenger-apps draaien meestal achter de Apache/Nginx
  van cPanel die dit al regelt).
- De app moet als **persistent proces** kunnen draaien (niet als cronjob),
  omdat Streamable HTTP een continu luisterende server vereist.

## Installatie

```bash
npm install
npm run build
```

Dit compileert TypeScript naar `dist/`. Voor lokale ontwikkeling:

```bash
cp .env.example .env
# vul .env in
npm run dev
```

## Configuratie (environment variables)

Zie [`.env.example`](./.env.example) voor de volledige lijst. Kern:

| Variabele | Omschrijving |
|---|---|
| `MCP_BEARER_TOKEN` | **Verplicht.** Bearer token voor authenticatie op de HTTP-endpoint. Genereer bv. met `openssl rand -hex 32`. |
| `IMAP_HOST`, `IMAP_PORT`, `IMAP_SECURE`, `IMAP_USER`, `IMAP_PASSWORD` | IMAP-verbinding. Poort 993 met `IMAP_SECURE=true` is de standaard (impliciete TLS). |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_SECURE`, `SMTP_USER`, `SMTP_PASSWORD` | SMTP-verbinding. Poort 587 (STARTTLS, `SMTP_SECURE=false`) of 465 (impliciete TLS, `SMTP_SECURE=true`). Als `SMTP_USER`/`SMTP_PASSWORD` leeg blijven, worden de IMAP-credentials hergebruikt. |
| `SMTP_FROM_ADDRESS`, `SMTP_FROM_NAME` | Afzenderadres/-naam voor `send_email`. |
| `PORT` | Poort waarop de server luistert (hosting geeft dit vaak automatisch mee). |

Gebruik nooit hardcoded wachtwoorden in code — alles loopt via environment
variables. Op cPanel/Plesk zet je deze via het "Node.js App"-scherm
(environment variables), niet in een `.env`-bestand op de productieserver.

### Meerdere accounts (optioneel)

In plaats van de losse `IMAP_*`/`SMTP_*` variabelen kun je `MAIL_ACCOUNTS_JSON`
zetten met een JSON-array van accounts (zie voorbeeld in `.env.example`). Elke
tool accepteert dan een optionele `account`-parameter met het gekozen `id`;
zonder opgave wordt het eerste account gebruikt.

## Draaien op gedeelde hosting (cPanel/Plesk "Node.js App")

1. Upload de repository (exclusief `node_modules`) naar de hosting, of
   clone/pull via Git indien beschikbaar.
2. Maak in cPanel een "Node.js App" aan:
   - **Application root**: map waar dit project staat.
   - **Application startup file**: `dist/index.js`.
   - **Application mode**: Production.
3. Zet de environment variables uit `.env.example` via het cPanel-scherm
   (inclusief `MCP_BEARER_TOKEN`, IMAP/SMTP-gegevens).
4. Open de cPanel-terminal voor de app (of "Run NPM Install") en voer uit:
   ```bash
   npm install --omit=dev
   npm run build
   ```
   (`npm run build` heeft de `devDependencies` — TypeScript — nodig; run dit
   dus vóór `npm install --omit=dev`, of doe eerst een volledige `npm install`
   gevolgd door `npm run build` en optioneel daarna opschonen.)
5. Start/herstart de app via cPanel. Passenger houdt het proces persistent
   draaiend.
6. Verifieer dat de publieke URL bereikbaar is over HTTPS, bv.:
   ```bash
   curl https://jouw-domein.nl/health
   ```

## Verbinden vanuit Claude.ai

1. Ga naar **Instellingen → Connectors → Aangepaste connector toevoegen**.
2. Vul als URL de publieke HTTPS-endpoint in, bv.
   `https://jouw-domein.nl/mcp`.
3. Kies authenticatietype **Bearer token** en vul de waarde van
   `MCP_BEARER_TOKEN` in.
4. Sla op — Claude kan nu de tools `list_emails`, `read_email`,
   `search_emails` en `send_email` gebruiken.

## Projectstructuur

```
/src
  index.ts          -> MCP server setup + Streamable HTTP endpoint
  auth.ts           -> bearer token check middleware
  config.ts         -> laden van account-/tokenconfiguratie uit env vars
  tools/
    listEmails.ts
    readEmail.ts
    searchEmails.ts
    sendEmail.ts
    shared.ts       -> gedeelde helpers (envelope-formatting, resultaten)
  mail/
    imapClient.ts
    smtpClient.ts
.env.example
```

## Niet-functionele eigenschappen

- Credentials worden nooit gelogd.
- E-mailinhoud wordt niet persistent opgeslagen; elke `read_email`/
  `list_emails`/`search_emails`-aanroep haalt live op via IMAP.
- Verbindingsfouten met de mailserver resulteren in een leesbare MCP-tool-
  error (`isError: true` + boodschap), niet in een gecrashed proces.
- Minimale dependencies (`@modelcontextprotocol/sdk`, `express`, `imapflow`,
  `mailparser`, `nodemailer`, `zod`, `dotenv`) om licht genoeg te blijven
  voor gedeelde hosting.

## Nog te verifiëren op de doelhosting

- Of het gekozen hostingpakket Node.js als persistent proces (Passenger)
  toestaat, en niet alleen cronjobs/CGI.
- Of uitgaande poorten 993 en 465/587 daadwerkelijk open staan.
- Of er een reverse proxy/extra TLS-configuratie nodig is voor de
  connector-URL (meestal niet, cPanel regelt dit voor Node.js-apps).
