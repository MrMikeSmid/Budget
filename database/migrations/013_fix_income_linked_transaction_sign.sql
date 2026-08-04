-- Een kasstroommutatie die aan een inkomst gekoppeld is, is geld dat
-- binnenkomt en hoort dus positief te zijn (groen, met plus) i.p.v.
-- negatief zoals een uitgave. Deze migratie repareert mutaties die al
-- gekoppeld waren vóórdat dit onderscheid gemaakt werd.

UPDATE transactions SET amount = ABS(amount) WHERE income_item_id IS NOT NULL AND amount < 0;
