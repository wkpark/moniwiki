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
//
$_revision = substr('$Revision$',1,-1);
$_release = '1.0rc17';

#ob_start("ob_gzhandler");

$timing=new Timer();

include("wikilib.php");

function _preg_escape($val) {
  return preg_replace('/([\$\^\.\[\]\{\}\|\(\)\+\*\/\\\\!\?]{1})/','\\\\\1',$val);
}

function _preg_search_escape($val) {
  return preg_replace('/([\/]{1})/','\\\\\1',$val);
}

function get_scriptname() {
  // Return full URL of current page.
  // $_SERVER["SCRIPT_NAME"] has bad value under CGI mode
  // set 'cgi.fix_pathinfo=1' in the php.ini under
  // apache 2.0.x + php4.2.x Win32
  return $_SERVER["SCRIPT_NAME"];
}

function _rawurlencode($url) {
  $name=rawurlencode($url);
  $urlname=preg_replace(array('/%2F/i','/%7E/i'),array('/','~'),$name);
  return $urlname;
}

function _urlencode($url) {
  #$name=urlencode(strtr($url,"+"," "));
  #return preg_replace(array('/%2F/i','/%7E/i','/%23/'),array('/','~','#'),$name);
  return preg_replace("/([^a-z0-9\/\?\.\+~#&:;=%]{1})/ie","'%'.strtoupper(dechex(ord('\\1')))",$url);
}

function qualifiedUrl($url) {
  if (substr($url,0,7)=="http://")
    return $url;
  return "http://$_SERVER[HTTP_HOST]$url";
}

function getPlugin($pluginname) {
  static $plugins=array();
  if ($plugins) return $plugins[strtolower($pluginname)];

  $handle= opendir('plugin');
  while ($file= readdir($handle)) {
    if (is_dir("plugin/$file")) continue;
    $name= substr($file,0,-4);
    $plugins[strtolower($name)]= $name;
  }
  return $plugins[strtolower($pluginname)];
}

function getProcessor($pro_name) {
  static $processors=array();
  if ($processors) return $processors[strtolower($pro_name)];

  $handle= opendir('plugin/processor');
  while ($file= readdir($handle)) {
    if (is_dir("plugin/processor/$file")) continue;
    $name= substr($file,0,-4);
    $processors[strtolower($name)]= $name;
  }
  return $processors[strtolower($pro_name)];
}

if (!function_exists ('bindtextdomain')) {
  $locale = array();

  function gettext ($text) {
    global $locale;
    if (!empty ($locale[$text]))
      return $locale[$text];
    return $text;
  }

  function _ ($text) {
    return gettext($text);
  }
}

function goto_form($action,$type="",$form="") {
  if ($type==1) {
    return "
<form name='go' id='go' method='get' action='$action'>
<span title='TitleSearch'>
<input type='radio' name='action' value='titlesearch' />
Title</span>
<span title='FullSearch'>
<input type='radio' name='action' value='fullsearch' />
Contents</span>&nbsp;
<input type='text' name='value' class='goto' accesskey='s' size='20' />
<input type='submit' name='status' value='Go' class='goto' style='width:23px' />
";
  } else if ($type==2) {
    return "
<form name='go' id='go' method='get' action='$action'>
<select name='action' style='width:60px'>
<option value='goto'/>&nbsp;&nbsp;&nbsp;
<option value='titlesearch'/>TitleSearch
<option value='fullsearch'/>FullSearch
</select>
<input type='text' name='value' accesskey='s' size='20' />
<input type='submit' name='status' value='Go' />
";
  } else if ($type==3) {
    return "
<form name='go' id='go' method='get' action='$action'>
<table class='goto'>
<tr><td nowrap='nowrap' style='width:220'>
<input type='text' name='value' size='28' accesskey='s' style='width:110px' />
<input type='submit' name='status' value='Go' class='goto' style='width:23px' />
</td></tr>
<tr><td>
<span title='TitleSearch' class='goto'>
<input type='radio' name='action' value='titlesearch' class='goto' />
Title(?)</span>
<span title='FullSearch' class='goto'>
<input type='radio' name='action' value='fullsearch' accesskey='s' class='goto'/>
Contents(/)</span>&nbsp;
</td></tr>
</table>
</form>
";
  } else {
    return <<<FORM
<form name='go' id='go' method='get' action='$action' onsubmit="return moin_submit();">
<input type='text' name='value' size='20' accesskey='s' style='width:100' />
<input type='hidden' name='action' value='goto' />
<input type='submit' name='status' value='Go' class='goto' style='width:23px;' />
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
  var $timers=array();
  var $total=0.0;
  function Timer() {
    $mt= explode(" ",microtime());
    $this->save=$mt[0]+$mt[1];
  }

  function Check($name="default") {
    $mt= explode(" ",microtime());
    $now=$mt[0]+$mt[1];
    $diff=$now-$this->save;
    $this->save=$now;
    if (isset($this->timers[$name]))
      $this->timers[$name]+=$diff;
    else
      $this->timers[$name]=$diff;
    $this->total+=$diff;
  }

  function Write() {
    while (list($name,$d) = each($this->timers)) {
      $out.=sprintf("%10s :%3.4f sec (%3.2f %%)\n",$name,$d,$d/$this->total*100);
    }
    return $out;
  }

  function Total() {
    return sprintf("%4.4f sec\n",$this->total);
  }

  function Clean() {
    $this->timers=array();
  }
}


class MetaDB_dba extends MetaDB {
  var $metadb;

  function MetaDB_dba($file,$type="db3") {
    if (function_exists('dba_open'))
      $this->metadb=@dba_open($file.".cache","r",$type);
  }

  function close() {
    dba_close($this->metadb);
  }

  function getSisterSites($pagename,$mode=1) {
    if ($pagename and dba_exists($pagename,$this->metadb)) {
       if (!$mode) return true;
       $sisters=dba_fetch($pagename,$this->metadb);

       if (strlen($sisters) > 40) return "[$pagename]";

       $ret="wiki:".
         str_replace(" ",":$pagename wiki:",$sisters).":$pagename";
       $pagename=_preg_search_escape($pagename);
       return preg_replace("/((:[^\s]+){2})(\:$pagename)/","\\1",$ret);
    }
    return "";
  }

  function getTwinPages($pagename,$mode=1) {
    if ($pagename && dba_exists($pagename,$this->metadb)) {
       if (!$mode) return true;

       $twins=dba_fetch($pagename,$this->metadb);
       $bullet=" ";
       if (strlen($twins) > 40) $bullet="\n * ";
       $ret=$bullet."wiki:".
         str_replace(" ",":$pagename$bullet"."wiki:",$twins).
         ":$pagename";

       $pagename=_preg_search_escape($pagename);
       $ret= preg_replace("/((:[^\s]+){2})(\:$pagename)/","\\1",$ret);
       return explode("\n",$ret);
    }
    return false;
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

  function getLikePages($needle,$count=500) {
    $keys=array();
    if (!$needle) return $keys;
    for ($key= dba_firstkey($this->metadb);
         $key !== false;
         $key= dba_nextkey($this->metadb)) {
      if (preg_match("/($needle)/i",$key)) {
        $keys[] = $key; $count--;
      }
      if ($count < 0) break;
    }
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
  function getLikePages() {
    return array();
  }
  function close() {
  }
}

class Counter_dba {
  var $counter;
  var $DB;
  function Counter_dba($DB) {
    if (!function_exists('dba_open')) return;
    if (!file_exists($DB->data_dir."/counter.db"))
       $this->counter=dba_open($DB->data_dir."/counter.db","n",$DB->dba_type);
    else
       $this->counter=@dba_open($DB->data_dir."/counter.db","w",$DB->dba_type);
    $this->DB=&$DB;
  }

  function incCounter($pagename,$options="") {
    if ($this->DB->owners and in_array($options['id'],$this->DB->owners))
      return;
    $count=dba_fetch($pagename,$this->counter);
    if (!$count) $count=0;
    $count++;
    dba_replace($pagename,$count,$this->counter);
  }

  function pageCounter($pagename) {
    $count=dba_fetch($pagename,$this->counter);
    return $count;
  }

  function close() {
    dba_close($this->counter);
  }
}

class Counter {
  function Counter($DB="") { }
  function incCounter($page,$options="") { }
  function pageCounter($page) { return 1; }
  function close() { }
}

class Security {
  var $DB;

  function Security($DB="") {
    $this->DB=$DB;
  }

# $options[page]: pagename
# $options[id]: user id
  function readable($options="") {
    return 1;
  }

  function writable($options="") {
    if (!$options['page']) return 0; # XXX
    return $this->DB->_isWritable($options['page']);
  }

  function validuser($options="") {
    return 1;
  }

  function is_allowed($action="read",$options) {
    return 1;
  }

  function is_protected($action="read",$options) {
    # password protected POST actions
    $protected_actions=array(
      "deletepage","deletefile","rename","rcspurge","chmod","backup","restore");
    $action=strtolower($action);

    if (in_array($action,$protected_actions)) {
      return 1;
    }
    return 0;
  }

  function is_valid_password($passwd,$options) {
    return
     $this->DB->admin_passwd==crypt($passwd,$this->DB->admin_passwd);
  }
}

function getConfig($configfile, $options=array()) {
  if (!file_exists($configfile)) {
    if ($options['init']) {
      $script= preg_replace("/\/([^\/]+)\.php$/","/monisetup.php",
               $_SERVER['SCRIPT_NAME']);
      header("Location: $script");
      exit;
    }
    return array();
  } 

  foreach ($options as $key=>$val) $$key=$val;
  unset($key,$val,$options);
  include($configfile);
  unset($configfile);

  $config=get_defined_vars();
#  print_r($config);

  if ($menu) $config['menu']=$menu;
  if ($icons) $config['icons']=$icons;
  if ($icon) $config['icon']=$icon;
  if ($actions) $config['actions']=$actions;

  return $config;
}

class WikiDB {
  function WikiDB($config=array()) {
    # Default Configuations
    $this->frontpage='FrontPage';
    $this->sitename='UnnamedWiki';
    $this->upload_dir= 'pds';
    $this->data_dir= './data';
    $this->query_prefix='/';
    $this->umask= 02;
    $this->charset='euc-kr';
    $this->lang='ko';
    $this->dba_type="db3";
    $this->use_counter=0;

    $this->text_dir= $this->data_dir.'/text';
    $this->cache_dir= $this->data_dir.'/cache';
    $this->vartmp_dir= '/var/tmp';
    $this->intermap= $this->data_dir.'/intermap.txt';
    $this->editlog_name= $this->data_dir.'/editlog';
    $this->shared_intermap=$this->data_dir."/text/InterMap";
    $this->shared_metadb=$this->data_dir."/metadb";
    $this->url_prefix= '/moniwiki';
    $this->imgs_dir= $this->url_prefix.'/imgs';
    $this->css_dir= 'css';
    $this->css_url= $this->url_prefix.'/css/default.css';

    $this->kbd_script= $this->url_prefix.'/css/kbd.js';
    $this->logo_img= $this->imgs_dir.'/moniwiki-logo.gif';
    $this->logo_page= $this->frontpage;
    $this->logo_string= '<img src="'.$this->logo_img.'" alt="[logo]" border="0" align="middle" />';
    $this->metatags='<meta name="ROBOTS" content="NOINDEX,NOFOLLOW" />';
    $this->use_smileys=1;
    $this->hr="<hr class='wikiHr' />";
    $this->date_fmt= 'Y-m-d';
    $this->datetime_fmt= 'Y-m-d h:i:s';
    #$this->changed_time_fmt = ' . . . . [h:i a]';
    $this->changed_time_fmt= ' [h:i a]'; # used by RecentChanges macro
    $this->admin_passwd= '10sQ0sKjIJES.';
    $this->purge_passwd= '';
    $this->rcs_user='root';
    $this->actions= array('DeletePage','LikePages');
    $this->show_hosts= TRUE;
    $this->iconset='moni';
    $this->goto_type='';
    $this->goto_form='';
    $this->template_regex='[a-z]Template$';
    $this->category_regex='^Category[A-Z]';
    $this->notify=0;
    $this->trail=0;
    $this->diff_type='fancy_diff';
    $this->use_sistersites=1;
    $this->use_twinpages=1;
    $this->pagetype=array();
#    $this->security_class="needtologin";

    # set user-specified configuration
    if ($config) {
      # read configurations
      foreach ($config as $key=>$val)
        $this->$key=$val;
    }

    if (!$this->purge_passwd)
      $this->purge_passwd=$this->admin_passwd;

#
    if (!$this->menu) {
      $this->menu= array($this->frontpage=>"accesskey='1'",'FindPage'=>"accesskey='4'",'TitleIndex'=>"accesskey='3'",'RecentChanges'=>"accesskey='2'");
      $this->menu_bra="";
      $this->menu_cat="|";
      $this->menu_sep="|";
    }

    if (!$this->icon) {
    $iconset=$this->iconset;
    $imgdir=$this->imgs_dir;
    $this->icon['upper']="<img src='$imgdir/$iconset-upper.gif' alt='U' align='middle' border='0' />";
    $this->icon['edit']="<img src='$imgdir/$iconset-edit.gif' alt='E' align='middle' border='0' />";
    $this->icon['diff']="<img src='$imgdir/$iconset-diff.gif' alt='D' align='middle' border='0' />";
    $this->icon['del']="<img src='$imgdir/$iconset-deleted.gif' alt='(del)' align='middle' border='0' />";
    $this->icon['info']="<img src='$imgdir/$iconset-info.gif' alt='I' align='middle' border='0' />";
    $this->icon['rss']="<img src='$imgdir/$iconset-rss.gif' alt='RSS' align='middle' border='0' />";
    $this->icon['show']="<img src='$imgdir/$iconset-show.gif' alt='R' align='middle' border='0' />";
    $this->icon['find']="<img src='$imgdir/$iconset-search.gif' alt='S' align='middle' border='0' />";
    $this->icon['help']="<img src='$imgdir/$iconset-help.gif' alt='H' align='middle' border='0' />";
    $this->icon['www']="<img src='$imgdir/$iconset-www.gif' alt='www' align='middle' border='0' />";
    $this->icon['mailto']="<img src='$imgdir/$iconset-email.gif' alt='M' align='middle' border='0' />";
    $this->icon['create']="<img src='$imgdir/$iconset-create.gif' alt='N' align='middle' border='0' />";
    $this->icon['new']="<img src='$imgdir/$iconset-new.gif' alt='U' align='middle' border='0' />";
    $this->icon['updated']="<img src='$imgdir/$iconset-updated.gif' alt='U' align='middle' border='0' />";
    $this->icon['user']="UserPreferences";
    $this->icon['home']="<img src='$imgdir/$iconset-home.gif' alt='M' align='middle' border='0' />";
    $this->icon_sep=" ";
    $this->icon_bra=" ";
    $this->icon_cat=" ";
    }

    if (!$this->icons) {
      $this->icons=array(
              array("","?action=edit",$this->icon['edit'],"accesskey='e'"),
              array("","?action=diff",$this->icon['diff'],"accesskey='c'"),
              array("","",$this->icon['show']),
              array("FindPage","",$this->icon['find']),
              array("","?action=info",$this->icon['info']),
              array("","?action=subscribe",$this->icon['mailto']),
              array("HelpContents","",$this->icon['help']),
           );
    }

    # load smileys
    if ($this->use_smileys){
      include_once("wikismiley.php");
      # set smileys rule
      $tmp=array_keys($smileys);
      $tmp=array_map("_preg_escape",$tmp);
      $rule=join($tmp,"|");
      $this->smiley_rule=$rule;
      $this->smileys=$smileys;
    }

    # ??? Number of lines output per each flush() call.
    // $this->lines_per_flush = 10;

    # ??? Is mod_rewrite being used to translate 'WikiWord' to
    // $this->rewrite = true;

    if ($this->path)
      putenv("PATH=".$this->path);

    $this->set_intermap();
    if ($this->shared_metadb)
      $this->metadb=new MetaDB_dba($this->shared_metadb,$this->dba_type);
    if (!$this->metadb->metadb)
      $this->metadb=new MetaDB();

    if ($this->use_counter)
      $this->counter=new Counter_dba($this);
    if (!$this->counter->counter)
      $this->counter=new Counter();

    if ($this->security_class) {
      include_once("plugin/security/$this->security_class.php");
      $class="Security_".$this->security_class;
      $this->security=new $class ($this);
    } else
      $this->security=new Security($this);
  }

  function Close() {
    $this->metadb->close();
    $this->counter->close();
  }

  function set_intermap() {
    # intitialize interwiki map
    $map=file($this->intermap);
    if ($this->sistermap and file_exists($this->sistermap))
      $map=array_merge($map,file($this->sistermap));

    # read shared intermap
    $shared_map=array();
    if (file_exists($this->shared_intermap)) {
      $shared_map=file($this->shared_intermap);
    }
    # merge
    $map=array_merge($map,$shared_map);

    for ($i=0;$i<sizeof($map);$i++) {
      $line=rtrim($map[$i]);
      if (!$line || $line[0]=="#" || $line[0]==" ") continue;
      if (preg_match("/^[A-Z]+/",$line)) {
        $dum=split("[[:space:]]",$line);
        $this->interwiki[$dum[0]]=trim($dum[1]);
        $this->interwikirule.="$dum[0]|";
      }
    }
    $this->interwikirule.="Self";
    $this->interwiki[Self]=get_scriptname().$this->query_prefix;
  }

  function _getPageKey($pagename) {
    # normalize a pagename to uniq key

    # moinmoin style internal encoding
    #$name=rawurlencode($pagename);
    #$name=strtr($name,"%","_");
    #$name=preg_replace("/%([a-f0-9]{2})/ie","'_'.strtolower('\\1')",$name);
    #$name=preg_replace(".","_2e",$name);

    #$name=str_replace("\\","",$pagename);
    #$name=stripslashes($pagename);
    $name=preg_replace("/([^a-z0-9]{1})/ie","'_'.strtolower(dechex(ord('\\1')))",$pagename);
    return $name;
  }

  function getPageKey($pagename) {
    $name=preg_replace("/([^a-z0-9]{1})/ie","'_'.strtolower(dechex(ord('\\1')))",$pagename);
    return $this->text_dir . '/' . $name;
  }

  function pageToKeyname($pagename) {
    return preg_replace("/([^a-z0-9]{1})/ie","'_'.strtolower(dechex(ord('\\1')))",$pagename);
  }

  function hasPage($pagename) {
    if (!$pagename) return false;
    $name=$this->getPageKey($pagename);
    return file_exists($name); 
  }

  function getPage($pagename,$options="") {
    return new WikiPage($pagename,$options);
  }

  function keyToPagename($key) {
  #  return preg_replace("/_([a-f0-9]{2})/e","chr(hexdec('\\1'))",$key);
  #  $pagename=preg_replace("/_([a-f0-9]{2})/","%\\1",$key);
  #  $pagename=str_replace("_","%",$key);
    $pagename=strtr($key,"_","%");
    return rawurldecode($pagename);
  }

  function getPageLists($options="") {
    $pages = array();
    $handle = opendir($this->text_dir);

    if (!$options) {
      while ($file = readdir($handle)) {
        if (is_dir($this->text_dir."/".$file)) continue;
        $pages[] = $this->keyToPagename($file);
      }
      closedir($handle);
      return $pages;
    } else if ($options['limit']) { # XXX
       while ($file = readdir($handle)) {
          if (is_dir($this->text_dir."/".$file)) continue;
          if (filemtime($this->text_dir."/".$file) > $options['limit'])
             $pages[] = $this->keyToPagename($file);
       }
       closedir($handle);
    } else if ($options['count']) {
       $count=$options['count'];
       while (($file = readdir($handle)) && $count > 0) {
          if (is_dir($this->text_dir."/".$file)) continue;
          $pages[] = $this->keyToPagename($file);
          $count--;
       }
       closedir($handle);
    } else if ($options['date']) {
       while ($file = readdir($handle)) {
          if (is_dir($this->text_dir."/".$file)) continue;
          $mtime=filemtime($this->text_dir."/".$file);
          $pagename= $this->keyToPagename($file);
          $pages[$pagename]= $mtime;
       }
       closedir($handle);
    }
    return $pages;
  }

  function getLikePages($needle,$mode=0) {
    $pages= array();
    $handle= opendir($this->text_dir);
    if ($mode==1)
      $needle_key= $this->pageToKeyname($needle);
    else $needle_key=$needle;

    while ($file = readdir($handle)) {
      if (is_dir($this->text_dir."/".$file)) continue;
      if (preg_match("/($needle_key)/",$file))
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
    define(MAXSIZE,5000);
    if ($size==0) $size=MAXSIZE;
    $filesize= filesize($this->editlog_name);
    if ($filesize > $size) {
      $fp= fopen($this->editlog_name, 'r');

      fseek($fp, -$size, SEEK_END);

      $dumm=fgets($fp,1024); # emit dummy
      while (!feof($fp)) {
        $line=fgets($fp,2048);
        $lines[]=$line;
      }
      fclose($fp);
    } else
      $lines=file($this->editlog_name);

    #$lines=$this->reverse($lines);
    $lines=array_reverse($lines);
    if (!$lines[0]) # delete last dummy
      unset($lines[0]);
    if (!$lines) $lines=array();

    if ($quick) {
      foreach($lines as $line) {
        $dum=explode("\t",$line,2);
        if ($keys[$dum[0]]) continue;
        $keys[$dum[0]]=1;
        $out[]=$line;
      }
      $lines=$out;
    }

    return $lines;
  }

  function _replace_variables($body,$options) {
    if ($this->template_regex
        && preg_match("/$this->template_regex/",$options['page']))
      return $body;

    $time=gmdate("Y-m-d\TH:i:s");

    $id=$options['id'];
    if ($id != 'Anonymous')
      if (!preg_match('/([A-Z][a-z0-9]+){2,}/',$id)) $id='['.$id.']';
 
    $body=preg_replace("/@DATE@/","[[Date($time)]]",$body);
    $body=preg_replace("/@TIME@/","[[DateTime($time)]]",$body);
    $body=preg_replace("/@SIG@/","-- $id [[DateTime($time)]]",$body);
    $body=preg_replace("/@PAGE@/",$options['page'],$body);
    $body=preg_replace("/@date@/","$time",$body);

    return $body;
  }

  function savePage($page,$comment="",$options=array()) {
    global $DBInfo;
    $user=new User();
    $REMOTE_ADDR=$_SERVER['REMOTE_ADDR'];
    $comment=escapeshellcmd($comment);
    $pagename=escapeshellcmd($page->name);

    $keyname=$this->_getPageKey($page->name);
    $key=$this->text_dir."/$keyname";

    $fp=fopen($key,"w");
    if (!$fp)
       return -1;
    $body=$this->_replace_variables($page->body,$options);
    $page->write($body);
    fwrite($fp, $body);
    fclose($fp);
    putenv('LOGNAME='.$DBInfo->rcs_user);
    $ret=system("ci -l -x,v/ -q -t-\"".$pagename."\" -m\"".$REMOTE_ADDR.";;".
            $user->id.";;".$comment."\" ".$key);
    #print $ret;
    $this->addLogEntry($keyname, $REMOTE_ADDR,$comment,"SAVE");
    return 0;
  }

  function deletePage($page,$comment="") {
    $REMOTE_ADDR=$_SERVER['REMOTE_ADDR'];

    $keyname=$this->_getPageKey($page->name);

    $delete=@unlink($this->text_dir."/$keyname");
    $this->addLogEntry($keyname, $REMOTE_ADDR,$comment,"SAVE");

    $handle= opendir($this->cache_dir);
    while ($file= readdir($handle)) {
      if (is_dir("$this->cache_dir/$file")) {
        $cache= new Cache_text($file);
        $cache->remove($page->name);
      }
    }
  }

  function renamePage($pagename,$new) {
    $REMOTE_ADDR=$_SERVER['REMOTE_ADDR'];

    $okey=$this->getPageKey($pagename);
    $nkey=$this->getPageKey($new);

    rename($okey,$nkey);
    $comment=sprintf(_("Rename %s to %s"),$pagename,$new);
    $this->addLogEntry($new, $REMOTE_ADDR,$comment,"SAVE");
  }

  function _isWritable($pagename) {
    $key=$this->getPageKey($pagename);
    # True if page can be changed
    return is_writable($key) or !file_exists($key);
  }

  function getPerms($pagename) {
    $key=$this->getPageKey($pagename);
    if (file_exists($key))
       return fileperms($key);
    return 0666;
  }

  function setPerms($pagename,$perms) {
    umask(000);
    $key=$this->getPageKey($pagename);
    if (file_exists($key)) chmod($key,$perms);
  }
}

class Cache_text {
  function Cache_text($arena) {
    global $DBInfo;
    umask(000);
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
    if ($mtime and ($mtime <= $this->mtime($key))) return false;

    if (is_array($val))
      $val=join("\n",array_keys($val))."\n";
    else
      $val=str_replace("\r","",$val);
    $this->_save($key,$val);
    return true;
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
    return false;
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
    if ($options['rev'])
      $this->rev=$options['rev'];
    else
      $this->rev=0; # current rev.
    $this->name= $name;
    $this->filename= $this->_filename($name);
    $this->urlname= _rawurlencode($name);
    $this->body= "";
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

#  function writable() {
#    # True if page can be changed
#    return is_writable($this->filename) or !$this->exists();
#  }

  function mtime () {
    return @filemtime($this->filename);
  }

  function size() {
    if ($this->fsize) return $this->fsize;
    $this->fsize=@filesize($this->filename);
    return $this->fsize;
  }

  function get_raw_body($options='') {
#    if (isset($this->body) && !$options[rev])
    if ($this->body && !$options['rev'])
       return $this->body;

    if (!$this->exists()) return '';

    if ($this->rev || $options['rev']) {
       if ($options['rev']) $rev=$options['rev'];
       else $rev=$this->rev;
       $fp=@popen("co -x,v/ -q -p\"".$rev."\" ".$this->filename,"r");
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
       $out="You have no permission to see this page.\n\n";
       $out.="See MoniWiki/AccessControl\n";
       return $out;
    }
    $this->fsize=filesize($this->filename);
#    $body="";
#    if ($fp) { while($line=fgets($fp, 2048)) $body.=$line; }
#    $this->$body=implode("", file($this->filename));
#    $this->body=$body;
    $body=fread($fp,$this->fsize);
    fclose($fp);
    $this->body=$body;

    return $this->body;
  }

  function _get_raw_body() {
    $fp=@fopen($this->filename,"r");
    if (!$fp) {
      $out="You have no permission to see this page.\n\n";
      $out.="See MoniWiki/AccessControl\n";
      return $out;
    }
    $size=filesize($this->filename);
    $this->body=fread($fp,$size);
    fclose($fp);

    return $this->body;
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

  function get_rev($mtime="") {
    if ($mtime) {
      $date=gmdate('Y/m/d H:i:s',$mtime);
      if ($date) 
         $opt="-d\<'$date'";
    }
    $fp=popen("rlog -x,v/ $opt ".$this->filename,"r");
#   if (!$fp)
#      print "No older revisions available";
# XXX
    while (!feof($fp)) {
      $line=fgets($fp,1024);
      preg_match("/^revision\s+([\d\.]+$)/",$line,$match);
      if ($match[1]) {
         $rev=$match[1];
         break;
      }
    }
    pclose($fp);
    if ($rev > 1.0)
      return $rev;
    return '';
  }
}

class Formatter {
  var $sister_idx=1;
  var $group="";

  function Formatter($page="",$options="") {
    global $DBInfo;

    $this->page=$page;
    $this->head_num=1;
    $this->head_dep=0;
    $this->toc=0;
    $this->highlight="";
    $this->prefix= get_scriptname();
    $this->url_prefix= $DBInfo->url_prefix;
    $this->actions= $DBInfo->actions;
    $this->in_p='';

    if (($p=strpos($page->name,"~")))
      $this->group=substr($page->name,0,$p+1);

    $this->sister_on=1;
    $this->sisters=array();
    $this->foots=array();
    $this->pagelinks=array();
    $this->icons="";

    $this->themeurl= $DBInfo->url_prefix;
    $this->themedir= dirname(__FILE__);
    $this->set_theme($options['theme']);

    #$this->baserule=array("/<([^\s][^>]*)>/","/`([^`]*)`/",
    $this->baserule=array("/<([^\s<>])/","/`([^`]*)`/",
                     "/'''([^']*)'''/","/(?<!')'''(.*)'''(?!')/",
                     "/''([^']*)''/","/(?<!')''(.*)''(?!')/",
                     "/\^([^ \^]+)\^(?:\s)/","/,,([^ ,]+),,(?:\s)/",
                     "/__([^ _]+)__(?:\s)/","/^-{4,}/");
    $this->baserepl=array("&lt;\\1","<tt class='wiki'>\\1</tt>",
                     "<b>\\1</b>","<b>\\1</b>",
                     "<i>\\1</i>","<i>\\1</i>",
                     "<sup>\\1</sup>","<sub>\\1</sub>",
                     "<u>\\1</u>","<hr class='wiki' />\n");

    # NoSmoke's MultiLineCell hack
    $this->extrarule=array("/{{\|/","/\|}}/");
    $this->extrarepl=array("</div><table class='closure'><tr class='closure'><td class='closure'><div>","</div></td></tr></table><div>");
    
    # set smily_rule,_repl
    if ($DBInfo->smileys) {
      $smiley_rule='/(?<=\s|^)('.$DBInfo->smiley_rule.')(?=\s|$)/e';
      $smiley_repl="\$this->smiley_repl('\\1')";

      $this->extrarule[]=$smiley_rule;
      $this->extrarepl[]=$smiley_repl;
    }

    #$punct="<\"\'}\]\|;,\.\!";
    $punct="<\'}\]\|;\.\)\!"; # , is omitted for the WikiPedia
    $url="wiki|http|https|ftp|nntp|news|irc|telnet|mailto|file";
    $urlrule="((?:$url):([^\s$punct]|(\.?[^\s$punct]))+)";
    #$urlrule="((?:$url):(\.?[^\s$punct])+)";
    #$urlrule="((?:$url):[^\s$punct]+(\.?[^\s$punct]+)+\s?)";
    # solw slow slow
    #(?P<word>(?:/?[A-Z]([a-z0-9]+|[A-Z]*(?=[A-Z][a-z0-9]|\b))){2,})
    $this->wordrule=
    # single bracketed rule [http://blah.blah.com Blah Blah]
    "(\[($url):[^\s\]]+(\s[^\]]+)?\])|".
    # InterWiki
    # strict but slow
    #"\b(".$DBInfo->interwikirule."):([^<>\s\'\/]{1,2}[^\(\)<>\s\']+\s{0,1})|".
    #"\b([A-Z][a-zA-Z]+):([^<>\s\'\/]{1,2}[^\(\)<>\s\']+\s{0,1})|".
    "\b([A-Z][a-zA-Z]+):([^<>\s\'\/]{1,2}[^\(\)<>\s\']+)|".
  # "(?<!\!|\[\[)\b(([A-Z]+[a-z0-9]+){2,})\b|".
  # "(?<!\!|\[\[)((?:\/?[A-Z]([a-z0-9]+|[A-Z]*(?=[A-Z][a-z0-9]|\b))){2,})\b|".
    # WikiName rule: WikiName ILoveYou (imported from the rule of NoSmoke)
    # protect WikiName rule !WikiName
    "(?<![a-z])\!?(?:\/?[A-Z]([A-Z]+[0-9a-z]|[0-9a-z]+[A-Z])[0-9a-zA-Z]*)+\b|".
    # single bracketed name [Hello World]
    "(?<!\[)\[([^\[:,<\s][^\[:,>]+)\](?!\])|".
    # bracketed with double quotes ["Hello World"]
    "(?<!\[)\[\\\"([^\\\"]+)\\\"\](?!\])|".
  # "(?<!\[)\[\\\"([^\[:,]+)\\\"\](?!\])|".
    "($urlrule)|".
    # single linkage rule ?hello ?abacus
    "(\?[A-Z]*[a-z0-9]+)";

    $this->cache= new Cache_text("pagelinks");
  }

  function header($args) {
    header($args);
  }

  function set_theme($theme="") {
    global $DBInfo;
    if ($theme) {
      $this->themedir.="/theme/$theme";
      $this->themeurl.="/theme/$theme";
    }
    $options['themedir']=$this->themedir;
    $options['themeurl']=$this->themeurl;
    $options['frontpage']=$DBInfo->frontpage;
    if (file_exists($this->themedir."/theme.php")) {
      $data=getConfig($this->themedir."/theme.php",$options);
      #print_r($data);

      if ($data) {
        # read configurations
        while (list($key,$val) = each($data)) $this->$key=$val;
      }
    }
    if (!$this->icon) {
      $this->icon=&$DBInfo->icon;

      $this->icon_bra=$DBInfo->icon_bra;
      $this->icon_cat=$DBInfo->icon_cat;
      $this->icon_sep=$DBInfo->icon_sep;
    }

    if (!$this->menu) {
      $this->menu=&$DBInfo->menu;

      $this->menu_bra=$DBInfo->menu_bra;
      $this->menu_cat=$DBInfo->menu_cat;
      $this->menu_sep=$DBInfo->menu_sep;
    }

    if (!$this->icons) {
      $this->icons=&$DBInfo->icons;
    }
  }

  function get_redirect() {
    $body=$this->page->get_raw_body();
    if ($body[0]=='#' and substr($body,0,10)=='#redirect ') {
      list($line,$dumm)=explode("\n",$body,2);
      list($tag,$val)=explode(" ",$line,2);
      if ($val) $this->pi['#redirect']=$val;
    }
  }

  function get_instructions($body="") {
    global $DBInfo;
    $pikeys=array('#redirect','#action');
    $pi=array();
    if (!$body) {
      if (!$this->page->exists()) return '';
      $body=$this->page->get_raw_body();
    }

    $key=substr($this->page->name,0,strpos($this->page->name,'/'));

    if (array_key_exists($key,$DBInfo->pagetype))
      $format=$DBInfo->pagetype[$key];
    else if ($body[0] == '<') {
      list($line, $dummy)= explode("\n", $body,2);
      if (substr($line,0,6) == '<?xml ')
        #$format='xslt';
        $format='xsltproc';
    } else {
      if ($body[0]=='#' and substr($body,0,8)=='#format ') {
        list($line,$body)=explode("\n",$body,2);
        list($tag,$format)=explode(" ",$line,2);
      } else if ($body[0] == '#' and $body[1] =='!') {
        list($line, $body)= explode("\n", $body,2);
        list($format,$args)= explode(" ", substr($line,2),2);
      }

      while ($body and $body[0] == '#') {
        # extract first line
        list($line, $body)= split("\n", $body,2);
        if ($line=='#') break;
        else if ($line[1]=='#') continue;

        list($key,$val,$args)= explode(" ",$line,2); # XXX
        $key=strtolower($key);
        if (in_array($key,$pikeys)) { $pi[$key]=$val; }
        else $notused[]=$line;
      }
    }

    if ($format) {
      if (function_exists("processor_".$format)) {
        $pi['#format']=$format;
      } else if ($processor=getProcessor($format)) {
        include_once("plugin/processor/$processor.php");
        $pi['#format']=$format;
      }
    }

    if ($notused) $body=join("\n",$notused)."\n".$body;
    return $pi;
  }

  function highlight_repl($val,$colref=array()) {
    static $color=array("style='background-color:#ff6;'",
                        "style='background-color:#aff;'",
                        "style='background-color:#0f3;'",
                        "style='background-color:#f99;'",
                        "style='background-color:#f9c;'",
                        "style='background-color:#c9f;'");
    $val=str_replace("\\\"",'"',$val);
    if ($val[0]=="<") return $val;

    $key=strtolower($val);

    if (isset($colref[$key]))
      return "<strong ".($color[$colref[$key] % 5]).">$val</strong>";
    return "<strong class='highlight'>$val</strong>";
  }

  function write($raw) {
    print $raw;
  }

  function link_repl($url,$attr='') {
    global $DBInfo;

    $url=str_replace('\"','"',$url);
    if ($url[0]=="[") {
      $url=substr($url,1,-1);
      $force=1;
    }
    if ($url[0]=="{") {
      $url=substr($url,3,-3);
      return "<tt class='wiki'>$url</tt>"; # No link
    } else if ($url[0]=="[") {
      $url=substr($url,1,-1);
      return $this->macro_repl($url); # No link
    } else if ($url[0]=='$') {
      #return processor_latex($this,"#!latex\n".$url);
      return $this->processor_repl('latex',$url);
    }

    if ($url[0]=="!") {
      $url[0]=" ";
      return $url;
    } else
    if (strpos($url,":")) {
      if (preg_match("/^mailto:/",$url)) {
        $url=str_replace("@","_at_",$url);
        $name=substr($url,7);
        return $this->icon['mailto']."<a href='$url' $attr>$name</a>";
      } else
      if (preg_match("/^(w|[A-Z])/",$url)) { # InterWiki or wiki:
        if (strpos($url," ")) { # have a space ?
          $dum=explode(" ",$url,2);
          return $this->interwiki_repl($dum[0],$dum[1]);
        }
        return $this->interwiki_repl($url);
      } else
      if ($force or strpos($url," ")) { # have a space ?
        list($url,$text)=explode(" ",$url,2);
        if (!$text) $text=$url;
        else if (preg_match("/^(http|ftp).*\.(png|gif|jpeg|jpg)$/i",$text))
          return "<a href='$url' $attr title='$url'><img border='0' alt='$url' src='$text' /></a>";
        list($icon,$dummy)=explode(":",$url,2);
        return "<img align='middle' alt='[$icon]' src='".$DBInfo->imgs_dir."/$icon.png' />". "<a $attr href='$url'>$text</a>";
      } else # have no space
      if (preg_match("/^(http|https|ftp)/",$url)) {
        if (preg_match("/\.(png|gif|jpeg|jpg)$/i",$url))
          return "<img alt='$url' src='$url' />";
        return "<a $attr href='$url'>$url</a>";
      }
      return "<a $attr href='$url'>$url</a>";
    } else {
      if ($url[0]=="?") $url=substr($url,1);
      return $this->word_repl($url);
    }
  }

  function interwiki_repl($url,$text="") {
    global $DBInfo;

    if ($url[0]=="w")
      $url=substr($url,5);
    $dum=explode(":",$url,2);
    $wiki=$dum[0]; $page=$dum[1];
#    if (!$page) { # wiki:Wiki/FrontPage
#      $dum1=explode("/",$url,2);
#      $wiki=$dum1[0]; $page=$dum1[1];
#    }

    if (!$page) {
      # wiki:FrontPage(not supported in the MoinMoin
      # or [wiki:FrontPage Home Page]
      $page=$dum[0];
      if (!$text)
        return $this->word_repl($page,'','',1);
      return $this->word_repl($page,$text,'',1);
    }

    $url=$DBInfo->interwiki[$wiki];
    # invalid InterWiki name
    if (!$url)
      return $dum[0].":".$this->word_repl($dum[1],$text);

    $urlpage=_urlencode(trim($page));
    #$urlpage=trim($page);
    if (strpos($url,'$PAGE') === false)
      $url.=$urlpage;
    else {
      # GtkRef http://developer.gnome.org/doc/API/2.0/gtk/$PAGE.html
      # GtkRef:GtkTreeView#GtkTreeView
      # is rendered as http://...GtkTreeView.html#GtkTreeView
      $page_only=strtok($urlpage,'#?');
      $query= substr($urlpage,strlen($page_only));
      #if ($query and !$text) $text=strtok($page,'#?');
      $url=str_replace('$PAGE',$page_only,$url).$query;
    }

    $img="<a href='$url' target='wiki'><img border='0' src='$DBInfo->imgs_dir/".
         strtolower($wiki)."-16.png' align='middle' height='16' width='16' ".
         "alt='$wiki:' title='$wiki:' /></a>";
    if (!$text) $text=str_replace("%20"," ",$page);
    else if (preg_match("/^(http|ftp).*\.(png|gif|jpeg|jpg)$/i",$text)) {
      $text= "<img border='0' alt='$text' src='$text' />";
      $img="";
    }

    if (preg_match("/\.(png|gif|jpeg|jpg)$/i",$url))
      return "<img border='0' alt='$text' src='$url' />";

    return $img. "<a href='".$url."' title='$wiki:$page'>$text</a>";
  }

  function store_pagelinks() {
    unset($this->pagelinks['TwinPages']);
    $this->cache->update($this->page->name,$this->pagelinks,$this->page->mtime());
  }

  function get_pagelinks() {
    if ($this->cache->exists($this->page->name)) {
      $links=$this->cache->fetch($this->page->name);
      if ($links !== false) return $links;
    }
    if ($this->page->exists()) {
      $body=$this->page->get_raw_body();
      # quickly generate a pseudo pagelinks
      preg_replace("/(".$this->wordrule.")/e","\$this->link_repl('\\1')",$body);
      $this->store_pagelinks();
      if ($this->pagelinks) {
        $links=join("\n",array_keys($this->pagelinks))."\n";
        $this->pagelinks=array();
        return $links;
      }
    }
    return '';
  }

  function word_repl($word,$text='',$attr='',$nogroup=0) {
    global $DBInfo;
    if ($word[0]=='"') { # ["extended wiki name"]
      $page=substr($word,1,-1);
      $word=$page;
    } else if ($word[0]=='#') { # Anchor syntax in the MoinMoin 1.1
      $anchor=strtok($word," ");
      return ($word=strtok("")) ? $this->link_to($anchor,$word):
                 "<a name='".($temp=substr($anchor,1))."' id='$temp'></a>";
    } else
      #$page=preg_replace("/\s+/","",$word); # concat words
      $page=normalize($word); # concat words
    if ($text) $word=$text;

    # User namespace extension
    if ($page[0]=='~' and ($p=strpos($page,'/'))) {
      # change ~User/Page to User~Page
      $page=substr($page,1,$p-1)."~".substr($page,$p+1);
    } else if (!$nogroup and $this->group and !strpos($page,'~')) {
      if ($page[0]=='/') $page=substr($page,1);
      else $page=$this->group.$page;
    } else if ($page[0]=='/') # SubPage
      $page=$this->page->name.$page;

    #$url=$this->link_url($page);
    $url=$this->link_url(_rawurlencode($page)); # XXX
    if (isset($this->pagelinks[$page])) {
      $idx=$this->pagelinks[$page];
      switch($idx) {
        case 0:
          return "<a class='nonexistent' href='$url'>?</a>$word";
        case -1:
          return "<a href='$url'>$word</a>";
        case -2:
          return "<a href='$url'>$word</a>".
            "<tt class='sister'><a href='$url'>&#x203a;</a></tt>";
        default:
          return "<a href='$url'>$word</a>".
            "<tt class='sister'><a href='#sister$idx'>&#x203a;$idx</a></tt>";
      }
    } else if ($DBInfo->hasPage($page)) {
      $this->pagelinks[$page]=-1;
      return "<a href='$url'>$word</a>";
    } else {
      if ($this->sister_on) {
        $sisters=$DBInfo->metadb->getSisterSites($page, $DBInfo->use_sistersites);
        if ($sisters === true) {
          $this->pagelinks[$page]=-2;
          return "<a href='$url'>$word</a>".
            "<tt class='sister'><a href='$url'>&#x203a;</a></tt>";
        }
        if ($sisters) {
          $this->sisters[]="<tt class='foot'>&#160;&#160;&#160;".
            "<a name='sister$this->sister_idx' id='sister$this->sister_idx'></a>".
            "<a href='#rsister$this->sister_idx'>$this->sister_idx&#x203a;</a>&#160;</tt> ".
            "$sisters <br/>";
          $this->pagelinks[$page]=$this->sister_idx++;
          $idx=$this->pagelinks[$page];
        }
        if ($idx > 0) {
          return "<a href='$url'>$word</a>".
           "<a name='rsister$idx' id='rsister$idx'>".
           "<tt class='sister'><a href='#sister$idx'>&#x203a;$idx</a></tt>";
        }
      }
      $this->pagelinks[$page]=0;
      return "<a class='nonexistent' href='$url'>?</a>$word";
    }
  }

  function head_repl($left,$head,$right) {
    $dep=strlen($left);
    if ($dep != strlen($right)) return "$left $head $right";
    $this->nobr=1;

    $head=str_replace('\"','"',$head); # revert \\" to \"

    if (!$this->depth_top) {
      $this->depth_top=$dep; $depth=1;
    } else {
      $depth=$dep - $this->depth_top + 1;
      if ($depth <= 0) $depth=1;
    }

#    $depth=$dep;
#    if ($dep==1) $depth++; # depth 1 is regarded same as depth 2
#    $depth--;

    $num="".$this->head_num;
    $odepth=$this->head_dep;

    if ($head[0] == '#') {
      # reset TOC numberings
      if ($this->toc_prefix) $this->toc_prefix++;
      else $this->toc_prefix=1;
      $head[0]=' ';
      $dum=explode(".",$num);
      $i=sizeof($dum);
      for ($j=0;$j<$i;$j++) $dum[$j]=1;
      $dum[$i-1]=0;
      $num=join($dum,".");
    }
    $open="";
    $close="";

    if (!$odepth) {
      $open.="<div class='section'>\n"; # <section>
    } else if ($odepth && ($depth > $odepth)) {
    #if ($odepth && ($depth > $odepth)) {
      $open.="<div class='section'>\n"; # <section>
      $num.=".1";
    } else if ($odepth) {
      $dum=explode(".",$num);
      $i=sizeof($dum)-1;
      if ($depth == $odepth) $close.="</div>\n<div class='section'>\n"; # </section><section>
      while ($depth < $odepth && $i > 0) {
         unset($dum[$i]);
         $i--;
         $odepth--;
         $close.="</div>\n"; # </section>
      }
      $dum[$i]++;
      $num=join($dum,".");
    }

    $this->head_dep=$depth; # save old
    $this->head_num=$num;

    $prefix=$this->toc_prefix;
    if ($this->toc)
      $head="<a href='#toc'>$num</a> $head";
    $purple=" <a class='purple' href='#s$prefix-$num'>#</a>";

    $close=$this->_check_p().$close; 

    return "$close$open<h$dep><a id='s$prefix-$num' name='s$prefix-$num'></a> $head$purple</h$dep>";
  }

  function macro_repl($macro) {
    preg_match("/^([A-Za-z]+)(\((.*)\))?$/",$macro,$match);
    $name=$match[1]; $option=($match[2] and !$match[3]) ? true:$match[3];

    if (!function_exists ("macro_".$name)) {

      if ($plugin=getPlugin($name))
        include_once("plugin/$plugin.php");
      else
        return "[[".$name."]]";
    }
    $ret=call_user_func("macro_$name",&$this,$option);
    return $ret;
  }

  function processor_repl($processor,$value,$options="") {
    if (!function_exists("processor_".$processor)) {
      $pf=getProcessor($processor);
      include_once("plugin/processor/$pf.php");
      $processor=$pf;
    }
    return call_user_func("processor_$processor",&$this,$value,$options);
  }

  function smiley_repl($smiley) {
    global $DBInfo;

    $img=$DBInfo->smileys[$smiley][3];

    $alt=str_replace("<","&lt;",$smiley);

    return "<img src='$DBInfo->imgs_dir/$img' align='middle' alt='$alt' title='$alt' />";
  }

  function link_url($pageurl,$query_string="") {
    global $DBInfo;
    $sep=$DBInfo->query_prefix;

    if ($sep == '?') {
      if ($pageurl && $query_string[0]=='?')
        # add 'dummy=1' to work around the buggy php
        $query_string= '&amp;'.substr($query_string,1).'&amp;dummy=1';
        # Did you have a problem with &amp;dummy=1 ?
        # then, please replace above line with next line.
        #$query_string= '&amp;'.substr($query_string,1);
      $query_string= $pageurl.$query_string;
    } else
      $query_string= $pageurl.$query_string;
    return sprintf("%s%s%s", $this->prefix, $sep, $query_string);
  }

  function link_tag($pageurl,$query_string="", $text="",$attr="") {
    # Return a link with given query_string.
    if (!$text)
      $text= $pageurl; # XXX
    if (!$pageurl)
      $pageurl=$this->page->urlname;
    $url=$this->link_url($pageurl,$query_string);
    return sprintf("<a href=\"%s\" %s>%s</a>", $url, $attr, $text);
  }

  function link_to($query_string="",$text="",$attr="") {
    if (!$text)
      $text=$this->page->name;
    return $this->link_tag($this->page->urlname,$query_string,$text,$attr);
  }

  function _list($on,$list_type,$numtype="",$closetype="") {
    if ($list_type=="dd") {
      if ($on)
         #$list_type="dl><dd";
         $list_type="div class='indent'";
      else
         #$list_type="dd></dl";
         $list_type="div";
      $numtype='';
    } else if ($list_type=="dl") {
      if ($on)
         $list_type="dl";
      else
         $list_type="dd></dl";
      $numtype='';
    } if (!$on and $closetype and $closetype !='dd')
      $list_type=$list_type."></li";

    if ($this->in_li==0 and $on) {
      $close=$this->_check_p();
      $open="<div class='list'>";
      $this->in_p='li';
    }
    if ($on) {
      if ($numtype) {
        $start=substr($numtype,1);
        if ($start)
          return "<$list_type type='$numtype[0]' start='$start'>";
        return "<$list_type type='$numtype[0]'>";
      }
      return "$close$open<$list_type>\n";
    } else {
      return "</$list_type>\n$close$open";
    }
  }

  function _check_p() {
    if ($this->in_p) {
      $this->in_p='';
      return "</div>\n"; #close
    }
    return '';
  }

  function _table_span($str) {
    $len=strlen($str)/2;
    if ($len > 1)
      return " align='center' colspan='$len'";
    return "";
  }

  function _table($on,$attr="") {
    if ($on)
      return "<table class='wiki' cellpadding='3' cellspacing='2' $attr>\n";
    return "</table>\n";
  }

  function send_page($body="",$options="") {
    global $DBInfo;

    if ($body) {
      $pi=$this->get_instructions(&$body);
      if ($pi['#format']) {
        print call_user_func("processor_".$pi['#format'],&$this,$body,$options);
        return;
      }
      $lines=explode("\n",$body);
    } else {
      $body=$this->page->get_raw_body();
      $pi=$this->get_instructions(&$body);
      $this->pi=$pi;
      if ($pi['#format']) {
        print call_user_func("processor_".$pi['#format'],&$this,$body,$options);
        return;
      }

      $twins=$DBInfo->metadb->getTwinPages($this->page->name,$DBInfo->use_twinpages);
      if ($body) {
        $body=rtrim($body); # delete last empty line
        $lines=explode("\n",$body);
      } else
        $lines=array();
      if ($twins === true) {
        if ($DBInfo->interwiki['TwinPages']) {
          if ($lines) $lines[]="----";
          $lines[]=sprintf(_("See %s"),"[wiki:TwinPages:".$this->page->name." "._("TwinPages")."]");
        }
      } else if ($twins) {
        if ($lines) $lines[]="----";
        $twins[0]=_("See TwinPages: ").$twins[0];
        $lines=array_merge($lines,$twins);
      }
    }

    # have no contents
    if (!$lines) return;

    $text="";
    #$in_p=1;
    $in_li=0;
    $in_pre=0;
    $in_table=0;
    $li_open=0;
    $indent_list[0]=0;
    $indent_type[0]="";

    $wordrule="({{{([^}]+)}}})|".
              "\[\[([A-Za-z0-9]+(\(((?<!\]\]).)*\))?)\]\]|"; # macro
    if ($DBInfo->enable_latex) # single line latex syntax
      $wordrule.="\\$\s([^\\$]+)\\$(?:\s|$)|".
                 "\\$\\$\s([^\\$]+)\\$\\$(?:\s|$)|";
    $wordrule.=$this->wordrule;

    foreach ($lines as $line) {

      # empty line
      #if ($line=="") {
      if (!strlen($line)) {
        if ($in_pre) { $this->pre_line.="\n";continue;}
        if ($in_li) { $text.="<br />\n"; continue;}
        if ($in_table) {
          $text.=$this->_table(0)."<br />\n";$in_table=0; continue;
        } else {
          if ($this->in_p) { $text.="</div><br />\n"; $this->in_p='';}
          else if ($this->in_p=='') { $text.="<br />\n";}
          continue;
        }
      }

      if ($line[0]=='#' and $line[1]=='#') continue; # comments

      if ($in_pre) {
         if (strpos($line,"}}}")===false) {
           $this->pre_line.=$line."\n";
           continue;
         } else {
           $p=strrpos($line,"}}}");
           if ($p>2 and $line[$p-3]=='\\') {
             $this->pre_line.=substr($line,0,$p-3).substr($line,$p-2)."\n";
             continue;
           }
           $len=strlen($line);
           $this->pre_line.=substr($line,0,$p-2);
           $line=substr($line,$p+1);
           $in_pre=-1;
         }
      #} else if ($in_pre == 0 && preg_match("/{{{[^}]*$/",$line)) {
      } else if (!(strpos($line,"{{{")===false) and 
                 preg_match("/{{{[^}]*$/",$line)) {
         $p=strpos($line,"{{{");
         $len=strlen($line);

         $processor="";
         $in_pre=1;

         # check processor
         if ($line[$p+3] == "#" and $line[$p+4] == "!") {
            list($tag,$dummy)=explode(" ",substr($line,$p+5),2);

            if (function_exists("processor_".$tag)) {
              $processor=$tag;
            } else if ($pf=getProcessor($tag)) {
              include_once("plugin/processor/$pf.php");
              $processor=$pf;
            }
         } else if ($line[$p+3] == ":") {
            # new formatting rule for a quote block (pre block + wikilinks)
            $line[$p+3]=" ";
            $in_quote=1;
         }

         $this->pre_line=substr($line,$p+3);
         if (trim($this->pre_line))
           $this->pre_line.="\n";
         $line=substr($line,0,$p);
      }
#     $line=str_replace("<","&lt;",$line);
      #$line=preg_replace("/\\$/","&#36;",$line);
      #$line=preg_replace("/<([^\s][^>]*)>/","&lt;\\1>",$line);
      #$line=preg_replace("/`([^`]*)`/","<tt class='wiki'>\\1</tt>",$line);

      # bold
      #$line=preg_replace("/'''([^']*)'''/","<b>\\1</b>",$line);
      #$line=preg_replace("/(?<!')'''(.*)'''(?!')/","<b>\\1</b>",$line);

      # italic 
      #$line=preg_replace("/''([^']*)''/","<i>\\1</i>",$line);
      #$line=preg_replace("/(?<!')''(.*)''(?!')/","<i>\\1</i>",$line);

      # Superscripts, subscripts
      #$line=preg_replace("/\^([^ \^]+)\^/","<sup>\\1</sup>",$line);
      #$line=preg_replace("/(?: |^)_([^ _]+)_/","<sub>\\1</sub>",$line);
      # rules
      #$line=preg_replace("/^-{4,}/","<hr />\n",$line);

      $line=preg_replace($this->baserule,$this->baserepl,$line);
      #if ($in_p and ($in_pre==1 or $in_li)) $line=$this->_check_p().$line;

      # bullet and indentation
      if ($in_pre != -1 && preg_match("/^(\s*)/",$line,$match)) {
      #if (preg_match("/^(\s*)/",$line,$match)) {
         $open="";
         $close="";
         $indtype="dd";
         $indlen=strlen($match[0]);
         if ($indlen > 0) {
           $line=substr($line,$indlen);
           #if (preg_match("/^(\*\s*)/",$line,$limatch)) {
           if ($line[0]=='*') {
             $limatch[1]='*';
             $line=preg_replace("/^(\*\s?)/","<li>",$line);
             if ($indent_list[$in_li] == $indlen) $line="</li>\n".$line;
             $numtype="";
             $indtype="ul";
           } elseif (preg_match("/^((\d+|[aAiI])\.)(#\d+)?\s/",$line,$limatch)){
             $line=preg_replace("/^((\d+|[aAiI])\.(#\d+)?)/","<li>",$line);
             if ($indent_list[$in_li] == $indlen) $line="</li>\n".$line;
             $numtype=$limatch[2];
             if ($limatch[3])
               $numtype.=substr($limatch[3],1);
             $indtype="ol";
           } elseif (preg_match("/^([^:]+)::\s/",$line,$limatch)) {
             $line=preg_replace("/^[^:]+::\s/",
                     "<dt class='wiki'>".$limatch[1]."</dt><dd>",$line);
             if ($indent_list[$in_li] == $indlen) $line="</dd>\n".$line;
             $numtype="";
             $indtype="dl";
           }
         }
         if ($indent_list[$in_li] < $indlen) {
            $this->in_li=$in_li;
            $in_li++;
            $indent_list[$in_li]=$indlen; # add list depth
            $indent_type[$in_li]=$indtype; # add list type
            $open.=$this->_list(1,$indtype,$numtype);
         } else if ($indent_list[$in_li] > $indlen) {
            while($in_li >= 0 && $indent_list[$in_li] > $indlen) {
               if ($indent_type[$in_li]!='dd' && $li_open == $in_li)
                 $close.="</li>\n";
               $close.=$this->_list(0,$indent_type[$in_li],"",$indent_type[$in_li-1]);
               unset($indent_list[$in_li]);
               unset($indent_type[$in_li]);
               $in_li--;
               $this->in_li=$in_li;
            }
         }
         if ($indent_list[$in_li] <= $indlen || $limatch) $li_open=$in_li;
         else $li_open=0;
      }

      #if (!$in_pre && !$in_table && preg_match("/^\|\|.*\|\|$/",$line)) {
      if (!$in_pre && $line[0]=='|' && !$in_table && preg_match("/^\|\|.*\|\|$/",$line)) {
         $open.=$this->_table(1);
         $in_table=1;
      #} elseif ($in_table && !preg_match("/^\|\|.*\|\|$/",$line)){
      } elseif ($in_table && $line[0]!='|' && !preg_match("/^\|\|.*\|\|$/",$line)){
         $close=$this->_table(0).$close;
         $in_table=0;
      }
      if ($in_table) {
         $line=preg_replace('/^((?:\|\|)+)(.*)\|\|$/e',"'<tr class=\"wiki\"><td class=\"wiki\"'.\$this->_table_span('\\1').'>\\2</td></tr>'",$line);
         $line=preg_replace('/((\|\|)+)/e',"'</td><td class=\"wiki\"'.\$this->_table_span('\\1').'>'",$line);
         $line=str_replace('\"','"',$line); # revert \\" to \"
      }


      # InterWiki, WikiName, {{{ }}}, !WikiName, ?single, ["extended wiki name"]
      # urls, [single bracket name], [urls text], [[macro]]
      $line=preg_replace("/(".$wordrule.")/e","\$this->link_repl('\\1')",$line);

      # Headings
      $line=preg_replace("/(?<!=)(={1,5})\s+(.*)\s+(={1,5})\s?$/e",
                         "\$this->head_repl('\\1','\\2','\\3')",$line);

      # Smiley
      #if ($smiley_rule) $line=preg_replace($smiley_rule,$smiley_repl,$line);
      # NoSmoke's MultiLineCell hack
      #$line=preg_replace(array("/{{\|/","/\|}}/"),
      #      array("</div><table class='closure'><tr class='closure'><td class='closure'><div>","</div></td></tr></table><div>"),$line);

      $line=preg_replace($this->extrarule,$this->extrarepl,$line);

      if ($this->in_p == '' and $line) { #and !$this->nobr) { #and !$this->nobr) {
        $text.="<div class='p'>\n";
        $this->in_p=$line;
      } else if ($this->in_p and !$indlen and $li_open and 0) {
        $close.=$this->_check_p()."<div class='p'>\n";
        $this->in_p=$line;
      }

      $line=$close.$open.$line;
      $open="";$close="";

      if ($in_pre==-1) {
         $in_pre=0;
         if ($processor) {
           $value=$this->pre_line;
           $out= call_user_func("processor_$processor",&$this,$value,$options);
           $line=$out.$line;
         } else if ($in_quote) {
            # htmlfy '<'
            $pre=str_replace("<","&lt;",$this->pre_line);
            $pre=preg_replace($this->baserule,$this->baserepl,$pre);
            $pre=preg_replace("/(".$wordrule.")/e","\$this->link_repl('\\1')",$pre);
            $line="<pre class='quote'>\n".$pre."</pre>\n".$line;
            $in_quote=0;
         } else {
            # htmlfy '<'
            $pre=str_replace("<","&lt;",$this->pre_line);
            $line="<pre class='wiki'>\n".$pre."</pre>\n".$line;
         }
         $this->nobr=1;
      }
      if ($DBInfo->auto_linebreak && !$in_table && !$this->nobr)
        $text.=$line."<br />\n"; 
      else
        $text.=$line."\n";
      $this->nobr=0;
    }

    # highlight text
    if ($this->highlight) {
      $highlight=_preg_search_escape($this->highlight);

      $colref=preg_split("/\s+/",$highlight);
      $highlight=join("|",$colref);
      $colref=array_flip(array_map("strtolower",$colref));

      $text=preg_replace('/((<[^>]*>)|('.$highlight.'))/ie',
                         "\$this->highlight_repl('\\1',\$colref)",$text);
    }

    # close all tags
    $close="";
    # close pre,table
    if ($in_pre) $close.="</pre>\n";
    if ($in_table) $close.="</table>\n";
    # close indent
    while($in_li >= 0 && $indent_list[$in_li] > 0) {
      if ($indent_type[$in_li]!='dd' && $li_open == $in_li)
        $close.="</li>\n";
#      $close.=$this->_list(0,$indent_type[$in_li]);
      $close.=$this->_list(0,$indent_type[$in_li],"",$indent_type[$in_li-1]);
      unset($indent_list[$in_li]);
      unset($indent_type[$in_li]);
      $in_li--;
    }
    # close div
    if ($this->in_p) $close.="</div>\n"; # </para>
    $this->in_p='';

    if ($this->head_dep) {
      $odepth=$this->head_dep;
      $dum=explode(".",$this->head_num);
      $i=sizeof($dum)-1;
      while (0 <= $odepth && $i >= 0) {
         $i--;
         $odepth--;
         $close.="</div>\n"; # </section>
      }
    }

    $text.=$close;
  
    print $text;
    if ($this->sisters and !$options['nosisters']) {
      $sisters=join("\n",$this->sisters);
      $sisters=preg_replace("/(".$wordrule.")/e","\$this->link_repl('\\1')",$sisters);
      print "<div id='wikiSister'>\n<tt class='foot'>----</tt><br/>\nSister Sites Index<br />\n$sisters</div>\n";
    }

    if ($options['pagelinks']) $this->store_pagelinks();
  }

  function _parse_rlog($log) {
    global $DBInfo;
    $lines=explode("\n",$log);
    $state=0;
    $flag=0;

    $url=$this->link_url($this->page->urlname);

    $out="<h2>"._("Revision History")."</h2>\n";
    $out.="<table class='info' border='0' cellpadding='3' cellspacing='2'>\n";
    $out.="<form method='post' action='$url'>";
    $out.="<th class='info'>#</th><th class='info'>Date and Changes</th>".
         "<th class='info'>Editor</th>".
         "<th><input type='submit' value='diff'></th>".
         "<th class='info'>actions</th>".
         "<th class='info'>admin.</th>";
         #"<th><input type='submit' value='admin'></th>";
    $out.= "</tr>\n";

    $users=array();
   
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
           $inf=preg_replace("/date:\s(.*);\s+author:.*;\s+state:.*;/","\\1",$line);
           list($inf,$change)=explode('lines:',$inf,2);
           $change=preg_replace("/\+(\d+)\s\-(\d+)/",
             "<span class='diff-added'>+\\1</span><span class='diff-removed'>-\\2</span>",$change);
           $state=3;
           break;
        case 3:
           $dummy=explode(";;",$line);
           $ip=$dummy[0];
           $user=$dummy[1];
           if ($user!='Anonymous') {
             if (in_array($user,$users)) $ip=$users[$user];
             else if ($DBInfo->hasPage($user)) {
               $ip=$this->link_tag($user);
               $users[$user]=$ip;
             }
           } else if ($DBInfo->interwiki['Whois'])
             $ip="<a href='".$DBInfo->interwiki['Whois']."$ip'>$ip</a>";

           $comment=$dummy[2];
           $state=4;
           break;
        case 4:
           $rowspan=1;
           if ($comment) $rowspan=2;
           $out.="<tr>\n";
           $out.="<th valign='top' rowspan=$rowspan>r$rev</th><td nowrap='nowrap'>$inf $change</td><td>$ip&nbsp;</td>";
           $achecked="";
           $bchecked="";
           if ($flag==1)
              $achecked="checked ";
           else if (!$flag)
              $bchecked="checked ";
           $out.="<td nowrap='nowrap'><input type='radio' name='rev' value='$rev' $achecked/>";
           $out.="<input type='radio' name='rev2' value='$rev' $bchecked/>";

           $out.="<td nowrap='nowrap'>".$this->link_to("?action=recall&rev=$rev","view").
                 " ".$this->link_to("?action=raw&rev=$rev","raw");
           if ($flag)
              $out.= " ".$this->link_to("?action=diff&rev=$rev","diff");
           $out.="</td><th>";
           if ($flag)
              $out.="<input type='checkbox' name='range[$flag]' value='$rev' />";
           $out.="</th></tr>\n";
           if ($comment)
              $out.="<tr><td class='info' colspan='5'>$comment&nbsp;</td></tr>\n";
           $state=1;
           $flag++;
           break;
      }
    }
    $out.="<tr><td colspan='6' align='right'><input type='checkbox' name='show' checked='checked' />show only ";
    if ($DBInfo->security->is_protected("rcspurge",array())) {
      $out.="<input type='password' name='passwd'>";
    }
    $out.="<input type='submit' name='rcspurge' value='purge'></td></tr>";
    $out.="<input type='hidden' name='action' value='diff'/></form></table>\n";
    return $out; 
  }

  function show_info() {
    $fp=popen("rlog -x,v/ ".$this->page->filename,"r");
#   if (!$fp)
#      print "No older revisions available";
# XXX
    while (!feof($fp)) {
      $line=fgets($fp,1024);
      $out .= $line;
    }
    pclose($fp);

    $msg=_("No older revisions available");
    if (!$out)
      print "<h2>$msg</h2>";
    else
      print $this->_parse_rlog($out);
  }

  function simple_diff($diff) {
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

  function fancy_diff($diff) {
    global $DBInfo;
    include_once("lib/difflib.php");
    $diff=str_replace("<","&lt;",$diff);
    $lines=explode("\n",$diff);
    $out="";
    unset($lines[0]); unset($lines[1]);

    $omarker=0;
    $orig=array();$new=array();
    foreach ($lines as $line) {
      $marker=$line[0];
      $line=substr($line,1);
      if ($marker=="@") $line='<div class="diff-sep">@'."$line</div>";
      else if ($marker=="-") {
        $omarker=1; $orig[]=$line; continue;
      }
      else if ($marker=="+") {
        $omarker=1; $new[]=$line; continue;
      }
      else if ($omarker) {
        $omarker=0;
        $buf="";
        $result = new WordLevelDiff($orig, $new, $DBInfo->charset);
        foreach ($result->orig() as $ll)
          $buf.= "<div class=\"diff-removed\">$ll</div>\n";
        foreach ($result->final() as $ll)
          $buf.= "<div class=\"diff-added\">$ll</div>\n";
        $orig=array();$new=array();
        $line=$buf.$line."<br />";
      }
      else if ($marker==" " and !$omarker)
        $line.="<br />";
      else if ($marker=="\\" && $line==" No newline at end of file") continue;
      $out.=$line."\n";
    }
    return $out;
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
    $opts[rev]=$this->page->get_rev();
   
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

      if (!$out) {
         $msg=_("No difference found");
         print "<h2>$msg</h2>";
      } else {
         $msg= _("Difference between yours and the current");
         print "<h2>$msg</h2>";
         $diff_type=$DBInfo->diff_type;
         print $this->$diff_type($out);
      }
      return;
    }

    if (!$rev1 and !$rev2) {
      $rev1=$this->page->get_rev();
    } else if (0 === strcmp($rev1 , (int)$rev1)) {
      $rev1=$this->page->get_rev($rev1);
    } else if ($rev1==$rev2) $rev2="";
    if ($rev1) $option="-r$rev1 ";
    if ($rev2) $option.="-r$rev2 ";

    if (!$option) {
      $msg= "No older revisions available";
      print "<h2>$msg</h2>";
      return;
    }
    $fp=popen("rcsdiff -x,v/ -u $option ".$this->page->filename,"r");
    if (!$fp)
      return;
    while (!feof($fp)) {
      $line=fgets($fp,1024);
      $out.= $line;
    }
    pclose($fp);
    if (!$out) {
      $msg= "No difference found";
      print "<h2>$msg</h2>";
    } else {
      if ($rev1==$rev2) print "<h2>"._("Difference between versions")."</h2>";
      else if ($rev1 and $rev2) {
        $msg= sprintf(_("Difference between r%s and r%s"),$rev1,$rev2);
        print "<h2>$msg</h2>";
      }
      else if ($rev1 or $rev2) {
        $msg=sprintf(_("Difference between r%s and the current"),$rev1.$rev2);
        print "<h2>$msg</h2>";
      }
      $diff_type=$DBInfo->diff_type;
      print $this->$diff_type($out);
    }
  }

  function send_header($header="",$options=array()) {
    global $DBInfo;
    $plain=0;

    if ($this->pi["#redirect"] != '' && $options['pi']) {
      $options['value']=$this->pi['#redirect'];
      $options['redirect']=1;
      $this->pi['#redirect']='';
      do_goto($this,$options);
      return;
    }
    if ($header) {
      if (is_array($header))
        foreach ($header as $head) {
          $this->header($head);
          if (preg_match("/^content\-type: text\/plain/i",$head))
            $plain=1;
        }
      else {
        $this->header($header);
        if (preg_match("/^content\-type: text\/plain/i",$header))
          $plain=1;
      }
    }
    if (isset($options['trail']))
      $this->set_trailer($options['trail'],$this->page->name);

    if (!$plain) {
      if (empty($options['title'])) $options['title']=$this->page->name;
      if (empty($options['css_url'])) $options['css_url']=$DBInfo->css_url;
      print <<<EOS
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
  <meta http-equiv="Content-Type" content="text/html;charset=$DBInfo->charset" /> 
  $DBInfo->metatags
  <title>$DBInfo->sitename:$options[title]</title>\n
EOS;
      if ($options['css_url'])
         print '<link rel="stylesheet" type="text/css" href="'.
               $options['css_url'].'"/>';
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

textarea.wiki { width:100%; }

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

#wikiHint {
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

  function send_footer($args=array(),$options="") {
    global $DBInfo;

    print "</div>\n";
    print $DBInfo->hr;
    $menu="";
    if ($this->pi['#action'] && !in_array($this->pi['#action'],$this->actions)){
      list($act,$txt)=explode(" ",$this->pi['#action'],2);
      if (!$txt) $txt=$act;
      $menu= $this->link_to("?action=$act",_($txt),"accesskey='x'");
      if (strtolower($act) == 'blog')
        $this->actions[]='BlogRss';
        
    } else if ($args['editable']) {
      if ($DBInfo->security->writable($options))
        $menu= $this->link_to("?action=edit",_("EditText"),"accesskey='x'");
      else
        $menu= _("NotEditable");
    } else
      $menu.= $this->link_to("?action=show",_("ShowPage"));
    $menu.=$this->menu_sep.$this->link_tag("FindPage","",_("FindPage"));

    if (!$args['noaction']) {
      foreach ($this->actions as $action)
        $menu.= $this->menu_sep.$this->link_to("?action=$action",_($action));
    }
    $menu = $this->menu_bra.$menu.$this->menu_cat;

    if ($mtime=$this->page->mtime()) {
      $lastedit=date("Y-m-d",$mtime);
      $lasttime=date("H:i:s",$mtime);
    }

    $banner= <<<FOOT
 <a href="http://validator.w3.org/check/referer"><img
  src="$DBInfo->imgs_dir/valid-xhtml10.png"
  border="0" width="88" height="31"
  align="middle"
  alt="Valid XHTML 1.0!" /></a>

 <a href="http://jigsaw.w3.org/css-validator/check/referer"><img
  src="$DBInfo->imgs_dir/vcss.png" 
  border="0" width="88" height="31"
  align="middle"
  alt="Valid CSS!" /></a>

 <a href="http://moniwiki.sourceforge.net/"><img
  src="$DBInfo->imgs_dir/moniwiki-powerd.png" 
  border="0" width="88" height="31"
  align="middle"
  alt="powerd by MoniWiki" /></a>
FOOT;

    if ($options['timer']) {
      $options['timer']->Check();
      $timer=$options['timer']->Total();
    }

    if (file_exists($this->themedir."/footer.php")) {
      $themeurl=$this->themeurl;
      include($this->themedir."/footer.php");
    } else {
      print "<div id='wikiFooter'>";
      print $menu.$banner;
      print "\n</div>\n";
    }
    print "</body>\n</html>\n";
    #include "prof_results.php";
  }

  function send_title($title="", $link="", $options="") {
    // Generate and output the top part of the HTML page.
    global $DBInfo;

    $name=$this->page->urlname;
    $action=$this->link_url($name);

    # find upper page
    $pos=strrpos($name,"/");
    if ($pos > 0) $upper=substr($name,0,$pos);
    else if ($this->group) $upper=substr($this->group,0,-1);

    if (!$title) $title=$options['title'];
    if (!$title) {
      if ($this->group) { # for UserNameSpace
        $group=$this->group;
        $title=substr($this->page->name,strlen($group));
        $name=$title;
        $group="<span class='group'>".(substr($group,0,-1))." &raquo;<br /></span>";
      } else     
        $title=$this->page->name;
      $title=preg_replace("/((?<=[a-z0-9])[A-Z][a-z0-9])/"," \\1",$title);
    }
    # setup title variables
    $heading=$this->link_to("?action=fullsearch&amp;value=$name",$title);
    $title="$group<span class='wikiTitle'><b>$title</b></span>";
    if ($link)
      $title="<a href=\"$link\" class='wikiTitle'>$title</a>";
    else if (empty($options['nolink']))
      $title=$this->link_to("?action=fullsearch&amp;value=$name",$title,"class='wikiTitle'");
    $logo=$this->link_tag($DBInfo->logo_page,'',$DBInfo->logo_string);
    $goto_form=$DBInfo->goto_form ?
      $DBInfo->goto_form : goto_form($action,$DBInfo->goto_type);

    if ($options['msg']) {
      $msg=<<<MSG
<table class="message" width="100%"><tr><td class="message">
$options[msg]
</td></tr></table>
MSG;
    }

    # navi bar
    $menu=array();
    if ($options['quicklinks']) {
      $quicklinks=array_flip(explode("\t",$options['quicklinks']));
    } else {
      $quicklinks=$this->menu;
    }
    $sister_save=$this->sister_on;
    $this->sister_on=0;
    foreach ($quicklinks as $item=>$attr) {
      if (strpos($item,' ') === false)
        $menu[]=$this->link_tag($item,"",_($item),$attr);
      else
        $menu[]=$this->link_repl($item,$attr);
    }
    $this->sister_on=$sister_save;
    $menu=$this->menu_bra.join($this->menu_sep,$menu).$this->menu_cat;

    # icons
    if ($upper)
      $upper_icon=$this->link_tag($upper,'',$this->icon['upper'])." ";

    if ($this->icons) {
      $icon=array();
      foreach ($this->icons as $item) {
        if ($item[3]) $attr=$item[3];
        else $attr='';
        $icon[]=$this->link_tag($item[0],$item[1],$item[2],$attr);
      }
      $icons=$this->icon_bra.join($this->icon_sep,$icon).$this->icon_cat;
    }

    $rss_icon=$this->link_tag("RecentChanges","?action=rss_rc",$this->icon['rss'])." ";

    # UserPreferences
    if ($options['id'] != "Anonymous") {
      $user_link=$this->link_tag("UserPreferences","",$options['id']);
      if ($DBInfo->hasPage($options['id']))
      $home=$this->link_tag($options['id'],"",$this->icon['home'])." ";
    } else
      $user_link=$this->link_tag("UserPreferences","",_($this->icon['user']));

    # print the title
    kbd_handler();

    if (file_exists($this->themedir."/header.php")) {
      $themeurl=$this->themeurl;
      include($this->themedir."/header.php");
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
      print "<div id='wikiHeader'>\n";
      print $header;
      print $menu.$user_link." ".$upper_icon.$icons.$home.$rss_icon;
      print $msg;
      print "</div>\n";
    }
    print $DBInfo->hr;
    if ($options['trail']) {
      $opt['nosisters']=1;
      print "<div id='wikiTrailer'>\n";
      print $this->trail;
      print "</div>\n";
    }
    print "<div id='wikiBody'>\n";
  }

  function send_editor($text="",$options="") {
    global $DBInfo;

    $COLS_MSIE = 80;
    $COLS_OTHER = 85;
    $cols = preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT']) ? $COLS_MSIE : $COLS_OTHER;

    $rows=$options['rows'] > 5 ? $options['rows']: 16;
    $cols=$options['cols'] > 60 ? $options['cols']: $cols;

    $preview=$options['preview'];

    $url=$this->link_url(_urlencode($option['page']));

    if (!$this->page->exists()) {
       $options['linkto']="?action=edit&amp;template=";
       print _("Use one of the following templates as an initial release :\n");
       print macro_TitleSearch($this,".*Template",$options);
       print _("To create your own templates, add a page with a 'Template' suffix.\n");
    }

    if ($options['conflict'])
       $extra='<input type="submit" name="button_merge" value="Merge" />';

    print "<a id='editor' name='editor' />\n";
    $previewurl=$this->link_url(_urlencode($options['page']),"#preview");
    #printf('<form method="post" action="%s">', $url);
    printf('<form method="post" action="%s">', $previewurl);
    print $this->link_to("?action=edit&amp;rows=".($rows-3),_("ReduceEditor"))." | ";
    print $this->link_tag('InterWiki',"",_("InterWiki"))." | ";
    print $this->link_tag('HelpOnEditing',"",_("HelpOnEditing"));
    if ($preview)
       print "|".$this->link_to('#preview',_("Skip to preview"));
    printf("<br />\n");
    if ($text) {
      $raw_body = str_replace('\r\n', '\n', $text);
    } else if ($this->page->exists()) {
      $raw_body = str_replace('\r\n', '\n', $this->page->_get_raw_body());
    } else if ($options['template']) {
      $p= new WikiPage($options['template']);
      $raw_body = str_replace('\r\n', '\n', $p->get_raw_body());
    } else
      $raw_body = sprintf(_("Describe %s here"), $options['page']);

    # for conflict check
    if ($options['datestamp'])
       $datestamp= $options['datestamp'];
    else
       $datestamp= $this->page->mtime();

    $raw_body = str_replace(array("&","<"),array("&amp;","&lt;"),$raw_body);

    # get categories
    $categories=array();
    $categories= $DBInfo->getLikePages($DBInfo->category_regex);
    if ($categories) {
      $select_category="<select name='category'>\n<option value=''>"._("--Select Category--")."</option>\n";
      foreach ($categories as $category)
        $select_category.="<option value='$category'>$category</option>\n";
      $select_category.="</select>\n";
    }

    $preview_msg=_("Preview");
    $save_msg=_("Save");
    $summary_msg=_("Summary of Change");
    print <<<EOS
<textarea class="wiki" id="content" wrap="virtual" name="savetext"
 rows="$rows" cols="$cols" class="wiki">$raw_body</textarea><br />
$summary_msg: <input name="comment" size="70" maxlength="70" style="width:200" /><br />
<input type="hidden" name="action" value="savepage" />
<input type="hidden" name="datestamp" value="$datestamp">
$select_category
<input type="submit" value="$save_msg" />&nbsp;
<!-- <input type="reset" value="Reset" />&nbsp; -->
<input type="submit" name="button_preview" value="$preview_msg" />
$extra
</form>
EOS;
    $this->show_hints();
    print "<a id='preview' name='preview' />";
  }

  function show_hints() {
    print "<div class=\"hint\">\n";
    print _("<b>Emphasis:</b> ''<i>italics</i>''; '''<b>bold</b>'''; '''''<b><i>bold italics</i></b>''''';\n''<i>mixed '''<b>bold</b>''' and italics</i>''; ---- horizontal rule.<br />\n<b>Headings:</b> = Title 1 =; == Title 2 ==; === Title 3 ===;\n==== Title 4 ====; ===== Title 5 =====.<br />\n<b>Lists:</b> space and one of * bullets; 1., a., A., i., I. numbered items;\n1.#n start numbering at n; space alone indents.<br />\n<b>Links:</b> JoinCapitalizedWords; [\"brackets and double quotes\"];\n[bracketed words];\nurl; [url]; [url label].<br />\n<b>Tables</b>: || cell text |||| cell text spanning two columns ||;\nno trailing white space allowed after tables or titles.<br />\n");
    print "</div>\n";
  }

  function set_trailer($trailer="",$pagename,$size=5) {
    global $DBInfo;
    if (!$trailer) $trail=$DBInfo->frontpage;
    else $trail=$trailer;
    $trails=array_diff(explode("\t",trim($trail)),array($pagename));

    $sister_save=$this->sister_on;
    $this->sister_on=0;
    $this->trail="";
    foreach ($trails as $page) {
      $this->trail.=$this->word_repl($page,'','',1)." &#x203a; ";
    }
    $this->trail.= " $pagename";
    $this->pagelinks=array(); # reset pagelinks
    $this->sister_on=$sister_save;

    if (!in_array($pagename,$trails)) $trails[]=$pagename;

    $idx=count($trails) - $size;
    if ($idx > 0) $trails=array_slice($trails,$idx);
    $trail=join("\t",$trails);

    setcookie("MONI_TRAIL",$trail,time()+60*60*24*30,get_scriptname());
  }
} # end-of-Formatter

# setup the locale like as the phpwiki style
function get_langs() {
  $lang= $_SERVER['HTTP_ACCEPT_LANGUAGE'];
  $langs=explode(",",preg_replace(array("/;[^;,]+/","/\-[a-z]+/"),"",$lang));
  return $langs;
}

# get the pagename
function get_pagename() {
  global $DBInfo;
  // $_SERVER["PATH_INFO"] has bad value under CGI mode
  // set 'cgi.fix_pathinfo=1' in the php.ini under
  // apache 2.0.x + php4.2.x Win32
  if (!empty($_SERVER['PATH_INFO'])) {
    if ($_SERVER['PATH_INFO'][0] == '/')
      $pagename=substr($_SERVER['PATH_INFO'],1);
    if (!$pagename) {
      $pagename = $DBInfo->frontpage;
    }
    $pagename=stripslashes($pagename);
  } else if (!empty($_SERVER['QUERY_STRING'])) {
    if (isset($goto)) $pagename=$goto;
    else {
      $pagename = $_SERVER['QUERY_STRING'];
      $temp = strtok($pagename,"&");

      if ($temp and strpos($temp,"="))
        $pagename = $DBInfo->frontpage;
      else
        $result = preg_match('/^([^&=]+)/',$pagename,$matches);
      if ($result) {
        $pagename = urldecode($matches[1]);
        $_SERVER['QUERY_STRING']=substr($_SERVER['QUERY_STRING'],strlen($pagename));
      }
    }
    if (!$pagename) $pagename= $DBInfo->frontpage;
  } else {
    $pagename = $DBInfo->frontpage;
  }

  if ($pagename[0]=='~' and ($p=strpos($pagename,"/")))
    $pagename=substr($pagename,1,$p-1)."~".substr($pagename,$p+1);
  return $pagename;
}

# Start Main
$Config=getConfig("config.php",array('init'=>1));

$DBInfo= new WikiDB($Config);
register_shutdown_function(array(&$DBInfo,'Close'));

$user=new User();
$options=array();
$options['id']=$user->id;

# MoniWiki theme
if (!$DBInfo->theme) $theme=$_GET['theme'];
else $theme=$DBInfo->theme;
if ($theme) $options['theme']=$theme;

if ($DBInfo->trail)
  $options['trail']=$user->trail;
if ($user->id != "Anonymous") {
  $udb=new UserDB($DBInfo);
  $user=$udb->getUser($user->id);
  $options['css_url']=$user->info['css_url'];
  $options['quicklinks']=$user->info['quicklinks'];
  #$options['name']=$user->info[name];
  if (!$theme) $options['theme']=$user->info['theme'];
} else {
  $options['css_url']=$user->css;
  if (!$theme) $options['theme']=$user->theme;
}

if ($DBInfo->theme and $DBInfo->theme_css)
  $options['css_url']=$DBInfo->url_prefix."/theme/$theme/css/default.css";

$options['timer']=&$timing;
$options['timer']->Check("load");
# get broswer's settings
$langs=get_langs();

if ($DBInfo->lang == 'auto') $lang= $langs[0].strtoupper($DBInfo->charset);
else $lang= $DBInfo->lang;

if (isset($locale)) {
  $lf="locale/".$lang."/LC_MESSAGES/moniwiki.php";
  if (file_exists($lf)) include_once($lf);
} else if ($lang != 'en') {
  setlocale(LC_ALL, $lang);
  bindtextdomain("moniwiki", "locale");
  textdomain("moniwiki");
}

$pagename=get_pagename();
//function render($pagename,$options) {
if ($pagename) {
  global $DBInfo;
  global $value,$action;
  # get primary variables
  if ($_SERVER['REQUEST_METHOD']=="POST") {
    if (!$GLOBALS['HTTP_RAW_POST_DATA']) {
      $action=$_POST['action'];
      $value=$_POST['value'];
      $goto=$_POST['goto'];
    } else {
      # RAW posted data. the $value and $action could be accessed under
      # "register_globals = On" in the php.ini
      $options['value']=$value;
    }
  } else if ($_SERVER['REQUEST_METHOD']=="GET") {
    $action=$_GET['action'];
    $value=$_GET['value'];
    $goto=$_GET['goto'];
    $rev=$_GET['rev'];
  }

  #print $_SERVER['REQUEST_URI'];
  $options['page']=$pagename;

  if ($action=="recall" || $action=="raw" && $rev) {
    $options['rev']=$rev;
    $page = $DBInfo->getPage($pagename,$options);
  } else
    $page = $DBInfo->getPage($pagename);

  $formatter = new Formatter($page,$options);

  if (!$action or $action=='show') {
    if ($value) { # ?value=Hello
      $options['value']=$value;
      do_goto($formatter,$options);
      return;
    } else if ($goto) { # ?goto=Hello
      $options['value']=$goto;
      do_goto($formatter,$options);
      return;
    }
    if (!$page->exists()) {
      $formatter->send_header("Status: 404 Not found",$options);

      $twins=$DBInfo->metadb->getTwinPages($page->name,1);
      if ($twins) {
        $formatter->send_title($page->name,"",$options);
        $twins=join("\n",$twins);
        $formatter->send_page(_("See TwinPages: ").$twins);
        echo "<br />or ".
          $formatter->link_to("?action=edit",$formatter->icon['create']._("Create this page"));
      } else {
        $formatter->send_title(sprintf("%s Not Found",$page->name),"",$options);
        print $formatter->link_to("?action=edit",$formatter->icon['create']._("Create this page"));
        print macro_LikePages($formatter,$page->name,&$err);
        if ($err['extra'])
          print $err['extra'];

        print "<hr />\n";
        print $formatter->link_to("?action=edit",$formatter->icon['create']._("Create this page"));
        print _(" or alternativly, use one of these templates:\n");
        $options['linkto']="?action=edit&amp;template=";
        print macro_TitleSearch($formatter,".*Template",$options);
        print _("To create your own templates, add a page with a 'Template' suffix\n");
      }

      $args['editable']=1;
      $formatter->send_footer($args,$options);
      return;
    }
    # display this page

    # increase counter
    $DBInfo->counter->incCounter($pagename,$options);

    if (!$action) $options['pi']=1; # protect a recursivly called #redirect

    $formatter->get_redirect();
    $formatter->send_header("",$options);
    $formatter->send_title("","",$options);
    $formatter->write("<div id='wikiContent'>\n");
    $options['timer']->Check("init");
#    $cache=new Cache_text('pages');
#    if ($cache->exists($pagename)) {
#      print $cache->fetch($pagename);
#    } else {
#      ob_start();
      $formatter->send_page();
      if ($DBInfo->use_referer)
        log_referer($_SERVER['HTTP_REFERER'],$pagename);
      flush();
#      $out=ob_get_contents();
#      ob_end_clean();
#      print $out;
#      $cache->update($pagename,$out);
#    }
    
    $options['timer']->Check("send_page");
    $formatter->write("</div>\n");
    $args['editable']=1;
    $formatter->send_footer($args,$options);
    return;
  }

  if ($action) {
    if (!$DBInfo->security->is_allowed($action,&$options)) {
      $msg=sprintf(_("Please login before \"%s\" this page"),$action);
      $formatter->send_header("Status: 406 Not Acceptable",$options);
      $formatter->send_title($msg,"", $options);
      $formatter->send_page("== "._("Goto UserPreferences")." ==\n".$options['err']);
      $formatter->send_footer($args,$options);
      return;
    } else if ($_SERVER['REQUEST_METHOD']=="POST" and
       $DBInfo->security->is_protected($action,&$options) and
       !$DBInfo->security->is_valid_password($_POST['passwd'],$options)) {
      # protect some POST actions and check a password

      $title = sprintf(_("Fail to \"%s\" !"), $action);
      $formatter->send_header("",$options);
      $formatter->send_title($title,"",$options);
      $formatter->send_page("== "._("Please enter the valid password")." ==");
      $formatter->send_footer("",$options);
      return;
    }

    if (!function_exists("do_post_".$action) and
      !function_exists("do_".$action)){
      if ($plugin=getPlugin($action))
        include_once("plugin/$plugin.php");
    }

    if (function_exists("do_".$action)) {
      if ($_SERVER['REQUEST_METHOD']=="POST")
        $options=array_merge($_POST,$options);
      else
        $options=array_merge($_GET,$options);
      call_user_func("do_$action",$formatter,$options);
      return;
    } else if (function_exists("do_post_".$action)) {
      if ($_SERVER['REQUEST_METHOD']=="POST")
        $options=array_merge($_POST,$options);
      else { # do_post_* set some primary variables as $options
        $options['value']=$_GET['value'];
      }
      call_user_func("do_post_$action",$formatter,$options);
      return;
    }
    do_invalid($formatter,$options);
    return;
  }
}

//$pagename=get_pagename();
//render($pagename,$options);
// vim:et:ts=2:
?>
