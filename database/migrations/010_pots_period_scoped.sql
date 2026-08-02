-- Potjes bestaan voortaan "vanaf" en (optioneel) "tot" een specifieke
-- periode, in plaats van overal zichtbaar te zijn zodra ze ergens
-- aangemaakt of verwijderd zijn. created_period_id/deleted_period_id
-- leggen vast in welke periode je aan het werken was toen je het potje
-- aanmaakte/verwijderde (niet de kloktijd "nu"): zo kun je alvast een
-- volgende, nog niet actieve maand voorbereiden — een potje dat je daar
-- verwijdert, blijft in de huidige en eerdere maanden gewoon bestaan.

ALTER TABLE pots ADD COLUMN created_period_id INTEGER REFERENCES budget_periods(id) ON DELETE SET NULL;
ALTER TABLE pots ADD COLUMN deleted_period_id INTEGER REFERENCES budget_periods(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_pots_created_period ON pots(created_period_id);
CREATE INDEX IF NOT EXISTS idx_pots_deleted_period ON pots(deleted_period_id);
