-- Ondersteunt de "meer betaald/ontvangen dan begroot"-waarschuwing op het
-- dashboard: eenmalig tonen, en na het bekijken van de bijbehorende mutatie
-- nooit meer terugkomen voor diezelfde regel.
ALTER TABLE fixed_costs ADD COLUMN warning_dismissed_at TEXT;
ALTER TABLE income_items ADD COLUMN warning_dismissed_at TEXT;
