
CREATE TABLE cesession_session (
    id VARCHAR(32) NOT NULL PRIMARY KEY,
    data LONGTEXT,
    dateactive TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX cesession_session_dateactive_key ON cesession_session(dateactive);

DROP TRIGGER IF EXISTS cesession_session_before_update;
CREATE TRIGGER cesession_session_before_update BEFORE UPDATE ON cesession_session
FOR EACH ROW
BEGIN
    SET NEW.dateactive = CURRENT_TIMESTAMP;
END;

