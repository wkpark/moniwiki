<?php
// Copyright 2005-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a searchdb plugin for the MoniWiki
//
// initial version from http://www.heddley.com/edd/php/search.html
// heavily modified to adopt to the MoniWiki 2003/07/19 by wkpark
// $Id$

class IndexDB_dba {
    var $db = null;
    var $type = 'n'; // N for 32-bit. n for 16 bit.
    var $wordcache = array();

    function IndexDB_dba($arena,$mode='r',$type) {
        global $DBInfo;
        $this->index_dir=$DBInfo->cache_dir.'/index';
        if (!file_exists($this->index_dir))
            mkdir($this->index_dir, 0777);
        $db=$this->index_dir.'/'.$arena.'.db';
        if (($this->db=@dba_open($db, $mode,$type)) === false) {
            if (($this->db=@dba_open($db, 'n',$type)) === false)
                return false;
            // startkey==256
            dba_insert('!!',256,$this->db);
            dba_sync($this->db);
        }
        return true;
    }

    function getPageID($pagename) {
        if (!$this->exists('!?'.$pagename))
            return $this->_getNewID($pagename);

        $pkey = dba_fetch('!?'.$pagename,$this->db);
        $pkey = unpack($this->type.'1'.$this->type, $pkey);
        return $pkey[$this->type];
    }

    function fetchValues($pagename) {
        $pkey=$this->getPageID($pagename);
        return $this->_fetchValues($pkey);
    }

    function _fetchValues($key) {
        if (is_int($key))
            $key='!?'.pack($this->type,$key);

        $pkey=dba_fetch($key,$this->db);
        return unpack($this->type.'*',$pkey);
    }

    function _fetch($key) {
        if (is_int($key))
            $key='!?'.pack($this->type,$key);

        return dba_fetch($key,$this->db);
    }

    function exists($key) {
        return dba_exists($key,$this->db);
    }

    function _current() {
        return dba_fetch('!!',$this->db); // currentKey
    }

    function _getNewID($pagename) {
        $pkey=$nkey=$this->_current();
        $type=$this->type;
        // Map key to this filename
        dba_insert('!?' . $pagename, pack($this->type, $pkey), $this->db);
        dba_insert('!?' . pack($this->type, $pkey), $pagename, $this->db);
        $nkey++; if ($nkey % 256 == 0) { $nkey++; }
        dba_replace('!!',$nkey,$this->db);
        return $pkey;
    }

    function _getIndexWords($string) {
        if (preg_match('/[^0-9A-Za-z]/u', $string)) {
            // split into single chars
            $charstr = @preg_replace('/(.)/u',' \1 ',$string);
            if(!is_null($charstr)) $string = $charstr; //recover from regexp failure

            // make fake words for indexing
            // Please see MoniWiki:FastSearchMacro, MoniWiki:FullTextIndexer
            if (strpos($string, ' ') !== false) {
                $ws = preg_split('/\s+/', trim($string));
                $words = array();
                $n = '';
                foreach ($ws as $w) {
                    $n.= $w;
                    $words[] = $n;
                }
                #print_r($words);
                return $words;
            }
        }
        return false;
    }

    function addWordCache($pagename,$words) {
        if (!is_array($words)) return;
        $type = $this->type;
        $key = $this->getPageID($pagename);

        $nwords = array();
        foreach ($words as $word) {
            $ws = $this->_getIndexWords($word);
            if (!is_array($ws))
                $ws = array($word);

            foreach ($ws as $w) {
                $a = !empty($this->wordcache[$w]) ? $this->wordcache[$w] : '';
                $a.= pack($this->type, $key);
                $this->wordcache[$w] = $a;
            }
        }
        return;
    }

    function flushWordCache($sort = true) {
        foreach ($this->wordcache as $word=>$entry) {
            if (dba_exists($word, $this->db)) {
                $a = dba_fetch($word, $this->db);
            } else {
                dba_insert($word, '', $this->db);
                $a = '';
            }
            $a .= $entry; // merge
            if ($sort) {
                $un = array_unique(unpack($this->type.'*', $a)); // FIXME slow
                asort($un);

                $na = '';
                foreach ($un as $u) $na.= pack($this->type, $u);
                dba_replace($word, $na, $this->db);
            } else {
                // $un = array_unique(unpack($this->type.'*', $a));
                // asort($un);
                // $na = call_user_func_array(pack,array_merge(array($this->type.'*'),(array)$un));
                dba_replace($word, $a, $this->db);
            }
            #print $word."/";
        }
        #print " *** \n";
        # Empty the holding queue
        $this->wordcache = array();
    }

    function addWords($pagename,$words) {
        if (!is_array($words)) return;
        $type=$this->type;
        $key=$this->getPageID($pagename);

        foreach ($words as $word) {
            if (dba_exists($word,$this->db)) {
                $a=dba_fetch($word,$this->db);
            } else {
                dba_insert($word,'',$this->db);
                $a='';
            }
            $a.=pack($type,$key);
            $un=array_unique(unpack($type.'*',$a));
            asort($un);
            $na='';
            foreach ($un as $u) $na.=pack($type,$u);
            dba_replace($word,$na,$this->db);
        }
        return;
    }

    function delWords($pagename,$words,$mode='') {
        if (!is_array($words)) return;
        $type=$this->type;
        $key=$this->getPageID($pagename);
        foreach ($words as $word) {
            if (dba_exists($word,$this->db)) {
                $a=dba_fetch($word,$this->db);
            } else {
                continue;
            }
            $un=array_unique(unpack($type.'*',$a));
            $ta=array_flip($un);
            unset($ta[$key]);
            $un=array_flip($ta);
            asort($un);
            foreach ($un as $u) $na.=pack($type,$u);
            dba_replace($word,$na,$this->db);
        }
        return;
    }

    function close() {
        return dba_close($this->db);
    }

    function sort() {
        for ($k = dba_firstkey($this->db); $k != false; $k = dba_nextkey($this->db)) {
            if (isset($k[1]) and $k[0] == '!') continue;

            $a = dba_fetch($k, $this->db);
            $aa = unpack($this->type.'*', $a);
            asort($aa);
            $na = '';
            foreach ($aa as $u) $na.=pack($this->type, $u);
            dba_replace($k, $na, $this->db);
        }
    }

    function test() {
        for ($k = dba_firstkey($this->db); $k != false; $k = dba_nextkey($this->db)) {
            if (isset($k[1]) and $k[0] == '!' and $k[1] == '?' and strlen($k) == 4) {
                #print $k."=>\n";
                #$kk = unpack($this->type.'1', substr($k,2));
                #print_r($kk);
            } else if (isset($k[0]) and $k[0] != '!') {
                print $k."=>\n";
            }
        }
    }
}

// vim:et:sts=4:sw=4:
?>
