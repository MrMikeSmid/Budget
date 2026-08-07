-- Iconenkoppelingen (omschrijving -> zelf-geüploade afbeelding) horen bij
-- de app als geheel, niet bij één huishouden: alleen een admin beheert ze
-- (zie IconMappingController), maar elk huishouden ziet dezelfde set. Staat
-- daarom in de centrale database i.p.v. per-huishouden zoals voorheen
-- (zie database/migrations/025_icon_mappings.sql, dat lokale spoor blijft
-- ongebruikt staan i.p.v. verwijderd — geen destructieve migratie nodig
-- voor een cosmetische feature).
CREATE TABLE IF NOT EXISTS icon_mappings (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    description TEXT NOT NULL UNIQUE COLLATE NOCASE,
    icon_path   TEXT NOT NULL,
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);
