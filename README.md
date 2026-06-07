# FTP-deployment

De applicatie wordt via GitHub Actions naar de FTP-server gedeployed. De workflow draait automatisch na een push naar `main` of `master` en kan ook handmatig worden gestart via **Actions → Deploy via FTP → Run workflow**.

## Benodigde repository secrets

Voeg onder **Settings → Secrets and variables → Actions** de volgende repository secrets toe:

| Secret | Waarde |
| --- | --- |
| `FTP_SERVER` | Hostnaam of URL van de FTP-server, bijvoorbeeld `ftp.example.nl` of `ftps://ftp.example.nl` |
| `FTP_USERNAME` | FTP-gebruikersnaam |
| `FTP_PASSWORD` | FTP-wachtwoord |
| `FTP_SERVER_DIR` | Doelmap op de server, bijvoorbeeld `/public_html/` |

De deployment spiegelt de volledige inhoud van de repository naar de doelmap. Bestanden die niet meer in de repository staan, worden ook van de server verwijderd. De mappen `.git` en `.github` en het placeholderbestand `.gitkeep` worden niet geüpload.

## Handmatig testen

Het onderliggende script kan lokaal worden uitgevoerd wanneer `lftp` is geïnstalleerd:

```bash
FTP_SERVER='ftp.example.nl' \
FTP_USERNAME='gebruiker' \
FTP_PASSWORD='wachtwoord' \
FTP_SERVER_DIR='/public_html/' \
./scripts/deploy-ftp.sh
```

> Let op: `--delete` houdt de servermap exact gelijk aan de repository. Gebruik daarom een map die uitsluitend voor deze applicatie bestemd is.
