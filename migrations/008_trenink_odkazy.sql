-- Trénink (plán, fáze, položky, záznamy) a odkazy na Přehledu
CREATE TABLE IF NOT EXISTS training_plans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  note TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS training_phases (
  id INT AUTO_INCREMENT PRIMARY KEY,
  plan_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  descr TEXT NULL,
  sort INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_phase_plan FOREIGN KEY (plan_id) REFERENCES training_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- days = čísla dnů v týdnu 1 (po) – 7 (ne), oddělená čárkou
CREATE TABLE IF NOT EXISTS training_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  phase_id INT NOT NULL,
  days VARCHAR(20) NOT NULL DEFAULT '1,2,3,4,5,6,7',
  title VARCHAR(200) NOT NULL,
  minutes INT NULL,
  kind VARCHAR(12) NOT NULL DEFAULT 'other',
  details TEXT NULL,
  sort INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_item_phase FOREIGN KEY (phase_id) REFERENCES training_phases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- status: done | skip ; reason (u skip): nemoc | zraneni | jine ; item_id NULL = vlastní trénink mimo plán
CREATE TABLE IF NOT EXISTS training_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tdate DATE NOT NULL,
  item_id INT NULL,
  title VARCHAR(200) NULL,
  kind VARCHAR(12) NULL,
  minutes INT NULL,
  status VARCHAR(6) NOT NULL,
  reason VARCHAR(10) NULL,
  note VARCHAR(300) NULL,
  created DATETIME NOT NULL,
  UNIQUE KEY uq_day_item (tdate, item_id),
  INDEX (tdate),
  CONSTRAINT fk_log_item FOREIGN KEY (item_id) REFERENCES training_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS links (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(80) NOT NULL,
  url VARCHAR(500) NOT NULL,
  sort INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

INSERT INTO links (title, url, sort) SELECT 'Tagovačka', 'https://tagovacka.cz/home', 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM links WHERE url = 'https://tagovacka.cz/home');
INSERT INTO links (title, url, sort) SELECT 'Discgolf Kladno – členové', 'https://members.discgolfkladno.cz/', 2 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM links WHERE url = 'https://members.discgolfkladno.cz/');
INSERT INTO links (title, url, sort) SELECT 'Můj profil PDGA', 'https://www.pdga.com/player/113434', 3 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM links WHERE url = 'https://www.pdga.com/player/113434');
INSERT INTO links (title, url, sort) SELECT 'Český discgolf (IDG)', 'https://ceskydiscgolf.cz/idg', 4 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM links WHERE url = 'https://ceskydiscgolf.cz/idg');
