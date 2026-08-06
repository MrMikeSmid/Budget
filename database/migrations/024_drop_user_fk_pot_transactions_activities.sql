-- pot_transactions.user_id en activities.user_id verwijzen sinds de
-- huishouden-opsplitsing niet meer naar een geldige, actueel bijgehouden
-- tabel: de lokale `users`-tabel in dit bestand is vervangen door de
-- centrale, applicatiebrede gebruikersdatabase (zie AppDatabase) en wordt
-- nergens meer bijgewerkt. Een ingelogde gebruiker heeft dus meestal een
-- ander (centraal) id dan enige rij die nog in deze lokale tabel staat,
-- waardoor de FOREIGN KEY-constraint op user_id elke nieuwe activiteit/
-- potje-transactie van zo'n gebruiker blokkeerde.
--
-- SQLite kent geen "ALTER TABLE ... DROP CONSTRAINT", dus de tabellen
-- worden herbouwd zonder die FK — alle overige kolommen, FK's (pot_id,
-- period_id, transfer_pot_id) en data blijven ongewijzigd.

PRAGMA foreign_keys = OFF;

CREATE TABLE pot_transactions_new (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    pot_id          INTEGER NOT NULL REFERENCES pots(id) ON DELETE CASCADE,
    user_id         INTEGER,
    txn_date        TEXT NOT NULL,
    description     TEXT NOT NULL,
    amount          REAL NOT NULL DEFAULT 0,
    created_at      TEXT NOT NULL DEFAULT (datetime('now')),
    period_id       INTEGER REFERENCES budget_periods(id) ON DELETE SET NULL,
    transfer_pot_id INTEGER REFERENCES pots(id) ON DELETE SET NULL,
    user_name       TEXT
);

INSERT INTO pot_transactions_new (id, pot_id, user_id, txn_date, description, amount, created_at, period_id, transfer_pot_id, user_name)
SELECT id, pot_id, user_id, txn_date, description, amount, created_at, period_id, transfer_pot_id, user_name FROM pot_transactions;

DROP TABLE pot_transactions;
ALTER TABLE pot_transactions_new RENAME TO pot_transactions;

CREATE INDEX IF NOT EXISTS idx_pot_txn_pot ON pot_transactions(pot_id);
CREATE INDEX IF NOT EXISTS idx_pot_txn_period ON pot_transactions(period_id);

CREATE TABLE activities_new (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER,
    user_name   TEXT NOT NULL,
    category    TEXT NOT NULL,
    description TEXT NOT NULL,
    amount      REAL,
    occurred_at TEXT NOT NULL DEFAULT (datetime('now'))
);

INSERT INTO activities_new (id, user_id, user_name, category, description, amount, occurred_at)
SELECT id, user_id, user_name, category, description, amount, occurred_at FROM activities;

DROP TABLE activities;
ALTER TABLE activities_new RENAME TO activities;

CREATE INDEX IF NOT EXISTS idx_activities_occurred ON activities(occurred_at);

PRAGMA foreign_keys = ON;
