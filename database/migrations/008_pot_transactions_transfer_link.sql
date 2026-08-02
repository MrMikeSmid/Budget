-- Onderscheidt een overboeking-tussen-twee-potjes (Overboeken, waarbij
-- zowel "van" als "naar" een potje is) van een gewone storting/opname
-- die het losse saldo raakt. Bij een potje-naar-potje overboeking wordt
-- op beide pot_transactions-rijen de andere potje-id gezet, zodat de
-- kasstroompagina die twee losse boekingen kan overslaan (ze raken het
-- losse saldo per saldo niet) en alleen de rijen toont die dat wel doen.

ALTER TABLE pot_transactions ADD COLUMN transfer_pot_id INTEGER REFERENCES pots(id) ON DELETE SET NULL;
