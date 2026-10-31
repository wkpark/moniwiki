<?php
// Copyright 2004 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a RcsLite versioning plugin for the MoniWiki
//
// $Id$

require_once(dirname(__FILE__).'/version.RCS.php');

class Version_RcsLite extends Version_RCS {
  var $DB;

  function __construct($DB) {
    parent::__construct($DB);
    include_once(dirname(__FILE__).'/rcslite.php');

    $this->rcs=new RcsLite(!empty($DB->rcs_dir) ? $DB->rcs_dir : 'RCS', !empty($DB->rcs_user) ? $DB->rcs_user : 'root');
    $this->rcs_dir=$this->rcs->rcs_dir;
    $this->DB=$DB;
  }

  function co($pagename,$rev,$opt='') {
    $filename= $this->_filename($pagename);

    $this->rcs->_process($filename);

    #if ($this->rcs->_author[$rev])
    $out=$this->rcs->getRevision($rev);

    if (!empty($opt['stdout'])) {
      if (!isset($this->rcs->_next[$rev]) || $this->rcs->_where === 'invalid') return '';
      @file_put_contents($filename, $out, LOCK_EX);
      return '';
    }

    return $out;
  }

  function ci($pagename,$log,$force=false) {
    $filename=$this->_filename($pagename);
    return $this->_ci($filename,$log,$force);
  }
  function _ci($filename,$log,$force=false) {
    $dir = dirname($filename).'/'.$this->rcs_dir;
    if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) return -1;
    $lock = @fopen($filename.'.##', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) {
      if ($lock) fclose($lock);
      return -1;
    }
    $this->rcs->_process($filename, 0, RCSLITE_FORCE);
    $result = in_array($this->rcs->_where, array('invalid', 'unreadable'), true) ? false : $this->rcs->addRevisionPage($log);
    flock($lock, LOCK_UN);
    fclose($lock);
    return $result === '' ? 0 : -1;
  }

  function rlog($pagename,$rev='',$opt='',$oldopt='') {
    $filename=$this->_filename($pagename);

    $this->rcs->_process($filename);

    return $this->rcs->rlog($rev,$opt,$oldopt);
  }

  function diff($pagename,$rev='',$rev2='',$params=array()) {
    $filename=$this->_filename($pagename);
    $this->rcs->_process($filename);

    if (empty($this->rcs->_head)) return '';
    if (!$rev2) {
      $old = $this->rcs->getRevision($rev ? $rev : $this->rcs->_head);
      $new = @file_get_contents($filename);
      return $new === false ? '' : $this->rcs->_diffText($new, $old, 'udiff');
    }
    $out=$this->rcs->revisionDiff($rev,$rev2,'udiff');

    return $out;
  }

  function purge($pagename,$rev) {
  }

  function delete($pagename) {
    $keyname=$this->DB->_getPageKey($pagename);
    // do not delete history at all.
    // just rename it.
    $this->_atticpage($pagename);
  }

  // store pagename
  function _atticpage($pagename) {
    $keyname = $this->DB->_getPageKey($pagename);
    $oname = $this->DB->text_dir.'/'.$this->rcs_dir.'/'.$keyname.',v';

    $ext = ',v';
    $i = 0;
    while (file_exists($this->DB->text_dir.'/'.$this->rcs_dir.'/'.$keyname.$ext)) {
      $i++;
      $ext = ','.$i;
    }

    $atticname = $this->DB->text_dir.'/'.$this->rcs_dir.'/'.$keyname.$ext;
    if ($i != 0)
      return rename($oname, $atticname);
    return false;
  }

  function rename($pagename,$new,$params=array()) {
    $keyname=$this->DB->_getPageKey($new);
    $oname=$this->DB->_getPageKey($pagename);
    $oldfile = $this->DB->text_dir.'/'.$this->rcs_dir."/$oname,v";
    $newfile = $this->DB->text_dir.'/'.$this->rcs_dir."/$keyname,v";
    if (!file_exists($oldfile) || file_exists($newfile) || !rename($oldfile, $newfile)) return -1;
    return $this->ci($new, isset($params['log']) ? $params['log'] : '', true);
  }

  function is_broken($pagename) {
    $this->rcs->_process($this->_filename($pagename), 0, RCSLITE_FORCE);
    return in_array($this->rcs->_where, array('invalid', 'unreadable'), true);
  }

  function get_rev($pagename,$mtime='',$last=0) {
    $filename=$this->_filename($pagename);
    $this->rcs->_process($filename);

    if ($last==1)
      return $this->rcs->_head;
    if ($mtime) {
      #print gmdate('Y/m/d H:i:s',$mtime);
      if ($mtime >= $this->rcs->_date[$this->rcs->_head])
         return $this->rcs->_head;
      for ($rev = $this->rcs->_head; $rev !== ''; $rev = $this->rcs->_next[$rev]) {
         $date = $this->rcs->_date[$rev];
         if ($mtime >= $date) {
            return $rev;
         }
      }
      return '';
    } else {
      return isset($this->rcs->_next[$this->rcs->_head]) ? $this->rcs->_next[$this->rcs->_head] : '';
    }

    $out = $this->rlog($pagename,'',$opt);
    if ($out) {
      $lines=explode("\n",$out);
      foreach ($lines as $line) {
        preg_match("/^revision\s+([\d\.]+)/",$line,$match);
        if ($rev == $match[1])
            continue;
        else {
          $rev=$match[1];
          break;
	}
      }
    }
    if ($rev) return $rev;
    return $this->rcs->_head;
  }
}
