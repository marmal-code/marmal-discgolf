-- Import z Českého discgolfu (ČADG / iDG live scoring): identifikátor turnaje
ALTER TABLE events ADD COLUMN IF NOT EXISTS cadg_id VARCHAR(40) NULL;
ALTER TABLE events ADD INDEX IF NOT EXISTS idx_cadg_id (cadg_id);
