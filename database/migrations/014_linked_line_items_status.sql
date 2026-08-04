-- Status van een gekoppelde last/inkomst wordt niet meer los bijgewerkt
-- (dat was dubbel werk) — zodra een kasstroommutatie eraan gekoppeld is,
-- staat de status automatisch op "Betaald"/"Ontvangen". Deze migratie zet
-- dat ook met terugwerkende kracht recht voor regels die al gekoppeld
-- waren vóórdat dit automatisch ging.

UPDATE fixed_costs SET status = 'Betaald'
WHERE id IN (SELECT DISTINCT fixed_cost_id FROM transactions WHERE fixed_cost_id IS NOT NULL)
  AND status != 'Betaald';

UPDATE income_items SET status = 'Ontvangen'
WHERE id IN (SELECT DISTINCT income_item_id FROM transactions WHERE income_item_id IS NOT NULL)
  AND status != 'Ontvangen';
