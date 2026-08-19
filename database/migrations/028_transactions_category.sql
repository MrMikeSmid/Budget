-- Eigen categorie op een kasstroommutatie: tot nu toe kwam de categorie
-- alleen indirect mee via een gekoppelde vaste last/inkomst, maar een
-- losse (niet-gekoppelde) mutatie kon helemaal geen categorie krijgen.
ALTER TABLE transactions ADD COLUMN category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL;
