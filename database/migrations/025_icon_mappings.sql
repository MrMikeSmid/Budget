-- Koppelt een omschrijving (bijv. "Netflix") aan een lokaal meegeleverd
-- merk-icoon (zie src/Support/BrandIcons.php), zodat elke vaste last met
-- die omschrijving automatisch het bijbehorende icoon toont. Hoort bij het
-- huishouden (net als de vaste lasten/inkomsten zelf), niet bij de centrale
-- app-database.

CREATE TABLE IF NOT EXISTS icon_mappings (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    description TEXT NOT NULL UNIQUE COLLATE NOCASE,
    icon_slug   TEXT NOT NULL,
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);
