<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org> all rights reserved.
// distributable under GPL see COPYING
//
// many codes are imported from the MoinMoin
// some codes are reused from the Phiki
//
// * MoinMoin is a python based wiki clone based on the PikiPiki
//    by Ju"rgen Hermann <jhs at web.de>
// * PikiPiki is a python based wiki clone by MartinPool
// * Phiki is a php based wiki clone based on the MoinMoin
//    by Fred C. Yankowski <fcy at acm.org>
//
// $Id$

include "wikilib.php";

function preg_escape($val) {
  return preg_replace('/([\^\.\{\}\|\(\)\/\\\\!]{1})/','\\\\\1',$val);
}

function get_scriptname() {
  // Return full URL of current page.
  return $_SERVER["SCRIPT_NAME"];
}

function qualifiedUrl($url) {
  global $HTTP_HOST;
  if (substr($url[0],0,7)=="http://")
    return $url;
  return "http://$HTTP_HOST$url";
}

function goto_form($action,$option="") {
  if ($option==1) {
    return "
<form name='go' id='go' method='get' action='$action'>
<span title='TitleSearch'>
<input type='radio' name='action' value='titlesearch' />
Title</span>
<span title='FullSearch'>
<input type='radio' name='action' value='fullsearch' />
Contents</span>&nbsp;
<input type='text' name='value' class='goto' size='20' />
<input type='submit' value='Go' class='goto' style='width:23px' />
";
  } else if ($option==2) {
    return "
<form name='go' id='go' method='get' action='$action'>
<select name='action' style='width:60px'>
<option value='goto'/>&nbsp;&nbsp;&nbsp;
<option value='titlesearch'/>TitleSearch
<option value='fullsearch'/>FullSearch
</select>
<input type='text' name='value' size='20' />
<input type='submit' value='Go' />
";
  } else if ($option==3) {
    return "
<form name='go' id='go' method='get' action='$action'>
<table class='goto'>
<tr><td nowrap='nowrap' style='width:220'>
<input type='text' name='value' size='28' style='width:110px' />
<input type='submit' value='Go' class='goto' style='width:23px' />
</td></tr>
<tr><td>
<span title='TitleSearch' class='goto'>
<input type='radio' name='action' value='titlesearch' class='goto' />
Title(?)</span>
<span title='FullSearch' class='goto'>
<input type='radio' name='action' value='fullsearch' class='goto'/>
Contents(/)</span>&nbsp;
</td></tr>
</table>
</form>
";
  } else {
    return <<<FORM
<form name='go' id='go' method='get' action='$action' onsubmit="return moin_submit();">
<input type='text' name='value' size='20' style='width:100' />
<input type='hidden' name='action' value='goto' />
<input type='submit' value='Go' class='goto' style='width:23px;' />
</form>
FORM;
  }
}

function kbd_handler() {
  global $DBInfo;

  if (!$DBInfo->kbd_script) return '';
  $prefix=get_scriptname();
  $sep= $DBInfo->query_prefix;
  print <<<EOS
<script language="JavaScript" type="text/javascript">
<!--
url_prefix="$prefix";
FrontPage="${sep}$DBInfo->frontpage";
//-->
</script>
<script type="text/javascript" src="$DBInfo->kbd_script">
</script>
EOS;
}

class Timer {
  function Timer() {
    $mt= explode(" ",microtime());
    $this->now=$mt[0]+$mt[1];
    $this->timing=$this->now;
  }

  function Check() {
    $mt= explode(" ",microtime());
    $this->now=$mt[0]+$mt[1];
    return $this->now-$this->timing;
  }
}

$timing=new Timer();

class MetaDB_dba extends MetaDB {
  var $metadb;

  function MetaDB_dba($file,$type="db3") {
    $this->metadb=@dba_open($file.".cache","r",$type);
  }

  function getSisterSites($pagename) {
    if (dba_exists($pagename,$this->metadb)) {
       $dum=explode(" ",dba_fetch($pagename,$this->metadb));
       return "wiki:".join(":$pagename wiki:",$dum).":$pagename";
    }
    return "";
  }

  function getTwinPages($pagename) {
    if ($pagename && dba_exists($pagename,$this->metadb)) {
       $dum=explode(" ",dba_fetch($pagename,$this->metadb));
       return "See TwinPages wiki:".join(":$pagename wiki:",$dum).":$pagename";
    }
    return "";
  }

  function getAllPages() {
    if ($this->keys) return $this->keys;
    for ($key= dba_firstkey($this->metadb);
         $key !== false;
         $key= dba_nextkey($this->metadb)) {
      $keys[] = $key;
    }
    $this->keys=$keys;
    return $keys;
  }
}

class MetaDB {
  function MetaDB() {
    return;
  }
  function getSisterSites($pagename) {
    return "";
  }
  function getTwinPages($pagename) {
    return "";
  }
  function getAllPages() {
    return array();
  }
}

class Counter_dba {
  var $counter;
  function Counter_dba($DB) {
    $this->counter=@dba_open($DB->data_dir."/counter.db","w",$DB->dba_type);
    if (!$this->counter)
       $this->counter=dba_open($DB->data_dir."/counter.db","n",$DB->dba_type);
  }

  function incCounter($pagename) {
    $count=dba_fetch($pagename,$this->counter);
    $count++;
    dba_replace($key,$count,$this->counter);
  }

  function pageCounter($pagename) {
    $count=dba_fetch($pagename,$this->counter);
    return $count;
  }
}

class Counter {
  function Counter($DB="") {
  }

  function incCounter($page) {
  }

  function pageCounter($page) {
    return 1;
  }
}

function getConfig($configfile) {
  if (!file_exists($configfile))
    return array();

  $org=get_defined_vars();
  include($configfile);
  $new=get_defined_vars();

  return array_diff($new,$org);
}

class WikiDB {
  function WikiDB($config=array()) {
  # Default Configuations
    $this->frontpage='FrontPage'; # _("FrontPage")
    $this->sitename='MoniWiki';
    $this->data_dir= './data';
    $this->upload_dir= './pds';
    $this->query_prefix='/';
    $this->umask= 02;
    $this->embeded=0;
    $this->charset='euc-kr';
    $this->lang='ko';
    $this->dba_type="db3";
    $this->use_counter=1;

    $this->text_dir= $this->data_dir.'/text';
    $this->cache_dir= $this->data_dir.'/cache';
    $this->intermap= $this->data_dir.'/intermap.txt';
    $this->editlog_name= $this->data_dir.'/editlog';
    $this->shared_intermap=$this->data_dir."/text/InterMap";
    $this->shared_metadb=$this->data_dir."/metadb";
    $this->url_prefix= '/moniwiki';
    $this->imgs_dir= $this->url_prefix.'/imgs';
    $this->css_dir= 'css';
    $this->logo_img= $this->imgs_dir.'/moniwiki.gif';

    $this->css_url= $this->url_prefix.'/css/default.css';
    $this->kbd_script= $this->url_prefix.'/css/kbd.js';
    $this->logo_string= '<img src="'.$this->logo_img.
                        '" alt="[RecentChanges]" border="0" align="middle" />';
    $this->use_smileys=1;
    $this->hr="<hr class='wikiHr' />";
    $this->date_fmt= 'D d M Y';
    $this->datetime_fmt= 'D d M Y h:i a';
    #$this->changed_time_fmt = ' . . . . [h:i a]';
    $this->changed_time_fmt= ' [h:i a]';
    $this->admin_passwd= '10sQ0sKjIJES.';
    $this->purge_passwd= '';
    $this->actions= array('DeletePage','LikePages');
    $this->show_hosts= TRUE;

    # set user-specified configuration
    if ($config) {
       # read configurations
       while (list($key,$val) = each($config))
          $this->$key=$val;
    }

    if (!$this->icon) {
    $this->icon[upper]="<img src='$this->imgs_dir/upper.gif' alt='U' align='middle' border='0' />";
    $this->icon[edit]="<img src='$this->imgs_dir/moin-edit.gif' alt='E' align='middle' border='0' />";
    $this->icon[diff]="<img src='$this->imgs_dir/moin-diff.gif' alt='D' align='middle' border='0' />";
    $this->icon[del]="<img src='$this->imgs_dir/moin-deleted.gif' alt='X' align='middle' border='0' />";
    $this->icon[info]="<img src='$this->imgs_dir/moin-info.gif' alt='I' align='middle' border='0' />";
    $this->icon[rss]="<img src='$this->imgs_dir/rss.png' alt='RSS' align='middle' border='0' />";
    $this->icon[show]="<img src='$this->imgs_dir/moin-show.gif' alt='R' align='middle' border='0' />";
    $this->icon[find]="<img src='$this->imgs_dir/moin-search.gif' alt='S' align='middle' border='0' />";
    $this->icon[help]="<img src='$this->imgs_dir/moin-help.gif' alt='H' align='middle' border='0' />";
    $this->icon[www]="<img src='$this->imgs_dir/moin-www.gif' alt='www' align='middle' border='0' />";
    $this->icon[mailto]="<img src='$this->imgs_dir/moin-www.gif' alt='www' align='middle' border='0' />";
    }

    if (!$this->menu) {
#    $this->menu="<img src='$this->imgs_dir/diff-7.gif'> ".
#                "<img src='$this->imgs_dir/edit-7.gif'> ".
#                "<img src='$this->imgs_dir/info-7.gif'> ".
#                "<img src='$this->imgs_dir/show-7.gif'> ".
#                "<img src='$this->imgs_dir/find-7.gif'> ".
#                "<img src='$this->imgs_dir/help-7.gif'> ".
#                "<img src='$this->imgs_dir/home-7.gif'> ";
    $this->menu="<img src='$this->imgs_dir/moin-diff.gif' alt='D' /> ".
                "<img src='$this->imgs_dir/moin-edit.gif' alt='E' /> ".
                "<img src='$this->imgs_dir/moin-info.gif' alt='I' /> ".
                "<img src='$this->imgs_dir/moin-show.gif' alt='R' /> ".
                "<img src='$this->imgs_dir/moin-search.gif' alt='S' /> ".
                "<img src='$this->imgs_dir/moin-help.gif' alt='H' /> ".
                "<img src='$this->imgs_dir/moin-home.gif' alt='Z' /> ";
    }

    # load smileys
    if ($this->use_smileys){
      include("wikismiley.php");
      # set smileys rule
      $tmp=array_keys($smileys);
      $tmp=array_map("preg_escape",$tmp);
      $rule=join($tmp,"|");
      $this->smiley_rule=$rule;
      $this->smileys=$smileys;
    }

    # ??? Number of lines output per each flush() call.
    // $this->lines_per_flush = 10;

    # ??? Is mod_rewrite being used to translate 'WikiWord' to
    // $this->rewrite = true;

    $this->set_intermap();
    if ($this->shared_metadb)
      $this->metadb=new MetaDB_dba($this->shared_metadb,$this->dba_type);
    if (!$this->metadb->metadb)
      $this->metadb=new MetaDB();

    if ($this->use_counter)
      $this->counter=new Counter_dba($this);
    if (!$this->counter->counter)
      $this->counter=new Counter();

  }

  function Close() {
    dba_close($this->counter);
  }

  function set_intermap() {
    # intitialize interwiki map
    $map=file($this->intermap);

    # read shared intermap
    $shared_map=array();
    if (file_exists($this->shared_intermap)) {
      $shared_map=file($this->shared_intermap);
    }
    # merge
    $map=array_merge($map,$shared_map);

    for ($i=0;$i<sizeof($map);$i++) {
      $line=trim($map[$i]);
      if (!$line || $line[0]=="#") continue;
      if (preg_match("/^[a-z]+/i",$line)) {
        $dum=split("[[:space:]]",$line);
        $this->interwiki[$dum[0]]=trim($dum[1]);
        $this->interwikirule.="$dum[0]|";
      }
    }
    $this->interwikirule.="Self";
    $this->interwiki[Self]=get_scriptname().$this->query_prefix;
  }


  function getPageKey($pagename) {
    # normalize a pagename to uniq key

    # moinmoin style internal encoding
    #$name=rawurlencode($pagename);
    #$name=preg_replace("/%([a-f0-9]{2})/ie","'_'.strtolower('\\1')",$name);
    #$name=preg_replace(".","_2e",$name);

    #$name=str_replace("\\","",$pagename);
    #$name=stripslashes($pagename);
    $name=preg_replace("/([^a-z0-9]{1})/ie","'_'.strtolower(dechex(ord('\\1')))",$pagename);

    return $this->text_dir . '/' . $name;
  }

  function hasPage($pagename) {
    if (!$pagename) return false;
    $name=$this->getPageKey($pagename);
    return file_exists($name); 
  }

  function getPage($pagename,$options="") {
    return new WikiPage($pagename,$options);
  }

#  function keyToPagename($key) {
#    $pagename=preg_replace("/_([a-f0-9]{2})/","%\\1",$key);
#    return urldecode($pagename);
#  }

  function keyToPagename($key) {
  #  return preg_replace("/_([a-f0-9]{2})/e","chr(hexdec('\\1'))",$key);
  #  $pagename=preg_replace("/_([a-f0-9]{2})/","%\\1",$key);
  #  $pagename=str_replace("_","%",$key);
    $pagename=strtr($key,"_","%");
    return urldecode($pagename);
  }


  function getPageLists($options="") {
    $pages = array();
    $handle = opendir($this->text_dir);

    if ($options[limit]) { # XXX
       while ($file = readdir($handle)) {
          if (is_dir($this->text_dir."/".$file)) continue;
          if (filemtime($file) > $options[limit])
             $pages[] = $this->keyToPagename($file);
       }
       closedir($handle);
       return $pages;
    } else if ($options[count]) {
       $count=$options[count];
       while (($file = readdir($handle)) && $count > 0) {
          if (is_dir($this->text_dir."/".$file)) continue;
          $pages[] = $this->keyToPagename($file);
          $count--;
       }
       closedir($handle);
       return $pages;
    }
    while ($file = readdir($handle)) {
       if (is_dir($this->text_dir."/".$file)) continue;
       $pages[] = $this->keyToPagename($file);
    }
    closedir($handle);
    return $pages;
  }

  function getCounter() {
    return sizeof($this->getPageLists());
  }

  function addLogEntry($page_name, $remote_name,$comment,$action="SAVE") {
    $user=new User();
    $fp_editlog = fopen($this->editlog_name, 'a+');
    $time= time();
    $host= gethostbyaddr($remote_name);
    $msg="$page_name\t$remote_name\t$time\t$host\t$user->id\t$comment\t$action\n";
    fwrite($fp_editlog, $msg);
    fclose($fp_editlog);
  }

  function reverse($arrayX) {
    $out= array();
    $size= count($arrayX);
    for ($i= $size - 1; $i >= 0; $i--)
      $out[]= $arrayX[$i];
    return $out;
  }

  function editlog_raw_lines($size=5000,$quick="") {
    $filesize= filesize($this->editlog_name);
    if ($filesize > $size) {
      $fp= fopen($this->editlog_name, 'r');

      fseek($fp, -$size, SEEK_END);

      $dumm=fgets($fp,1024); # emit dummy
      while (!feof($fp)) {
        $line=fgets($fp,2048);
#       $line=preg_replace("/[\r\n]+$/","",$line);
#       print $line."<br />";
        $lines[]=$line;
      }
      fclose($fp);
    } else
      $lines=file($this->editlog_name);

    $lines=$this->reverse($lines);
    unset($lines[0]); # delete last dummy

    if ($quick) {
      foreach($lines as $line) {
        $dum=explode("\t",$line,2);
        if ($keys[$dum[0]]) continue;
        $keys[$dum[0]]=1;
        $out[]=$line;
      }
      $lines=$out;
    }

    #return file($this->editlog_name);
    return $lines;
  }

  function savePage($page,$comment="") {
    $REMOTE_ADDR=$_SERVER[REMOTE_ADDR];

    $key=$this->getPageKey($page->name);

    $fp=fopen($key,"w");
    if (!$fp)
       return -1;
    fwrite($fp, $page->body);
    fclose($fp);
    system("ci -q -t-".$page->name." -l -m'".$REMOTE_ADDR.";;".$comment."' ".$key);
    $this->addLogEntry($page->name, $REMOTE_ADDR,$comment,"SAVE");
    return 0;
  }

  function deletePage($page,$comment="") {
    $REMOTE_ADDR=$_SERVER[REMOTE_ADDR];

    $key=$this->getPageKey($page->name);

    $delete=@unlink($key);
#    system("ci -q -t-".$page->name." -l -m'".$REMOTE_ADDR.";;".$comment."' ".$key);
    $this->addLogEntry($page->name, $REMOTE_ADDR,$comment,"DEL");
  }
}

class Cache_text {
  function Cache_text($arena) {
    global $DBInfo;
    $this->cache_dir=$DBInfo->cache_dir."/$arena";
    if (!file_exists($this->cache_dir))
       mkdir($this->cache_dir, 0777);
  }

  function getKey($pagename) {
    $name=preg_replace("/([^a-z0-9]{1})/ie","'_'.strtolower(dechex(ord('\\1')))",$pagename);
    return $this->cache_dir . '/' . $name;
  }

  function update($pagename,$val,$mtime="") {
    $key=$this->getKey($pagename);
    if ($mtime <= $this->mtime($key)) return;

    if (is_array($val)) {
       $val=join("\n",array_keys($val))."\n";
    } else {
       $val=str_replace("\r","",$val);
    }
    $this->_save($key,$val);
  }

  function _save($key,$val) {
    umask(011);
    $fp=fopen($key,"w+");
    fwrite($fp,$val);
    fclose($fp);
  }

  function fetch($pagename,$mtime="") {
    $key=$this->getKey($pagename);
    if ($this->_exists($key)) {
       if (!$mtime) {
          return $this->_fetch($key);
       }
       else if ($this->_mtime($key) > $mtime)
          return $this->_fetch($key);
    }
    return -1;
  }

  function exists($pagename) {
    $key=$this->getKey($pagename);
    return $this->_exists($key);
  }

  function _exists($key) {
    return file_exists($key);
  }

  function _fetch($key) {
    $fp=fopen($key,"r");
    $content=fread($fp,filesize($key));
    fclose($fp);
    return $content;
  }

  function _mtime($key) {
    return filemtime($key);
  }

  function mtime($pagename) {
    $key=$this->getKey($pagename);
    if ($this->_exists($key))
       return $this->_mtime($key);
    return 0;
  }

#  function needsUpdate($pagename) {
#    $key=$this->getKey($pagename);
#  }

  function remove($pagename) {
    $key=$this->getKey($pagename);
    if ($this->_exists($key))
       unlink($key);
  }
}

class WikiPage {
  var $fp;
  var $filename;
  var $rev;
  function WikiPage($name,$options="") {
    if ($options[rev])
      $this->rev=$options[rev];
    else
      $this->rev=0; # current rev.
    $this->name = $name;
    $this->filename = $this->_filename($name);
    $this->urlname = $this->_urlname($name);
    $this->body = "";
  }

  function _urlname($pagename) {
    # MoinMoin style, have to be merged with WikiDB XXX
    $name=rawurlencode($pagename);
    $urlname=preg_replace('/%2F/i','/',$name);
    return $urlname;
  }

  function _filename($pagename) {
    # have to be factored out XXX
    # Return filename where this word/page should be stored.
    global $DBInfo;
    return $DBInfo->getPageKey($pagename);
  }

  function exists() {
    # Does a page for the given word already exist?
    return file_exists($this->filename);
  }

  function writable() {
    # True if page can be changed
    return is_writable($this->filename) or !$this->exists();
  }

  function mtime () {
    return @filemtime($this->filename);
  }

  function get_raw_body($options='') {
    if ($this->body && !$options[rev])
       return $this->body;

    if (!$this->exists()) return '';

    if ($this->rev || $options[rev]) {
       if ($options[rev]) $rev=$options[rev];
       else $rev=$this->rev;
       $fp=@popen("co -q -p'".$rev."' ".$this->filename,"r");
       if (!$fp)
          return "";
       while (!feof($fp)) {
          $line=fgets($fp,2048);
          $out.= $line;
       }
       pclose($fp);
       return $out;
    }

    $fp=@fopen($this->filename,"r");
    if (!$fp) {
       print $this->filename;
       return "";
    }
    $this->fsize=filesize($this->filename);
#    $body="";
#    if ($fp) {
#       while($line=fgets($fp, 2048))
#          $body.=$line;
#    }
#    $this->$body=implode("", file($this->filename));
#    $this->body=$body;
    $this->$body=fread($fp,$this->fsize);
    fclose($fp);

    return $this->$body;
  }

  function _get_raw_body() {
    $fp=@fopen($this->filename,"r");
    $size=filesize($this->filename);
    $body=fread($fp,$size);
    fclose($fp);

    return $body;
  }

  function set_raw_body($body) {
    $this->body=$body;
  }

  function update() {
    if ($this->body)
       $this->write($this->body);
  }

  function write($body) {
    if ($body)
       $this->body=$body;
  }
}


class Formatter {
 var $sister_idx=1;

 function Formatter($page="",$options="") {
   $this->page=$page;
   $this->head_num=1;
   $this->head_dep=0;
   $this->toc=0;
   $this->highlight="";
   $this->prefix=get_scriptname();

   $this->sister_on=1;
   $this->sisters=array();
   $this->foots=array();
   $this->gen_pagelinks=1;

   #
   $punct="<\"\'}\]\|\;\,\.\!";
   $url="http|ftp|telnet|mailto|wiki";
   $urlrule="((?:$url):[^\s$punct]+(\.?[^\s$punct]+)+)";
   # solw slow slow
   #(?P<word>(?:/?[A-Z]([a-z0-9]+|[A-Z]*(?=[A-Z][a-z0-9]|\b))){2,})
#             "(?<!\!|\[\[|[a-z])(([A-Z]+[a-z0-9]+){2,})(?!([a-z0-9]))|".
   $this->wordrule="(\[($url):[^\s\]]+(\s[^\]]*)+\])|".
             "(\!([A-Z]+[a-z0-9]+){2,})(?!(:|[a-z0-9]))|".
             "(?<!\!|\[\[|[a-z])((?:\/?[A-Z]([a-z0-9]+|[A-Z]*(?=[A-Z][a-z0-9]|\b))){2,})(?!([a-z0-9]))|".
             "(?<!\[)\[([^\[:,\s\d][^\[:,]+)\](?!\])|".
             "(?<!\[)\[\\\"([^\[:,]+)\\\"\](?!\])|".
             "($urlrule)|".
             "(\?[a-z0-9]+)";
 }

 function highlight_repl($val) {
   if ($val[0]=="<") return str_replace("\\\"",'"',$val);
   return "<strong class='highlight'>$val</strong>";
 }

 function link_repl($url) {
   global $DBInfo;

#   print $url.";";
   $url=str_replace("\\\"",'"',$url);
   #$url=str_replace("\\\\\"",'"',$url);
   if ($url[0]=="[")
      $url=substr($url,1,-1);
   if ($url[0]=="{") {
      $url=substr($url,3,-3);
      return "<tt class='wiki'>$url</tt>"; # No link
   } else if ($url[0]=="[") {
      $url=substr($url,1,-1);
      return $this->macro_repl($url); # No link
   }

   if ($url[0]=="!") {
      $url[0]=" ";
      return $url;
   } else
   if (preg_match("/:/",$url)) {
     if (preg_match("/^mailto:/",$url)) {
       $url=str_replace("@","_at_",$url);
       $name=substr($url,7);
       return $DBInfo->icon[mailto]."<a href='$url'>$name</a>";
     } else
     if (preg_match("/^wiki:/",$url)) {
       if (preg_match("/\s/",$url)) { # have a space ?
         $dum=explode(" ",$url,2);
         return $this->interwiki_repl($dum[0],$dum[1]);
       }
        return $this->interwiki_repl($url);
     } else
     if (preg_match("/\s/",$url)) { # have a space ?
       $dum=explode(" ",$url,2);
       return $DBInfo->icon[www]. "<a href='$dum[0]'>$dum[1]</a>";
     } else
     if (preg_match("/^(http|ftp)/",$url)) {
       if (preg_match("/(png|gif|jpeg|jpg)$/i",$url))
         return "<img src='$url' />";
       return "<a href='$url'>$url</a>";
     }
   } else {
     if ($url[0]=="?") $url=substr($url,1);
     return $this->word_repl($url);
   }
 }

 function interwiki_repl($url,$text="") {
   global $DBInfo;

   if ($url[0]=="w")
      $url=substr($url,5);
   $dum=explode(":",$url);
   $wiki=$dum[0]; $page=$dum[1];
   if (!$page) {
      $dum1=explode("/",$url);
      $wiki=$dum1[0]; $page=$dum1[1];
   }
   if (!$page) {
      $wiki="Self"; $page=$dum[0];
   }

   if (!$text) $text=$page;

   if (!$DBInfo->interwiki[$wiki]) return "$wiki:$page";

   $page=trim($page);

   if ($this->gen_pagelinks) $this->add_pagelinks($page);

   $img=strtolower($wiki);
   return "<img src='$DBInfo->imgs_dir/$img-16.png' width='16' height='16' align='middle' alt='$wiki:'/>".
  "<a href='".$DBInfo->interwiki[$wiki]."$page' title='$wiki:$page'>$text</a>";
 }

 function add_pagelinks($word) {
   if (!$this->pagelinks[$word] && $word != "TwinPages")
      $this->pagelinks[$word]=1;
 }

 function store_pagelinks() {
   $cache= new Cache_text("pagelinks");
   $cache->update($this->page->name,$this->pagelinks,$this->page->mtime());
 }

 function get_pagelinks() {
   $cache= new Cache_text("pagelinks");
   if ($cache->exists($this->page->name)) {
      $links=$cache->fetch($this->page->name);
      if ($links != -1) return $links;
   }
   if ($this->page->exists()) {
     $body=$this->page->get_raw_body();
     $save=$this->gen_pagelinks;
     $this->gen_pagelinks=1;
     preg_replace("/(".$this->wordrule.")/e","\$this->link_repl('\\1')",$body);
     $this->gen_pagelinks=$save;
     $this->store_pagelinks();
     if ($this->pagelinks) {
        $links=join("\n",array_keys($this->pagelinks))."\n";
        unset($this->pagelinks);
        return $links;
     }
   }
   return "";
 }

 function word_repl($word) {
   global $DBInfo;
   if ($word[0]=='"') { # ["extended wiki name"]
      $page=substr($word,1,-1);
      $word=$page;
   } else
      $page=preg_replace("/\s+/","",$word);

   if ($word[0]=='/')
      $page=$this->page->name.$page;

   if ($this->gen_pagelinks) $this->add_pagelinks($page);

   $url=$this->link_url($page);

   if ($DBInfo->hasPage($page)) {
      return "<a href='$url'>$word</a>";
   } else {
      if ($this->sister_on)
         $sisters=$DBInfo->metadb->getSisterSites($word);
      if ($sisters) {
         if (!$this->sister[$word]) {
            $this->sisters[]="<tt class='foot'><sup>&#160;&#160;&#160;".
                  "<a name='sister$this->sister_idx'/>".
                  "<b>$this->sister_idx)</b>&#160;</sup></tt> ".
                  "$sisters<br/>";
            $this->sister[$word]=$this->sister_idx++;
         }
         $idx=$this->sister[$word];
         return "<a href='$url'>$word</a>".
                "<sup><a href='#sister$idx'>$idx)</a></sup>";
      }
      return "<a href='$url'>?</a>$word";
   }
 }

 function head_repl($left,$head,$right) {
   $dep=strlen($left);
   if ($dep != strlen($right)) return "$left $head $right";

   $depth=$dep;
   if ($dep==1) $depth++; # depth 1 is regarded same as depth 2
   $depth--;

   $num="".$this->head_num;
   $odepth=$this->head_dep;
#   $open="";
#   $close="";

   if ($odepth && ($depth > $odepth)) {
#      $open.="<dd><dl>\n"; 
      $num.=".1";
   } else if ($odepth) {
      $dum=explode(".",$num);
      $i=sizeof($dum)-1;
      while ($depth < $odepth) {
         unset($dum[$i]);
         $i--;
         $odepth--;
#         $close.="</dl></dd>\n"; 
      }
      $dum[$i]++;
      $num=join($dum,".");
   }

   $this->head_dep=$depth; # save old
   $this->head_num=$num;

   if ($this->toc)
      $head="<a href='#toc'>$num</a> $head";

   return "<h$dep><a id='s$num' name='s$name' /> $head</h$dep>";
 }

 function macro_repl($macro) {
   preg_match("/^([A-Za-z]+)(\((.*)\))?$/",$macro,$match);
   $name=$match[1]; $option=$match[3];

   if (!function_exists ("macro_".$name))
      return "[[".$name."]]";
   eval("\$ret=macro_$name(&\$this,\$option);");
   return $ret;
 }

 function smiley_repl($smiley) {
   global $DBInfo;

   $img=$DBInfo->smileys[$smiley][3];

   $alt=str_replace("<","&lt;",$smiley);

   return "<img src='$DBInfo->imgs_dir/$img' align='middle' alt='$alt' border='0' />";
 }

 function link_url($pageurl,$query_string="") {
   global $DBInfo;
   $sep=$DBInfo->query_prefix;

   if ($sep == '?') {
     if ($pageurl && $query_string[0]=='?')
       # add 'dummy=1' to work around the buggy php
       $query_string= '&amp;'.substr($query_string,1).'&amp;dummy=1';
     $query_string= $pageurl.$query_string;
   } else
     $query_string= $pageurl.$query_string;
   return sprintf("%s%s%s", $this->prefix, $sep, $query_string);
 }

 function link_tag($pageurl,$query_string="", $text="",$attr="") {
   # Return a link with given query_string.
   if (!$text)
     $text= $pageurl; # XXX
   $url=$this->link_url($pageurl,$query_string);
   return sprintf("<a href='%s' %s>%s</a>", $url, $attr, $text);
 }

 function link_to($query_string="",$text="",$attr="") {
   if (!$text)
      $text=$this->page->name;
   return $this->link_tag($this->page->urlname,$query_string,$text,$attr);
 }

 function _list($on,$list_type,$numtype="",$close="") {
   if ($list_type=="dd") {
      if ($on)
         $list_type="dl><dd";
      else
         $list_type="dd></dl";
   } else if (!$on && $close !=1)
      $list_type=$list_type."></li";
   if ($on) {
      if ($numtype) {
         $start=substr($numtype,1);
         if ($start)
            return "<$list_type type='$numtype[0]' start='$start'>";
         return "<$list_type type='$numtype[0]'>";
      }
      return "<$list_type>\n";
   } else {
      return "</$list_type>\n";
   }
 }

 function _table_span($str) {
   $len=strlen($str)/2;
   if ($len > 1) {
      return " align='center' colspan='$len'";
   }
   return "";
 }

 function _table($on,$attr="") {
   if ($on)
      return "<table class='wiki' cellpadding='3' cellspacing='2' $attr>\n";
   else
      return "</table>\n";
 }

 function send_page($body="") {
   global $DBInfo;
   # get body

   if (!$body) {
      $twins=$DBInfo->metadb->getTwinPages($this->page->name);
      $body=$this->page->get_raw_body();
      if ($body)
        $lines=explode("\n",$body);
      else
        $lines=array();
      if ($twins) {
         if ($lines) $lines[]="----";
         $lines[]=$twins;
      }
   } else
      $lines=explode("\n",$body);

   if (!$lines) return;

   # get smily_rule
   if ($DBInfo->smileys) {
     $smiley_rule='/(?:\s|^)('.$DBInfo->smiley_rule.')(?:\s|$)/e';
     $smiley_repl="\$this->smiley_repl('\\1')";
   }

   $text="";
   $in_pre=0;
   $in_p=0;
   $in_li=0;
   $li_open=0;
   $in_table=0;
   $indent_list[0]=0;
   $indent_type[0]="";

   $wordrule="({{{([^}]+)}}})|".
             "\[\[([A-Za-z0-9]+(\(((?<!\]\]).)*\))?)\]\]|". # macro
             $this->wordrule;

   foreach ($lines as $line) {
      # strip trailing '\n'
      $line=preg_replace("/\n$/", "", $line);

#      if ($line=="" && $indlen) {continue;}
      if ($line=="" && $in_pre) {$text.="\n";continue;}
#      if ($line=="" && $in_p && !$in_table) {$in_p=0; $text.="<p>\n";continue;}
      if ($line=="" && !$in_li && !$in_table) {
         if (!$in_p) { $text.="<div>"; $in_p=1; continue;}
         if ($in_p==2) { $text.="</div><br />\n<div>"; $in_p=1; continue;}
         if ($in_p) { $text.="<br />\n"; $in_p=2; continue;}
      } else if ($in_p == 1) $in_p= 2;
      if (substr($line,0,2)=="##") continue; # comment

      if ($in_pre == 0 && preg_match("/{{{[^}]*$/",$line)) {
         $p=strpos($line,"{{{");
         $len=strlen($line);

         $this->processor="";
         $in_pre=1;

         # check processor
         if ($line[$p+3] == "#" && $line[$p+4] == "!") {
            $dummy=explode(" ",substr($line,$p+5),2);

            if (function_exists("processor_".$dummy[0])) {
              $this->processor=$dummy[0];
            }
         } else if ($line[$p+3] == ":") {
            $line[$p+3]=" ";
            $in_quote=1;
         }

         if ($in_pre) {
            $this->pre_line=substr($line,$p+3);
            if (trim($this->pre_line))
               $this->pre_line.="\n";
            $line=substr($line,0,$p);
         }
      } else if ($in_pre && preg_match("/}}}/",$line)) {
         $p=strrpos($line,"}}}");
         $len=strlen($line);
         if ($in_pre) {
            $this->pre_line.=substr($line,0,$p-2);
            $line=substr($line,$p+1);
            $in_pre=-1;
#         } else {
#            $line=substr_replace($line,"</pre>xx",$p-3,$p);
#            $in_pre=0;
         }
      } else if ($in_pre) {
         $this->pre_line.=$line."\n";
         continue;
      }
      if (!$in_pre) {
      #$line=preg_replace("/\\$/","&#36;",$line);
      $line=preg_replace("/<([^\s][^>]*)>/","&lt;\\1>",$line);
      $line=preg_replace("/`([^`]*)`/","<tt class='wiki'>\\1</tt>",$line);

      # bold
      $line=preg_replace("/'''([^']*)'''/","<b>\\1</b>",$line);
      $line=preg_replace("/(?<!')'''(.*)'''(?!')/","<b>\\1</b>",$line);
      # italic 
      $line=preg_replace("/''([^']*)''/","<i>\\1</i>",$line);
      $line=preg_replace("/(?<!')''(.*)''(?!')/","<i>\\1</i>",$line);

      # Superscripts, subscripts
      $line=preg_replace("/\^([^ \^]+)\^/","<sup>\\1</sup>",$line);
      $line=preg_replace("/(?: |^)_([^ _]+)_/","<sub>\\1</sub>",$line);

      $line=preg_replace("/^-{4,}/","<hr />\n",$line);

      # Smiley
      if ($smiley_rule)
         $line=preg_replace($smiley_rule,$smiley_repl,$line);

      # bullet
      if (!$in_pre && preg_match("/^(\s*)/",$line,$match)) {
         $open="";
         $close="";
         $indtype="dd";
         $indlen=strlen($match[0]);
         #print "<!-- indlen=$indlen -->\n";
         if ($indlen > 0) {
            $line=substr($line,$indlen);
            if (preg_match("/^(\*\s*)/",$line,$limatch)) {
               $line=preg_replace("/^(\*\s*)/","<li>",$line);
               if ($indent_list[$in_li] == $indlen) $line="</li>\n".$line;
               $numtype="";
               $indtype="ul";
            #} else if (preg_match("/^((\d+|[aAiI])\.)/",$line,$limatch)) {
            } else if (preg_match("/^((\d+|[aAiI])\.)(#\d+)?/",$line,$limatch)) {
               $line=preg_replace("/^((\d+|[aAiI])\.(#\d+)?)/","<li>",$line);
               if ($indent_list[$in_li] == $indlen) $line="</li>\n".$line;
               $numtype=$limatch[2];
               if ($limatch[3])
                  $numtype.=substr($limatch[3],1);
               $indtype="ol";
            }
         }
         if ($indent_list[$in_li] < $indlen) {

            $in_li++;
            $indent_list[$in_li]=$indlen; # add list depth
            $indent_type[$in_li]=$indtype; # add list type
            $open.=$this->_list(1,$indtype,$numtype);
         } else if ($indent_list[$in_li] > $indlen) {
#            if ($indent_type[$in_li]!='dd' && $li_open) $close="xxx</li>\n".$close;
            while($in_li >= 0 && $indent_list[$in_li] > $indlen) {
               if ($indent_type[$in_li]!='dd' && $li_open == $in_li) $close.="</li>\n";
               $close.=$this->_list(0,$indent_type[$in_li],"",$in_li);
               unset($indent_list[$in_li]);
               unset($indent_type[$in_li]);
               $in_li--;
            }
         }
         if ($indent_list[$in_li] <= $indlen || $limatch) $li_open=$in_li;
         else $li_open=0;
      }

      if (!$in_pre && !$in_table && preg_match("/^\|\|.*\|\|$/",$line)) {
         $open.=$this->_table(1);
         $in_table=1;
      } else if ($in_table && !preg_match("/^\|\|.*\|\|$/",$line)) {
         $close=$this->_table(0).$close;
         #$close.=$this->_table(0);
         $in_table=0;
      }
      if ($in_table) {
         $line=preg_replace('/^((?:\|\|)+)(.*)\|\|$/e',"'<tr class=\"wiki\"><td class=\"wiki\"'.\$this->_table_span('\\1').'>\\2</td></tr>'",$line);
         $line=preg_replace('/((\|\|)+)/e',"'</td><td class=\"wiki\"'.\$this->_table_span('\\1').'>'",$line);
         $line=str_replace('\"','"',$line); # revert \\" to \"
      }
      $line=$close.$open.$line;
      $open="";$close="";

      # InterWiki
      $rule="/(?<!wiki:)(".$DBInfo->interwikirule."):([^<>\s\'\/]{1,2}[^<>\s\']+[\s]{0,1})/";
      $repl="wiki:\\1:\\2";
      $line=preg_replace($rule, $repl, $line);
      # WikiName, {{{ }}}, !WikiName, ?single, ["extended wiki name"]
      # urls, [single bracket name], [urls text], [[macro]]
      $line=preg_replace("/(".$wordrule.")/e","\$this->link_repl('\\1')",$line);

      # Headings
      $line=preg_replace('/(?<!=)(={1,5})\s+(.*)\s+(={1,5})$/e',
                         "\$this->head_repl('\\1','\\2','\\3')",$line);
      }
      if ($in_pre==-1) {
         $in_pre=0;
         if ($this->processor) {
             eval("\$out=processor_$this->processor(&\$this,\$option);");
            $line=$out.$line;
         } else if ($in_quote) {
            # htmlfy '<'
            $pre=preg_replace("/</","&lt;",$this->pre_line);
            $pre=preg_replace("/(".$wordrule.")/e","\$this->link_repl('\\1')",$pre);
            $line="<pre class='quote'>\n".$pre."</pre>\n".$line;
            $in_quote=0;
         } else {
            # htmlfy '<'
            $pre=preg_replace("/</","&lt;",$this->pre_line);
            $line="<pre class='wiki'>\n".$pre."</pre>\n".$line;
         }
      }
      $text.=$line."\n";
   }
   # strip slash only for double quotes
   $text=str_replace('\"','"',$text);

   # highlight text
   if ($this->highlight)
      $text=preg_replace('/((<[^>]*>)|('.$this->highlight.'))/ie',
                         "\$this->highlight_repl('\\1')",$text);

   # close all tags
   $close="";
   # close pre,table,p
   if ($in_pre) $close.="</pre>\n";
   if ($in_table) $close.="</table>\n";
   if ($in_p) $close.="</div>\n";
   # close indent
   while($in_li >= 0 && $indent_list[$in_li] > 0) {
      $close.=$this->_list(0,$indent_type[$in_li]);
      unset($indent_list[$in_li]);
      unset($indent_type[$in_li]);
      $in_li--;
   }

   $text.=$close;
  
   print $text;
   if ($this->sisters) {
      $sisters=join("\n",$this->sisters);
      $sisters=preg_replace("/(".$wordrule.")/e","\$this->link_repl('\\1')",$sisters);
      print "<div id='wikiSister'>\n<br/><tt class='foot'>----</tt><br/>\nSister Sites Index<br />\n$sisters</div>\n";
   }

   $this->store_pagelinks();
 }

 function _parse_rlog($log) {
   $lines=explode("\n",$log);
   $state=0;
   $flag=0;

   $url=$this->link_url($this->page->name);

   $out="<h2>Revision History</h2>\n";
   $out.="<table class='info' border='0' cellpadding='3' cellspacing='2'>\n";
   $out.="<form method='post' action='$url'>";
   $out.="<th class='info'>#</th><th class='info'>Date and Changes</th>".
         "<th class='info'>Editor</th>".
         "<th><input type='submit' value='diff'></th>".
         "<th class='info'>actions</th>".
         "<th class='info'>admin.</th>";
         #"<th><input type='submit' value='admin'></th>";
   $out.= "</tr>\n";
   
   foreach ($lines as $line) {
      if (!$state) {
        if (!preg_match("/^---/",$line)) { continue;}
        else {$state=1; continue;}
      }
      
      switch($state) {
        case 1:
           preg_match("/^revision ([0-9\.]*)/",$line,$match);
           $rev=$match[1];
           $state=2;
           break;
        case 2:
           $inf=preg_replace("/date:\s(.*)author:.*;\s+state:.*;/","\\1",$line);
           $state=3;
           break;
        case 3:
           $dummy=explode(";;",$line);
           $ip=$dummy[0];
           $user=$dummy[1];
           $comment=$dummy[2];
           $state=4;
           break;
        case 4:
           $rowspan=1;
           if ($comment) $rowspan=2;
           $out.="<tr>\n";
           $out.="<th rowspan=$rowspan>r$rev</th><td>$inf</td><td>$ip&nbsp;</td>";
           $achecked="";
           $bchecked="";
           if ($flag==1)
              $achecked="checked ";
           else if (!$flag)
              $bchecked="checked ";
           $out.="<td><input type='radio' name='rev' value='$rev' $achecked/>";
           $out.="<input type='radio' name='rev2' value='$rev' $bchecked/>";

           $out.="<td>".$this->link_to("?action=recall&rev=$rev","view").
                 " ".$this->link_to("?action=raw&rev=$rev","raw");
           if ($flag)
              $out.= " ".$this->link_to("?action=diff&rev=$rev","diff");
           $out.="</td><th>";
           if ($flag)
              $out.="<input type='checkbox' name='range[$flag]' value='$rev' />";
           $out.="</th></tr>\n";
           if ($comment)
              $out.="<tr><td colspan=3>$comment&nbsp;</td></tr>\n";
           $state=1;
           $flag++;
           break;
      }
   }
   $out.="<tr><td colspan='6' align='right'><input type='checkbox' name='show' checked='checked' />show only <input type='password' name='passwd'>";
   $out.="<input type='submit' name='button_admin' value='purge'></td></tr>";
   $out.="<input type='hidden' name='action' value='diff'/></form></table>\n";
   return $out; 
 }

 function show_info() {
   $fp=popen("rlog ".$this->page->filename,"r");
#   if (!$fp)
#      print "No older revisions available";
# XXX
   while (!feof($fp)) {
      $line=fgets($fp,1024);
      $out .= $line;
   }
   pclose($fp);
   if (!$out)
      print "<h2>No older revisions available</h2>";
   else
      print $this->_parse_rlog($out);
 }

 function _parse_diff($diff) {
   $diff=str_replace("<","&lt;",$diff);
   $lines=explode("\n",$diff);
   $out="";
   unset($lines[0]); unset($lines[1]);
   foreach ($lines as $line) {
      $marker=$line[0];
      $line=substr($line,1);
      if ($marker=="@") $line='<div class="diff-sep">@'."$line</div>";
      else if ($marker=="-") $line='<div class="diff-removed">'."$line</div>";
      else if ($marker=="+") $line='<div class="diff-added">'."$line</div>";
      else if ($marker=="\\" && $line==" No newline at end of file") continue;
      else $line.="<br />";
      $out.=$line."\n";
   }
   return $out;
 }

 function get_rev() {
   $fp=popen("rlog ".$this->page->filename,"r");
#   if (!$fp)
#      print "No older revisions available";
# XXX
   while (!feof($fp)) {
      $line=fgets($fp,1024);
      preg_match("/^head:\s+(\d+)\.(\d+)$/",$line,$match);
      if ($match[2]) {
         $rev=$match[1].".".($match[2]-1);
         break;
      }
   }
   pclose($fp);
   if ($rev > 1.0)
      return $rev;
   return '';
 }

 function get_merge($text,$rev="") {
   global $DBInfo;

   if (!$text) return '';
   # save new
   $tmpf3=tempnam($DBInfo->vartmp_dir,"MERGE_NEW");
   $fp= fopen($tmpf3, "w");
   fwrite($fp, $text);
   fclose($fp);

   # recall old rev
   $opts[rev]=$this->get_rev();
   
   $orig=$this->page->get_raw_body($opts);
   $tmpf2=tempnam($DBInfo->vartmp_dir,"MERGE_ORG");
   $fp= fopen($tmpf2, "w");
   fwrite($fp, $orig);
   fclose($fp);

   $fp=popen("merge -p ".$this->page->filename." $tmpf2 $tmpf3","r");

   if (!$fp) {
      unlink($tmpf2);
      unlink($tmpf3);
      return '';
   }
   while (!feof($fp)) {
      $line=fgets($fp,1024);
      $out .= $line;
   }
   pclose($fp);
   unlink($tmpf2);
   unlink($tmpf3);

   $out=preg_replace("/(<{7}|>{7}).*\n/","\\1\n",$out);

   return $out;
 }

 function get_diff($rev1="",$rev2="",$text="") {
   global $DBInfo;
   $option="";

   if ($text) {
      $tmpf=tempnam($DBInfo->vartmp_dir,"DIFF");
      $fp= fopen($tmpf, "w");
      fwrite($fp, $text);
      fclose($fp);

      $fp=popen("diff -u $tmpf ".$this->page->filename,"r");
      if (!$fp) {
         unlink($tmpf);
         return;
      }
      while (!feof($fp)) {
         $line=fgets($fp,1024);
         $out .= $line;
      }
      pclose($fp);
      unlink($tmpf);

      if (!$out)
         print "<h2>No difference found</h2>";
      else {
         print "<h2>Difference between yours and the current</h2>";
         print $this->_parse_diff($out);
      }
      return;
   }

   if (!$rev1 and !$rev2) {
      $rev1=$this->get_rev();
   }
   else if ($rev1==$rev2) $rev2="";
   if ($rev1) $option="-r$rev1 ";
   if ($rev2) $option.="-r$rev2 ";

   if (!$option) {
      print "<h2>No older revisions available</h2>";
      return;
   }
   $fp=popen("rcsdiff -u $option ".$this->page->filename,"r");
   if (!$fp)
      return;
   while (!feof($fp)) {
      $line=fgets($fp,1024);
      $out .= $line;
   }
   pclose($fp);
   if (!$out)
      print "<h2>No difference found</h2>";
   else {
      if ($rev1==$rev2) print "<h2>Difference between versions</h2>";
      else if ($rev1 and $rev2) print "<h2>Difference between r$rev1 and r$rev2</h2>";
      else if ($rev1 or $rev2) print "<h2>Difference between r$rev1$rev2 and the current</h2>";
      print $this->_parse_diff($out);
   }
 }

  function send_header($header="",$options=array()) {
    global $DBInfo;
    $plain=0;
    if ($header) {
      if (is_array($header))
        foreach ($header as $head) {
          header($head);
          if (preg_match("/^content\-type: text\/plain/i",$head)) {
             $plain=1;
          }
        }
      else
        header($header);
    }

#  if ($DBInfo->embeded) return;
   if (!$plain) {
      if (!$options[title]) $options[title]=$this->page->name;
      if (!$options[css_url]) $options[css_url]=$DBInfo->css_url;
      print <<<EOS
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<!-- <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd"> -->
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
  <meta http-equiv="Content-Type" content="text/html;charset=$DBInfo->charset" /> 
  <meta name="ROBOTS" content="NOINDEX,NOFOLLOW" />
  <title>$options[title]</title>\n
EOS;
      if ($options[css_url])
         print '<link rel="stylesheet" type="text/css" href="'.
               $options[css_url].'"/>';
# default CSS
      else print <<<EOS
<style type="text/css">
<!--
body {font-family:Georgia,Verdana,Lucida,sans-serif;font-size:14px; background-color:#FFF9F9;}
a:link {color:#993333;}
a:visited {color:#CE5C00;}
a:hover {background-color:#E2ECE5;color:#000;}
.title {
  font-family:palatino, Georgia,Tahoma,Lucida,sans-serif;
  font-size:28px;
  font-weight:bold;
  color:#639ACE;
  text-decoration: none;
}
tt.wiki {font-family:Lucida Typewriter,fixed,lucida,fixed;font-size:12px;}
tt.foot {font-family:Tahoma,lucida,fixed;font-size:12px;}

pre.wiki {
  padding-left:6px;
  padding-top:6px; 
  font-family:Lucida TypeWriter,monotype,lucida,fixed;font-size:14px;
  background-color:#000000;
  color:#FFD700; /* gold */
}

textarea.wiki {
  font-family:Georgia,monotype,lucida,fixed;font-size:14px;
}

pre.quote {
  padding-left:6px;
  padding-top:6px;
  white-space:pre-wrap;
  white-space: -moz-pre-wrap; 
  font-family:Georgia,monotype,lucida,fixed;font-size:14px;
  background-color:#F7F8E6;
}

table.wiki {
/* background-color:#E2ECE5;*/
/* border-collapse: collapse; */
  border: 0px outset #E2ECE5;
}

td.wiki {
  background-color:#E2ECE2;
/* border-collapse: collapse; */
  border: 0px inset #E2ECE5;
}

th.info {
  background-color:#E2ECE2;
/*  border-collapse: collapse; */
/*  border: 1px solid silver; */
}

h1,h2,h3,h4,h5 {
  font-family:Tahoma;
/* background-color:#E07B2A; */
  padding-left:6px;
  border-bottom:1px solid #999;
}

div.diff-added {
   font-family:Verdana,Lucida Sans TypeWriter,Lucida Console,fixed;
   font-size:12px;
   background-color:#61FF61;
   color:black;
}

div.diff-removed {
   font-family:Verdana,Lucida Sans TypeWriter,Lucida Console,fixed;
   font-size:12px;
   background-color:#E9EAB8;
   color:black;
}

div.diff-sep {
   font-family:georgia,Verdana,Lucida Sans TypeWriter,Lucida Console,fixed;
   font-size:12px;
   background-color:#000000;
   color:#FFD700; /* gold */
}

td.message {
    margin-top: 6pt;
    background-color: #E8E8E8;
    border-style:solid;
    border-width:1pt;
    border-color:#990000;
    color:#440000;
    padding:0px;
    width:100%;
}

.hint {
   font-family:Georgia,Verdana,Lucida,sans-serif;
   font-size:10px;
   background-color:#E2DAE2;
}

.highlight {
   background-color:#FFFF40;
}
//-->

</style>
EOS;

    print "\n</head>\n<body>\n";
   }
 }

 function send_footer($options=array(),$timer="") {
   global $DBInfo;

   print "</div>\n";
   print $DBInfo->hr;
   print "<div id='wikiFooter'>";
   if ($options[html])
      print "$options[html]";
   else {
      if ($options[editable]) {
         if ($this->page->writable())
            print $this->link_to("?action=edit",'EditText')." | ";
         else
            print "NotEditable | ";
      }
      if ($options[showpage])
         print $this->link_to("",'ShowPage')." | ";
      print $this->link_tag("FindPage");
   }

   if (!$options[noaction])
      foreach ($DBInfo->actions as $action)
         print "|".$this->link_to("?action=$action",$action);

   print <<<FOOT
 <a href="http://validator.w3.org/check/referer"><img
  src="$DBInfo->imgs_dir/valid-xhtml10.png" border="0"
  align="middle" width="88" height="31"
  alt="Valid XHTML 1.0!" /></a>

 <a href="http://jigsaw.w3.org/css-validator/check/referer"><img
  src="$DBInfo->imgs_dir/vcss" 
  style="border:0;width:88px;height:31px"
  align="middle"
  alt="Valid CSS!" /></a>
FOOT;

   if ($timer)
      printf("<br />%7.4f",$timer->Check());
   
   print "\n</div>\n";

   if (!$DBInfo->embeded) print "</body>\n</html>\n";
 }

 function send_title($title="", $link="", $opts="") {
   // Generate and output the top part of the HTML page.
   global $DBInfo;

   $name=$this->page->name;

   $action=$this->link_url($name);

   # find upper page
   $pos=strrpos($name,"/");
   if ($pos > 0) $upper=substr($name,0,$pos);

   if (!$title) $title=$name;
   # setup title variables
   $title="<font class='title'><b>$title</b></font>";
   if ($link)
      $title="<a href=\"$link\" class='title'>$title</a>";
   else if (!$opts[nolink])
      $title=$this->link_to("?action=fullsearch&amp;value=$name",$title,"class='title'");
   $logo=$this->link_tag('RecentChanges','',$DBInfo->logo_string);
   $goto_form=goto_form($action);

   if ($opts[msg]) {
     $msg=<<<MSG
<table class="message" width="100%"><tr><td class="message">
$opts[msg]
</td></tr></table>
MSG;
   }

   $menu =$this->link_tag($DBInfo->frontpage,"",_($DBInfo->frontpage))." | ";
   $menu.=$this->link_tag("FindPage","",_("FindPage"))." | ";
   $menu.=$this->link_tag("TitleIndex","",_("TitleIndex"))." | ";
   $menu.=$this->link_tag("UserPreferences","",_("UserPreferences"));
   # icons
   $icons="";
   if ($upper)
      $icons.=$this->link_tag($upper,'',$DBInfo->icon[upper])." ";
   $icons.=$this->link_to("?action=edit",$DBInfo->icon[edit])." ";
   $icons.=$this->link_to("?action=diff",$DBInfo->icon[diff])." ";
   $icons.=$this->link_to("",$DBInfo->icon[show])." ";
   $icons.=$this->link_tag("FindPage",'',$DBInfo->icon[find])." ";
   $icons.=$this->link_to("?action=info",$DBInfo->icon[info])." ";
   $icons.=$this->link_tag("HelpContents",'',$DBInfo->icon[help])." ";
   $rss_icon.=$this->link_tag("RecentChanges","?action=rss_rc",$DBInfo->icon[rss])." ";

   # print the title
   kbd_handler();
   print "<div id='wikiHeader'>\n";

   if (file_exists("header.php")) {
      include "header.php";
   } else { #default header
      $header="<table width='100%' border='0' cellpadding='3' cellspacing='0'>";
      $header.="<tr>";
      if ($DBInfo->logo_string) {
         $header.="<td rowspan='2' width='10%' valign='top'>";
         $header.=$logo;
         $header.="</td>";
      }
      $header.="<td>$title</td>";
      $header.="</tr><tr><td>\n";
      $header.=$goto_form;
      $header.="</td></tr></table>\n";

      # menu
      print $header;
      print $menu." |".$icons.$rss_icon;
      print $msg;
   }
   print "</div>\n";
   print $DBInfo->hr;
   print "<div id='wikiBody'>\n";
 }

 function send_editor($text="",$options="") {
    global $HTTP_USER_AGENT;
    $COLS_MSIE = 80;
    $COLS_OTHER = 85;
    $cols = preg_match('/MSIE/', $HTTP_USER_AGENT) ? $COLS_MSIE : $COLS_OTHER;

    $rows=$options[rows] > 5 ? $options[rows]: 16;
    $cols=$options[cols] > 60 ? $options[cols]: $cols;

    $preview=$options[preview];
    $url=$this->link_url($this->page->name);

    if (!$this->page->exists()) {
       $options[linkto]="?action=edit&amp;template=";
       print "Use one of the following templates as an initial release :\n";
       print macro_TitleSearch($this,".*Template",$options);
       print "To create your own templates, add a page with a 'Template' suffix.\n";
    }

    if ($options[conflict])
       $extra='<input type="submit" name="button_merge" value="Merge" />';

    print "<a id='editor' name='editor' />\n";
    printf('<form method="post" action="%s">', $url);
    #printf('<form method="POST" action="%s/%s#preview">', get_scriptname(),$this->page->name);
    printf("<br />\n");
    print $this->link_to("?action=edit&amp;rows=".($rows-3),"ReduceEditor")." | ";
    print $this->link_tag('InterWiki')." | ";
    print $this->link_tag('HelpOnEditing');
    if ($preview)
       print "|".$this->link_to('#preview',"Skip to preview");
    printf("<br />\n");
    if ($text) {
      $raw_body = str_replace('\r\n', '\n', $text);
    } else if ($this->page->exists()) {
      $raw_body = str_replace('\r\n', '\n', $this->page->get_raw_body());
    } else if ($options[template]) {
      $p= new WikiPage($options[template]);
      $raw_body = str_replace('\r\n', '\n', $p->get_raw_body());
    } else
      $raw_body = sprintf("Describe %s here", $this->page->name);

    # for conflict check
    if ($options[datestamp])
       $datestamp= $options[datestamp];
    else
       $datestamp= $this->page->mtime();

    print <<<EOS
<textarea class="wiki" id="content" wrap="virtual" name="savetext"
 rows="$rows" cols="$cols" style="width:100%">$raw_body</textarea><br />
Summary of Change: <input name="comment" size="70" maxlength="70" style="width:200" /><br />
<input type="hidden" name="action" value="savepage" />
<input type="hidden" name="datestamp" value="$datestamp">
<input type="submit" value="Save" />&nbsp;
<input type="reset" value="Reset" />&nbsp;
<input type="submit" name="button_preview" value="Preview" />
$extra
</form>
EOS;
    print <<<EOS
<div id="WikiHint">
<b>Emphasis:</b> ''<i>italics</i>''; '''<b>bold</b>'''; '''''<b><i>bold italics</i></b>''''';
    ''<i>mixed '''<b>bold</b>''' and italics</i>''; ---- horizontal rule.<br />
<b>Headings:</b> = Title 1 =; == Title 2 ==; === Title 3 ===;
    ==== Title 4 ====; ===== Title 5 =====.<br />
<b>Lists:</b> space and one of * bullets; 1., a., A., i., I. numbered items;
    1.#n start numbering at n; space alone indents.<br />
<b>Links:</b> JoinCapitalizedWords; ["brackets and double quotes"];
    [bracketed words];
    url; [url]; [url label].<br />
<b>Tables</b>: || cell text |||| cell text spanning two columns ||;
    no trailing white space allowed after tables or titles.<br />
</div>
<a id='preview' name='preview' />
EOS;
 }

}

# Start Main
$Config=getConfig("config.php");

$DBInfo= new WikiDB($Config);
$user=new User();
$options=array();
$options[id]=$user->id;
if ($user->id != "Anonymous") {
  $udb=new UserDB($DBInfo);
  $user=$udb->getUser($user->id);
  $options[css_url]=$user->info[css_url];
#  $name=$user->info[name];
} else {
  if ($user->css)
    $options[css_url]=$user->css;
}

#setlocale(LC_ALL, 'ko');
if (!function_exists ('bindtextdomain')) {
     function gettext ($text) {
       return $text;
     }

     function _ ($text) {
       return $text;
     }
}
#    $locale = array();
#
#    function gettext ($text) {
#        global $locale;
#        if (!empty ($locale[$text]))
#            return $locale[$text];
#        return $text;
#    }
#
#    function _ ($text) {
#        return gettext($text);
#    }
#}
else {
   setlocale(LC_ALL, $DBInfo->lang);
   bindtextdomain("moniwiki", "locale");
   textdomain("moniwiki");
}

if (!empty($_SERVER[PATH_INFO])) {
   if ($_SERVER[PATH_INFO][0] == '/')
      $pagename=substr($_SERVER[PATH_INFO],1);
   if (!$pagename) {
      $pagename = $DBInfo->frontpage;
   }
} else if (!empty($_SERVER[QUERY_STRING])) {
   if (isset($goto)) $pagename=$goto;
   else {
     $pagename = $_SERVER[QUERY_STRING];
     $result = preg_match('/^([^&=]+)/',$pagename,$matches);
     if ($result) {
        $pagename = urldecode($matches[1]);
        $QUERY_STRING=substr($QUERY_STRING,strlen($pagename));
     }
   }
   if (!$pagename) $pagename= $DBInfo->frontpage;
} else {
   $pagename = $DBInfo->frontpage;
}

#print_r(array_keys($HTTP_GET_VARS));
#print_r($HTTP_GET_VARS);

$action=$HTTP_GET_VARS[action];
$value=$HTTP_GET_VARS[value];
$goto=$HTTP_GET_VARS[goto];

if ($_SERVER[REQUEST_METHOD]=="POST") {
 $request=$HTTP_POST_VARS;
 $action=$request[action];
 if ($action=="savepage") {
   $savetext=$request[savetext];
   $datestamp=$request[datestamp];
   $button_preview=$request[button_preview];
   $button_merge=$request[button_merge];

   $page = $DBInfo->getPage($pagename);
   $formatter = new Formatter($page);
   $formatter->send_header("",$options);

   $savetext=str_replace("\r", "", $savetext);
   $savetext=stripslashes($savetext);
   if ($savetext and $savetext[strlen($savetext)-1] != "\n")
     $savetext.="\n";
   $new=md5($savetext);

   if ($page->exists()) {
      # check difference
      $body=$page->get_raw_body();
      $body=str_replace("\r", "", $body);
      $orig=md5($body);
      # check datestamp
      if ($page->mtime() > $datestamp) {
         $opts[msg]=sprintf("Someone else saved the page while you edited %s",$formatter->link_tag($page->name));
         $formatter->send_title(_("Conflict error!"),"",$opts);
         $options[preview]=1; 
         $options[conflict]=1; 
         $options[datestamp]=$datestamp; 
         if ($button_merge) {
            $merge=$formatter->get_merge($savetext);
            if ($merge) $savetext=$merge;
            unset($options[datestamp]); 
         }
         $formatter->send_editor($savetext,$options);
         print $formatter->link_tag('GoodStyle')." | ";
         print $formatter->link_tag('InterWiki')." | ";
         print $formatter->link_tag('HelpOnEditing')." | ";
         print $formatter->link_to("#editor",_("Goto Editor"));
         print "<table border='1' align='center' width='100%'><tr><td>\n";
         $formatter->get_diff("","",$savetext);
         print "</td></tr></table>\n";
         $formatter->send_footer();
         return;
      }
   }

   if (!$button_preview && $orig == $new) {
      $opts[msg]="Go back or return to ".$formatter->link_tag($page->name);
      $formatter->send_title(_("No difference found"),"",$opts);
      $formatter->send_footer();
      return;
   }
   $formatter->page->set_raw_body($savetext);

   if ($button_preview) {
      $title=sprintf("Preview of %s",$formatter->link_tag($page->name));
      $formatter->send_title($title,"");
     
      $options[preview]=1; 
      $options[datestamp]=$datestamp; 
      $formatter->send_editor($savetext,$options);
      print $DBInfo->hr;
      #print "<hr d='wikiHr' />\n";
      print $formatter->link_tag('GoodStyle')." | ";
      print $formatter->link_tag('InterWiki')." | ";
      print $formatter->link_tag('HelpOnEditing')." | ";
      print $formatter->link_to("#editor",_("Goto Editor"));
      print "<div class='wikiPreview'>\n";
      print "<table border='1' align='center' width='95%'><tr><td>\n";
      $formatter->send_page();
      print "</td></tr></table>\n";
      print $DBInfo->hr;
      #print "<hr id='wikiHr' />\n";
      print "</div>\n";
      print $formatter->link_tag('GoodStyle')." | ";
      print $formatter->link_tag('InterWiki')." | ";
      print $formatter->link_tag('HelpOnEditing')." | ";
      print $formatter->link_to("#editor",_("Goto Editor"));
   } else {
      $page->write($savetext);
      $ret=$DBInfo->savePage($page);
      if ($ret == -1)
        $opts[msg]=$formatter->link_tag($page->name)." is not editable";
      else
        $opts[msg]=$formatter->link_tag($page->name)." is saved";
      $formatter->send_title("","",$opts);
      $formatter->send_page();
   }
   $args[showpage]=1;
   $args[editable]=0;
   $formatter->send_footer($args);

   exit;
   }
}

#if (!empty($QUERY_STRING))
#   $query= $QUERY_STRING;

if ($pagename) {

   if ($action=="recall" || $action=="raw" && $rev) {
       $options[rev]=$rev;
       $page = $DBInfo->getPage($pagename,$options);
   } else
       $page = $DBInfo->getPage($pagename);

   $formatter = new Formatter($page);

   if (!$action) {
      if ($value) { # ?value=Hello
        $options[value]=$value;
        do_goto($formatter,$options);
        return;
      } else if ($goto) { # ?goto=Hello
        $options[value]=$goto;
        do_goto($formatter,$options);
        return;
      }
      if (!$page->exists()) {
        $formatter->send_header(array("Status: 404 Not found"),$options);

        $twins=$DBInfo->metadb->getTwinPages($page->name);
        if ($twins) {
           $formatter->send_title($page->name);
           #$formatter->send_page($twins."\n----\n");
           $formatter->send_page($twins);
           print "<br /><br />or ".
             $formatter->link_to("?action=edit",_("Create this page"));
        } else {
           $formatter->send_title($page->name." Not Found");
           print $formatter->link_to("?action=edit",_("Create this page"));
           print macro_LikePages($formatter,$page->name);

           print "<hr />\n";
           print $formatter->link_to("?action=edit",_("Create this page"));
           print " or alternativly, use one of these templates:\n";
           $options[linkto]="?action=edit&amp;template=";
           print macro_TitleSearch($formatter,".*Template",$options);
           print "To create your own templates, add a page with a 'Template' suffix\n";
        }

        #$args[showpage]=1;
        $args[editable]=1;
        $formatter->send_footer($args);
        return;
      }
      $DBInfo->counter->incCounter($pagename);

      $formatter->send_header("",$options);
      $formatter->send_title();
      $formatter->send_page();
      #$args[showpage]=1;
      $args[editable]=1;
      $formatter->send_footer($args,$timing);
      return;
   }

   if ($action=="diff") {
      if ($button_admin) {
         if (!$range) $range=array();
         $rr='';
         $dum=array();
         foreach (array_keys($range) as $r) {
         #   print "[$r]-$range[$r]";
            if (!$rr) $rr=$range[$r];
            if ($range[$r+1]) continue;
            else
               $rr.=":".$range[$r];
            $dum[]=$rr;$rr='';
         }
         #print_r($dum);
         $options[passwd]=$passwd;
         $options[show]=$show;
         $options[range]=$dum;
         $options[page]=$page->name;
         $options[timer]=$timing;
         do_RcsPurge($formatter,$options);
         return;
      }
      $formatter->send_header("",$options);
      $formatter->send_title("Diff for $rev ".$page->name);
      print $formatter->get_diff($rev,$rev2);
      print "<br /><hr />\n";
      $formatter->send_page();
      $args[showpage]=1;
      #$args[editable]=1;
      $formatter->send_footer($args,$timing);

      return;
   }
   if ($action=="recall" || $action=="raw") {
     if ($action=="raw") {
        $header[]="Content-Type: text/plain";
        $formatter->send_header($header,$options);
     } else {
        $formatter->send_header("",$options);
        $formatter->send_title("Rev. $rev ".$page->name);
     }
     if (!$page->exists() || !$page->get_raw_body()) {
        if ($action=="raw") {
        } else {
           $formatter->send_footer();
        }
        return;
     }
     if ($action=="raw") {
        print $page->get_raw_body();
     } else {
        $formatter->send_page();
        $args[showpage]=1;
        #$args[editable]=1;
        $formatter->send_footer($args,$timing);
     }
     return;
   } else if ($action=="edit" && $page->writable()) {
     $formatter->send_header("",$options);
     $formatter->send_title("Edit ".$page->name);
     $options[rows]=$rows; 
     $options[cols]=$cols;
     $options[template]=$template;
     $formatter->send_editor("",$options);
     $args[showpage]=1;
     #$args[editable]=1;
     $formatter->send_footer($args);
   } else if ($action=="info") {
     $formatter->send_header("",$options);
     $formatter->send_title("Info. for ".$page->name);
     $formatter->show_info();
     $args[showpage]=1;
     #$args[editable]=1;
     $formatter->send_footer($args,$timing);
   } else if ($action=="DeletePage") {
     $options[page]=$page->name;
     $options[comment]=$comment;
     $options[passwd]=$passwd;
     do_DeletePage($formatter,$options);
   } else if ($action) {
     #print_r($HTTP_POST_VARS);
     #print_r($HTTP_GET_VARS);
     #print($login_id);

     if (function_exists("do_post_".$action)) {
        $options=array_merge($options,$HTTP_POST_VARS);
        $options[page]=$page->name;
        $options[timer]=$timing;
        eval("do_post_".$action."(\$formatter,\$options);");
     } else if (function_exists("do_".$action)) {
        if ($_SERVER[REQUEST_METHOD]=="POST")
           $options=array_merge($options,$HTTP_POST_VARS);
        else
           $options=array_merge($options,$HTTP_GET_VARS);
        $options[page]=$page->name;
        $options[timer]=$timing;
        eval("do_".$action."(\$formatter,\$options);");
     } else {
        $formatter->send_header("Status: 406 Not Acceptable",$options);
        $formatter->send_title("406 Not Acceptable");
        $args[editable]=1;
        # $formatter->send_page("");
        $formatter->send_footer($args);
        return;
     }
   }
}

?>
