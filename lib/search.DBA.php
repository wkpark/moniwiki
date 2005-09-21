<?php
// Copyright 2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a searchdb plugin for the MoniWiki
//
// initial version from http://www.heddley.com/edd/php/search.html
// heavily modified to adopt to the MoniWiki 2003/07/19 by wkpark
// $Id$

class IndexDB_dba {
    var $db=null;
    var $type="N";

    function IndexDB_dba($arena,$mode='r',$type) {
        global $DBInfo;
        $this->index_dir=$DBInfo->cache_dir."/index";
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
        print $pagename;
        if (!$this->exists($pagename))
            return $this->_getNewID($pagename);

        $pkey=dba_fetch('!?'.$pagename,$this->db);
        $pkey=unpack($this->type.'1'.$this->type,$pkey);
        return $pkey[$this->type];
    }

    function fetchValues($pagename) {
        $pkey=$this->getPageID($pagename);
        return $this->_fetchValues($pkey);
    }

    function _fetchValues($key) {
        if (is_int($key))
            $key=pack($this->type,$key);

        $pkey=dba_fetch('!?'.$key,$this->db);
        return unpack($this->type.'*',$pkey);
    }

    function _fetch($key) {
        if (is_int($key))
            $key=pack($this->type,$key);

        return dba_fetch('!?'.$key,$this->db);
    }

    function exists($key) {
        return dba_exists('!?'.$key,$this->db);
    }

    function _current() {
        return dba_fetch("!!",$this->db); // currentKey
    }

    function _getNewID($pagename) {
        $pkey=$nkey=$this->_current();
        $type=$this->type;
        dba_insert('!?'.$pagename,pack($type,$pkey),$this->db);
        dba_insert('!?'.pack($type,$pkey),$pagename,$this->db);
        $nkey++; if ($nkey % 256 ==0) { $nkey++; }
        dba_replace("!!",$nkey,$this->db);
        return $pkey;
    }

    function addWords($pagename,$words) {
        if (!is_array($words)) return;
        $type=$this->type;
        $key=$this->getPageID($pagename);
        foreach ($words as $word) {
            if (dba_exists('!?'.$word,$this->db)) {
                $a=dba_fetch('!?'.$word,$this->db);
            } else {
                dba_insert('!?'.$word,'',$this->db);
                $a='';
            }
            $a.=pack($type,$key);
            $un=array_unique(unpack($type.'*',$a));
            arsort($un);
            $na='';
            foreach ($un as $u) $na.=pack($type,$u);
            dba_replace('!?'.$word,$na,$this->db);
        }
        return;
    }

    function delWords($pagename,$words,$mode='') {
        if (!is_array($words)) return;
        $type=$this->type;
        $key=$this->getPageID($pagename);
        foreach ($words as $word) {
            if (dba_exists('!?'.$word,$this->db)) {
                $a=dba_fetch('!?'.$word,$this->db);
            } else {
                continue;
            }
            $un=array_unique(unpack($type.'*',$a));
            $ta=array_flip($un);
            unset($ta[$key]);
            $un=array_flip($ta);
            arsort($un);
            foreach ($un as $u) $na.=pack($type,$u);
            dba_replace('!?'.$word,$na,$this->db);
        }
        return;
    }

    function close() {
        return dba_close($this->db);
    }
}

// vim:et:sts=4
?>
