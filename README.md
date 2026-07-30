# Budgetbeheer

Een kleine, zelfstandige PHP/SQLite-app om een huishoudbudget bij te houden. De app heeft geen framework of buildstap nodig.

## Starten

```bash
php -S localhost:8080 -t public
```

Open daarna <http://localhost:8080>. De SQLite-database wordt automatisch aangemaakt in `var/budget.sqlite`.

## Mogelijkheden

- inkomsten en uitgaven registreren, wijzigen en verwijderen;
- budgetten per categorie en maand instellen;
- dashboard met saldo, maandtotalen en budgetvoortgang;
- filteren op maand, soort en zoekterm;
- CSV exporteren en importeren (puntkomma of komma als scheidingsteken);
- voorbeeldgegevens met een druk op de knop toevoegen.

## CSV-indeling

De eerste regel mag de volgende kolommen bevatten:

```text
datum;omschrijving;categorie;type;bedrag;notitie
```

`type` is `inkomst` of `uitgave`; bedragen mogen een komma of punt als decimaalteken gebruiken.
