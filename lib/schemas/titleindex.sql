-- MYSQL
-- MYSQL titleindex structure
--

DROP TABLE IF EXISTS titleindex;

-- MYSQL -- set names utf8;
CREATE TABLE titleindex (
	_id		INT(11) PRIMARY KEY AUTO_INCREMENT,
	title		VARCHAR(255) BINARY NOT NULL,
	body		MEDIUMTEXT DEFAULT '',
	`mtime`		INT(11) NOT NULL,
	is_redirect	INT(1) DEFAULT 0,
	is_deleted	INT(1) DEFAULT 0,
	created		INT(11) DEFAULT 0
);

CREATE INDEX titleindex_index ON titleindex(title);
