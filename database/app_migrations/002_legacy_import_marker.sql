-- Losstaande markering dat de eenmalige LegacyImporter-omzetting is
-- afgerond, en welk huishouden daaruit is voortgekomen. Bewust een aparte
-- tabel i.p.v. een vast, "gereserveerd" id in households/users: die twee
-- tabellen krijgen hun id's gewoon via het normale autoincrement, net als
-- elk ander (nieuw geregistreerd) huishouden/gebruiker, zodat een
-- gelijktijdige registratie er nooit tegenaan kan botsen.

CREATE TABLE IF NOT EXISTS legacy_import (
    id           INTEGER PRIMARY KEY CHECK (id = 1),
    household_id INTEGER NOT NULL REFERENCES households(id),
    completed_at TEXT NOT NULL DEFAULT (datetime('now'))
);
