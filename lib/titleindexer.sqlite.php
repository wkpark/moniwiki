<?php
/**
 * A SQLite3 TitleIndexer
 *
 * @since 2015/05/17
 * @since 1.2.5
 * @author Won-Kyu Park <wkpark@gmail.com>
 * @license GPLv2
 */

class TitleIndexer_sqlite {
    var $db = NULL;
    var $dbname = '';
    var $use_regexp = null;
    var $pages_limit = 5000;

    function TitleIndexer_sqlite($name = 'titleindex_sqlite')
    {
        global $Config;

        // setup sqlite database filename
        $this->dbname = !empty($Config['sqlite_dbname']) ? $Config['sqlite_dbname'] :
            $Config['data_dir'].'/'.$name.'.db';

        // set default pages_limit
        $this->pages_limit = isset($Config['pages_limit']) ?
                $Config['pages_limit'] : $this->pages_limit;

        register_shutdown_function(array(&$this, 'close'));
    }

    function _connect()
    {
        if ($this->db)
            return;

        $db = new SQLite3($this->dbname);
        if (!is_object($db)) {
            trigger_error("Fail to open SQLite3 DB");
            return;
        }
        $this->db = $db;

        // load REGEXP extension
        // You have to check the 'sqlite3.extension_dir' option
        // in the php.ini or conf.d/sqlte3.ini file like as following:
        //
        // sqlite3.extension_dir=/usr/lib/sqlite3/
        //
        // but the current sqlite3-pcre does not support UTF8
        // if you want to use REGEXP with UTF-8 support
        // you have to enable UTF8 support by calling pcre_compile() with the PCRE_UTF8 option
        // in the sqlite3-pcre source code found at
        // http://git.altlinux.org/people/at/packages/?p=sqlite3-pcre.git
        //
        // or you can use sqlite3::createFunction()
        //
        if ($this->use_regexp != null) {
            $this->db->createFunction('regexp', array(&$this, '_php_regexp'), 2);
            $this->use_regexp = true;
        }
        if ($this->use_regexp == null && $this->db->loadExtension('pcre.so')) {
            $this->use_regexp = true;
        }
    }

    function _php_regexp($str, $expr)
    {
        if (preg_match('/'.$expr.'/', $str))
            return true;

        return false;
    }

    function mtime()
    {
        return @filemtime($this->dbname);
    }

    /**
     * update selected page
     *
     * @access public
     */
    function update($pagename)
    {
        return $this->addPage($pagename);
    }

    function init()
    {
        // NOOP
    }

    function init_module()
    {
        if (file_exists($this->dbname) && filesize($this->dbname) > 0)
            return;

        // init SQLite3 database.
        require_once(dirname(__FILE__).'/../tools/utils.php');
        
        $schema = make_sql(dirname(__FILE__).'/../lib/schemas/titleindex.sql', '', 'sqlite');
        $lines = explode("\n", $schema);
        foreach ($lines as $i=>$line) {
            $line = rtrim($line);
            if (isset($line[2]) && $line[2] == ' ' && $line[0] == '-' && $line[1] == '-')
                unset($lines[$i]);
            else
                $lines[$i] = $line;
        }
        $striped = implode("\n", $lines);
        $lines = explode(";\n", $striped);

        $this->_connect();
        foreach ($lines as $q) {
            // ignore DROP statement
            if (preg_match('@^drop\s@i', $q)) continue;
            $ret = $this->db->exec($q);
            if (!$ret) {
                trigger_error(sprintf(_("Fail to init SQLite3 with '%s' statement."), $q));
                return;
            }
        }
    }

    function getPagesByIds($ids)
    {
        $this->_connect();

        // get total pages
        $res = $this->db->query('SELECT COUNT(*) from `titleindex`');
        $row = $res->fetchArray();
        if (!$row)
            return array();

        $selected = array();
        foreach ($ids as $id) {
            $id = intval($id);
            $query = 'SELECT `title` from `titleindex` WHERE `is_deleted` = 0 LIMIT 1 OFFSET '.$id;
            $res = $this->db->query($query);
            $row = $res->fetchArray();
            if ($row)
                $selected[] = $row['title'];
        }

        return $selected;
    }


    function pageCount()
    {
        $this->_connect();
        $res = $this->db->query('SELECT COUNT(*) from `titleindex` WHERE `is_deleted` = 0');
        $row = $res->fetchArray();
        if ($row)
            return $row[0];
        return 0;
    }

    function sort()
    {
        // noop
    }

    function addPage($pagename)
    {
        global $DBInfo;

        if (!isset($pagename[0])) return false;

        $page = $DBInfo->getPage($pagename);
        $body = $page->_get_raw_body();
        $mtime = $page->mtime();

        $this->_connect();

        $query = $this->db->prepare('SELECT `title`,_id from `titleindex` where `title` = ?');
        $query->bindValue(1, $pagename);
        $res = $query->execute();
        $row = $res->fetchArray();
        if ($row) {
            // update
            $id = $row['_id'];

            // already exists
            // change deleted status
            $query = $this->db->prepare('UPDATE `titleindex` SET `is_deleted` = 0,'.
                    '`body` = ?, mtime = ? WHERE `_id` = ?');
            $query->bindValue(1, $body);
            $query->bindValue(2, $mtime);
            $query->bindValue(3, $id);
            $res = $query->execute();
            return $res;
        }

        // insert
        $query = $this->db->prepare('INSERT into `titleindex` (`title`, `body`, `created`, `mtime`) '.
                'values (?,?,?,?)');
        $query->bindValue(1, $pagename);
        $query->bindValue(3, $body);
        $query->bindValue(3, $mtime);
        $query->bindValue(4, $mtime);
        $res = $query->execute();
        return $res;
    }

    function deletePage($pagename)
    {
        if (!isset($pagename[0])) return false;

        $this->_connect();

        $query = $this->db->prepare('SELECT title,_id from `titleindex` WHERE `title` = ?');
        $query->bindValue(1, $pagename);
        $res = $query->execute();
        $row = $res->fetchArray();
        if (!$row)
            // not exists
            return false;

        $id = $row['_id'];
        $res = $this->db->query('UPDATE `titleindex` SET `is_deleted` = 1, mtime = '.time().' WHERE '.
                '`_id` = '.$id);
        return $res;
    }

    function renamePage($oldname, $newname)
    {
        if (!isset($oldname[0])) return false;
        if (!isset($newname[0])) return false;

        $this->_connect();

        $query = $this->db->prepare('SELECT title,_id from `titleindex` WHERE `title` = ?');
        $query->bindValue(1, $oldname);
        $res = $query->execute();
        $row = $res->fetchArray();
        if (!$row)
            // page not exists
            return -1;
        $id = $row['_id'];

        $query->bindValue(1, $newname);
        $res = $query->execute();
        $row = $res->fetchArray();
        if ($row)
            // new page exists
            return -2;

        $query = $this->db->prepare('UPDATE `titleindex` SET `title` = ?,'.
                '`mtime` = ? WHERE `_id` = ?');
        $query->bindValue(1, $newname);
        $query->bindValue(2, time());
        $query->bindValue(3, $id);
        $res = $query->execute();
        return $res;
    }

    function getLikePages($needle, $limit = 100, $params = array())
    {
        if (!isset($needle[0])) return false; // null needle

        $offset = 0;
        if (!empty($params['offset']) and
                is_numeric($params['offset']) and
                $params['offset'] > 0)
            $offset = $params['offset'];

        // Workaround bug: escape \n char
        $needle = str_replace("\x0a", "\x1a", $needle);

        $this->_connect();

        $pages = array();

        // use_regexp ?
        if ($this->use_regexp !== true) {
            $search = 'LIKE';

            $pre = '%';
            $suf = '%';
            if ($needle[0] == '^') {
                $pre = '';
                $needle = substr($needle, 1);
            }
            if (substr($needle, -1) == '$') {
                $suf = '';
                $needle = substr($needle, 0, -1);
            }

            $expr = $pre.$needle.$suf;
        } else {
            $search = 'REGEXP';

            $pre = '';
            $suf = '';
            if ($needle[0] == '^') {
                $pre = '^';
                $needle = substr($needle, 1);
            }
            if (substr($needle, -1) == '$') {
                $suf = '$';
                $needle = substr($needle, 0, -1);
            }

            $expr = $pre.$needle.$suf;
        }

        $q = 'SELECT `title` FROM `titleindex` WHERE `title` '.$search.' ?';
        if ($limit > 0)
            $q.= ' LIMIT '.intval($limit);
        if ($offset > 0)
            $q.= ' OFFSET '.$offset;

        $query = $this->db->prepare($q);
        $query->bindValue(1, $expr);
        $res = $query->execute();
        if ($res) {
            while ($rows = $res->fetchArray()) {
                $pages[] = $rows['title'];
            }
        }

        // return
        $info = array();
        $info['offset'] = $offset;
        $info['count'] = count($pages);
        if (isset($params['ret'])) $params['ret'] = $info;
        else if (isset($params['retval'])) $params['retval'] = $info;

        return $pages;
    }

    function getPages($params)
    {
        global $Config;

        $offset = 0;
        if (!empty($params['offset']) and
                is_numeric($params['offset']) and
                $params['offset'] > 0)
            $offset = $params['offset'];

        // set pages_limit
        $pages_limit = $this->pages_limit;

        $total = $this->pageCount();
        $size = $pages_limit;
        if (!empty($params['all'])) $size = $total;

        // make query string
        $against = '';
        $mode = '';
        if (!empty($params['search'])) {
            foreach ($params['excl'] as $excl) {
                if (strpos($excl, ' ') !== false)
                    $against.= ' -('.$excl.')';
                else
                    $against.= ' -'.$excl;
            }

            foreach ($params['incl'] as $incl) {
                if (strpos($incl, ' ') !== false)
                    $against.= ' +('.$incl.')';
                else
                    $against.= ' +'.$incl;
            }
        }

        if (!empty($against)) {
            $q = 'SELECT `title` FROM `titleindex` WHERE MATCH(`body`) '.
                'AGAINST(? IN BOOLEAN MODE)';
        } else {
            $q = 'SELECT `title` FROM `titleindex` ORDER BY `title` DESC ';
        }

        if ($pages_limit > 0)
            $q .= ' LIMIT '.intval($pages_limit);

        if ($offset > 0)
            $q .= ' OFFSET '.intval($offset);

        $query = $this->db->prepare($q);
        if (!empty($against))
            $query->bindValue(1, $against);
        $res = $query->execute();
        if ($res) {
            while ($rows = $res->fetchArray()) {
                $pages[] = $rows['title'];
            }
        }

        // return
        $info = array();
        $info['offset'] = $offset;
        $info['count'] = count($pages);
        if (isset($params['ret'])) $params['ret'] = $info;
        else if (isset($params['retval'])) $params['retval'] = $info;

        return $pages;
    }

    function close()
    {
        if ($this->db)
            $this->db->close();
    }
}

// vim:et:sts=4:sw=4:
