-- Modelnaam configureerbaar maken i.p.v. hardcoded in de code: Google
-- hernoemt/vervangt Gemini-modelnamen wel eens, en dan hoeft dit niet meer
-- via een codewijziging + deploy opgelost te worden.
ALTER TABLE ai_settings ADD COLUMN gemini_model TEXT NOT NULL DEFAULT 'gemini-2.5-flash';
