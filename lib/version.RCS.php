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
    $this->_ci($key,$log);
  }

  function _ci($key,$log) {
    $dir=dirname($key);
    if (!is_dir($dir.'/RCS')) {
      $om=umask(000);
      _mkdir_p($dir.'/RCS', 2777);
      umask($om);
    }
    $mlog = '';
    $plog = '';
    if (getenv('OS') == 'Windows_NT' and isset($log[0])) {
      // win32 cmd.exe arguments do not accept UTF-8 charset correctly.
      // just use the stdin commit msg method instead of using -m"log" argument.
      $logfile = tempnam($this->DB->vartmp_dir, 'COMMIT_LOG');
      $fp = fopen($logfile, 'w');
      if (is_resource($fp)) {
        fwrite($fp, $log);
        fclose($fp);
        $plog = ' < '.$logfile;
      }
    }
    if (empty($plog)) {
      $log = escapeshellarg($log);
      $mlog = ' -m'.$log;
    }

    // setup lockfile
    $lockfile = $key.'.##';
    touch($lockfile);
    $fl = fopen($key.'.##', 'w');
    $counter = 0;
    $locked = true;

    // lock timeout
    $timeout = isset($this->DB->savepage_timeout) && $this->DB->savepage_timeout > 5 ?
        $this->DB->savepage_timeout : 5;

    while(!flock($fl, LOCK_EX | LOCK_NB)) {
      if ($counter ++ < $timeout) {
        sleep(1);
      } else {
        $locked = false;
        break;
      }
    }

    if ($locked):
    if (!empty($this->DB->rcs_always_unlock)) {
      $fp = popen("rcs -l -M $key", 'r');
      if (is_resource($fp)) pclose($fp);
    }

    $fp = @popen("ci -l -x,v/ -q -t-\"".$key."\" ".$mlog." ".$key.$plog.$this->NULL,"r");
    if (is_resource($fp)) pclose($fp);
    if (isset($plog[0])) unlink($logfile);

    flock($fl, LOCK_UN);
    fclose($fl);

    // remove lockfile
    unlink($lockfile);

    return 0;
    endif;

    // fail to get flock
    return -1;
  }

  function rlog($pagename,$rev='',$opt='',$oldopt='') {
    $dmark = '';
    if (isset($rev[0]) and in_array($rev[0], array('>', '<'))) {
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
    $rev = escapeshellcmd($rev);
    $rev2 = escapeshellcmd($rev2);
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
    $tag = 'revision';
    $end = '$';
    if ($last==1) {
      $tag='revision';
      $opt='-r';
      $end = '\s*';
    }
    if ($mtime) {
      $date=gmdate('Y/m/d H:i:s',$mtime);
      $opt = "-d'$date'";
      $tag = 'revision';
      $end = '\s*';
    }

    $rev = '';
    $total = 0;
    $selected = 0;

    if (empty($opt)) { $opt = '-r'; $end = '\s*'; }

    $out= $this->rlog($pagename,'',$opt);

    $total = 0;

    // get the number of the total revisons and the selected revisions
    if (isset($out[0])) {
      for ($line=strtok($out,"\n"); $line !== false;$line=strtok("\n")) {
        if (empty($total)) {
          if (preg_match("/^total revisions:\s+(\d+)(?:;\s+selected revisions:\s+(\d+))?\s*$/", $line, $match)) {
            $total = $match[1];
            $selected = $match[2];
            if ($selected == 0) return '';
          }
        } else if (preg_match("/^$tag\s+(\d\.\d+)$end/", $line, $match)) {
          $rev = $match[1];
          $line = strtok("\n");
          preg_match("/^date: ([^;]+);/", $line, $match);
          $date = $match[1];
          break;
        }
      }
    }

    if ($mtime or $last) return $rev;
    if (empty($date)) return '';

    // get the previous version number
    $date = gmdate('"Y/m/d H:i:s"', strtotime($date.' GMT') - 1); // HACK 1-second before
    $opt = '-d'.$date;
    $out = $this->rlog($pagename, '', $opt);
    if (isset($out[0])) {
      for ($line = strtok($out, "\n"); $line !== false; $line = strtok("\n")) {
        if (preg_match("/^$tag\s+(\d\.\d+)$end/", $line, $match)) {
          $rev = $match[1];
          break;
        }
      }
    }

    return $rev;
  }

  function is_broken($pagename) {
    $keyname = $this->DB->_getPageKey($pagename);
    $fname = $this->DB->text_dir."/RCS/$keyname,v";

    $fp = @fopen($fname, 'r');
    if (!is_resource($fp)) return 0; // ignore

    fseek($fp, -4, SEEK_END);
    $end = fread($fp, 4);
    fclose($fp);
    //if ($end != "@\n") return 1; // broken
    if (!preg_match("/@\n\s*$/", $end)) return 1; // broken
    return 0;
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
