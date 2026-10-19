-- Pevně nastavený hráč: Martin Malý, ČADG číslo 2914 (interní id 1930), PDGA 113434
INSERT INTO settings (skey, sval) VALUES ('player_name','Martin Malý'),('player_pdga','113434'),('cadg_no','2914'),('cadg_user_id','1930'),('cadg_profile',''),('cadg_last','')
ON DUPLICATE KEY UPDATE sval = VALUES(sval);
