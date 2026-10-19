ALTER TABLE discs ADD COLUMN IF NOT EXISTS training TINYINT(1) NOT NULL DEFAULT 0 AFTER in_bag;
ALTER TABLE discs ADD COLUMN IF NOT EXISTS testing TINYINT(1) NOT NULL DEFAULT 0 AFTER training;
UPDATE discs SET training = 1 WHERE in_bag = 0 AND for_sale = 0 AND is_sold = 0 AND is_lost = 0;
UPDATE discs SET in_bag = 0, for_sale = 0 WHERE is_sold = 1 OR is_lost = 1;
