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
    var $type = 'N'; // N for 32-bit. n for 16 bit.
    var $wordcache = array();
    var $arena = '';

    var $dbname = ''; // db file name
    var $prefix = '';
    var $use_stemming = 0; // 0: noop / 1: fake stemming / 2: using KoreanStemmer 

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
            if (!file_exists($this->dbname) or filemtime($this->index_dir.'/'.$arena.'.new.db') > filemtime($this->dbname)) {
                @touch($this->dbname);
                $tmpname = '.tmp_'.time();
                copy($this->index_dir.'/'.$arena.'.new.db', $this->dbname.$tmpname);
                rename($this->dbname.$tmpname, $this->dbname);
            }
        }

        if (($this->db=@dba_open($this->dbname, $mode,$type)) === false) {
            if (($this->db=@dba_open($this->dbname, 'n',$type)) === false)
                return false;
            // startkey==256
            dba_insert("\001", 1,$this->db);
            dba_sync($this->db);
        }
        register_shutdown_function(array(&$this,'close'));
        return true;
    }

    function getPageID($pagename) {
        if (!$this->exists("\002".$pagename))
            return $this->_getNewID($pagename);

        $pkey = dba_fetch("\002".$pagename,$this->db);
        $pkey = unpack($this->type.'1'.$this->type, $pkey);
        return $pkey[$this->type];
    }

    function fetchValues($pagename) {
        $pkey=$this->getPageID($pagename);
        return $this->_fetchValues($pkey);
    }

    function _fetchValues($key) {
        if (is_int($key))
            $key="\003".pack($this->type,$key);

        $pkey=dba_fetch($key,$this->db);
        return unpack($this->type.'*',$pkey);
    }

    function _fetch($key) {
        if (is_int($key))
            $key="\003".pack($this->type,$key);

        return dba_fetch($key,$this->db);
    }

    function exists($key) {
        return dba_exists($key,$this->db);
    }

    function _current() {
        return dba_fetch("\001",$this->db); // currentKey
    }

    function _getNewID($pagename) {
        $pkey=$nkey=$this->_current();
        $type=$this->type;
        // Map key to this filename
        dba_insert("\002" . $pagename, pack($this->type, $pkey), $this->db);
        dba_insert("\003" . pack($this->type, $pkey), $pagename, $this->db);
        $nkey++;
        // if ($nkey % 256 == 0) { $nkey++; }
        dba_replace("\001",$nkey,$this->db);
        return $pkey;
    }


    function _fakeIndexWords($string, &$words) {
        if (preg_match('/^[\x{AC00}-\x{D7AF}]+$/u', $string)) { // XXX
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

    function _chunkWords($string, &$words, $all = true) {
        // dokuwiki like indexing
        if (!$all) // except hangul syllables
	    $ws = preg_split('/([^\x{AC00}-\x{D7AF}])/u', $string, -1,  PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        else
	    $ws = preg_split('//u', $string, -1,  PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if (count($ws) > 1) {
            foreach ($ws as $w) {
                $words[] = $w;
            }
            return true;
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
        foreach ($words as $k=>$word) {
            if (!isset($word[0])) continue;

            if ($word[0] == "\010" and preg_match('/[^0-9A-Za-z]/u', $word)) {
                //$ret = $this->_fakeIndexWords($word, $new_words);
                $ret = $this->_chunkWords($word, $new_words, true);
                if ($ret) unset($words[$k]); // XXX
            }
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
        if (dba_exists("\002".$pagename, $this->db)) {
            $key = dba_fetch("\002".$pagename, $this->db);
            $keyval = unpack($this->type.'1'.$this->type, $key);
            dba_delete("\003".$key, $this->db);
            dba_delete("\002".$pagename, $this->db);
            return true;
        }
        return false;
    }

    function hasPage($pagename) {
        if (dba_exists("\002".$pagename, $this->db))
            return true;

        return false;
    }

    function close() {
        if (is_resource($this->db)) {
            $this->flushWordCache();
            $ret = dba_close($this->db);

            if (!empty($this->prefix)) {
                $postfix = '.'.$this->prefix;
                rename($this->dbname, $this->index_dir.'/'.$this->arena.$postfix.'.db');
            }
            return $ret;
        }
        return false;
    }

    function sort() {
        for ($k = dba_firstkey($this->db); $k !== false; $k = dba_nextkey($this->db)) {
            if (isset($k[0]) and strcmp($k[0], "\010") < 0) continue;

            $a = dba_fetch($k, $this->db);
            $aa = array_unique(unpack($this->type.'*', $a)); // FIXME slow
            asort($aa);
            $na = '';
            foreach ($aa as $u) $na.=pack($this->type, $u);
            dba_replace($k, $na, $this->db);
        }
    }

    function test() {
        for ($k = dba_firstkey($this->db); $k !== false; $k = dba_nextkey($this->db)) {
            if (isset($k[0]) and $k[0] == "\003" and strlen($k) == 4) { // FIXME
                #print $k."=>\n";
                #$kk = unpack($this->type.'1', substr($k,2));
                #print_r($kk);
            } else if (isset($k[0]) and strcmp($k[0], "\010") > 0) {
                print $k."=>\n";
            }
        }
    }

    function title() {
        $count = 0;
        for ($k = dba_firstkey($this->db); $k !== false; $k = dba_nextkey($this->db)) {
            if (isset($k[1]) and $k[0] == "\002") {
                $count++;
                print substr($k, 1)."\n";
            }
        }
        #print 'Total '.$count."\n";
    }

    function getAllPages() {
        $count = 0;
        $pages = array();
        for ($k = dba_firstkey($this->db); $k !== false; $k = dba_nextkey($this->db)) {
            if (isset($k[1]) and $k[0] == "\002") {
                $count++;
                $pages[] = substr($k, 1);
            } else if ($count > 0) {
                break;
            }
        }
        return $pages;
    }

    // store same length words to '??<length>' key to search all words.
    function packWords() {
        $words = array();
        $len = 0;
        for ($k = dba_firstkey($this->db); $k !== false; $k = dba_nextkey($this->db)) {
            if (isset($k[0]) and strcmp($k[0], "\010") > 0) {
                // is it UTF-8 3-bytes ? FIXME
                //if (preg_match('/^([\xe0-\xef][\x80-\xbf]{2})+$/', $k)) {
                if (preg_match('/[^a-zA-Z0-9]/u', $k)) {
                    $len = mb_strlen($k, 'UTF-8'); // FIXME
                    $words[$len] .= $k."\n";
                }
            } else if ($len > 1) {
                break;
            }
        }

        foreach ($words as $len=>$w) {
            // XXX debug
            // file_put_contents($this->index_dir.'/'.$this->arena.'.w'.$len.'.txt' , $words[$len]);
            if (dba_exists("\004".$len, $this->db)) {
                dba_replace("\004".$len, $w, $this->db);
            } else {
                dba_insert("\004".$len, $w, $this->db);
            }
        }
    }

    // match word individually - slow slow
    function _match($word) {
        $words = array();
        for ($k = dba_firstkey($this->db); $k !== false; $k = dba_nextkey($this->db)) {
            if (isset($k[0]) and strcmp($k[0], "\010") > 0 and preg_match('@'.$word.'@', $k)) {
                $words[] = $k;
            }
        }
        return $words;
    }

    // faster than _match()
    function _search($word) {
        $words = array();
        $len = mb_strlen($word, 'UTF-8'); // FIXME
        for (; $len < 20; $len++) {
            if (dba_exists("\004".$len, $this->db)) {
                $content = dba_fetch("\004".$len, $this->db);
                preg_match_all('@^.*'.$word.'.*$@m', $content, $match);
                if (isset($match[0])) {
                    foreach ($match[0] as $m) $words[] = $m;
                }
            }
        }
        return $words;
    }

    // search pages with words
    function searchPages($words) {
        if (!is_array($words)) $words = array($words);

        $words = array_map('strtolower', $words);

        $idx = array();
        $new_words = array();
        foreach ($words as $word) {
            $new_words = array_merge($idx, $this->_search($word));
        }
        $words = array_merge($words, $new_words);

        $word = array_shift($words);
        $idx = $this->_fetchValues($word);
        foreach ($words as $word) {
            $ids = $this->_fetchValues($word); // FIXME
            foreach ($ids as $id) $idx[] = $id;
        }

        $pages = array();
        foreach ($idx as $id) {
            $key = $this->_fetch($id);
            $pages[$id] = $key;
        }
        return $pages;
    }
}

// vim:et:sts=4:sw=4:
?>
