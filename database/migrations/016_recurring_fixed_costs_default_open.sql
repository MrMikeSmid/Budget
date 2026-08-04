-- Terugkerende vaste lasten kregen bij het kopiëren naar een nieuwe periode
-- geen status mee (leeg), waardoor ze niet meetelden in "Nog openstaand" en
-- geen "Open"-badge lieten zien — onduidelijk wat er nog betaald moest
-- worden. Nieuwe kopieën krijgen voortaan meteen status "Open"
-- (zie FixedCost::copyRecurring()); deze migratie zet dat ook met
-- terugwerkende kracht recht, maar alleen voor regels die nog niet aan een
-- kasstroommutatie gekoppeld zijn (anders zou het al vaststaande "Betaald"
-- overschreven kunnen worden).

UPDATE fixed_costs SET status = 'Open'
WHERE status = ''
  AND is_recurring = 1
  AND id NOT IN (SELECT fixed_cost_id FROM transactions WHERE fixed_cost_id IS NOT NULL);
