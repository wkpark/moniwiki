<?php
// Copyright 2003-2004 Won-Kyu Park <wkpark at kldp.org> all rights reserved.
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
$_release = '1.1';

#ob_start("ob_gzhandler");

error_reporting(E_ALL ^ E_NOTICE);
#error_reporting(E_ALL);
$Config=getConfig("config.php",array('init'=>1));
include("wikilib.php");

$timing=new Timer();

function getPlugin($pluginname) {
  static $plugins=array();
  if ($plugins) return $plugins[strtolower($pluginname)];
  global $DBInfo;
  if ($DBInfo->include_path)
    $dirs=explode(':',$DBInfo->include_path);
  else
    $dirs=array('.');
 
  foreach ($dirs as $dir) {
    $handle= @opendir($dir.'/plugin');
    if (!$handle) continue;
    while ($file= readdir($handle)) {
      if (is_dir($dir."/plugin/$file")) continue;
      $name= substr($file,0,-4);
      $plugins[strtolower($name)]= $name;
    }
  }

  return $plugins[strtolower($pluginname)];
}

function getModule($module,$name) {
  $mod=$module.'_'.$name;
  if (!class_exists($mod))
    include_once('lib/'.strtolower($module).'.'.$name.'.php');
  return $mod;
}

function getProcessor($pro_name) {
  static $processors=array();
  if ($processors) return $processors[strtolower($pro_name)];
  global $DBInfo;
  if ($DBInfo->include_path)
    $dirs=explode(':',$DBInfo->include_path);
  else
    $dirs=array('.');

  foreach ($dirs as $dir) {
    $handle= @opendir($dir.'/plugin/processor');
    if (!$handle) continue;
    while ($file= readdir($handle)) {
      if (is_dir($dir."/plugin/processor/$file")) continue;
      $name= substr($file,0,-4);
      $processors[strtolower($name)]= $name;
    }
  }

  if (is_array($DBInfo->processors))
    $processors=array_merge($processors,$DBInfo->processors);

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
</form>
";
  } else if ($type==2) {
    return "
<form name='go' id='go' method='get' action='$action'>
<select name='action' style='width:60px'>
<option value='goto'>goto</option>
<option value='titlesearch'>TitleSearch</option>
<option value='fullsearch'>FullSearch</option>
</select>
<input type='text' name='value' class='goto' accesskey='s' size='20' />
<input type='submit' name='status' value='Go' />
</form>
";
  } else if ($type==3) {
    return "
<form name='go' id='go' method='get' action='$action'>
<table class='goto'>
<tr><td nowrap='nowrap' style='width:220px'>
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
<input type='text' name='value' size='20' accesskey='s' class='goto' style='width:100px' />
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
_qp="$sep";
FrontPage= "$DBInfo->frontpage";
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

      if (strlen($sisters) > 40) return "TwinPages:$pagename";

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
      "deletepage","deletefile","rename","rcspurge","rcs","chmod","backup","restore");
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

  #foreach ($options as $key=>$val) $$key=$val;
  extract($options);
  unset($key,$val,$options);
  include($configfile);
  unset($configfile);

  $config=get_defined_vars();
#  print_r($config);

#  if ($menu) $config['menu']=$menu;
#  if ($icons) $config['icons']=$icons;
#  if ($icon) $config['icon']=$icon;
#  if ($actions) $config['actions']=$actions;
  if (isset($config['include_path']))
    ini_set('include_path',$config['include_path']);

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
    $this->charset='utf-8';
    $this->lang='auto';
    $this->dba_type="db3";
    $this->use_counter=0;

    $this->text_dir= $this->data_dir.'/text';
    $this->cache_dir= $this->data_dir.'/cache';
    $this->vartmp_dir= '/var/tmp';
    $this->intermap= $this->data_dir.'/intermap.txt';
    $this->interwikirule='';
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
    $this->metatags='<meta name="robots" content="noindex,nofollow" />';
    $this->use_smileys=1;
    $this->hr="<hr class='wikiHr' />";
    $this->date_fmt= 'Y-m-d';
    $this->date_fmt_rc= 'D d M Y';
    $this->date_fmt_blog= 'M d, Y';
    $this->datetime_fmt= 'Y-m-d H:i:s';
    #$this->changed_time_fmt = ' . . . . [h:i a]';
    $this->changed_time_fmt= ' [h:i a]'; # used by RecentChanges macro
    $this->admin_passwd= 'daEPulu0FLGhk'; # default value moniwiki
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
    $this->origin=0;
    $this->arrow=" &#x203a; ";
    $this->home='Home';
    $this->diff_type='fancy';
    $this->hr_type='simple';
    $this->nonexists='simple';
    $this->use_sistersites=1;
    $this->use_twinpages=1;
    $this->use_hostname=1;
    $this->pagetype=array();
    $this->smiley='wikismiley';
    $this->convmap=array(0xac00, 0xd7a3, 0x0000, 0xffff); /* for euc-kr */
    $this->theme='';

    $this->inline_latex=0;
    $this->processors=array();

    $this->perma_icon='#';
    $this->purple_icon='#';
    $this->use_purple=0;
    $this->version_class='RCS';

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

    // for lower version compatibility
    $this->imgs_dir_url=$this->imgs_dir.'/';
    $this->imgs_dir_interwiki=$this->imgs_dir.'/';

    $imgs_realdir=basename($this->imgs_dir);
    if (file_exists($imgs_realdir.'/interwiki/'.'moniwiki-16.png'))
      $this->imgs_dir_interwiki=$this->imgs_dir.'/interwiki/';

    if (empty($this->icon)) {
    $iconset=$this->iconset;
    $imgdir=$this->imgs_dir;

    // for lower version compatibility
    $ext='png';
    if (is_dir($imgs_realdir.'/'.$iconset)) $iconset.='/';
    else $iconset.='-';
    if (!file_exists($imgs_realdir.'/'.$iconset.'home.png')) $ext='gif';

    if (file_exists($imgs_realdir.'/'.$iconset.'http.png'))
      $this->imgs_dir_url=$this->imgs_dir.'/'.$iconset;

    $this->icon['upper']="<img src='$imgdir/${iconset}upper.$ext' alt='U' align='middle' border='0' />";
    $this->icon['edit']="<img src='$imgdir/${iconset}edit.$ext' alt='E' align='middle' border='0' />";
    $this->icon['diff']="<img src='$imgdir/${iconset}diff.$ext' alt='D' align='middle' border='0' />";
    $this->icon['del']="<img src='$imgdir/${iconset}deleted.$ext' alt='(del)' align='middle' border='0' />";
    $this->icon['info']="<img src='$imgdir/${iconset}info.$ext' alt='I' align='middle' border='0' />";
    $this->icon['rss']="<img src='$imgdir/${iconset}rss.$ext' alt='RSS' align='middle' border='0' />";
    $this->icon['show']="<img src='$imgdir/${iconset}show.$ext' alt='R' align='middle' border='0' />";
    $this->icon['find']="<img src='$imgdir/${iconset}search.$ext' alt='S' align='middle' border='0' />";
    $this->icon['help']="<img src='$imgdir/${iconset}help.$ext' alt='H' align='middle' border='0' />";
    $this->icon['www']="<img src='$imgdir/${iconset}www.$ext' alt='www' align='middle' border='0' />";
    $this->icon['mailto']="<img src='$imgdir/${iconset}email.$ext' alt='M' align='middle' border='0' />";
    $this->icon['create']="<img src='$imgdir/${iconset}create.$ext' alt='N' align='middle' border='0' />";
    $this->icon['new']="<img src='$imgdir/${iconset}new.$ext' alt='U' align='middle' border='0' />";
    $this->icon['updated']="<img src='$imgdir/${iconset}updated.$ext' alt='U' align='middle' border='0' />";
    $this->icon['user']="UserPreferences";
    $this->icon['home']="<img src='$imgdir/${iconset}home.$ext' alt='M' align='middle' border='0' />";
    $this->icon['main']="<img src='$imgdir/${iconset}main.$ext' alt='^' align='middle' border='0' />";
    $this->icon['print']="<img src='$imgdir/${iconset}print.$ext' alt='P' align='middle' border='0' />";
    $this->icon['attach']="<img src='$imgdir/${iconset}attach.$ext' alt='@' align='middle' border='0' />";
    $this->icon['external']="<img src='$imgdir/${iconset}external.$ext' alt='[]' align='middle' border='0' />";
    $this->icon_sep=" ";
    $this->icon_bra=" ";
    $this->icon_cat=" ";
    }

    if (empty($this->icons)) {
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
      include_once($this->smiley.".php");
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
    if ($this->rcs_user)
      putenv('LOGNAME='.$this->rcs_user);
    if ($this->timezone)
      putenv('TZ='.$this->timezone);

    $this->set_intermap();
    if ($this->shared_metadb)
      $this->metadb=new MetaDB_dba($this->shared_metadb,$this->dba_type);
    if (!$this->metadb->metadb)
      $this->metadb=new MetaDB();

    if ($this->use_counter)
      $this->counter=new Counter_dba($this);
    if (!$this->counter->counter)
      $this->counter=new Counter();

    if (isset($this->security_class)) {
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
    if (file_exists($this->shared_intermap))
      $map=array_merge($map,file($this->shared_intermap));

    for ($i=0;$i<sizeof($map);$i++) {
      $line=rtrim($map[$i]);
      if (!$line || $line[0]=="#" || $line[0]==" ") continue;
      if (preg_match("/^[A-Z]+/",$line)) {
        $wiki=strtok($line,' ');$url=strtok(' ');
        $this->interwiki[$wiki]=trim($url);
        $this->interwikirule.="$wiki|";
        #$dum=split("[[:space:]]",$line);
        #$this->interwiki[$dum[0]]=trim($dum[1]);
        #$this->interwikirule.="$dum[0]|";
      }
    }
    $this->interwikirule.="Self";
    $this->interwiki['Self']=get_scriptname().$this->query_prefix;
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
    $pagename=strtr($key,'_','%');
    return rawurldecode($pagename);
  }

  function getPageLists($options='') {
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
    if ($user->id != 'Anonymous') {
      $udb=new UserDB($this);
      $udb->checkUser($user);
    }
    $comment=strtr($comment,"\t"," ");
    $fp_editlog = fopen($this->editlog_name, 'a+');
    $time= time();
    if ($this->use_hostname) $host= gethostbyaddr($remote_name);
    else $host= $remote_name;
    $msg="$page_name\t$remote_name\t$time\t$host\t$user->id\t\ $comment\ \t$action\n";
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

  function editlog_raw_lines($size=6000,$quick="") {
    define(DEFSIZE,6000);
    if ($size==0) $size=DEFSIZE;
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
    $body=preg_replace("/@USERNAME@/","$id",$body);
    $body=preg_replace("/@TIME@/","[[DateTime($time)]]",$body);
    $body=preg_replace("/@SIG@/","-- $id [[DateTime($time)]]",$body);
    $body=preg_replace("/@PAGE@/",$options['page'],$body);
    $body=preg_replace("/@date@/","$time",$body);

    return $body;
  }

  function savePage(&$page,$comment="",$options=array()) {
    $user=new User();
    if ($user->id != 'Anonymous') {
      $udb=new UserDB($this);
      $udb->checkUser($user);
    }
    $REMOTE_ADDR=$_SERVER['REMOTE_ADDR'];
    $comment=escapeshellcmd($comment);

    $keyname=$this->_getPageKey($page->name);
    $key=$this->text_dir."/$keyname";

    $fp=fopen($key,"w");
    if (!$fp)
       return -1;
    flock($fp,LOCK_EX);
    $body=$this->_replace_variables($page->body,$options);
    $page->write($body);
    fwrite($fp, $body);
    flock($fp,LOCK_UN);
    fclose($fp);

    $log=$REMOTE_ADDR.';;'.$user->id.';;'.$comment;
    if ($this->version_class) {
      $class=getModule('Version',$this->version_class);
      $version=new $class ($this);
      $ret=$version->ci($page->name,$log);
    }
    # check minor edits XXX
    $minor=0;
    if ($this->use_minorcheck or $options['minorcheck']) {
      $info=$page->get_info();
      if ($info[1]) {
        eval('$check='.$info[1].';');
        if (abs($check) < 3) $minor=1;
      }
    }
    if (!$minor or !$options['minor'])
      $this->addLogEntry($keyname, $REMOTE_ADDR,$comment,"SAVE");
    return 0;
  }

  function deletePage($page,$options='') {
    $REMOTE_ADDR=$_SERVER['REMOTE_ADDR'];

    $comment=$options['comment'];

    $keyname=$this->_getPageKey($page->name);

    $delete=@unlink($this->text_dir."/$keyname");
    if ($options['history'] && $this->version_class) {
      $class=getModule('Version',$this->version_class);
      $version=new $class ($this);
      $version->delete($page->name);
    }
    $this->addLogEntry($keyname, $REMOTE_ADDR,$comment,"SAVE");

    $handle= opendir($this->cache_dir);
    while ($file= readdir($handle)) {
      if ($file[0] != '.' and is_dir("$this->cache_dir/$file")) {
        $cache= new Cache_text($file);
        $cache->remove($page->name);

        # blog cache
        if ($file == 'blogchanges') {
          $handle2= opendir("$this->cache_dir/$file");
          while ($fcache= readdir($handle2)) {
            #print $keyname.';'.$fcache."\n";
            if (preg_match("/\d+_2e$keyname$/",$fcache))
              unlink("$this->cache_dir/$file/$fcache");
          }
        } # for blog cache
      }
    }
  }

  function renamePage($pagename,$new,$options='') {
    $REMOTE_ADDR=$_SERVER['REMOTE_ADDR'];

    $okey=$this->getPageKey($pagename);
    $nkey=$this->getPageKey($new);
    $keyname=$this->_getPageKey($new);

    rename($okey,$nkey);
    if ($options['history'] && $this->version_class) {
      $class=getModule('Version',$this->version_class);
      $version=new $class ($this);
      $version->rename($pagename,$new);
    }

    $comment=sprintf(_("Rename %s to %s"),$pagename,$new);
    $this->addLogEntry($keyname, $REMOTE_ADDR,$comment,"SAVE");
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
    umask(0700);
    $key=$this->getPageKey($pagename);
    if (file_exists($key)) chmod($key,$perms);
  }
}

class Version_RCS {
  var $DB;

  function Version_RCS($DB) {
    $this->DB=$DB;
  }

  function _filename($pagename) {
    # have to be factored out XXX
    # Return filename where this word/page should be stored.
    return $this->DB->getPageKey($pagename);
  }

  function co($pagename,$rev,$opt='') {
    $filename= $this->_filename($pagename);

    $fp=@popen("co -x,v/ -q -p\"".$rev."\" ".$filename,"r");
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
    $pagename=escapeshellcmd($pagename);
    $ret=system("ci -l -x,v/ -q -t-\"".$pagename."\" -m\"".$log."\" ".$key);
  }

  function rlog($pagename,$rev='',$opt='',$oldopt='') {
    if ($rev)
      $rev = "-r$rev";
    $filename=$this->_filename($pagename);

    $fp= popen("rlog $opt $oldopt -x,v/ $rev ".$filename,"r");
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

  function diff($pagename,$rev="",$rev2="") {
    if ($rev) $option="-r$rev ";
    if ($rev2) $option.="-r$rev2 ";

    $filename=$this->_filename($pagename);
    $fp=popen("rcsdiff -x,v/ -u $option ".$filename,'r');
    if (!$fp) return '';
    while (!feof($fp)) {
      # trashing first two lines
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

    $out= $this->rlog($pagename,'',$opt);
    if ($out) {
      for ($line=strtok($out,"\n"); $line !== false;$line=strtok("\n")) {
        preg_match("/^$tag\s+([\d\.]+)$/",$line,$match);
        if ($match[1]) {
          $rev=$match[1];
          break;
        }
      }
    }
    return $rev;
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

  function _del($key) {
    unlink($key);
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
    $content='';
    if (($size=filesize($key)) >0)
      $content=fread($fp,$size);
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
  var $body;

  function WikiPage($name,$options="") {
    if ($options['rev'])
      $this->rev=$options['rev'];
    else
      $this->rev=0; # current rev.
    $this->name= $name;
    $this->filename= $this->_filename($name);
    $this->urlname= _rawurlencode($name);
    $this->body= "";
    $this->title=get_title($name);
    #$this->title=preg_replace("/((?<=[A-Za-z0-9])[A-Z][a-z0-9])/"," \\1",$name);
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
    global $DBInfo;

    if ($this->body && !$options['rev'])
       return $this->body;

    if ($this->rev || $options['rev']) {
      if ($options['rev']) $rev=$options['rev'];
      else $rev=$this->rev;

      if ($DBInfo->version_class) {
        $class=getModule('Version',$DBInfo->version_class);
        $version=new $class ($DBInfo);
        $out = $version->co($this->name,$rev);
        return $out;
      } else {
        return _("Version info does not supported by this wiki");
      }
    }

    $fp=@fopen($this->filename,"r");
    if (!$fp) {
      if (file_exists($this->filename)) {
        $out="You have no permission to see this page.\n\n";
        $out.="See MoniWiki/AccessControl\n";
        return $out;
      }
      $out=_("File does not exists");
      return $out;
    }
    $this->fsize=filesize($this->filename);
    if ($this->fsize > 0)
      $body=fread($fp,$this->fsize);
    fclose($fp);
    $this->body=$body;

    return $this->body;
  }

  function _get_raw_body() {
    $fp=@fopen($this->filename,"r");
    if ($fp) {
      $size=filesize($this->filename);
      if ($size >0)
        $this->body=fread($fp,$size);
      fclose($fp);
    } else
      return '';

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
    #if ($body)
    $this->body=$body;
  }

  function get_rev($mtime="",$last=0) {
    global $DBInfo;

    if ($DBInfo->version_class) {
      $class=getModule('Version',$DBInfo->version_class);
      $version=new $class ($DBInfo);
      $rev= $version->get_rev($this->name,$mtime,$last);

      if ($rev > 1.0)
        return $rev;
    }
    return '';
  }

  function get_info($rev='') {
    global $DBInfo;

    $info=array('','','','','');
    if (!$rev)
      $rev=$this->get_rev('',1);
    if (!$rev) return $info;

    if ($DBInfo->version_class) {
      $class=getModule('Version',$DBInfo->version_class);
      $version=new $class ($DBInfo);
      $out= $version->rlog($this->name,$rev,$opt);
    } else {
      return $info;
    }

    $state=0;
    if ($out) {
      for ($line=strtok($out,"\n"); $line !== false;$line=strtok("\n")) {
        if ($state == 0 and preg_match("/^date:\s.*$/",$line)) {
          $tmp=preg_replace("/date:\s(.*);\s+author:.*;\s+state:.*;/","\\1",rtrim($line));
          $tmp=explode('lines:',$tmp);
          $info[0]=$tmp[0];$info[1]=$tmp[1];
          $state=1;
        } else if ($state) {
          list($info[2],$info[3],$info[4])=explode(';;',$line,3);
          break;
        }
      }
    }
    return $info;
  }
}

class Formatter {
  var $sister_idx=1;
  var $group='';
  var $use_purple=1;
  var $purple_number=0;

  function Formatter($page="",$options="") {
    global $DBInfo;

    $this->page=$page;
    $this->head_num=1;
    $this->head_dep=0;
    $this->toc=0;
    $this->highlight="";
    $this->prefix= get_scriptname();
    $this->url_prefix= $DBInfo->url_prefix;
    $this->imgs_dir= $DBInfo->imgs_dir;
    $this->imgs_dir_interwiki=$DBInfo->imgs_dir_interwiki;
    $this->imgs_dir_url=$DBInfo->imgs_dir_url;
    $this->actions= $DBInfo->actions;
    $this->inline_latex=
      $DBInfo->inline_latex == 1 ? 'latex':$DBInfo->inline_latex;
    $this->use_purple=$DBInfo->use_purple;

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

    $this->external_on=0;
    $this->external_target='';
    if ($DBInfo->external_target)
      $this->external_target='target="'.$DBInfo->external_target.'" ';

    #$this->baserule=array("/<([^\s][^>]*)>/","/`([^`]*)`/",
    $this->baserule=array("/<([^\s<>])/","/`([^`' ]+)'/","/(?<!`)`([^`]*)`/",
                     "/'''([^']*)'''/","/(?<!')'''(.*)'''(?!')/",
                     "/''([^']*)''/","/(?<!')''(.*)''(?!')/",
                     "/\^([^ \^]+)\^(?=\s|$)/","/\^\^([^\^]+)\^\^(?!^)/",
                     "/(?<!,),,([^ ,]+),,(?!,)/",
                     "/(?<!_)__([^_]+)__(?!_)/","/^(-{4,})/e",
                     "/(?<!-)--([^\s][^-]+[^\s])--(?!-)/",
                     );
    $this->baserepl=array("&lt;\\1","&#96;\\1'","<tt class='wiki'>\\1</tt>",
                     "<strong>\\1</strong>","<strong>\\1</strong>",
                     "<i>\\1</i>","<i>\\1</i>",
                     "<sup>\\1</sup>","<sup>\\1</sup>",
                     "<sub>\\1</sub>",
                     "<u>\\1</u>",
                     "\$this->$DBInfo->hr_type"."_hr('\\1')",
                     #"<div class='separator'><hr class='wiki' /></div>\n",
                     "<del>\\1</del>",
                     );

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
    $punct="<\'}\]\)\|;\.\!"; # , is omitted for the WikiPedia
    $url="wiki|http|https|ftp|nntp|news|irc|telnet|mailto|file|attachment";
    if ($DBInfo->url_schemas) $url.='|'.$DBInfo->url_schemas;
    $urlrule="((?:$url):([^\s$punct]|(\.?[^\s$punct]))+)";
    #$urlrule="((?:$url):(\.?[^\s$punct])+)";
    #$urlrule="((?:$url):[^\s$punct]+(\.?[^\s$punct]+)+\s?)";
    # solw slow slow
    #(?P<word>(?:/?[A-Z]([a-z0-9]+|[A-Z]*(?=[A-Z][a-z0-9]|\b))){2,})
    $this->wordrule=
    # single bracketed rule [http://blah.blah.com Blah Blah]
    "(\[\^?($url):[^\s\]]+(\s[^\]]+)?\])|".
    # InterWiki
    # strict but slow
    #"\b(".$DBInfo->interwikirule."):([^<>\s\'\/]{1,2}[^\(\)<>\s\']+\s{0,1})|".
    #"\b([A-Z][a-zA-Z]+):([^<>\s\'\/]{1,2}[^\(\)<>\s\']+\s{0,1})|".
    #"\b([A-Z][a-zA-Z]+):([^<>\s\'\/]{1,2}[^\(\)<>\s\']+[^\(\)<>\s\',\.:\?\!]+)|".
    "(\b|\^?)([A-Z][a-zA-Z]+):([^\(\)<>\s\']+[^\(\)<>\s\',\.:\?\!]+)|".
    # "(?<!\!|\[\[)\b(([A-Z]+[a-z0-9]+){2,})\b|".
    # "(?<!\!|\[\[)((?:\/?[A-Z]([a-z0-9]+|[A-Z]*(?=[A-Z][a-z0-9]|\b))){2,})\b|".
    # WikiName rule: WikiName ILoveYou (imported from the rule of NoSmoke)
    # protect WikiName rule !WikiName
    "(?<![a-z])\!?(?:((\.{1,2})?\/)?[A-Z]([A-Z]+[0-9a-z]|[0-9a-z]+[A-Z])[0-9a-zA-Z]*)+\b|".
    # single bracketed name [Hello World]
    "(?<!\[)\!?\[([^\[:,<\s'][^\[:,>]{1,255})\](?!\])|".
    # bracketed with double quotes ["Hello World"]
    "(?<!\[)\!?\[\\\"([^\\\"]+)\\\"\](?!\])|".
    # "(?<!\[)\[\\\"([^\[:,]+)\\\"\](?!\])|".
    "($urlrule)|".
    # single linkage rule ?hello ?abacus
    #"(\?[A-Z]*[a-z0-9]+)";
    "(\?[A-Za-z0-9]+)";
    $this->footrule="\[\*[^\]]*\s[^\]]+\]";

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
    }

    if (!isset($this->menu_bra)) {
      $this->menu_bra=$DBInfo->menu_bra;
      $this->menu_cat=$DBInfo->menu_cat;
      $this->menu_sep=$DBInfo->menu_sep;
    }

    if (!$this->icons) {
      $this->icons=&$DBInfo->icons;
    }
    if (!$this->purple_icon) {
      $this->purple_icon=$DBInfo->purple_icon;
    }
    if (!$this->perma_icon) {
      $this->perma_icon=$DBInfo->perma_icon;
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

  function get_instructions(&$body) {
    global $DBInfo;
    $pikeys=array('#redirect','#action','#title','#keywords','#noindex');
    $pi=array();
    if (!$body) {
      if (!$this->page->exists()) return '';
      if ($this->pi) return $this->pi;
      $body=$this->page->get_raw_body();
      $update_body=1;
    }{

      $pos=strpos($this->page->name,'/') ? 1:0;
      $key=strtok($this->page->name,'/');
      $format=$DBInfo->pagetype[$key];
      if ($format) {
        $temp=explode("/",$format);
        $format=$temp[$pos];
      }
    }
    if (!$format and $body[0] == '<') {
      list($line, $dummy)= explode("\n", $body,2);
      if (substr($line,0,6) == '<?xml ')
        #$format='xslt';
        $format='xsltproc';
    } else {
      if ($body[0]=='#' and substr($body,0,8)=='#format ') {
        list($line,$body)=explode("\n",$body,2);
        list($tag,$format,$args)=explode(" ",$line,3);
        $pi['args']=$args;
      } else if ($body[0] == '#' and $body[1] =='!') {
        list($line, $body)= explode("\n", $body,2);
        list($format,$args)= explode(" ", substr($line,2),2);
        $pi['args']=$args;
      }
      if ($format=='wiki') $format=='';

      while ($body and $body[0] == '#') {
        # extract first line
        list($line, $body)= split("\n", $body,2);
        if ($line=='#') break;
        else if ($line[1]=='#') { $notused[]=$line; continue;}

        #list($key,$val,$args)= explode(" ",$line,2); # XXX
        list($key,$val)= explode(" ",$line,2); # XXX
        $key=strtolower($key);
        if (in_array($key,$pikeys)) { $pi[$key]=$val ? $val:1; }
        else $notused[]=$line;
      }
    }

    if ($format) {
      if ($format == 'wiki') {
        #just ignore
      } else if (function_exists("processor_".$format)) {
        $pi['#format']=$format;
      } else if ($processor=getProcessor($format)) {
        include_once("plugin/processor/$processor.php");
        $pi['#format']=$format;
      } else
        $pi['#format']='plain';
    }

    if ($notused) $body=join("\n",$notused)."\n".$body;
    if ($update_body) $this->page->write($body." "); # workaround XXX
    #if ($update_body) $this->page->write($body);
    return $pi;
  }

  function highlight_repl($val,$colref=array()) {
    static $color=array("style='background-color:#ffff99;'",
                        "style='background-color:#99ffff;'",
                        "style='background-color:#99ff99;'",
                        "style='background-color:#ff9999;'",
                        "style='background-color:#ff99ff;'",
                        "style='background-color:#9999ff;'",
                        "style='background-color:#999999;'",
                        "style='background-color:#886800;'",
                        "style='background-color:#004699;'",
                        "style='background-color:#990099;'");
    $val=str_replace("\\\"",'"',$val);
    if ($val[0]=="<") return $val;

    $key=strtolower($val);

    if (isset($colref[$key]))
      return "<strong ".($color[$colref[$key] % 10]).">$val</strong>";
    return "<strong class='highlight'>$val</strong>";
  }

  function write($raw) {
    print $raw;
  }

  function link_repl($url,$attr='') {
    $url=str_replace('\"','"',$url);
    if ($url[0]=="[") {
      $url=substr($url,1,-1);
      $force=1;
    }
    switch ($url[0]) {
    case '{':
      $url=substr($url,3,-3);
      if ($url[0]=='#' and ($p=strpos($url,' '))) {
        $col=strtok($url,' '); $url=strtok(' ');
        if (!preg_match('/^#[0-9a-f]{6}$/',$col)) $col=substr($col,1);
        return "<font color='$col'>$url</font>";
      }
      return "<tt class='wiki'>$url</tt>"; # No link
      break;
    case '[':
      $url=substr($url,1,-1);
      return $this->macro_repl($url); # No link
      break;
    case '$':
      #return processor_latex($this,"#!latex\n".$url);
      $url=preg_replace('/<\/?sup>/','^',$url);
      return $this->processor_repl($this->inline_latex,$url);
      break;
    case '#': # Anchor syntax in the MoinMoin 1.1
      $anchor=strtok($url,' ');
      return ($word=strtok('')) ? $this->link_to($anchor,$word):
                 "<a name='".($temp=substr($anchor,1))."' id='$temp'></a>";
      break;
    case '*':
      return $this->macro_repl('FootNote',$url);
      break;
    case '!':
      $url=substr($url,1);
      return $url;
      break;
    default:
      break;
    }

    if (strpos($url,":")) {
      if ($url[0]=='a') # attachment:
        return $this->macro_repl('Attachment',substr($url,11));

      if ($url[0] == '^') {
        $attr.=' target="_blank" ';
        $url=substr($url,1);
        $external_icon=$this->icon['external'];
      }

      if (preg_match("/^mailto:/",$url)) {
        $url=str_replace("@","_at_",$url);
        $link=str_replace('&','&amp;',$url);
        $name=substr($url,7);
        return $this->icon['mailto']."<a href='$link' $attr>$name</a>$external_icon";
      }

      if (preg_match("/^(w|[A-Z])/",$url)) { # InterWiki or wiki:
        if (strpos($url," ")) { # have a space ?
          $dum=explode(" ",$url,2);
          return $this->interwiki_repl($dum[0],$dum[1],$attr,$external_icon);
        }
        
        return $this->interwiki_repl($url,'',$attr,$external_icon);
      }
      if ($force or strpos($url," ")) { # have a space ?
        list($url,$text)=explode(" ",$url,2);
        $link=str_replace('&','&amp;',$url);
        if (!$text) $text=$url;
        else {
          if (preg_match("/^(http|ftp).*\.(png|gif|jpeg|jpg)$/i",$text)) {
            $text=str_replace('&','&amp;',$text);
            return "<a href='$link' $attr $this->external_target title='$url'><img border='0' alt='$url' src='$text' /></a>";
          }
          if ($this->external_on)
            $external_link='<span class="externalLink">('.$url.')</span>';
        }
        $icon=strtok($url,':');
        return "<img align='middle' alt='[$icon]' src='".$this->imgs_dir_url."$icon.png' />". "<a class='externalLink' $attr $this->ex_target href='$link'>$text</a>".$external_icon.$external_link;
      } # have no space
      $link=str_replace('&','&amp;',$url);
      if (preg_match("/^(http|https|ftp)/",$url)) {
        if (preg_match("/(^.*\.(png|gif|jpeg|jpg))(([\?&]([a-z]+=[0-9a-z]+))*)$/i",$url,$match)) {
          $url=$match[1];
          $attrs=explode('&',substr($match[3],1));
          foreach ($attrs as $arg) {
            $name=strtok($arg,'=');
            $val=strtok(' ');
            if ($name and $val) $attr.=$name.'="'.$val.'" ';
            if ($name == 'align') $attr.='class="img'.ucfirst($val).'" ';
          }
          return "<img alt='$link' $attr src='$url' />";
        }
      }
      return "<a class='externalLink' $attr href='$link' $this->ex_target>$url</a>";
    } else {
      if ($url[0]=="?") $url=substr($url,1);
      return $this->word_repl($url,'',$attr);
    }
  }

  function interwiki_repl($url,$text='',$attr='',$extra='') {
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
        return $this->word_repl($page,$page.$extra,$attr,1);
      return $this->word_repl($page,$text.$extra,$attr,1);
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

    $img="<a href='$url' target='wiki'>".
         "<img border='0' src='$this->imgs_dir_interwiki".
         strtolower($wiki)."-16.png' align='middle' height='16' ".
         "width='16' alt='$wiki:' title='$wiki:' /></a>";
    #if (!$text) $text=str_replace("%20"," ",$page);
    if (!$text) $text=urldecode($page);
    else if (preg_match("/^(http|ftp).*\.(png|gif|jpeg|jpg)$/i",$text)) {
      $text= "<img border='0' alt='$text' src='$text' />";
      $img="";
    }

    if (preg_match("/\.(png|gif|jpeg|jpg)$/i",$url))
      return "<a href='".$url."' $attr title='$wiki:$page'><img border='0' align='middle' alt='$text' src='$url' /></a>$extra";

    return $img. "<a href='".$url."' $attr title='$wiki:$page'>$text</a>$extra";
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
    $pi=$this->get_instructions($dum);
    if ($pi['#format']) return '';
    if ($this->page->exists()) {
      $body=$this->page->get_raw_body();
      # quickly generate a pseudo pagelinks
      preg_replace("/(?<!\[\[)(".$this->wordrule.")/e","\$this->link_repl('\\1')",$body);
      $this->store_pagelinks();
      if ($this->pagelinks) {
        $links=join("\n",array_keys($this->pagelinks))."\n";
        $this->pagelinks=array();
        return $links;
      }
    }
    return '';
  }

  function word_repl($word,$text='',$attr='',$nogroup=0,$islink=1) {
    global $DBInfo;
    $nonexists='nonexists_'.$DBInfo->nonexists;
    if ($word[0]=='"') { # ["extended wiki name"]
      $page=substr($word,1,-1);
      $word=$page;
    } else
      #$page=preg_replace("/\s+/","",$word); # concat words
      $page=normalize($word); # concat words

    if (!$DBInfo->use_twikilink) $islink=0;
    list($page,$page_text,$gpage)=
      normalize_word($page,$this->group,$this->page->name,$nogroup,$islink);
    if ($text) {
      if (preg_match("/^(http|ftp).*\.(png|gif|jpeg|jpg)$/i",$text)) {
        $text=str_replace('&','&amp;',$text);
        $word="<img border='0' alt='$word' src='$text' /></a>";
      } else $word=$text;
    } else 
      $word=$text=$page_text ? $page_text:$word;
    #print $text;
    $word=htmlspecialchars($word);

    $url=_urlencode($page);
    $url_only=strtok($url,'#?'); # for [WikiName#tag] [wiki:WikiName#tag Tag]
    #$query= substr($url,strlen($url_only));
    $page=urldecode($url_only);
    $url=$this->link_url($url);

    //$url=$this->link_url(_rawurlencode($page)); # XXX
    //$page=urldecode($page); # XXX
    //$page=rawurldecode($page); # C++
    if (isset($this->pagelinks[$page])) {
      $idx=$this->pagelinks[$page];
      switch($idx) {
        case 0:
          #return "<a class='nonexistent' href='$url'>?</a>$word";
          return call_user_func(array(&$this,$nonexists),$word,$url);
        case -1:
          return "<a href='$url' $attr>$word</a>";
        case -2:
          return "<a href='$url' $attr>$word</a>".
            "<tt class='sister'><a href='$url'>&#x203a;</a></tt>";
        case -3:
          #$url=$this->link_url(_rawurlencode($gpage));
          return $this->link_tag(_rawurlencode($gpage),'',$this->icon['main']).
            "<a href='$url' $attr>$word</a>";
        default:
          return "<a href='$url' $attr>$word</a>".
            "<tt class='sister'><a href='#sister$idx'>&#x203a;$idx</a></tt>";
      }
    } else if ($DBInfo->hasPage($page)) {
      $this->pagelinks[$page]=-1;
      return "<a href='$url' $attr>$word</a>";
    } else {
      if ($gpage and $DBInfo->hasPage($gpage)) {
        $this->pagelinks[$page]=-3;
        #$url=$this->link_url(_rawurlencode($gpage));
        return $this->link_tag(_rawurlencode($gpage),'',$this->icon['main']).
          "<a href='$url' $attr>$word</a>";
      }
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
           "<tt class='sister'>".
           "<a name='rsister$idx' id='rsister$idx'></a>".
           "<a href='#sister$idx'>&#x203a;$idx</a></tt>";
        }
      }
      $this->pagelinks[$page]=0;
      #return "<a class='nonexistent' href='$url'>?</a>$word";
      return call_user_func(array(&$this,$nonexists),$word,$url);
    }
  }

  function nonexists_simple($word,$url) {
    return "<a class='nonexistent' href='$url'>?</a>$word";
  }

  function nonexists_nolink($word,$url) {
    return "$word";
  }

  function nonexists_forcelink($word,$url) {
    return "<a class='nonexistent' href='$url'>$word</a>";
  }

  function nonexists_fancy($word,$url) {
    global $DBInfo;
    #if (preg_match("/^[a-zA-Z0-9\/~]/",$word))
    if (ord($word[0]) < 125) {
      $link=$word[0];
      if ($word[0]=='&') {
        $link=strtok($word,';').';';$last=strtok('');
      } else
        $last=substr($word,1);
      return "<a class='nonexistent' href='$url'>$link</a>".$last;
    }
    if (function_exists('mb_encode_numericentity')) {
      if (function_exists('iconv') and strtolower($DBInfo->charset) != 'utf-8')
        $utfword=iconv($DBInfo->charset,'utf-8',$word);
      else
        $utfword=$word;
      $mbword=mb_encode_numericentity($utfword,$DBInfo->convmap,'utf-8');
      $tag=strtok($mbword,';').';'; $last=strtok('');
      return "<a class='nonexistent' href='$url'>$tag</a>".$last;
    } else {
      return "<a class='nonexistent' href='$url'>?</a>$word";
    }
  }

  function head_repl($depth,$head) {
    $dep=strlen($depth);
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

    if ($odepth && ($depth > $odepth)) {
      $num.=".1";
    } else if ($odepth) {
      $dum=explode(".",$num);
      $i=sizeof($dum)-1;
      while ($depth < $odepth && $i > 0) {
         unset($dum[$i]);
         $i--;
         $odepth--;
      }
      $dum[$i]++;
      $num=join($dum,".");
    }

    $this->head_dep=$depth; # save old
    $this->head_num=$num;

    $prefix=$this->toc_prefix;
    if ($this->toc)
      $head="<a href='#toc'>$num</a> $head";
    $perma=" <a class='perma' href='#s$prefix-$num'>$this->perma_icon</a>";

    return "$close$open<h$dep><a id='s$prefix-$num' name='s$prefix-$num'></a> $head$perma</h$dep>";
  }

  function macro_repl($macro,$value='',$options='') {
    if (!$value and (strpos($macro,'(') !== false)) {
      preg_match("/^([A-Za-z]+)(\((.*)\))?$/",$macro,$match);
      $name=$match[1]; $args=($match[2] and !$match[3]) ? true:$match[3];
    } else {
      $name=$macro; $args=$value;
    }

    if (!function_exists ("macro_".$name)) {

      if ($plugin=getPlugin($name))
        include_once("plugin/$plugin.php");
      else
        return "[[".$name."]]";
    }
    $ret=call_user_func_array("macro_$name",array(&$this,$args,$options));
    return $ret;
  }

  function processor_repl($processor,$value,$options="") {
    if (!function_exists("processor_".$processor)) {
      $pf=getProcessor($processor);
      if (!$pf)
      return call_user_func('processor_plain',$this,$value,$options);
      include_once("plugin/processor/$pf.php");
      $processor=$pf;
    }
    return call_user_func("processor_$processor",$this,$value,$options);
  }

  function smiley_repl($smiley) {
    global $DBInfo;

    $img=$DBInfo->smileys[$smiley][3];

    $alt=str_replace("<","&lt;",$smiley);

    return "<img src='$this->imgs_dir/$img' align='middle' alt='$alt' title='$alt' />";
  }

  function link_url($pageurl,$query_string="") {
    global $DBInfo;
    $sep=$DBInfo->query_prefix;

    if (!$query_string and $this->query_string) $query_string=$this->query_string;

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

  function fancy_hr($rule) {
    $sz=($sz=strlen($rule)-4) < 6 ? ($sz ? $sz+2:0):8;
    $size=$sz ? " size='$sz'":'';
    return "<div class='separator'><hr$size class='wiki' /></div>";
  }

  function simple_hr() {
    return "<div class='separator'><hr class='wiki' /></div>";
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
      $list_type=$list_type.'>'.$this->_purple().'</li';

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

  function _check_p($in_p) {
    if ($in_p) {
      $in_p='li';
      return "</div>\n<div>"; #close
    }
    return '';
  }

  function _table_span($str,$align='') {
    $tok=strtok($str,'&');
    $len=strlen($tok)/2;
    $extra=strtok('');
    $attr=array();
    if ($extra) {
      $para=substr($extra,3,-1);
      # rowspan
      if (preg_match("/^\|(\d+)$/",$para,$match))
        $attr[]="rowspan='$match[1]'";
      else if ($para[0]=='#')
        $attr[]="bgcolor='$para'";
      else
        $attr[]=$para;
    }
    if ($align) $attr[]="align='center'";
    if ($len > 1)
      $attr[]="colspan='$len'"; #$attr[]="align='center' colspan='$len'";
    return implode(' ',$attr);
  }

  function _table($on,$attr='') {
    if ($attr) {
      $attr=substr($attr,4,-1);
    }
    if ($on)
      return "<table class='wiki' cellpadding='3' cellspacing='2' $attr>\n";
    return "</table>\n";
  }

  function _purple() {
    if (!$this->use_purple) return '';
    $id=sprintf('%03d',$this->purple_number++);
    $nid='p'.$id;
    return "<span class='purple'><a name='$nid' id='$nid'></a><a href='#$nid'>(".$id.")</a></span>";
  }

  function _div($on,&$in_div) {
    $tag=array("</div>\n","<div>\n");
    if ($on) $in_div++;
    else {
      if (!$in_div) return '';
      $in_div--;
    }
    if (!$on) $purple=$this->_purple();
    #return "(".$in_div.")".$tag[$on];
    return $purple.$tag[$on];
  }

  function _li($on,$empty='') {
    $tag=array("</li>\n",'<li>');
    if (!$on and !$empty) $purple=$this->_purple();
    return $purple.$tag[$on];
  }

  function _fixpath() {
    $this->url_prefix= qualifiedUrl($DBInfo->url_prefix);
    $this->prefix= qualifiedUrl($this->prefix);
    $this->imgs_dir= qualifiedUrl($this->imgs_dir);
  }

  function send_page($body="",$options=array()) {
    global $DBInfo;
    if ($options['fixpath']) $this->_fixpath();

    if ($body) {
      $pi=$this->get_instructions($body);
      if ($pi['#format']) {
        if ($pi['args']) $pi_line="#!".$pi['#format']." $pi[args]\n";
        print call_user_func("processor_".$pi['#format'],$this,
          $pi_line.$this->page->body,$options);
        return;
      }
      $lines=explode("\n",$body);
    } else {
      #$pi=$this->get_instructions(&$body);
      $pi=$this->get_instructions($dum);
      $body=$this->page->get_raw_body($options);
      $this->pi=$pi;
      if ($pi['#format']) {
        if ($pi['args']) $pi_line="#!".$pi['#format']." $pi[args]\n";
        print call_user_func("processor_".$pi['#format'],$this,$pi_line.$body,$options);
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

    $text='';
    $in_p='';
    $in_div=0;
    $in_li=0;
    $in_pre=0;
    $in_table=0;
    $li_open=0;
    $li_empty=0;
    $indent_list[0]=0;
    $indent_type[0]="";

    $wordrule="({{{([^}]+)}}})|".
              "\[\[([A-Za-z0-9]+(\(((?<!\]\]).)*\))?)\]\]|"; # macro
    if ($DBInfo->inline_latex) # single line latex syntax
      $wordrule.="(?<=\s|^)\\$([^\\$]+)\\$(?:\s|$)|".
                 "(?<=\s|^)\\$\\$([^\\$]+)\\$\\$(?:\s|$)|";
    #if ($DBInfo->builtin_footnote) # builtin footnote support
    $wordrule.=$this->footrule.'|';
    $wordrule.=$this->wordrule;

    foreach ($lines as $line) {
      # empty line
      if (!strlen($line)) {
        if ($in_pre) { $this->pre_line.="\n";continue;}
        if ($in_li) { $text.=$this->_purple()."<br />\n";$li_empty=1; continue;}
        if ($in_table) {
          $text.=$this->_table(0)."<br />\n";$in_table=0; continue;
        } else {
          #if ($in_p) { $text.="</div><br />\n"; $in_p='';}
          if ($in_p) { $text.=$this->_div(0,$in_div)."<br />\n"; $in_p='';}
          else if ($in_p=='') { $text.="<br />\n";}
          continue;
        }
      }
      if (!$in_pre and $line[0]=='#' and $line[1]=='#') {
        if ($line[2]=='[') {
          $macro=substr($line,4,-2);
          $text.= $this->macro_repl($macro);
        }
        continue; # comments
      }

      $p_close='';
      if (preg_match('/^-{4,}/',$line)) {
        if ($DBInfo->auto_linebreak) $this->nobr=1; // XXX
        if ($in_p) { $p_close=$this->_div(0,$in_div); $in_p='';}
      } else if ($in_p == '') {
        $p_close=$this->_div(1,$in_div);
        $in_p= $line;
      }

      if ($in_pre) {
         if (strpos($line,"}}}")===false) {
           $this->pre_line.=$line."\n";
           continue;
         } else {
           #$p=strrpos($line,"}}}");
           $p= strlen($line) - strpos(strrev($line),'}}}') - 1;
           if ($p>2 and $line[$p-3]=='\\') {
             $this->pre_line.=substr($line,0,$p-3).substr($line,$p-2)."\n";
             continue;
           }
           $this->pre_line.=substr($line,0,$p-2);
           $line=substr($line,$p+1);
           $in_pre=-1;
         }
      #} else if ($in_pre == 0 && preg_match("/{{{[^}]*$/",$line)) {
      } else if (!(strpos($line,"{{{")===false) and 
                 preg_match("/{{{[^{}]*$/",$line)) {
         #$p=strrpos($line,"{{{")-2;
         $p= strlen($line) - strpos(strrev($line),'{{{') - 3;

         $processor="";
         $in_pre=1;
         $np=0;

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
            $np=1;
            if ($line[$p+4]=='#' or $line[$p+4]=='.') {
              $pre_style=strtok(substr($line,$p+4),' ');
              $np++;
              if ($pre_style) $np+=strlen($pre_style);
            } else
              $pre_style='';
            $in_quote=1;
         }

         $this->pre_line=substr($line,$p+$np+3);
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
             if ($indent_list[$in_li] == $indlen && $indent_type[$in_li]!='dd') $line=$this->_li(0).$line;
             $numtype="";
             $indtype="ul";
           } elseif (preg_match("/^((\d+|[aAiI])\.)(#\d+)?\s/",$line,$limatch)){
             $line=preg_replace("/^((\d+|[aAiI])\.(#\d+)?)/","<li>",$line);
             if ($indent_list[$in_li] == $indlen) $line=$this->_li(0).$line;
             $numtype=$limatch[2][0];
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
            $in_li++;
            $indent_list[$in_li]=$indlen; # add list depth
            $indent_type[$in_li]=$indtype; # add list type
            $open.=$this->_list(1,$indtype,$numtype);
         } else if ($indent_list[$in_li] > $indlen) {
            while($in_li >= 0 && $indent_list[$in_li] > $indlen) {
               if ($indent_type[$in_li]!='dd' && $li_open == $in_li)
                 $close.=$this->_li(0,$li_empty);
               $close.=$this->_list(0,$indent_type[$in_li],"",$indent_type[$in_li-1]);
               unset($indent_list[$in_li]);
               unset($indent_type[$in_li]);
               $in_li--;
            }
            $li_empty=0;
         }
         if ($indent_list[$in_li] <= $indlen || $limatch) $li_open=$in_li;
         else $li_open=0;
      }

      #if (!$in_pre && !$in_table && preg_match("/^\|\|.*\|\|$/",$line)) {
      if (!$in_pre && $line[0]=='|' && !$in_table && preg_match("/^((\|\|)+)(&lt;[^>]+>)?.*\|\|$/",$line,$match)) {
         $open.=$this->_table(1,$match[3]);
         $line=preg_replace('/^((\|\|)+)(&lt;[^>]+>)?/','\\1',$line);
         $in_table=1;
      #} elseif ($in_table && !preg_match("/^\|\|.*\|\|$/",$line)){
      } elseif ($in_table && $line[0]!='|' && !preg_match("/^\|\|.*\|\|$/",$line)){
         $close=$this->_table(0).$close;
         $in_table=0;
      }
      if ($in_table) {
         $line=preg_replace('/^((?:\|\|)+(&lt;[^>]+>)?)((\s?)(.*))\|\|$/e',"'<tr class=\"wiki\"><td class=\"wiki\" '.\$this->_table_span('\\1','\\4').'>\\3</td></tr>'",$line);
         $line=preg_replace('/((\|\|)+(&lt;[^>]+>)?)(\s?)/e',"'</td><td class=\"wiki\" '.\$this->_table_span('\\1','\\4').'>\\4'",$line);
         $line=str_replace('\"','"',$line); # revert \\" to \"
      }

      # InterWiki, WikiName, {{{ }}}, !WikiName, ?single, ["extended wiki name"]
      # urls, [single bracket name], [urls text], [[macro]]
      $line=preg_replace("/(".$wordrule.")/e","\$this->link_repl('\\1')",$line);

      # Headings
      $line=preg_replace("/(?<!=)(={1,5})\s+(.*)\s+\\1\s?$/e",
                         "\$this->head_repl('\\1','\\2')",$line);
      #$line=preg_replace("/(?<!=)(={1,5})\s+(.*)\s+(={1,5})\s?$/",
      #                    $this->head_repl("$1","$2","$3"),$line);

      # Smiley
      #if ($smiley_rule) $line=preg_replace($smiley_rule,$smiley_repl,$line);
      # NoSmoke's MultiLineCell hack
      #$line=preg_replace(array("/{{\|/","/\|}}/"),
      #      array("</div><table class='closure'><tr class='closure'><td class='closure'><div>","</div></td></tr></table><div>"),$line);

      $line=preg_replace($this->extrarule,$this->extrarepl,$line);

      $line=$close.$p_close.$open.$line;
      $open="";$close="";

      if ($in_pre==-1) {
         $in_pre=0;
         if ($processor) {
           $value=$this->pre_line;
           $out= call_user_func("processor_$processor",$this,$value,$options);
           $line=$out.$line;
         } else if ($in_quote) {
            # htmlfy '<'
            $pre=str_replace("<","&lt;",$this->pre_line);
            $pre=preg_replace($this->baserule,$this->baserepl,$pre);
            $pre=preg_replace("/(".$wordrule.")/e","\$this->link_repl('\\1')",$pre);
            $attr='class="quote"';
            if ($pre_style) {
              $tag=$pre_style[0];
              $style=substr($pre_style,1);
              switch($tag) {
              case '#':
                $attr="id='$style'";
                break;
              case '.':
                $attr="class='$style'";
                break;
              }
            }
            $line="<pre $attr>\n".$pre."</pre>\n".$line;
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
    } # end rendering loop

    # highlight text
    if ($this->highlight) {
      $highlight=_preg_search_escape($this->highlight);

      $colref=preg_split("/\|/",$highlight);
      #$colref=preg_split("/\s+/",$highlight);
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
        $close.=$this->_li(0);
#     $close.=$this->_list(0,$indent_type[$in_li]);
      $close.=$this->_list(0,$indent_type[$in_li],"",$indent_type[$in_li-1]);
      unset($indent_list[$in_li]);
      unset($indent_type[$in_li]);
      $in_li--;
    }
    # close div
    #if ($in_p) $close.="</div>\n"; # </para>
    if ($in_p) $close.=$this->_div(0,$in_div); # </para>

    # activate <del></del> tag
    #$text=preg_replace("/(&lt;)(\/?del>)/i","<\\2",$text);
    $text.=$close;
  
    print $text;
    if ($this->sisters and !$options['nosisters']) {
      $sisters=join("\n",$this->sisters);
      $sisters=preg_replace("/(".$wordrule.")/e","\$this->link_repl('\\1')",$sisters);
      print "<div id='wikiSister'>\n<tt class='foot'>----</tt><br/>\nSister Sites Index<br />\n$sisters</div>\n";
    }

    if ($options['pagelinks']) $this->store_pagelinks();
  }

  function simple_diff($diff) {
    $diff=str_replace("<","&lt;",$diff);
    $out="";
    //unset($lines[0]); unset($lines[1]); // XXX

    for ($line=strtok($diff,"\n"); $line !== false;$line=strtok("\n")) {
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
    #unset($lines[0]); unset($lines[1]);

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
        foreach ($result->_final() as $ll)
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
    # recall old rev
    $opts['rev']=$this->page->get_rev();
    $orig=$this->page->get_raw_body($opts);

    if (0) {
      # save new
      $tmpf3=tempnam($DBInfo->vartmp_dir,'MERGE_NEW');
      $fp= fopen($tmpf3, 'w');
      fwrite($fp, $text);
      fclose($fp);

      $tmpf2=tempnam($DBInfo->vartmp_dir,'MERGE_ORG');
      $fp= fopen($tmpf2, 'w');
      fwrite($fp, $orig);
      fclose($fp);

      $fp=popen("merge -p ".$this->page->filename." $tmpf2 $tmpf3",'r');

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
    } else {
      include_once('lib/diff3.php');
      # current
      $current=$this->page->_get_raw_body();

      $merge= new Diff3(explode("\n",$orig),
        explode("\n",$text),explode("\n",$current));
      $out=implode("\n",$merge->merged_output());
    }

    $out=preg_replace("/(<{7}|>{7}).*\n/","\\1\n",$out);

    return $out;
  }

  function send_header($header="",$options=array()) {
    global $DBInfo;
    $plain=0;

    $media='media="screen"';
    if ($options['action']=='print') $media='';

    if ($this->pi['#redirect'] != '' && $options['pi']) {
      $options['value']=$this->pi['#redirect'];
      $options['redirect']=1;
      $this->pi['#redirect']='';
      do_goto($this,$options);
      return;
    }
    #$this->header("Expires: Tue, 01 Jan 2002 00:00:00 GMT");
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

    if (isset($this->pi['#noindex'])) {
      $metatags='<meta name="robots" content="noindex,nofollow" />';
    } else {
      if ($options['metatags'])
        $metatags=$options['metatags'];
      else {
        $metatags=$DBInfo->metatags;
      }
    }

    if (isset($options['trail']))
      $this->set_trailer($options['trail'],$this->page->name);
    else if ($DBInfo->origin)
      $this->set_origin($this->page->name);

    if (!$plain) {
      # find upper page
      $pos=strrpos($this->page->name,"/");
      if ($pos > 0) $upper=substr($this->page->urlname,0,$pos);
      else if ($this->group) $upper=_urlencode(substr($this->page->name,strlen($this->group)));
      if ($this->pi['#keywords'])
        $keywords='<meta name="keywords" content="'.$this->pi['#keywords'].'" />';
      else if ($DBInfo->use_keywords) {
        $keywords=strip_tags($this->page->title);
        $keywords=str_replace(" ",", ",$keywords);
        $keywords="<meta name=\"keywords\" content=\"$keywords\" />";
      }

      if (empty($options['title'])) {
        $options['title']=$this->pi['#title'] ? $this->pi['#title']:
          $this->page->title;
        $options['title']=
          htmlspecialchars($options['title']);
      }
      if (empty($options['css_url'])) $options['css_url']=$DBInfo->css_url;
      print <<<EOS
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
  <meta http-equiv="Content-Type" content="text/html;charset=$DBInfo->charset" /> 
  $metatags
  $keywords
EOS;
      print "  <title>$DBInfo->sitename: ".$options['title']."</title>\n";
      if ($upper)
        print '  <link rel="Up" href="'.$this->link_url($upper)."\" />\n";
      $raw_url=$this->link_url($this->page->urlname,"?action=raw");
      $print_url=$this->link_url($this->page->urlname,"?action=print");
      print '  <link rel="Alternate" title="Wiki Markup" href="'.
        $raw_url."\" />\n";
      print '  <link rel="Alternate" media="print" title="Print View" href="'.
        $print_url."\" />\n";
      if ($options['css_url'])
        print '  <link rel="stylesheet" type="text/css" '.$media.' href="'.
          $options['css_url'].'" />';
# default CSS
      else print <<<EOS
<style type="text/css">
<!--
body {font-family:Georgia,Verdana,Lucida,sans-serif; background-color:#FFF9F9;}
a:link {color:#993333;}
a:visited {color:#CE5C00;}
a:hover {background-color:#E2ECE5;color:#000;}
.wikiTitle {
  font-family:palatino, Georgia,Tahoma,Lucida,sans-serif;
  font-size:28px;
  font-weight:bold;
  color:#639ACE;
  text-decoration: none;
}
tt.wiki {font-family:Lucida Typewriter,fixed,lucida,monospace;font-size:12px;}
tt.foot {font-family:Tahoma,lucida,monospace;font-size:12px;}

pre.wiki {
  padding-left:6px;
  padding-top:6px; 
  font-family:Lucida TypeWriter,monotype,lucida,monospace;font-size:14px;
  background-color:#000000;
  color:#FFD700; /* gold */
}

textarea.wiki { width:100%; }

pre.quote {
  padding-left:6px;
  padding-top:6px;
  white-space:pre-wrap;
  white-space: -moz-pre-wrap; 
  font-family:Georgia,monotype,lucida,monospace;font-size:14px;
  background-color:#F7F8E6;
}

table.wiki { border: 0px outset #E2ECE5; }

td.wiki {
  background-color:#E2ECE2;
  border: 0px inset #E2ECE5;
}

th.info { background-color:#E2ECE2; }

h1,h2,h3,h4,h5 {
  font-family:Tahoma,sans-serif;
  padding-left:6px;
  border-bottom:1px solid #999;
}

div.diff-added {
  font-family:Verdana,Lucida Sans TypeWriter,Lucida Console,monospace;
  font-size:12px;
  background-color:#61FF61;
  color:black;
}

div.diff-removed {
  font-family:Verdana,Lucida Sans TypeWriter,Lucida Console,monospace;
  font-size:12px;
  background-color:#E9EAB8;
  color:black;
}

div.diff-sep {
  font-family:georgia,Verdana,Lucida Sans TypeWriter,Lucida Console,monospace;
  font-size:12px;
  background-color:#000000;
  color:#FFD700; /* gold */
}

div.message {
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

      print "\n</head>\n<body $options[attr]>\n";
    }
  }

  function get_actions($args='',$options) {
    if ($this->pi['#action'] && !in_array($this->pi['#action'],$this->actions)){
      list($act,$txt)=explode(" ",$this->pi['#action'],2);
      if (!$txt) $txt=$act;
      $menu= $this->link_to("?action=$act",_($txt),"accesskey='x'");
      if (strtolower($act) == 'blog')
        $this->actions[]='BlogRss';
        
    } else if ($args['editable']) {
      if ($args['editable']==1)
        $menu= $this->link_to("?action=edit",_("EditText"),"accesskey='x'");
      else
        $menu= _("NotEditable");
    } else
      $menu.= $this->link_to('?action=show',_("ShowPage"));
    $menu.=$this->menu_sep.$this->link_tag("FindPage","",_("FindPage"));

    if (!$args['noaction']) {
      foreach ($this->actions as $action)
        $menu.= $this->menu_sep.$this->link_to("?action=$action",_($action));
    }
    return $this->menu_bra.$menu.$this->menu_cat;
  }

  function send_footer($args='',$options='') {
    global $DBInfo;

    print "</div>\n";
    print $DBInfo->hr;
    if ($args['editable'] and !$DBInfo->security->writable($options))
      $args['editable']=-1;
    
    $menu=$this->get_actions($args,$options);

    if ($mtime=$this->page->mtime()) {
      $lastedit=date("Y-m-d",$mtime);
      $lasttime=date("H:i:s",$mtime);
    }

    $banner= <<<FOOT
 <a href="http://validator.w3.org/check/referer"><img
  src="$this->imgs_dir/valid-xhtml10.png"
  border="0" width="88" height="31"
  align="middle"
  alt="Valid XHTML 1.0!" /></a>

 <a href="http://jigsaw.w3.org/css-validator/check/referer"><img
  src="$this->imgs_dir/vcss.png" 
  border="0" width="88" height="31"
  align="middle"
  alt="Valid CSS!" /></a>

 <a href="http://moniwiki.sourceforge.net/"><img
  src="$this->imgs_dir/moniwiki-powered.png" 
  border="0" width="88" height="31"
  align="middle"
  alt="powered by MoniWiki" /></a>
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
    $saved_pagelinks = $this->pagelinks;

    # find upper page
    $pos=strrpos($name,"/");
    if ($pos > 0) {
      $upper=substr($name,0,$pos);
      $upper_icon=$this->link_tag($upper,'',$this->icon['upper'])." ";
    } else if ($this->group) {
      $upper=_urlencode(substr($this->page->name,strlen($this->group)));
      $upper_icon=$this->link_tag($upper,'',$this->icon['main'])." ";
    }

    if (!$title) {
      $title=htmlspecialchars($this->pi['#title']);
      if (!$title) $title=$options['title'];
    } else {
      $title=htmlspecialchars($title);
    }
    if (!$title) {
      if ($this->group) { # for UserNameSpace
        $group=$this->group;
        $title=substr($this->page->name,strlen($group));
        $name=$title;
        $group="<div class='wikiGroup'>".(substr($group,0,-1))." &raquo;</div>";
      } else     
        $title=$this->page->title;
      $title=htmlspecialchars($title);
    }
    # setup title variables
    #$heading=$this->link_to("?action=fullsearch&amp;value="._urlencode($name),$title);
    $title="$group<span class='wikiTitle'><b>$title</b></span>";
    if ($link)
      $title="<a href=\"$link\" class='wikiTitle'>$title</a>";
    else if (empty($options['nolink']))
      $title=$this->link_to("?action=fullsearch&amp;value="._urlencode($name),$title,"class='wikiTitle'");
    $logo=$this->link_tag($DBInfo->logo_page,'',$DBInfo->logo_string);
    $goto_form=$DBInfo->goto_form ?
      $DBInfo->goto_form : goto_form($action,$DBInfo->goto_type);

    if ($options['msg']) {
      $msg=<<<MSG
<div class="message">
$options[msg]
</div>
MSG;
    }

    # navi bar
    $menu=array();
    if ($options['quicklinks']) {
      # get from the user setting
      $quicklinks=array_flip(explode("\t",$options['quicklinks']));
    } else {
      # get from the config.php
      $quicklinks=$this->menu;
    }
    $sister_save=$this->sister_on;
    $this->sister_on=0;
    foreach ($quicklinks as $item=>$attr) {
      if (strpos($item,' ') === false) {
        if (strpos($attr,'=') === false) $attr="accesskey='$attr'";
        # like 'MoniWiki'=>'accesskey="1"'
        $menu[]=$this->word_repl($item,_($item),$attr);
#        $menu[]=$this->link_tag($item,"",_($item),$attr);
      } else {
        # like a 'http://moniwiki.sf.net MoniWiki'
        $menu[]=$this->link_repl($item,$attr);
      }
    }
    $this->sister_on=$sister_save;
    $menu=$this->menu_bra.join($this->menu_sep,$menu).$this->menu_cat;

    # icons
    #if ($upper)
    #  $upper_icon=$this->link_tag($upper,'',$this->icon['upper'])." ";

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

    #
    if (file_exists($this->themedir."/header.php")) {
      $trail=&$option['trail'];
      $origin=&$this->origin;

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
    if (empty($themeurl) or !$_NEWTHEME) {
      print $DBInfo->hr;
      if ($options['trail']) {
        print "<div id='wikiTrailer'>\n";
        print $this->trail;
        print "</div>\n";
      }
      if ($this->origin) {
        print "<div id='wikiOrigin'>\n";
        print $this->origin;
        print "</div>\n";
      }
    }
    print "<div id='wikiBody'>\n";
    $this->pagelinks=$saved_pagelinks;
  }

  function set_origin($pagename) {
    global $DBInfo;

    $orig='';
    if ($pagename != $DBInfo->frontpage) {
      # save setting
      $sister_save=$this->sister_on;
      $this->sister_on=0;

      $parent=_($DBInfo->home);
      $text='';
      if ($this->group) {
        $group=strtok($pagename,'~');
        $text=strtok('');
        #$pagename=$group.'.'.$text;
        #$pagename='[wiki:'.$pagename.' '.$text.']';
        $main=strtok($text,'/');
      }
      if ($group)
        # represent: Main     > MoniWiki    > WikiName
        # real link: MoniWiki > Ko~MoniWiki > Ko~MoniWiki/WikiName
        $origin=$this->word_repl('"'.$main.'"',_("Main"),'',1,0);
      else
        # represent: Home       > WikiName > SubPage
        # real link: $frontpage > WikiName > WikiName/SubPage
        $origin=$this->word_repl('"'.$DBInfo->frontpage.'"',$parent,'',1,0);
      $parent='';

      $text=strtok($text,'/');
      $key=strtok($pagename,'/');
      while($key !== false) {
        if ($parent) $parent.='/'.$key;
        else {
          $parent.=$key;
          $key=$text;
        }
        $okey=$key;
        $key=strtok('/');
        if ($key)
          $origin.=$DBInfo->arrow.$this->word_repl('"'.$parent.'"',$okey,'',1,0);
        else
          $origin.=$DBInfo->arrow.$this->word_repl('"'.$parent.'"',$okey,'',1,0);
      }
      # reset pagelinks
      $this->pagelinks=array();
      $this->sister_on=$sister_save;
    } else {
      $origin=$DBInfo->home;
    }
    $this->origin=$origin;
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
      $this->trail.=$this->word_repl('"'.$page.'"','','',1,0).$DBInfo->arrow;
    }
    $this->trail.= ' '.htmlspecialchars($pagename);
    $this->pagelinks=array(); # reset pagelinks
    $this->sister_on=$sister_save;

    if (!in_array($pagename,$trails)) $trails[]=$pagename;

    $idx=count($trails) - $size;
    if ($idx > 0) $trails=array_slice($trails,$idx);
    $trail=join("\t",$trails);

    setcookie('MONI_TRAIL',$trail,time()+60*60*24*30,get_scriptname());
  }
} # end-of-Formatter

# setup the locale like as the phpwiki style
function get_locales($mode=1) {
  $languages=array(
    'en'=>array('en_US','english',''),
    'fr'=>array('fr_FR','france',''),
    'ko'=>array('ko_KR','korean',''),
  );
  $lang= $_SERVER['HTTP_ACCEPT_LANGUAGE'];
  $langs=explode(',',preg_replace(array("/;[^;,]+/","/\-[a-z]+/"),'',$lang));
  if ($languages[$langs[0]]) return array($languages[$langs[0]][0]);
  return array($languages[0][0]);
}

function set_locale($lang,$charset='') {
  $supported=array(
    'en_US'=>array('ISO-8859-1'),
    'fr_FR'=>array('ISO-8859-1'),
    'ko_KR'=>array('EUC-KR','UHC'),
  );
  if ($lang == 'auto') {
    # get broswer's settings
    $langs=get_locales();
    $lang= $langs[0];

    $charset= strtoupper($charset);
    # XXX
    if (function_exists('nl_langinfo'))
      $server_charset= nl_langinfo(CODESET);

    if ($charset == 'UTF-8') {
      if ($charset != $server_charset) $lang.=".".$charset;
    } else {
      if ($supported[$lang] && in_array($charset,$supported[$lang])) {
        return $lang.'.'.$charset;
      } else {
        return 'en_US'; // default
      }
    }
  }
  return $lang;
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
    $pagename=_stripslashes($pagename);
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

$DBInfo= new WikiDB($Config);
register_shutdown_function(array(&$DBInfo,'Close'));

$user=new User();
$options=array();
$options['id']=$user->id;

# MoniWiki theme
if (empty($DBInfo->theme) and isset($_GET['theme'])) $theme=$_GET['theme'];
else $theme=$DBInfo->theme;
if ($theme) $options['theme']=$theme;

if ($DBInfo->trail) {
  $options['trail']=$user->trail;
}
if ($options['id'] != 'Anonymous') {
  $udb=new UserDB($DBInfo);
  $userinfo=$udb->getUser($user->id);

  # Does it have valid ticket ?
  if ($user->ticket == $userinfo->info['ticket']) {
    $user=$userinfo;
    $options['css_url']=$user->info['css_url'];
    $options['quicklinks']=$user->info['quicklinks'];
    if (!$theme) $options['theme']=$user->info['theme'];
  } else {
    $options['id']='Anonymous';
    $options['css_url']=$user->css;
    if (!$theme) $options['theme']=$user->theme;
  }
} else {
  $options['css_url']=$user->css;
  if (!$theme) $options['theme']=$user->theme;
}

if ($DBInfo->theme and $DBInfo->theme_css)
  $options['css_url']=$DBInfo->url_prefix."/theme/$theme/css/default.css";

$options['timer']=&$timing;
$options['timer']->Check("load");

$lang= set_locale($DBInfo->lang,$DBInfo->charset);
$DBInfo->lang=$lang;

if (isset($locale)) {
  if (!@include_once('locale/'.$lang.'/LC_MESSAGES/moniwiki.php'))
    @include_once('locale/'.substr($lang,0,2).'/LC_MESSAGES/moniwiki.php');
} else if (substr($lang,0,2) == 'en') {
  $test=setlocale(LC_ALL, $lang);
} else {
  if ($DBInfo->include_path) $dirs=explode(':',$DBInfo->include_path);
  else $dirs=array('.');

  $test=setlocale(LC_ALL, $lang);
  foreach ($dirs as $dir) {
    $ldir=$dir.'/locale';
    if (is_dir($ldir)) {
      bindtextdomain('moniwiki', $ldir);
      textdomain("moniwiki");
      break;
    }
  }
}

$pagename=get_pagename();
//function render($pagename,$options) {
if ($pagename) {
  global $DBInfo;
  global $value,$action;
  # get primary variables
  if ($_SERVER['REQUEST_METHOD']=="POST") {
    # hack for TWiki plugin
    if ($_FILES['filepath']['name']) $action='draw';
    if ($GLOBALS['HTTP_RAW_POST_DATA']) {
      # RAW posted data. the $value and $action could be accessed under
      # "register_globals = On" in the php.ini
      $options['value']=$value;
    } else {
      $value=$_POST['value'];
      $action=$_POST['action'] ? $_POST['action']:$action;
    }
    $goto=$_POST['goto'];
  } else if ($_SERVER['REQUEST_METHOD']=="GET") {
    $action=$_GET['action'];
    $value=$_GET['value'];
    $goto=$_GET['goto'];
    $rev=$_GET['rev'];
    $refresh=$_GET['refresh'];
  }


  #print $_SERVER['REQUEST_URI'];
  $options['page']=$pagename;

#  if ($action=="recall" || $action=="raw" && $rev) {
#    $options['rev']=$rev;
#    $page = $DBInfo->getPage($pagename,$options);
#  } else
  $page = $DBInfo->getPage($pagename);

  $formatter = new Formatter($page,$options);
  $formatter->refresh=$refresh;

  // check black list
  if (!empty($DBInfo->blacklist)) {
    include_once 'lib/checkip.php';
    if (check_ip($DBInfo->blacklist, $_SERVER['REMOTE_ADDR'])) {
      $options['title']=_("You are in the black list");
      $options['msg']=_("Please contact WikiMasters");
      do_invalid($formatter,$options);
      return;
    }
  }
  if (!empty($DBInfo->kiwirian)) {
    if (!is_array($DBInfo->kiwirian)) {
      $DBInfo->kiwirian=explode(':',$DBInfo->kiwirian);
    }
    if (in_array($options['id'],$DBInfo->kiwirian)) {
      $options['title']=_("You are blocked in this wiki");
      $options['msg']=_("Please contact WikiMasters");
      do_invalid($formatter,$options);
      return;
    }
  }

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
      if ($DBInfo->auto_search) {
        $npage=str_replace(' ','',$page->name);
        if ($DBInfo->hasPage($npage)) {
          $options['value']=$npage;
          do_goto($formatter,$options);
          return;
        }
        $options['value']=$page->name;
        $options['check']=1;
        if (do_titlesearch($formatter,$options))
          return;
      }

      $formatter->send_header("Status: 404 Not found",$options);

      $twins=$DBInfo->metadb->getTwinPages($page->name,1);
      if ($twins) {
        $formatter->send_title($page->name,"",$options);
        $twins=join("\n",$twins);
        $formatter->send_page(_("See TwinPages: ").$twins);
        echo "<br />".
          $formatter->link_to("?action=edit",$formatter->icon['create']._("Create this page"));
      } else {
        $formatter->send_title(sprintf("%s Not Found",$page->name),"",$options);
        $button= $formatter->link_to("?action=edit",$formatter->icon['create']._("Create this page"));
        print $button;
        print sprintf(_(" or click %s to fullsearch this page.\n"),$formatter->link_to("?action=fullsearch&amp;value=$options[page]",_("title")));
        print $formatter->macro_repl('LikePages',$page->name,$err);
        if ($err['extra'])
          print $err['extra'];

        print "<hr />\n$button";
        print _(" or alternativly, use one of these templates:\n");
        $options['linkto']="?action=edit&amp;template=";
        print macro_TitleSearch($formatter,$DBInfo->template_regex,$options);
        print _("To create your own templates, add a page with a 'Template' suffix.\n");
      }

      $args['editable']=1;
      $formatter->send_footer($args,$options);
      return;
    }
    # display this page

    # increase counter
    $DBInfo->counter->incCounter($pagename,$options);

    if (!$action) $options['pi']=1; # protect a recursivly called #redirect

#    if (!$DBInfo->security->is_allowed('read',$options)) {
#      do_invalid($formatter,$options);
#      return;
#    }

    #$formatter->get_redirect();
    $formatter->pi=$formatter->get_instructions($dum);
    if ($DBInfo->body_attr)
      $options['attr']=$DBInfo->body_attr;
    $formatter->send_header("",$options);
    $formatter->send_title("","",$options);

    if ($formatter->pi['#title'] and $DBInfo->use_titlecache) {
      $tcache=new Cache_text('title');
      if (!$tcache->exists($pagename) or $_GET['update_title'])
        $tcache->update($pagename,$formatter->pi['#title']);
    }
    $formatter->write("<div id='wikiContent'>\n");
    $options['timer']->Check("init");
    $options['pagelinks']=1;
#    $cache=new Cache_text('pages');
#    if ($cache->exists($pagename)) {
#      print $cache->fetch($pagename);
#    } else {
#      ob_start();
      $formatter->send_page('',$options);
      if ($DBInfo->use_referer)
        log_referer($_SERVER['HTTP_REFERER'],$pagename);
      flush();
#      ob_end_flush();
#      ob_end_clean();
#      $out=ob_get_contents();
#      ob_end_clean();
#      print $out;
#      $cache->update($pagename,$out);
#    }
    $options['timer']->Check("send_page");
    $formatter->write("</div>\n");

    if ($DBInfo->extra_macros) {
      if (!is_array($DBInfo->extra_macros)) {
        print '<div id="wikiExtra">'."\n";
        print $formatter->macro_repl($DBInfo->extra_macros);
        print '</div>'."\n";
      } else {
        print '<div id="wikiExtra">'."\n";
        foreach ($DBInfo->extra_macros as $macro)
          print $formatter->macro_repl($macro);
        print '</div>'."\n";
      }
    }
    
    $args['editable']=1;
    $formatter->send_footer($args,$options);
    return;
  }

  if ($action) {
    $options['metatags']='<meta name="robots" content="noindex,nofollow" />';

    if (!$DBInfo->security->is_allowed($action,$options)) {
      $msg=sprintf(_("You are not allowed to '%s'"),$action);
      $formatter->send_header("Status: 406 Not Acceptable",$options);
      $formatter->send_title($msg,"", $options);
      if ($options['err'])
        $formatter->send_page($options['err']);
      $formatter->send_footer($args,$options);
      return;
    } else if ($_SERVER['REQUEST_METHOD']=="POST" and
      $DBInfo->security->is_protected($action,$options) and
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
// vim:et:sts=2:
?>
