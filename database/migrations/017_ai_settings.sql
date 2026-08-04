-- AI-advies (Gemini): API key + systeemprompt. Gedeeld, net als de rest van
-- de instellingen — deze app kent geen scheiding per gebruiker. De key komt
-- hier terecht in plaats van in code/git, en het bestand zelf is al
-- afgeschermd (storage/.htaccess: Deny from all), net als de rest van de
-- database.
CREATE TABLE IF NOT EXISTS ai_settings (
    id             INTEGER PRIMARY KEY CHECK (id = 1),
    gemini_api_key TEXT,
    system_prompt  TEXT NOT NULL DEFAULT '',
    updated_at     TEXT NOT NULL DEFAULT (datetime('now'))
);

INSERT OR IGNORE INTO ai_settings (id, system_prompt) VALUES (
    1,
    'Je bent een persoonlijke financieel adviseur. Op basis van de onderstaande financiële gegevens van de gebruiker, geef kort en concreet advies (max 150 woorden) over uitgavenpatronen, spaarmogelijkheden en eventuele aandachtspunten. Wees vriendelijk maar direct, en vermijd algemene open deuren.'
);

-- Cache van het laatst gegenereerde advies per periode, zodat niet elke
-- dashboardweergave een nieuwe (betaalde) Gemini-aanroep kost.
CREATE TABLE IF NOT EXISTS ai_advice_cache (
    period_id    INTEGER PRIMARY KEY REFERENCES budget_periods(id) ON DELETE CASCADE,
    advice_text  TEXT NOT NULL,
    generated_at TEXT NOT NULL DEFAULT (datetime('now'))
);
