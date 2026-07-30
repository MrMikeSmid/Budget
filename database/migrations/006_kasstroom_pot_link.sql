-- Koppelt een kasstroommutatie optioneel aan een potje: zo trekt een
-- mutatie ("tanken", etc.) automatisch af/bij op het gekozen potje en is
-- de mutatie ook zichtbaar als transactie onder dat potje.

ALTER TABLE transactions ADD COLUMN pot_id INTEGER REFERENCES pots(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_txn_pot ON transactions(pot_id);
