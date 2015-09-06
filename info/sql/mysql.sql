
CREATE TABLE cesession_session (
    id VARCHAR(32) PRIMARY KEY,
    data LONGTEXT,
    dateactive TIMESTAMP NOT NULL DEFAULT NOW(),
    INDEX(dateactive)
) ENGINE='InnoDB' DEFAULT CHARSET='UTF8';

DROP TRIGGER IF EXISTS cesession_session_before_update;
DELIMITER $$
CREATE TRIGGER cesession_session_before_update BEFORE UPDATE ON cesession_session
FOR EACH ROW
BEGIN
    SET NEW.dateactive = NOW();
END;
$$
DELIMITER ;

