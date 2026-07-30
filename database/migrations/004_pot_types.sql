-- Onderscheid tussen leefpotjes (dagelijkse uitgaven) en spaarpotjes
-- (geld dat opzij gezet wordt), voor de statistiekenpagina.

ALTER TABLE pots ADD COLUMN type TEXT NOT NULL DEFAULT 'leefpotje';
