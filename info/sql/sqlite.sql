
CREATE TABLE cesession_session (
    id VARCHAR(32) NOT NULL PRIMARY KEY,
    data LONGTEXT,
    dateactive TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX cesession_session_dateactive_key ON cesession_session(dateactive);

DROP TRIGGER IF EXISTS cesession_session_after_update;
CREATE TRIGGER cesession_session_after_update AFTER UPDATE ON cesession_session
FOR EACH ROW
BEGIN
    UPDATE cesession_session SET dateactive = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;

