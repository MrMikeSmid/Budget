-- Niet-maandelijkse terugkerende inkomsten (kwartaal/halfjaarlijks/
-- jaarlijks), zelfde mechanisme als de vaste lasten uit migratie 005:
-- optioneel op een vaste datum i.p.v. simpelweg elke periode, en
-- recurrence_group_id wijst naar de oorspronkelijke (eerste) regel van
-- een terugkerende reeks zodat we over alle periodes heen kunnen bepalen
-- wanneer de eerstvolgende herhaling weer aan de beurt is.

ALTER TABLE income_items ADD COLUMN recurrence_interval TEXT NOT NULL DEFAULT 'maandelijks';
ALTER TABLE income_items ADD COLUMN recurrence_mode TEXT NOT NULL DEFAULT 'periode';
ALTER TABLE income_items ADD COLUMN recurrence_date TEXT;
ALTER TABLE income_items ADD COLUMN recurrence_group_id INTEGER REFERENCES income_items(id) ON DELETE SET NULL;
