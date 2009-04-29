<?php
// Copyright 2003-2007 Won-Kyu Park <wkpark at kldp.org> all rights reserved.
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
$_release = '1.1.4-CVS';

#ob_start("ob_gzhandler");

error_reporting(E_ALL ^ E_NOTICE);
#error_reporting(E_ALL);

$timing=new Timer();

function getPlugin($pluginname) {
  static $plugins=array();
  if (is_bool($pluginname) and $pluginname)
    return sizeof($plugins);
  $pname = strtolower($pluginname);
  if (!empty($plugins)) return isset($plugins[$pname]) ? $plugins[$pname]:'';
  global $DBInfo;

  $cp=new Cache_text('settings');
  if (empty($DBInfo->manual_plugin_admin)) {
    if (!empty($DBInfo->include_path))
      $dirs=explode(':',$DBInfo->include_path);
    else
      $dirs=array('.');
    $updated=false;
    $mt=$cp->mtime('plugins');
    foreach ($dirs as $d) {
      if (is_dir($d.'/plugin/')) {
        $ct=filemtime($d.'/plugin/.'); // XXX mtime fix
        $updated=$ct > $mt ? true:$updated;
      }
    }
    if ($updated) {
      $cp->remove('plugins');
      $cp->remove('processors');
    }
  }

  if ($cp->exists('plugins')) {
    $plugins=unserialize($cp->fetch('plugins'));
    if (!empty($DBInfo->myplugins) and is_array($DBInfo->myplugins))
      $plugins=array_merge($plugins,$DBInfo->myplugins);
    return isset($plugins[$pname]) ? $plugins[$pname]:'';
  }
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

  if (!empty($plugins))
    $cp->update('plugins',serialize($plugins));
  if (is_array($DBInfo->myplugins))
    $plugins=array_merge($plugins,$DBInfo->myplugins);

  return $plugins[$pname];
}

function getModule($module,$name) {
  $mod=$module.'_'.$name;
  if (!class_exists($mod))
    include_once('lib/'.strtolower($module).'.'.$name.'.php');
  return $mod;
}

function getProcessor($pro_name) {
  static $processors=array();
  if (is_bool($pro_name) and $pro_name)
    return sizeof($processors);
  $prog = strtolower($pro_name);
  if (!empty($processors)) return isset($processors[$prog]) ? $processors[$prog]:'';
  global $DBInfo;

  $cp=new Cache_text('settings');

  if ($cp->exists('processors')) {
    $processors=unserialize($cp->fetch('processors'));
    if (is_array($DBInfo->myprocessors))
      $processors=array_merge($processors,$DBInfo->myprocessors);
    return $processors[$prog];
  }
  if (!empty($DBInfo->include_path))
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

  if ($processors)
    $cp->update('processors',serialize($processors));
  if (is_array($DBInfo->myprocessors))
    $processors=array_merge($processors,$DBInfo->myprocessors);

  return $processors[strtolower($pro_name)];
}

function getFilter($filtername) {
  static $filters=array();
  if ($filters) return $filters[strtolower($filtername)];
  global $DBInfo;
  if (!empty($DBInfo->include_path))
    $dirs=explode(':',$DBInfo->include_path);
  else
    $dirs=array('.');

  foreach ($dirs as $dir) {
    $handle= @opendir($dir.'/plugin/filter');
    if (!$handle) continue;
    while ($file= readdir($handle)) {
      if (is_dir($dir."/plugin/filter/$file")) continue;
      $name= substr($file,0,-4);
      $filters[strtolower($name)]= $name;
    }
  }

  if (!empty($DBInfo->myfilters) and is_array($DBInfo->myfilters))
    $filters=array_merge($filters,$DBInfo->myfilters);

  return $filters[strtolower($filtername)];
}

if (!function_exists ('bindtextdomain')) {
  $_locale = array();

  function gettext ($text) {
    global $_locale,$locale;
    if (sizeof($_locale) == 0) $_locale=&$locale;
    if (!empty ($_locale[$text]))
      return $_locale[$text];
    return $text;
  }

  function _ ($text) {
    return gettext($text);
  }
}

function _t ($text) {
  return gettext($text);
}

function goto_form($action,$type="",$form="") {
  if ($type==1) {
    return "
<form id='go' method='get' action='$action'>
<div>
<span title='TitleSearch'>
<input type='radio' name='action' value='titlesearch' />
Title</span>
<span title='FullSearch'>
<input type='radio' name='action' value='fullsearch' />
Contents</span>&nbsp;
<input type='text' name='value' class='goto' accesskey='s' size='20' />
<input type='submit' name='status' value='Go' style='width:23px' />
</div>
</form>
";
  } else if ($type==2) {
    return "
<form id='go' method='get' action='$action'>
<div>
<select name='action' style='width:60px'>
<option value='goto'>goto</option>
<option value='titlesearch'>TitleSearch</option>
<option value='fullsearch'>FullSearch</option>
</select>
<input type='text' name='value' class='goto' accesskey='s' size='20' />
<input type='submit' name='status' value='Go' />
</div>
</form>
";
  } else if ($type==3) {
    return "
<form id='go' method='get' action='$action'>
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
<form id='go' method='get' action='$action' onsubmit="moin_submit(this);">
<div>
<input type='text' name='value' size='20' accesskey='s' class='goto' style='width:100px' />
<input type='hidden' name='action' value='goto' />
<input type='submit' name='status' value='Go' style='width:23px;' />
</div>
</form>
FORM;
  }
}

function kbd_handler() {
  global $Config;

  if (!$Config['kbd_script']) return '';
  $prefix=get_scriptname();
  $sep= $Config['query_prefix'];
  print <<<EOS
<script type="text/javascript">
/*<![CDATA[*/
url_prefix="$prefix";
_qp="$sep";
FrontPage= "$Config[frontpage]";
/*]]>*/
</script>
<script type="text/javascript" src="$Config[kbd_script]"></script>\n
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
    $out= '';
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
  var $aux=array();

  function MetaDB_dba($file,$type="db3") {
    if (function_exists('dba_open'))
      $this->metadb=@dba_open($file.".cache","r",$type);
  }

  function close() {
    dba_close($this->metadb);
  }

  function attachDB($db) {
    $this->aux=$db;
  }

  function getSisterSites($pagename,$mode=1) {
    if (!$this->aux->hasPage($pagename) and !dba_exists($pagename,$this->metadb)) {
      if ($mode) return '';
      return false;
    }
    if (!$mode) return true;
    $sisters=dba_fetch($pagename,$this->metadb);
    $addons=$this->aux->getSisterSites($pagename,$mode);

    if ($sisters)
      $ret='[wiki:'.str_replace(' ',":$pagename] [wiki:",$sisters).":$pagename]";
    $pagename=_preg_search_escape($pagename);
    if ($addons) $ret=rtrim($addons.' '.$ret);

    if ($mode==1 and strlen($ret) > 80) return "[wiki:TwinPages:$pagename]";
    return preg_replace("/((:[^\s]+){2})(\:$pagename)/","\\1",$ret);
  }

  function getTwinPages($pagename,$mode=1) {
    if (!$this->aux->hasPage($pagename) and !dba_exists($pagename,$this->metadb)) {
      if ($mode) return array();
      return false;
    }
    if (!$mode) return true;

    $twins=dba_fetch($pagename,$this->metadb);
    $addons=$this->aux->getTwinPages($pagename,$mode);
    $ret=array();
    if ($twins) {
      $ret="[wiki:".str_replace(" ",":$pagename] [wiki:",$twins). ":$pagename]";

      $pagename=_preg_search_escape($pagename);
      $ret= preg_replace("/((:[^\s]+){2})(\:$pagename)/","\\1",$ret);
      $ret= explode(' ',$ret);
    }

    if ($addons) $ret=array_merge($addons,$ret);
    if (sizeof($ret) > 8) {
      if ($mode==1) return array("TwinPages:$pagename");
      $ret=array_map(create_function('$a','return " * $a";'),$ret);
    }

    return $ret;
  }

  function hasPage($pagename) {
    if (dba_exists($pagename,$this->metadb)) return true;
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
  function getSisterSites($pagename,$mode=1) {
    if ($mode) return '';
    return false;
  }

  function getTwinPages($pagename,$mode=1) {
    if ($mode) return array();
    return false;
  }

  function hasPage($pgname) {
    return false;
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

class MetaDB_text extends MetaDB {
  var $db=array();
  function MetaDB_text($file) {
    $lines=file($file);
    if (!empty($lines))
    foreach ($lines as $line) {
      $line=trim($line);
      if ($line[0]=='#' or !$line) continue;
      # support three types of aliases
      #
      # dest<alias1,alias2,...
      # dest,alias1,alias2,...
      # alias>dest1,dest2,dest3,...
      #
      if (($p=strpos($line,'>')) !== false) {
        list($key,$list)=explode('>',$line,2);
        $this->db[$key]=$list;
      } else {
        if (($p=strpos($line,'<')) !== false) {
          list($val,$keys)=explode('<',$line,2);
          $keys=explode(',',$keys);
        } else {
          $keys=explode(',',$line);
          $val=array_shift($keys);
        }

        foreach ($keys as $k) {
          $this->db[$k]=$this->db[$k] ? $this->db[$k].','.$val:$val;
        }
      }
    }
  }

  function hasPage($pagename) {
    if (isset($this->db[$pagename])) return true;
    return false;
  }

  function getTwinPages($pagename,$mode=1) {
    if (empty($this->db[$pagename])) {
      if (!empty($mode)) return array();
      return false;
    }
    if (empty($mode)) return true;
    $twins=$this->db[$pagename];

    $ret='[wiki:'.str_replace(',',"] [wiki:",$twins).']';

    $pagename=_preg_search_escape($pagename);
    $ret= preg_replace("/((:[^\s]+){2})(\:$pagename)/","\\1",$ret);
    return explode(' ',$ret);
  }
  function getSisterSites($pagename,$mode=1) {
    if (empty($this->db[$pagename])) {
      if ($mode) return '';
      return false;
    }
    if (empty($mode)) return true;

    $twins=$this->db[$pagename];
    $ret='[wiki:'.str_replace(',',"] [wiki:",$twins).']';

    return $ret;
  }

  function getAllPages() {
    return array_keys($this->db);
  }
}

class Counter_dba {
  var $counter;
  var $DB;
  function Counter_dba($DB,$dbname='counter') {
    if (!function_exists('dba_open')) return;
    if (!file_exists($DB->data_dir.'/'.$dbname.'.db'))
      $this->counter=dba_open($DB->data_dir.'/'.$dbname.'.db',"n",$DB->dba_type);
    else
      $this->counter=@dba_open($DB->data_dir.'/'.$dbname.'.db',"w",$DB->dba_type);
    $this->DB=&$DB;
  }

  function incCounter($pagename,$options="") {
    if ($this->DB->owners and in_array($options['id'],$this->DB->owners))
      return;
    $count=dba_fetch($pagename,$this->counter);
    if (!$count) $count=0;
    $count++;
    dba_replace($pagename,$count,$this->counter);
    return $count;
  }

  function pageCounter($pagename) {
    $count = dba_fetch($pagename,$this->counter);
    return $count ? $count: 0;
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
      "deletepage","deletefile","rename","rcspurge","rcs","chmod","backup","restore","rcsimport","revert","userinfo");
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
      if (is_string($options['init'])) $script .= '?init='.$options['init'];
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
  function WikiDB(&$config) {
    # Default Configuations
    $this->frontpage='FrontPage';
    $this->sitename='UnnamedWiki';
    $this->upload_dir= 'pds';
    $this->data_dir= './data';
    $this->query_prefix='/';
    $this->umask= 0770;
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
    $this->logo_string= '<img src="'.$this->logo_img.'" alt="[logo]" style="vertical-align:middle;border:0px" />';
    $this->metatags='<meta name="robots" content="noindex,nofollow" />';
    $this->doctype=<<<EOS
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
EOS;
    $this->use_smileys=1;
    $this->hr="<hr class='wikiHr' />";
    $this->date_fmt= 'Y-m-d';
    $this->date_fmt_rc= 'D d M Y';
    $this->date_fmt_blog= 'M d, Y';
    $this->datetime_fmt= 'Y-m-d H:i:s';
    $this->default_markup= 'wiki';
    #$this->changed_time_fmt = ' . . . . [h:i a]';
    $this->changed_time_fmt= ' [h:i a]'; # used by RecentChanges macro
    $this->admin_passwd= 'daEPulu0FLGhk'; # default value moniwiki
    $this->purge_passwd= '';
    $this->rcs_user='root';
    $this->actions= array('DeletePage','LikePages');
    $this->show_hosts= TRUE;
    $this->iconset='moni';
    $this->css_friendly='0';
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
    $this->use_category=1;
    $this->use_camelcase=1;
    $this->use_sistersites=1;
    $this->use_singlebracket=1;
    $this->use_twinpages=1;
    $this->use_hostname=1;
    $this->email_guard='hex';
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
    $this->title_rule='((?<=[a-z0-9]|[B-Z]{2})([A-Z][a-z]))';
    $this->login_strict=1;

    # set user-specified configuration
    if ($config) {
      # read configurations
      foreach ($config as $key=>$val) {
        if ($key{0}=='_') continue; // internal variables
        $this->$key=$val;
      }
    }

    if (!$this->purge_passwd)
      $this->purge_passwd=$this->admin_passwd;

    if ($this->use_wikiwyg and !$this->sectionedit_attr)
      $this->sectionedit_attr=1;

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

    if (empty($this->upload_dir_url))
      $this->upload_dir_url= $this->upload_dir;

    $doc_root = getenv("DOCUMENT_ROOT"); // for Unix
    $imgs_realdir= $doc_root.$this->imgs_dir;
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

    $this->icon['upper']="<img src='$imgdir/${iconset}upper.$ext' alt='U' style='vertical-align:middle;border:0px' />";
    $this->icon['edit']="<img src='$imgdir/${iconset}edit.$ext' alt='E' style='vertical-align:middle;border:0px' />";
    $this->icon['diff']="<img src='$imgdir/${iconset}diff.$ext' alt='D' style='vertical-align:middle;border:0px' />";
    $this->icon['del']="<img src='$imgdir/${iconset}deleted.$ext' alt='(del)' style='vertical-align:middle;border:0px' />";
    $this->icon['info']="<img src='$imgdir/${iconset}info.$ext' alt='I' style='vertical-align:middle;border:0px' />";
    $this->icon['rss']="<img src='$imgdir/${iconset}rss.$ext' alt='RSS' style='vertical-align:middle;border:0px' />";
    $this->icon['show']="<img src='$imgdir/${iconset}show.$ext' alt='R' style='vertical-align:middle;border:0px' />";
    $this->icon['find']="<img src='$imgdir/${iconset}search.$ext' alt='S' style='vertical-align:middle;border:0px' />";
    $this->icon['help']="<img src='$imgdir/${iconset}help.$ext' alt='H' style='vertical-align:middle;border:0px' />";
    $this->icon['pref']="<img src='$imgdir/${iconset}pref.$ext' alt='C' style='vertical-align:middle;border:0px' />";
    $this->icon['www']="<img src='$imgdir/${iconset}www.$ext' alt='www' style='vertical-align:middle;border:0px' />";
    $this->icon['mailto']="<img src='$imgdir/${iconset}email.$ext' alt='M' style='vertical-align:middle;border:0px' />";
    $this->icon['create']="<img src='$imgdir/${iconset}create.$ext' alt='N' style='vertical-align:middle;border:0px' />";
    $this->icon['new']="<img src='$imgdir/${iconset}new.$ext' alt='U' style='vertical-align:middle;border:0px' />";
    $this->icon['updated']="<img src='$imgdir/${iconset}updated.$ext' alt='U' style='vertical-align:middle;border:0px' />";
    $this->icon['user']="UserPreferences";
    $this->icon['home']="<img src='$imgdir/${iconset}home.$ext' alt='M' style='vertical-align:middle;border:0px' />";
    $this->icon['main']="<img src='$imgdir/${iconset}main.$ext' class='icon' alt='^' style='vertical-align:middle;border:0px' />";
    $this->icon['print']="<img src='$imgdir/${iconset}print.$ext' alt='P' style='vertical-align:middle;border:0px' />";
    $this->icon['scrap']="<img src='$imgdir/${iconset}scrap.$ext' alt='S' style='vertical-align:middle;border:0px' />";
    $this->icon['unscrap']="<img src='$imgdir/${iconset}unscrap.$ext' alt='S' style='vertical-align:middle;border:0px' />";
    $this->icon['attach']="<img src='$imgdir/${iconset}attach.$ext' alt='@' style='vertical-align:middle;border:0px' />";
    $this->icon['external']="<img class='externalLink' src='$imgdir/${iconset}external.$ext' alt='[]' style='vertical-align:middle;border:0px' />";
    $this->icon_sep=" ";
    $this->icon_bra=" ";
    $this->icon_cat=" ";
    }

    if (empty($this->icons)) {
      $this->icons=array(
              'edit'=>array("","?action=edit",$this->icon['edit'],"accesskey='e'"),
              'diff'=>array("","?action=diff",$this->icon['diff'],"accesskey='c'"),
              'show'=>array("","",$this->icon['show']),
              'find'=>array("FindPage","",$this->icon['find']),
              'info'=>array("","?action=info",$this->icon['info']));
      if ($this->notify)
        $this->icons['subscribe']=array("","?action=subscribe",$this->icon['mailto']);
      $this->icons['help']=array("HelpContents","",$this->icon['help']);
      $this->icons['pref']=array("UserPreferences","",$this->icon['pref']);
    }
    $config=get_object_vars($this); // merge default settings to $config

    # load smileys
    if ($this->use_smileys){
      include_once($this->smiley.".php");
      # set smileys rule
      if ($this->shared_smileymap and file_exists($this->shared_smileymap)) {
        $myicons=array();
        $lines=file($this->shared_smileymap);
        foreach ($lines as $l) {
          if ($l[0] != ' ') continue;
          if (!preg_match('/^ \*\s*([^ ]+)\s(.*)$/',$l,$m)) continue;
          $name=_preg_escape($m[1]);
          list($img,$extra)=explode(' ',$m[2]);
          if (preg_match('/^(http|ftp):.*\.(png|jpg|jpeg|gif)/',$img)) {
            $myicons[$name]=array(16,16,0,$img);
          } else {
            continue;
          }
        }
        #print_r($myicons);
        $smileys=array_merge($smileys,$myicons);
      }

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

    $this->interwiki=null;

    if (!empty($this->use_alias) and file_exists($this->aliaspage))
      $this->alias=new MetaDB_text($this->aliaspage);
    else
      $this->alias=new MetaDB();

    if ($this->shared_metadb)
      $this->metadb=new MetaDB_dba($this->shared_metadb,$this->dba_type);
    if (!$this->metadb->metadb) {
      if ($this->alias) $this->metadb=$this->alias;
      else $this->metadb=new MetaDB();
    } else {
      $this->metadb->attachDB($this->alias);
    }

    if ($this->use_counter)
      $this->counter=new Counter_dba($this);
    if (!$this->counter->counter)
      $this->counter=new Counter();

    if (!empty($this->security_class)) {
      include_once("plugin/security/$this->security_class.php");
      $class="Security_".$this->security_class;
      $this->security=new $class ($this);
    } else
      $this->security=new Security($this);
    if ($this->filters) {
      if (!is_array($this->filters)) {
        $this->filters=preg_split('/(\||,)/',$this->filters);
      }
    }
    if ($this->postfilters) {
      if (!is_array($this->postfilters)) {
        $this->postfilters=preg_split('/(\||,)/',$this->postfilters);
      }
    }

    # check and prepare $url_mappings
    if ($this->url_mappings) {
      if (!is_array($this->url_mappings)) {
        $maps=explode("\n",$this->url_mappings);
        $tmap=array();
        $rule='';
        foreach ($maps as $map) {
          if (strpos($map,' ')) {
            $key=strtok($map,' ');
            $val=strtok('');
            $tmap["$key"]=$val;
            $rule.=preg_quote($key,'/').'|';
          }
        }
        $this->url_mappings=$tmap;
        $this->url_mapping_rule=substr($rule,0,-1);
      }
    }
  }

  function Close() {
    $this->metadb->close();
    $this->counter->close();
  }

  function _getPageKey($pagename) {
    # normalize a pagename to uniq key

    # moinmoin style internal encoding
    #$name=rawurlencode($pagename);
    #$name=strtr($name,"%","_");
    #$name=preg_replace("/%([a-f0-9]{2})/ie","'_'.strtolower('\\1')",$name);
    #$name=preg_replace(".","_2e",$name);

    // clean up ':' like as the dokuwiki
    $pn= preg_replace('#:+#',':',$pagename);
    $pn= trim($pn,':-');
    $pn= preg_replace('#:[:\._\-]+#',':',$pn);

    $pn= preg_replace("/([^a-z0-9:]{1})/ie","'_'.strtolower(dechex(ord(substr('\\1',-1))))",$pn);
    $name=preg_replace('#:#','.d/',$pn);
    #$name=preg_replace("/([^a-z0-9]{1})/ie","'_'.strtolower(dechex(ord('\\1')))",$pagename);
    return $name;
  }

  function getPageKey($pagename) {
    #$name=preg_replace("/([^a-z0-9]{1})/ie","'_'.strtolower(dechex(ord('\\1')))",$pagename);
    $name=$this->_getPageKey($pagename);
    #$name=preg_replace("/([^a-z0-9]{1})/ie","'_'.strtolower(dechex(ord(substr('\\1',-1))))",$pagename);
    return $this->text_dir . '/' . $name;
  }

  function pageToKeyname($pagename) {
    return $this->_getPageKey($pagename);
    #return preg_replace("/([^a-z0-9]{1})/ie","'_'.strtolower(dechex(ord(substr('\\1',-1))))",$pagename);
    #return preg_replace("/([^a-z0-9]{1})/ie","'_'.strtolower(dechex(ord('\\1')))",$pagename);
  }

  function hasPage($pagename) {
    if (!$pagename) return false;
    $name=$this->getPageKey($pagename);
    return @file_exists($name);
  }

  function getPage($pagename,$options="") {
    return new WikiPage($pagename,$options);
  }

  function keyToPagename($key) {
  #  return preg_replace("/_([a-f0-9]{2})/e","chr(hexdec('\\1'))",$key);
  #  $pagename=preg_replace("/_([a-f0-9]{2})/","%\\1",$key);
  #  $pagename=str_replace("_","%",$key);

    $pagename=preg_replace('%\.d/%',':',$key);

    $pagename=strtr($pagename,'_','%');
    return rawurldecode($pagename);
  }

  function getPageLists($options=array()) {
    $pages = array();

    $pcid=md5(serialize($options));
    $pc=new Cache_text('pagelist');
    if (filemtime($this->text_dir) < $pc->mtime($pcid) and $pc->exists($pcid)) {
      $list=unserialize($pc->fetch($pcid));
      if (is_array($list)) return $list;
    }

    $handle = opendir($this->text_dir);

    if (!$options) {
      while ($file = readdir($handle)) {
        if (is_dir($this->text_dir."/".$file)) continue;
        $pages[] = $this->keyToPagename($file);
      }
      closedir($handle);
      $pc->update($pcid,serialize($pages));

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
    $pc->update($pcid,serialize($pages));
    return $pages;
  }

  function getLikePages($needle,$count=100,$opts='') {
    $pages= array();

    if (!$needle) return false;
    $all= $this->getPageLists();

    $m = @preg_match("/$needle/".$opts,'dummy');
    if ($m===false) return array(); 
    foreach ($all as $page) {
      if (preg_match("/$needle/".$opts,$page)) {
        $pages[] = $page; $count--;
      }
      if ($count < 0) break;
    }
    return $pages;
  }

  function getCounter() {
    return sizeof($this->getPageLists());
  }

  function addLogEntry($page_name, $remote_name,$comment,$action="SAVE") {
    $user=&$this->user;
  
    $myid=$user->id;
    if ($user->info['nick']) {
      $myid.=' '.$user->info['nick'];
      $options['nick']=$user->info['nick'];
    }
    $comment=strtr($comment,"\t"," ");
    $fp_editlog = fopen($this->editlog_name, 'a+');
    $time= time();
    if ($this->use_hostname) $host= gethostbyaddr($remote_name);
    else $host= $remote_name;
    $page_name=trim($page_name);
    $msg="$page_name\t$remote_name\t$time\t$host\t$myid\t$comment\t$action\n";
    fwrite($fp_editlog, $msg);
    fclose($fp_editlog);
  }

  function editlog_raw_lines($days,$opts=array()) {
    $lines=array();

    $time_current= time();
    $secs_per_day= 24*60*60;

    if ($opts['ago']) {
      $date_from= $time_current - ($opts['ago'] * $secs_per_day);
      $date_to= $date_from + ($days * $secs_per_day);
    } else {
      if ($opts['items']) {
        $date_from= $time_current - (365 * $secs_per_day);
      } else {
        $date_from= $time_current - ($days * $secs_per_day);
      }
      $date_to= $time_current;
    }
    $check=$date_to;

    $itemnum=$opts['items'] ? $opts['items']:200;

    $fp= fopen($this->editlog_name, 'r');
    while (is_resource($fp) and ($fz=filesize($this->editlog_name))>0){
      fseek($fp,0,SEEK_END);
      if ($fz <= 1024) {
        fseek($fp,0);
        $ll=rtrim(fread($fp,1024));
        $lines=array_reverse(explode("\n",$ll));
        break;   
      }
      $a=-1; // hack, don't read last \n char.
      $last='';
      fseek($fp,0,SEEK_END);
      while($date_from < $check and !feof($fp)){
        $rlen=$fz + $a;
        if ($rlen > 1024) { $rlen=1024;}
        else if ($rlen <= 0) break;
        $a-=$rlen;
        fseek($fp,$a,SEEK_END);
        $l=fread($fp,$rlen);
        if ($rlen != 1024) $l="\n".$l; // hack, for the first log entry.
        while(($p=strrpos($l,"\n"))!==false) {
          $line=substr($l,$p+1).$last;
          $last='';
          $l=substr($l,0,$p);
          $dumm=explode("\t",$line,4);
          $check=$dumm[2];
          if ($date_from>$check) break;
          if ($date_to>$check) {
            $lines[]=$line;
            $pages[$dumm[0]]=1;
            if (sizeof($pages) >= $itemnum) { $check=0; break; }
          }
          $last='';
        }
        $last=$l.$last;
      }
      #print $a;
      #print sizeof($lines);
      #print_r($lines);
      fclose($fp);
      break;   
    }

    if ($opts['quick']) {
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

    if ($options['id'] == 'Anonymous') {
      $id=$options['name'] ?
        _stripslashes($options['name']):$_SERVER['REMOTE_ADDR'];
    } else {
      $id=$options['nick'] ? $options['nick']:$options['id'];
      if (!preg_match('/([A-Z][a-z0-9]+){2,}/',$id)) $id='['.$id.']';
    }
 
    $body=preg_replace("/@DATE@/","[[Date($time)]]",$body);
    $body=preg_replace("/@USERNAME@/","$id",$body);
    $body=preg_replace("/@TIME@/","[[DateTime($time)]]",$body);
    $body=preg_replace("/@SIG@/","-- $id [[DateTime($time)]]",$body);
    $body=preg_replace("/@PAGE@/",$options['page'],$body);
    $body=preg_replace("/@date@/","$time",$body);

    return $body;
  }

  function _savePage($filename,$body,$options=array()) {
    $dir=dirname($filename);
    if (!is_dir($dir)) {
      $om=umask(~$this->umask);
      _mkdir_p($dir, 0777);
      umask($om);
    }

    $fp=fopen($filename,"w");
    if (!is_resource($fp))
       return -1;

    flock($fp,LOCK_EX);
    fwrite($fp, $body);
    flock($fp,LOCK_UN);
    fclose($fp);

    if ($this->version_class) {
      $class=getModule('Version',$this->version_class);
      $version=new $class ($this);
      $om=umask(~$this->umask);
      $ret=$version->_ci($filename,$options['log']);
      chmod($filename,0666 & $this->umask);
      umask($om);
    }
    return 0;
  }

  function savePage(&$page,$comment="",$options=array()) {
    $user=&$this->user;
    if ($user->id == 'Anonymous')
      if (strlen($comment)>80) $comment=''; // restrict comment length for anon.

    $REMOTE_ADDR=$_SERVER['REMOTE_ADDR'];
    $comment=escapeshellcmd($comment);

    $myid=$user->id;
    if ($user->info['nick']) {
      $myid.=' '.$user->info['nick'];
      $options['nick']=$user->info['nick'];
    }
    $options['myid']=$myid;

    $keyname=$this->_getPageKey($page->name);
    $key=$this->text_dir."/$keyname";

    $body=$this->_replace_variables($page->body,$options);
    $log=$REMOTE_ADDR.';;'.$myid.';;'.$comment;
    $options['log']=$log;
    $options['pagename']=$page->name;
    $ret=$this->_savePage($key,$body,$options);
    if ($ret == -1) return -1;
    #
    $page->write($body);

    # check minor edits XXX
    $minor=0;
    if ($this->use_minorcheck or $options['minorcheck']) {
      $info=$page->get_info();
      if ($info[1]) {
        eval('$check='.$info[1].';');
        if (abs($check) < 3) $minor=1;
      }
    }
    if (!$options['minor'] and !$minor)
      $this->addLogEntry($keyname, $REMOTE_ADDR,$comment,"SAVE");
    return 0;
  }

  function deletePage($page,$options='') {
    $REMOTE_ADDR=$_SERVER['REMOTE_ADDR'];

    $comment=$options['comment'];
    $user=&$this->user;

    $keyname=$this->_getPageKey($page->name);

    if ($this->version_class) {
      $class=getModule('Version',$this->version_class);
      $version=new $class ($this);
      $log=$REMOTE_ADDR.';;'.$user->id.';;'.$comment;
      $ret=$version->ci($page->name,$log);
      if ($options['history'])
        $version->delete($page->name);
    }
    $delete=@unlink($this->text_dir."/$keyname");
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
    $okeyname=$this->_getPageKey($pagename);
    $keyname=$this->_getPageKey($new);

    rename($okey,$nkey);
    $newdir=$this->upload_dir.'/'.$keyname;
    $olddir=$this->upload_dir.'/'.$this->_getPageKey($pagename);
    if (!file_exists($newdir) and file_exists($olddir))
      rename($olddir,$newdir);

    if ($options['history'] && $this->version_class) {
      $class=getModule('Version',$this->version_class);
      $version=new $class ($this);
      $version->rename($pagename,$new);
    }

    $comment=sprintf(_("Rename %s to %s"),$pagename,$new);
    $this->addLogEntry($okeyname, $REMOTE_ADDR,'',"SAVE");
    $this->addLogEntry($keyname, $REMOTE_ADDR,$comment,"SAVE");
  }

  function _isWritable($pagename) {
    $key=$this->getPageKey($pagename);
    $dir=dirname($key);
    # global lock
    if (@file_exists($dir.'/.lock')) return false;
    # True if page can be changed
    return @is_writable($key) or !@file_exists($key);
  }

  function getPerms($pagename) {
    $key=$this->getPageKey($pagename);
    if (file_exists($key))
       return fileperms($key);
    return 0666;
  }

  function setPerms($pagename,$perms) {
    $om=umask(0700);
    $key=$this->getPageKey($pagename);
    if (file_exists($key)) chmod($key,$perms);
    umask($om);
  }
}

class Version_RCS {
  var $DB;

  function Version_RCS($DB) {
    $this->DB=$DB;
    $this->NULL='';
    if(getenv("OS")!="Windows_NT") $this->NULL=' 2>/dev/null';
    if ($DB->rcs_error_log) $this->NULL='';
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
    if ($opt['stdout']) $ropt='-r';
    $fp=@popen("co -x,v/ -q $ropt$rev ".$filename.$this->NULL,"r");
    if ($opt['stdout']) {
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
    $rev = (is_numeric($rev) and $rev > 0) ? "-r$rev":'';
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
  function export($pagename) {
    $keyname=$this->DB->_getPageKey($pagename);
    $fname=$this->DB->text_dir."/RCS/$keyname,v";
    $fp=fopen($fname,'r');
    if (is_resource($fp)) {
      $sz=filesize($fname);
      $out=fread($fp,$sz);
      fclose($fp);
    }
    return $out;
  }

  function import($pagename,$rcsfile) {
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

class Cache_text {
  var $depth=0;
  var $ext='';
  var $arena='default';
  function Cache_text($arena,$depth=0,$ext='',$dir='') {
    global $Config;
    $this->depth=$depth;
    $this->arena=$arena;
    $this->ext=$ext ? '.'.$ext:'';
    $this->cache_dir=$dir ? $dir.'/'.$arena: $Config['cache_dir'].'/'.$arena;
  }

  function _getKey($pagename,$md5=1) {
    if ($this->depth>0) {
      $key=$md5 ? md5($pagename):$pagename;
      $prefix=substr($key,0,$this->depth);
      return $this->arena.'/'.$prefix.'/'.$key.$this->ext;
    }
    return $this->arena.'/'.
      preg_replace("/([^a-z0-9]{1})/ie","'_'.strtolower(dechex(ord('\\1')))",
      $pagename).$this->ext;
  }

  function getKey($pagename) {
    if ($this->depth>0) {
      $key=md5($pagename);
      $prefix=substr($key,0,$this->depth);
      $key=$prefix.'/'.$key.$this->ext;
      return $this->cache_dir . '/' . $key;
    }
    return $this->cache_dir .'/'.
      preg_replace("/([^a-z0-9]{1})/ie","'_'.strtolower(dechex(ord('\\1')))",
      $pagename).$this->ext;
  }

  function update($pagename,$val,$mtime="") {
    if (!$pagename) return false;
    $key=$this->getKey($pagename);
    if (file_exists($key) and !is_writable($key)) return false;
    if ($mtime and ($mtime <= $this->mtime($key))) return false;

    if (is_array($val))
      $val=join("\n",array_keys($val))."\n";
    else
      $val=str_replace("\r","",$val);
    $this->_save($key,$val);
    return true;
  }

  function _save($key,$val) {
    $dir=dirname($key);
    if (!is_dir($dir)) {
      $om=umask(000);
      _mkdir_p($dir, 0777);
      umask($om);
    }
    $fp=fopen($key,"w+");
    if ($fp) {
      flock($fp,LOCK_EX);
      fwrite($fp,$val);
      flock($fp,LOCK_UN);
      fclose($fp);
    }
  }

  function _del($key) {
    unlink($key);
  }

  function fetch($pagename,$mtime="",$params=array()) {
    $key=$this->getKey($pagename);
    if ($this->_exists($key)) {
       if (!$mtime) {
          return $this->_fetch($key,$params);
       }
       else if ($this->_mtime($key) > $mtime)
          return $this->_fetch($key,$params);
    }
    return false;
  }

  function exists($pagename) {
    $key=$this->getKey($pagename);
    return $this->_exists($key);
  }

  function _exists($key) {
    return @file_exists($key);
  }

  function _fetch($key,$params=array()) {
    $fp=fopen($key,"r");
    if (!is_resource($fp)) return '';

    $ret='';
    
    if (($size=filesize($key)) >0) {
      while (!empty($params['uniq']) and empty($params['raw'])) { # include cache if it is a valid php cache
        # cache header : <,?,php /* Generator Version uniqid tpl_path(optional) */
        $check=fgets($fp);
        if ($check{0}=='<' and $check{1}=='?') {
          list($tag,$sep,$generator,$ver,$id,$path,$extra)=explode(' ',$check);
          $ok=1;
          if (!empty($params['uniq']) and $params['uniq'] != $id) $ok=0;
          if (!empty($ok) and !empty($params['path']) and $params['path'] != $path) $ok=0;
          if (!empty($ok)) {
            fclose($fp);
            global $Config;
            $TPL_VAR=&$params['_vars']; # builtin Template_ support
            if (isset($TPL_VAR['_theme']) and is_array($TPL_VAR['_theme']) and $TPL_VAR['_theme']['compat'])
              extract($TPL_VAR['_theme']);
            $ehandle=false;
            if (!empty($params['formatter'])) {
              $formatter=&$params['formatter']; # XXX
              if (method_exists($formatter,'internal_errorhandler')) {
                set_error_handler(array($formatter,'internal_errorhandler'));
                $ehandle=true;
              }
            }
            if (!empty($params['print'])) {
              $ret= include $key; // Do we need more secure method ?
              if ($ehandle) restore_error_handler();
              return $ret;
            } else {
              ob_start();
              include $key;
              if ($ehandle) restore_error_handler();
              $fetch = ob_get_contents();
              ob_end_clean();
              return $fetch;
            }
          }
        }
        rewind($fp);
        break;
      }
      $ret=fread($fp,$size);
    }
    fclose($fp);
    if (!empty($params['print'])) return print $ret;
    return $ret;
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
    if (!empty($options['rev']))
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
    return @file_exists($this->filename);
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

    if ($this->body && empty($options['rev']))
       return $this->body;

    $rev= !empty($options['rev']) ? $options['rev']:(!empty($this->rev) ? $this->rev:'');
    if (!empty($rev)) {
      if (!empty($DBInfo->version_class)) {
        $class=getModule('Version',$DBInfo->version_class);
        $version=new $class ($DBInfo);
        $out = $version->co($this->name,$rev);
        return $out;
      } else {
        return _("Version info does not supported in this wiki");
      }
    }
    $fp=@fopen($this->filename,"r");
    if (!is_resource($fp)) {
      if (file_exists($this->filename)) {
        $out="You have no permission to see this page.\n\n";
        $out.="See MoniWiki/AccessControl\n";
        return $out;
      }
      $out=_("File does not exist");
      return $out;
    }
    $this->fsize=filesize($this->filename);
    if ($this->fsize > 0)
      $body=fread($fp,$this->fsize);
    fclose($fp);
    $this->body=$body;

    return $body;
  }

  function _get_raw_body() {
    $fp=@fopen($this->filename,"r");
    if (is_resource($fp)) {
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

    if (!empty($DBInfo->version_class)) {
      $class=getModule('Version',$DBInfo->version_class);
      $version=new $class ($DBInfo);
      $rev= $version->get_rev($this->name,$mtime,$last);

      if (!empty($rev)) return $rev;
    }
    return '';
  }

  function get_info($rev='') {
    global $DBInfo;

    $info=array('','','','','');
    if (empty($rev))
      $rev=$this->get_rev('',1);
    if (empty($rev)) return $info;

    if (!empty($DBInfo->version_class)) {
      $class=getModule('Version',$DBInfo->version_class);
      $version=new $class ($DBInfo);
      $out= $version->rlog($this->name,$rev,$opt);
    } else {
      return $info;
    }

    $state=0;
    if (isset($out)) {
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
  var $java_scripts=array();

  function Formatter($page="",$options=array()) {
    global $DBInfo;

    $this->page=$page;
    $this->head_num=1;
    $this->head_dep=0;
    $this->sect_num=0;
    $this->toc=0;
    $this->toc_prefix='';
    $this->highlight="";
    $this->prefix= (isset($options['prefix'])) ? $options['prefix']:get_scriptname();
    $this->self_query='';
    $this->url_prefix= $DBInfo->url_prefix;
    $this->imgs_dir= $DBInfo->imgs_dir;
    $this->imgs_dir_interwiki=$DBInfo->imgs_dir_interwiki;
    $this->imgs_dir_url=$DBInfo->imgs_dir_url;
    $this->actions= $DBInfo->actions;
    $this->inline_latex=
      $DBInfo->inline_latex == 1 ? 'latex':$DBInfo->inline_latex;
    $this->use_purple=$DBInfo->use_purple;
    $this->section_edit=$DBInfo->use_sectionedit;
    $this->auto_linebreak=$DBInfo->auto_linebreak;
    $this->nonexists=$DBInfo->nonexists;
    $this->url_mappings=&$DBInfo->url_mappings;
    $this->url_mapping_rule=&$DBInfo->url_mapping_rule;
    $this->css_friendly=$DBInfo->css_friendly;
    $this->use_smartdiff=$DBInfo->use_smartdiff;
    $this->use_easyalias=$DBInfo->use_easyalias;
    $this->submenu=$DBInfo->submenu;
    $this->email_guard=$DBInfo->email_guard;
    $this->interwiki_target=$DBInfo->interwiki_target ?
      ' target="'.$DBInfo->interwiki_target.'"':'';
    $this->filters=$DBInfo->filters;
    $this->postfilters=$DBInfo->postfilters;
    $this->use_rating=$DBInfo->use_rating;
    $this->use_etable=$DBInfo->use_etable;
    $this->use_metadata=$DBInfo->use_metadata;
    $this->use_namespace=$DBInfo->use_namespace;
    $this->udb=&$DBInfo->udb;
    $this->user=&$DBInfo->user;
    $this->check_openid_url=$DBInfo->check_openid_url;
    $this->register_javascripts($DBInfo->javascripts);
    $this->dynamic_macros=$DBInfo->dynamic_macros;

    if (($p=strpos($page->name,"~")))
      $this->group=substr($page->name,0,$p+1);

    $this->sister_on=1;
    $this->sisters=array();
    $this->foots=array();
    $this->pagelinks=array();
    $this->aliases=array();
    $this->icons="";
    $this->quote_style=$DBInfo->quote_style? $DBInfo->quote_style:'quote';

    $this->themedir= $DBInfo->themedir ? $DBInfo->themedir:dirname(__FILE__);
    $this->themeurl= $DBInfo->themeurl ? $DBInfo->themeurl:$DBInfo->url_prefix;
    $this->set_theme($options['theme']);

    $this->NULL='';
    if(getenv("OS")!="Windows_NT") $this->NULL=' 2>/dev/null';

    $this->_macrocache=0;
    $this->wikimarkup=0;
    $this->pi=array();
    $this->external_on=0;
    $this->external_target='';
    if ($DBInfo->external_target)
      $this->external_target='target="'.$DBInfo->external_target.'"';

    $this->baserule=array("/(?<!\<)<([^\s<>])/",
                     "/&(?!([^&;]+|#[0-9]+|#x[0-9a-fA-F]+);)/",
                     "/'''([^']*)'''/","/(?<!')'''(.*)'''(?!')/",
                     "/''([^']*)''/","/(?<!')''(.*)''(?!')/",
                     "/`(?<!\s)(?!`)([^`']+)(?<!\s)'(?=\s|$)/",
                     "/`(?<!\s)(?U)(.*)(?<!\s)`/",
                     "/^[ ]*(-{4,})$/e",
                     "/^(={4,})$/",
                     "/,,([^,]{1,40}),,/",
                     "/\^([^ \^]+)\^(?=\s|$)/",
                     "/\^\^(?<!\s)(?!\^)(?U)(.+)(?<!\s)\^\^/",
                     "/__(?<!\s)(?!_)(?U)(.+)(?<!\s)__/",
                     "/--(?<!\s)(?!-)(?U)(.+)(?<!\s)--/",
                     "/~~(?<!\s)(?!~)(?U)(.+)(?<!\s)~~/",
                     #"/(\\\\\\\\)/", # tex, pmWiki
                     );
    $this->baserepl=array("&lt;\\1",
                     "&amp;",
                     "<strong>\\1</strong>","<strong>\\1</strong>",
                     "<em>\\1</em>","<em>\\1</em>",
                     "&#96;\\1'","<tt>\\1</tt>",
                     "\$formatter->$DBInfo->hr_type"."_hr('\\1')",
                     "<br clear='all' />",
                     "<sub>\\1</sub>",
                     "<sup>\\1</sup>",
                     "<sup>\\1</sup>",
                     "<em class='underline'>\\1</em>",
                     "<del>\\1</del>",
                     "<del>\\1</del>",
                     #"<br />\n",
                     );

    # NoSmoke's MultiLineCell hack
    $this->extrarule=array("/{{\|(.*)\|}}/","/{{\|/","/\|}}/");
    $this->extrarepl=array("<table class='closure'><tr class='closure'><td class='closure'>\\1</td></tr></table>","</div><table class='closure'><tr class='closure'><td class='closure'><div>","</div></td></tr></table><div>");
    
    # set smily_rule,_repl
    if ($DBInfo->smileys) {
      $this->smiley_rule='/(?<=\s|^|>)('.$DBInfo->smiley_rule.')(?=\s|<|$)/e';
      $this->smiley_repl="\$formatter->smiley_repl('\\1')";

      #$this->baserule[]=$smiley_rule;
      #$this->baserepl[]=$smiley_repl;
    }
    $this->footrule="\[\*[^\]]*\s[^\]]+\]";

    $this->cache= new Cache_text("pagelinks");
    $this->bcache= new Cache_text("backlinks");
    # XXX
  }

  function set_wordrule($pis=array()) {
    global $DBInfo;

    $single=''; # single bracket
    $camelcase= isset($pis['#camelcase']) ? $pis['#camelcase']:
      $DBInfo->use_camelcase;

    if (!empty($pis['#singlebracket']) or !empty($DBInfo->use_singlebracket))
      $single='?';

    #$punct="<\"\'}\]\|;,\.\!";
    #$punct="<\'}\]\)\|;\.\!"; # , is omitted for the WikiPedia
    #$punct="<\'}\]\|\.\!"; # , is omitted for the WikiPedia
    $punct="<\'}\]\|\.\!\010\006"; # , is omitted for the WikiPedia
    $punct="<>\"\'}\]\|\.\!\010\006"; # " and > added
    $url="wiki|http|https|ftp|nntp|news|irc|telnet|mailto|file|attachment";
    if ($DBInfo->url_schemas) $url.='|'.$DBInfo->url_schemas;
    $this->urls=$url;
    $urlrule="((?:$url):\"[^\"]+\"[^\s$punct]*|(?:$url):(?:[^\s$punct]|(\.?[^\s$punct]))+(?<![,\.\):;\"\'>]))";
    #$urlrule="((?:$url):(\.?[^\s$punct])+)";
    #$urlrule="((?:$url):[^\s$punct]+(\.?[^\s$punct]+)+\s?)";
    # solw slow slow
    #(?P<word>(?:/?[A-Z]([a-z0-9]+|[A-Z]*(?=[A-Z][a-z0-9]|\b))){2,})
    $this->wordrule=
    # single bracketed rule [http://blah.blah.com Blah Blah]
    "(?:\[\^?($url):[^\s\]]+(?:\s[^\]]+)?\])|".
    # InterWiki
    # strict but slow
    #"\b(".$DBInfo->interwikirule."):([^<>\s\'\/]{1,2}[^\(\)<>\s\']+\s{0,1})|".
    #"\b([A-Z][a-zA-Z]+):([^<>\s\'\/]{1,2}[^\(\)<>\s\']+\s{0,1})|".
    #"\b([A-Z][a-zA-Z]+):([^<>\s\'\/]{1,2}[^\(\)<>\s\']+[^\(\)<>\s\',\.:\?\!]+)|".
    "(?:\b|\^?)(?:[A-Z][a-zA-Z]+):(?:[^:\(\)<>\s\']?[^\s<\'\",\!\010\006]+(?:\s(?![\x21-\x7e]))?(?<![,\.\)>]))|".
    #"(?:\b|\^?)(?:[A-Z][a-zA-Z]+):(?:[^:\(\)<>\s\']?[^\s<\'\",:\!\010\006]+(?:\s(?![\x21-\x7e]))?(?<![,\.\)>]))|".
    #"(\b|\^?)([A-Z][a-zA-Z]+):([^:\(\)<>\s\']?[^<>\s\'\",:\?\!\010\006]*(\s(?![\x21-\x7e]))?)";
    # for PR #301713
    #
    # new regex pattern for
    #  * double bracketted rule similar with MediaWiki [[Hello World]]
    #  * single bracketted words [Hello World] etc.
    #  * single bracketted words with double quotes ["Hello World"]
    #  * double bracketted words with double quotes [["Hello World"]]
    "(?<!\[)\!?\[(\[)$single(\")?(?:[^\[\]\",<\s'][^\[\],>]{0,255}[^\"])(?(4)\")(?(3)\])\](?!\])";

    if ($camelcase)
      $this->wordrule.='|'.
      "(?<![a-zA-Z])\!?(?:((\.{1,2})?\/)?[A-Z]([A-Z]+[0-9a-z]|[0-9a-z]+[A-Z])[0-9a-zA-Z]*)+\b";
    else
      # only bangmeta syntax activated
      $this->wordrule.='|'.
      "(?<![a-zA-Z])\!(?:((\.{1,2})?\/)?[A-Z]([A-Z]+[0-9a-z]|[0-9a-z]+[A-Z])[0-9a-zA-Z]*)+\b";
    # "(?<!\!|\[\[)\b(([A-Z]+[a-z0-9]+){2,})\b|".
    # "(?<!\!|\[\[)((?:\/?[A-Z]([a-z0-9]+|[A-Z]*(?=[A-Z][a-z0-9]|\b))){2,})\b|".
    # WikiName rule: WikiName ILoveYou (imported from the rule of NoSmoke)
    # and protect WikiName rule !WikiName
    #"(?:\!)?((?:\.{1,2}?\/)?[A-Z]([A-Z]+[0-9a-z]|[0-9a-z]+[A-Z])[0-9a-zA-Z]*)+\b|".

    $this->wordrule.='|'.
    # double bracketed rule similar with MediaWiki [[Hello World]]
    #"(?<!\[)\!?\[\[([^\[:,<\s'][^\[:,>]{1,255})\]\](?!\])|".
    # bracketed with double quotes ["Hello World"]
    #"(?<!\[)\!?\[\\\"([^\\\"]+)\\\"\](?!\])|".
    # "(?<!\[)\[\\\"([^\[:,]+)\\\"\](?!\])|".
    "($urlrule)|".
    # single linkage rule ?hello ?abacus
    #"(\?[A-Z]*[a-z0-9]+)";
    "(\?[A-Za-z0-9]+)";

    #if ($sbracket)
    #  # single bracketed name [Hello World]
    #  $this->_wordrule.= "|(?<!\[)\!?\[([^\[,<\s'][^\[,>]{1,255})\](?!\])";
    #else
    #  # only anchor [#hello], footnote [* note] allowed 
    #  $this->wordrule.= "|(?<!\[)\!?\[([#\*\+][^\[:,>]{1,255})\](?!\])";
    return $this->wordrule;
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

    $data=array();
    if (file_exists(dirname(__FILE__).'/theme.php')) {
      $used=array('icons','icon');
      $options['themedir']='.';
      $options['themeurl']=$DBInfo->url_prefix;
      $options['frontpage']=$DBInfo->frontpage;
      $data=getConfig(dirname(__FILE__).'/theme.php',$options);

      foreach ($data as $k=>$v) 
        if (!in_array($k, $used)) unset($data[$k]);
    }
    $options['themedir']=$this->themedir;
    $options['themeurl']=$this->themeurl;
    $options['frontpage']=$DBInfo->frontpage;

    $this->icon=array();
    if (file_exists($this->themedir."/theme.php")) {
      $data0=getConfig($this->themedir."/theme.php",$options);
      if (!empty($data0))
        $data=array_merge($data0,$data);
    }
    if (!empty($data)) {
      # read configurations
      while (list($key,$val) = each($data)) $this->$key=$val;
    }
    $this->icon=array_merge($DBInfo->icon,$this->icon);

    if (!isset($this->icon_bra)) {
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

    if (!$this->icons)
      $this->icons = array();
    $this->icons = array_merge($DBInfo->icons,$this->icons);

    if (!$this->icon_list) {
      $this->icon_list=$DBInfo->icon_list ? $DBInfo->icon_list:null;
    }
    if (!$this->purple_icon) {
      $this->purple_icon=$DBInfo->purple_icon;
    }
    if (!$this->perma_icon) {
      $this->perma_icon=$DBInfo->perma_icon;
    }
  }

  function include_theme($theme,$file='default',$params=array()) {
    $theme=trim($theme,'.-_');
    $theme=preg_replace(array('/\/+/','/\.+/'),array('/',''),$theme);
    if (preg_match('/_tpl$/',$theme)) {
      $type='tpl';
    } else {
      $type='php';
    }

    $theme_dir='theme/'.$theme;

    if (file_exists($theme_dir."/theme.php")) {
      $this->_vars['_theme']=_load_php_vars($theme_dir."/theme.php",$params);
    }

    $theme_path=$theme_dir.'/'.$file.'.'.$type;
    if (!file_exists($theme_path)) {
      trigger_error(sprintf(_("File '%s' does not exist."),$file),E_USER_NOTICE);
      return '';
    }
    switch($type) {
    case 'tpl':
      $params['path']=$theme_path;
      $out= $this->processor_repl('tpl_','',$params);
      break;
    case 'php':
      global $Config;
      $TPL_VAR=&$this->_vars;
      if (isset($TPL_VAR['_theme']) and is_array($TPL_VAR['_theme']) and $TPL_VAR['_theme']['compat'])
        extract($TPL_VAR);
      if ($params['print']) {
        $out=include $theme_path;
      } else {
        ob_start();
        include $theme_path;
        $out=ob_get_contents();
        ob_end_clean();
      }
      break;

    default:
      break;
    }
    return $out;
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
    global $Config;
    $pikeys=array('#redirect','#action','#title','#keywords','#noindex',
      '#format','#filter','#postfilter','#twinpages','#notwins','#nocomment','#comment',
      '#language','#camelcase','#nocamelcase','#cache','#nocache',
      '#singlebracket','#nosinglebracket','#rating','#norating','#nodtd');
    $pi=array();

    $update_body=false;
    $format='';
    if ( empty($this->pi['#format'])) {
      preg_match('%(:|/)%',$this->page->name,$sep);
      $key=strtok($this->page->name,':/');
      if (isset($Config['pagetype'][$key]) and $f=$Config['pagetype'][$key]) {
        $p=preg_split('%(:|/)%',$f);
        $p2=strlen($p[0].$p[1])+1;
        $p[1]=$p[1] ? $f{strlen($p[0])}.$p[1]:'';
        $p[2]=$p[2] ? $f{$p2}.$p[2]:'';
        $format=$p[0];
        if ($sep[1]) { # have : or /
          $format = ($sep[1]==$p[1]{0}) ? substr($p[1],1):
                    (($sep[1]==$p[2]{0}) ? substr($p[2],1):'plain');
        }
      } else if (isset($Config['pagetype']['*']))
        $format=$Config['pagetype']['*']; // default page type
    } else {
      if (empty($body) and !empty($this->pi['#format']))
        $format=$this->pi['#format'];
    }

    if (empty($body)) {
      if (!$this->page->exists()) return array();
      if ($this->pi) return $this->pi;
      $body=$this->page->get_raw_body();
      $update_body=true;
    }

    if ($this->use_metadata) {
      include_once('lib/metadata.php');
      list($this->metas,$nbody)=_get_metadata($body);
      if ($nbody!=null) $body=$nbody;
    }

    if (!$format and $body[0] == '<') {
      list($line, $dummy)= explode("\n", $body,2);
      if (substr($line,0,6) == '<?xml ')
        #$format='xslt';
        $format='xsltproc';
      elseif (preg_match('/^<\?php(\s|\b)/',$line))
        $format='php'; # builtin php detect
    } else {
      if ($body[0] == '#' and $body[1] =='!') {
        list($line, $body)= explode("\n", $body,2);
        $format= trim(substr($line,2));
      }

      $notused=array();
      $pilines=array();
      while ($body and $body[0] == '#') {
        # extract first line
        list($line, $body)= split("\n", $body,2);
        if ($line=='#') break;
        else if ($line[1]=='#') { $notused[]=$line; continue;}
        $pilines[]=$line;

        list($key,$val)= explode(" ",$line,2);
        $key=strtolower($key);
        $val=trim($val);
        if (in_array($key,$pikeys)) { $pi[$key]=$val ? $val:1; }
        else {
           $notused[]=$line;
           array_pop($pilines);
        }
      }
      $piline=implode("\n",$pilines);
      $piline=$piline ? $piline."\n":'';
      #
      if (isset($pi['#notwins'])) $pi['#twinpages']=0;
      if (isset($pi['#nocamelcase'])) $pi['#camelcase']=0;
      if (isset($pi['#nocache'])) $pi['#cache']=0;
      if (isset($pi['#nofilter'])) unset($pi['#filter']);
      if (isset($pi['#nosinglebracket'])) $pi['#singlebracket']=0;
    }

    if (empty($pi['#format']) and !empty($format)) $pi['#format']=$format; // override default
    if (!isset($pi['#format'])) $pi['#format']= $Config['default_markup'];

    if (($p = strpos($pi['#format'],' '))!== false) {
      $pi['args'] = substr($pi['#format'],$p+1);
      $pi['#format']= substr($pi['#format'],0,$p);
    }

    if ($notused) $body=join("\n",$notused)."\n".$body;
    if ($update_body) $this->page->write($body." "); # workaround XXX
    #if ($update_body) $this->page->write($body);
    $pi['raw']=$piline;
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

  function _diff_repl($arr) {
    if ($arr[1]{0}=="\010") { $tag='ins'; $sty='added'; }
    else { $tag='del'; $sty='removed'; }
    if (strpos($arr[2],"\n") !== false)
      return "<div class='diff-$sty'>".$arr[2]."</div>";
    return "<$tag class='diff-$sty'>".$arr[2]."</$tag>";
  }

  function write($raw) {
    print $raw;
  }

  function link_repl($url,$attr='',$opts=array()) {
    $nm = 0;
    $force = 0;
    if (is_array($url)) $url=$url[1];
    #if ($url[0]=='<') { print $url;return $url;}
    $url=str_replace('\"','"',$url); // XXX
    $bra = '';
    $ket = '';
    if ($url{0}=='[') {
      $bra='[';
      $ket=']';
      $url=substr($url,1,-1);
      $force=1;
    }
    switch ($url[0]) {
    case '{':
      $url=substr($url,3,-3);
      $url=str_replace("<","&lt;",$url);
      if (preg_match('/^({([^{}]+)})/s',$url,$sty)) { # textile like styling
        $url=substr($url,strlen($sty[1]));
        return "<span style='$sty[2]'>$url</span>";
      }
      if ($url[0]=='#' and ($p=strpos($url,' '))) {
        $col=strtok($url,' '); $url=strtok('');
        #if (!preg_match('/^#[0-9a-f]{6}$/',$col)) $col=substr($col,1);
        #return "<span style='color:$col'>$url</span>";
        if (preg_match('/^#[0-9a-f]{6}$/',$col))
          return "<span style='color:$col'>$url</span>";
        $url=$col.' '.$url;
      } else if (preg_match('/^((?:\+|\-)([1-6]?))(?=\s)(.*)$/',$url,$m)) {
        $m[3]=str_replace("&lt;","<",$m[3]);
        if ($m[2]=='') $m[1].='1';
        $fsz=array(
          '-5'=>'10%','-4'=>'20%','-3'=>'40%','-2'=>'60%','-1'=>'80%',
          '+1'=>'140%','+2'=>'180%','+3'=>'220%','+4'=>'260%','+5'=>'200%');
        return "<span style='font-size:".$fsz[$m[1]]."'>$m[3]</span>";
      }
      if ($url[0]==' ' and in_array($url[1],array('#','-','+')) !==false)
        $url='<span class="markup invisible"> </span>'.substr($url,1);
      return "<tt class='wiki'>".$url."</tt>"; # No link
      break;
    case '<':
      $nm = 1; // XXX <<MacroName>> support
      $url=substr($url,2,-2);
      return $this->macro_repl($url); # No link
    case '[':
      $url=substr($url,1,-1);
      return $this->macro_repl($url); # No link
      break;
    case '$':
      #return processor_latex($this,"#!latex\n".$url);
      $url=preg_replace('/<\/?sup>/','^',$url);
      //if ($url[1] != '$') $opt=array('type'=>'inline');
      //else $opt=array('type'=>'block');
      $opt=array('type'=>'inline');
      return $this->processor_repl($this->inline_latex,$url,$opt);
      break;
    case '#': # Anchor syntax in the MoinMoin 1.1
      $anchor=strtok($url,' ');
      return ($word=strtok('')) ? $this->link_to($anchor,$word):
                 "<a id='".($temp=substr($anchor,1))."'></a>";
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

    $url=str_replace('&lt;','<',$url); // revert from baserule
    $url=preg_replace('/&(?!#?[a-z0-9]+;)/i','&amp;',$url);

    if (($p=strpos($url,':')) !== false and
        (!isset($url{$p+1}) or (isset($url{$p+1}) and $url{$p+1}!=':'))) {
      if ($url[0]=='a') { # attachment:
        $url=preg_replace('/&amp;/i','&',$url);
        return $this->macro_repl('attachment',substr($url,11));
      }

      $external_icon='';
      $external_link='';
      if ($url[0] == '^') {
        $attr.=' target="_blank" ';
        $url=substr($url,1);
        $external_icon=$this->icon['external'];
      }

      if ($this->url_mappings) {
        $url=
          preg_replace('/('.$this->url_mapping_rule.')/ie',"\$this->url_mappings['\\1']",$url);
      }

      if (preg_match("/^(:|w|[A-Z])/",$url))
        return $this->interwiki_repl($url,'',$attr,$external_icon);
      else if (!preg_match('/^('.$this->urls.')/',$url)) {
        if ($this->use_namespace)
          return $this->interwiki_repl($url,'',$attr,$external_icon);
        else
          return $bra.$url.$ket;
      }

      if (preg_match("/^mailto:/",$url)) {
        $email=substr($url,7);
        $link=strtok($email,' ');
        $myname=strtok('');
        $link=email_guard($link,$this->email_guard);
        $myname=!empty($myname) ? $myname:$link;
        #$link=preg_replace('/&(?!#?[a-z0-9]+;)/i','&amp;',$link);
        return $this->icon['mailto']."<a class='externalLink' href='mailto:$link' $attr>$myname</a>$external_icon";
      }

      if ($force or preg_match('@ @',$url)) { # have a space ?
        if (($p = strpos($url,' ')) !== false) {
          $text = substr($url,$p+1);
          $url = substr($url,0,$p);
        }
        #$link=str_replace('&','&amp;',$url);
        $link=preg_replace('/&(?!#?[a-z0-9]+;)/i','&amp;',$url);
        if (empty($text)) $text=$url;
        else {
          $img_attr='';
          if (preg_match("/^attachment:/",$text)) {
            $atext=$text;
            if (($p=strpos($text,'?')) !== false) {
              $atext=substr($text,0,$p);
              parse_str(substr($text,$p+1),$attrs);
              foreach ($attrs as $n=>$v) {
                $img_attr.="$n=\"$v\" ";
              }
            }

            $text=$this->macro_repl('attachment',substr($text,11),1);
            $text=qualifiedUrl($this->url_prefix.'/'.$text);
          }
          if (preg_match("/^(http|ftp).*\.(png|gif|jpeg|jpg)$/i",$text)) {
            $atext=!empty($atext) ? $atext:$text;
            $text=str_replace('&','&amp;',$text);
            return "<a class='externalLink named' href='$link' $attr $this->external_target title='$url'><img class='external' style='border:0px' alt='$atext' src='$text' $img_attr/></a>";
          }
          if (!empty($this->external_on))
            $external_link='<span class="externalLink">('.$url.')</span>';
        }
        if (substr($url,0,7)=='http://' and $url[7]=='?') {
          $link=substr($url,7);
          return "<a href='$link'>$text</a>";
        } else if ($this->check_openid_url and preg_match("@^https?://@i",$url)) {
          if (is_object($this->udb) and $this->udb->_exists($url)) {
            $icon='openid';
            $icon="<a class='externalLink' href='$link'><img class='url' alt='[$icon]' src='".$this->imgs_dir_url."$icon.png' /></a>";
            $attr.=' title="'.$link.'"';
            $link=$this->link_url(_rawurlencode($text));
          }
        }
        if (empty($icon)) {
          $icon= strtok($url,':');
          $icon="<img class='url' alt='[$icon]' src='".$this->imgs_dir_url."$icon.png' />";
        }
        if ($text != $url) $eclass='named';
        else $eclass='unnamed';
        $link =str_replace(array('<','>'),array('&#x3c;','&#x3e;'),$link);
        return $icon. "<a class='externalLink $eclass' $attr $this->external_target href='$link'>$text</a>".$external_icon.$external_link;
      } # have no space
      $link = str_replace(array('<','>'),array('&#x3c;','&#x3e;'),$url);
      if (preg_match("/^(http|https|ftp)/",$url)) {
        if (preg_match("/(^.*\.(png|gif|jpeg|jpg))(\?.*?)?$/i",$url,$match)) {
          $url=preg_replace('/&amp;/','&',$url);
          $url=$match[1];
          $attrs=explode('&',substr($match[3],1));
          foreach ($attrs as $arg) {
            $name=strtok($arg,'=');
            $val=strtok(' ');
            if ($name and $val) $attr.=' '.$name.'="'.urldecode($val).'"';
            if ($name == 'align') $attr.=' class="img'.ucfirst($val).'"';
          }
          return "<img alt='$link' $attr src='$url' />";
        }
      }
      if (substr($url,0,7)=='http://' and $url[7]=='?') {
        $link=substr($url,7);
        return "<a class='internalLink' href='$link'>$link</a>";
      }
      $url=urldecode($url);
      return "<a class='externalLink' $attr href='$link' $this->external_target>$url</a>";
    } else {
      if ($url{0}=='?') {
          $url=substr($url,1);
      }
      return $this->word_repl($url,'',$attr);
    }
  }

  function interwiki_repl($url,$text='',$attr='',$extra='') {
    global $DBInfo;

    if ($url[0]=="w")
      $url=substr($url,5);
    else if ($url[0]==":")
      $url=substr($url,1);

    $wiki='';
    # wiki:MoinMoin:FrontPage
    # wiki:MoinMoin/FrontPage for MoinMoin compatibility.
    if (preg_match('/^([A-Z][a-zA-Z]+):(.*)$/',$url,$m)) {
      $wiki=$m[1]; $url=$m[2];
    }

    # wiki:"Hello World" wiki:MoinMoin:"Hello World"
    # [wiki:"Hello World" hello world]
    if (isset($url{0}) and $url[0]=='"') {
      if (preg_match('/^((")?[^"]+\2)((\s+)?(.*))?$/',$url,$m)) {
        $url=$m[1];
        if (isset($m[5])) $text=$m[5];
      }
    } else if (($p=strpos($url,' '))!==false) {
      $text=substr($url,$p+1);
      if (!empty($text)) $url=substr($url,0,$p);
    }

    if (empty($wiki)) {
      # wiki:FrontPage (not supported in the MoinMoin)
      # or [wiki:FrontPage Home Page]
      return $this->word_repl($url,$text.$extra,$attr,1);
    }

    # invalid InterWiki name
    if (empty($DBInfo->interwiki[$wiki])) {
      #$dum0=preg_replace("/(".$this->wordrule.")/e","\$this->link_repl('\\1')",$wiki);
      #return $dum0.':'.($page?$this->link_repl($page,$text):'');

      return $this->word_repl("$wiki:$url",$text.$extra,$attr,1);
    }

    $icon=$this->imgs_dir_interwiki.strtolower($wiki).'-16.png';
    $sx=16;$sy=16;
    if (isset($DBInfo->intericon[$wiki])) {
      $icon=$DBInfo->intericon[$wiki][2];
      $sx=$DBInfo->intericon[$wiki][0];
      $sy=$DBInfo->intericon[$wiki][1];
    }

    $page=$url;
    $url=$DBInfo->interwiki[$wiki];

    if ($page[0]=='"') # "extended wiki name"
      $page=substr($page,1,-1);

    if ($page=='/') $page='';
    $sep='';
    if (substr($page,-1)==' ') {
      $sep='<b></b>'; // auto append SixSingleQuotes
      $page=rtrim($page);
    }
    $urlpage=_urlencode($page);
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


    $img="<a class=\"interwiki\" href='$url' $this->interwiki_target>".
         "<img class=\"interwiki\" alt=\"$wiki:\" src='$icon' style='border:0' height='$sy' ".
         "width='$sx' title='$wiki:' /></a>";
    #if (!$text) $text=str_replace("%20"," ",$page);
    if (!$text) $text=urldecode($page);
    else if (preg_match("/^(http|ftp|attachment):.*\.(png|gif|jpeg|jpg)$/i",$text)) {
      if (substr($text,0,11)=='attachment:') {
        $fname=substr($text,11);
        $ntext=$this->macro_repl('Attachment',$fname,1);
        if (!file_exists($ntext))
          $text=$this->macro_repl('Attachment',$fname);
        else {
          $text=qualifiedUrl($this->url_prefix.'/'.$ntext);
          $text= "<img style='border:0' alt='$text' src='$text' />";
        }
      } else
        $text= "<img style='border:0' alt='$text' src='$text' />";
      $img='';
    }

    if (preg_match("/\.(png|gif|jpeg|jpg)$/i",$url))
      return "<a href='".$url."' $attr title='$wiki:$page'><img style='vertical-align:middle;border:0px' alt='$text' src='$url' /></a>$extra";

    if (!$text) return $img;
    return $img. "<a href='".$url."' $attr title='$wiki:$page'>$text</a>$extra$sep";
  }

  function store_pagelinks() {
    global $DBInfo;
    unset($this->pagelinks['TwinPages']);
    $new=array_keys($this->pagelinks);
    $cur=unserialize($this->cache->fetch($this->page->name));
    if (!is_array($cur)) $cur=array();

    $ad=array_diff($new,$cur);
    $de=array_diff($cur,$new);
    // merge new backlinks
    foreach ($ad as $a) {
      if (!$a or !$DBInfo->hasPage($a)) continue;
      $bl=unserialize($this->bcache->fetch($a));
      if (!is_array($bl)) $bl=array();
      array_merge($bl,array($this->page->name));
      $bl=array_unique($bl);
      $this->bcache->update($a,serialize($bl));
    }
    // remove back links
    foreach ($de as $d) {
      if (!$d or !$DBInfo->hasPage($d)) continue;
      $bl=unserialize($this->bcache->fetch($d));
      if (!is_array($bl)) $bl=array();
      $bl=array_diff($bl,array($this->page->name));
      $this->bcache->update($d,serialize($bl));
    }
    // XXX
    if ($new)
      $this->cache->update($this->page->name,serialize($new));
    else
      $this->cache->remove($this->page->name);
#      $this->page->mtime());
  }

  function get_pagelinks() {
    if (!$this->wordrule) $this->set_wordrule();
    if ($this->cache->exists($this->page->name)) {
      $links=$this->cache->fetch($this->page->name);
      if ($links !== false) return unserialize($links);
    }
    // no pagelinks found. XXX
    return array();
  }

  function get_backlinks() {
    if ($this->bcache->exists($this->page->name)) {
      $links=$this->bcache->fetch($this->page->name);
      if ($links !== false) return unserialize($links);
    }
    // no backlinks found. XXX
    return array();
  }

  function word_repl($word,$text='',$attr='',$nogroup=0,$islink=1) {
    global $DBInfo;
    $nonexists='nonexists_'.$this->nonexists;

    $extended = false;
    if ($word[0]=='"') {
      # ["extended wiki name"]
      # ["Hello World" Go to Hello]
      if (preg_match('/^((")?[^"]+\2)((\s+)?(.*))?$/',$word,$m)) {
        $word=substr($m[1],1,-1);
        if (isset($m[5])) $text=$m[5]; // text arg ignored
      }
      $extended=true;
      $page=$word;
    } else
      #$page=preg_replace("/\s+/","",$word); # concat words
      $page=normalize($word); # concat words

    if (empty($DBInfo->use_twikilink)) $islink=0;
    list($page,$page_text,$gpage)=
      normalize_word($page,$this->group,$this->page->name,$nogroup,$islink);
    if ($text) {
      if (preg_match("/^(http|ftp|attachment).*\.(png|gif|jpeg|jpg)$/i",$text)) {
        if (substr($text,0,11)=='attachment:') {
          $fname=substr($text,11);
          $ntext=$this->macro_repl('attachment',$fname,1);
          if (!file_exists($ntext)) {
            $word=$this->macro_repl('attachment',$fname);
          } else {
            $text=qualifiedUrl($this->url_prefix.'/'.$ntext);
            $word= "<img style='border:0' alt='$text' src='$text' /></a>";
          }
        } else {
          $text=str_replace('&','&amp;',$text);
          $word="<img style='border:0' alt='$word' src='$text' /></a>";
        }
      } else $word=$text;
    } else {
      $word=$text=$page_text ? $page_text:$word;
      #print $text;
      $word=htmlspecialchars($word);
      $word=str_replace('&amp;#','&#',$word); # hack
    }

    $url=_urlencode($page);
    $url_only=strtok($url,'#?'); # for [WikiName#tag] [wiki:WikiName#tag Tag]
    #$query= substr($url,strlen($url_only));
    if ($extended) $page=rawurldecode($url_only); # C++
    else $page=urldecode($url_only);
    $url=$this->link_url($url);

    #check current page
    if ($page == $this->page->name) $attr.=' class="current"';

    //$url=$this->link_url(_rawurlencode($page)); # XXX
    $idx = 0; // XXX
    if (isset($this->pagelinks[$page])) {
      $idx=$this->pagelinks[$page];
      switch($idx) {
        case 0:
          #return "<a class='nonexistent' href='$url'>?</a>$word";
          return call_user_func(array(&$this,$nonexists),$word,$url,$page);
        case -1:
          $title='';
          $tpage=urlencode($page);
          if ($tpage != $word) $title="title=\"$tpage\" ";
          return "<a href='$url' $title$attr>$word</a>";
        case -2:
          return "<a href='$url' $attr>$word</a>".
            "<tt class='sister'><a href='$url'>&#x203a;</a></tt>";
        case -3:
          #$url=$this->link_url(_rawurlencode($gpage));
          return $this->link_tag(_rawurlencode($gpage),'',$this->icon['main'],'class="main"').
            "<a href='$url' $attr>$word</a>";
        default:
          return "<a href='$url' $attr>$word</a>".
            "<tt class='sister'><a href='#sister$idx'>&#x203a;$idx</a></tt>";
      }
    } else if ($DBInfo->hasPage($page)) {
      $title='';
      $this->pagelinks[$page]=-1;
      $tpage=urlencode($page);
      if ($tpage != $word) $title="title=\"$tpage\" ";
      return "<a href='$url' $title$attr>$word</a>";
    } else {
      if ($gpage and $DBInfo->hasPage($gpage)) {
        $this->pagelinks[$page]=-3;
        #$url=$this->link_url(_rawurlencode($gpage));
        return $this->link_tag(_rawurlencode($gpage),'',$this->icon['main'],'class="main"').
          "<a href='$url' $attr>$word</a>";
      }
      if (!empty($this->aliases[$page])) return $this->aliases[$page];
      if (!empty($this->sister_on)) {
        $sisters=$DBInfo->metadb->getSisterSites($page, $DBInfo->use_sistersites);
        if ($sisters === true) {
          $this->pagelinks[$page]=-2;
          return "<a href='$url'>$word</a>".
            "<tt class='sister'><a href='$url'>&#x203a;</a></tt>";
        }
        if (!empty($sisters)) {
          if (!empty($this->use_easyalias) and strpos($sisters,' ') === false) {
            # this is a alias
            $this->use_easyalias=0;
            $url=$this->link_repl(substr($sisters,0,-1).' '.$word.']');
            $this->use_easyalias=1;
            $this->aliases[$page]=$url;
            return $url;
          }
          $this->sisters[]=
            "<li><tt class='foot'><a id='sister$this->sister_idx'></a>".
            "<a href='#rsister$this->sister_idx'>$this->sister_idx&#x203a;</a></tt> ".
            "$sisters </li>";
          $this->pagelinks[$page]=$this->sister_idx++;
          $idx=$this->pagelinks[$page];
        }
        if ($idx > 0) {
          return "<a href='$url'>$word</a>".
           "<tt class='sister'>".
           "<a id='rsister$idx'></a>".
           "<a href='#sister$idx'>&#x203a;$idx</a></tt>";
        }
      }
      $this->pagelinks[$page]=0;
      #return "<a class='nonexistent' href='$url'>?</a>$word";
      return call_user_func(array(&$this,$nonexists),$word,$url,$page);
    }
  }

  function nonexists_simple($word,$url) {
    return "<a class='nonexistent nomarkup' href='$url' rel='nofollow'>?</a>$word";
  }

  function nonexists_nolink($word,$url) {
    return "$word";
  }

  function nonexists_always($word,$url,$page) {
    $title='';
    if ($page != $word) $title="title=\"$page\" ";
    return "<a href='$url' $title rel='nofollow'>$word</a>";
  }

  function nonexists_forcelink($word,$url) {
    return "<a class='nonexistent' rel='nofollow' href='$url'>$word</a>";
  }

  function nonexists_fancy($word,$url) {
    global $DBInfo;
    if ($word[0]=='<' and preg_match('/^<[^>]+>/',$word))
      return "<a class='nonexistent' rel='nofollow' href='$url'>$word</a>";
    #if (preg_match("/^[a-zA-Z0-9\/~]/",$word))
    if (ord($word[0]) < 125) {
      $link=$word[0];
      if ($word[0]=='&') {
        $link=strtok($word,';').';';$last=strtok('');
      } else
        $last=substr($word,1);
      return "<span><a class='nonexistent' rel='nofollow' href='$url'>$link</a>".$last.'<span>';
    }
    if (strtolower($DBInfo->charset) == 'utf-8')
      $utfword=$word;
    else if (function_exists('iconv'))
      $utfword=iconv($DBInfo->charset,'utf-8',$word);
    if ($utfword) {
      if (function_exists('mb_encode_numericentity')) {
        $mbword=mb_encode_numericentity($utfword,$DBInfo->convmap,'utf-8');
      } else {
        include_once('lib/compat.php');
        $mbword=utf8_mb_encode($utfword);
      }
      $tag=strtok($mbword,';').';'; $last=strtok('');
      if ($tag)
        return "<span><a class='nonexistent' rel='nofollow' href='$url'>$tag</a>".$last.'<span>';
    }
    return "<a class='nonexistent nomarkup' rel='nofollow' href='$url'>?</a>$word";
  }

  function head_repl($depth,$head,&$headinfo,$attr='') {
    $dep=$depth;
    $this->nobr=1;

    if ($headinfo == null)
      return "<h$dep$attr>$head</h$dep>";

    $head=str_replace('\"','"',$head); # revert \\" to \"

    if (!$headinfo['top']) {
      $headinfo['top']=$dep; $depth=1;
    } else {
      $depth=$dep - $headinfo['top'] + 1;
      if ($depth <= 0) $depth=1;
    }

#    $depth=$dep;
#    if ($dep==1) $depth++; # depth 1 is regarded same as depth 2
#    $depth--;

    $num=''.$headinfo['num'];
    $odepth=$headinfo['dep'];

    if ($head[0] == '#') {
      # reset TOC numberings
      # default prefix is empty.
      if (!empty($this->toc_prefix)) $this->toc_prefix++;
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

    $headinfo['dep']=$depth; # save old
    $headinfo['num']=$num;

    $prefix=$this->toc_prefix;
    if ($this->toc)
      $head="<span class='tocnumber'><a href='#toc'>$num</a> </span>$head";
    $perma='';
    if (!empty($this->perma_icon))
    $perma=" <a class='perma' href='#s$prefix-$num'>$this->perma_icon</a>";

    return "$close$open<h$dep$attr><a id='s$prefix-$num'></a>$head$perma</h$dep>";
  }

  function include_functions()
  {
    foreach (func_get_args() as $f) function_exists($f) or include_once 'plugin/function/'.$f.'.php';
  }

  function macro_repl($macro,$value='',$options='') {
    // macro ID
    $this->mid=!empty($options['mid']) ? $options['mid']:
      (!empty($this->mid) ? ++$this->mid:1);

    preg_match("/^([A-Za-z0-9]+)(\((.*)\))?$/",$macro,$match);
    if (empty($match)) return $this->word_repl($macro);
    $bra='';$ket='';
    if ($this->wikimarkup and $macro != 'attachment' and !$options['nomarkup']) {
      $markups=str_replace(array('=','-','<'),array('==','-=','&lt;'),$macro);
      $markups=preg_replace('/&(?!#?[a-z0-9]+;)/i','&amp;',$markups);
      $bra= "<span class='wikiMarkup'><!-- wiki:\n[[$markups]]\n-->";
      $ket= '</span>';
      $options['nomarkup']=1; // for the attachment macro
    }
    if (empty($value) and isset($match[2])) { #strpos($macro,'(') !== false)) {
      $name=$match[1];
      $args=empty($match[3]) ? true:$match[3];

    } else {
      $name=$macro; $args=$value;
    }

    if (!function_exists ('macro_'.$name)) {
      $np = getPlugin($name);
      if (empty($np)) return $this->link_repl($name);
      include_once('plugin/'.$np.'.php');
      if (!function_exists ('macro_'.$name)) return '[['.$macro.']]';
    }

    if ($this->_macrocache and empty($options['call']) and
      (isset($this->dynamic_macros[strtolower($name)]) or
      isset($this->dynamic_macros[$name]))) {
      $arg = '';
      if ($args === true) $arg = '()';
      else if (!empty($args)) $arg = '('.$args.')';
      $macro=$name.$arg;
      $md5sum= md5($macro);
      $this->_macros[$md5sum]=array($macro,$mid);
      return '[['.$md5sum.']]';
    }

    $ret=call_user_func_array('macro_'.$name,array(&$this,$args,&$options));
    if (is_array($ret)) return $ret;
    return $bra.$ret.$ket;
  }

  function processor_repl($processor,$value,$options="") {
    $bra='';$ket='';
    if (!empty($this->wikimarkup) and empty($options['nomarkup'])) {
      if ($options['type'] == 'inline') {
        $markups=str_replace(array('=','-','&','<'),array('==','-=','&amp;','&lt;'),$value);
        $bra= "<span class='wikiMarkup' style='display:inline'><!-- wiki:\n".$markups."\n-->";
      } else {
        if ($processor == $this->pi['#format']) { $btag='';$etag=''; }
        else { $btag='{{{';$etag='}}}'; }
        if ($value{0}!='#' and $value{1}!='!') $notag="\n";
        $markups=str_replace(array('=','-','&','<'),array('==','-=','&amp;','&lt;'),$value);
        $bra= "<span class='wikiMarkup'><!-- wiki:\n".$btag.$notag.$markups.$etag."\n-->";
      }
      $ket= '</span>';
    }
    $pf = $processor;
    if (!($f = function_exists('processor_'.$processor)))
      $pf = getProcessor($processor);
    if (empty($pf)) {
      $ret= call_user_func('processor_plain',$this,$value,$options);
      return $bra.$ret.$ket;
    }
    if (!$f and !($c=class_exists('processor_'.$pf))) {
      include_once("plugin/processor/$pf.php");
      $name='processor_'.$pf;
      if (!($f=function_exists($name)) and !($c=class_exists($name))) {
        $processor='plain';
        $f=true;
      }
    }

    if ($f) {
      if (!empty($this->use_smartdiff) and
        preg_match("/\006|\010/", $value)) $processor='plain';

      $ret= call_user_func_array("processor_$processor",array(&$this,$value,$options));
      return $bra.$ret.$ket;
    }

    $classname='processor_'.$pf;
    $myclass= & new $classname($this,$options);
    $ret= call_user_func(array($myclass,'process'),$value,$options);
    if ($myclass->_type=='wikimarkup') return $ret;

    return $bra.$ret.$ket;
  }

  function filter_repl($filter,$value,$options='') {
    if (!function_exists('filter_'.$filter)) {
      $ff=getFilter($filter);
      if (!$ff) return $value;
      include_once("plugin/filter/$ff.php");
      #$filter=$ff;
    }
    if (!function_exists ("filter_".$filter)) return $value;

    return call_user_func("filter_$filter",$this,$value,$options);
  }

  function postfilter_repl($filter,$value,$options='') {
    if (!function_exists('postfilter_'.$filter) and !function_exists('filter_'.$filter)) {
      $ff=getFilter($filter);
      if (!$ff) return $value;
      include_once("plugin/filter/$ff.php");
      #$filter=$ff;
    }
    if (!function_exists ("postfilter_".$filter)) return $value;

    return call_user_func("postfilter_$filter",$this,$value,$options);
  }

  function ajax_repl($plugin,$options='') {
    if (!function_exists('ajax_'.$plugin) and !function_exists('do_'.$plugin)) {
      $ff=getPlugin($plugin);
      if (!$ff)
        return ajax_invalid($this,array('title'=>_("Invalid ajax action.")));
      include_once("plugin/$ff.php");
    }
    if (!function_exists ('ajax_'.$plugin)) {
      if (function_exists('do_'.$plugin)) {
        call_user_func('do_'.$plugin,$this,$options);
        return;
      } else if (function_exists('macro_'.$plugin)) {
        print call_user_func_array('macro_'.$plugin,array(&$this,'',$options));
        return;
      }
      return ajax_invalid($this,array('title'=>_("Invalid ajax action.")));
    }

    return call_user_func('ajax_'.$plugin,$this,$options);
  }

  function smiley_repl($smiley) {
    global $DBInfo;

    $img=$DBInfo->smileys[$smiley][3];

    $alt=str_replace("<","&lt;",$smiley);

    if (preg_match('/^(https?|ftp):/',$img))
      return "<img src='$img' style='border:0' class='smiley' alt='$alt' title='$alt' />";
    return "<img src='$this->imgs_dir/$img' style='border:0' class='smiley' alt='$alt' title='$alt' />";
  }

  function link_url($pageurl,$query_string="") {
    global $DBInfo;
    $sep=$DBInfo->query_prefix;

    if (!$query_string) {
      if (isset($this->query_string)) $query_string=$this->query_string;
    } else if ($query_string and $query_string{0}=='#') {
      $query_string= $this->self_query.$query_string;
    }
    #{
    #    $query_string = $this->query_string;
    #  } else if ($query_string[0]=='?') {
    #    $query_string= $this->query_string.'&amp;'.substr($query_string,1);
    #  } else {
    #  }
    #}

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
    if ($query_string{0}=='?') $attr=empty($attr) ? 'rel="nofollow"':$attr.' rel="nofollow"';
    $url=$this->link_url($pageurl,$query_string);
    return sprintf("<a href=\"%s\" %s>%s</a>", $url, $attr, $text);
  }

  function link_to($query_string="",$text="",$attr="") {
    if (!$text)
      $text=htmlspecialchars($this->page->name);

    return $this->link_tag($this->page->urlname,$query_string,$text,$attr);
  }

  function fancy_hr($rule) {
    $sz=($sz=strlen($rule)-4) < 6 ? ($sz ? $sz+2:0):8;
    $size=$sz ? " style='height:{$sz}px'":'';
    return "<div class='separator'><hr$size /></div>";
  }

  function simple_hr() {
    return "<div class='separator'><hr /></div>";
  }

  function _list($on,$list_type,$numtype="",$closetype="",
    $divtype=' class="indent"') {
    $close='';$open='';
    if ($list_type=="dd") {
      if ($on)
         #$list_type="dl><dd";
         $list_type="div$divtype";
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
        $lists=array(
          'c'=>'circle',
          's'=>'square',
          'i'=>'lower-roman',
          'I'=>'upper-roman',
          'a'=>'lower-latin',
          'A'=>'upper-latin',
          'n'=>'none'
        );
        $start=substr($numtype,1);
        $litype='';
        if (array_key_exists($numtype{0},$lists))
          $litype=' style="list-style-type:'.$lists[$numtype{0}].'"';
        if (!empty($start)) {
          #$litype[]='list-type-style:'.$lists[$numtype{0}];
          return "<$list_type$litype start='$start'>";
        }
        return "<$list_type$litype>";
      }
      return "$close$open<$list_type>"; // FIX Wikiwyg
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

  function _td_span($str,$align='') {
    $len=strlen($str)/2;
    if ($len==1) return '';
    $attr[]="colspan='$len'"; #$attr[]="align='center' colspan='$len'";
    return ' '.implode(' ',$attr);
  }

  function _attr($attr,&$sty,$myclass=array(),$align='') {
    $aligns=array('center'=>1,'left'=>1,'right'=>1);
    $attrs=preg_split('@(\w+\=(?:"[^"]*"|\'[^\']*\')\s*|\w+\=[^"\']+\s*)@',
      $attr,-1, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);

    $myattr=array();
    foreach ($attrs as $at) {
      $at=str_replace(array("'",'"'),'',rtrim($at));
      $k=strtok($at,'=');
      $v=strtok('');
      $k=strtolower($k);
      if ($k == 'style') {
        $stys=preg_split('@;\s*@',$v,-1,PREG_SPLIT_NO_EMPTY);
        foreach ($stys as $my) {
          $nk=strtok($my,':');
          $nv=strtok('');
          $sty[$nk]=$nv;
        }
      } else {
        switch($k) {
          case 'class':
            if (isset($aligns[$v]))
              $align=$v;
            else $myclass[]=$v;
            break;
          case 'align':
            $align=$v;
            break;
          case 'bgcolor':
            $sty['background-color']=strtolower($v);
            break;
          case 'border':
          case 'width':
          case 'height':
          case 'color':
            $sty[$k]=strtolower($v);
            break;
          default:
            if ($v) $myattr[$k]=$v;
            break;
        }
      }
    }

    if ($align) $myclass[]=$align;
    if ($myclass) $myattr['class']=implode(' ',array_unique($myclass));
    if ($sty) {
      $mysty='';
      foreach ($sty as $k=>$v) $mysty.="$k:$v;";
      $myattr['style']=$mysty;
    }
    return $myattr;
  }

  function _td($line,&$tr_attr) {
    $cells=preg_split('/((?:\|\|)+)/',$line,-1,
      PREG_SPLIT_DELIM_CAPTURE);
    $row='';
    for ($i=1,$s=sizeof($cells);$i<$s;$i+=2) {
      $align='';
      $m=array();
      preg_match('/^((&lt;[^>]+>)*)(\s?)(.*)(?<!\s)(\s*)?$/s',
        $cells[$i+1],$m);
      $cell=$m[3].$m[4].$m[5];
      if (isset($cell{0}) and $cell{strlen($cell)-1} == "\n")
        $cell = substr($cell,0,-1).' '; // XXX

      if (strpos($cell,"\n") !== false)
        $cell=$this->processor_repl('monimarkup',$cell, array('notoc'=>1));
      if ($m[3] and $m[5]) $align='center';
      else if (!$m[3]) $align='';
      else if (!$m[5]) $align='right';

      $attr=$this->_td_attr($m[1],$align);
      if (!$tr_attr) $tr_attr=$m[1]; // XXX
      $attr.=$this->_td_span($cells[$i]);
      $row.="<td $attr>".$cell.'</td>';
    }
    return $row;
  }

  function _td_attr(&$val,$align='') {
    if (!$val) {
      if ($align) return 'class="'.$align.'"';
      return '';
    }
    $para=str_replace(array('&lt;','&gt'),array('<','>'),$val);
    $paras= explode('><',substr($para,1,-1));
    # rowspan
    $sty=array();
    $rsty=array();
    $attr=array();
    $rattr=array();
    $myattr=array();
    $myclass=array();

    foreach ($paras as $para) {
    if (preg_match("/^(\^|v)?\|(\d+)$/",$para,$match)) {
      $attr['rowspan']=$match[2];
      if ($match[1]) {
        if ($match[1] == '^') $attr['valign']='top';
        else $attr['valign']='bottom';
      }
    }
    else if (strlen($para)==1) {
      switch ($para) {
      case '(':
        $align='left';
        break;
      case ')':
        $align='right';
        break;
      case ':':
        $align='center';
        break;
      default:
        break;
      }
      $myattr=$this->_attr('',$sty,$myclass,$align);
      $attr=array_merge($attr,$myattr);
    }
    else if (preg_match("/^\-(\d+)$/",$para,$match))
      $attr['colspan']=$match[1];
    else if ($para[0]=='#')
      $sty['background-color']=strtolower($para);
    else {
      if (substr($para,0,7)=='rowspan') {
        $attr['rowspan']=substr($para,7);
      } else if (substr($para,0,3)=='row') {
        // row properties
        $val=substr($para,3);
        $myattr=$this->_attr($val,$rsty);
        $rattr=array_merge($rattr,$myattr);
      } else {
        $myattr=$this->_attr($para,$sty,$myclass,$align);
        $attr=array_merge($attr,$myattr);
      }
    }
    }
    $myclass=!empty($attr['class']) ? $attr['class']:'';
    unset($attr['class']);
    if (!empty($myclass))
      $attr['class']=trim($myclass);

    $val='';
    foreach ($rattr as $k=>$v) $val.=$k.'="'.$v.'" ';

    $ret='';
    foreach ($attr as $k=>$v) $ret.=$k.'="'.$v.'" ';
    return $ret;
  }

  function _table($on,&$attr) {
    if (!$on) return "</table>\n";

    $sty=array();
    $myattr=array();
    $mattr=array();
    $attrs=str_replace(array('&lt;','&gt'),array('<','>'),$attr);
    $attrs= explode('><',substr($attrs,1,-1));
    $myclass=array('wiki');
    $rattr=array();
    $attr='';
    foreach ($attrs as $tattr) {
      $tattr=trim($tattr);
      if (empty($tattr)) continue;
      if ($tattr[0]=='#') {
        $sty['background-color']=strtolower($tattr);
      } else if (substr($tattr,0,5)=='table') {
        $tattr=substr($tattr,5);
        $mattr=$this->_attr($tattr,$sty,$myclass);
        $myattr=array_merge($myattr,$mattr);
      } else { // not table attribute
        $rattr[]=$tattr;
        #else $myattr=$this->_attr($tattr,$sty,$myclass);
      }
    }
    if (!empty($rattr)) $attr='&lt;'.implode('>&lt;',$rattr).'>';
    if (!empty($myattr)) {
      $my='';
      foreach ($myattr as $k=>$v) $my.=$k.'="'.$v.'" ';
    }
    else $my='class="wiki"';
    return "<table cellpadding='3' cellspacing='2' $my>\n";
  }

  function _purple() {
    if (!$this->use_purple) return '';
    $id=sprintf('%03d',$this->purple_number++);
    $nid='p'.$id;
    return "<span class='purple'><a name='$nid' id='$nid'></a><a href='#$nid'>(".$id.")</a></span>";
  }

  function _div($on,&$in_div,&$enclose,$attr='') {
    $close=$open='';
    $tag=array("</div>\n","<div$attr>");
    if ($on) { $in_div++; $open=$enclose;}
    else {
      if (!$in_div) return '';
      $close=$enclose;
      $in_div--;
    }
    $enclose='';
    $purple='';
    if (!$on) $purple=$this->_purple();
    #return "(".$in_div.")".$tag[$on];
    return $purple.$open.$tag[$on].$close;
  }

  function _li($on,$empty='') {
    $tag=array("</li>\n",'<li>');
    $purple='';
    if (!$on and !$empty) $purple=$this->_purple();
    return $purple.$tag[$on];
  }

  function _fixpath() {
    $this->url_prefix= qualifiedUrl($DBInfo->url_prefix);
    $this->prefix= qualifiedUrl($this->prefix);
    $this->imgs_dir= qualifiedUrl($this->imgs_dir);
    $this->imgs_dir_interwiki=qualifiedUrl($this->imgs_dir_interwiki);
    $this->imgs_dir_url=qualifiedUrl($this->imgs_dir_url);
  }

  function postambles() {
    $save= $this->wikimarkup;
    $this->wikimarkup=0;
    if (!empty($this->postamble)) {
      $sz=sizeof($this->postamble);
      for ($i=0;$i<$sz;$i++) {
        $postamble=implode("\n",$this->postamble);
        if (!trim($postamble)) continue;
        list($type,$name,$val)=explode(':',$postamble,3);
        if (in_array($type,array('macro','processor'))) {
          switch($type) {
            case 'macro':
              print $this->macro_repl($name,$val,$options);
              break;
            case 'processor':
              print $this->processor_repl($name,$val,$options);
              break;
          }
        }
      }
    }
    $this->wikimarkup=$save;
  }

  function send_page($body="",$options=array()) {
    global $DBInfo;
    if (!empty($options['fixpath'])) $this->_fixpath();
    // reset macro ID
    $this->mid=0;

    if ($this->wikimarkup) $this->nonexists='always';

    if ($body) {
      $pi=$this->get_instructions($body);

      if ($this->wikimarkup and $pi['raw']) {
        $pi_html=str_replace("\n","<br />\n",$pi['raw']);
        print "<span class='wikiMarkup'><!-- wiki:\n$pi[raw]\n-->$pi_html</span>";
      }
      $this->set_wordrule($pi);
      $fts=array();
      if (isset($pi['#filter'])) $fts=preg_split('/(\||,)/',$pi['#filter']);
      if (!empty($this->filters)) $fts=array_merge($fts,$this->filters);
      if (!empty($fts)) {
        foreach ($fts as $ft) {
          $body=$this->filter_repl($ft,$body,$options);
        }
      }
      if (isset($pi['#format']) and $pi['#format'] != 'wiki') {
        $pi_line='';
        if (!empty($pi['args'])) $pi_line="#!".$pi['#format']." $pi[args]\n";
        $savepi=$this->pi; // hack;;
        $this->pi=$pi;
        $text= $this->processor_repl($pi['#format'],
          $pi_line.$body,$options);
        $this->pi=$savepi;
        if ($this->use_smartdiff)
          $text= preg_replace_callback(array("/(\006|\010)(.*)\\1/sU"),
            array(&$this,'_diff_repl'),$text);

        $fts=array();
        if (isset($pi['#postfilter'])) $fts=preg_split('/(\||,)/',$pi['#postfilter']);
        if (!empty($this->postfilters)) $fts=array_merge($fts,$this->postfilters);
        if (!empty($fts)) {
          foreach ($fts as $ft)
            $text=$this->postfilter_repl($ft,$text,$options);
        }
	$this->postambles();

        print $this->get_javascripts();
        print $text;

        return;
      }
      $lines=explode("\n",$body);
    } else {
      # XXX need to redesign pagelink method ?
      if (empty($DBInfo->without_pagelinks_cache)) {
        $dmt=filemtime($DBInfo->text_dir.'/.'); // mtime fix XXX
        $this->update_pagelinks= $dmt > $this->cache->mtime($this->page->name);
        #like as..
        #if (!$this->update_pagelinks) $this->pagelinks=$this->get_pagelinks();
      }

      if (isset($options['rev'])) {
        $body=$this->page->get_raw_body($options);
        $pi=$this->get_instructions($body);
      } else {
        $pi=$this->get_instructions($dum);
        $body=$this->page->get_raw_body($options);
      }
      $this->set_wordrule($pi);
      if (!empty($this->wikimarkup) and !empty($pi['raw']))
        print "<span class='wikiMarkup'><!-- wiki:\n$pi[raw]\n--></span>";

      if (!empty($this->use_rating) and empty($this->wikimarkup) and empty($pi['#norating'])) {
        $this->pi=$pi;
        $old=$this->mid;
        if (isset($pi['#rating'])) $rval=$pi['#rating'];
        else $rval='0';

        print '<div class="wikiRating">'.$this->macro_repl('Rating',$rval,array('mid'=>'page'))."</div>\n";
        $this->mid=$old;
      }

      $fts=array();
      if (isset($pi['#filter'])) $fts=preg_split('/(\||,)/',$pi['#filter']);
      if (!empty($this->filters)) $fts=array_merge($fts,$this->filters);
      if ($fts) {  
        foreach ($fts as $ft) {
          $body=$this->filter_repl($ft,$body,$options);
        }
      }

      $this->pi=$pi;
      if (isset($pi['#format']) and $pi['#format'] != 'wiki') {
        $pi_line='';
        if (isset($pi['args'])) $pi_line="#!".$pi['#format']." $pi[args]\n";
        $text= $this->processor_repl($pi['#format'],$pi_line.$body,$options);

        $fts=array();
        if (isset($pi['#postfilter'])) $fts=preg_split('/(\||,)/',$pi['#postfilter']);
        if (!empty($this->postfilters)) $fts=array_merge($fts,$this->postfilters);
        if ($fts) {
          foreach ($fts as $ft)
            $text=$this->postfilter_repl($ft,$text,$options);
        }
	$this->postambles();
        print $this->get_javascripts();
        print $text;

        if (!empty($DBInfo->use_tagging) and isset($pi['#keywords'])) {
          $tmp="----\n";
          if (is_string($DBInfo->use_tagging))
            $tmp.=$DBInfo->use_tagging;
          else
            $tmp.=_("Tags:")." [[Keywords]]";
          $this->send_page($tmp); // XXX
        }
        //$this->store_pagelinks(); // XXX
        return;
      }

      if (!empty($body)) {
        $body=rtrim($body); # delete last empty line
        $lines=explode("\n",$body);
      } else
        $lines=array();

      if (!empty($DBInfo->use_tagging) and isset($pi['#keywords'])) {
        $lines[]="----";
        if (is_string($DBInfo->use_tagging))
          $lines[]=$DBInfo->use_tagging;
        else
          $lines[]="Tags: [[Keywords]]";
      }

      $twin_mode=$DBInfo->use_twinpages;
      if (isset($pi['#twinpages'])) $twin_mode=$pi['#twinpages'];
      $twins=$DBInfo->metadb->getTwinPages($this->page->name,$twin_mode);

      if ($twins === true) {
        if (isset($DBInfo->interwiki['TwinPages'])) {
          if (!empty($lines)) $lines[]="----";
          $lines[]=sprintf(_("See %s"),"[wiki:TwinPages:".$this->page->name." "._("TwinPages")."]");
        }
      } else if (!empty($twins)) {
        if (!empty($lines)) $lines[]="----";
        if (sizeof($twins)>8) $twins[0]="\n".$twins[0]; // XXX
        $twins[0]=_("See [TwinPages]:").$twins[0];
        $lines=array_merge($lines,$twins);
      }
    }

    # have no contents
    if (empty($lines)) return;

    # for headings
    if (isset($options['notoc'])) {
      $headinfo = null;
    } else {
      $headinfo['top'] = 0;
      $headinfo['num'] = 1;
      $headinfo['dep'] = 0;
    }

    $text='';
    $in_p='';
    $in_div=0;
    $in_li=0;
    $in_pre=0;
    $in_quote=0;
    $in_table=0;
    $li_open=0;
    $li_empty=0;
    $div_enclose='';
    $my_div=0;
    $indent_list[0]=0;
    $indent_type[0]="";
    $_myindlen=array(0);
    $oline='';

    $wordrule="(?:{{{(?U)(?:.+)}}})|".
              "\[\[(?:[A-Za-z0-9]+(?:\((?:(?<!\]\]).)*\))?)\]\]|". # macro
              "<<(?:[A-Za-z0-9]+(?:\((?:(?<!\>\>).)*\))?)>>|"; # macro
    if ($DBInfo->inline_latex) # single line latex syntax
      $wordrule.="(?<=\s|^|>)\\$(?!(?:Id|Revision))(?:[^\\$]+)\\$(?=\s|\.|\,|$)|".
                 "(?<=\s|^|>)\\$\\$(?:[^\\$]+)\\$\\$(?=\s|$)|";
    #if ($DBInfo->builtin_footnote) # builtin footnote support
    $wordrule.=$this->footrule.'|';
    $wordrule.=$this->wordrule;

    $formatter=&$this;

    foreach ($lines as $line) {
      # empty line
      if (!strlen($line) and empty($oline)) {
        if ($in_pre) { $this->pre_line.="\n";continue;}
        if ($in_li) {
          if ($in_table) {
            $text.=$this->_table(0,$dumm);$in_table=0;$li_empty=1;
          }
          $text.=$this->_purple()."<br />\n";
          if ($li_empty==0 && !$this->auto_linebreak ) $text.="<br />\n";
          $li_empty=1;
          continue;
        }
        if ($in_table) {
          $text.=$this->_table(0,$dumm)."<br />\n";$in_table=0; continue;
        } else {
          #if ($in_p) { $text.="</div><br />\n"; $in_p='';}
          if ($in_p) { $text.=$this->_div(0,$in_div,$div_enclose)."<br />\n"; $in_p='';}
          else if ($in_p=='') { $text.="<br />\n";}
          continue;
        }
      }

      if (!$in_pre and $line[0]=='#' and $line[1]=='#') {
        $out='';
        if ($line[2]=='[') {
          $macro=substr($line,4,-2);
          $out= $this->macro_repl($macro,'',array('nomarkup'=>1));
        } else if ($line[2]=='#') {
          $div_enclose.='<div id="'.substr($line,3).'">';
          $my_div++;
        } else if ($line[2]=='.') {
          $div_enclose.='<div class="'.substr($line,3).'">';
          $my_div++;
        } else if ($my_div>0) {
          $div_enclose.='</div>';
          $my_div--;
        }

        if ($this->wikimarkup) {
          $out=$out ? $out:$line.'<br />';
          $nline=str_replace(array('=','-','&','<'),array('==','-=','&amp;','&lt;'),$line);
          $text=$text."<span class='wikiMarkup'><!-- wiki:\n$nline\n\n-->$out</span>";
        }
        else $text.=$out;
        unset($out);
        continue; # comments
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
      #} else if (preg_match("/(\{{2,3})[^{}]*$/",$line,$m)) {
      } else if (!(strpos($line,"{{{")===false) and 
                 preg_match("/{{{[^{}]*$/",$line)) {

         #$p=strrpos($line,"{{{")-2;
         #$p= strlen($line) - strpos(strrev($line),$m[1]) - strlen($m[1]);
         $p= strlen($line) - strpos(strrev($line),'{{{') - 3;

         $processor="";
         $in_pre=1;
         $np=0;

         # check processor
         $t = isset($line{$p+3});
         if ($t and $line[$p+3] == "#" and $line[$p+4] == "!") {
            list($tag,$dummy)=explode(" ",substr($line,$p+5),2);

            if (function_exists("processor_".$tag)) {
              $processor=$tag;
            } else if ($pf=getProcessor($tag)) {
              if (!function_exists("processor_".$pf))
                include_once("plugin/processor/$pf.php");
              $processor=$pf;
            }
         } else if ($t and $line[$p+3] == ":") {
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
         if (!$line and $this->auto_linebreak) $this->nobr=1;
      }

      $ll=strlen($line);
      if ($line[$ll-1]=='&') {
        $oline.=substr($line,0,-1)."\n";
        continue;
      } else if (empty($oline) and preg_match('/^\s*\|\|/',$line) and !preg_match('/\|(\||-+)\s*$/',$line)) {
        $oline.=$line."\n";
        continue;
      } else if (!empty($oline) and ($in_table or preg_match('/^\s*\|\|/',$oline)) and !preg_match('/\|(\||-+)\s*$/',$line)) {
        $oline.=$line."\n";
        continue;
      } else {
        $line=$oline.$line;
        $oline='';
      }

      $p_closeopen='';
      if (preg_match('/^[ ]*-{4,}$/',$line)) {
        if ($this->auto_linebreak) $this->nobr=1; // XXX
        if ($in_p) { $p_closeopen=$this->_div(0,$in_div,$div_enclose); $in_p='';}
      } else if ($in_p == '' and $line!=='') {
        $p_closeopen=$this->_div(1,$in_div,$div_enclose);
        $in_p= $line;
      }

      // split into chunks
      $chunk=preg_split('/({{{.+}}})/U',$line,-1,PREG_SPLIT_DELIM_CAPTURE);
      $nc='';
      $k=1;
      foreach ($chunk as $c) {
        if ($k%2) {
          $nc.=preg_replace($this->baserule,$this->baserepl,$c);
        } else if (in_array($c[3],array('#','-','+'))) { # {{{#color text}}}
          $nc.=preg_replace($this->baserule,$this->baserepl,$c);
        } else $nc.=$c;
        $k++;
      }
      $line=$nc;
      #$line=preg_replace($this->baserule,$this->baserepl,$line);
      #if ($in_p and ($in_pre==1 or $in_li)) $line=$this->_check_p().$line;

      # bullet and indentation
      # and quote begin with ">"
      if ($in_pre != -1 &&
        preg_match("/^(((>\s)*>(?!>))|(\s*>*))/",$line,$match)) {
      #if (preg_match("/^(\s*)/",$line,$match)) {
         #print "{".$match[1].'}';
         $open="";
         $close="";
         $indtype="dd";
         $indlen=strlen($match[0]);
         $line=substr($line,$indlen);
         $liopen='';
         if ($indlen > 0) {
           $myindlen=$indlen;
           # check div type.
           $mydiv=array('indent');
           if ($match[0][$indlen-1]=='>') {
             # get user defined style
             if (($line[0]=='.' or $line[0]=='#') and ($p=strpos($line,' '))) {
               $divtype='';
               $mytag=substr($line,1,$p-1);
               if ($line[0]=='.') $mydiv[]=$mytag;
               else $divtype=' id="'.$mytag.'"';
               $divtype.=' class="quote '.implode(' ',$mydiv).'"';
               $line=substr($line,$p+1);
             } else {
               if ($line[0] == ' ')
                 $line=substr($line,1); // with space
               $divtype=' class="quote indent '.$this->quote_style.'"';
             }
           } else {
             $divtype=' class="indent"';
           }

           if ($line[0]=='*') {
             $limatch[1]='*';
             $myindlen=($line{1}==' ') ? $indlen+2:$indlen+1;
             preg_match("/^(\*\s?)/",$line,$m);
             $liopen='<li>'; // XXX
             $line=substr($line,strlen($m[1]));
             if ($indent_list[$in_li] == $indlen && $indent_type[$in_li]!='dd'){
                $close.=$this->_li(0);
                $_myindlen[$in_li]=$myindlen;
             }
             $numtype="";
             $indtype="ul";
           } elseif (preg_match("/^(([1-9]\d*|[aAiI])\.)(#\d+)?\s/",$line,$limatch)){
             $myindlen=$indlen+strlen($limatch[1])+1;
             $line=substr($line,strlen($limatch[0]));
             if ($indent_list[$in_li] == $indlen) {
                $close.=$this->_li(0);
                $_myindlen[$in_li]=$myindlen;
             }
             $numtype=$limatch[2][0];
             if ($limatch[3])
               $numtype.=substr($limatch[3],1);
             $indtype="ol";
             $lival='';
             if ($in_li and $limatch[3])
               $lival=' value="'.substr($limatch[3],1).'"';
             $liopen="<li$lival>"; // XXX
           } elseif (preg_match("/^([^:]+)::\s/",$line,$limatch)) {
             $myindlen=$indlen;
             $line=preg_replace("/^[^:]+::\s/",
                     "<dt class='wiki'>".$limatch[1]."</dt><dd>",$line);
             if ($indent_list[$in_li] == $indlen) $line="</dd>\n".$line;
             $numtype="";
             $indtype="dl";
           } else if ($_myindlen[$in_li] == $indlen) {
             $indlen=$indent_list[$in_li]; // XXX
           }
         }
         if ($indent_list[$in_li] < $indlen) {
            $in_li++;
            $indent_list[$in_li]=$indlen; # add list depth
            $_myindlen[$in_li]=$myindlen; # add list depth
            $indent_type[$in_li]=$indtype; # add list type
            $open.=$this->_list(1,$indtype,$numtype,'',$divtype);
         } else if ($indent_list[$in_li] > $indlen) {
            while($in_li >= 0 && $indent_list[$in_li] > $indlen) {
               if ($indent_type[$in_li]!='dd' && $li_open == $in_li)
                 $close.=$this->_li(0,$li_empty);
               $close.=$this->_list(0,$indent_type[$in_li],"",
                 $indent_type[$in_li-1]);
               unset($indent_list[$in_li]);
               unset($indent_type[$in_li]);
               unset($_myindlen[$in_li]);
               $in_li--;
            }
            #$li_empty=0;
         }
         if ($liopen) $open.=$liopen;
         $li_empty=0;
         if ($indent_list[$in_li] <= $indlen || $limatch) $li_open=$in_li;
         else $li_open=0;
      }

      #if (!$in_pre && !$in_table && preg_match("/^\|\|.*\|\|$/",$line)) {
      if (!$in_pre && $line[0]=='|' && !$in_table && preg_match("/^(\|([^\|]+)?\|((\|\|)*))((?:&lt;[^>\|]*>)*)(.*)$/s",$line,$match)) {
        $open.=$this->_table(1,$match[5]);
        if (!empty($match[2])) $open.='<caption>'.$match[2].'</caption>';
        $line='||'.$match[3].$match[5].$match[6];
        $in_table=1;
        if ($this->use_etable && !preg_match('/\|(\||-+)$/',$match[6])) {
          $text.=$open;
          $this->table_line.=substr($line,2)."\n";
          continue;
        }
      } elseif ($in_table && ($line[0]!='|' or
              !preg_match("/^\|{2}.*(?:\|(\||-+))$/s",$line))) {
        if ($this->use_etable && $in_table && preg_match('/^\|\|/',$line)) {
          $this->table_line.=substr($line,2)."\n";
          continue;
        }
        $close=$this->_table(0,$dumm).$close;
        $in_table=0;
      }
      while ($in_table) {
        $line=preg_replace('/(\|\||\|-+)$/','',$line);
        if ($this->use_etable && $this->table_line) {
          $nline='||'.$this->table_line;
          $this->table_line='';
          if (!preg_match('/^\|+$/',$line)) $nline.=$line;
 
          $row=$this->_td($nline,$tr_attr);
          if (!$this->in_tr) {
            $this->in_tr=1;
            $nline="<tr $tr_attr>".$row;
            $tr_attr='';
          } else {
            $nline=$row;
          }
          if (preg_match('/^\|{3,}$/',$line)) {

            $nline.='</tr>';
            $this->in_tr=0;
          }
          $line=$nline;
        } else {
          $tr_attr='';
          $row=$this->_td($line,$tr_attr);
          $line="<tr $tr_attr>".$row.'</tr>';
          $tr_attr='';
        }

        $line=str_replace('\"','"',$line); # revert \\" to \"
        break;
      }
      $tline='';
      if ($this->use_etable && !$in_table && $this->table_line) {
        $row=$this->_td('||'.$this->table_line,$tr_attr);
          if (!$this->in_tr) {
            $tline="<tr $tr_attr>";
            $tr_attr='';
          }
          $tline.=$row.'</tr>';
          $this->in_tr=0;
          $this->table_line='';
          $tline=str_replace('\"','"',$tline); # revert \\" to \"
          $tline=preg_replace_callback("/(".$wordrule.")/",
            array(&$this,'link_repl'),$tline);
      }

      # InterWiki, WikiName, {{{ }}}, !WikiName, ?single, ["extended wiki name"]
      # urls, [single bracket name], [urls text], [[macro]]

      $line=preg_replace_callback("/(".$wordrule.")/",
        array(&$this,'link_repl'),$line);
      #$line=preg_replace("/(".$wordrule.")/e","\$this->link_repl('\\1')",$line);

      # Headings
      if (preg_match("/(?<!=)(={1,5})\s+(.*)\s+\\1\s?$/",$line,$m)) {
        $this->sect_num++;
        if ($p_closeopen) { // ignore last open
          $p_closeopen='';
          $this->_div(0,$in_div,$div_enclose);
        }

        while($in_div > 0)
          $p_closeopen.=$this->_div(0,$in_div,$div_enclose);
        $p_closeopen.=$this->_div(1,$in_div,$div_enclose);
        $in_p='';
        if ($this->section_edit && !$this->preview) {
          $act='edit';

          $wikiwyg_mode='';
          if ($DBInfo->use_wikiwyg ==1) {
            $wikiwyg_mode=',true';
          }
          if ($DBInfo->sectionedit_attr) {
            if (!is_string($DBInfo->sectionedit_attr))
              $sect_attr=' onclick="javascript:sectionEdit(null,this,'.
                $this->sect_num.$wikiwyg_mode.');return false;"';
            else
              $sect_attr=$DBInfo->sectionedit_attr;
          }
          $url=$this->link_url($this->page->urlname,
            '?action='.$act.'&amp;section='.$this->sect_num);
          $lab=_("edit");
          $edit="<div class='sectionEdit' style='float:right;'><span class='sep'>[</span><span><a href='$url'$sect_attr><span>$lab</span></a></span><span class='sep'>]</span></div>\n";
          $anchor_id='sect-'.$this->sect_num;
          $anchor="<a id='$anchor_id'></a>";
        }
        $attr='';
        if ($DBInfo->use_folding) {
          if ($DBInfo->use_folding == 1) {
            $attr=" onclick=\"document.getElementById('sc-$this->sect_num').style.display=document.getElementById('sc-$this->sect_num').style.display!='none'? 'none':'block';\"";
          } else {
            $attr=" onclick=\"foldingSection(this,'sc-$this->sect_num');\"";
          }
        }

        $line=$anchor.$edit.$this->head_repl(strlen($m[1]),$m[2],$headinfo,$attr);
        $dummy='';
        $line.=$this->_div(1,$in_div,$dummy,' id="sc-'.$this->sect_num.'"'); // for folding
        $edit='';$anchor='';
      }

      # Smiley
      if ($this->smiley_rule)
        $line=preg_replace($this->smiley_rule,$this->smiley_repl,$line);
      # NoSmoke's MultiLineCell hack
      #$line=preg_replace(array("/{{\|/","/\|}}/"),
      #      array("</div><table class='closure'><tr class='closure'><td class='closure'><div>","</div></td></tr></table><div>"),$line);

      if ($this->auto_linebreak and in_array(trim($line),array('{{|','|}}')))
        $this->nobr=1;
      $line=preg_replace($this->extrarule,$this->extrarepl,$line);
      #if ($this->auto_linebreak and preg_match('/<div>$/',$line))
      #  $this->nobr=1;

      $line=$tline.$close.$p_closeopen.$open.$line;
      $tline='';
      $open="";$close="";

      if ($in_pre==-1) {
         $in_pre=0;

         # for smart diff
         $show_raw=0;
         if ($this->use_smartdiff and
           preg_match("/\006|\010/", $this->pre_line)) $show_raw=1;

         if ($processor and !$show_raw) {
           $value=&$this->pre_line;
           $out= $this->processor_repl($processor,$value,$options);
           #if ($this->wikimarkup)
           #  $line='<div class="wikiMarkup">'."<!-- wiki:\n{{{".
           #    $value."}}}\n-->$out</div>";
           #else
           #  $line=$out.$line;
           $line=$out.$line;
           unset($out);
         } else if ($in_quote) {
            # htmlfy '<'
            $pre=str_replace("<","&lt;",$this->pre_line);
            $pre=preg_replace($this->baserule,$this->baserepl,$pre);
            $pre=preg_replace_callback("/(".$wordrule.")/",
              array(&$this,'link_repl'),$pre);
            #$pre=preg_replace("/(".$wordrule.")/e","\$this->link_repl('\\1')",$pre);
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
            $out="<pre $attr>\n".$pre."</pre>\n";
            if ($this->wikimarkup) {
              $nline=str_replace(array('=','-','&','<'),array('==','-=','&amp;','&lt;'),$this->pre_line);
              $out='<span class="wikiMarkup">'."<!-- wiki:\n{{{:$pre_style\n".
                str_replace('}}}','\}}}',$nline).
                "}}}\n-->".$out."</span>";
            }
            $line=$out.$line;
            $in_quote=0;
         } else {
            # htmlfy '<', '&'
            if (!empty($DBInfo->default_pre)) {
              $out=$this->processor_repl($DBInfo->default_pre,$this->pre_line,$options);
            } else {
              $pre=str_replace(array('&','<'),
                               array("&amp;","&lt;"),
                               $this->pre_line);
              $pre=preg_replace("/&lt;(\/?)(ins|del)/","<\\1\\2",$pre);
              # FIXME Check open/close tags in $pre
              #$out="<pre class='wiki'>\n".$pre."</pre>";
              $out="<pre class='wiki'>".$pre."</pre>";
              if ($this->wikimarkup) {
                $nline=str_replace(array('=','-','&','<'),array('==','-=','&amp;','&lt;'),$this->pre_line);
                $out='<span class="wikiMarkup">'."<!-- wiki:\n{{{\n".
                  str_replace('}}}','\}}}',$nline).
                  "}}}\n-->".$out."</span>";
              }
            }
            $line=$out.$line;
            unset($out);
         }
         $this->nobr=1;
      }
      if ($this->auto_linebreak && !$in_table && !$this->nobr)
        $text.=$line."<br />\n"; 
      else
        $text.=$line ? $line."\n":'';
      $this->nobr=0;
      # empty line for quoted div
      if (!$this->auto_linebreak and !$in_pre and trim($line) =='')
        $text.="<br />\n";

    } # end rendering loop
    # for smart_diff (div)
    if ($this->use_smartdiff)
      $text= preg_replace_callback(array("/(\006|\010)(.*)\\1/sU"),
          array(&$this,'_diff_repl'),$text);

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
    $fts=array();
    if (!empty($pi['#postfilter'])) $fts=preg_split('/(\||,)/',$pi['#postfilter']);
    if (!empty($this->postfilters)) $fts=array_merge($fts,$this->postfilters);
    if ($fts) {
      foreach ($fts as $ft)
        $text=$this->postfilter_repl($ft,$text,$options);
    }

    # close all tags
    $close="";
    # close pre,table
    if ($in_pre) $close.="</pre>\n";
    if ($in_table) $close.="</table>\n";
    # close indent
    while($in_li >= 0 && $indent_list[$in_li] > 0) {
      if ($indent_type[$in_li]!='dl' && $li_open == $in_li) // XXX
        $close.=$this->_li(0);
#     $close.=$this->_list(0,$indent_type[$in_li]);
      $close.=$this->_list(0,$indent_type[$in_li],"",$indent_type[$in_li-1]);
      unset($indent_list[$in_li]);
      unset($indent_type[$in_li]);
      $in_li--;
    }
    # close div
    #if ($in_p) $close.="</div>\n"; # </para>
    if ($in_p) $close.=$this->_div(0,$in_div,$div_enclose); # </para>
    #if ($div_enclose) $close.=$this->_div(0,$in_div,$div_enclose);
    while ($my_div>0) { $close.="</div>\n"; $my_div--;}
    while($in_div > 0)
      $close.=$this->_div(0,$in_div,$div_enclose);

    # activate <del></del> tag
    #$text=preg_replace("/(&lt;)(\/?del>)/i","<\\2",$text);
    $text.=$close;
  
    # postamble
    $this->postambles();

    print $this->get_javascripts();
    print $text;
    if ($this->sisters and !$options['nosisters']) {
      $sister_save=$this->sister_on;
      $this->sister_on=0;
      $sisters=join("\n",$this->sisters);
      $sisters=preg_replace("/(".$wordrule.")/e","\$this->link_repl('\\1')",$sisters);
      $msg=_("Sister Sites Index");
      print "<div id='wikiSister'>\n<div class='separator'><tt class='foot'>----</tt></div>\n$msg<br />\n<ul>$sisters</ul></div>\n";
      $this->sister_on=$sister_save;
    }

    if ($this->foots)
      print $this->macro_repl('FootNote','',$options);

    if (!empty($this->update_pagelinks) and !empty($options['pagelinks'])) $this->store_pagelinks();
  }

  function register_javascripts($js) {
    if (is_array($js)) {
      foreach ($js as $j) $this->register_javascripts($j);
      return true;
    } else {
      if ($js{0} == '<') { $tag=md5($js); }
      else $tag=$js;
      if (!isset($this->java_scripts[$tag]))
        $this->java_scripts[$tag]=$js;
      else return false;
      return true;
    }
  }

  function get_javascripts() {
    global $Config;
    if (!empty($Config['use_jspacker']) and !empty($Config['cache_public_dir'])) {
      include_once('lib/fckpacker.php'); # good but not work with prototype.
      define ('JS_PACKER','FCK_Packer/MoniWiki');
      $constProc = new FCKConstantProcessor();
      #$constProc->RemoveDeclaration = false ;
      #include_once('lib/jspacker.php'); # bad!
      #$packer = new JavaScriptPacker('', 0);
      #$packer->pack(); // init compressor
      #include_once('lib/jsmin.php'); # not work.
      
      $out='';
      $packed='';
      $pjs = array();
      $keys = array();
      foreach ($this->java_scripts as $k=>$js)
        if (!empty($js)) $keys[]=$k;

      if (empty($keys)) return '';
      $uniq = md5(implode(';',$keys));
      $cache=new Cache_text('js',2,'html');

      if ($cache->exists($uniq)) {
        foreach ($keys as $k) $this->java_scripts[$k]='';
        return $cache->fetch($uniq);
      }

      foreach ($this->java_scripts as $k=>$js) {
        if ($js) {
          if ($js{0} != '<') {
            if (preg_match('@^(http://|/)@',$js)) {
              $out.="<script type='text/javascript' src='$js'></script>\n";
            } else {
              if (file_exists('local/'.$js)) {
                $fp = fopen('local/'.$js,'r');
                if (is_resource($fp)) {
                  $_js = fread($fp,filesize('local/'.$js));
                  fclose($fp);
                  $packed.='/* '.$js.' */'."\n";
                  #$packed.= JSMin::minify($_js);
                  $packed.= FCKJavaScriptCompressor::Compress($_js, $constProc)."\n";
                  #$packed.= $packer->_pack($_js)."\n";
                  $pjs[]=$k;
                }
              } else { // is it exist ?
                $js=$this->url_prefix.'/local/'.$js;
                $out.="<script type='text/javascript' src='$js'></script>\n";
              }
            }
          } else { //
            $out.=$js;
            if ( 0 and preg_match('/<script[^>]+(src=("|\')([^\\2]+)\\2)?[^>]*>(.*)<\/script>\s*$/s',$js,$m)) {
              if (!empty($m[3])) {
                $out.=$js;
                #$out.="<script type='text/javascript' src='$js'></script>\n";
              } else if (!empty($m[4])) {
                $packed.='/* embeded '.$k.'*/'."\n";
                #$packed.= $packer->_pack($js)."\n";
                $packed.= FCKJavaScriptCompressor::Compress($m[4], $constProc)."\n";
                #$packed.= JSMin::minify($js);
                $pjs[]=$k;
              }
            }
          }
          $this->java_scripts[$k]='';

        }
      }
      $suniq = md5(implode(';',$pjs));

      $fc = new Cache_text('js',2,'js',$Config['cache_public_dir']);
      $jsname = $fc->_getKey($suniq,0);
      $out.='<script type="text/javascript" src="'.$Config['cache_public_url'].'/'.$jsname.'"></script>'."\n";
      $cache->update($uniq,$out);

      $ver = FCKJavaScriptCompressor::Revision();
      $header='/* '.JS_PACKER.' '.$ver.' '.md5($packed).' '.date('Y-m-d H:i:s').' */'."\n";
      # save real compressed js file.
      $fc->_save($Config['cache_public_dir'].'/'.$jsname,$header.$packed);
      return $out;
    }
    $out='';
    foreach ($this->java_scripts as $k=>$js) {
      if ($js) {
        if ($js{0} != '<') {
          if (!preg_match('@^(http://|/)@',$js))
            $js=$this->url_prefix.'/local/'.$js;
          $out.="<script type='text/javascript' src='$js'></script>\n";
        } else {
          $out.=$js;
        }
        $this->java_scripts[$k]='';
      }
    }
    return $out;
  }

  function get_merge($text,$rev="") {
    global $DBInfo;

    if (!$text) return '';
    # recall old rev
    $opts['rev']=$this->page->get_rev();
    $orig=$this->page->get_raw_body($opts);

    if (!empty($DBInfo->use_external_merge)) {
      # save new
      $tmpf3=tempnam($DBInfo->vartmp_dir,'MERGE_NEW');
      $fp= fopen($tmpf3, 'w');
      fwrite($fp, $text);
      fclose($fp);

      $tmpf2=tempnam($DBInfo->vartmp_dir,'MERGE_ORG');
      $fp= fopen($tmpf2, 'w');
      fwrite($fp, $orig);
      fclose($fp);

      $fp=popen("merge -p ".$this->page->filename." $tmpf2 $tmpf3".$this->NULL,'r');

      if (!is_resource($fp)) {
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
        explode("\n",$current),explode("\n",$text));
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
    $header = !empty($header) ? $header:(!empty($options['header']) ? $options['header']:null) ;
    #print_r($header);
    #$this->header("Expires: Tue, 01 Jan 2002 00:00:00 GMT");
    if (!empty($header)) {
      if (is_array($header))
        foreach ($header as $head) {
          $this->header($head);
          if (preg_match("/^content\-type: text\//i",$head))
            $plain=1;
        }
      else {
        $this->header($header);
        if (preg_match("/^content\-type: text\//i",$header))
          $plain=1;
      }
    }
    $content_type=
      $DBInfo->content_type ? $DBInfo->content_type: "text/html";

    if ($DBInfo->force_charset)
      $force_charset = '; charset='.$DBInfo->charset;

    if (!$plain)
      $this->header('Content-type: '.$content_type.$force_charset);
#    if (!$plain)
#      $this->header('Content-type: '.$content_type);

    if ($options['action_mode']=='ajax') return;

    if (isset($this->pi['#noindex'])) {
      $metatags='<meta name="robots" content="noindex,nofollow" />'."\n";
    } else {
      if ($options['metatags'])
        $metatags=$options['metatags'];
      else {
        $metatags=$DBInfo->metatags;
      }

      $mtime=$this->page->mtime(); // delay indexing from dokuwiki
      if ($DBInfo->delayindex and ((time() - $mtime) < $DBInfo->delayindex)) {
        if (preg_match("/<meta\s+name=('|\")?robots\\1[^>]+>/i",
          $metatags)) {
          $metatags=preg_replace("/<meta\s+name=('|\")?robots\\1[^>]+>/i",
            '<meta name="Robots" content="noindex,nofollow" />',
            $metatags);
        } else {
          $metatags.='<meta name="robots" content="noindex,nofollow" />'."\n";
        }
      }
    }

    $js=$DBInfo->js;

    if (!$plain) {
      if (isset($options['trail']))
        $this->set_trailer($options['trail'],$this->page->name);
      else if ($DBInfo->origin)
        $this->set_origin($this->page->name);

      # find upper page
      $pos=0;
      preg_match('/(\:|\/)/',$this->page->name,$sep); # NameSpace/SubPage or NameSpace:SubNameSpacePage
      if ($sep[1]) $pos=strrpos($this->page->name,$sep);
      if ($pos > 0) $upper=substr($this->page->urlname,0,$pos);
      else if ($this->group) $upper=_urlencode(substr($this->page->name,strlen($this->group)));
      if ($this->pi['#keywords'])
        $keywords='<meta name="keywords" content="'.$this->pi['#keywords'].'" />'."\n";
      else if ($DBInfo->use_keywords) {
        $keywords=strip_tags($this->page->title);
        $keywords=str_replace(" ",", ",$keywords); # XXX
        $keywords=htmlspecialchars($keywords);
        $keywords="<meta name=\"keywords\" content=\"$keywords\" />\n";
      }
      # find sub pages
      if ($DBInfo->use_subindex and !$options['action']) {
        $scache=new Cache_text('subpages');
        if (!($subs=$scache->exists($this->page->name))) {
          if (($p = strrpos($this->page->name,'/')) !== false)
            $rule=_preg_search_escape(substr($this->page->name,0,$p));
          else
            $rule=_preg_search_escape($this->page->name);
          $subs=$DBInfo->getLikePages('^'.$rule.'\/',1);
          if ($subs) $scache->update($this->page->name,1);
        }
        if (!empty($subs)) {
          $subindices='';
          if (!$DBInfo->use_ajax) {
            $subindices= '<div>'.$this->macro_repl('PageList','',array('subdir'=>1)).'</div>';
            $btncls='class="close"';
          } else
            $btncls='';
          $this->subindex="<fieldset id='wikiSubIndex'>".
            "<legend title='[+]' $btncls onclick='javascript:toggleSubIndex(\"wikiSubIndex\")'></legend>".
            $subindices."</fieldset>\n";
        }
      }

      if (empty($options['title'])) {
        $options['title']=$this->pi['#title'] ? $this->pi['#title']:
          $this->page->title;
        $options['title']=
          htmlspecialchars($options['title']);
      }
      if (empty($options['css_url'])) $options['css_url']=$DBInfo->css_url;
      if (!$this->pi['#nodtd']) print $DBInfo->doctype;
      print "<head>\n";

      print '<meta http-equiv="Content-Type" content="'.$content_type.
        ';charset='.$DBInfo->charset."\" />\n";
      print <<<JSHEAD
<script type="text/javascript">
/*<![CDATA[*/
_url_prefix="$DBInfo->url_prefix";
/*]]>*/
</script>
JSHEAD;
      print $metatags.$js."\n";
      print $this->get_javascripts();
      print $keywords;
      print "  <title>$DBInfo->sitename: ".$options['title']."</title>\n";
      if ($upper)
        print '  <link rel="Up" href="'.$this->link_url($upper)."\" />\n";
      $raw_url=$this->link_url($this->page->urlname,"?action=raw");
      $print_url=$this->link_url($this->page->urlname,"?action=print");
      print '  <link rel="Alternate" title="Wiki Markup" href="'.
        $raw_url."\" />\n";
      print '  <link rel="Alternate" media="print" title="Print View" href="'.
        $print_url."\" />\n";
      if ($options['css_url']) {
        print '  <link rel="stylesheet" type="text/css" '.$media.' href="'.
          $options['css_url']."\" />\n";
        if (file_exists('./css/_user.css'))
          print '  <link rel="stylesheet" media="screen" type="text/css" href="'.
            $DBInfo->url_prefix."/css/_user.css\" />\n";
# default CSS
      } else print <<<EOS
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

    }
  }

  function get_actions($args='',$options) {
    $menu=array();
    if ($this->pi['#action'] && !in_array($this->pi['#action'],$this->actions)){
      list($act,$txt)=explode(" ",$this->pi['#action'],2);
      if (!$txt) $txt=$act;
      $menu[]= $this->link_to("?action=$act",_($txt),"accesskey='x'");
      if (strtolower($act) == 'blog')
        $this->actions[]='BlogRss';
        
    } else if ($args['editable']) {
      if ($args['editable']==1)
        $menu[]= $this->link_to("?action=edit",_("EditText"),"accesskey='x'");
      else
        $menu[]= _("NotEditable");
      if ($args['refresh']==1)
        $menu[]= $this->link_to("?refresh=1",_("Refresh"),"accesskey='n'");
    } else
      $menu[]= $this->link_to('?action=show',_("ShowPage"));
    $menu[]=$this->link_tag("FindPage","",_("FindPage"));

    if (!$args['noaction']) {
      foreach ($this->actions as $action) {
        if (strpos($action,' ')) {
          list($act,$text)=explode(' ',$action,2);
          if ($options['page'] == $this->page->name) {
            $menu[]= $this->link_to($act,_($text));
          } else {
            $menu[]= $this->link_tag($options['page'],$act,_($text));
          }
        } else {
          $menu[]= $this->link_to("?action=$action",_($action));
        }
      }
    }
    return $menu;
  }

  function send_footer($args='',$options='') {
    global $DBInfo;

    if ($options['action_mode']=='ajax') return;

    print "<!-- wikiBody --></div>\n";
    print $DBInfo->hr;
    if ($args['editable'] and !$DBInfo->security->writable($options))
      $args['editable']=-1;

    $key=$DBInfo->pageToKeyname($options['page']);
    if (!in_array('UploadedFiles',$this->actions) and is_dir($DBInfo->upload_dir."/$key"))
      $this->actions[]='UploadedFiles';

    $menus=$this->get_actions($args,$options);

    $hide_actions= $this->popup + $DBInfo->hide_actions;
    $menu = '';
    if (!$hide_actions or
      ($hide_actions and $options['id']!='Anonymous')) {
      if (!$this->css_friendly) {
        $menu=$this->menu_bra.implode($this->menu_sep,$menus).$this->menu_cat;
      } else {
        $menu="<div id='wikiAction'>";
        $menu.='<ul><li>'.implode("</li>\n<li>\n",$menus)."</li></ul>";
        $menu.="</div>";
      }
    }

    if ($mtime=$this->page->mtime()) {
      $lastedit=gmdate("Y-m-d",$mtime+$options['tz_offset']);
      $lasttime=gmdate("H:i:s",$mtime+$options['tz_offset']);
    }

    $validator_xhtml=$DBInfo->validator_xhtml ? $DBInfo->validator_xhtml:'http://validator.w3.org/check/referer';
    $validator_css=$DBInfo->validator_css ? $DBInfo->validator_xhtml:'http://jigsaw.w3.org/css-validator';

    $banner= <<<FOOT
 <a href="$validator_xhtml"><img
  src="$this->imgs_dir/valid-xhtml10.png"
  style="border:0;vertical-align:middle" width="88" height="31"
  alt="Valid XHTML 1.0!" /></a>

 <a href="$validator_css"><img
  src="$this->imgs_dir/vcss.png" 
  style="border:0;vertical-align:middle" width="88" height="31"
  alt="Valid CSS!" /></a>

 <a href="http://moniwiki.sourceforge.net/"><img
  src="$this->imgs_dir/moniwiki-powered.png" 
  style="border:0;vertical-align:middle" width="88" height="31"
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
      print $menu;
      if (!$this->css_friendly) print $banner;
      else print "<div id='wikiBanner'>$banner</div>\n";
      print "\n</div>\n";
    }
    print "</body>\n</html>\n";
    #include "prof_results.php";
  }

  function send_title($msgtitle="", $link="", $options="") {
    // Generate and output the top part of the HTML page.
    global $DBInfo;

    if ($options['action_mode']=='ajax') return;

    $name=$this->page->urlname;
    $action=$this->link_url($name);
    $saved_pagelinks = $this->pagelinks;

    # find upper page
    $pos=0;
    preg_match('/(\:|\/)/',$name,$sep); # NameSpace/SubPage or NameSpace:SubNameSpacePage
    if ($sep[1]) $pos=strrpos($name,$sep[1]);
    $mypgname=$this->page->name;
    if ($pos > 0) {
      $upper=substr($name,0,$pos);
      $upper_icon=$this->link_tag($upper,'',$this->icon['upper'])." ";
    } else if ($this->group) {
      $group=$this->group;
      $mypgname=substr($this->page->name,strlen($group));
      $upper=_urlencode($mypgname);
      $upper_icon=$this->link_tag($upper,'',$this->icon['main'])." ";
    }

    $title=htmlspecialchars($this->pi['#title']);
    if (!empty($msgtitle)) {
      $msgtitle = htmlspecialchars($msgtitle);
    } else if (isset($options['msgtitle'])) {
      $msgtitle = $options['msgtitle'];
    }

    if (!$msgtitle) $msgtitle=$options['title'];
    if (!$title) {
      if ($group) { # for UserNameSpace
        $title=$mypgname;
        $groupt=substr($group,0,-1).' &raquo;';
        $groupt=
          "<span class='wikiGroup'>$groupt</span>";
      } else     
        $title=$this->page->title;
      $title=htmlspecialchars($title);
    }
    # setup title variables
    #$heading=$this->link_to("?action=fullsearch&amp;value="._urlencode($name),$title);
    if ($DBInfo->use_backlinks) $qext='&amp;backlinks=1';
    if ($link)
      $title="<a href=\"$link\">$title</a>";
    else if (empty($options['nolink']))
      $title=$this->link_to("?action=fullsearch$qext&amp;value="._urlencode($mypgname),$title);

    $title="$groupt<span class='wikiTitle'>$title</span>";
    #$title="<span class='wikiTitle'><b>$title</b></span>";

    $logo=$this->link_tag($DBInfo->logo_page,'',$DBInfo->logo_string);
    $goto_form=$DBInfo->goto_form ?
      $DBInfo->goto_form : goto_form($action,$DBInfo->goto_type);

    if ($options['msg'] or $msgtitle) {
      $msgtype = isset($options['msgtype']) ? ' '.$options['msgtype']:' warn';
      
      $mtitle=$msgtitle ? "<h3>".$msgtitle."</h3>\n":"";
      $msg=<<<MSG
<div class="message"><span class='$msgtype'>
$mtitle$options[msg]</span>
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
    $titlemnu=0;
    if (isset($quicklinks[$this->page->name])) {
      #$attr.=" class='current'";
      $titlemnu=1;
    } else {
      $quicklinks[$this->page->name]='';
    }

    if ($DBInfo->use_userlink and isset($quicklinks['UserPreferences']) and $options['id'] != 'Anonymous') {
        $tmpid= 'wiki:UserPreferences '.$options['id'];
        $quicklinks[$tmpid]= $quicklinks['UserPreferences'];
        unset($quicklinks['UserPreferences']);
    }

    $save = $this->nonexists;
    $this->nonexists = 'forcelink';
    foreach ($quicklinks as $item=>$attr) {
      if (strpos($item,' ') === false) {
        if (strpos($attr,'=') === false) $attr="accesskey='$attr'";
        # like 'MoniWiki'=>'accesskey="1"'
        $menu[$item]=$this->word_repl($item,_($item),$attr);
#        $menu[]=$this->link_tag($item,"",_($item),$attr);
      } else {
        # like a 'http://moniwiki.sf.net MoniWiki'
        $menu[$item]=$this->link_repl($item,$attr);
      }
    }
    if (!empty($DBInfo->use_titlemenu) and $titlemnu == 0 ) {
      $len = $DBInfo->use_titlemenu > 15 ? $DBInfo->use_titlemenu:15;
      #$attr="class='current'";
      $mnuname=htmlspecialchars($this->page->name);
      if ($DBInfo->hasPage($this->page->name)) {
        if (strlen($mnuname) < $len) {
          $menu[$this->page->name]=$this->word_repl($mypgname,$mnuname,$attr);
        } else if (function_exists('mb_strimwidth')) {
          $my=mb_strimwidth($mypgname,0,$len,'...');
          $menu[$this->page->name]=$this->word_repl($mypgname,htmlspecialchars($my),$attr);
        }
      }
    }
    $this->nonexists = $save;
    $this->sister_on=$sister_save;
    if (!$this->css_friendly) {
      $menu=$this->menu_bra.join($this->menu_sep,$menu).$this->menu_cat;
    } else {
      #for ($i=0,$szm=sizeof($menu);$i<$szm;$i++) {
      #  #if $menu[$i]==
      #  $menu[$i]="<li >".$menu[$i]."</li>\n";
      #}
      $menu='<div id="wikiMenu"><ul><li class="first">'.implode("</li><li>",$menu)."</li></ul></div>\n";
      # set current attribute.
      $menu=preg_replace("/(li)>(<a\s[^>]+current[^>]+)/",
        "$1 class='current'>$2",$menu);
    }
    $this->topmenu=$menu;

    # submenu XXX
    if ($this->submenu) {
      $smenu=array();
      $mnu_pgname=($group ? $group.'~':'').$this->submenu;
      if ($DBInfo->hasPage($mnu_pgname)) {
        $pg=$DBInfo->getPage($mnu_pgname);
        $mnu_raw=$pg->get_raw_body();
        $mlines=explode("\n",$mnu_raw);
        foreach ($mlines as $l) {
          if ($mk and preg_match('/^\s{2,}\*\s*(.*)$/',$l,$m)) {
            if (!is_array($smenu[$mk])) $smenu[$mk]=array();
            $smenu[$mk][]=$m[1];
            if (!$smenu[$m[1]]) $smenu[$m[1]]=$mk;
          } else if (preg_match('/^ \*\s*(.*)$/',$l,$m)) {
            $mk=$m[1];
          }
        }


        # make $submenu, $submain
        $cmenu=null;
        if (isset($smenu[$this->page->name])) {
          $cmenu=&$smenu[$this->page->name];
        }

        $submain='';
        if (isset($smenu['Main'])) {
          $submenus=array();
          foreach ($smenu['Main'] as $item) {
            $submenus[]=$this->link_repl($item);
          }
          $submain='<ul><li>'.implode("</li><li>",$submenus)."</li></ul>\n";
        }

        $submenu='';
        if ($cmenu and ($cmenu != 'Main' or !empty($DBInfo->submenu_showmain))) {
          if (is_array($cmenu)) {
            $smenua=$cmenu;
          } else {
            $smenua=$smenu[$cmenu];
          }

          $submenus=array();
          foreach ($smenua as $item) {
            $submenus[]=$this->link_repl($item);
          }
          #print_r($submenus);
          $submenu='<ul><li>'.implode("</li><li>",$submenus)."</li></ul>\n";
          # set current attribute.
          $submenu=preg_replace("/(li)>(<a\s[^>]+current[^>]+)/",
            "$1 class='current'>$2",$submenu);
        }
      }
    }

    # icons
    #if ($upper)
    #  $upper_icon=$this->link_tag($upper,'',$this->icon['upper'])." ";

    # UserPreferences
    if ($options['id'] != "Anonymous") {
      $user_link=$this->link_tag("UserPreferences","",$options['id']);
      if ($DBInfo->hasPage($options['id'])) {
        $home=$this->link_tag($options['id'],"",$this->icon['home'])." ";
        unset($this->icons['pref']); // insert home icon
        $this->icons['home']=array($options['id'],"",$this->icon['home']);
        $this->icons['pref']=array("UserPreferences","",$this->icon['pref']);
      } else
        $this->icons['pref']=array("UserPreferences","",$this->icon['pref']);
      if (isset($options['scrapped'])) {
        if ($options['scrapped'])
          $this->icons['scrap']=array('','?action=scrap&amp;unscrap=1',$this->icon['unscrap']);
        else
          $this->icons['scrap']=array('','?action=scrap',$this->icon['scrap']);
      }

    } else
      $user_link=$this->link_tag("UserPreferences","",_($this->icon['user']));

    if ($this->icons) {
      $icon=array();
      $myicons=array();

      if ($this->icon_list) {
        $inames=explode(',',$this->icon_list);
        foreach ($inames as $item) {
          if (isset($this->icons[$item])) {
            $myicons[$item]=$this->icons[$item];
          } else if (isset($this->icon[$item])) {
            $myicons[$item]= array("",'?action='.$item,$this->icon[$item]);
          }
        }
      } else {
        $myicons=&$this->icons;
      }
      foreach ($myicons as $item) {
        if ($item[3]) $attr=$item[3];
        else $attr='';
        $icon[]=$this->link_tag($item[0],$item[1],$item[2],$attr);
      }
      $icons=$this->icon_bra.join($this->icon_sep,$icon).$this->icon_cat;
    }

    $rss_icon=$this->link_tag("RecentChanges","?action=rss_rc",$this->icon['rss'])." ";
    $this->_vars['rss_icon']=&$rss_icon;
    $this->_vars['icons']=&$icons;
    $this->_vars['title']=$title;
    $this->_vars['menu']=$menu;
    $this->_vars['upper_icon']=$upper_icon;
    $this->_vars['home']=$home;

    # print the title
    kbd_handler();

    if (empty($this->newtheme) or $this->newtheme != 2) {
      print "</head>\n<body $options[attr]>\n";
      print '<div><a id="top" name="top" accesskey="t"></a></div>'."\n";
    }
    #
    if (file_exists($this->themedir."/header.php")) {
      $trail="<div id='wikiTrailer'>\n".$this->trail."</div>\n";
      $origin="<div id='wikiOrigin'>\n".$this->origin."</div>\n";

      $subindex=$this->subindex;
      $themeurl=$this->themeurl;
      include($this->themedir."/header.php");
    } else { #default header
      $header="<table width='100%' border='0' cellpadding='3' cellspacing='0'>";
      $header.="<tr>";
      if ($DBInfo->logo_string) {
         $header.="<td rowspan='2' style='width:10%' valign='top'>";
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
      if (!$this->css_friendly)
        print $menu." ".$user_link." ".$upper_icon.$icons.$rss_icon;
      else {
        print "<div id='wikiLogin'>".$user_link."</div>";
        print "<div id='wikiIcon'>".$upper_icon.$icons.$rss_icon.'</div>';
        print $menu;
      }
      print $msg;
      print "</div>\n";
    }
    if (!$this->popup and (empty($themeurl) or !$this->_newtheme)) {
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
      print $this->subindex;
    }
    print "<div id='wikiBody'>\n";
    #if ($this->subindex and !$this->popup and (empty($themeurl) or !$this->_newtheme))
    #  print $this->subindex;
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
    $this->_vars['origin']=&$this->origin;
  }

  function set_trailer($trailer="",$pagename,$size=5) {
    global $DBInfo;
    if (empty($trailer)) $trail=$DBInfo->frontpage;
    else $trail=$trailer;
    $trails=array_diff(explode("\t",trim($trail)),array($pagename));

    $sister_save=$this->sister_on;
    $this->sister_on=0;
    $this->trail="";
    $save = $this->nonexists;
    $this->nonexists = 'forcelink';
    foreach ($trails as $page) {
      $this->trail.=$this->word_repl('"'.$page.'"','','',1,0).$DBInfo->arrow;
    }
    $this->nonexists = $save;
    $this->trail.= ' '.htmlspecialchars($pagename);
    $this->pagelinks=array(); # reset pagelinks
    $this->sister_on=$sister_save;

    $this->_vars['trail']=&$this->trail;

    if (!in_array($pagename,$trails)) $trails[]=$pagename;

    if (!empty($DBInfo->trail) and $DBInfo->trail > 5)
      $size = $DBInfo->trail;

    $idx=count($trails) - $size;
    if ($idx > 0) $trails=array_slice($trails,$idx);
    $trail=join("\t",$trails);

    setcookie('MONI_TRAIL',$trail,time()+60*60*24*30,get_scriptname());
  }

  function errlog($prefix="LoG",$tmpname='') {
    global $DBInfo;

    $this->mylog='';
    $this->LOG='';
    if ($DBInfo->use_errlog) {
      if(getenv("OS")!="Windows_NT") {
        $this->mylog=$tmpname ? $DBInfo->vartmp_dir.'/'.$tmpname:
          tempnam($DBInfo->vartmp_dir,$prefix);
        $this->LOG=' 2>'.$this->mylog;
      }
    } else {
      if(getenv("OS")!="Windows_NT") $this->LOG=' 2>/dev/null';
    }
  }

  function get_errlog($all=0,$raw=0) {
    global $DBInfo;

    $log=&$this->mylog;
    if ($log and file_exists($log) and ($sz=filesize($log))) {
      $fd=fopen($log,'r');
      if (is_resource($fd)) {
        $maxl=$DBInfo->errlog_maxline ? min($DBInfo->errlog_maxline,200):20;
        if ($all or $sz <= $maxl*70) { # approx log size ~ line * 70
          $out=fread($fd,$sz);
        } else {
          for ($i=0;($i<$maxl) and ($s=fgets($fd,1024));$i++)
             $out.=$s;
          $out.= "...\n";
        }
        fclose($fd);
        unlink($log);
        $this->LOG='';
        $this->mylog='';

        if (!$DBInfo->raw_errlog and !$raw) {
          $out=preg_replace('/(\/[a-z0-9.]+)+/','/XXX',$out);
        }
        return $out;
      }
    }
    return '';
  }

  function internal_errorhandler($errno, $errstr, $errfile, $errline) {
    $errfile=basename($errfile);
    switch ($errno) {
    case E_WARNING:
      echo "<div><b>WARNING</b> [$errno] $errstr<br />\n";
      echo " in cache $errfile($errline)<br /></div>\n";
      break;
    case E_NOTICE:
      #echo "<div><b>NOTICE</b> [$errno] $errstr<br />\n";
      #echo "  on line $errline in cache $errfile<br /></div>\n";
      break;
    case E_USER_ERROR:
      echo "<div><b>ERROR</b> [$errno] $errstr<br />\n";
      echo "  Fatal error in file $errfile($errline)";
      echo ", PHP " . PHP_VERSION . " (" . PHP_OS . ")<br />\n";
      echo "Skip...<br /></div>\n";
      break;
  
    case E_USER_WARNING:
      echo "<b>MoniWiki WARNING</b> [$errno] $errstr<br />\n";
      break;
  
    case E_USER_NOTICE:
      echo "<b>MoniWiki NOTICE</b> [$errno] $errstr<br />\n";
      break;
  
    default:
      echo "Unknown error type: [$errno] $errstr<br />\n";
      break;
    }
  
    /* http://kr2.php.net/manual/en/function.set-error-handler.php */
    return true;
  }

} # end-of-Formatter

# setup the locale like as the phpwiki style
function get_locales($mode=1) {
  $languages=array(
    'en'=>array('en_US','english',''),
    'fr'=>array('fr_FR','france',''),
    'ko'=>array('ko_KR','korean',''),
  );
  $lang= strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']);
  $lang= strtr($lang,'_','-');
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
    $server_charset = '';
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
  // $_SERVER["PATH_INFO"] has bad value under CGI mode
  // set 'cgi.fix_pathinfo=1' in the php.ini under
  // apache 2.0.x + php4.2.x Win32
  if (!empty($_SERVER['PATH_INFO'])) {
    if ($_SERVER['PATH_INFO'][0] == '/')
      $pagename=substr($_SERVER['PATH_INFO'],1);
  } else if (!empty($_SERVER['QUERY_STRING'])) {
    $goto=$_POST['goto'] ? $_POST['goto']:$_GET['goto'];
    if (!empty($goto)) $pagename=$goto;
    else {
      $pagename = $_SERVER['QUERY_STRING'];
      $temp = strtok($pagename,"&");
      $p=strpos($temp,"=");
      if (!$temp or $p===false) {
        if (preg_match('/^([^&=]+)/',$pagename,$matches)) {
          $pagename = urldecode($matches[1]);
          $_SERVER['QUERY_STRING']=substr($_SERVER['QUERY_STRING'],strlen($pagename));
        }
      } else if ($p>0) {
        $k = substr($temp,0,$p);
        $v = substr($temp,$p+1);
        if ($k =='value') {
          $pagename= substr($temp,$p+1);
          $_SERVER['QUERY_STRING']=substr($_SERVER['QUERY_STRING'],strlen($temp));
        } else if ($k =='action' and $v =='login') {
          $pagename="UserPreferences";
        } else {
          $pagename='';
        }
      } else {
        $pagename=''; // get default pagename later in the wiki_main().
      }
    }
  }
  if ($pagename) {
    $pagename=_stripslashes($pagename);

    if ($pagename[0]=='~' and ($p=strpos($pagename,"/")))
      $pagename=substr($pagename,1,$p-1)."~".substr($pagename,$p+1);
  }
  return $pagename;
}

function init_requests(&$options) {
  global $DBInfo;

  if (!empty($DBInfo->user_class)) {
    include_once('plugin/user/'.$DBInfo->user_class.'.php');
    $class = 'User_'.$DBInfo->user_class;
    $user = new $class();
  } else {
    $user = new WikiUser();
  }

  $udb=new UserDB($DBInfo);
  $DBInfo->udb=$udb;

  if (!empty($DBInfo->trail)) // read COOKIE trailer
    $options['trail']=trim($user->trail) ? $user->trail:'';

  if ($user->id != 'Anonymous') {
    $test = $udb->checkUser($user); # is it valid user ?
    if ($user->id != 'Anonymous')
      $user=$udb->getUser($user->id); // read user info
    else
      $user->setID('Anonymous');
    if ($test == 1) {
      if ($DBInfo->login_strict > 0 ) {
        # auto logout
        $options['header'] = $user->unsetCookie();
      } else if ($DBInfo->login_strict < 0 ) {
        $options['msg'] = _("Someone logged in at another place !");
      }
    }
  }
  $options['id']=$user->id;
  $DBInfo->user=$user;

# MoniWiki theme
if ((empty($DBInfo->theme) or isset($_GET['action'])) and isset($_GET['theme'])) $theme=$_GET['theme'];
else if ($DBInfo->theme_css) $theme=$DBInfo->theme;
if ($theme) $options['theme']=$theme;

if ($options['id'] != 'Anonymous') {
  $options['css_url']=$user->info['css_url'];
  $options['quicklinks']=$user->info['quicklinks'];
  $options['tz_offset']=$user->info['tz_offset'];
  if (!$theme) $options['theme']=$user->info['theme'];
} else {
  $options['css_url']=$user->css;
  $options['tz_offset']=$user->tz_offset;
  if (!$theme) $options['theme']=$theme=$user->theme;
}

if (!$options['theme']) $options['theme']=$theme=$DBInfo->theme;

if ($theme and ($DBInfo->theme_css or !$options['css_url']))
  $options['css_url']=($DBInfo->themeurl ? $DBInfo->themeurl:$DBInfo->url_prefix)."/theme/$theme/css/default.css";

  $options['pagename']=get_pagename();
  if (!empty($DBInfo->robots)) {
    $options['is_robot']=isRobot($_SERVER['HTTP_USER_AGENT']);
  }

  if ($user->id != 'Anonymous' and !empty($DBInfo->use_scrap)) {
    $pages = explode("\t",$user->info['scrapped_pages']);
    $tmp = array_flip($pages);
    if (isset($tmp[$options['pagename']]))
      $options['scrapped']=1;
    else
      $options['scrapped']=0;
  }
}

function init_locale($lang) {
  global $Config,$_locale,$locale;
if (isset($_locale)) {
  if (!@include_once('locale/'.$lang.'/LC_MESSAGES/moniwiki.php'))
    @include_once('locale/'.substr($lang,0,2).'/LC_MESSAGES/moniwiki.php');
} else if (substr($lang,0,2) == 'en') {
  $test=setlocale(LC_ALL, $lang);
} else {
  if ($Config['include_path']) $dirs=explode(':',$Config['include_path']);
  else $dirs=array('.');

  $domain='moniwiki';
  if ($Config['use_local_translation']) {
    $langdir=$lang;
    if(getenv("OS")=="Windows_NT") $langdir=substr($lang,0,2);
    # gettext cache workaround
    # http://kr2.php.net/manual/en/function.gettext.php#58310
    $ldir=$Config['cache_dir']."/locale/$langdir/LC_MESSAGES/";
    if (file_exists($ldir.'md5sum')) {
      $tmp=file($ldir.'md5sum');
      if (file_exists($ldir.'moniwiki-'.$tmp[0].'.mo')) {
        $domain=$domain.'-'.$tmp[0];
        array_unshift($dirs,$Config['cache_dir']);
      }
    }
  }

  $test=setlocale(LC_ALL, $lang);
  foreach ($dirs as $dir) {
    $ldir=$dir.'/locale';
    if (is_dir($ldir)) {
      bindtextdomain($domain, $ldir);
      textdomain($domain);
      break;
    }
  }
  if ($Config['set_lang']) putenv("LANG=".$lang);
  if (function_exists('bind_textdomain_codeset'))
    bind_textdomain_codeset ($domain, $Config['charset']);
}

}

function get_frontpage($lang) {
  global $Config;

  $lcid=substr(strtok($lang,'_'),0,2);
  return $Config['frontpages'][$lcid] ? $Config['frontpages'][$lcid]:$Config['frontpage'];
}

function wiki_main($options) {
  global $DBInfo,$Config;
  $pagename=$options['pagename'] ? $options['pagename']: $DBInfo->frontpage;
  
  # get primary variables
  if ($_SERVER['REQUEST_METHOD']=="POST") {
    # hack for TWiki plugin
    if ($_FILES['filepath']['name']) $action='draw';
    if ($GLOBALS['HTTP_RAW_POST_DATA']) {
      # RAW posted data. the $value and $action could be accessed under
      # "register_globals = On" in the php.ini
      # hack for Oekaki: PageName----action----filename
      list($pagename,$action,$value)=explode('----',$pagename,3);
      $options['value']=$value;
    } else {
      $value=$_POST['value'];
      $action=$_POST['action'] ? $_POST['action']:$action;
      if (!$action) $dum=explode('----',$pagename,3);
      if ($dum[0] && $dum[1]) {
        $pagename=$dum[0];
        $action=$dum[1];
        $value=$dum[2] ? $dum[2]:'';
      }
    }
    $goto=$_POST['goto'];
    $popup=$_POST['popup'];
  } else if ($_SERVER['REQUEST_METHOD']=="GET") {
    $action=$_GET['action'];
    $value=$_GET['value'];
    $goto=$_GET['goto'];
    $rev=$_GET['rev'];
    $refresh=($options['id'] == 'Anonymous') ? 0:$_GET['refresh'];
    $popup=$_GET['popup'];
  }
  $full_action=$action;
  if (($p=strpos($action,'/'))!==false) {
    $full_action=strtr($action,'/','-');
    $action_mode=substr($action,$p+1);
    $action=substr($action,0,$p);
  }

  if (!empty($options['is_robot'])) {
    if (!empty($DBInfo->security_class_robot)) {
      $class='Security_'.$DBInfo->security_class_robot;
      include_once('plugin/security/'.$DBInfo->security_class_robot.'.php');
    } else {
      $class='Security_robot';
      include_once('plugin/security/robot.php');
    }
    $DBInfo->security=new $class ($DBInfo);
    if (!$DBInfo->security->is_allowed($action,$options))
      $action='show';
    $DBInfo->extra_macros='';
  }

  #print $_SERVER['REQUEST_URI'];
  $options['page']=$pagename;

  $page = $DBInfo->getPage($pagename);

  $formatter = new Formatter($page,$options);

  if ($Config['baserule']) {
    $dummy = 'dummy';
    foreach ($Config['baserule'] as $rule=>$repl) {
      $t = @preg_match($rule,$repl);
      if ($t!==false) {
        $formatter->baserule[]=$rule;
        $formatter->baserepl[]=$repl;
      }
    }
  }

  $formatter->refresh=$refresh;
  $formatter->popup=$popup;
  $formatter->macro_repl('InterWiki','',array('init'=>1));
  $formatter->macro_repl('UrlMapping','',array('init'=>1));
  $formatter->tz_offset=$options['tz_offset'];

  // simple black list check
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

  while (!$action or $action=='show') {
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
      if ($DBInfo->auto_search && $action!='show' && $p=getPlugin($DBInfo->auto_search)) {
        $action=$DBInfo->auto_search;
        break;
      }

      $msg_404='';
      if (!$Config['no_404']) $msg_404="Status: 404 Not found"; # for IE
      if (!empty($options['is_robot']) or $Config['nofancy_404']) {
        $formatter->header($msg_404);
        print '<html><head></head><body><h1>'.$msg_404.'</h1></body></html>';
        return;
      }
      $formatter->send_header($msg_404,$options);

      $twins=$DBInfo->metadb->getTwinPages($page->name,2);
      if ($twins) {
        $formatter->send_title('','',$options);
        $twins="\n".join("\n",$twins);
        $formatter->send_page(_("See [TwinPages]: ").$twins);
        echo "<br />".
          $formatter->link_to("?action=edit",$formatter->icon['create']._("Create this page"));
      } else {
        $oldver='';
        if ($DBInfo->version_class) {
          getModule('Version',$DBInfo->version_class);
          $class="Version_".$DBInfo->version_class;
          $version=new $class ($DBInfo);
          $oldver= $version->rlog($formatter->page->name,'','','-z');
        }
        $button= $formatter->link_to("?action=edit",$formatter->icon['create']._("Create this page"));
        if ($oldver) {
          $formatter->send_title(sprintf(_("%s has saved revisions"),$page->name),"",$options);
          print '<h2>'.sprintf(_("%s or click %s to fulltext search.\n"),$button,$formatter->link_to("?action=fullsearch&amp;value=$searchval",_("here"))).'</h2>';
          $options['info_actions']=array('recall'=>'view','revert'=>'revert');
          $options['title']='<h3>'.sprintf(_("Old Revisions of the %s"),htmlspecialchars($page->name)).'</h3>';
          print $formatter->macro_repl('Info','',$options);
        } else {
          $formatter->send_title(sprintf(_("%s is not found in this Wiki"),$page->name),"",$options);
          $searchval=htmlspecialchars($options['page']);
          print '<h2>'.sprintf(_("%s or click %s to fulltext search.\n"),$button,$formatter->link_to("?action=fullsearch&amp;value=$searchval",_("here"))).'</h2>';
          print $formatter->macro_repl('LikePages',$page->name,$err);
          if ($err['extra'])
            print $err['extra'];

          print '<h2>'._("Please try to search with another word").'</h2>';
          $ret = array('call'=>1);
          $ret = $formatter->macro_repl('TitleSearch','',$ret);

          #if ($ret['hits'] == 0)
          print "<div class='searchResult'>".$ret['form']."</div>";
        }

        print "<hr />\n";
        $options['linkto']="?action=edit&amp;template=";
        $tmpls= macro_TitleSearch($formatter,$DBInfo->template_regex,$options);
        if ($tmpls) {
          print sprintf(_("%s or alternativly, use one of these templates:\n"),$button);
          print $tmpls;
        } else {
          print "<h3>"._("You have no templates")."</h3>";
        }
        print sprintf(_("To create your own templates, add a page with '%s' pattern.\n"),$DBInfo->template_regex);
      }

      $args['editable']=1;
      $formatter->send_footer($args,$options);
      return;
    }
    # display this page

    if ($DBInfo->use_redirect_msg and $action=='show' and $_GET['redirect']){
      $options['msg']=
        '<h3>'.sprintf(_("Redirected from page \"%s\""),
          $formatter->link_tag($_GET['redirect'],'?action=show'))."</h3>";
    }
    # increase counter
    if (empty($options['is_robot']))
    $DBInfo->counter->incCounter($pagename,$options);

    if (!$action) $options['pi']=1; # protect a recursivly called #redirect

    if ($DBInfo->control_read and !$DBInfo->security->is_allowed('read',$options)) {
      do_invalid($formatter,$options);
      return;
    }


    $formatter->pi=$formatter->get_instructions($dum);
    if ($DBInfo->body_attr)
      $options['attr']=$DBInfo->body_attr;

    $formatter->send_header("",$options);
    if (empty($options['is_robot'])) {
      $formatter->send_title("","",$options);
    }

    if ($formatter->pi['#title'] and $DBInfo->use_titlecache) {
      $tcache=new Cache_text('title');
      if (!$tcache->exists($pagename) or $_GET['update_title'])
        $tcache->update($pagename,$formatter->pi['#title']);
    }
    if ($DBInfo->use_keywords or $DBInfo->use_tagging or $_GET['update_keywords']) {
      $tcache=new Cache_text('keywords');
      if (!$formatter->pi['#keywords']) {
        $tcache->remove($pagename);
      } else if (!$tcache->exists($pagename) or
        $tcache->mtime($pagename) < $formatter->page->mtime() or
        $_GET['update_keywords']) {
        $keys=explode(',',$formatter->pi['#keywords']);
        $keys=array_map('trim',$keys);
        $tcache->update($pagename,serialize($keys));
      }
    }
    if ($DBInfo->use_referer)
      log_referer($_SERVER['HTTP_REFERER'],$pagename);

    $formatter->write("<div id='wikiContent'>\n");
    $options['timer']->Check("init");
    $options['pagelinks']=1;
    if ($Config['cachetime'] > 0 and !$formatter->pi['#nocache']) {
      $cache=new Cache_text('pages',2,'html');
      $mcache=new Cache_text('dynamicmacros',2);
      $mtime=$cache->mtime($pagename);
      $dtime=filemtime($Config['text_dir'].'/.'); // mtime fix XXX
      $now=time();
      $check=$now-$mtime;
      $extra_out='';
      $_macros=null;
     
      if (!$formatter->refresh and (($mtime > $dtime) and ($check < $Config['cachetime']))) {
        $_macros= unserialize($mcache->fetch($pagename));
        $out= $cache->fetch($pagename);
        $mytime=gmdate("Y-m-d H:i:s",$mtime+$options['tz_offset']);
        $extra_out= "<!-- Cached at $mytime -->";
      } else {
        $formatter->_macrocache=1;
        ob_start();
        $formatter->send_page('',$options);
        flush();
        $out=ob_get_contents();
        ob_end_clean();
        $formatter->_macrocache=0;
        $_macros=&$formatter->_macros;
        if (!$formatter->pi['#nocache']) {
          $cache->update($pagename,$out);
          if (isset($_macros))
            $mcache->update($pagename,serialize($_macros));
        }
      }
      if (!empty($_macros)) {
        $mrule=array();
        $mrepl=array();
        foreach ($_macros as $k=>$v) {
          $mrule[]='[['.$k.']]';
          $options['mid']=$v[1];
          $mrepl[]=$formatter->macro_repl($v[0],'',$options); // XXX
        }
        $out=str_replace($mrule,$mrepl,$out);
      }
      print $out.$extra_out;
      $args['refresh']=1; // add refresh menu
    } else {
      $formatter->send_page('',$options);
    }
    $options['timer']->Check("send_page");
    $formatter->write("<!-- wikiContent --></div>\n");

    if ($DBInfo->extra_macros and
        $formatter->pi['#format'] == $DBInfo->default_markup) {
      if ($formatter->pi['#nocomment']) {
        $options['nocomment']=1;
        $options['notoolbar']=1;
      }
      $options['mid']='dummy';
      print '<div id="wikiExtra">'."\n";
      $mout = '';
      $extra = array();
      if (is_array($DBInfo->extra_macros))
        $extra = $DBInfo->extra_macros;
      else
        $extra[] = $DBInfo->extra_macros; // XXX
      if ($formatter->pi['#comment']) array_unshift($extra,'Comment');

      foreach ($extra as $macro)
        $mout.= $formatter->macro_repl($macro,'',$options);
      print $formatter->get_javascripts();
      print $mout;
      print '</div>'."\n";
    }
    
    $args['editable']=1;
    if (empty($options['is_robot']))
      $formatter->send_footer($args,$options);
    return;
  }

  if ($action) {
    $options['metatags']='<meta name="robots" content="noindex,nofollow" />';
    $options['custom']='';
    $options['help']='';
    $options['value']=$value;

    $a_allow=$DBInfo->security->is_allowed($action,$options);
    if ($action_mode) {
      $myopt=$options;
      $myopt['explicit']=1;
      $f_allow=$DBInfo->security->is_allowed($full_action,$myopt);
      # check if hello/ajax is defined or not
      if ($f_allow === false)
        $f_allow=$a_allow; # follow action permission if it is not defined explicitly.
      if (!$f_allow) {
        if ($action_mode=='ajax') {
          return ajax_invalid($formatter,array('title'=>_("Invalid ajax action.")));
        }
        return do_invalid($formatter,array('title'=>_("Invalid macro action.")));
      }
    } else if (!$a_allow) {
      if ($options['custom']!='' and
          method_exists($DBInfo->security,$options['custom'])) {
        $options['action']=$action;
        if ($action)
        call_user_func(array(&$DBInfo->security,$options['custom']),$formatter,$options);
        return;
      }
      $msg=sprintf(_("You are not allowed to '%s'"),$action);
      $formatter->send_header("Status: 406 Not Acceptable",$options);
      $formatter->send_title($msg,"", $options);
      if ($options['err'])
        $formatter->send_page($options['err']);

      if ($options['help'] and
          method_exists($DBInfo->security,$options['help'])) {
        print "<div id='wikiHelper'>";
        print call_user_method($options['help'],$DBInfo->security,$formatter,$options);
        print "</div>\n";
      }

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

    if (in_array($action_mode,array('ajax','macro'))) {
      if ($_SERVER['REQUEST_METHOD']=="POST")
        $options=array_merge($_POST,$options);
      else
        $options=array_merge($_GET,$options);
      $options['action_mode']=$action_mode;
      if ($action_mode=='ajax')
        $formatter->ajax_repl($action,$options);
      else if ($DBInfo->use_macro_as_action) # XXX
        print $formatter->macro_repl($action,$options['value'],$options);
      else
        do_invalid($formatter,$options);
      return;
    }

    $plugin=($pn=getPlugin($action)) ? $pn:$action;
    if (!function_exists("do_post_".$plugin) and
      !function_exists("do_".$plugin) and $pn){
        include_once("plugin/$pn.php");
    }

    if (function_exists("do_".$plugin)) {
      if ($_SERVER['REQUEST_METHOD']=="POST")
        $options=array_merge($_POST,$options);
      else
        $options=array_merge($_GET,$options);
      call_user_func("do_$plugin",$formatter,$options);
      return;
    } else if (function_exists("do_post_".$plugin)) {
      if ($_SERVER['REQUEST_METHOD']=="POST")
        $options=array_merge($_POST,$options);
      else { # do_post_* set some primary variables as $options
        $options['value']=$_GET['value'];
      }
      call_user_func("do_post_$plugin",$formatter,$options);
      return;
    }
    do_invalid($formatter,$options);
    return;
  }
}

if (!defined('INC_MONIWIKI')):
# Start Main
$Config=getConfig("config.php",array('init'=>1));
include_once("wikilib.php");
include_once("lib/win32fix.php");

$DBInfo= new WikiDB($Config);
register_shutdown_function(array(&$DBInfo,'Close'));

$options=array();
$options['timer']=&$timing;
$options['timer']->Check("load");

$lang= set_locale($DBInfo->lang,$DBInfo->charset);
init_locale($lang);
init_requests($options);
if (!$options['pagename']) $options['pagename']= get_frontpage($lang);
$DBInfo->lang=$lang;

if (session_id()== '' && !$DBInfo->nosession){
  session_name("MONIWIKI");
  session_start();
}

wiki_main($options);
endif;
// vim:et:sts=2:sw=2
?>
