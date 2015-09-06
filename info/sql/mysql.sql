
CREATE TABLE cesession_session (
    id VARCHAR(32) PRIMARY KEY,
    data LONGTEXT,
    dateactive TIMESTAMP NOT NULL DEFAULT NOW(),
    INDEX(dateactive)
) ENGINE='InnoDB' DEFAULT CHARSET='UTF8';

