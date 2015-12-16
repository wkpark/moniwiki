<?php
/**
 * A MySQL TitleIndexer
 *
 * @since 2015/05/17
 * @since 1.2.5
 * @author Won-Kyu Park <wkpark at kldp.org>
 * @license GPLv2
 */

class TitleIndexer_mysql {
    var $conn = NULL;
    var $host = 'localhost';
    var $db = 'moniwiki';
    var $passwd = '';
    var $charset = 'utf-8';
    var $use_regexp = false;
    var $pages_limit = 5000;

    function TitleIndexer_mysql($name = 'titleindex')
    {
        global $Config;

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

        // set default pages_limit
        $this->pages_limit = isset($Config['pages_limit']) ?
                $Config['pages_limit'] : $this->pages_limit;

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

    function mtime()
    {
        return time(); // FIXME
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
        // NOOP
    }

    function getPagesByIds($ids)
    {
        $this->_connect();

        // get total pages
        $res = mysql_query('SELECT COUNT(*) from `titleindex`');
        $row = mysql_fetch_array($res);
        mysql_free_result($res);
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
        mysql_free_result($res);

        return $selected;
    }


    function pageCount()
    {
        $this->_connect();
        $res = mysql_query('SELECT COUNT(*) from `titleindex` WHERE `is_deleted` = 0');
        $row = mysql_fetch_array($res);
        mysql_free_result($res);
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
        $pgname = @mysql_real_escape_string($pagename);
        $body = @mysql_real_escape_string($body);
        $res = mysql_query('SELECT `title`,_id from `titleindex` where `title` = \''.$pgname.'\'');
        $row = mysql_fetch_array($res);
        if ($row) {
            // update
            $id = $row['_id'];
            // already exists
            // change deleted status
            $res = mysql_query('UPDATE `titleindex` SET `is_deleted` = 0,'.
                    '`body` = \''.$body.'\', mtime = '.$mtime.' WHERE '.
                    '`_id` = '.$id);
            return $res;
        }

        $res = mysql_query('INSERT into `titleindex` (`title`, `body`, `created`, `mtime`) values ('.
                '\''.$pgname.'\','.'\''.$body.'\','.$mtime.','.$mtime.')');
        return $res;
    }

    function deletePage($pagename)
    {
        if (!isset($pagename[0])) return false;

        $this->_connect();
        $pgname = @mysql_real_escape_string($pagename);
        $res = mysql_query('SELECT title,_id from `titleindex` WHERE `title` = \''.$pgname.'\'');
        $row = mysql_fetch_array($res);
        mysql_free_result($res);
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

        $this->_connect();
        $pgname = @mysql_real_escape_string($oldname);

        $res = mysql_query('SELECT `title`,_id from `titleindex` WHERE `title` = \''.$pgname.'\'');
        $row = mysql_fetch_array($res);
        mysql_free_result($res);
        if (!$row)
            // page not exists
            return -1;
        $id = $row['_id'];

        $n_pgname = @mysql_real_escape_string($newname);
        $res = mysql_query('SELECT `title` from `titleindex` WHERE `title` = \''.$n_pgname.'\'');
        $row = mysql_fetch_array($res);
        mysql_free_result($res);
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

        $offset = 0;
        if (!empty($params['offset']) and
                is_numeric($params['offset']) and
                $params['offset'] > 0)
            $offset = $params['offset'];

        // Workaround bug: escape \n char
        $needle = str_replace("\x0a", "\x1a", $needle);

        $this->_connect();

        $pages = array();

        // MySQL REGEXP is not work with multibyte chars
        if (!$this->use_regexp) {
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

            $escaped = @mysql_real_escape_string($needle);
            $expr = $pre.$escaped.$suf;
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

            $escaped = @mysql_real_escape_string($needle);
            $expr = $pre.$escaped.$suf;
        }

        $query = 'SELECT `title` FROM `titleindex` WHERE `title` '.$search.' \''.$expr.'\'';
        if ($limit > 0)
            $query.= ' LIMIT '.intval($limit);
        if ($offset > 0)
            $query.= ' OFFSET '.$offset;

        $res = mysql_query($query);
        if ($res) {
            while ($rows = mysql_fetch_row($res)) {
                $pages[] = $rows[0];
            }
        }
        mysql_free_result($res);

        // return
        $info = array();
        $info['offset'] = $offset;
        $info['count'] = count($pages);
        if (isset($params['ret'])) $params['ret'] = $info;
        else if (isset($params['retval'])) $params['retval'] = $info;

        return $pages;
    }

    function getPages($params) {
        $offset = 0;
        if (!empty($params['offset']) and
                is_numeric($params['offset']) and
                $params['offset'] > 0)
            $offset = $params['offset'];

        // get pages_limit
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

            $against = @mysql_real_escape_string($against);
            $query = 'SELECT `title` FROM `titleindex` WHERE MATCH(`body`) '.
                'AGAINST(\''.$against.'\' IN BOOLEAN MODE)';

        } else {
            // FIXME
            $query = 'SELECT `title` FROM `titleindex` ORDER BY `title` DESC ';
        }
        if ($pages_limit > 0)
            $query .= ' LIMIT '.intval($pages_limit);

        if ($offset > 0)
            $query .= ' OFFSET '.intval($offset);

        $res = mysql_query($query);
        if ($res) {
            while ($rows = mysql_fetch_row($res)) {
                $pages[] = $rows[0];
            }
        }
        mysql_free_result($res);

        $info = array();
        $info['offset'] = $offset;
        $info['count'] = count($pages);
        if (isset($params['ret'])) $params['ret'] = $info;
        else if (isset($params['retval'])) $params['retval'] = $info;

        return $pages;
    }

    function close() {
        if ($this->conn)
            mysql_close($this->conn);
    }
}

// vim:et:sts=4:sw=4:
