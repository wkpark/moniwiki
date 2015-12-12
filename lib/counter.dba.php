<?php
/**
 * Copyright 2003-2015 Won-Kyu Park <wkpark@gmail.com> all rights reserved.
 * distributable under GPLv2 see COPYING
 *
 * DBA Counter class for MoniWiki
 *
 * @since  2003/05/02
 * @author Won-Kyu Park <wkpark@gmail.com>
 * @license GPLv2
 *
 */

class Counter_dba extends Counter_base
{
    var $counter = null;
    var $data_dir;
    var $dba_type;
    var $owners;
    var $dbname = 'counter';

    function Counter_dba($conf, $dbname = 'counter')
    {
        if (!function_exists('dba_open'))
            return;

        $this->dba_type = $conf->dba_type;
        $this->data_dir = $conf->data_dir;
        $this->owners = is_array($conf->owners) ? $conf->owners : false;
        $this->dbname = $dbname;

        if (!file_exists($this->data_dir.'/'.$dbname.'.db')) {
            // create
            $db = dba_open($this->data_dir.'/'.$dbname.'.db', 'n', $this->dba_type);
            dba_close($db);
        }
        $this->counter = @dba_open($this->data_dir.'/'.$dbname.'.db', 'r', $this->dba_type);
    }

    function incCounter($pagename, $params = array())
    {
        if (is_array($this->owners) && in_array($params['id'], $this->owners))
            return;
        $count = dba_fetch($pagename, $this->counter);
        if (!$count) $count=0;
        $count++;

        // increase counter without locking
        $db = dba_open($this->data_dir.'/'.$this->dbname.'.db', 'w-', $this->dba_type);
        dba_replace($pagename, $count, $db);
        dba_close($db);
        return $count;
    }

    function pageCounter($pagename)
    {
        $count = dba_fetch($pagename, $this->counter);
        return $count ? $count: 0;
    }

    function getPageHits($perpage = 200, $page = 0, $cutoff = 0)
    {
        // get the first key
        $k = dba_firstkey($this->counter);

        // skip $perpage * $page
        if ($k !== false && $page > 0) {
            $i = $perpage * $page;
            while ($i > 0 && $k !== false) {
                $i--;
                $k = dba_nextkey($this->counter);
            }
        }
        $hits = array();
        $i = $perpage;

        // no limit
        if ($perpage == -1)
            $i = 2147483647; // PHP_INT_MAX
        for (; $k !== false && $i > 0; $k = dba_nextkey($this->counter)) {
            $v = dba_fetch($k, $this->counter);
            if ($v > $cutoff)
                $hits[$k] = $v;
            $i--;
        }
        return $hits;
    }

    function close()
    {
        if (is_resource($this->counter))
            dba_close($this->counter);
    }
}

// vim:et:sts=4:sw=4:
