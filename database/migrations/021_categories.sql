-- Categorieën: één gedeelde lijst voor inkomsten, vaste lasten en leningen,
-- zodat je op één plek kunt zien hoeveel geld er per categorie in/uit gaat.
CREATE TABLE IF NOT EXISTS categories (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

ALTER TABLE income_items ADD COLUMN category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL;
ALTER TABLE fixed_costs ADD COLUMN category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL;
ALTER TABLE loans ADD COLUMN category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_income_items_category ON income_items(category_id);
CREATE INDEX IF NOT EXISTS idx_fixed_costs_category ON fixed_costs(category_id);
CREATE INDEX IF NOT EXISTS idx_loans_category ON loans(category_id);
