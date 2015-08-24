
CREATE TABLE cesession_session (
    id VARCHAR(32) PRIMARY KEY,
    auth BIGINT UNSIGNED,
    data LONGTEXT,
    dateactive TIMESTAMP NOT NULL DEFAULT NOW() ON UPDATE CURRENT_TIMESTAMP,
    INDEX(auth),
    INDEX(dateactive),
    CONSTRAINT FOREIGN KEY (auth) REFERENCES auth (id) ON DELETE CASCADE
) ENGINE='InnoDB' DEFAULT CHARSET='UTF8';

