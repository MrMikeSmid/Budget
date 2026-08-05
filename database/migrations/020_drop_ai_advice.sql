-- Het AI-advies (Gemini) is volledig verwijderd uit de app — inclusief de
-- API key en systeemprompt, die anders zonder gebruiksdoel zouden blijven
-- rondslingeren in de database.
DROP TABLE IF EXISTS ai_advice_cache;
DROP TABLE IF EXISTS ai_settings;
