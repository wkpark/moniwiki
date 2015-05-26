<?php
/**
 * A MySQL TitleIndexer
 *
 * @since 2015/05/17
 * @author Won-Kyu Park <wkpark at kldp.org>
 * @license GPLv2
 */

class TitleIndexer_mysql {
    var $text_dir = '';
    var $conn = NULL;
    var $host = 'localhost';
    var $db = 'moniwiki';
    var $passwd = '';
    var $charset = 'utf-8';

    function TitleIndexer_mysql($name = 'titleindex')
    {
        global $Config;

        $this->text_dir = $Config['text_dir'];
        // setup mysql config
        if (!empty($Config['config_mysql']) and
                file_exists('config/mysql.'.$Config['config_mysql'].'.php')) {

            $conf = _load_php_vars('config/mysql.'.$Config['config_mysql'].'.php');
            $this->host = !empty($conf['host']) ? $conf['host'] : 'localhost';
            $this->db = !empty($conf['dbname']) ? $conf['dbname'] : 'moniwiki';
            $this->user = !empty($conf['user']) ? $conf['user'] : 'moniwiki';
            $this->passwd = !empty($conf['passwd']) ? $conf['passwd'] : '';
        } else {
            $host = !empty($Config['mysql_host']) ? $Config['mysql_host'] : 'localhost';
            $this->host = $host;
            $this->db = !empty($Config['mysql_dbname']) ? $Config['mysql_dbname'] : 'moniwiki';
            $this->user = !empty($Config['mysql_user']) ? $Config['mysql_user'] : 'moniwiki';
            $this->passwd = !empty($Config['mysql_passwd']) ? $Config['mysql_passwd'] : '';
        }

        $this->charset = strtolower($Config['charset']);
        register_shutdown_function(array(&$this,'close'));
    }

    function _connect()
    {
        if ($this->conn)
            return;

        $charset = str_replace('-', '', $this->charset);
        $conn = @mysql_connect($this->host, $this->user, $this->passwd);
        if (!is_resource($conn)) {
            trigger_error("Fail to connect DB");
            return;
        }
        $this->conn = $conn;
        mysql_select_db($this->db);
        @mysql_query('set names '.$charset);
    }

    /**
     * update selected page
     *
     * @access public
     */
    function update($pagename)
    {
        // NOOP
        return true;
    }

    function init()
    {
        // NOOP
    }

    function init_module()
    {
        global $DBInfo;

        // exclusive lock to prevent multiple init() calls
        $eslock = $DBInfo->vartmp_dir.'/mysql.lock';
        $lock = @fopen($eslock, 'x');
        if (is_resource($lock)) {
            if (flock($lock, LOCK_EX)) {
                $this->init();
                flock($lock, LOCK_UN);
            }
            fclose($lock);
            unlink($eslock);
        }
    }

    function getPagesByIds($ids)
    {
        $this->_connect();

        // get total pages
        $res = mysql_query('SELECT COUNT(*) from `titleindex`');
        $row = mysql_fetch_array($res);
        if (!$row)
            return array();

        $total = $row[0];

        // $ids not used FIXME
        $num = sizeof($ids);

        $selected = array();
        while (sizeof($selected) < $num) {
            $query = 'select t.title from titleindex as t JOIN(select ceil(rand() * '.$total.') as id) as r2'.
                    ' where r2.id = t._id and t.is_deleted = 0';
            $res = mysql_query($query);
            $row = mysql_fetch_array($res);
            if ($row)
                $selected[] = $row[0];
        }

        return $selected;
    }


    function pageCount()
    {
        $this->_connect();
        $res = mysql_query('SELECT COUNT(*) from `titleindex` WHERE `is_deleted` = 0');
        $row = mysql_fetch_array($res);
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
        $mtime = $page->mtime();
        $pgname = mysql_escape_string($pagename);
        $this->_connect();
        $res = mysql_query('SELECT `title`,_id from `titleindex` where `title` = \''.$pgname.'\'');
        $row = mysql_fetch_array($res);
        if ($row) {
            $id = $row['_id'];
            // already exists
            // change deleted status
            $res = mysql_query('UPDATE `titleindex` SET `is_deleted` = 0, mtime = '.time().' WHERE '.
                    '`_id` = '.$id);
            return $res;
        }

        $res = mysql_query('INSERT into `titleindex` (`title`, `created`, `mtime`) values (\''.
                $pgname.'\', '.$mtime.','.$mtime.')');
        return $res;
    }

    function deletePage($pagename)
    {
        global $DBInfo;

        if (!isset($pagename[0])) return false;

        $pgname = mysql_escape_string($pagename);

        $this->_connect();
        $res = mysql_query('SELECT title,_id from `titleindex` WHERE `title` = \''.$pgname.'\'');
        $row = mysql_fetch_array($res);
        if (!$row)
            // not exists
            return false;
        $id = $row['_id'];

        $res = mysql_query('UPDATE `titleindex` SET `is_deleted` = 1, mtime = '.time().' WHERE '.
                '`_id` = '.$id);
        return $res;
    }

    function renamePage($oldname, $newname)
    {
        if (!isset($oldname[0])) return false;
        if (!isset($newname[0])) return false;

        $pgname = mysql_escape_string($oldname);

        $this->_connect();
        $res = mysql_query('SELECT `title`,_id from `titleindex` WHERE `title` = \''.$pgname.'\'');
        $row = mysql_fetch_array($res);
        if (!$row)
            // page not exists
            return -1;
        $id = $row['_id'];

        $n_pgname = mysql_escape_string($newname);
        $res = mysql_query('SELECT `title` from `titleindex` WHERE `title` = \''.$n_pgname.'\'');
        $row = mysql_fetch_array($res);
        if ($row)
            // new page exists
            return -2;

        $res = mysql_query('UPDATE `titleindex` SET `title` = \''.$n_pgname.'\','.
                '`mtime` = '.time().' WHERE `_id` = '.$id);
        return $res;
    }

    function getLikePages($needle, $limit = 100, $params = array())
    {
        if (!isset($needle[0])) return false; // null needle

        // Workaround bug: escape \n char
        $needle = str_replace("\x0a", "\x1a", $needle);

        $pages = array();

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

        // FIXME
        $query = 'SELECT `title` FROM `titleindex` WHERE `title` LIKE \''.$pre.$needle.$suf.'\'';
        if ($limit > 0)
                $query.= ' LIMIT '.intval($limit);

        $this->_connect();
        $res = mysql_query($query);
        if ($res) {
            while ($rows = mysql_fetch_row($res)) {
                $pages[] = $rows[0];
            }
        }

        return $pages;
    }

    function getPages($params) {
        global $DBInfo;

        $offset = 0;
        if (!empty($params['offset']) and
                is_numeric($params['offset']) and
                $params['offset'] > 0)
            $offset = $params['offset'];

        // set page_limit
        $pages_limit = isset($DBInfo->pages_limit) ?
                $DBInfo->pages_limit : 5000; // 5000 pages

        $total = $this->pageCount();
        $size = $pages_limit;
        if (!empty($params['all'])) $size = $total;

        // FIXME
        $query = 'SELECT `title` FROM `titleindex` ORDER BY `title` DESC ';
        if ($pages_limit > 0)
                ' LIMIT '.intval($pages_limit);

        if ($offset > 0)
                ' OFFSET '.intval($offset);

        $this->_connect();
        $res = mysql_query($query);
        if ($res) {
            while ($rows = mysql_fetch_row($res)) {
                $pages[] = $rows[0];
            }
        }

        $info['offset'] = $offset;
        $info['count'] = count($pages);
        if (isset($params['ret'])) $params['ret'] = $info;

        return $pages;
    }

    function close() {
        if ($this->conn)
            mysql_close($this->conn);
    }
}

// vim:et:sts=4:sw=4:
