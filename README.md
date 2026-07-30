# Budgetapp

Mobile-first budget- en kasstroombeheer, gebouwd als vervanging van het
Excel-budgetoverzicht. PHP + SQLite, geen framework, ontworpen om makkelijk
uit te breiden en later te verplaatsen.

## Wat de app doet

Vier onderdelen, direct gebaseerd op het oorspronkelijke Excel-bestand:

- **Kasstroom** — transacties per budgetperiode met automatisch lopend saldo.
- **Vaste lasten** — begroot vs. werkelijk per post, met status (Betaald/Open/...).
- **Inkomsten** — begroot vs. ontvangen per inkomstenbron.
- **Potjes** — losse spaarpotjes; een potje kan gekoppeld worden aan het
  actuele eindsaldo van een periode (zoals het "Vaste lasten"-potje in de
  Excel dat naar het Kasstroom-tabblad verwees).

Budgetperiodes zijn niet aan een kalendermaand gebonden (bijv. "20 juli t/m
19 augustus") en je maakt er elke maand een nieuwe aan — oudere periodes
blijven bewaard. Alle statussen zijn vrije tekst, dus net zo flexibel als in
Excel.

Iedereen met een account heeft volledige rechten (geen rollen/niveaus). Er is
geen openbare registratie: het eerste account maak je aan bij de eerste
bezoek aan de app, daarna maak je extra accounts aan via het account binnen
de app ("Meer" → "Accounts").

## Lokaal draaien

Vereist: PHP 8.1+ met de `pdo_sqlite`-extensie, en Composer.

```bash
composer install
cp config/config.example.php config/config.php
php -S localhost:8000
```

Open `http://localhost:8000/index.php` — de eerste keer wordt gevraagd een
account aan te maken. De SQLite-database en het schema worden automatisch
aangemaakt in `storage/database.sqlite`.

## Live zetten (mikesmid.nl/development)

Het bestaande deploy-script (`.github/workflows/deploy.yml`) upload de repo
via FTP naar de serverdirectory, en installeert automatisch
composer-dependencies. Het sluit twee dingen bewust uit van elke deploy, zodat
ze nooit overschreven worden:

- `config/config.php` — instellingen die per omgeving verschillen.
- `storage/` — bevat de SQLite-database, moet dus overleven tussen deploys.

**Eenmalige stappen op de server, vóór de eerste deploy** (via FTP of de
hostingpaneel-bestandsbeheerder):

1. Maak `config/config.php` aan op basis van `config/config.example.php` en
   pas eventueel het `db_path` aan.
2. Zorg dat er een schrijfbare map `storage/` bestaat op de server.

Dat is alles — de app maakt de database en het `.htaccess`-bestand dat de
map beschermt zelf aan bij het eerste bezoek. Na deze eenmalige stap kun je
gewoon blijven pushen naar de deploy-branch; de data blijft altijd staan.

## Verplaatsen naar een andere map

De app gebruikt overal relatieve links (`index.php?page=...`) in plaats van
een vast basispad. Verplaats de hele map (bijv. van `/development` naar
`/budget`) en alles blijft werken zonder configuratie aan te passen.

## Uitbreiden

- `database/migrations/*.sql` — nieuwe migratie toevoegen = nieuw
  genummerd `.sql`-bestand; wordt automatisch bij de eerstvolgende request
  toegepast (bijgehouden in de `migrations`-tabel).
- `src/Models` — één klasse per tabel; `LineItem` is de gedeelde basis voor
  Inkomsten en Vaste lasten.
- `src/Controllers` + `index.php` — routes zijn simpele `pagina-naam` →
  handler-koppelingen in `index.php`, geen routing-magie.
- `views/` — kale PHP-templates, `views/layout.php` is de gedeelde
  pagina-opmaak (bottom-nav op mobiel, zijnav vanaf ~900px).

## Beveiliging

- Wachtwoorden met `password_hash`/`password_verify`.
- CSRF-token verplicht op elke POST.
- `storage/`, `config/`, `src/` en `database/` hebben elk een eigen
  `.htaccess` die directe toegang blokkeert — alleen `index.php` en
  `assets/` zijn publiek bereikbaar.
