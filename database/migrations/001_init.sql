-- Initial schema for the budget app.
-- SQLite. Applied automatically by Support/Database.php on first connect.

CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    name          TEXT NOT NULL,
    email         TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    created_at    TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS budget_periods (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    name            TEXT NOT NULL,
    start_date      TEXT NOT NULL,
    end_date        TEXT NOT NULL,
    opening_balance REAL NOT NULL DEFAULT 0,
    is_active       INTEGER NOT NULL DEFAULT 0,
    created_at      TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS income_items (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    period_id   INTEGER NOT NULL REFERENCES budget_periods(id) ON DELETE CASCADE,
    description TEXT NOT NULL,
    budgeted    REAL NOT NULL DEFAULT 0,
    actual      REAL,
    status      TEXT NOT NULL DEFAULT '',
    sort_order  INTEGER NOT NULL DEFAULT 0,
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS fixed_costs (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    period_id   INTEGER NOT NULL REFERENCES budget_periods(id) ON DELETE CASCADE,
    description TEXT NOT NULL,
    budgeted    REAL NOT NULL DEFAULT 0,
    actual      REAL,
    status      TEXT NOT NULL DEFAULT '',
    sort_order  INTEGER NOT NULL DEFAULT 0,
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS transactions (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    period_id   INTEGER NOT NULL REFERENCES budget_periods(id) ON DELETE CASCADE,
    txn_date    TEXT NOT NULL,
    description TEXT NOT NULL,
    amount      REAL NOT NULL DEFAULT 0,
    is_settled  INTEGER NOT NULL DEFAULT 0,
    sort_order  INTEGER NOT NULL DEFAULT 0,
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS pots (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    name             TEXT NOT NULL,
    icon             TEXT NOT NULL DEFAULT '',
    amount           REAL,
    note             TEXT NOT NULL DEFAULT '',
    linked_period_id INTEGER REFERENCES budget_periods(id) ON DELETE SET NULL,
    sort_order       INTEGER NOT NULL DEFAULT 0,
    created_at       TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_income_period ON income_items(period_id);
CREATE INDEX IF NOT EXISTS idx_fixed_period ON fixed_costs(period_id);
CREATE INDEX IF NOT EXISTS idx_txn_period ON transactions(period_id);
CREATE INDEX IF NOT EXISTS idx_pots_linked_period ON pots(linked_period_id);
