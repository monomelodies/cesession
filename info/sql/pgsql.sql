
CREATE TABLE cesession_session (
    id VARCHAR(32) NOT NULL PRIMARY KEY,
    data TEXT,
    dateactive TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW()
);
CREATE INDEX cesession_session_dateactive_key ON cesession_session(dateactive);

CREATE OR REPLACE FUNCTION cesession_session_before_update() RETURNS "trigger" AS $$
BEGIN
    NEW.dateactive := NOW();
    RETURN NEW;
END;
$$ LANGUAGE 'plpgsql';

CREATE TRIGGER cesession_session_before_update BEFORE UPDATE ON cesession_session FOR EACH ROW EXECUTE PROCEDURE cesession_session_before_update();

