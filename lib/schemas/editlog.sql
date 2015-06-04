-- MYSQL
-- MYSQL titleindex structure
--

DROP TABLE IF EXISTS editlog;

-- MYSQL -- set names utf8;
CREATE TABLE editlog (
	_id		INT(11) PRIMARY KEY AUTO_INCREMENT,
	page_id		INT(11) NOT NULL,
	`timestamp`	INT(11) NOT NULL,
	user		varchar(30) DEFAULT NULL,
	ip		varchar(15) DEFAULT NULL,
	ip2long		INT(11) DEFAULT NULL,
	ipall		varchar(255) DEFAULT NULL,
	comment		TEXT DEFAULT '',
	action		INT(2) DEFAULT 0
);

CREATE INDEX editlog_timestamp_index ON editlog(`timestamp`);
