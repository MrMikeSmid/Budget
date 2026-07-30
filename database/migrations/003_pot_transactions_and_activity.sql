-- Transacties (stortingen/opnames) op potjes, en een activiteitenlogboek
-- dat elke mutatie in de app bijhoudt voor de tijdlijnpagina.

CREATE TABLE IF NOT EXISTS pot_transactions (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    pot_id      INTEGER NOT NULL REFERENCES pots(id) ON DELETE CASCADE,
    user_id     INTEGER REFERENCES users(id) ON DELETE SET NULL,
    txn_date    TEXT NOT NULL,
    description TEXT NOT NULL,
    amount      REAL NOT NULL DEFAULT 0,
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_pot_txn_pot ON pot_transactions(pot_id);

CREATE TABLE IF NOT EXISTS activities (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER REFERENCES users(id) ON DELETE SET NULL,
    user_name   TEXT NOT NULL,
    category    TEXT NOT NULL,
    description TEXT NOT NULL,
    amount      REAL,
    occurred_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_activities_occurred ON activities(occurred_at);
