CREATE TABLE IF NOT EXISTS inquiry_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  inquiry_id INT NOT NULL,
  disc_id INT NOT NULL,
  INDEX (inquiry_id),
  CONSTRAINT fk_inq_item FOREIGN KEY (inquiry_id) REFERENCES inquiries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO inquiry_items (inquiry_id, disc_id)
  SELECT i.id, i.disc_id FROM inquiries i WHERE i.disc_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM inquiry_items x WHERE x.inquiry_id = i.id);

CREATE TABLE IF NOT EXISTS push_subs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  endpoint VARCHAR(600) NOT NULL,
  endpoint_hash CHAR(40) NOT NULL UNIQUE,
  name VARCHAR(80) NOT NULL DEFAULT 'Zařízení',
  created DATETIME NOT NULL,
  last_ok DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
