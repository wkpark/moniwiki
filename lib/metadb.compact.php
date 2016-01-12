<?php
/**
 * MetaDB_compact class for MoniWiki
 *
 * @since  2003/04/15
 * @modified  2015/11/28
 * @author wkpark@kldp.org
 * @license GPLv2
 *
 */

define('INTERWIKI_NONE', 0);
define('INTERWIKI_KOWIKI', 1);
define('INTERWIKI_RIGVEDA', 2);
define('INTERWIKI_NAMU', 4);
define('INTERWIKI_WIKIPEDIA', 8);
define('INTERWIKI_LIBRE', 16);
define('INTERWIKI_DC', 32);
define('INTERWIKI_ORI', 64);
define('INTERWIKI_ZETA', 128);

define('TITLEINDEX_NORMAL', 0);
define('TITLEINDEX_MEDIAWIKI', 1);
define('TITLEINDEX_NAMU', 2);

class MetaDB_compact extends MetaDB {
    var $metadb = null;
    var $storage;
    var $aux = array();

    function MetaDB_compact()
    {
        $args = func_get_args();
        $num = func_num_args();
        if ($num == 2) {
            // old style MetaDB_compact($file, $type)
            $conf = array();
            $conf['dbname'] = $args[0];
            $conf['dba_type'] = $args[1];
        } else {
            // new style MetaDB_compact($conf)
            if (is_array($args[0])) {
                $conf = $args[0];
            } else {
                $conf = array();
                $conf['dbname'] = $args[0];
                $conf['dba_type'] = 'db4';
            }
        }

        // FIXME storage type
        $type = 'dba';

        $class = 'Storage_'.$type;
        $this->storage = new $class($conf['dbname'], $conf['dba_type']);
        if ($this->storage->is_supported()) {
            $ret = $this->storage->open();
            if ($ret === false)
                return false;
        }

        // setup interwikis
        $this->interwikis = array(
                'KoWikiPedia'=>INTERWIKI_KOWIKI,
                'RigvedaWiki'=>INTERWIKI_RIGVEDA,
                'NamuWiki'=>INTERWIKI_NAMU,
                'WikiPedia'=>INTERWIKI_WIKIPEDIA,
                'LibreWiki'=>INTERWIKI_LIBRE,
                'DCWiki'=>INTERWIKI_DC,
                'OriWiki'=>INTERWIKI_ORI,
                'ZetaWiki'=>INTERWIKI_ZETA,
        );

        $this->types = array(
                'kowikipedia'=>TITLEINDEX_MEDIAWIKI,
                'rigvedawiki'=>TITLEINDEX_NORMAL,
                'namuwiki'=>TITLEINDEX_NAMU,
                'wikipedia'=>TITLEINDEX_MEDIAWIKI,
                'librewiki'=>TITLEINDEX_MEDIAWIKI,
                'dcwiki'=>TITLEINDEX_MEDIAWIKI,
                'oriwiki'=>TITLEINDEX_MEDIAWIKI,
                'zetawiki'=>TITLEINDEX_MEDIAWIKI,
        );

        $this->intermap = array_flip($this->interwikis);
        $this->metadb = new StdClass;
        $this->dbname = $conf['dbname'];

        return true;
    }

    function close()
    {
        $this->storage->close();
    }

    function attachDB($db)
    {
        $this->aux = $db;
    }

    function getSisterSites($pagename, $mode = 1)
    {
        $norm = preg_replace('/\s+/', '', $pagename);
        if ($norm == $pagename) {
            $nodb = !$this->hasPage($pagename);
        } else {
            $nodb = !$this->hasPage($pagename) && !$this->hasPage($norm);
        }
        if (!$this->aux->hasPage($pagename) and $nodb) {
            if ($mode) return '';
            return false;
        }
        if (!$mode) return true;
        $sisters = $this->_getSisters($pagename);
        if ($sisters == null)
            $sisters = $this->_getSisters($norm);
        $addons = $this->aux->getSisterSites($pagename, $mode);

        // escape " chars
        $name = strtr($pagename, array('"'=>'%22'));
        if (($pos = strpos($pagename, ' ')) !== false)
            // interwiki links with spaces like as InterWiki:"Foo bar"
            $name = '"'.$name.'"';

        $ret = '';
        if ($sisters)
            $ret = '[wiki:'.str_replace(' ', ":$name]\n[wiki:", $sisters).":$name]";
        if ($addons) $ret = rtrim($addons."\n".$ret);

        if ($mode == 1 and strlen($ret) > 80) return "[wiki:TwinPages:$name]";
        return $ret;
    }

    function getTwinPages($pagename, $mode = 1)
    {
        $norm = preg_replace('/\s+/', '', $pagename);
        if ($norm == $pagename) {
            $nodb = !$this->hasPage($pagename);
        } else {
            $nodb = !$this->hasPage($pagename) && !$this->hasPage($norm);
        }
        if (!$this->aux->hasPage($pagename) and $nodb) {
            if ($mode)
                return array();
            return false;
        }
        if (!$mode)
            return true;

        $twins = $this->_getTwins($pagename);
        if ($twins == null)
            $twins = $this->_getTwins($norm);

        $addons = $this->aux->getTwinPages($pagename, $mode);

        if ($addons) $twins = array_merge($addons, $twins);
        if (sizeof($twins) > 8) {
            if ($mode == 1) return array("TwinPages:$pagename");
            $twins = array_map(create_function('$a', 'return " * $a";'), $twins);
        }

        return $twins;
    }

    function hasPage($pagename)
    {
        if ($this->storage->exists($pagename))
            return true;
        return false;
    }

    function getLikePages($needle, $count = 500)
    {
        return $this->storage->getLikePages($needle, $count);
    }

    function _getWikis($pagename)
    {
        $val = $this->storage->get($pagename);

        if (empty($this->intermaps[$val])) {
            $j = 1;
            $wikis = array();
            while ($val >= $j) {
                if (($j & $val) == $j) {
                    $wikis[] = $this->intermap[$j];
                }
                $j = $j << 1;
            }
            $this->intermaps[$val] = $wikis;
        } else {
            $wikis = $this->intermaps[$val];
        }

        return $wikis;
    }

    function _getTwins($pagename)
    {
        $name = strtr($pagename, array('"'=>'%22'));
        if (($pos = strpos($pagename, ' ')) !== false)
            $name = '"'.$name.'"';

        $wikis = $this->_getWikis($pagename);
        $twins = array();
        foreach ($wikis as $wiki) {
            $twins[] = $wiki.':'.$name;
        }
        return $twins;
    }

    function _getSisters($pagename)
    {
        $wikis = $this->_getWikis($pagename);
        return implode(' ', $wikis);
    }

    // parse titleindex
    function parse($titleindex, $interwiki = 'kowikipedia')
    {
        if (!file_exists($titleindex)) {
            return false;
        }

        $type = $this->storage->get_type();
        $class = 'Storage_'.$type;
        $storage = new $class($this->dbname, $this->dba_type);
        $storage->open('w-');

        $interwikis = array_map('strtolower', $this->intermap);
        $interwikis = array_flip($interwikis);

        $interwiki = strtolower($interwiki);
        if (!isset($interwikis[$interwiki])) {
            return false;
        }

        $type = $this->types[$interwiki];
        $interwiki = $interwikis[$interwiki];

        $fp = fopen($titleindex, 'r');
        while(($line = fgets($fp, 2048)) !== false) {
            $item = trim($line, "\n");

            if ($type == TITLEINDEX_MEDIAWIKI) {
                $page = str_replace('_', ' ', $item);
            } else {
                $page = $item;
            }

            $v = $interwiki;
            if (!$create) {
                $o = $storage->get($page);
                $v |= $o;
            }
            $storage->update($page, $v);
        }
        fclose($fp);

        $storage->close();
        return true;
    }
}

class Storage_dba {
    var $db = null;

    // init class
    function Storage_dba($dbname, $dba_type)
    {
        $this->dbname = !empty($dbname) ? $dbname : 'temp.db';
        $this->dba_type = !empty($dba_type) ? $dba_type : 'db4';
    }

    // try to open a dba file
    function open($mode = null)
    {
        if (file_exists($this->dbname)) {
            $default_mode = 'r';
            if (empty($mode))
                $mode = $default_mode;
        } else {
            $mode = 'c';
        }

        $this->db = dba_open($this->dbname, $mode, $this->dba_type);
        if (is_resource($this->db))
            return true;
        return false;
    }

    // supported or not
    function is_supported()
    {
        if (function_exists('dba_open'))
            return true;
        return false;
    }

    // get storage type
    function get_type()
    {
        return 'dba';
    }

    // is it exist $key ?
    function exists($key)
    {
        return dba_exists($key, $this->db);
    }

    // fetch a $key,$data pair
    function get($key)
    {
        $data = dba_fetch($key, $this->db);
        if (isset($data[1]) and $data[1] == ':' and in_array($data[0], array('a', 'O'))) {
            $tmp = unserialize($data);
            if ($tmp !== false)
                $data = $tmp;
        }
        return $data;
    }

    // put a $key,$data pair
    function put($key, $data)
    {
        if (is_array($data))
            $data = serialize($data);
        return dba_insert($key, $data, $this->db);
    }

    // update an existing $key
    function update($key, $data)
    {
        if (is_array($data))
            $data = serialize($data);
        return dba_replace($key, $data, $this->db);
    }

    // close dba
    function close()
    {
        if (is_resource($this->db))
            dba_close($this->db);
    }
}

// vim:et:sts=4:sw=4:
