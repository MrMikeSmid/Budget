-- Unieke bank-referentie van een geïmporteerde mutatie (Knab "Referentie",
-- MT940 :61:-referentie, CAMT.053 AcctSvcrRef), gebruikt om een herhaalde
-- import van dezelfde periode niet nogmaals als dubbele mutatie aan te maken.
-- ING levert geen stabiele referentie, dus blijft daar leeg.
ALTER TABLE transactions ADD COLUMN bank_reference TEXT;

CREATE INDEX IF NOT EXISTS idx_transactions_bank_reference ON transactions(bank_reference);
