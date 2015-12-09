<?php
/**
 * MetaDB_dba class for MoniWiki
 *
 * @since  2003/04/15
 * @author wkpark@kldp.org
 * @license GPLv2
 *
 */

class MetaDB_dba extends MetaDB {
    var $metadb;
    var $aux = array();

    function MetaDB_dba($file, $type = 'db4') {
        if (function_exists('dba_open'))
            $this->metadb = @dba_open($file.".cache", 'r', $type);
    }

    function close() {
        dba_close($this->metadb);
    }

    function attachDB($db) {
        $this->aux = $db;
    }

    function getSisterSites($pagename, $mode = 1) {
        $norm = preg_replace('/\s+/', '', $pagename);
        if ($norm == $pagename) {
            $nodb = !dba_exists($pagename, $this->metadb);
        } else {
            $nodb = !dba_exists($pagename, $this->metadb) && !dba_exists($norm, $this->metadb);
        }
        if (!$this->aux->hasPage($pagename) and $nodb) {
            if ($mode) return '';
            return false;
        }
        if (!$mode) return true;
        $sisters = dba_fetch($pagename, $this->metadb);
        if ($sisters == null)
            $sisters = dba_fetch($norm, $this->metadb);
        $addons = $this->aux->getSisterSites($pagename, $mode);

        $ret = '';
        if ($sisters)
            $ret='[wiki:'.str_replace(' ', ":$pagename]\n[wiki:", $sisters).":$pagename]";
        $pagename = _preg_search_escape($pagename);
        if ($addons) $ret = rtrim($addons."\n".$ret);

        if ($mode == 1 and strlen($ret) > 80) $ret = "[wiki:TwinPages:$pagename]";
        return preg_replace("/((:[^\s]+){2})(\:$pagename)/", "\\1", $ret);
    }

    function getTwinPages($pagename, $mode = 1) {
        $norm = preg_replace('/\s+/', '', $pagename);
        if ($norm == $pagename) {
            $nodb = !dba_exists($pagename, $this->metadb);
        } else {
            $nodb = !dba_exists($pagename, $this->metadb) && !dba_exists($norm, $this->metadb);
        }
        if (!$this->aux->hasPage($pagename) and $nodb) {
            if ($mode) return array();
            return false;
        }
        if (!$mode) return true;

        $twins = dba_fetch($pagename, $this->metadb);
        if ($twins == null)
            $twins = dba_fetch($norm, $this->metadb);
        $addons = $this->aux->getTwinPages($pagename, $mode);
        $ret = array();
        if ($twins) {
            $ret = "[wiki:".str_replace(' ',":$pagename] [wiki:",$twins). ":$pagename]";

            $pagename = _preg_search_escape($pagename);
            $ret = preg_replace("/((:[^\s]+){2})(\:$pagename)/","\\1",$ret);
            $ret = explode(' ', $ret);
        }

        if ($addons) $ret = array_merge($addons, $ret);
        if (sizeof($ret) > 8) {
            if ($mode == 1) return array("TwinPages:$pagename");
            $ret = array_map(create_function('$a', 'return " * $a";'), $ret);
        }

        return $ret;
    }

    function hasPage($pagename) {
        if (dba_exists($pagename, $this->metadb)) return true;
        return false;
    }

    function getAllPages() {
        if ($this->keys) return $this->keys;
        for ($key = dba_firstkey($this->metadb);
                $key !== false;
                $key = dba_nextkey($this->metadb)) {
            $keys[] = $key;
        }
        $this->keys = $keys;
        return $keys;
    }

    function getLikePages($needle, $count = 500) {
        $keys = array();
        if (!$needle) return $keys;
        for ($key = dba_firstkey($this->metadb);
                $key !== false;
                $key = dba_nextkey($this->metadb)) {
            if (preg_match("/($needle)/i", $key)) {
                $keys[] = $key; $count--;
            }
            if ($count < 0) break;
        }
        return $keys;
    }
}

// vim:et:sts=4:sw=4:
