<?php
// Copyright 2008-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a Git versioning plugin for the MoniWiki
//
// WARNING: experimental
//

require_once(dirname(__FILE__).'/version.RCS.php');

class Version_Git extends Version_RCS {
  var $DB;

  function Version_Git($DB) {
    $this->DB=$DB;

    $this->cwd=getcwd();

    $this->NULL='';
    if(getenv("OS")!="Windows_NT")
      $this->NULL=' 2>/dev/null';

    if ($DB->rcs_error_log) $this->NULL='';

    $this->git_user=$DB->git_user;

    // init
    if (!is_dir($this->DB->text_dir.'/.git'))
      $log = $this->_init();
  }

  function _init() {
    chdir($this->DB->text_dir);
    $fp=popen('git init','r');

    $out='';
    if ($fp) {
      while (!feof($fp)) {
        $line=fgets($fp,2048);
        $out.= $line;
      }
      pclose($fp);
    }
    chdir($this->cwd);
    return $out;
  }

  function _filename($pagename) {
    # Return filename where this word/page should be stored.
    return $this->DB->_getPageKey($pagename);
  }

  function co($pagename,$rev='',$opt='') {
    # XXX
    $filename= $this->_filename($pagename);

    #if ($rev) $rev=':'.$rev;
    if (preg_match('/^[a-zA-Z0-9]+$/', $rev))
      $rev = "$rev:";
    else
      $rev = '';

    chdir($this->DB->text_dir);
    $fp=@popen('git show '.$rev.$filename,"r");
    chdir($this->cwd);
    $out='';
    if ($fp) {
      while (!feof($fp)) {
        $line=fgets($fp,2048);
        $out.= $line;
      }
      pclose($fp);
    }
    return $out;
  }

  function ci($pagename,$log) {
    $key=$this->_filename($pagename);
    $this->_ci($key, $log);
  }

  function _ci($filename,$log) {
    $log = escapeshellarg($log);
    $key=basename($filename); # XXX
    chdir($this->DB->text_dir);
    $fp=popen('git add '.$key.$this->NULL,"r");
    if ($fp) pclose($fp);
    $fp=popen('git commit -m'.$log.' '.$key.$this->NULL,"r");
    if ($fp) pclose($fp);

    chdir($this->cwd);
  }

  function _add($pagename,$log) {
    $key=$this->_filename($pagename);
    chdir($this->DB->text_dir);
    $ret=system('git add '.$key.$this->NULL);
    chdir($this->cwd);
  }

  function rlog($pagename,$rev='',$opt='',$oldopt='') {
    // oldopts are incompatible options only supported by the rlog in the rcs
    if (preg_match('/^[a-zA-Z0-9]+$/', $rev))
      $rev = ":$rev";
    else
      $rev = '';
    $filename=$this->_filename($pagename);

    $sep=str_repeat('-',28);
    $sep2=str_repeat('=',77);
    $rlog_format="--pretty=format:\"$sep%nrevision %H%ndate: %at%n%s%b\"";

    chdir($this->DB->text_dir);
    $fp= popen('git log '."$rlog_format $rev ".$filename.$this->NULL,"r");
    chdir($this->cwd);
    $out='';
    if (is_resource($fp)) {
      while (!feof($fp)) {
        $line=fgets($fp,1024);
        $out .= $line;
      }
      pclose($fp);
      if ($out)
        return $out."\n$sep2\n";
    }
    return '';
  }

  function diff($pagename,$rev='',$rev2='') {
    $filename=$this->_filename($pagename);
    chdir($this->DB->text_dir);

    if (!preg_match('/^[a-zA-Z0-9]+$/', $rev))
      $rev = '';
    if (!preg_match('/^[a-zA-Z0-9]+$/', $rev2))
      $rev2 = '';

    if ($rev and $rev2)
      $revs="$rev $rev2 ";
    else if ($rev or $rev2)
      $revs="$rev$rev2 HEAD ";

    $fp= popen('git diff --no-color '.$revs.$filename,'r');

    chdir($this->cwd);

    if (!$fp) return '';
    while (!feof($fp)) {
      # trashing first two lines XXX
      $line=fgets($fp,1024);
      if (preg_match('/^--- /',$line)) {
        $line=fgets($fp,1024);
        break;
      }
    }
    while (!feof($fp)) {
      $line=fgets($fp,1024);
      $out.= $line;
    }

    pclose($fp);
    return $out;
  }

  function get_rev($pagename,$mtime,$last=0) {
    # FIXME
    if ($last==1) {
      $tag='head:';
      $opt='-h';
    } else $tag='HEAD~1';
    if ($mtime) {
      $date=gmdate('Y-m-d H:i:s',$mtime);
      if ($date) {
        chdir($this->DB->text_dir);
        $filename=$this->_filename($pagename);

        $opt="--reverse --all --since=\"$date\" ";
        $fp= popen('git rev-list '.$opt.$filename,'r');

        chdir($this->cwd);
        if (!$fp) return '';
        $out='';
        if (!feof($fp)) {
          # trashing first two lines XXX
          $line=fgets($fp,1024);
          $out.= $line;
        }
        $tag=rtrim($line);
        pclose($fp);
      }
    }

    return $tag;
  }

  function purge($pagename,$rev) {
  }

  function delete($pagename) {
    // FIXME
    $filename=$this->_filename($pagename);
    chdir($this->DB->text_dir);
    system('git rm '.$filename);
    chdir($this->cwd);
  }

  function rename($pagename,$new) {
    // FIXME
    $keyname=$this->DB->_getPageKey($new);
    chdir($this->DB->text_dir);
    system('git mv '.$filename.' '.$keyname);
    chdir($this->cwd);
  }
}

// vim:et:sts=2:sw=2
