-- Koppelt een potje-transactie (storting/opname) aan de budgetperiode
-- waarin hij is geboekt, zodat het bedrag ook van/naar het periodesaldo
-- wordt verrekend: geld dat in een potje gestort wordt, staat niet meer
-- op het saldo, en komt bij een opname weer terug op het saldo.

ALTER TABLE pot_transactions ADD COLUMN period_id INTEGER REFERENCES budget_periods(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_pot_txn_period ON pot_transactions(period_id);

-- Bestaande potje-transacties met terugwerkende kracht koppelen aan de
-- periode waar hun datum in valt, zodat het saldo ook voor al ingevoerde
-- mutaties klopt.
UPDATE pot_transactions
SET period_id = (
    SELECT bp.id FROM budget_periods bp
    WHERE pot_transactions.txn_date >= bp.start_date AND pot_transactions.txn_date <= bp.end_date
    ORDER BY bp.start_date DESC
    LIMIT 1
)
WHERE period_id IS NULL;
