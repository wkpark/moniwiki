<?php
/**
 * MetaDB_text class for MoniWiki
 *
 * @since  2003/04/15
 * @author wkpark@kldp.org
 * @license GPLv2
 *
 */

class MetaDB_text extends MetaDB {
    var $alias; // alias metadata
    var $db; // extra aliases from the AliasPageNames

    function MetaDB_text($db = array()) {
        // open aliasname metadata
        $this->alias = new Cache_Text('aliasname');
        $this->db = $db;
    }

    function hasPage($pagename) {
        if ($this->alias->exists($pagename) or
                !empty($this->db[$pagename])) return true;
        return false;
    }

    function getTwinPages($pagename, $mode = 1) {
        if (!$this->alias->exists($pagename) and
                empty($this->db[$pagename])) {
            if (!empty($mode)) return array();
            return false;
        }
        if (empty($mode)) return true;
        $twins = $this->alias->fetch($pagename);
        if (empty($twins))
            $twins = $this->db[$pagename];
        else if (!empty($this->db[$pagename]))
            $twins = array_merge($twins, $this->db[$pagename]);

        // wiki:Hello World -> wiki:"Hello World"
        $twins = preg_replace_callback('@^((?:[^\s]{2,}:)*)(.*)$@',
                create_function('$m',
                    'return \'[wiki:\'.$m[1].\'"\'.$m[2].\'"]\';'), $twins);
        return $twins;
    }

    function getSisterSites($pagename, $mode = 1) {
        $ret = $this->getTwinPages($pagename, $mode);

        if (is_array($ret))
            return implode("\n", $ret);

        return $ret;
    }
}

// vim:et:sts=4:sw=4:
