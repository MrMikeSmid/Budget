-- Markeert een periode als (handmatig) afgesloten via de "Periode
-- afsluiten"-flow (zie PeriodCloseController). Puur informatief: de
-- knop verdwijnt en de vaste-lastenpagina toont een waarschuwing, maar
-- bewerken blijft mogelijk — geen harde vergrendeling.
ALTER TABLE budget_periods ADD COLUMN closed_at TEXT NULL;
