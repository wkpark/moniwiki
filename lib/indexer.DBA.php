<?php
// Copyright 2005-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a searchdb plugin for the MoniWiki
//
// initial version from http://www.heddley.com/edd/php/search.html
// heavily modified to adopt to the MoniWiki 2003/07/19 by wkpark
// $Id$

class Indexer_dba {
    var $db = null;
    var $type = 'n'; // N for 32-bit. n for 16 bit.
    var $wordcache = array();
    var $arena = '';

    var $dbname = ''; // db file name
    var $prefix = '';
    var $use_stemming = 1; // 0: noop / 1: fake stemming / 2: using KoreanStemmer 

    function Indexer_dba($arena,$mode='r',$type, $prefix = '') {
        global $DBInfo;

        $this->index_dir=$DBInfo->cache_dir.'/index';
        if (!file_exists($this->index_dir))
            mkdir($this->index_dir, 0777);

        $this->prefix = $prefix;
        if (!empty($prefix))
            $prefix = $prefix . '.';

        $this->dbname = $this->index_dir.'/'.$prefix.$arena.'.db';
        $this->arena = $arena;

        // check updated db file.
        if (empty($prefix) and file_exists($this->index_dir.'/'.$arena.'.new.db')) {
            if (filemtime($this->index_dir.'/'.$arena.'.new.db') > filemtime($this->dbname)) {
                copy($this->index_dir.'/'.$arena.'.new.db', $this->dbname);
            }
        }

        if (($this->db=@dba_open($this->dbname, $mode,$type)) === false) {
            if (($this->db=@dba_open($this->dbname, 'n',$type)) === false)
                return false;
            // startkey==256
            dba_insert('!!',256,$this->db);
            dba_sync($this->db);
        }
        register_shutdown_function(array(&$this,'close'));
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

    function _getIndexWords($string, &$words) {
        if (empty($string)) return false;

        if (preg_match('/[^0-9A-Za-z]/u', $string)) { // FIXME
            // split into single chars
	    $chars = preg_split('//u', $string, -1, PREG_SPLIT_NO_EMPTY);

            // make fake words for indexing
            // Please see MoniWiki:FastSearchMacro, MoniWiki:FullTextIndexer
            $sz = count($chars);
            if ($sz > 1) {
                $n = $chars[0];
                for ($i=1; $i < $sz; $i++) {
                    $n.= $chars[$i];
                    $words[] = $n;
                }
                #print_r($words);
                return true;
            }
        }
        return false;
    }

    function _stemmingWords($words) {
        static $indexer = null;
        if ($this->use_stemming > 1) {
            include_once(dirname(__FILE__).'/stemmer.ko.php');
            if (empty($indexer))
                $indexer = new KoreanStemmer();

            $founds = array();
            foreach ($words as $word) {
                if (preg_match('/[^0-9A-Za-z]/u', $word)) {
                    $match = null;
                    $stem = $indexer->getStem(trim($word), $match, $type);
                    if (!empty($stem))
                        $founds[] = $stem;
                } else {
                    $founds[] = $word;
                }
            }
            return $founds;
        }

        $new_words = array();
        foreach ($words as $word) {
            $this->_getIndexWords($word, $new_words);
        }
        $words = array_unique(array_merge($words, $new_words));
        return $words;
    }


    function addWordCache($pagename,$words) {
        if (!is_array($words)) return;
        $type = $this->type;
        $key = $this->getPageID($pagename);

        if ($this->use_stemming)
            $words = $this->_stemmingWords($words);
        $packed = pack($this->type, $key);
        foreach ($words as $word) {
            $a = !empty($this->wordcache[$word]) ? $this->wordcache[$word] : '';
            $a.= $packed;
            $this->wordcache[$word] = $a;
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
        if (!is_array($words) or empty($words)) return;
        $type=$this->type;
        $key=$this->getPageID($pagename);

        if ($this->use_stemming)
            $words = $this->_stemmingWords($words);
        $packed = pack($type, $key);
        foreach ($words as $word) {
            if (dba_exists($word,$this->db)) {
                $a=dba_fetch($word,$this->db);
            } else {
                dba_insert($word,'',$this->db);
                $a='';
            }
            $a.= $packed;
            $un=array_unique(unpack($type.'*',$a));
            asort($un);
            $na='';
            foreach ($un as $u) $na.=pack($type,$u);
            dba_replace($word,$na,$this->db);
        }
        return;
    }

    function delWords($pagename,$words,$mode='') {
        if (!is_array($words) or empty($words)) return;
        $type=$this->type;
        $key=$this->getPageID($pagename);
        #print $key."<br />";
        #print "<pre>";

        if ($this->use_stemming)
            $words = $this->_stemmingWords($words);
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
            #print $word."\n";
            #print_r($un);
            $na = '';
            foreach ($un as $u) $na.=pack($type,$u);
            if (empty($na))
                dba_delete($word,$this->db);
            else
                dba_replace($word,$na,$this->db);
        }
        #print "</pre>";
        return;
    }

    function deletePage($pagename) {
        if (dba_exists('!?'.$pagename, $this->db)) {
            $key = dba_fetch('!?'.$pagename, $this->db);
            $keyval = unpack($this->type.'1'.$this->type, $key);
            dba_delete('!?'.$key, $this->db);
            dba_delete('!?'.$pagename, $this->db);
            return true;
        }
        return false;
    }

    function hasPage($pagename) {
        if (dba_exists('!?'.$pagename, $this->db))
            return true;

        return false;
    }

    function close() {
        if (is_resource($this->db)) {
            $this->flushWordCache();
            $ret = dba_close($this->db);

            if (!empty($this->prefix)) {
                $postfix = $this->prefix.'.';
                rename($this->dbname, $this->index_dir.'/'.$this->arena.$postfix.'.db');
            }
            return $ret;
        }
        return false;
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
