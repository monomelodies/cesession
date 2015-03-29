
CREATE TABLE cesession (
    id VARCHAR(32) NOT NULL PRIMARY KEY,
    ip VARCHAR(39),
    ipforward VARCHAR(39),
    useragent VARCHAR(255),
    datecreated TIMESTAMP NOT NULL DEFAULT NOW(),
    dateactive DATETIME NOT NULL,
    checksum VARCHAR(32) NOT NULL,
    data LONGTEXT,
    status BIGINT UNSIGNED NOT NULL DEFAULT 0,
    INDEX(dateactive),
    INDEX(status)
) ENGINE='InnoDB' DEFAULT CHARSET='utf8';

