-- Propojení s aplikací Moje Zdraví: stažené tréninky (posilovna, putting cvičení) a značka automatického splnění
CREATE TABLE IF NOT EXISTS health_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  src VARCHAR(12) NOT NULL,              -- g<id> = posilovna, d<id> = disc golf cvičení v Moje Zdraví
  kind VARCHAR(5) NOT NULL,              -- gym | drill
  d DATE NOT NULL,
  title VARCHAR(120) NOT NULL,
  variant VARCHAR(120) NULL,
  minutes INT NOT NULL DEFAULT 0,
  score DECIMAL(8,2) NULL,
  metric VARCHAR(80) NULL,
  unit VARCHAR(12) NULL,
  better VARCHAR(4) NULL,                -- up | down
  feeling TINYINT NULL,
  knee TINYINT NULL,
  sets_done INT NULL,
  volume_kg INT NULL,
  synced DATETIME NOT NULL,
  marked TINYINT(1) NOT NULL DEFAULT 0,  -- už jednou odškrtnuto v plánu (ruční vrácení se nepřepíše)
  UNIQUE KEY uq_src (src),
  INDEX (d)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

ALTER TABLE training_log ADD COLUMN IF NOT EXISTS src VARCHAR(12) NULL;
