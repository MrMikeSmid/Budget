-- Admin-vlag (app-breed, los van de "geen rollen binnen een huishouden"-
-- filosofie — dit is een operator/beheerdersrecht, geen huishouden-rol) en
-- een centrale settings-tabel voor dingen die je liever via de app instelt
-- dan via config.php op de server (bijv. SMTP-gegevens, zonder dat daar
-- FTP-toegang voor nodig is).

ALTER TABLE users ADD COLUMN is_admin INTEGER NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS settings (
    key   TEXT PRIMARY KEY,
    value TEXT
);

-- Eenmalige bootstrap: accounts die al bestonden vóór er een admin-paneel
-- en werkende e-mailverzending was, alsnog activeren zodat ze niet
-- vastzitten zolang er nog geen SMTP is ingesteld. Draait maar één keer,
-- ooit — nieuwe registraties na vandaag doorlopen gewoon de normale
-- verificatie-eis.
UPDATE users SET email_verified_at = datetime('now') WHERE email_verified_at IS NULL;

UPDATE users SET is_admin = 1 WHERE email = 'mike.smid@icloud.com';
