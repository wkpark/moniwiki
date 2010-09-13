<?php
// Copyright 2008 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a RCS versioning plugin for the MoniWiki
//
// @since  2003/08/22
// @author wkpark@kldp.org
//
// $Id$
//

class Version_RCS {
  var $DB;

  function Version_RCS($DB) {
    $this->DB=$DB;
    $this->NULL='';
    if(getenv("OS")!="Windows_NT") $this->NULL=' 2>/dev/null';
    if (!empty($DB->rcs_error_log)) $this->NULL='';
  }

  function _filename($pagename) {
    # have to be factored out XXX
    # Return filename where this word/page should be stored.
    return $this->DB->getPageKey($pagename);
  }

  function co($pagename,$rev,$opt=array()) {
    $filename= $this->_filename($pagename);

    $rev=(is_numeric($rev) and $rev>0) ? "\"".$rev."\" ":'';
    $ropt='-p';
    if (!empty($opt['stdout'])) $ropt='-r';
    $fp=@popen("co -x,v/ -q $ropt$rev ".$filename.$this->NULL,"r");
    if (!empty($opt['stdout'])) {
      if (is_resource($fp)) {
        pclose($fp);
        return '';
      }
    }

    $out='';
    if (is_resource($fp)) {
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
    $pgname=escapeshellcmd($pagename);
    $this->_ci($key,$log);
  }

  function _ci($key,$log) {
    $dir=dirname($key);
    if (!is_dir($dir.'/RCS')) {
      $om=umask(000);
      _mkdir_p($dir.'/RCS', 2777);
      umask($om);
    }
    $fp=@popen("ci -l -x,v/ -q -t-\"".$key."\" -m\"".$log."\" ".$key.$this->NULL,"r");
    if (is_resource($fp)) pclose($fp);
  }

  function rlog($pagename,$rev='',$opt='',$oldopt='') {
    $dmark = '';
    if ($rev[0] == '>' or $rev[0] == '<') {
      $dmark = $rev[0];
      $rev = substr($rev, 1);
    }
    if (is_numeric($rev) and preg_match('@^[0-9]{10}$@', trim($rev))) {
      // this is mtime
      $date = gmdate('Y/m/d H:i:s', $rev);
      $rev = '';
      if ($date)
        $rev = "-d\\$dmark'$date'";
    } else if (is_numeric($rev) and $rev > 0) {
      // normal revision
      $rev = "-r$rev";
    } else {
      $rev = '';
    }

    $filename=$this->_filename($pagename);

    $fp= popen("rlog $opt $oldopt -x,v/ $rev ".$filename.$this->NULL,"r");
    $out='';
    if (is_resource($fp)) {
      while (!feof($fp)) {
        $line=fgets($fp,1024);
        $out .= $line;
      }
      pclose($fp);
    }
    return $out;
  }

  function diff($pagename,$rev="",$rev2="") {
    $option = '';
    if ($rev) $option="-r$rev ";
    if ($rev2) $option.="-r$rev2 ";

    $filename=$this->_filename($pagename);
    $fp=popen("rcsdiff -x,v/ --minimal -u $option ".$filename.$this->NULL,'r');
    if (!is_resource($fp)) return '';
    while (!feof($fp)) {
      # trashing first two lines
      $line=fgets($fp,1024);
      if (preg_match('/^--- /',$line)) {
        $line=fgets($fp,1024);
        break;
      }
    }
    $out = '';
    while (!feof($fp)) {
      $line=fgets($fp,1024);
      $out.= $line;
    }
    pclose($fp);
    return $out;
  }

  function purge($pagename,$rev) {
  }

  function delete($pagename) {
    $keyname=$this->DB->_getPageKey($pagename);
    @unlink($this->DB->text_dir."/RCS/$keyname,v");
  }

  function rename($pagename,$new) {
    $keyname=$this->DB->_getPageKey($new);
    $oname=$this->DB->_getPageKey($pagename);
    rename($this->DB->text_dir."/RCS/$oname,v",
      $this->DB->text_dir."/RCS/$keyname,v");
  }

  function get_rev($pagename,$mtime='',$last=0) {
    $opt = '';
    if ($last==1) {
      $tag='head:';
      $opt='-h';
    } else $tag='revision';
    if ($mtime) {
      $date=gmdate('Y/m/d H:i:s',$mtime);
      if ($date) {
        $opt="-d\<'$date'";
        $tag='revision';
      }
    }

    $rev = '';
    $out= $this->rlog($pagename,'',$opt);
    if ($out) {
      for ($line=strtok($out,"\n"); $line !== false;$line=strtok("\n")) {
        preg_match("/^$tag\s+([\d\.]+)$/",$line,$match);
        if (isset($match[1])) {
          $rev=$match[1];
          break;
        }
      }
    }
    return $rev;
  }
  function export($pagename) {
    $keyname=$this->DB->_getPageKey($pagename);
    $fname=$this->DB->text_dir."/RCS/$keyname,v";
    $fp=fopen($fname,'r');
    $out = '';
    if (is_resource($fp)) {
      $sz=filesize($fname);
      if ($sz > 0)
        $out=fread($fp,$sz);
      fclose($fp);
    }
    return $out;
  }

  function import($pagename,$rcsfile) {
    if (empty($rcsfile))
      return false;

    $keyname=$this->DB->_getPageKey($pagename);
    $fname=$this->DB->text_dir."/RCS/$keyname,v";
    $om=umask(0770);
    chmod($fname,0664);
    umask($om);
    $fp=fopen($fname,'w');
    if (is_resource($fp)) {
      fwrite($fp,$rcsfile);
      fclose($fp);
      return true;
    }
    return false;
  }
}

// vim:et:ts=8:sts=2:sw=2
?>
