-- De centrale, applicatiebrede gebruikersdatabase (zie AppDatabase) vervangt
-- de rol van de lokale `users`-tabel hierin. Om Pot::ledger() zonder
-- cross-database join te laten werken, wordt de naam van de gebruiker nu
-- rechtstreeks op de pot_transactions-rij bewaard (zelfde patroon als
-- activities.user_name), eenmalig teruggevuld vanuit de nog-aanwezige lokale
-- users-tabel. Die tabel zelf blijft bestaan (wordt alleen niet meer
-- beschreven) — geen destructieve wijziging op bestaande productiedata.

ALTER TABLE pot_transactions ADD COLUMN user_name TEXT;

UPDATE pot_transactions
SET user_name = (SELECT name FROM users WHERE users.id = pot_transactions.user_id)
WHERE user_id IS NOT NULL;
