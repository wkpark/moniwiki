-- MYSQL
-- a simple wiki schema for MySQL
--

DROP TABLE IF EXISTS documents;

-- MYSQL -- set names utf8;
CREATE TABLE documents (
	_id	INT(11) PRIMARY KEY AUTO_INCREMENT, 
	title	VARCHAR(255) BINARY NOT NULL,
	body	MEDIUMTEXT NOT NULL,
	`mtime`	INT(11) NOT NULL
);

CREATE INDEX title_index ON documents(title);
