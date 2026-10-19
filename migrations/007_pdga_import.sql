-- Import z PDGA Live: id turnaje a výsledky po jamkách
ALTER TABLE events ADD COLUMN IF NOT EXISTS pdga_id INT NULL;
ALTER TABLE events ADD INDEX IF NOT EXISTS idx_pdga_id (pdga_id);
ALTER TABLE rounds ADD COLUMN IF NOT EXISTS hole_scores VARCHAR(160) NULL;
ALTER TABLE rounds ADD COLUMN IF NOT EXISTS hole_pars VARCHAR(160) NULL;
