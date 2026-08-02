-- Potjes worden voortaan "zacht" verwijderd (deleted_at gezet i.p.v. de
-- rij weg te halen). Een pot_transactions-rij verdween tot nu toe altijd
-- mee via ON DELETE CASCADE, en een gekoppelde kasstroommutatie verloor
-- zijn pot_id via ON DELETE SET NULL — beide keren veranderde dat met
-- terugwerkende kracht het al berekende saldo van allang afgesloten
-- periodes zodra je een potje verwijderde. Nu blijft de rij (en dus de
-- geschiedenis en de periodesaldi) gewoon bestaan; het potje verdwijnt
-- alleen uit de actieve lijsten en keuzemenu's.

ALTER TABLE pots ADD COLUMN deleted_at TEXT;
