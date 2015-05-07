<?php
// Copyright 2004-2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a CVS versioning plugin for the MoniWiki
//
// $Id$
// WARNING: experimental
//

require_once(dirname(__FILE__).'/version.RCS.php');

class Version_CVS extends Version_RCS {
  var $DB;

  function Version_CVS($DB) {
    $this->DB=$DB;

    $this->cwd=getcwd();

    $this->cvs_root=$DB->cvs_root;
    $this->cvs_user=$DB->cvs_user;
    $this->modname=$DB->sitename; // XXX
    if ($this->cvs_root) {
      putenv("CVSROOT=".$this->cvs_root);

      if ($this->cvs_root[0] == '/' and is_dir($this->cvs_root)) {
        if (is_dir($this->cvs_root.'/'.$this->modname)) {
          // Is site could be Korean or other local charset ?
          // Is modname could contain any space chars ?
        } else {
          // import
          // How can I make a revision info as 1.1 ?
          $log = $this->_import($this->modname);
          $this->_checkout($this->modname);
        }
      }
    }
  }

  function _import($modname) {
    chdir($this->DB->text_dir);
    $fp=popen("cvs import -m \"".$this->DB->sitename."\" ".
      $this->modname." VENDOR INIT","r");

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

  function _checkout($modname) {
    @mkdir($this->DB->text_dir."/CVS",0777);

    chdir($this->DB->vartmp_dir);
    system("cvs checkout $modname > /dev/null",$ret);
    chdir($this->cwd);
    // Entries  Repository  Root
    foreach (array('Entries','Repository','Root') as $file)
      copy($this->DB->vartmp_dir."/".$modname."/CVS/$file",
        $this->DB->text_dir."/CVS/$file");
    return $ret ? false : true;
  }

  function _filename($pagename) {
    # Return filename where this word/page should be stored.
    return $this->DB->_getPageKey($pagename);
  }

  function co($pagename,$rev,$opt='') {
    $filename= $this->_filename($pagename);

    chdir($this->DB->text_dir);
    $fp=@popen("cvs co -p -r$rev ".$this->modname."/".$filename,"r");
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

    $this->_ci($filename,$log);
  }

  function _ci($filename,$log) {
    $key=basename($filename); // XXX
    chdir($this->DB->text_dir);
    //$ret=system("cvs commit -q -t-\"".$pagename."\" -m\"".$log."\" ".$key);
    // only *NIX work
    $log = escapeshellarg($log);
    if (!file_exists($this->cvs_root."/".$this->modname."/".$key.",v"))
       $ret=system("cvs add -m".$log." ".$key." >/dev/null");
    $ret=system("cvs commit -m".$log." ".$key." >/dev/null");
    chdir($this->cwd);
  }

  function _add($pagename,$log) {
    $key=$this->_filename($pagename);
    chdir($this->DB->text_dir);
    $log = escapeshellarg($log);
    $ret=system("cvs add -m".$log." ".$key);
    #$ret=system("cvs add -q -t-\"".$pagename."\" -m\"".$log."\" ".$key);
    chdir($this->cwd);
  }

  function rlog($pagename,$rev='',$opt='',$oldopt='') {
    // oldopts are incompatible options only supported by the rlog in the rcs
    // opt is low level arg
    if ($rev and preg_match('/^[0-9\.]+$/', $rev)) {
      $rev = "-r$rev";
    } else {
      return;
    }
    $filename=$this->_filename($pagename);

    chdir($this->DB->text_dir);
    $fp= popen("cvs log $opt $rev ".$filename,"r");
    chdir($this->cwd);
    $out='';
    if ($fp) {
      while (!feof($fp)) {
        $line=fgets($fp,1024);
        $out .= $line;
      }
      pclose($fp);
    }
    return $out;
  }

  function diff($pagename,$option) {
    $filename=$this->_filename($pagename);
    chdir($this->DB->text_dir);
    $fp= popen("cvs diff -u $option ".$filename,'r');
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

  function purge($pagename,$rev) {
  }

  function delete($pagename) {
    $filename=$this->_filename($pagename);
    chdir($this->DB->text_dir);
    system("cvs rm ".$filename);
    chdir($this->cwd);
  }

  function rename($pagename,$new) {
    $keyname=$this->DB->_getPageKey($new);
    $oname=$this->DB->_getPageKey($pagename);
    rename($this->cvs_root."/".$this->modname."/"."$oname,v",
           $this->cvs_root."/".$this->modname."/"."$oname,v");
  }
}

// vim:et:ts=8:sts=2:sw=2
?>
