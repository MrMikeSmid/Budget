-- Door een eerdere (inmiddels opgeloste) bug rond dubbele periode-aanmaak
-- zijn er soms twee losse terugkerende reeksen met dezelfde omschrijving en
-- hetzelfde bedrag ontstaan, die daardoor bij elke nieuwe periode allebei
-- gekopieerd werden ("dubbele" inkomsten/lasten). Dit ruimt zulke exacte
-- duplicaten binnen een periode op, maar uitsluitend als het duplicaat nog
-- nooit is aangeraakt (geen werkelijk bedrag, geen gekoppelde mutatie/
-- aflossing) — zo kan dit nooit echte, al gebruikte regels wegvegen.

DELETE FROM income_items
WHERE actual IS NULL
  AND id NOT IN (SELECT income_item_id FROM transactions WHERE income_item_id IS NOT NULL)
  AND id NOT IN (SELECT MIN(id) FROM income_items GROUP BY period_id, description, budgeted)
  AND EXISTS (
      SELECT 1 FROM income_items dup
      WHERE dup.period_id = income_items.period_id
        AND dup.description = income_items.description
        AND dup.budgeted = income_items.budgeted
        AND dup.id != income_items.id
  );

DELETE FROM fixed_costs
WHERE actual IS NULL
  AND id NOT IN (SELECT fixed_cost_id FROM transactions WHERE fixed_cost_id IS NOT NULL)
  AND id NOT IN (SELECT fixed_cost_id FROM loan_payments WHERE fixed_cost_id IS NOT NULL)
  AND id NOT IN (SELECT MIN(id) FROM fixed_costs GROUP BY period_id, description, budgeted)
  AND EXISTS (
      SELECT 1 FROM fixed_costs dup
      WHERE dup.period_id = fixed_costs.period_id
        AND dup.description = fixed_costs.description
        AND dup.budgeted = fixed_costs.budgeted
        AND dup.id != fixed_costs.id
  );
