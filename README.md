# FTP-deployment

De applicatie wordt via GitHub Actions naar de FTP-server gedeployed. De workflow draait automatisch na een push naar `main` of `master` en kan ook handmatig worden gestart via **Actions → Deploy via FTP → Run workflow**.

## Benodigde repository secrets

Voeg onder **Settings → Secrets and variables → Actions** de volgende repository secrets toe:

| Secret | Waarde |
| --- | --- |
| `FTP_SERVER` | Alleen de hostnaam of FTP(S)-URL, bijvoorbeeld `ftp.example.nl` of `ftps://ftp.example.nl` |
| `FTP_USERNAME` | Alleen de FTP-gebruikersnaam |
| `FTP_PASSWORD` | Alleen het FTP-wachtwoord |
| `FTP_SERVER_DIR` | Alleen de doelmap op de server, bijvoorbeeld `/public_html/` |

Voer elke waarde in als één regel, zonder aanhalingstekens en zonder de naam van de secret. Voor `FTP_SERVER` is bijvoorbeeld `ftp.example.nl` correct; voer niet `FTP_SERVER=ftp.example.nl` in. Per ongeluk meegekopieerde lege regels vóór of na een waarde worden door de workflow verwijderd. Een regeleinde midden in een waarde blijft een fout en moet in de repository secret worden verwijderd.

De deployment spiegelt de volledige inhoud van de repository naar de doelmap. Bestanden die niet meer in de repository staan, worden ook van de server verwijderd. De mappen `.git` en `.github` en het placeholderbestand `.gitkeep` worden niet geüpload.

## Deployment starten

Alle deploylogica staat in `.github/workflows/deploy.yml`; er is geen afzonderlijke `scripts`-map nodig. Start de deployment met een push naar `main` of `master`, of handmatig via het tabblad **Actions** in GitHub.

> Let op: `--delete` houdt de servermap exact gelijk aan de repository. Gebruik daarom een map die uitsluitend voor deze applicatie bestemd is.
