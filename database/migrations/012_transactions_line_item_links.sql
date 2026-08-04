-- Koppelt kasstroom-uitgaven aan de vaste last (of inkomstenpost) die ze
-- feitelijk zijn. Tot nu toe stond een last in "Vaste lasten" volledig los
-- van de daadwerkelijke afschrijving op kasstroom, terwijl dezelfde
-- betaling vaak op beide plekken werd genoteerd — zonder koppeling werd
-- zo'n betaling dubbel van het saldo afgetrokken (eenmaal via het
-- "werkelijk"-bedrag van de last, eenmaal via de kasstroommutatie).

ALTER TABLE transactions ADD COLUMN fixed_cost_id INTEGER REFERENCES fixed_costs(id) ON DELETE SET NULL;
ALTER TABLE transactions ADD COLUMN income_item_id INTEGER REFERENCES income_items(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS idx_txn_fixed_cost ON transactions(fixed_cost_id);
CREATE INDEX IF NOT EXISTS idx_txn_income_item ON transactions(income_item_id);

-- Bestaande kasstroommutaties met terugwerkende kracht koppelen: alleen
-- wanneer de mutatie nog geen bron-potje heeft (dat is al een andere,
-- geldige koppeling) en er in dezelfde periode precies één last/inkomst
-- met exact dezelfde omschrijving bestaat (ondubbelzinnige match).
UPDATE transactions
SET fixed_cost_id = (
    SELECT fc.id FROM fixed_costs fc
    WHERE fc.period_id = transactions.period_id
      AND lower(trim(fc.description)) = lower(trim(transactions.description))
    LIMIT 1
)
WHERE pot_id IS NULL
  AND fixed_cost_id IS NULL
  AND (
    SELECT COUNT(*) FROM fixed_costs fc
    WHERE fc.period_id = transactions.period_id
      AND lower(trim(fc.description)) = lower(trim(transactions.description))
  ) = 1;

UPDATE transactions
SET income_item_id = (
    SELECT ii.id FROM income_items ii
    WHERE ii.period_id = transactions.period_id
      AND lower(trim(ii.description)) = lower(trim(transactions.description))
    LIMIT 1
)
WHERE pot_id IS NULL
  AND fixed_cost_id IS NULL
  AND income_item_id IS NULL
  AND (
    SELECT COUNT(*) FROM income_items ii
    WHERE ii.period_id = transactions.period_id
      AND lower(trim(ii.description)) = lower(trim(transactions.description))
  ) = 1;
