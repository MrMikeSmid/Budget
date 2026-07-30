-- Markeert inkomsten/vaste lasten als terugkerend, zodat ze bij het
-- aanmaken van een nieuwe periode automatisch overgenomen kunnen worden.

ALTER TABLE income_items ADD COLUMN is_recurring INTEGER NOT NULL DEFAULT 0;
ALTER TABLE fixed_costs ADD COLUMN is_recurring INTEGER NOT NULL DEFAULT 0;
