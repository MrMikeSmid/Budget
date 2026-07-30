-- Leningen/schulden: een lening heeft een totaalbedrag en een maandelijkse
-- termijn. Elke betaalde termijn (via de gekoppelde vaste last) wordt
-- vastgelegd als een aparte betaling, zodat het openstaande bedrag altijd
-- terug te rekenen is (zelfde patroon als pot_transactions).

CREATE TABLE IF NOT EXISTS loans (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    name            TEXT NOT NULL,
    total_amount    REAL NOT NULL,
    monthly_payment REAL NOT NULL,
    note            TEXT NOT NULL DEFAULT '',
    created_at      TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS loan_payments (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    loan_id       INTEGER NOT NULL REFERENCES loans(id) ON DELETE CASCADE,
    fixed_cost_id INTEGER REFERENCES fixed_costs(id) ON DELETE CASCADE,
    amount        REAL NOT NULL,
    created_at    TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_loan_payments_loan ON loan_payments(loan_id);
CREATE INDEX IF NOT EXISTS idx_loan_payments_fc ON loan_payments(fixed_cost_id);

-- Niet-maandelijkse terugkerende vaste lasten (kwartaal/halfjaarlijks/
-- jaarlijks), optioneel op een vaste datum i.p.v. simpelweg elke periode.
-- recurrence_group_id wijst naar de oorspronkelijke (eerste) regel van een
-- terugkerende reeks, zodat we over alle periodes heen kunnen bepalen
-- wanneer de eerstvolgende herhaling weer aan de beurt is.

ALTER TABLE fixed_costs ADD COLUMN recurrence_interval TEXT NOT NULL DEFAULT 'maandelijks';
ALTER TABLE fixed_costs ADD COLUMN recurrence_mode TEXT NOT NULL DEFAULT 'periode';
ALTER TABLE fixed_costs ADD COLUMN recurrence_date TEXT;
ALTER TABLE fixed_costs ADD COLUMN recurrence_group_id INTEGER REFERENCES fixed_costs(id) ON DELETE SET NULL;
ALTER TABLE fixed_costs ADD COLUMN loan_id INTEGER REFERENCES loans(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_fixed_costs_loan ON fixed_costs(loan_id);
