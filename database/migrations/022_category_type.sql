-- Elke categorie is óf een inkomsten- óf een uitgaven-categorie — bepaalt
-- welke van de twee (inkomsten/lasten) op de categoriedetailpagina getoond
-- wordt. Bestaande categorieën worden als "uitgaven" aangenomen (de meest
-- voorkomende soort).
ALTER TABLE categories ADD COLUMN type TEXT NOT NULL DEFAULT 'uitgaven';
