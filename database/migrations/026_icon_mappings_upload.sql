-- De meegeleverde iconenbibliotheek is vervangen door zelf te uploaden
-- afbeeldingen (zie IconMappingController::upload()). Kolom hernoemen naar
-- wat hij nu bevat: de bestandsnaam van de upload binnen
-- storage/households/{id}/icons/, niet meer een merk-slug. Bestaande
-- koppelingen (naar een inmiddels verwijderd meegeleverd icoon) blijven
-- gewoon staan — de app valt terug op de placeholder-letter zolang het
-- bestand niet bestaat, dus dit is veilig zonder databaseverlies.
ALTER TABLE icon_mappings RENAME COLUMN icon_slug TO icon_path;
