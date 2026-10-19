-- Disc golf cvičení žijí v téhle aplikaci (definice v inc/drills.php); výsledky se posílají do Moje Zdraví a historie ze Zdraví se sem jednorázově načte.
CREATE TABLE IF NOT EXISTS drill_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  drill VARCHAR(30) NOT NULL,
  variant VARCHAR(20) NOT NULL DEFAULT 'normal',
  d DATE NOT NULL,
  minutes INT NOT NULL DEFAULT 0,
  score DECIMAL(7,2) NOT NULL DEFAULT 0,
  summary MEDIUMTEXT NULL,
  events MEDIUMTEXT NULL,
  src VARCHAR(8) NOT NULL DEFAULT 'dg',      -- dg = odehráno tady, zdravi = načteno z Moje Zdraví
  zdravi_id INT NULL,                         -- id řádku v Moje Zdraví (jen u src = zdravi)
  sent TINYINT(1) NOT NULL DEFAULT 0,         -- 1 = už odesláno do Moje Zdraví
  created DATETIME NOT NULL,
  INDEX (drill, d),
  UNIQUE KEY uq_zdravi (zdravi_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- cvičení se dřív (v7) stahovala do health_sessions, teď mají vlastní tabulku
DELETE FROM health_sessions WHERE kind = 'drill';
