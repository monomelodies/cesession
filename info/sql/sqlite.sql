
CREATE TABLE cesession (
    id VARCHAR(32) NOT NULL PRIMARY KEY,
    ip VARCHAR(39),
    ipforward VARCHAR(39),
    useragent VARCHAR(255),
    datecreated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    dateactive DATETIME NOT NULL,
    checksum VARCHAR(32) NOT NULL,
    data LONGTEXT,
    status INTEGER NOT NULL DEFAULT 0
    INDEX(dateactive)
);
CREATE INDEX cesession_dateactive_key ON cesession(dateactive);
CREATE INDEX cesession_status_key ON cesession(status);

