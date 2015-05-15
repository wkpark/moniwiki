<?php
// Copyright 2003-2015 Won-Kyu Park <wkpark at kldp.org> all rights reserved.
// distributable under GPLv2 see COPYING
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
// $Id: wiki.php,v 1.639 2011/08/09 13:51:53 wkpark Exp $
//
$_revision = substr('$Revision: 1.1216.1.47 $',1,-1);
$_release = '1.2.4p8';

#ob_start("ob_gzhandler");

error_reporting(E_ALL ^ E_NOTICE);
#error_reporting(E_ALL);

/**
 * get macro/action plugins
 *
 * @param macro/action name
 * @return a basename of the plugin or null or false(disabled)
 */
function getPlugin($pluginname) {
  static $plugins=array();
  if (is_bool($pluginname) and $pluginname)
    return sizeof($plugins);
  $pname = strtolower($pluginname);
  if (!empty($plugins)) return isset($plugins[$pname]) ? $plugins[$pname]:'';
  global $DBInfo;

  $cp = new Cache_text('settings', array('depth'=>0));
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

  if ($plugins = $cp->fetch('plugins')) {
    if (!empty($DBInfo->myplugins) and is_array($DBInfo->myplugins))
      $plugins=array_merge($plugins,$DBInfo->myplugins);
    return isset($plugins[$pname]) ? $plugins[$pname]:'';
  }
  if (!empty($DBInfo->include_path))
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

  // get predefined macros list
  $tmp = get_defined_functions();
  foreach ($tmp['user'] as $u) {
    if (preg_match('/^macro_(.*)$/', $u, $m))
      $plugins[strtolower($m[1])] = $m[1];
  }

  if (!empty($plugins))
    $cp->update('plugins',$plugins);
  if (!empty($DBInfo->myplugins) and is_array($DBInfo->myplugins))
    $plugins=array_merge($plugins,$DBInfo->myplugins);

  return isset($plugins[$pname]) ? $plugins[$pname]:'';
}

function getProcessor($pro_name) {
  static $processors=array();
  if (is_bool($pro_name) and $pro_name)
    return sizeof($processors);
  $prog = strtolower($pro_name);
  if (!empty($processors)) return isset($processors[$prog]) ? $processors[$prog]:'';
  global $DBInfo;

  $cp = new Cache_text('settings', array('depth'=>0));

  if ($processors=$cp->fetch('processors')) {
    if (is_array($DBInfo->myprocessors))
      $processors=array_merge($processors,$DBInfo->myprocessors);
    return isset($processors[$prog]) ? $processors[$prog]:'';
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
    $cp->update('processors', $processors);
  if (is_array($DBInfo->myprocessors))
    $processors=array_merge($processors,$DBInfo->myprocessors);

  return isset($processors[$prog]) ? $processors[$prog]:'';
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

function kbd_handler($prefix = '') {
  global $Config;

  if (!$Config['kbd_script']) return '';
  $prefix ? null : $prefix = get_scriptname();
  $sep= $Config['query_prefix'];
  return <<<EOS
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

    $ret = '';
    if ($sisters)
      $ret='[wiki:'.str_replace(' ',":$pagename]\n[wiki:",$sisters).":$pagename]";
    $pagename=_preg_search_escape($pagename);
    if ($addons) $ret=rtrim($addons."\n".$ret);

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
  function getLikePages($needle, $count = 1) {
    return array();
  }
  function close() {
  }
}

class MetaDB_text extends MetaDB {
  var $alias; // alias metadata
  var $db; // extra aliases from the AliasPageNames

  function MetaDB_text($db = array()) {
    // open aliasname metadata
    $this->alias = new Cache_Text('aliasname');
    $this->db = $db;
  }

  function hasPage($pagename) {
    if ($this->alias->exists($pagename) or
        !empty($this->db[$pagename])) return true;
    return false;
  }

  function getTwinPages($pagename,$mode=1) {
    if (!$this->alias->exists($pagename) and
        empty($this->db[$pagename])) {
      if (!empty($mode)) return array();
      return false;
    }
    if (empty($mode)) return true;
    $twins = $this->alias->fetch($pagename);
    if (empty($twins))
      $twins = $this->db[$pagename];
    else if (!empty($this->db[$pagename]))
      $twins = array_merge($twins, $this->db[$pagename]);

    // wiki:Hello World -> wiki:"Hello World"
    $twins = preg_replace_callback('@^((?:[^\s]{2,}:)*)(.*)$@',
      create_function('$m',
        'return \'[wiki:\'.$m[1].\'"\'.$m[2].\'"]\';'), $twins);
    return $twins;
  }

  function getSisterSites($pagename,$mode=1) {
    $ret = $this->getTwinPages($pagename, $mode);

    if (is_array($ret))
      return implode("\n", $ret);

    return $ret;
  }
}

class Counter_dba {
  var $counter = null;
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
    if ($this->counter)
      dba_close($this->counter);
  }
}

class Counter {
  function Counter($DB="") { }
  function incCounter($page,$options="") { }
  function pageCounter($page) { return 1; }
  function close() { }
}

class Security_base {
  var $DB;

  function Security_base($DB = '') {
    $this->DB=$DB;
  }

# $options[page]: pagename
# $options[id]: user id
  function readable($options="") {
    return 1;
  }

  function writable($options="") {
    if (!isset($options['page'][0])) return 0; # XXX
    return $this->DB->_isWritable($options['page']);
  }

  function validuser($options="") {
    return 1;
  }

  function is_allowed($action="read",&$options) {
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
  extract($options);
  unset($key,$val,$options);

  // ignore BOM and garbage characters
  ob_start();
  $myret = @include($configfile);
  ob_get_contents();
  ob_end_clean();

  if ($myret === false) {
    if (!empty($init)) {
      $script= preg_replace("/\/([^\/]+)\.php$/",'/monisetup.php',
               $_SERVER['SCRIPT_NAME']);
      if (is_string($init)) $script .= '?init='.$init;
      header("Location: $script");
      exit;
    }
    return array();
  } 
  unset($configfile);
  unset($myret);

  $config=get_defined_vars();

  if (isset($config['include_path']))
    ini_set('include_path',ini_get('include_path').PATH_SEPARATOR.$config['include_path']);

  return $config;
}

class WikiDB {
  function WikiDB($config) {
    // set configurations
    if (is_object($config)) {
      $conf = get_object_vars($config); // merge default settings to $config
    } else {
      $conf = &$config;
    }
    foreach ($conf as $key=>$val) {
      if ($key[0]=='_') continue; // internal variables
      $this->$key=$val;
    }

    $this->initEnv();
    $this->initModules();
    register_shutdown_function(array(&$this,'Close'));
  }

  function initEnv() {
    if (!empty($this->path))
      putenv("PATH=".$this->path);

    if (!empty($this->rcs_user))
      putenv('LOGNAME='.$this->rcs_user);
    if (!empty($this->timezone))
      putenv('TZ='.$this->timezone);
    if (function_exists('date_default_timezone_set')) {
      // suppress date() warnings for PHP5.x
      date_default_timezone_set(@date_default_timezone_get());
    }
  }

  function initModules() {
    if (!empty($this->use_counter)) {
      $this->counter = new Counter_dba($this);
      if ($this->counter->counter == null) {
        $this->use_counter = 0;
        $this->counter = null;
      }
    }
    #$this->interwiki=null;

    if (!empty($this->security_class)) {
      include_once("plugin/security/$this->security_class.php");
      $class='Security_'.$this->security_class;
      $this->security=new $class ($this);
    } else
      $this->security=new Security_base($this);
  }

  function initAlias() {
    // parse the aliaspage
    if (!empty($this->use_alias) and file_exists($this->aliaspage)) {
      $ap = new Cache_text('settings');
      $aliases = $ap->fetch('alias');
      if (empty($aliases) or $ap->mtime() < filemtime($this->aliaspage)) {
        $aliases = get_aliases($this->aliaspage);
        $ap->update('alias', $aliases);
      }
    }

    if (!empty($aliases)) {
      $this->alias= new MetaDB_text($aliases);
    } else {
      $this->alias= new MetaDB();
    }
  }

  function initMetaDB() {
    if (empty($this->alias)) $this->initAlias();

    if (!empty($this->shared_metadb))
      $this->metadb= new MetaDB_dba($this->shared_metadb,$this->dba_type);
    if (empty($this->metadb->metadb)) {
      if (is_object($this->alias)) $this->metadb=$this->alias;
      else $this->metadb= new MetaDB();
    } else {
      $this->metadb->attachDB($this->alias);
    }
  }

  function Close() {
    if (!empty($this->metadb) and is_object($this->metadb))
      $this->metadb->close();
    if (!empty($this->counter) and is_object($this->counter))
      $this->counter->close();
  }

  // moinmoin 1.0.x style internal encoding
  function _pgencode($m) {
    return '_'.sprintf("%02s", strtolower(dechex(ord(substr($m[1],-1)))));
  }

  function _getPageKey($pagename) {
    # normalize a pagename to uniq key

    # moinmoin style internal encoding
    #$name=rawurlencode($pagename);
    #$name=strtr($name,"%","_");
    #$name=preg_replace("/%([a-f0-9]{2})/ie","'_'.strtolower('\\1')",$name);
    #$name=preg_replace(".","_2e",$name);

    $pagename = strtr($pagename, array("\x1a" => "\x0a")); # HACK "%0a" char bug
    // clean up ':' like as the dokuwiki
    if (!empty($this->use_namespace)) {
      $pn= preg_replace('#:+#',':',$pagename);
      $pn= trim($pn,':');
      $pn= preg_replace('#:+#',':',$pn);
    } else {
      $pn = $pagename;
    }

    // namespace spearator ':' like as 'Foobar:Hello'
    $separator = ':';
    if (empty($this->use_namespace)) $separator = '';

    $pn = preg_replace_callback("/([^a-z0-9".$separator."]{1})/i",
            array($this, '_pgencode'), $pn);
    if (!empty($this->use_namespace))
      $name = preg_replace('#:#','.d/',$pn); // Foobar:Hello page will be stored as text/Foobar.d/Hello
    else
      $name = $pn;
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
    if (!isset($pagename[0])) return false;
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

    $pagename = $key;

    // for namespace
    if (!empty($this->use_namespace))
      $pagename=preg_replace('%\.d/%', ':', $key);

    $pagename=strtr($pagename,'_','%');
    $pagename = strtr($pagename, array('%0a' => "%1a")); // HACK "%0a" char bug
    return rawurldecode($pagename);
  }

  function mtime() {
    // workaround to check the dir mtime of the text_dir
    if ($this->use_fakemtime)
      return @filemtime($this->editlog_name);

    return @filemtime($this->text_dir);
  }

  function checkUpdated($time, $delay = 1800) {
    return $this->mtime() <= $time + $delay;
  }

  /**
   * support lazy loading
   *
   */

  function &lazyLoad($name) {
    if (empty($this->$name)) {
      // get extra args
      $tmp = func_get_args();
      array_shift($tmp);
      $params = array();
      for ($i = 0, $num = count($tmp); $i < $num; $i++) {
        if (is_array($tmp[$i]))
          $params = array_merge($params, $tmp[$i]);
        else
          $params[] = $tmp[$i];
      }
      if (count($params) == 1) $params = $params[0];

      $classname = $name.'_class';
      // get $this->foobar_class
      if (!empty($this->$classname)) {
        // classname provided like as 'type' and the real classname is 'foobar_type'
        $file = $name.'.'.$this->$classname; // foobar.type.php
        $class0 = $name.'_'.$this->$classname; // foobar_type class
        if (class_exists($class0)) {
          $class = $class0;
        } else if (class_exists($this->$classname)) {
          $class = $this->$classname;
        } else if ((@include_once('lib/'.$file.'.php')) || (@include_once('lib/'.strtolower($file).'.php'))) {
          $class = $name.'_'.$this->$classname; // foobar_type class
        } else {
          // full classname provided like as Foobar_Type
          $file1 = strtr($this->$classname, '_', '.');
          if ((@include_once('lib/'.$file1.'.php')) || (@include_once('lib/'.strtolower($file1).'.php'))) {
            $class = $this->$classname;
          } else {
            trigger_error(sprintf(_("File '%s' or '%s' does not exist."), $file, $file1), E_USER_ERROR);
            exit;
          }
        }
        // create
        if (!empty($params))
          $this->$name = new $class($params);
        else
          $this->$name = new $class();

        // init module
        if (method_exists($this->$name, 'init_module')) {
          call_user_func(array($this->$name, 'init_module'));
        }
      }
    }
    return $this->$name;
  }

  function getPageLists($options = array()) {
    $indexer = $this->lazyLoad('titleindexer');
    return $indexer->getPages($options);
  }

  function getLikePages($needle,$count=100,$opts='') {
    $pages= array();

    if (!$needle) return false;

    $m = @preg_match("/$needle/".$opts,'dummy');
    if ($m===false) return array(); 
    $indexer = $this->lazyLoad('titleindexer');
    return $indexer->getLikePages($needle, $count);
  }

  function getCounter() {
    $indexer = $this->lazyLoad('titleindexer');
    return $indexer->pageCount();
  }

  function addLogEntry($page_name, $remote_name,$comment,$action="SAVE") {
    $user=&$this->user;
  
    $myid=$user->id;
    if ($myid == 'Anonymous' and !empty($user->verified_email))
      $myid.= '-'.$user->verified_email;

    $comment=strtr(strip_tags($comment),
      array("\r\n"=>' ', "\r"=>' ',"\n"=>' ', "\t"=>' '));
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

    if (!empty($opts['ago'])) {
      $date_from= $time_current - ($opts['ago'] * $secs_per_day);
      $date_to= $date_from + ($days * $secs_per_day);
    } else if (!empty($opts['from'])) {
      $from = strtotime($opts['from']);
      if ($time_current > $from)
        $date_from= $from;
      else
        $date_from = $time_current - ($from - $time_current);

      $date_to= $date_from + ($days * $secs_per_day);
    } else {
      if (!empty($opts['items'])) {
        $date_from= $time_current - (365 * $secs_per_day);
      } else {
        $date_from= $time_current - ($days * $secs_per_day);
      }
      $date_to= $time_current;
    }
    $check=$date_to;

    $itemnum=!empty($opts['items']) ? $opts['items']:200;

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
      #echo $a;
      #echo sizeof($lines);
      #print_r($lines);
      fclose($fp);
      break;   
    }

    if (!empty($opts['quick'])) {
      $out = array();
      foreach($lines as $line) {
        $dum=explode("\t",$line,2);
        if (!empty($dum[0]) and !empty($keys[$dum[0]])) continue;
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
      $id=!empty($options['name']) ?
        _stripslashes($options['name']):$_SERVER['REMOTE_ADDR'];
    } else {
      $id=!empty($options['nick']) ? $options['nick']:$options['id'];
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

    $fp=@fopen($filename,"a+b");
    if (!is_resource($fp))
       return -1;

    flock($fp, LOCK_EX); // XXX
    ftruncate($fp, 0);
    fwrite($fp, $body);
    flock($fp, LOCK_UN);
    fclose($fp);

    $ret = 0;
    if (!empty($this->version_class)) {
      $om=umask(~$this->umask);
      $ver = $this->lazyLoad('version', $this);
      $ret = $ver->_ci($filename,$options['log']);
      if ($ret == -1)
        $options['retval']['msg'] = _("Fail to save version information");
      chmod($filename,0666 & $this->umask);
      umask($om);
    }
    return $ret;
  }

  function savePage(&$page,$comment="",$options=array()) {
    $user=&$this->user;
    if ($user->id == 'Anonymous' and !empty($this->anonymous_log_maxlen))
      if (strlen($comment)>$this->anonymous_log_maxlen) $comment=''; // restrict comment length for anon.

    $REMOTE_ADDR=$_SERVER['REMOTE_ADDR'];

    $myid=$user->id;
    if (!empty($user->info['nick'])) {
      $myid.=' '.$user->info['nick'];
      $options['nick']=$user->info['nick'];
    } else if ($myid == 'Anonymous' and !empty($user->verified_email)) {
      $myid.= '-'.$user->verified_email;
    }
    $options['myid']=$myid;

    $keyname=$this->_getPageKey($page->name);
    $key=$this->text_dir."/$keyname";

    $body=$this->_replace_variables($page->body,$options);

    if (file_exists($key)) {
      $action = 'SAVE';
    } else {
      $action = 'CREATE';
    }

    $log=$REMOTE_ADDR.';;'.$myid.';;'.$comment;
    $options['log']=$log;
    $options['pagename']=$page->name;

    $is_new = false;
    if (!file_exists($key)) $is_new = true;
    $ret=$this->_savePage($key,$body,$options);
    if ($ret == -1) return -1;

    #
    $page->write($body);

    # check minor edits XXX
    $minor=0;
    if (!empty($this->use_minorcheck) or !empty($options['minorcheck'])) {
      $info = $page->get_info();
      if (!empty($info[0][1])) {
        eval('$check='.$info[1].';');
        if (abs($check) < 3) $minor=1;
      }
    }
    if (empty($options['minor']) and !$minor)
      $this->addLogEntry($keyname, $REMOTE_ADDR,$comment,$action);

    $indexer = $this->lazyLoad('titleindexer');
    if ($is_new) $indexer->addPage($page->name);
    else $indexer->update($page->name); // just update mtime
    return 0;
  }

  function deletePage($page,$options='') {
    $REMOTE_ADDR=$_SERVER['REMOTE_ADDR'];

    $comment=$options['comment'];
    $user=&$this->user;

    $keyname=$this->_getPageKey($page->name);

    if ($this->version_class) {
      $log=$REMOTE_ADDR.';;'.$user->id.';;'.$comment;
      $version = $this->lazyLoad('version', $this);
      $ret = $version->ci($page->name,$log);
      if (!empty($options['history']))
        $version->delete($page->name);
    }
    $delete=@unlink($this->text_dir."/$keyname");
    $this->addLogEntry($keyname, $REMOTE_ADDR, $comment, 'DELETE');

    $indexer = $this->lazyLoad('titleindexer');
    $indexer->deletePage($page->name);
    // remove pagelinks and backlinks
    store_pagelinks($page->name, array());

    // remove aliases
    if (!empty($this->use_alias))
      store_aliases($page->name, array());

    $handle= opendir($this->cache_dir);
    $permanents = array('backlinks', 'keywords', 'aliases', 'wordindex', 'redirect');
    while ($file= readdir($handle)) {
      if ($file[0] != '.' and is_dir("$this->cache_dir/$file") and is_file($this->cache_dir.'/'.$file.'/.info')) {
        // do not delete permanent caches
        if (in_array($file, $permanents)) continue;

        $cache= new Cache_text($file);
        $cache->remove($page->name);

        # blog cache
        if ($file == 'blogchanges') {
          $files = array();
          $cache->_caches($files, array('prefix'=>1));
          foreach ($files as $file) {
            #echo $keyname.';'.$fcache."\n";
            if (preg_match("/\d+_2e$keyname$/", $file))
              unlink($this->cache_dir.'/'.$file);
          }
        } # for blog cache
      }
    }
    return 0;
  }

  function renamePage($pagename,$new,$options='') {
    $REMOTE_ADDR=$_SERVER['REMOTE_ADDR'];

    // remove pagelinks and backlinks
    store_pagelinks($pagename, array());
    // remove aliases
    if (!empty($this->use_alias))
      store_aliases($pagename, array());

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
      $version = $this->lazyLoad('version', $this);
      $version->rename($pagename,$new);
    }

    $comment=sprintf(_("Rename %s to %s"),$pagename,$new);
    $this->addLogEntry($okeyname, $REMOTE_ADDR, '', 'DELETE');
    $this->addLogEntry($keyname, $REMOTE_ADDR, $comment, 'CREATE');

    $indexer = $this->lazyLoad('titleindexer');
    $indexer->renamePage($pagename, $new);
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

class WikiPage {
  var $fp;
  var $filename;
  var $pi = null;
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

  function etag($params = array()) {
    global $DBInfo;
    $dep = '';
    $tag = '';
    if (!empty($DBInfo->etag_seed))
      $tag.= $DBInfo->etag_seed;

    // check some parameters
    foreach (array('action', 'lang', 'theme') as $k)
      if (isset($params[$k])) $tag.= $params[$k];

    if (!empty($params['deps'])) {
      foreach ($params['deps'] as $d) {
        !empty($params[$d]) ? $tag.= $params[$d] : true;
      }
    }
    if ($params['action'] != 'raw' || empty($params['nodep']))
      $dep.= $DBInfo->mtime();
    return md5($this->mtime().$dep.$tag.$this->name);
  }

  function size() {
    if ($this->fsize) return $this->fsize;
    $this->fsize=@filesize($this->filename);
    return $this->fsize;
  }

  function lines() {
    return get_file_lines($this->filename);
  }

  function get_raw_body($options='') {
    global $DBInfo;

    if ($this->body && empty($options['rev']))
       return $this->body;

    $rev= !empty($options['rev']) ? $options['rev']:(!empty($this->rev) ? $this->rev:'');
    if (!empty($rev)) {
      if (!empty($DBInfo->version_class)) {
        $version = $DBInfo->lazyLoad('version', $DBInfo);
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
      $version = $DBInfo->lazyLoad('version', $DBInfo);
      $rev= $version->get_rev($this->name,$mtime,$last);

      if (!empty($rev)) return $rev;
    }
    return '';
  }

  function get_info($rev='') {
    global $DBInfo;

    $infos = array();
    if (empty($rev))
      $rev=$this->get_rev('',1);
    if (empty($rev)) return false;

    if (!empty($DBInfo->version_class)) {
      $opt = '';

      $version = $DBInfo->lazyLoad('version', $DBInfo);
      $out = $version->rlog($this->name,$rev,$opt);
    } else {
      return false;
    }

    $state=0;
    if (isset($out)) {
      for ($line=strtok($out,"\n"); $line !== false;$line=strtok("\n")) {
        if ($state == 0 and preg_match("/^date:\s.*$/",$line)) {
          $info = array();
          $tmp=preg_replace("/date:\s(.*);\s+author:.*;\s+state:.*;/","\\1",rtrim($line));
          $tmp=explode('lines:',$tmp);
          $info[0]=$tmp[0];
          $info[1]=isset($tmp[1]) ? $tmp[1] : '';
          $state=1;
        } else if ($state) {
          list($info[2],$info[3],$info[4])=explode(';;',$line,3);
          $infos[] = $info;
          $state = 0;
        }
      }
    }
    return $infos;
  }

  function get_redirect() {
    $body = $this->get_raw_body();
    if ($body[0] == '#' and ($p = strpos($body, "\n")) !== false) {
      $line = substr($body, 0, $p);
      if (preg_match('/#redirect\s/i', $line)) {
        list($tag, $val) = explode(' ', $line, 2);
        if (isset($val[0])) return $val;
      }
    }
  }

  function get_instructions($body = '', $params = array()) {
    global $Config;

    $pikeys=array('#redirect','#action','#title','#notitle','#keywords','#noindex',
      '#format','#filter','#postfilter','#twinpages','#notwins','#nocomment','#comment',
      '#language','#camelcase','#nocamelcase','#cache','#nocache','#alias', '#linenum', '#nolinenum',
      '#singlebracket','#nosinglebracket','#rating','#norating','#nodtd');
    $pi=array();

    $format='';

    // get page format from $pagetype
    if ( empty($this->pi['#format']) and !empty($Config['pagetype'])) {
      preg_match('%(:|/)%',$this->name,$sep);
      $key=strtok($this->name,':/');
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

    $update_pi = false;
    if (empty($body)) {
      if (!$this->exists()) return array();
      if (isset($this->pi)) return $this->pi;

      $pi_cache = new Cache_text('PI');
      if (empty($params['refresh']) and $this->mtime() < $pi_cache->mtime($this->name)) {
        $pi = $pi_cache->fetch($this->name);

        if (!isset($pi['#format']))
          $pi['#format'] = $Config['default_markup'];

        return $pi;
      }

      $body=$this->get_raw_body();
      $update_pi = true;
    }

    if (!empty($Config['use_metadata'])) {
      // FIXME experimental
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
        list($format, $body) = explode("\n", $body, 2);
        $format = rtrim(substr($format, 2));
      }

      // not parsed lines are comments
      $notparsed=array();
      $pilines=array();
      $body_start = 0;
      while ($body and $body[0] == '#') {
        $body_start++;
        # extract first line
        list($line, $body)= explode("\n", $body,2);
        if ($line=='#') break;
        else if ($line[1]=='#') { $notparsed[]=$line; continue;}
        $pilines[]=$line;

        $val = '';
        if (($pos = strpos($line, ' ')) !== false) 
          list($key,$val)= explode(' ',$line,2);
        else
          $key = trim($line);
        $key=strtolower($key);
        $val=trim($val);
        if (in_array($key,$pikeys)) { $pi[$key]=$val ? $val:1; }
        else {
           $notparsed[]=$line;
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
      if (isset($pi['#nolinenum'])) $pi['#linenum']=0;
    }

    if (empty($pi['#format']) and !empty($format))
      $pi['#format'] = $format; // override default

    if (!empty($pi['#format']) and ($p = strpos($pi['#format'],' '))!== false) {
      $pi['args'] = substr($pi['#format'],$p+1);
      $pi['#format']= substr($pi['#format'],0,$p);
    }

    if (!empty($piline)) $pi['raw']= $piline;
    if (!empty($body_start)) $pi['start_line'] = $body_start;

    if ($update_pi) {
      $pi_cache->update($this->name, $pi);
      $this->cache_instructions($pi);
    }

    if (!isset($pi['#format']))
      $pi['#format']= $Config['default_markup'];

    return $pi;
  }

  function cache_instructions($pi) {
    global $Config;
    global $DBInfo;

    $pagename = $this->name;

    // update aliases
    if (!empty($Config['use_alias'])) {
      $ac = new Cache_text('alias');
      // is it removed ?
      if ($ac->exists($pagename) and
          empty($pi['#alias']) and empty($pi['#title'])) {
        // remove aliases
        store_aliases($pagename, array());
      } else if (!$ac->exists($pagename) or
          $ac->mtime($pagename) < $this->mtime() or !empty($_GET['update_alias'])) {
        $as = array();
        // parse #alias
        if (!empty($pi['#alias']))
          $as = get_csv($pi['#alias']);
        // add #title as a alias
        if (!empty($pi['#title']))
          $as[] = $pi['#title'];

        // update aliases
        store_aliases($pagename, $as);
      }
    }

    // update #redirect
    $rc = new Cache_Text('redirect');
    $old = $rc->fetch($pagename);
    if ($old or isset($pi['#redirect'][0])) {
      // update invert redirect index
      $rc2 = new Cache_Text('redirects');
      if ($old != $pi['#redirect']) {
        // update direct cache
        $rc->update($pagename, $pi['#redirect']);
        if (!isset($pi['#redirect'][0])) {
          $rc->remove($pagename);
        } else if ($DBInfo->hasPage($pi['#redirect'])) {
          // add redirect links
          $redirects = $rc2->fetch($pi['#redirect']);
          if (empty($redirects)) $redirects = array();
          $redirects = array_merge($redirects, array($pagename));
          $rc2->update($pi['#redirect'], $redirects);
        }

        if ($old != '' and $old != false) {
          // delete redirect links
          $l = $rc2->fetch($old);
          if ($l !== false and is_array($l)) {
            $redirects = array_diff($l, array($pagename));
            if (empty($redirects)) $rc2->remove($old);
            else $rc2->update($old, $redirects);
          }
        }
      }
    }

    if (!empty($Config['use_keywords']) or !empty($Config['use_tagging']) or !empty($_GET['update_keywords'])) {
      $tcache= new Cache_text('keyword');
      $cache = new Cache_text('keywords');

      $cur = $tcache->fetch($pagename);
      if (empty($cur)) $cur = array();
      $keys = array();
      if (empty($pi['#keywords'])) {
        $tcache->remove($pagename);
      } else {
        $keys = explode(',', $pi['#keywords']);
        $keys = array_map('trim', $keys);
        if (!$tcache->exists($pagename) or
          $tcache->mtime($pagename) < $this->mtime() or
          !empty($_GET['update_keywords'])) {
          $tcache->update($pagename, $keys);
        }
      }

      $adds = array_diff($keys, $cur);
      $dels = array_diff($cur, $keys);

      // merge new keywords
      foreach ($adds as $a) {
        if (!isset($a[0])) continue;
        $l = $cache->fetch($a);
        if (!is_array($l)) $l = array();
        $l = array_merge($l, array($pagename));
        $cache->update($a, $l);
      }

      // remove deleted keywords
      foreach ($dels as $d) {
        if (!isset($d[0])) continue;
        $l = $cache->fetch($d);
        if (!is_array($l)) $l = array();
        $l = array_diff($l, array($pagename));
        $cache->update($d, $l);
      }
    }

    if (!empty($pi['#title']) and !empty($Config['use_titlecache'])) {
      $tc = new Cache_text('title');
      $old = $tc->fetch($pagename);
      if (!isset($pi['#title']))
        $tc->remove($pagename);
      else if ($old != $pi['#title'] or !$tcache->exists($pagename) or !empty($_GET['update_title']))
        $tc->update($pagename,$pi['#title']);
    }

    return;
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
    $this->prefix= (isset($options['prefix'])) ? $options['prefix']:get_scriptname();
    $this->self_query='';
    $this->url_prefix= $DBInfo->url_prefix;
    $this->imgs_dir= $DBInfo->imgs_dir;
    $this->imgs_url_interwiki=$DBInfo->imgs_url_interwiki;
    $this->imgs_dir_url=$DBInfo->imgs_dir_url;
    $this->actions= $DBInfo->actions;
    $this->inline_latex=
      $DBInfo->inline_latex == 1 ? 'latex':$DBInfo->inline_latex;
    $this->use_purple=$DBInfo->use_purple;
    $this->section_edit=$DBInfo->use_sectionedit;
    $this->auto_linebreak=!empty($DBInfo->auto_linebreak) ? 1 : 0;
    $this->nonexists=$DBInfo->nonexists;
    $this->url_mappings=&$DBInfo->url_mappings;
    $this->css_friendly=$DBInfo->css_friendly;
    $this->use_smartdiff=!empty($DBInfo->use_smartdiff) ? $DBInfo->use_smartdiff : 0;
    $this->use_easyalias=$DBInfo->use_easyalias;
    $this->use_group=!empty($DBInfo->use_group) ? $DBInfo->use_group : 0;
    $this->use_htmlcolor = !empty($DBInfo->use_htmlcolor) ? $DBInfo->use_htmlcolor : 0;

    // strtr() old wiki markups
    $this->trtags = !empty($DBInfo->trtags) ? $DBInfo->trtags : null;
    $this->submenu=!empty($DBInfo->submenu) ? $DBInfo->submenu : null;
    $this->email_guard=$DBInfo->email_guard;
    $this->interwiki_target=!empty($DBInfo->interwiki_target) ?
      ' target="'.$DBInfo->interwiki_target.'"':'';
    $this->filters=!empty($DBInfo->filters) ? $DBInfo->filters : null;
    $this->postfilters=!empty($DBInfo->postfilters) ? $DBInfo->postfilter : null;
    $this->use_rating=!empty($DBInfo->use_rating) ? $DBInfo->use_rating : 0;
    $this->use_metadata=!empty($DBInfo->use_metadata) ? $DBInfo->use_metadata : 0;
    $this->use_smileys=$DBInfo->use_smileys;
    $this->use_namespace=!empty($DBInfo->use_namespace) ? $DBInfo->use_namespace : '';
    $this->mediawiki_style=!empty($DBInfo->mediawiki_style) ? 1 : '';
    $this->lang=$DBInfo->lang;
    $this->udb=&$DBInfo->udb;
    $this->user=&$DBInfo->user;
    $this->check_openid_url=!empty($DBInfo->check_openid_url) ? $DBInfo->check_openid_url : 0;
    $this->register_javascripts($DBInfo->javascripts);
    $this->fetch_action = !empty($DBInfo->fetch_action) ? $DBInfo->fetch_action : null;
    $this->fetch_images = !empty($DBInfo->fetch_images) ? $DBInfo->fetch_images : 0;
    $this->fetch_imagesize = !empty($DBInfo->fetch_imagesize) ? $DBInfo->fetch_imagesize : 0;
    if (empty($this->fetch_action))
      $this->fetch_action = $this->link_url('', '?action=fetch&amp;url=');
    else
      $this->fetch_action = $DBInfo->fetch_action;

    if ($this->use_group and ($p=strpos($page->name,"~")))
      $this->group=substr($page->name,0,$p+1);

    $this->sister_on=1;
    $this->sisters=array();
    $this->foots=array();
    $this->pagelinks=array();
    $this->aliases=array();
    $this->icons="";
    $this->quote_style= !empty($DBInfo->quote_style) ? $DBInfo->quote_style:'quote';

    $this->themedir= !empty($DBInfo->themedir) ? $DBInfo->themedir:dirname(__FILE__);
    $this->themeurl= !empty($DBInfo->themeurl) ? $DBInfo->themeurl:$DBInfo->url_prefix;
    $this->set_theme(!empty($options['theme']) ? $options['theme'] : '');

    $this->NULL='';
    if(getenv("OS")!="Windows_NT") $this->NULL=' 2>/dev/null';

    $this->_macrocache=0;
    $this->wikimarkup=0;
    $this->pi=array();
    $this->external_on=0;
    $this->external_target='';
    if (!empty($DBInfo->external_target))
      $this->external_target='target="'.$DBInfo->external_target.'"';

    // set filter
    if (!empty($this->filters)) {
      if (!is_array($this->filters)) {
        $this->filters=preg_split('/(\||,)/',$this->filters);
      }
    } else {
      $this->filters = '';
    }
    if (!empty($this->postfilters)) {
      if (!is_array($this->postfilters)) {
        $this->postfilters=preg_split('/(\||,)/',$this->postfilters);
      }
    } else {
      $this->postfilters = '';
    }

    $this->baserule=array("/(?<!\<)<(?=[^<>]*>)/",
                     "/&(?!([^&;]+|#[0-9]+|#x[0-9a-fA-F]+);)/",
                     "/(?<!')'''((?U)(?:[^']|(?<!')'(?!')|'')*)?'''(?!')/",
                     "/''''''/", // SixSingleQuote
                     "/(?<!')''((?:[^']|[^']'(?!'))*)''(?!')/",
                     "/`(?<!\s)(?!`)([^`']+)(?<!\s)'(?=\s|$)/",
                     "/`(?<!\s)(?U)(.*)(?<!\s)`/",
                     "/^(={4,})$/",
                     "/,,([^,]{1,40}),,/",
                     "/\^([^ \^]+)\^(?=\s|$)/",
                     "/\^\^(?<!\s)(?!\^)(?U)(.+)(?<!\s)\^\^/",
                     "/__(?<!\s)(?!_)(?U)(.+)(?<!\s)__/",
                     "/--(?<!\s)(?!-)(?U)(.+)(?<!\s)--/",
                     "/~~(?<!\s)(?!~)(?U)(.+)(?<!\s)~~/",
                     #"/(\\\\\\\\)/", # tex, pmWiki
                     );
    $this->baserepl=array("&lt;",
                     "&amp;",
                     "<strong>\\1</strong>",
                     "<strong></strong>",
                     "<em>\\1</em>",
                     "&#96;\\1'","<code>\\1</code>",
                     "<br clear='all' />",
                     "<sub>\\1</sub>",
                     "<sup>\\1</sup>",
                     "<sup>\\1</sup>",
                     "<em class='underline'>\\1</em>",
                     "<del>\\1</del>",
                     "<del>\\1</del>",
                     #"<br />\n",
                     );

    // set extra baserule
    if (!empty($DBInfo->baserule)) {
      foreach ($DBInfo->baserule as $rule=>$repl) {
        $t = @preg_match($rule,$repl);
        if ($t!==false) {
          $this->baserule[]=$rule;
          $this->baserepl[]=$repl;
        }
      }
    }

    // check and prepare $url_mappings
    if (!empty($DBInfo->url_mappings)) {
      if (!is_array($DBInfo->url_mappings)) {
        $maps=explode("\n",$DBInfo->url_mappings);
        $tmap=array();
        foreach ($maps as $map) {
          if (strpos($map,' ')) {
            $key=strtok($map,' ');
            $val=strtok('');
            $tmap["$key"]=$val;
          }
        }
        $this->url_mappings=$tmap;
      }
    }

    # recursive footnote regex
    $this->footrule='\[\*[^\[\]]*((?:[^\[\]]++|\[(?13)\])*)\]';

  }

  /**
   * init Smileys
   * load smileys and set smily_rule and smiley_repl
   */
  function initSmileys() {
    $this->smileys = getSmileys();

    $tmp = array_keys($this->smileys);
    $tmp = array_map('_preg_escape', $tmp);
    $rule = implode('|', $tmp);

    $this->smiley_rule = '/(?<=\s|^|>)('.$rule.')(?=\s|<|$)/';
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
    if (!empty($DBInfo->url_schemas)) $url.='|'.$DBInfo->url_schemas;
    $this->urls=$url;
    $urlrule="((?:$url):\"[^\"]+\"[^\s$punct]*|(?:$url):(?:[^\s$punct]|(\.?[^\s$punct]))+(?<![,\.\):;\"\'>]))";
    #$urlrule="((?:$url):(\.?[^\s$punct])+)";
    #$urlrule="((?:$url):[^\s$punct]+(\.?[^\s$punct]+)+\s?)";
    # solw slow slow
    #(?P<word>(?:/?[A-Z]([a-z0-9]+|[A-Z]*(?=[A-Z][a-z0-9]|\b))){2,})
    $this->wordrule=
    # nowiki
    "!?({{{(?:(?:[^{}]+|{[^{}]+}(?!})|(?<!{){{1,2}(?!{)|(?<!})}{1,2}(?!})|(?<=\\\\)[{}]{3}(?!}))|(?2))++}}})|".
    # {{{{{{}}}, {{{}}}}}}, {{{}}}
    "(?:(?!<{{{){{{}}}(?!}}})|{{{(?:{{{|}}})}}})|".
    # single bracketed rule [http://blah.blah.com Blah Blah]
    "(?:\[\^?($url):[^\s\]]+(?:\s[^\]]+)?\])|".
    # InterWiki
    # strict but slow
    #"\b(".$DBInfo->interwikirule."):([^<>\s\'\/]{1,2}[^\(\)<>\s\']+\s{0,1})|".
    #"\b([A-Z][a-zA-Z]+):([^<>\s\'\/]{1,2}[^\(\)<>\s\']+\s{0,1})|".
    #"\b([A-Z][a-zA-Z]+):([^<>\s\'\/]{1,2}[^\(\)<>\s\']+[^\(\)<>\s\',\.:\?\!]+)|".
    "(?:\b|\^?|!?)(?:[A-Z][a-zA-Z0-9]+):(?:\"[^\"]+\"|[^:\(\)<>\s\']?[^\s<\'\",\!\010\006]+(?:\s(?![\x21-\x7e]))?)(?<![,\.\)>])|".
    #"(?:\b|\^?)(?:[A-Z][a-zA-Z]+):(?:[^:\(\)<>\s\']?[^\s<\'\",:\!\010\006]+(?:\s(?![\x21-\x7e]))?(?<![,\.\)>]))|".
    #"(\b|\^?)([A-Z][a-zA-Z]+):([^:\(\)<>\s\']?[^<>\s\'\",:\?\!\010\006]*(\s(?![\x21-\x7e]))?)";
    # for PR #301713
    #
    # new regex pattern for
    #  * double bracketted rule similar with MediaWiki [[Hello World]]
    #  * single bracketted words [Hello World] etc.
    #  * single bracketted words with double quotes ["Hello World"]
    #  * double bracketted words with double quotes [["Hello World"]]
    "(?<!\[)\!?\[(\[)$single(\")?(?:[^\[\]\",<\s'\*]?[^\[\]]{0,255}[^\"])(?(5)\"(?:[^\"\]]*))(?(4)\])\](?!\])";

    if ($camelcase)
      $this->wordrule.='|'.
      "(?<![a-zA-Z0-9#])\!?(?:((\.{1,2})?\/)?[A-Z]([A-Z]+[0-9a-z]|[0-9a-z]+[A-Z])[0-9a-zA-Z]*)+\b";
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
    if (!empty($theme)) {
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
    if (!empty($DBInfo->icon))
    $this->icon=array_merge($DBInfo->icon,$this->icon);

    if (!isset($this->icon_bra)) {
      $this->icon_bra=$DBInfo->icon_bra;
      $this->icon_cat=$DBInfo->icon_cat;
      $this->icon_sep=$DBInfo->icon_sep;
    }

    if (empty($this->menu)) {
      $this->menu=&$DBInfo->menu;
    }

    if (!isset($this->menu_bra)) {
      $this->menu_bra=!empty($DBInfo->menu_bra) ? $DBInfo->menu_bra : '';
      $this->menu_cat=!empty($DBInfo->menu_cat) ? $DBInfo->menu_cat : '';
      $this->menu_sep=!empty($DBInfo->menu_sep) ? $DBInfo->menu_sep : '';
    }

    if (!$this->icons)
      $this->icons = array();

    if (!empty($DBInfo->icons))
    $this->icons = array_merge($DBInfo->icons,$this->icons);

    if (empty($this->icon_list)) {
      $this->icon_list=!empty($DBInfo->icon_list) ? $DBInfo->icon_list:null;
    }
    if (empty($this->purple_icon)) {
      $this->purple_icon=$DBInfo->purple_icon;
    }
    if (empty($this->perma_icon)) {
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

  function _diff_repl($arr) {
    if ($arr[1]{0}=="\010") { $tag='ins'; $sty='added'; }
    else { $tag='del'; $sty='removed'; }
    if (strpos($arr[2],"\n") !== false)
      return "<div class='diff-$sty'>".$arr[2]."</div>";
    return "<$tag class='diff-$sty'>".$arr[2]."</$tag>";
  }

  function write($raw) {
    echo $raw;
  }

  function _url_mappings_callback($m) {
    return $this->url_mappings[$m[1]];
  }

  function link_repl($url,$attr='',$opts=array()) {
    $nm = 0;
    $force = 0;
    $double_bracket = false;
    if (is_array($url)) $url=$url[1];
    #if ($url[0]=='<') { echo $url;return $url;}
    $url=str_replace('\"','"',$url); // XXX
    $bra = '';
    $ket = '';
    if ($url{0}=='[') {
      $bra='[';
      $ket=']';
      $url=substr($url,1,-1);
      $force=1;
    }
    // set nomacro option for callback
    if (!empty($this->nomacro)) $opts['nomacro'] = 1;

    switch ($url[0]) {
    case '{':
      $url=substr($url,3,-3);
      if (empty($url))
        return "<code class='nowiki'></code>"; # No link
      if (preg_match('/^({([^{}]+)})/s',$url,$sty)) { # textile like styling
        $url=substr($url,strlen($sty[1]));
        $url = preg_replace($this->baserule, $this->baserepl, $url); // apply inline formatting rules
        return "<span style='$sty[2]'>$url</span>";
      }
      if ($url[0]=='#' and ($p=strpos($url,' '))) {
        $col=strtok($url,' '); $url=strtok('');
        #$url = str_replace('<', '&lt;', $url);
        if (!empty($this->use_htmlcolor) and !preg_match('/^#[0-9a-f]{6}$/i', $col)) {
          $col = substr($col, 1);
          return "<span style='color:$col'>$url</span>";
        }
        if (preg_match('/^#[0-9a-f]{6}$/i',$col))
          return "<span style='color:$col'>$url</span>";
        $url=$col.' '.$url;
      } else if (preg_match('/^((?:\+|\-)([1-6]?))(?=\s)(.*)$/',$url,$m)) {
        if ($m[2]=='') $m[1].='1';
        $fsz=array(
          '-5'=>'10%','-4'=>'20%','-3'=>'40%','-2'=>'60%','-1'=>'80%',
          '+1'=>'140%','+2'=>'180%','+3'=>'220%','+4'=>'260%','+5'=>'200%');
        return "<span style='font-size:".$fsz[$m[1]]."'>$m[3]</span>";
      }

      $url = str_replace("<","&lt;",$url);
      if ($url[0]==' ' and in_array($url[1],array('#','-','+')) !==false)
        $url='<span class="markup invisible"> </span>'.substr($url,1);
      return "<code class='wiki'>".$url."</code>"; # No link
      break;
    case '<':
      $nm = 1; // XXX <<MacroName>> support
      $url=substr($url,2,-2);
      preg_match("/^([^\(]+)(\((.*)\))?$/", $url, $match);
      if (isset($match[1])) {
        $myname = getPlugin($match[1]);
        if (!empty($myname)) {
          if (!empty($opts['nomacro'])) return ''; # remove macro
          return $this->macro_repl($url); # valid macro
        }
      }
      return '<<'.$url.'>>';
      break;
    case '[':
      $bra.='[';
      $ket.=']';
      $url=substr($url,1,-1);
      $double_bracket = true;

      if (preg_match("/^([^\(:]+)(\((.*)\))?$/", $url, $match)) {
        if (isset($match[1])) {
          $name = $match[1];
        } else {
          $name = $url;
        }

        // check alias
        $myname = getPlugin($name);
        if (!empty($myname)) {
          if (!empty($opts['nomacro'])) return ''; # remove macro
          return $this->macro_repl($url); # No link
        }
      }

      break;
    case '$':
      #return processor_latex($this,"#!latex\n".$url);
      $url=preg_replace('/<\/?sup>/','^',$url);
      //if ($url[1] != '$') $opt=array('type'=>'inline');
      //else $opt=array('type'=>'block');
      $opt=array('type'=>'inline');
      return $this->processor_repl($this->inline_latex,$url,$opt);
      break;
    case '*':
        if (!empty($opts['nomacro'])) return ''; # remove macro
      $url = preg_replace($this->baserule, $this->baserepl, $url); // apply inline formatting rules
      return $this->macro_repl('FootNote',$url);
      break;
    case '!':
      $url=substr($url,1);
      return $url;
      break;
    default:
      break;
    }

    if ($url[0] == '#') {
      // Anchor syntax in the MoinMoin 1.1
      $anchor = strtok($url,' |');
      return ($word = strtok('')) ? $this->link_to($anchor, $word):
                 "<a id='".substr($anchor, 1)."'></a>";
    }

    //$url=str_replace('&lt;','<',$url); // revert from baserule
    $url=preg_replace('/&(?!#?[a-z0-9]+;)/i','&amp;',$url);

    // ':' could be used in the title string.
    $urltest = $url;
    $tmp = preg_split('/\s|\|/', $url); // [[foobar foo]] or [[foobar|foo]]
    if (count($tmp) > 1) $urltest = $tmp[0];

    if ($url[0] == '"') {
      $url = preg_replace('/&amp;/i', '&', $url);
      // [["Hello World"]], [["Hello World" Page Title]]
      return $this->word_repl($bra.$url.$ket, '', $attr);
    } else
    if (($p = strpos($urltest, ':')) !== false and
        (!isset($url{$p+1}) or (isset($url{$p+1}) and $url{$p+1}!=':'))) {

      // namespaced pages
      // [[한글:페이지]], [[한글:페이지 이름]]
      // mixed name with non ASCII chars
      if (preg_match('/^([^\^a-zA-Z0-9]+.*)\:/', $url))
        return $this->word_repl($bra.$url.$ket, '', $attr);

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

      if (!empty($this->url_mappings)) {
        if (!isset($this->url_mapping_rule))
          $this->macro_repl('UrlMapping', '', array('init'=>1));
        if (!empty($this->url_mapping_rule))
          $url=
            preg_replace_callback('/('.$this->url_mapping_rule.')/i',
              array($this, '_url_mappings_callback'), $url);
      }

      // InterWiki Pages
      if (preg_match("/^(:|w|[A-Z])/",$url)
          or (!empty($this->urls) and !preg_match('/^('.$this->urls.')/',$url))) {
        $url = preg_replace('/&amp;/i', '&', $url);
        return $this->interwiki_repl($url,'',$attr,$external_icon);
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

      if ($force or strstr($url, ' ') or strstr($url, '|')) {
        if (($tok = strtok($url, ' |')) !== false) {
          $text = strtok('');
          $text = preg_replace($this->baserule, $this->baserepl, $text);
          $text = str_replace('&lt;', '<', $text); // revert from baserule
          $url = $tok;
        }
        #$link=str_replace('&','&amp;',$url);
        $link=preg_replace('/&(?!#?[a-z0-9]+;)/i','&amp;',$url);
        if (!isset($text[0])) $text=$url;
        else {
          $img_attr='';
          $img_cls = '';
          if (preg_match("/^attachment:/",$text)) {
            $atext=$text;
            if (($p=strpos($text,'?')) !== false) {
              $atext=substr($text,0,$p);
              parse_str(substr($text,$p+1),$attrs);
              foreach ($attrs as $n=>$v) {
                if ($n == 'align') $img_cls = ' img'.ucfirst($v);
                else
                  $img_attr.="$n=\"$v\" ";
              }
            }

            $msave = $this->_macrocache;
            $this->_macrocache = 0;
            $fname = $this->macro_repl('attachment', substr($text, 11), 1);
            if (file_exists($fname))
              $text = qualifiedUrl($this->url_prefix.'/'.$fname);
            else
              $text = $this->macro_repl('attachment', substr($text, 11));
            $this->_macrocache = $msave; // restore _macrocache
          }
          $text = preg_replace('/&amp;/i', '&', $text);
          if (preg_match("/^((?:https?|ftp).*\.(png|gif|jpeg|jpg))(?:\?|&(?!>amp;))?(.*?)?$/i",$text, $match)) {
            $cls = 'externalImage';
            $type = strtoupper($match[2]);
            $atext=isset($atext[0]) ? $atext:$text;
            $url = str_replace('&','&amp;',$match[1]);
            // trash dummy query string
            $url = preg_replace('@(\?|&)\.(png|gif|jpe?g)$@', '', $url);
            $tmp = !empty($match[3]) ? preg_replace('/&amp;/', '&', $match[3]) : '';
            $attrs = explode('&', $tmp);
            $eattr = '';
            foreach ($attrs as $a) {
              $name = strtok($a, '=');
              $val = strtok(' ');
              if ($name == 'align') $cls.=' img'.ucfirst($val);
              else if ($name and $val) $eattr.=' '.$name.'="'.urldecode($val).'"';
            }

            $size = '';
            if (!empty($this->fetch_imagesize))
              $size = '('.$this->macro_repl('ImageFileSize', $url).')';

            if (!empty($this->fetch_images) and !preg_match('@^https?://'.$_SERVER['HTTP_HOST'].'@', $url))
              $url = $this->fetch_action. str_replace(array('&', '?'), array('%26', '%3f'), $url);

            return "<div class='$cls$img_cls'><div><a class='externalLink named' href='$link' $attr $this->external_target title='$link'><img $eattr alt='$atext' src='$url' $img_attr/></a>".
                "<div><a href='$url'><span>[$type "._("external image")."$size]</span></a></div></div></div>";
          }
          if (!empty($this->external_on))
            $external_link='<span class="externalLink">('.$url.')</span>';
        }
        $icon = '';
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
        if (empty($this->_no_urlicons) and empty($icon)) {
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
        $url1 = preg_replace('/&amp;/','&',$url);
        if (preg_match("/(^.*\.(png|gif|jpeg|jpg))(?:\?|&(?!>amp;))?(.*?)?$/i", $url1, $match)) {
          $cls = 'externalImage';
          $url=$match[1];
          // trash dummy query string
          $url = preg_replace('@(\?|&)\.(png|gif|jpe?g)$@', '', $url);
          $type = strtoupper($match[2]);
          $attrs = !empty($match[3]) ? explode('&', $match[3]) : array();
          foreach ($attrs as $arg) {
            $name=strtok($arg,'=');
            $val=strtok(' ');
            if ($name == 'align') $cls.=' img'.ucfirst($val);
            else if ($name and $val) $attr.=' '.$name.'="'.urldecode($val).'"';
          }
          $size = '';
          if (!empty($this->fetch_imagesize))
            $size = '('.$this->macro_repl('ImageFileSize', $url).')';

          // XXX fetch images
          $fetch_url = $url;
          if (!empty($this->fetch_images) and !preg_match('@^https?://'.$_SERVER['HTTP_HOST'].'@', $url))
            $fetch_url = $this->fetch_action.
                str_replace(array('&', '?'), array('%26', '%3f'), $url);

          return "<div class=\"$cls\"><div><img alt='$link' $attr src='$fetch_url' />".
                "<div><a href='$url'><span>[$type "._("external image")."$size]</span></a></div></div></div>";
        }
      }
      if (substr($url,0,7)=='http://' and $url[7]=='?') {
        $link=substr($url,7);
        return "<a class='internalLink' href='$link'>$link</a>";
      }
      $url=urldecode($url);

      // auto detect the encoding of a given URL
      if (function_exists('mb_detect_encoding'))
        $url = _autofixencode($url);

      return "<a class='externalLink' $attr href='$link' $this->external_target>$url</a>";
    } else {
      if ($url{0}=='?')
        $url=substr($url,1);

      $url = preg_replace('/&amp;/i', '&', $url);
      return $this->word_repl($bra.$url.$ket, '', $attr);
    }
  }

  function interwiki_repl($url,$text='',$attr='',$extra='') {
    global $DBInfo;

    if ($url[0]=="w")
      $url=substr($url,5);
    else if ($url[0]==":")
      $url=substr($url,1);
    $url = ltrim($url); // ltrim wikiwords

    $wiki='';
    # wiki:MoinMoin:FrontPage
    # wiki:MoinMoin/FrontPage for MoinMoin compatibility.
    if (preg_match('/^([A-Z][a-zA-Z0-9]+):(.*)$/',$url,$m)) {
      $wiki=$m[1]; $url=$m[2];
    }

    # wiki:"Hello World" wiki:MoinMoin:"Hello World"
    # [wiki:"Hello World" hello world]
    if (isset($url{0}) and $url[0]=='"') {
      if (preg_match('/^((")?[^"]+\2)((\s+)?(.*))?$/',$url,$m)) {
        #$url=$m[1];
        #if (isset($m[5])) $text=$m[5];
      }
    } else if (($p=strpos($url,' '))!==false) {
      $text=substr($url,$p+1);
      if (isset($text[0])) $url=substr($url,0,$p);
    }

    if (empty($wiki)) {
      # wiki:FrontPage (not supported in the MoinMoin)
      # or [wiki:FrontPage Home Page]
      return $this->word_repl($url,$text.$extra,$attr,1);
    }

    if (empty($DBInfo->interwiki)) {
      $this->macro_repl('InterWiki', '', array('init'=>1));
    }

    // invalid InterWiki name
    if (empty($DBInfo->interwiki[$wiki])) {
      #$dum0=preg_replace("/(".$this->wordrule.")/e","\$this->link_repl('\\1')",$wiki);
      #return $dum0.':'.($page?$this->link_repl($page,$text):'');

      return $this->word_repl("$wiki:$url",$text.$extra,$attr,1);
    }

    $icon=$this->imgs_url_interwiki.strtolower($wiki).'-16.png';
    $sx=16;$sy=16;
    if (isset($DBInfo->intericon[$wiki])) {
      $icon=$DBInfo->intericon[$wiki][2];
      $sx=$DBInfo->intericon[$wiki][0];
      $sy=$DBInfo->intericon[$wiki][1];
    }

    $page=$url;
    $url=$DBInfo->interwiki[$wiki];

    if (isset($page[0]) and $page[0]=='"') # "extended wiki name"
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

  function get_pagelinks() {
    if (!is_object($this->cache))
      $this->cache= new Cache_text('pagelinks');

    if ($this->cache->exists($this->page->name)) {
      $links=$this->cache->fetch($this->page->name);
      if ($links !== false) return $links;
    }
    $links = get_pagelinks($this, $this->page->_get_raw_body());
    return $links;
  }

  function get_backlinks() {
    if (!is_object($this->bcache))
      $this->bcache= new Cache_text('backlinks');

    if ($this->bcache->exists($this->page->name)) {
      $links=$this->bcache->fetch($this->page->name);
      if ($links !== false) return $links;
    }
    // no backlinks found. XXX
    return array();
  }

  function word_repl($word,$text='',$attr='',$nogroup=0,$islink=1) {
    global $DBInfo;
    $nonexists='nonexists_'.$this->nonexists;

    $word = $page = trim($word, '[]'); // trim out [[Hello World]] => Hello World

    $extended = false;
    if (($word[0] == '"' or $word[0] == 'w') and preg_match('/^(?:wiki\:)?((")?[^"]+\2)((\s+|\|)?(.*))?$/', $word, $m)) {
      # ["extended wiki name"]
      # ["Hello World" Go to Hello]
      # [wiki:"Hello World" Go to Main]
      $word = substr($m[1], 1, -1);
      if (isset($m[5][0])) $text = $m[5]; // text arg ignored

      $extended=true;
      $page=$word;
    } else if (($p = strpos($word, '|')) !== false) {
      // or MediaWiki/WikiCreole like links
      $text = substr($word, $p + 1);
      $word = substr($word, 0, $p);
      $page = $word;
    }
    if (!$extended and empty($DBInfo->mediawiki_style)) {
      #$page=preg_replace("/\s+/","",$word); # concat words
      $page=normalize($word); # concat words
    }

    if (empty($DBInfo->use_twikilink)) $islink=0;
    list($page,$page_text,$gpage)=
      normalize_word($page,$this->group,$this->page->name,$nogroup,$islink);
    if (isset($text[0])) {
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
          // trash dummy query string
          $text = preg_replace('@(\?|&)\.(png|gif|jpe?g)$@', '', $text);

          if (!empty($this->fetch_images) and !preg_match('@^https?://'.$_SERVER['HTTP_HOST'].'@', $text))
            $text = $this->fetch_action. str_replace(array('&', '?'), array('%26', '%3f'), $text);

          $word="<img style='border:0' alt='$word' src='$text' /></a>";
        }
      } else {
        $word = preg_replace($this->baserule, $this->baserepl, $text);
        //$word = str_replace('&lt;', '<', $word); // revert from baserule
      }
    } else {
      $word=$text=$page_text ? $page_text:$word;
      #echo $text;
      $word=_html_escape($word);
    }

    $url=_urlencode($page);
    $url_only=strtok($url,'#?'); # for [WikiName#tag] [wiki:WikiName#tag Tag]
    #$query= substr($url,strlen($url_only));
    if ($extended) $page=rawurldecode($url_only); # C++
    else $page=urldecode($url_only);
    $url=$this->link_url($url);

    #check current page
    if ($page == $this->page->name) $attr.=' class="current"';

    if (!empty($this->forcelink))
      return $this->nonexists_always($word, $url, $page);

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
          if ($tpage != $word) $title = 'title="'._html_escape($page).'" ';
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
      if ($tpage != $word) $title = 'title="'._html_escape($page).'" ';
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
        if (empty($DBInfo->metadb)) $DBInfo->initMetaDB();
        $sisters=$DBInfo->metadb->getSisterSites($page, $DBInfo->use_sistersites);
        if ($sisters === true) {
          $this->pagelinks[$page]=-2;
          return "<a href='$url'>$word</a>".
            "<tt class='sister'><a href='$url'>&#x203a;</a></tt>";
        }
        if (!empty($sisters)) {
          if (!empty($this->use_easyalias) and !preg_match('/^\[wiki:[A-Z][A-Za-z0-9]+:.*$/', $sisters)) {
            # this is a alias
            $this->use_easyalias=0;
            $tmp = explode("\n", $sisters);
            $url=$this->link_repl(substr($tmp[0],0,-1).' '.$word.']');
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

  function nonexists_simple($word, $url, $page) {
    $title = '';
    if ($page != $word) $title = 'title="'._html_escape($page).'" ';
    return "<a class='nonexistent nomarkup' {$title}href='$url' rel='nofollow'>?</a>$word";
  }

  function nonexists_nolink($word,$url) {
    return "$word";
  }

  function nonexists_always($word,$url,$page) {
    $title = '';
    if ($page != $word) $title = 'title="'._html_escape($page).'" ';
    return "<a href='$url' {$title}rel='nofollow'>$word</a>";
  }

  function nonexists_forcelink($word, $url, $page) {
    $title = '';
    if ($page != $word) $title = 'title="'._html_escape($page).'" ';
    return "<a class='nonexistent' rel='nofollow' {$title}href='$url'>$word</a>";
  }

  function nonexists_fancy($word, $url, $page) {
    global $DBInfo;
    $title = '';
    if ($page != $word) $title = 'title="'._html_escape($page).'" ';
    if ($word[0]=='<' and preg_match('/^<[^>]+>/',$word))
      return "<a class='nonexistent' rel='nofollow' {$title}href='$url'>$word</a>";
    #if (preg_match("/^[a-zA-Z0-9\/~]/",$word))
    if (ord($word[0]) < 125) {
      $link=$word[0];
      if ($word[0]=='&') {
        $link=strtok($word,';').';';$last=strtok('');
      } else
        $last=substr($word,1);
      return "<span><a class='nonexistent' rel='nofollow' {$title}href='$url'>$link</a>".$last.'</span>';
    }
    if (strtolower($DBInfo->charset) == 'utf-8')
      $utfword=$word;
    else if (function_exists('iconv')) {
      $utfword=iconv($DBInfo->charset,'utf-8',$word);
    }
    while ($utfword !== false and isset($utfword[0])) {
      preg_match('/^(.)(.*)$/u', $utfword, $m);
      if (!empty($m[1])) {
        $tag = $m[1];
        if (strtolower($DBInfo->charset) != 'utf-8' and function_exists('iconv')) {
          $tag = iconv('utf-8', $DBInfo->charset, $tag);
          if ($tag === false) break;
          $last = substr($word, strlen($tag));
        } else {
          $last = !empty($m[2]) ? $m[2] : '';
        }
        return "<span><a class='nonexistent' rel='nofollow' {$title}href='$url'>$tag</a>".$last.'</span>';
      }
      break;
    }
    return "<a class='nonexistent' rel='nofollow' {$title}href='$url'>$word</a>";
  }

  function head_repl($depth,$head,&$headinfo,$attr='') {
    $dep=$depth < 6 ? $depth : 5;
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
      $num=implode('.', $dum);
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
      $num=implode('.', $dum);
    }

    $headinfo['dep']=$depth; # save old
    $headinfo['num']=$num;

    $prefix=$this->toc_prefix;
    if ($this->toc)
      $head="<span class='tocnumber'><a href='#toc'>$num<span class='dot'>.</span></a> </span>$head";
    $perma='';
    if (!empty($this->perma_icon))
    $perma=" <a class='perma' href='#s$prefix-$num'>$this->perma_icon</a>";

    return "$close$open<h$dep$attr><a id='s$prefix-$num'></a>$head$perma</h$dep>";
  }

  function include_functions()
  {
    foreach (func_get_args() as $f) function_exists($f) or include_once 'plugin/function/'.$f.'.php';
  }

  function macro_repl($macro,$value='',$options=array()) {
    preg_match("/^([^\(]+)(\((.*)\))?$/", $macro, $match);
    if (empty($value) and isset($match[2])) { #strpos($macro,'(') !== false)) {
      $name = $match[1];
      $args = empty($match[3]) ? true : $match[3];
    } else {
      $name = $macro;
      $args = $value;
    }

    // check alias
    $myname = getPlugin($name);
    if (empty($myname)) return '[['.$macro.']]';
    $macro_name = '';
    if (strtolower($name) != strtolower($myname))
      $macro_name = strtolower($name);
    $name = $myname;

    if (isset($macro_name[0]) and is_array($options))
      $options['macro_name'] = $macro_name;

    // macro ID
    $this->mid=!empty($options['mid']) ? $options['mid']:
      (!empty($this->mid) ? ++$this->mid:1);

    $bra='';$ket='';
    if (!empty($this->wikimarkup) and $macro != 'attachment' and empty($options['nomarkup'])) {
      $markups=str_replace(array('=','-','<'),array('==','-=','&lt;'),$macro);
      $markups=preg_replace('/&(?!#?[a-z0-9]+;)/i','&amp;',$markups);
      $bra= "<span class='wikiMarkup'><!-- wiki:\n[[$markups]]\n-->";
      $ket= '</span>';
      $options['nomarkup']=1; // for the attachment macro
    }

    if (!function_exists ('macro_'.$name)) {
      $np = getPlugin($name);
      if (empty($np)) return '[['.$macro.']]';
      include_once('plugin/'.$np.'.php');
      if (!function_exists ('macro_'.$np)) return '[['.$macro.']]';
      $name = $np;
    }

    $ret=call_user_func_array('macro_'.$name,array(&$this,$args,&$options));
    if (is_array($ret)) return $ret;
    return $bra.$ret.$ket;
  }

  function macro_cache_repl($name, $args)
  {
    $arg = '';
    if ($args === true) $arg = '()';
    else if (!empty($args)) $arg = '('.$args.')';
    $macro = $name.$arg;
    $md5sum = md5($macro);
    $this->_dynamic_macros[$macro] = array($md5sum, $this->mid);
    return '@@'.$md5sum.'@@';
  }

  function processor_repl($processor,$value, $options = false) {
    $bra='';$ket='';
    if (!empty($this->wikimarkup) and empty($options['nomarkup'])) {
      if (!empty($options['type']) and $options['type'] == 'inline') {
        $markups=str_replace(array('=','-','&','<'),array('==','-=','&amp;','&lt;'),$value);
        $bra= "<span class='wikiMarkup' style='display:inline'><!-- wiki:\n".$markups."\n-->";
      } else {
        if (!empty($options['nowrap']) and !empty($this->pi['#format']) and $processor == $this->pi['#format']) { $btag='';$etag=''; }
        else { $btag='{{{';$etag='}}}'; }
        $notag = '';
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
        preg_match("/\006|\010/", $value)) $pf='plain';

      $ret= call_user_func_array("processor_$pf",array(&$this,$value,$options));
      if (!is_string($ret)) return $ret;
      return $bra.$ret.$ket;
    }

    $classname='processor_'.$pf;
    $myclass= new $classname($this,$options);
    $ret= call_user_func(array($myclass,'process'),$value,$options);
    if (!empty($options['nowrap']) and !empty($myclass->_type) and $myclass->_type=='wikimarkup') return $ret;
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
        echo call_user_func_array('macro_'.$plugin,array(&$this,'',$options));
        return;
      }
      return ajax_invalid($this,array('title'=>_("Invalid ajax action.")));
    }

    return call_user_func('ajax_'.$plugin,$this,$options);
  }

  function smiley_repl($smiley) {
    // check callback style
    if (is_array($smiley)) $smiley = $smiley[1];
    $img=$this->smileys[$smiley][3];

    $alt=str_replace("<","&lt;",$smiley);

    if (preg_match('/^(https?|ftp):/',$img))
      return "<img src='$img' style='border:0' class='smiley' alt='$alt' title='$alt' />";
    return "<img src='$this->imgs_dir/$img' style='border:0' class='smiley' alt='$alt' title='$alt' />";
  }

  /**
   * temporary callback hack example to support extra params with callback
   */
  function _array_callback($match, $init = false) {
    static $array;

    if ($init) {
      // XXX hack to store extra params with callback
      $array = $match;
      return;
    }
    return $array[$match[1]];
  }

  function link_url($pageurl, $query_string='') {
    global $DBInfo;
    $sep=$DBInfo->query_prefix;

    if (empty($query_string)) {
      if (isset($this->query_string)) $query_string=$this->query_string;
    } else if ($query_string[0] == '#') {
      $query_string= $this->self_query.$query_string;
    }

    if ($sep == '?') {
      if (isset($pageurl[0]) && isset($query_string[0]) && $query_string[0]=='?')
        # add 'dummy=1' to work around the buggy php
        $query_string= '&amp;'.substr($query_string,1).'&amp;dummy=1';
        # Did you have a problem with &amp;dummy=1 ?
        # then, please replace above line with next line.
        #$query_string= '&amp;'.substr($query_string,1);
      $query_string= $pageurl.$query_string;
    } else
      $query_string= $pageurl.$query_string;
    return $this->prefix . $sep . $query_string;
  }

  function link_tag($pageurl,$query_string="", $text="",$attr="") {
    # Return a link with given query_string.
    $text = strval($text);
    if (!isset($text[0]))
      $text= $pageurl; # XXX
    if (!isset($pageurl[0]))
      $pageurl=$this->page->urlname;
    if (isset($query_string[0]) and $query_string[0]=='?')
      $attr=empty($attr) ? 'rel="nofollow"' : $attr;
    $url=$this->link_url($pageurl,$query_string);
    return '<a href="'.$url.'" '. $attr .'><span>'.$text.'</span></a>';
  }

  function link_to($query_string="",$text="",$attr="") {
    if (empty($text))
      $text=_html_escape($this->page->name);

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
    $dtype = array('dd'=>'div', 'dq'=>'blockquote');
    if ($list_type=="dd" or $list_type=="dq") {
      if ($on)
         $list_type=$dtype[$list_type]."$divtype";
      else
         $list_type=$dtype[$list_type];
      $numtype='';
    } else if ($list_type=="dl") {
      if ($on)
         $list_type="dl";
      else
         $list_type="dd></dl";
      $numtype='';
    } if (!$on and $closetype and !in_array($closetype, array('dd', 'dq')))
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

  function _td($line,&$tr_attr, $wordrule = '') {
    $cells=preg_split('/((?:\|\|)+)/',$line,-1,
      PREG_SPLIT_DELIM_CAPTURE);
    $row='';
    for ($i=1,$s=sizeof($cells);$i<$s;$i+=2) {
      $align='';
      $m=array();
      preg_match('/^((&lt;[^>]+>)*)([ ]*)(.*?)([ ]*)?(\s*)$/s',
        $cells[$i+1],$m);
      $cell=$m[3].$m[4].$m[5];

      // count left, right spaces to align
      $l = strlen($m[3]);
      $r = strlen($m[5]);

      // strip last "\n"
      if (substr($cell, -1) == '\n')
        $cell = substr($cell, 0, -1);
      if (strpos($cell,"\n") !== false) {
        // strip first space.
        if ($cell[0] == ' ' and !preg_match('/^[ ](?:(\d+|i|a|A)\.|[*])[ ]/', $cell))
          $cell = substr($cell, 1);
        $cell = str_replace("\002\003", '||', $cell); // revert table separator ||
        $params = array('notoc'=>1);
        $cell = str_replace('&lt;', '<', $cell); // revert from baserule
        $cell = strtr($cell, array('\\}}}'=>'}}}', '\\{{{'=>'{{{')); // FIXME
        $cell=$this->processor_repl('monimarkup',$cell, $params);
        $cell = str_replace('&lt;', '<', $cell); // revert from baserule
        // do not align multiline cells
        $l = '';
        $r = '';
      } else if (isset($wordrule[0])) {
        $cell = preg_replace_callback("/(".$wordrule.")/",
          array(&$this, 'link_repl'), $cell);
      }
      if ($l and $r) {
        if ($l > 1 and $r > 1)
          $align = 'center';
        else if ($l > 1)
          $align = 'right';
      }
      else if (!$l) $align='';
      else if (!$r) $align='right';

      $tag = 'td';
      $attrs = $this->_td_attr($m[1], $align);
      if (!$tr_attr) $tr_attr=$m[1]; // XXX

      // check TD is header or not
      if (isset($attrs['heading'])) {
        $tag = 'th';
        unset($attrs['heading']);
      }
      $attr = '';
      foreach ($attrs as $k=>$v) $attr.= $k.'="'.trim($v, "'\"").'" ';
      $attr.= $this->_td_span($cells[$i]);
      $row.= "<$tag $attr>".$cell.'</'.$tag.'>';
    }
    return $row;
  }

  function _td_attr(&$val,$align='') {
    if (!$val) {
      if ($align) return array('class'=>$align);
      return array();
    }
    $para=str_replace(array('&lt;','&gt'),array('<','>'),$val);
    // split attributes <:><|3> => ':', '|3'
    $tmp = explode('><',substr($para,1,-1));
    $paras = array();
    foreach ($tmp as $p) {
      // split attributes <(-2> => '(', '-2'
      if (preg_match_all('/([\^_v\(:\)\!=]|[-\|]\d+|\d+%|#[0-9a-fA-F]{6}|(?:colspan|rowspan|[a-z]+)\s*=\s*.+)/i', $p, $m))
        $paras = array_merge($paras, $m[1]);
      else
        $paras[] = $p;
    }
    # rowspan
    $sty=array();
    $rsty=array();
    $attr=array();
    $rattr=array();
    $myattr=array();
    $myclass=array();

    foreach ($paras as $para) {
    if (preg_match("/^(\-|\|)(\d+)$/",$para,$match)) {
      if ($match[1] == '-')
        $attr['colspan'] = $match[2];
      else
        $attr['rowspan'] = $match[2];
      $para = '';
    }
    else if (strlen($para)==1) {
      switch ($para) {
      case '^':
        $attr['valign']='top';
        break;
      case 'v':
      case '_':
        $attr['valign']='bottom';
        break;
      case '(':
        $align='left';
        break;
      case ')':
        $align='right';
        break;
      case ':':
        $align='center';
        break;
      case '!':
      case '=':
        $attr['heading'] = true; // hack to support table header
        break;
      default:
        break;
      }
    } else if ($para[0]=='#') {
      $sty['background-color']=strtolower($para);
      $para = '';
    } else if (is_numeric($para[0])) {
      $attr['width'] = $para;
      $para = '';
    } else {
      if (substr($para,0,7)=='colspan') {
        $attr['colspan'] = trim(substr($para, 8), ' =');
        $para = '';
      } else if (substr($para,0,7)=='rowspan') {
        $attr['rowspan'] = trim(substr($para, 8), ' =');
        $para = '';
      } else if (substr($para,0,3)=='row') {
        // row properties
        $val=substr($para,3);
        $myattr=$this->_attr($val,$rsty);
        $rattr=array_merge($rattr,$myattr);
        continue;
      }
    }
    $myattr=$this->_attr($para,$sty,$myclass,$align);
    $attr=array_merge($attr,$myattr);
    }
    $myclass=!empty($attr['class']) ? $attr['class']:'';
    unset($attr['class']);
    if (!empty($myclass))
      $attr['class']=trim($myclass);

    $val='';
    foreach ($rattr as $k=>$v) $val.=$k.'="'.trim($v, "'\"").'" ';

    return $attr;
  }

  function _table($on,&$attr) {
    if (!$on) return "</table>\n";

    $sty=array();
    $myattr=array();
    $mattr=array();
    $attrs=str_replace(array('&lt;','&gt'),array('<','>'),$attr);
    $attrs= explode('><',substr($attrs,1,-1));
    $myclass=array();
    $rattr=array();
    $attr='';
    foreach ($attrs as $tattr) {
      $tattr=trim($tattr);
      if (empty($tattr)) continue;
      if (substr($tattr,0,5)=='table') {
        $tattr=substr($tattr,5);
        $mattr=$this->_attr($tattr,$sty,$myclass);
        $myattr=array_merge($myattr,$mattr);
      } else { // not table attribute
        $rattr[]=$tattr;
        #else $myattr=$this->_attr($tattr,$sty,$myclass);
      }
    }
    if (!empty($rattr)) $attr='&lt;'.implode('>&lt;',$rattr).'>';
    if (!empty($myattr['class']))
      $myattr['class'] = 'wiki '.$myattr['class'];
    else
      $myattr['class'] = 'wiki';
    $my = '';
    foreach ($myattr as $k=>$v) $my.=$k.'="'.$v.'" ';
    return "<table cellspacing='0' $my>\n";
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
    //$this->url_prefix= qualifiedUrl($this->url_prefix);
    $this->prefix= qualifiedUrl($this->prefix);
    $this->imgs_dir= qualifiedUrl($this->imgs_dir);
    $this->imgs_url_interwiki=qualifiedUrl($this->imgs_url_interwiki);
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
              echo $this->macro_repl($name,$val,$options);
              break;
            case 'processor':
              echo $this->processor_repl($name,$val,$options);
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

    if (isset($body[0])) {
      unset($this->page->pi['#format']); // reset page->pi to get_instructions() again
      $this->text = $body;
      $pi=$this->page->get_instructions($body);

      if ($this->wikimarkup and $pi['raw']) {
        $pi_html=str_replace("\n","<br />\n",$pi['raw']);
        echo "<span class='wikiMarkup'><!-- wiki:\n$pi[raw]\n-->$pi_html</span>";
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
        $opts = $options;
        $opts['nowrap'] = 1;
        $text= $this->processor_repl($pi['#format'],
          $pi_line.$body,$opts);
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

        if (empty($options['nojavascript']))
          echo $this->get_javascripts();
        echo $text;

        return;
      }
      // strtr old wiki markups
      if (!empty($this->trtags))
        $body = strtr($body, $this->trtags);
      $lines=explode("\n",$body);
      $el = end($lines);
      // delete last empty line
      if (!isset($el[0])) array_pop($lines);
    } else {
      # XXX need to redesign pagelink method ?
      if (empty($DBInfo->without_pagelinks_cache)) {
        if (empty($this->cache) or !is_object($this->cache))
          $this->cache= new Cache_text('pagelinks');

        $dmt= $DBInfo->mtime();
        $this->update_pagelinks= $dmt > $this->cache->mtime($this->page->name);
        #like as..
        #if (!$this->update_pagelinks) $this->pagelinks=$this->get_pagelinks();
      }

      if (isset($options['rev'])) {
        $body=$this->page->get_raw_body($options);
        $pi=$this->page->get_instructions($body);
      } else {
        $pi=$this->page->get_instructions('', $options);
        $body=$this->page->get_raw_body($options);
      }
      $this->text = &$body; // XXX

      $this->set_wordrule($pi);
      if (!empty($this->wikimarkup) and !empty($pi['raw']))
        echo "<span class='wikiMarkup'><!-- wiki:\n$pi[raw]\n--></span>";

      if (!empty($this->use_rating) and empty($this->wikimarkup) and empty($pi['#norating'])) {
        $this->pi=$pi;
        $old=$this->mid;
        if (isset($pi['#rating'])) $rval=$pi['#rating'];
        else $rval='0';

        echo '<div class="wikiRating">'.$this->macro_repl('Rating',$rval,array('mid'=>'page'))."</div>\n";
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
        $opts = $options;
        $opts['nowrap'] = 1;
        if (!empty($pi['start_line'])) {
          // trash PI instructions
          $i = $pi['start_line'];
          // set $start param
          $opts['start'] = $i;
          $pos = 0;
          while (($p = strpos($body, "\n", $pos)) !== false and $i > 0) {
            $pos = $p + 1;
            $i --;
          }
          if ($pos > 0) {
            $body = substr($body, $pos);
          }
        }
        $text= $this->processor_repl($pi['#format'],$pi_line.$body,$opts);

        $fts=array();
        if (isset($pi['#postfilter'])) $fts=preg_split('/(\||,)/',$pi['#postfilter']);
        if (!empty($this->postfilters)) $fts=array_merge($fts,$this->postfilters);
        if ($fts) {
          foreach ($fts as $ft)
            $text=$this->postfilter_repl($ft,$text,$options);
        }
	$this->postambles();
        echo $this->get_javascripts();
        echo $text;

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
        // strtr old wiki markups
        if (!empty($this->trtags))
          $body = strtr($body, $this->trtags);

        $lines=explode("\n",$body);
        $el = end($lines);
        // delete last empty line
        if (!isset($el[0])) array_pop($lines);
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
      if (empty($DBInfo->metadb)) $DBInfo->initMetaDB();
      $twins=$DBInfo->metadb->getTwinPages($this->page->name,$twin_mode);

      if ($twins === true) {
        if (!empty($DBInfo->use_twinpages)) {
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

    # is it redirect page ?
    if (isset($pi['#redirect'][0]) and
        empty($this->wikimarkup))
    {
      $url = $pi['#redirect'];
      $anchor = '';
      if (($p = strpos($url, '#')) > 0) {
        $anchor = substr($url, $p);
        $url = substr($url, 0, $p);
      }
      $url=_rawurlencode($url);

      $lnk = $this->link_tag($url,
        '?action=show'.$anchor,
        $pi['#redirect']);
      $msg = _("Redirect page");
      $this->write("<div class='wikiRedirect'><span>$msg</span><p>".$lnk."</p></div>");
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

    $is_writable = 1;
    if (!$DBInfo->security->writable($options))
      $is_writable = 0;

    $text='';
    $in_p='';
    $in_div=0;
    $in_bq=0;
    $in_li=0;
    $in_pre=0;
    $in_table=0;
    $li_open=0;
    $li_empty=0;
    $div_enclose='';
    $indent_list[0]=0;
    $indent_type[0]="";
    $_myindlen=array(0);
    $oline='';
    $pre_line = '';

    $wordrule="\[\[(?:[A-Za-z0-9]+(?:\((?:(?<!\]\]).)*\))?)\]\]|". # macro
              "<<(?:[^<>]+(?:\((?:(?<!\>\>).)*\))?)>>|"; # macro
    if ($DBInfo->inline_latex) # single line latex syntax
      $wordrule.="(?<=\s|^|>)\\$(?!(?:Id|Revision))(?:[^\\$]+)\\$(?=\s|\.|,|<|$)|".
                 "(?<=\s|^|>)\\$\\$(?:[^\\$]+)\\$\\$(?=\s|<|$)|";
    #if ($DBInfo->builtin_footnote) # builtin footnote support
    $wordrule.=$this->wordrule;
    $wordrule.='|'.$this->footrule;

    $formatter=&$this;

    $ii = isset($pi['start_line']) ? $pi['start_line'] : 0;
    if (isset($formatter->pi['#linenum']) and empty($formatter->pi['#linenum']))
      $this->linenum = -99999;
    else
      $this->linenum = $ii;

    $lcount = count($lines);
    for (; $ii < $lcount; $ii++) {
      $line = $lines[$ii];
      $this->linenum++;
      $lid = $this->linenum;
      # empty line
      if (!strlen($line) and empty($oline)) {
        if ($in_pre) { $pre_line.="\n";continue;}
        if ($in_li) {
          if ($in_table) {
            $text.=$this->_table(0,$dumm);$in_table=0;$li_empty=1;
          }
          if ($indent_type[$in_li] == 'dq') {
            // close all tags for quote blocks '> '
            while($in_li >= 0 && $indent_list[$in_li] > 0) {
               if (!in_array($indent_type[$in_li], array('dd', 'dq')) && $li_open == $in_li)
                 $text.=$this->_li(0,$li_empty);
               $text.=$this->_list(0,$indent_type[$in_li],"",
                 $indent_type[$in_li-1]);
               unset($indent_list[$in_li]);
               unset($indent_type[$in_li]);
               unset($_myindlen[$in_li]);
               $in_li--;
            }
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
          if ($in_bq) { $text.= str_repeat("</blockquote>\n", $in_bq); $in_bq = 0; }
          if ($in_p) { $text.=$this->_div(0,$in_div,$div_enclose)."<br />\n"; $in_p='';}
          else if ($in_p=='') { $text.="<br />\n";}
          continue;
        }
      }

      // comments
      if (!$in_pre and isset($line[1]) and $line[0]=='#' and $line[1]=='#') {

        if ($this->wikimarkup) {
          $out = $line.'<br />';
          $nline=str_replace(array('=','-','&','<'),array('==','-=','&amp;','&lt;'),$line);
          $text=$text."<span class='wikiMarkup'><!-- wiki:\n$nline\n\n-->$out</span>";
        }
        continue;
      }

      if ($in_pre) {
        $pre_line.= "\n".$line;
        if (preg_match("/^({{{(?:(?:[^{}]+|{[^{}]+}(?!})|(?<!{){{1,2}(?!{)|(?<!})}{1,2}(?!})|(?<=\\\\)[{}]{3}(?!}))|(?1))*+}}})/x",
          $pre_line, $match)) {

          $p = strlen($match[1]);
          $line = substr($pre_line, $p);
          $pre_line = $match[1];

          if ($in_table || (!empty($oline) and preg_match('/^\s*\|\|/', $oline))) {
            $pre_line = str_replace('||', "\002\003", $pre_line); // escape || chars
            $line = $pre_line.$line;
            $in_pre = 0;
          } else {
            $pre_line = substr($pre_line, 3, -3); // strip {{{, }}}

            // strip the blockquote markers '> ' from the pre block
            if ($in_bq > 0 and preg_match("/\n((?:\>\s)*\>\s?)/s", $pre_line, $match))
              $pre_line = str_replace("\n".$match[1], "\n", $pre_line);
            $in_pre = -1;
          }
        } else {
          continue;
        }
      } else {
        $chunk = preg_replace_callback(
                    "/(({{{(?:(?:[^{}]+|{[^{}]+}(?!})|(?<!{){{1,2}(?!{)|(?<!})}{1,2}(?!})|(?<=\\\\)[{}]{3}(?!}))|(?2))*+}}})|".
                    // unclosed inline pre tags
                    "(?:(?!<{{{){{{}}}(?!}}})|{{{(?:{{{|}}})}}}))/x",
                    create_function('$m', 'return str_repeat("_", strlen($m[1]));'), $line);
        if (($p = strpos($chunk, '{{{')) !== false) {
          $processor = '';
          $in_pre = 1;

          $pre_line = substr($line, $p);

          if (!isset($line[0]) and !empty($this->auto_linebreak)) $this->nobr = 1;

          // check processor
          $t = isset($line[$p+3]);
          if ($t and $line[$p+3] == '#' and $line[$p+4] == '!') {
            $dummy = explode(' ', substr($line, $p+5), 2);
            $tag = $dummy[0];

            if (!empty($tag)) $processor = $tag;
          }
          $line = substr($line, 0, $p);
        }
      }

      $ll=strlen($line);
      if ($ll and $line[$ll-1]=='&') {
        $oline.=substr($line,0,-1);
        continue;
      } else if (preg_match('/^\s*\|\|/',$line) and $in_pre) {
        // "||{{{foobar..." case
        $oline.= isset($oline[0]) ? "\n".$line : $line;
        continue;
      } else if (!isset($oline[0]) and preg_match('/^\s*\|\|/',$line) and !preg_match('/\|(\||-+)\s*$/',$line)) {
        $oline.= $line;
        continue;
      } else if (!empty($oline)
          and ($in_table or preg_match('/^\s*\|\|/',$oline))
          and !preg_match('/\|(\||-+)\s*$/',$line) and isset($lines[$ii + 1])) {
          // not closed table and not reached at the end line
        $oline.= "\n".$line;
        continue;
      } else {
        $line = isset($oline[0]) ? $oline."\n".$line : $line;
        $oline='';
      }

      $p_closeopen='';
      if (preg_match('/^[ ]*(-{4,})$/',$line, $m)) {
        $func = $DBInfo->hr_type.'_hr';
        $line = $formatter->$func($m[1]);
        if ($this->auto_linebreak) $this->nobr=1; // XXX
        if ($in_bq) { $p_closeopen.= str_repeat("</blockquote>\n", $in_bq); $in_bq = 0; }
        if ($in_p) { $p_closeopen.=$this->_div(0,$in_div,$div_enclose); $in_p='';}
      } else {
        if ($in_p == '' and $line!=='') {
          $p_closeopen=$this->_div(1,$in_div,$div_enclose, $lid > 0 ? ' id="aline-'.$lid.'"' : '');
          $in_p= $line;
        }

        // split into chunks. nested {{{}}} and [ ] inline elems
        $chunk=preg_split("/({{{
                        (?:(?:[^{}]+|
                        {[^{}]+}(?!})|
                        (?<!{){{1,2}(?!{)|
                        (?<!})}{1,2}(?!})|
                        (?<=\\\\)[{}]{3}(?!}))|(?1)
                          )++}}}|
                        \[ (?: (?>[^\[\]]+) | (?R) )* \])/x",$line,-1,PREG_SPLIT_DELIM_CAPTURE);
        $inline = array(); // save inline nowikis

        if (count($chunk) > 1) {
          // protect inline nowikis
          $nc = '';
          $k = 1;
          $idx = 1;
          foreach ($chunk as $c) {
            if ($k % 2) {
              $nc.= $c;
            } else if (in_array($c[3],array('#','-','+'))) { # {{{#color text}}}
              $nc.= $c;
            } else {
              $inline[$idx] = $c;
              $nc.= "\017".$idx."\017";
              $idx++;
            }
            $k++;
          }
          $line = $nc;
        }

        if (($len = strlen($line)) > 10000) {
          // XXX too long string will crash at preg_replace() with PHP 5.3.8
          $new = '';
          $start = 0;
          while (($start + 10000) < $len && ($pos = strpos($line, "\n", $start + 10000)) > 0) {
            $chunk = substr($line, $start, $pos - $start + 1);#.'<font color="#ff0000">xxxxxx</font>';
            $new.= preg_replace($this->baserule, $this->baserepl, $chunk);
            $start = $pos + 2;
          }
          $new.= preg_replace($this->baserule,$this->baserepl, substr($line, $start));
          $line = $new;
          //$line = preg_replace($this->baserule,$this->baserepl,$line);
        } else {
          $line = preg_replace($this->baserule,$this->baserepl,$line);
        }

        // restore inline nowikis
        if (!empty($inline)) {
          $this->_array_callback($inline, true);
          $line = preg_replace_callback("/\017(\d+)\017/",
            array(&$this, '_array_callback'), $line);
        }
      }

      // blockquote
      if ($in_pre != -1 and (!$in_table or !isset($oline[0])) and $line[0] == '>' and preg_match('/^((?:>\s)*>\s?(?!>))/', $line, $match)) {
        $tmp = strlen($match[1]);
        $line = substr($line, $tmp); // strip markers
        $tmp+= ($tmp % 2 != 0) ? 1 : 0;
        $depth = $tmp / 2;
        if ($depth == $in_bq) {
          // continue
        } if ($depth > $in_bq) {
          $p_closeopen.= str_repeat("<blockquote class='quote'>", $depth - $in_bq);
          $in_bq = $depth;
        } else {
          $p_closeopen.= str_repeat("</blockquote>\n", $in_bq - $depth);
          $in_bq = $depth;
        }
      } else if (!$in_pre and $in_bq > 0) {
        $p_closeopen.= str_repeat("</blockquote>\n", $in_bq);
        $in_bq = 0;
      }
      #if ($in_p and ($in_pre==1 or $in_li)) $line=$this->_check_p().$line;

      # bullet and indentation
      # and quote begin with ">"
      if ($in_pre != -1 &&
        preg_match("/^(((>\s)*>(?!>))|(\s*>*))/",$line,$match)) {
      #if (preg_match("/^(\s*)/",$line,$match)) {
         #echo "{".$match[1].'}';
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
             $indtype = 'dq';
             # get user defined style
             if (($line[0]=='.' or $line[0]=='#') and ($p=strpos($line,' '))) {
               $divtype='';
               $mytag=substr($line,1,$p-1);
               if ($line[0]=='.') $mydiv[]=$mytag;
               else $divtype=' id="'.$mytag.'"';
               $divtype.=' class="quote '.implode(' ',$mydiv).'"';
               $line=substr($line,$p+1);
             } else {
               if ($line[0] == ' ') {
                 $line=substr($line,1); // with space
                 $myindlen = $indlen + 1;
               }
               $divtype=' class="quote indent '.$this->quote_style.'"';
             }
           } else {
             $divtype=' class="indent"';
           }

           $numtype = '';
           if ($line[0]=='*') {
             $limatch[1]='*';
             $myindlen=(isset($line[1]) and $line[1]==' ') ? $indlen+2:$indlen+1;
             preg_match("/^(\*\s?)/",$line,$m);
             $liopen='<li>'; // XXX
             $line=substr($line,strlen($m[1]));
             if ($indent_list[$in_li] == $indlen && !in_array($indent_type[$in_li], array('dd', 'dq'))){
                $close.=$this->_li(0);
                $_myindlen[$in_li]=$myindlen;
             }
             $numtype="";
             $indtype="ul";
           } elseif (preg_match("/^(([1-9]\d*|[aAiI])\.)(#\d+)?\s/",$line,$limatch)){
             $myindlen=$indlen+strlen($limatch[1])+1;
             $line=substr($line,strlen($limatch[0]));
             if ($indent_list[$in_li] == $indlen && !in_array($indent_type[$in_li], array('dd', 'dq'))) {
                $close.=$this->_li(0);
                $_myindlen[$in_li]=$myindlen;
             }
             $numtype=$limatch[2][0];
             if (isset($limatch[3]))
               $numtype.=substr($limatch[3],1);
             $indtype="ol";
             $lival='';
             if ($in_li and isset($limatch[3]))
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
         if ($indent_list[$in_li] > $indlen ||
            $indtype != 'dd' && $indent_type[$in_li][1] != $indtype[1]) {
           $fixlen = $indlen;
           if ($indent_list[$in_li] == $indlen and
               $indlen > 0 and $in_li > 0 and $indent_type[$in_li] != $indtype)
             $fixlen = $indent_type[$in_li - 1]; // close prev tags

            while($in_li >= 0 && $indent_list[$in_li] > $fixlen) {
               if (!in_array($indent_type[$in_li], array('dd', 'dq')) && $li_open == $in_li)
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
         if ($indent_list[$in_li] < $indlen) {
            $in_li++;
            $indent_list[$in_li]=$indlen; # add list depth
            $_myindlen[$in_li]=$myindlen; # add list depth
            $indent_type[$in_li]=$indtype; # add list type
            $open.=$this->_list(1,$indtype,$numtype,'',$divtype);
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
      } elseif ($in_table && ($line[0]!='|' or
              !preg_match("/^\|{2}.*(?:\|(\||-+))$/s",rtrim($line)))) {
        $close=$this->_table(0,$dumm).$close;
        $in_table=0;
      }
      $skip_link = false;
      while ($in_table) {
        $line=preg_replace('/(\|\||\|-+)$/','',rtrim($line));
        {
          $skip_link = strpos($line, "\n") !== false;
          $tr_attr='';
          $row=$this->_td($line, $tr_attr, $skip_link ? $wordrule : '');
          if ($lid > 0) $tr_attr.= ' id="line-'.$lid.'"';
          $line="<tr $tr_attr>".$row.'</tr>';
          $tr_attr='';
          $lid = '';
        }

        $line=str_replace('\"','"',$line); # revert \\" to \"
        break;
      }

      # InterWiki, WikiName, {{{ }}}, !WikiName, ?single, ["extended wiki name"]
      # urls, [single bracket name], [urls text], [[macro]]

      if (!$skip_link)
      $line=preg_replace_callback("/(".$wordrule.")/",
        array(&$this,'link_repl'),$line);
      #$line=preg_replace("/(".$wordrule.")/e","\$this->link_repl('\\1')",$line);

      # Headings
      while (preg_match("/(?<!=)(={1,})\s+(.*)\s+\\1\s?$/",$line,$m)) {
        if ($in_bq) {
          $dummy = null;
          $line = $this->head_repl(strlen($m[1]), $m[2], $dummy);
          break;
        }
        $this->sect_num++;
        #if ($p_closeopen) { // ignore last open
        #  #$p_closeopen='';
        #  $p_closeopen.= '}}'.$this->_div(0,$in_div,$div_enclose);
        #}

        while($in_div > 0)
          $p_closeopen.=$this->_div(0,$in_div,$div_enclose);
        $p_closeopen.=$this->_div(1,$in_div,$div_enclose, ' class="section"');
        $in_p='';
        $edit = ''; $anchor = '';
        if ($is_writable && $this->section_edit && empty($this->preview)) {
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
        $attr = $lid > 0 ? ' id="line-'.$lid.'"' : '';
        $lid = '';

        $line=$anchor.$edit.$this->head_repl(strlen($m[1]),$m[2],$headinfo,$attr);
        $dummy='';
        $line.=$this->_div(1,$in_div,$dummy,' id="sc-'.$this->sect_num.'"'); // for folding
        $edit='';$anchor='';
        break;
      }

      # Smiley
      if (!empty($this->use_smileys) and empty($this->smiley_rule))
        $this->initSmileys();

      if (!empty($this->smiley_rule)) {
        $chunk = preg_split("@(<tt[^>]*>.*</tt>)@", $line, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (count($chunk) > 1) {
          $nline = '';
          $k = 1;
          foreach ($chunk as $c) {
            if ($k % 2) {
              if (isset($c[0]))
                $nline.= preg_replace_callback($this->smiley_rule,
                            array(&$this, 'smiley_repl'), $c);
            } else {
              $nline.= $c;
            }
            $k++;
          }
          $line = $nline;
        } else {
          $line = preg_replace_callback($this->smiley_rule,
                      array(&$this, 'smiley_repl'), $line);
        }
      }

      if (!empty($this->extrarule))
      $line=preg_replace($this->extrarule,$this->extrarepl,$line);
      #if ($this->auto_linebreak and preg_match('/<div>$/',$line))
      #  $this->nobr=1;

      $line=$close.$p_closeopen.$open.$line;
      $open="";$close="";

      if ($in_pre==-1) {
         $in_pre=0;

         # for smart diff
         $show_raw=0;
         if ($this->use_smartdiff and
           preg_match("/\006|\010/", $pre_line)) $show_raw=1;

         // revert escaped {{{, }}}
         $pre_line = strtr($pre_line, array('\\}}}'=>'}}}', '\\{{{'=>'{{{')); // FIXME

         if ($processor and !$show_raw) {
           $value=&$pre_line;
           if ($processor == 'wiki') {
             $processor = 'monimarkup';
             if (isset($options['notoc']))
               $save_toc = $options['notoc'];
             $options['notoc'] = 1;
           }
           $out= $this->processor_repl($processor,$value,$options);
           if (isset($save_toc)) {
             // do not shoe edit section link in the processor mode
             $options['notoc'] = $save_toc;
             unset($save_toc);
           }
           #if ($this->wikimarkup)
           #  $line='<div class="wikiMarkup">'."<!-- wiki:\n{{{".
           #    $value."}}}\n-->$out</div>";
           #else
           #  $line=$out.$line;
           $line=$out.$line;
           unset($out);
         } else {
            # htmlfy '<', '&'
            if (!empty($DBInfo->default_pre)) {
              $out=$this->processor_repl($DBInfo->default_pre,$pre_line,$options);
            } else {
              $pre=str_replace(array('&','<'),
                               array("&amp;","&lt;"),
                               $pre_line);
              $pre=preg_replace("/&lt;(\/?)(ins|del)/","<\\1\\2",$pre);
              # FIXME Check open/close tags in $pre
              #$out="<pre class='wiki'>\n".$pre."</pre>";
              $out="<pre class='wiki'>".$pre."</pre>";
              if ($this->wikimarkup) {
                $nline=str_replace(array('=','-','&','<'),array('==','-=','&amp;','&lt;'),$pre_line);
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

      $lidx = '';
      if ($lid > 0) $lidx = "<span class='line-anchor' id='line-".$lid."'></span>";

      if (isset($line[0]) and $this->auto_linebreak && !$in_table && !$this->nobr)
        $text.=$line.$lidx."<br />\n"; 
      else
        $text.=$line ? $line.$lidx."\n":'';
      $this->nobr=0;
      # empty line for quoted div
      if (!$this->auto_linebreak and !$in_pre and trim($line) =='')
        $text.="<br />\n";

    } # end rendering loop
    # for smart_diff (div)
    if ($this->use_smartdiff)
      $text= preg_replace_callback(array("/(\006|\010)(.*)\\1/sU"),
          array(&$this,'_diff_repl'),$text);

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
    if ($in_pre) {
      // fail to close pre tag
      $text.= $this->processor_repl($processor, $pre_line, $options);
    }
    if ($in_table) $close.="</table>\n";
    # close indent
    while($in_li >= 0 && $indent_list[$in_li] > 0) {
      if (!in_array($indent_type[$in_li], array('dd', 'dq')) && $li_open == $in_li) // XXX
        $close.=$this->_li(0);
#     $close.=$this->_list(0,$indent_type[$in_li]);
      $close.=$this->_list(0,$indent_type[$in_li],"",$indent_type[$in_li-1]);
      unset($indent_list[$in_li]);
      unset($indent_type[$in_li]);
      $in_li--;
    }
    # close div
    #if ($in_p) $close.="</div>\n"; # </para>
    if ($in_bq) { $close.= str_repeat("</blockquote>\n", $in_bq); $in_bq = 0; }
    if ($in_p) $close.=$this->_div(0,$in_div,$div_enclose); # </para>
    #if ($div_enclose) $close.=$this->_div(0,$in_div,$div_enclose);
    while($in_div > 0)
      $close.=$this->_div(0,$in_div,$div_enclose);

    # activate <del></del> tag
    #$text=preg_replace("/(&lt;)(\/?del>)/i","<\\2",$text);
    $text.=$close;
  
    # postamble
    $this->postambles();

    if (empty($options['nojavascript']))
      echo $this->get_javascripts();
    echo $text;
    if (!empty($this->sisters) and empty($options['nosisters'])) {
      $sister_save=$this->sister_on;
      $this->sister_on=0;
      $sisters=implode("\n",$this->sisters);
      $sisters = preg_replace_callback("/(".$wordrule.")/",
              array(&$this, 'link_repl'), $sisters);
      $msg=_("Sister Sites Index");
      echo "<div id='wikiSister'>\n<div class='separator'><tt class='foot'>----</tt></div>\n$msg<br />\n<ul>$sisters</ul></div>\n";
      $this->sister_on=$sister_save;
    }

    if (!empty($this->foots))
      echo $this->macro_repl('FootNote','',$options);

    if (!empty($this->update_pagelinks) and !empty($options['pagelinks']))
      store_pagelinks($this->page->name, array_keys($this->pagelinks));
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
      $cache=new Cache_text('js', array('ext'=>'html'));

      if ($cache->exists($uniq)) {
        foreach ($keys as $k) $this->java_scripts[$k]='';
        return $cache->fetch($uniq);
      }

      foreach ($this->java_scripts as $k=>$js) {
        if ($js) {
          if ($js{0} != '<') {
            $async = '';
            if (strpos($js, ',') !== false && substr($js, 0, 5) == 'async') {
              $async = ' async';
              $js = substr($js, 6);
            }
            if (preg_match('@^(http://|/)@',$js)) {
              $out.="<script$async type='text/javascript' src='$js'></script>\n";
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
                $out.="<script$async type='text/javascript' src='$js'></script>\n";
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

      $fc = new Cache_text('js', array('ext'=>'js', 'dir'=>$Config['cache_public_dir']));
      $jsname = $fc->getKey($suniq,0);
      $out.='<script type="text/javascript" src="'.$Config['cache_public_url'].'/'.$jsname.'"></script>'."\n";
      $cache->update($uniq,$out);

      $ver = FCKJavaScriptCompressor::Revision();
      $header='/* '.JS_PACKER.' '.$ver.' '.md5($packed).' '.date('Y-m-d H:i:s').' */'."\n";
      # save real compressed js file.
      $fc->_save($jsname, $header.$packed);
      return $out;
    }
    $out='';
    foreach ($this->java_scripts as $k=>$js) {
      if ($js) {
        if ($js{0} != '<') {
          $async = '';
          if (strpos($js, ',') !== false && substr($js, 0, 5) == 'async') {
            $async = ' async';
            $js = substr($js, 6);
          }
          if (!preg_match('@^(http://|/)@',$js))
            $js=$this->url_prefix.'/local/'.$js;
          $out.="<script$async type='text/javascript' src='$js'></script>\n";
        } else {
          $out.=$js;
        }
        $this->java_scripts[$k]='';
      }
    }
    return $out;
  }

  function get_diff($text, $rev = '') {
    global $DBInfo;

    if (!isset($text[0])) return '';
    if (!empty($DBInfo->use_external_diff)) {
      $tmpf2 = tempnam($DBInfo->vartmp_dir, 'DIFF_NEW');
      $fp = fopen($tmpf2, 'w');
      if (!is_resource($fp)) return ''; // ignore
      fwrite($fp, $text);
      fclose($fp);

      $fp = popen('diff -u '.$this->page->filename.' '.$tmpf2.$this->NULL, 'r');
      if (!is_resource($fp)) {
        unlink($tmpf2);
        return '';
      }
      $out = '';
      while (!feof($fp)) {
        $line = fgets($fp, 1024);
        $out.= $line;
      }
      pclose($fp);
      unlink($tmpf2);
    } else {
      require_once('lib/difflib.php');
      $orig = $this->page->_get_raw_body();
      $olines = explode("\n", $orig);
      $tmp = array_pop($olines);
      if ($tmp != '') $olines[] = $tmp;
      $nlines = explode("\n", $text);
      $tmp = array_pop($nlines);
      if ($tmp != '') $nlines[] = $tmp;
      $diff = new Diff($olines, $nlines);
      $unified = new UnifiedDiffFormatter;
      $unified->trailing_cr = "&nbsp;\n"; // hack to see inserted empty lines
      $out.= $unified->format($diff);
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
    if (isset($options['action'][0]) and $options['action'] == 'print') $media = '';

    if (isset($this->pi['#redirect'][0]) && !empty($options['pi'])) {
      $options['value']=$this->pi['#redirect'];
      $options['redirect']=1;
      $this->pi['#redirect']='';
      do_goto($this,$options);
      return true;
    }
    $header = !empty($header) ? $header:(!empty($options['header']) ? $options['header']:null) ;

    if (!empty($header)) {
      foreach ((array)$header as $head) {
        $this->header($head);
        if (preg_match("/^content\-type: text\//i",$head))
          $plain=1;
      }
    }
    $mtime = isset($options['mtime']) ? $options['mtime'] : $this->page->mtime();
    if (is_static_action($options) or
        (!empty($DBInfo->use_conditional_get) and !empty($mtime)
        and empty($options['nolastmod'])
        and $this->page->is_static))
    {
      $lastmod = gmdate('D, d M Y H:i:s \G\M\T', $mtime);
      $this->header('Last-Modified: '.$lastmod);
      $etag = $this->page->etag($options);
      if (!empty($options['etag']))
        $this->header('ETag: "'.$options['etag'].'"');
      else
        $this->header('ETag: "'.$etag.'"');

      $meta_lastmod = '<meta http-equiv="last-modified" content="'.$lastmod.'" />'."\n";
    }

    $content_type=
      isset($DBInfo->content_type[0]) ? $DBInfo->content_type : 'text/html';

    $force_charset = '';
    if (!empty($DBInfo->force_charset))
      $force_charset = '; charset='.$DBInfo->charset;

    if (!$plain)
      $this->header('Content-type: '.$content_type.$force_charset);

    if (!empty($options['action_mode']) and $options['action_mode'] =='ajax') return true;

    # disabled
    #$this->header("Vary: Accept-Encoding, Cookie");
    #if (strstr($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') and function_exists('ob_gzhandler')) {
    #  ob_start('ob_gzhandler');
    #  $etag.= '.gzip';
    #}

    if (!empty($options['metatags']))
      $metatags = $options['metatags'];
    else
      $metatags = $DBInfo->metatags;

    if (!empty($options['noindex']) || !empty($this->pi['#noindex']) ||
        (!empty($mtime) and !empty($DBInfo->delayindex) and ((time() - $mtime) < $DBInfo->delayindex))) {
      // delay indexing like as dokuwiki
      if (preg_match("/<meta\s+name=('|\")?robots\\1[^>]+>/i", $metatags)) {
        $metatags = preg_replace("/<meta\s+name=('|\")?robots\\1[^>]+>/i",
            '<meta name="robots" content="noindex,nofollow" />',
            $metatags);
      } else {
        $metatags.= '<meta name="robots" content="noindex,nofollow" />'."\n";
      }
    }
    if (isset($DBInfo->metatags_extra))
      $metatags.= $DBInfo->metatags_extra;

    $js=!empty($DBInfo->js) ? $DBInfo->js : '';

    if (!$plain) {
      if (isset($options['trail']))
        $this->set_trailer($options['trail'],$this->page->name);
      else if ($DBInfo->origin)
        $this->set_origin($this->page->name);

      # find upper page
      $up_separator = '/';
      if (!empty($this->use_namespace)) $up_separator.= '|\:';
      $pos=0;
      preg_match('@(' . $up_separator . ')@',$this->page->name,$sep); # NameSpace/SubPage or NameSpace:SubNameSpacePage
      if (isset($sep[1])) $pos=strrpos($this->page->name,$sep[1]);
      if ($pos > 0) $upper=substr($this->page->urlname,0,$pos);
      else if ($this->group) $upper=_urlencode(substr($this->page->name,strlen($this->group)));
      $keywords = '';
      if (!empty($this->pi['#keywords']))
        $keywords='<meta name="keywords" content="'._html_escape($this->pi['#keywords']).'" />'."\n";
      else if (!empty($DBInfo->use_keywords)) {
        $keywords=strip_tags($this->page->title);
        $keywords=str_replace(" ",", ",$keywords); # XXX
        $keywords=_html_escape($keywords);
        $keywords="<meta name=\"keywords\" content=\"$keywords\" />\n";
      }
      # find sub pages
      if (empty($options['action']) and !empty($DBInfo->use_subindex)) {
        $scache= new Cache_text('subpages');
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
          if (empty($DBInfo->use_ajax)) {
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
        $options['title']=!empty($this->pi['#title']) ? $this->pi['#title']:
          $this->page->title;
        $options['title']=
          _html_escape($options['title']);
      } else {
        $options['title'] = strip_tags($options['title']);
      }
      $theme_type = !empty($this->_newtheme) ? $this->_newtheme : '';
      if (empty($options['css_url'])) $options['css_url']=$DBInfo->css_url;
      if (empty($this->pi['#nodtd']) and !isset($options['retstr']) and $theme_type != 2) echo $DBInfo->doctype;
      if ($theme_type == 2 or isset($options['retstr']))
        ob_start();
      else
        echo "<head>\n";

      echo '<meta http-equiv="Content-Type" content="'.$content_type.
        ';charset='.$DBInfo->charset."\" />\n";
      echo <<<JSHEAD
<script type="text/javascript">
/*<![CDATA[*/
_url_prefix="$DBInfo->url_prefix";
/*]]>*/
</script>
JSHEAD;
      echo $metatags,$js,"\n";
      echo $this->get_javascripts();
      echo $keywords;
      if (!empty($meta_lastmod)) echo $meta_lastmod;
      echo "  <title>$DBInfo->sitename: ",$options['title'],"</title>\n";
      if (!empty($upper))
        echo '  <link rel="Up" href="',$this->link_url($upper),"\" />\n";
      $raw_url=$this->link_url($this->page->urlname,"?action=raw");
      $print_url=$this->link_url($this->page->urlname,"?action=print");
      echo '  <link rel="Alternate" title="Wiki Markup" href="',
        $raw_url,"\" />\n";
      echo '  <link rel="Alternate" media="print" title="Print View" href="',
        $print_url,"\" />\n";
      if ($options['css_url']) {
        $css_url = _html_escape($options['css_url']);
        echo '  <link rel="stylesheet" type="text/css" ',$media,' href="',
          $css_url."\" />\n";
        if (file_exists('./css/_user.css')) // FIXME
          echo '  <link rel="stylesheet" media="screen" type="text/css" href="',
            $DBInfo->url_prefix,"/css/_user.css\" />\n";
      }

      echo kbd_handler(!empty($options['prefix']) ? $options['prefix'] : '');

      if ((isset($this->_newtheme) and $this->_newtheme == 2) or isset($options['retstr'])) {
        $ret = ob_get_contents();
        ob_end_clean();
        if (isset($options['retstr']))
          $options['retstr'] = $ret;
        $this->header_html = $ret;
      } else {
        echo "</head>\n";
      }
    }
    return true;
  }

  function get_actions($args='',$options) {
    $menu=array();
    if (!empty($this->pi['#action']) && !in_array($this->pi['#action'],$this->actions)){
      $tmp =explode(" ",$this->pi['#action'],2);
      $act = $txt = $tmp[0];
      if (!empty($tmp[1])) $txt = $tmp[1];
      $menu[]= $this->link_to("?action=$act",_($txt)," rel='nofollow' accesskey='x'");
      if (strtolower($act) == 'blog')
        $this->actions[]='BlogRss';
        
    } else if (!empty($args['editable'])) {
      if ($args['editable']==1)
        $menu[]= $this->link_to("?action=edit",_("EditText")," rel='nofollow' accesskey='x'");
      else
        $menu[]= _("NotEditable");
    } else
      $menu[]= $this->link_to('?action=show',_("ShowPage"));

    if (!empty($args['refresh']) and $args['refresh'] ==1)
      $menu[]= $this->link_to("?refresh=1",_("Refresh")," rel='nofollow' accesskey='n'");
    $menu[]=$this->link_tag("FindPage","",_("FindPage"));

    if (empty($args['noaction'])) {
      foreach ($this->actions as $action) {
        if (strpos($action,' ')) {
          list($act,$text)=explode(' ',$action,2);
          if ($options['page'] == $this->page->name) {
            $menu[]= $this->link_to($act,_($text));
          } else {
            $menu[]= $this->link_tag($options['page'],$act,_($text));
          }
        } else {
          $menu[]= $this->link_to("?action=$action",_($action), " rel='nofollow'");
        }
      }
    }
    return $menu;
  }

  function send_footer($args='',$options=array()) {
    global $DBInfo;

    empty($options) ? $options = array('id'=>'Anonymous',
                                  'tz_offset'=>$this->tz_offset,
                                  'page'=>$this->page->name) : null;
    

    if (!empty($options['action_mode']) and $options['action_mode'] =='ajax') return;

    echo "<!-- wikiBody --></div>\n";
    echo $DBInfo->hr;
    if (!empty($args['editable']) and !$DBInfo->security->writable($options))
      $args['editable']=-1;

    $key=$DBInfo->pageToKeyname($options['page']);
    if (!in_array('UploadedFiles',$this->actions) and is_dir($DBInfo->upload_dir."/$key"))
      $this->actions[]='UploadedFiles';

    $menus=$this->get_actions($args,$options);

    $hide_actions=!empty($DBInfo->hide_actions) ? $DBInfo->hide_actions : 0;
    $hide_actions+= $this->popup;
    $menu = '';
    if (!$hide_actions or
      ($hide_actions and $options['id']!='Anonymous')) {
      if (!$this->css_friendly) {
        $menu=$this->menu_bra.implode($this->menu_sep,$menus).$this->menu_cat;
      } else {
        $menu="<div id='wikiAction'>";
        $menu.='<ul><li class="first">'.implode("</li>\n<li>\n",$menus)."</li></ul>";
        $menu.="</div>";
      }
    }

    if ($mtime=$this->page->mtime()) {
      $lastedit=gmdate("Y-m-d",$mtime+$options['tz_offset']);
      $lasttime=gmdate("H:i:s",$mtime+$options['tz_offset']);
    }

    $validator_xhtml=!empty($DBInfo->validator_xhtml) ? $DBInfo->validator_xhtml:'http://validator.w3.org/check/referer';
    $validator_css=!empty($DBInfo->validator_css) ? $DBInfo->validator_xhtml:'http://jigsaw.w3.org/css-validator';

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

    $timer = '';
    if (isset($options['timer']) and is_object($options['timer'])) {
      $options['timer']->Check();
      $timer=$options['timer']->Total();
    }

    if (file_exists($this->themedir."/footer.php")) {
      $themeurl=$this->themeurl;
      include($this->themedir."/footer.php");
    } else {
      echo "<div id='wikiFooter'>";
      echo $menu;
      if (!$this->css_friendly) echo $banner;
      else echo "<div id='wikiBanner'>$banner</div>\n";
      echo "\n</div>\n";
    }
    if (empty($this->_newtheme) or $this->_newtheme != 2)
      echo "</body>\n</html>\n";
    #include "prof_results.php";
  }

  function send_title($msgtitle="", $link="", $options="") {
    // Generate and output the top part of the HTML page.
    global $DBInfo;

    if (!empty($options['action_mode']) and $options['action_mode']=='ajax') return;

    $name=$this->page->urlname;
    $action=$this->link_url($name);
    $saved_pagelinks = $this->pagelinks;

    # find upper page
    $up_separator = '/';
    if (!empty($this->use_namespace)) $up_separator.= '|\:';
    $pos=0;
    preg_match('@(' . $up_separator . ')@',$name,$sep); # NameSpace/SubPage or NameSpace:SubNameSpacePage
    if (isset($sep[1])) $pos=strrpos($name,$sep[1]);
    $mypgname=$this->page->name;
    $upper_icon = '';
    if ($pos > 0) {
      $upper=substr($name,0,$pos);
      $upper_icon=$this->link_tag($upper,'',$this->icon['upper'])." ";
    } else if (!empty($this->group)) {
      $group=$this->group;
      $mypgname=substr($this->page->name,strlen($group));
      $upper=_urlencode($mypgname);
      $upper_icon=$this->link_tag($upper,'',$this->icon['main'])." ";
    }

    $title = '';
    if (isset($this->pi['#title']))
      $title=_html_escape($this->pi['#title']);
    if (!empty($msgtitle)) {
      $msgtitle = _html_escape($msgtitle);
    } else if (isset($options['msgtitle'])) {
      $msgtitle = $options['msgtitle'];
    }

    if (empty($msgtitle) and !empty($options['title'])) $msgtitle=$options['title'];
    $groupt = '';
    if (empty($title)) {
      if (!empty($group)) { # for UserNameSpace
        $title=$mypgname;
        $groupt=substr($group,0,-1).' &raquo;'; // XXX
        $groupt=
          "<span class='wikiGroup'>$groupt</span>";
      } else {
        $groupt = '';
        $title=$this->page->title;
      }
      $title=_html_escape($title);
    }
    # setup title variables
    #$heading=$this->link_to("?action=fullsearch&amp;value="._urlencode($name),$title);
    $qext = '';
    if (!empty($DBInfo->use_backlinks)) $qext='&amp;backlinks=1';
    if (isset($link[0]))
      $title="<a href=\"$link\">$title</a>";
    else if (empty($options['nolink']))
      $title=$this->link_to("?action=fullsearch$qext&amp;value="._urlencode($mypgname),$title);

    if (isset($this->pi['#notitle']))
      $title = '';
    else
      $title=$groupt."<span class='wikiTitle'>$title</span>";

    $logo=$this->link_tag($DBInfo->logo_page,'',$DBInfo->logo_string);
    $goto_form=$DBInfo->goto_form ?
      $DBInfo->goto_form : goto_form($action,$DBInfo->goto_type);

    if (!empty($options['msg']) or !empty($msgtitle)) {
      $msgtype = isset($options['msgtype']) ? ' '.$options['msgtype']:' warn';
      
      $mtitle0=!empty($options['msg']) ? $options['msg'] : '';
      $mtitle=!empty($msgtitle) ? "<h3>".$msgtitle."</h3>\n":"";
      $msg=<<<MSG
<div class="message" id="wiki-message"><span class='$msgtype'>
$mtitle$mtitle0</span>
</div>
MSG;
      if (isset($DBInfo->hide_log) and $DBInfo->hide_log > 0 and preg_match('/timer/', $msgtype)) {
        $time = intval($DBInfo->hide_log * 1000); // sec to ms
        $js = array('js/scriptaculous.js', 'js/effects.js');
        $this->register_javascripts(array($js));
        $msg .= $this->get_javascripts();
          $msg .=<<<MSG
<script type="text/javascript">
/*<![CDATA[*/
Event.observe(window, 'load', function() {
    setTimeout("$('wiki-message').fade()", $time);
});
/*]]>*/
</script>
MSG;
      }
    }

    # navi bar
    $menu=array();
    if (!empty($options['quicklinks'])) {
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
    }

    if (!empty($DBInfo->use_userlink) and isset($quicklinks['UserPreferences']) and $options['id'] != 'Anonymous') {
        $tmpid= 'wiki:UserPreferences '.$options['id'];
        $quicklinks[$tmpid]= $quicklinks['UserPreferences'];
        unset($quicklinks['UserPreferences']);
    }

    $this->forcelink = 1;
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
      $mnuname=_html_escape($this->page->name);
      if ($DBInfo->hasPage($this->page->name)) {
        if (strlen($mnuname) < $len) {
          $menu[$this->page->name]=$this->word_repl($mypgname,$mnuname,$attr);
        } else if (function_exists('mb_strimwidth')) {
          $my=mb_strimwidth($mypgname,0,$len,'...', $DBInfo->charset);
          $menu[$this->page->name]=$this->word_repl($mypgname,_html_escape($my),$attr);
        }
      }
    }
    $this->forcelink = 0;
    $this->sister_on=$sister_save;
    if (empty($this->css_friendly)) {
      $menu=$this->menu_bra.implode($this->menu_sep,$menu).$this->menu_cat;
    } else {
      $cls = 'first';
      $mnu = '';
      foreach ($menu as $k=>$v) {
        if (preg_match('/current/', $v)) {
          $cls .=' current';
        }
        # set current page attribute.
        $mnu.='<li'.(!empty($cls) ? ' class="'. $cls .'"' : '').'>'.$menu[$k]."</li>\n";
        $cls = '';
      }
      $menu='<div id="wikiMenu"><ul>'.$mnu."</ul></div>\n";
    }
    $this->topmenu=$menu;

    # submenu XXX
    if (!empty($this->submenu)) {
      $smenu=array();
      $mnu_pgname=(!empty($group) ? $group.'~':'').$this->submenu;
      if ($DBInfo->hasPage($mnu_pgname)) {
        $pg=$DBInfo->getPage($mnu_pgname);
        $mnu_raw=$pg->get_raw_body();
        $mlines=explode("\n",$mnu_raw);
        foreach ($mlines as $l) {
          if (!empty($mk) and preg_match('/^\s{2,}\*\s*(.*)$/',$l,$m)) {
            if (isset($smenu[$mk]) and !is_array($smenu[$mk])) $smenu[$mk]=array();
            $smenu[$mk][]=$m[1];
            if (isset($smenu[$m[1]])) $smenu[$m[1]]=$mk;
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
      if (empty($DBInfo->no_wikihomepage) and $DBInfo->hasPage($options['id'])) {
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

    if (!empty($DBInfo->check_editable)) {
      if (!$DBInfo->security->is_allowed('edit', $options))
        $this->icons['edit'] = array('', '?action=edit', $this->icon['locked']);
    }

    if (!empty($this->icons)) {
      $icon=array();
      $myicons=array();

      if (!empty($this->icon_list)) {
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
        if (!empty($item[3])) $attr=$item[3];
        else $attr='';
        $icon[]=$this->link_tag($item[0],$item[1],$item[2],$attr);
      }
      $icons=$this->icon_bra.implode($this->icon_sep,$icon).$this->icon_cat;
    }

    $rss_icon=$this->link_tag("RecentChanges","?action=rss_rc",$this->icon['rss'])." ";
    $this->_vars['rss_icon']=&$rss_icon;
    $this->_vars['icons']=&$icons;
    $this->_vars['title']=$title;
    $this->_vars['menu']=$menu;
    isset($upper_icon) ? $this->_vars['upper_icon']=$upper_icon : null;
    isset($home) ? $this->_vars['home']=$home : null;
    if (!empty($options['header']))
      $this->_vars['header'] = $header = $options['header'];
    else if (isset($this->_newtheme) and $this->_newtheme == 2 and !empty($this->header_html))
      $this->_vars['header'] = $header = $this->header_html;

    if ($mtime = $this->page->mtime()) {
      $tz_offset = $this->tz_offset;
      $lastedit = gmdate("Y-m-d", $mtime + $tz_offset);
      $lasttime = gmdate("H:i:s", $mtime + $tz_offset);
      $this->_vars['lastedit'] = $lastedit;
      $this->_vars['lasttime'] = $lasttime;
    }

    # print the title

    if (empty($this->_newtheme) or $this->_newtheme != 2) {
      if (isset($this->_newtheme) and $this->_newtheme != 2)
        echo '<body'.(!empty($options['attr']) ? ' ' . $options['attr'] : '' ) .">\n";
      echo '<div><a id="top" name="top" accesskey="t"></a></div>'."\n";
    }
    #
    if (file_exists($this->themedir."/header.php")) {
      if (!empty($this->trail))
        $trail=&$this->trail;
      if (!empty($this->origin))
        $origin=&$this->origin;

      $subindex=!empty($this->subindex) ? $this->subindex : '';
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
      echo "<div id='wikiHeader'>\n";
      echo $header;
      if (!$this->css_friendly)
        echo $menu." ".$user_link." ".$upper_icon.$icons.$rss_icon;
      else {
        echo "<div id='wikiLogin'>".$user_link."</div>";
        echo "<div id='wikiIcon'>".$upper_icon.$icons.$rss_icon.'</div>';
        echo $menu;
      }
      if (!empty($msg))
        echo $msg;
      echo "</div>\n";
    }
    if (empty($this->popup) and (empty($themeurl) or empty($this->_newtheme))) {
      echo $DBInfo->hr;
      if ($options['trail']) {
        echo "<div id='wikiTrailer'><p>\n";
        echo $this->trail;
        echo "</p></div>\n";
      }
      if (!empty($this->origin)) {
        echo "<div id='wikiOrigin'><p>\n";
        echo $this->origin;
        echo "</p></div>\n";
      }
      if (!empty($this->subindex))
        echo $this->subindex;
    }
    echo "<div id='wikiBody'>\n";
    #if ($this->subindex and !$this->popup and (empty($themeurl) or !$this->_newtheme))
    #  echo $this->subindex;
    $this->pagelinks=$saved_pagelinks;
  }

  function set_origin($pagename) {
    global $DBInfo;

    $orig='';
    $this->forcelink = 1;
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
    $this->forcelink = 0;
  }

  function set_trailer($trailer="",$pagename,$size=5) {
    global $DBInfo;
    if (empty($trailer)) $trail=$DBInfo->frontpage;
    else $trail=$trailer;

    if (is_numeric($DBInfo->trail) and $DBInfo->trail > 5)
      $size = $DBInfo->trail;

    if (empty($DBInfo->jstrail)) {
      $trails=array_diff(explode("\t",trim($trail)),array($pagename));

      $sister_save=$this->sister_on;
      $this->sister_on=0;
      $this->trail="";
      $this->forcelink = 1;
      foreach ($trails as $page) {
        $this->trail.=$this->word_repl('"'.$page.'"','','',1,0).'<span class="separator">'.$DBInfo->arrow.'</span>';
      }
      $this->forcelink = 0;
      $this->trail.= ' '._html_escape($pagename);
      $this->pagelinks=array(); # reset pagelinks
      $this->sister_on=$sister_save;

      if (!in_array($pagename,$trails)) $trails[]=$pagename;

      $idx=count($trails) - $size;
      if ($idx > 0) $trails=array_slice($trails,$idx);
      $trail=implode("\t",$trails);

      setcookie('MONI_TRAIL',$trail,time()+60*60*24*30,get_scriptname());
    } else {
      $pagename = _html_escape($pagename);
      $url = get_scriptname();
      $this->trail = <<<EOF
<script type='text/javascript'>
(function() {
  var url_prefix = "$url";
  var query_prefix = "$DBInfo->query_prefix";
  var trail_size = $size;

  // get trails from cookie
  var cookieName = "MONI_TRAIL=";
  var pos = document.cookie.indexOf(cookieName);
  var trails = [];
  if (pos != -1) {
    var end = document.cookie.indexOf(";", pos + cookieName.length);
    if (end == -1) end = document.cookie.length;

    trails = unescape(document.cookie.substring(pos + cookieName.length, end)).split("\\t");
  } else {
    trails[0] = encodeURIComponent("$DBInfo->frontpage");
  }
  var span = document.createElement("span");

  // render trails
  var str = [];
  var ntrails = [];
  var trail = document.createElement("span");
  var idx = trails.length - trail_size;
  if (idx > 0) trails = trails.splice(idx, trail_size);

  for (var i = 0, j = 0; i < trails.length; i++) {
    var url = escape(trails[i]).replace(/\\+/g, "%20");
    var txt = decodeURIComponent(escape(trails[i])).replace(/\\+/g, " ");
    if (txt == "$pagename") continue;
    str[j] = "<a href='" + url_prefix + query_prefix + url + "'>" + txt + "</a>";
    ntrails[j] = escape(trails[i]);
    j++;
  }
  str[j] = "$pagename";
  ntrails[j] = encodeURIComponent("$pagename");
  document.write(str.join("<span class='separator'>$DBInfo->arrow</span>"));

  // set the trailer again
  var exp = new Date(); // 30-days expires
  exp.setTime(exp.getTime() + 30*24*60*60*1000);
  var cookie = cookieName + ntrails.join("\\t") +
    "; expires=" + exp.toGMTString() +
    "; path=$url";

  document.cookie = cookie;
})();
</script>
EOF;
    }

    $this->_vars['trail']=&$this->trail;
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
        $maxl=!empty($DBInfo->errlog_maxline) ? min($DBInfo->errlog_maxline,200):20;
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

        if (empty($DBInfo->raw_errlog) and !$raw) {
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
  if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE']))
    return array($languages['en'][0]);
  $lang= strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']);
  $lang= strtr($lang,'_','-');
  $langs=explode(',',preg_replace(array("/;[^;,]+/","/\-[a-z]+/"),'',$lang));
  if ($languages[$langs[0]]) return array($languages[$langs[0]][0]);
  return array($languages['en'][0]);
}

function set_locale($lang,$charset='') {
  $supported=array(
    'en_US'=>array('ISO-8859-1'),
    'fr_FR'=>array('ISO-8859-1'),
    'ko_KR'=>array('EUC-KR','UHC'),
  );
  $charset= strtoupper($charset);
  if ($lang == 'auto') {
    # get broswer's settings
    $langs=get_locales();
    $lang= $langs[0]; // XXX
  }
  // check server charset
  $server_charset = '';
  if (function_exists('nl_langinfo'))
    $server_charset= nl_langinfo(CODESET);

  if ($charset == 'UTF-8') {
    $lang.= '.'.$charset;
  } else {
    if ($supported[$lang] && in_array($charset,$supported[$lang])) {
      return $lang.'.'.$charset;
    } else {
      return 'en_US'; // default
    }
  }
  return $lang;
}

# get the pagename
function get_pagename() {
  // $_SERVER["PATH_INFO"] has bad value under CGI mode
  // set 'cgi.fix_pathinfo=1' in the php.ini under
  // apache 2.0.x + php4.2.x Win32
  $pagename = '';
  if (isset($_SERVER['PATH_INFO'])) {
    if ($_SERVER['PATH_INFO'][0] == '/')
      $pagename=substr($_SERVER['PATH_INFO'],1);
  } else if (!empty($_SERVER['QUERY_STRING'])) {
    $goto=isset($_POST['goto'][0]) ? $_POST['goto']:(isset($_GET['goto'][0]) ? $_GET['goto'] : '');
    if (isset($goto[0])) $pagename=$goto;
    else {
      parse_str($_SERVER['QUERY_STRING'], $arr);
      $keys = array_keys($arr);
      if (!empty($arr['action'])) {
        if ($arr['action'] == 'edit') {
          if (!empty($arr['value'])) $pagename = $arr['value'];
        } else if ($arr['action'] == 'login') {
          $pagename = 'UserPreferences';
        }
        unset($arr['action']);
      }
      foreach ($arr as $k=>$v)
        if (empty($v)) $pagename = $k;
    }
  }
  if (isset($pagename[0])) {
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
  if ((empty($DBInfo->theme) or isset($_GET['action'])) and isset($_GET['theme'])) {
    // check theme
    if (preg_match('@^[a-zA-Z0-9_-]+$@', $_GET['theme']))
      $theme = $_GET['theme'];
  } else {
    if (is_mobile()) {
      if (isset($_GET['mobile'])) {
        if (empty($_GET['mobile'])) {
          setcookie('desktop', 1, time()+60*60*24*30, get_scriptname());
          $_COOKIE['desktop'] = 1;
        } else {
          setcookie('desktop', 0, time()-60*60*24*30, get_scriptname());
          unset($_COOKIE['desktop']);
        }
      }
    }
    if (isset($_COOKIE['desktop'])) {
      $DBInfo->metatags_extra = '';
      if (!empty($DBInfo->theme_css))
        $theme = $DBInfo->theme;
    } else if ((is_mobile() or !empty($DBInfo->force_mobile)) and !empty($DBInfo->mobile_theme)) {
      $theme = $DBInfo->mobile_theme;
      $DBInfo->menu = !empty($DBInfo->mobile_menu) ? $DBInfo->mobile_menu :
          array('FrontPage'=>1, 'RecentChanges'=>2);
      $DBInfo->use_wikiwyg = 0; # disable wikiwyg
      $DBInfo->use_sectionedit = 0; # disable section edit
    } else if ($DBInfo->theme_css) {
      $theme=$DBInfo->theme;
    }
  }
  if (!empty($theme)) $options['theme']=$theme;

if ($options['id'] != 'Anonymous') {
  $options['css_url']=!empty($user->info['css_url']) ? $user->info['css_url'] : '';
  $options['quicklinks']=!empty($user->info['quicklinks']) ? $user->info['quicklinks'] : '';
  $options['tz_offset']=!empty($user->info['tz_offset']) ? $user->info['tz_offset'] : date('Z');
  if (empty($theme)) $options['theme']=!empty($user->info['theme']) ? $user->info['theme'] : '';
} else {
  $options['css_url']=$user->css;
  $options['tz_offset']=$user->tz_offset;
  if (empty($theme)) $options['theme']=$theme=$user->theme;
}

if (!$options['theme']) $options['theme']=$theme=$DBInfo->theme;

if ($theme and ($DBInfo->theme_css or !$options['css_url']))
  $options['css_url']=(!empty($DBInfo->themeurl) ? $DBInfo->themeurl:$DBInfo->url_prefix)."/theme/$theme/css/default.css";

  $options['pagename']=get_pagename();

  if ($user->id != 'Anonymous' and !empty($DBInfo->use_scrap)) {
    $pages = explode("\t",$user->info['scrapped_pages']);
    $tmp = array_flip($pages);
    if (isset($tmp[$options['pagename']]))
      $options['scrapped']=1;
    else
      $options['scrapped']=0;
  }
}

function init_locale($lang, $domain = 'moniwiki', $init = false) {
  global $Config,$_locale;
  if (isset($_locale)) {
    if (!@include_once('locale/'.$lang.'/LC_MESSAGES/'.$domain.'.php'))
      @include_once('locale/'.substr($lang,0,2).'/LC_MESSAGES/'.$domain.'.php');
  } else if (substr($lang,0,2) == 'en') {
    $test=setlocale(LC_ALL, $lang);
  } else {
    if (!empty($Config['include_path'])) $dirs=explode(':',$Config['include_path']);
    else $dirs=array('.');

    while ($Config['use_local_translation']) {
      $langdir=$lang;
      if(getenv("OS")=="Windows_NT") $langdir=substr($lang,0,2);
      # gettext cache workaround
      # http://kr2.php.net/manual/en/function.gettext.php#58310
      $ldir=$Config['cache_dir']."/locale/$langdir/LC_MESSAGES/";

      $tmp = '';
      $fp = @fopen($ldir.'md5sum', 'r');
      if (is_resource($fp)) {
        $tmp = '-'.trim(fgets($fp,1024));
        fclose($fp);
      } else {
        $init = 1;
      }

      if ($init and !file_exists($ldir.$domain.$tmp.'mo')) {
        include_once(dirname(__FILE__).'/plugin/msgtrans.php');
        macro_msgtrans(null,$lang,array('init'=>1));
      } else {
        $domain=$domain.$tmp;
        array_unshift($dirs,$Config['cache_dir']);
      }
      break;
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
    if (!empty($Config['set_lang'])) putenv("LANG=".$lang);
    if (function_exists('bind_textdomain_codeset'))
      bind_textdomain_codeset ($domain, $Config['charset']);
  }
}

function get_frontpage($lang) {
  global $Config;

  $lcid=substr(strtok($lang,'_'),0,2);
  return !empty($Config['frontpages'][$lcid]) ? $Config['frontpages'][$lcid]:$Config['frontpage'];
}

function wiki_main($options) {
  global $DBInfo,$Config;
  $pagename=isset($options['pagename'][0]) ? $options['pagename']: $DBInfo->frontpage;

  # get primary variables
  if (isset($_SERVER['REQUEST_METHOD']) and $_SERVER['REQUEST_METHOD']=='POST') {
    // reset some reserved variables
    if (isset($_POST['retstr'])) unset($_POST['retstr']);
    if (isset($_POST['header'])) unset($_POST['header']);

    # hack for TWiki plugin
    $action = '';
    if (!empty($_FILES['filepath']['name'])) $action='draw';
    if (isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
      # hack for Oekaki: PageName----action----filename
      list($pagename,$action,$value)=explode('----',$pagename,3);
      $options['value']=$value;
    } else {
      $value=!empty($_POST['value']) ? $_POST['value'] : '';
      $action=!empty($_POST['action']) ? $_POST['action'] : $action;

      if (empty($action)) {
        $dum=explode('----',$pagename,3);
        if (isset($dum[0][0]) && isset($dum[1][0])) {
          $pagename=trim($dum[0]);
          $action=trim($dum[1]);
          $value=isset($dum[2][0]) ? $dum[2] : '';
        }
      }
    }
    $goto=!empty($_POST['goto']) ? $_POST['goto'] : '';
    $popup=!empty($_POST['popup']) ? 1 : 0;
  } else {
    // reset some reserved variables
    if (isset($_GET['retstr'])) unset($_GET['retstr']);
    if (isset($_GET['header'])) unset($_GET['header']);

    $action=!empty($_GET['action']) ? $_GET['action'] : '';
    $value=isset($_GET['value'][0]) ? $_GET['value'] : '';
    $goto=isset($_GET['goto'][0]) ? $_GET['goto'] : '';
    $rev=!empty($_GET['rev']) ? $_GET['rev'] : '';
    if ($options['id'] == 'Anonymous')
      $refresh = 0;
    else
      $refresh = !empty($_GET['refresh']) ? $_GET['refresh'] : '';
    $popup=!empty($_GET['popup']) ? 1 : 0;
  }
  // parse action
  // action=foobar, action=foobar/macro, action=foobar/json etc.
  $full_action=$action;
  $action_mode='';
  if (($p=strpos($action,'/'))!==false) {
    $full_action=strtr($action,'/','-');
    $action_mode=substr($action,$p+1);
    $action=substr($action,0,$p);
  }

  $options['page']=$pagename;
  $options['action'] = &$action;

  // check pagename length
  $key = $DBInfo->pageToKeyname($pagename);
  if (!empty($options['action']) && strlen($key) > 255) {
    $i = 252; // 252 + reserved 3 (.??) = 255

    $newname = $DBInfo->keyToPagename(substr($key, 0, 252));
    $j = mb_strlen($newname, $Config['charset']);
    $j--;
    do {
      $newname = mb_substr($pagename, 0, $j, $Config['charset']);
      $key = $DBInfo->pageToKeyname($newname);
    } while (strlen($key) > 248 && --$j > 0);

    $options['page'] = $newname;
    $options['orig_pagename'] = $pagename; // original page name
    $pagename = $newname;
  } else {
    $options['orig_pagename'] = '';
  }

  $page = $DBInfo->getPage($pagename);
  $page->is_static = false; // FIXME

  $pis = array();

  // get PI cache
  if ($page->exists()) {
    $page->pi = $pis = $page->get_instructions('', array('refresh'=>$refresh));

    // set some PIs for robot
    if (!empty($options['is_robot'])) {
      $page->is_static = true;
    } else if ($_SERVER['REQUEST_METHOD'] == 'GET' or $_SERVER['REQUEST_METHOD'] == 'HEAD') {
      if (empty($action) and empty($refresh))
        $page->is_static = empty($pis['#nocache']) && empty($pis['#dynamic']);
    }
  }

  // HEAD support for robots
  if (!empty($_SERVER['REQUEST_METHOD']) and $_SERVER['REQUEST_METHOD'] == 'HEAD') {
    if (!$page->exists()) {
      header("HTTP/1.1 404 Not found");
      header("Status: 404 Not found");
    } else {
      if ($page->is_static or is_static_action($options)) {
        $mtime = $page->mtime();
        $etag = $page->etag($options);
        $lastmod = gmdate('D, d M Y H:i:s \G\M\T', $mtime);
        header('Last-Modified: '.$lastmod);
        if (!empty($action)) {
          $etag = '"'.$etag.'"';
          header('ETag: '.$etag);
        }

        // checksum request
        if (isset($_SERVER['HTTP_X_GET_CHECKSUM']))
          header('X-Checksum: md5-'. md5($page->get_raw_body()));
      }
    }
    return;
  }

  if (is_static_action($options) or
      (!empty($DBInfo->use_conditional_get) and $page->is_static)) {
    $mtime = $page->mtime();
    $etag = $page->etag($options);
    $lastmod = gmdate('D, d M Y H:i:s \G\M\T', $mtime);
    $need = http_need_cond_request($mtime, $lastmod, $etag);
    if (!$need) {
      @ob_end_clean();
      $headers = array();
      $headers[] = 'HTTP/1.0 304 Not Modified';
      $headers[] = 'Last-Modified: '.$lastmod;

      foreach ($headers as $header) header($header);
      return;
    }
  }

  $formatter = new Formatter($page,$options);

  // is it robot ?
  if (!empty($DBInfo->robots) and !isset($_SESSION['is_robot'])) {
    if (empty($_SERVER['HTTP_USER_AGENT']))
      $options['is_robot'] = 1;
    else
      $options['is_robot'] = isRobot($_SERVER['HTTP_USER_AGENT']);
    $_SESSION['is_robot'] = $options['is_robot'];
  } else if (isset($_SESSION['is_robot'])) {
    $options['is_robot'] = $_SESSION['is_robot'];
  }

  if (!empty($options['is_robot'])) {
    if (!empty($DBInfo->security_class_robot)) {
      $class='Security_'.$DBInfo->security_class_robot;
      include_once('plugin/security/'.$DBInfo->security_class_robot.'.php');
    } else {
      $class='Security_robot';
      include_once('plugin/security/robot.php');
    }
    $DBInfo->security = new $class ($DBInfo);
    // is it allowed to robot ?
    if (!$DBInfo->security->is_allowed($action,$options))
      $action='show';
    $DBInfo->extra_macros='';
  }

  $formatter->refresh=!empty($refresh) ? $refresh : '';
  $formatter->popup=!empty($popup) ? $popup : '';
  $formatter->tz_offset=$options['tz_offset'];

  // simple black/white list of network check
  $no_checkip = false;
  if (!empty($DBInfo->whitelist)) {
    if (empty($_SESSION['whitelist'])) {
      require_once 'lib/checkip.php';
      if (check_ip($DBInfo->whitelist, $_SERVER['REMOTE_ADDR'])) {
        $no_checkip = true;
        $_SESSION['whitelist'] = true;
      }
    } else if (!empty($_SESSION['whitelist'])) {
      $no_checkip = true;
    }
  }

  if (!$no_checkip and !empty($DBInfo->blacklist)) {
    if (!isset($_SESSION['blacklist'])) {
      require_once 'lib/checkip.php';
      if (check_ip($DBInfo->blacklist, $_SERVER['REMOTE_ADDR'])) {
        $_SESSION['blacklist'] = true;
      } else {
        $_SESSION['blacklist'] = false;
      }
    }

    if ($_SESSION['blacklist']) {
      $options['title']=_("You are in the black list");
      $options['msg']=_("Please contact WikiMasters");
      do_invalid($formatter,$options);
      return false;
    }
  }

  if (!empty($DBInfo->kiwirian)) {
    if (!isset($_SESSION['kiwirian'])) {
      if (!is_array($DBInfo->kiwirian)) {
        $DBInfo->kiwirian=explode(':',$DBInfo->kiwirian);
      }
      if (in_array($options['id'],$DBInfo->kiwirian))
        $_SESSION['kiwirian'] = true;
      else
        $_SESSION['kiwirian'] = false;
    }

    if ($_SESSION['kiwirian'] === true) {
      $options['title']=_("You are blocked in this wiki");
      $options['msg']=_("Please contact WikiMasters");
      do_invalid($formatter,$options);
      return false;
    }
  }

  while (empty($action) or $action=='show') {
    if (isset($value[0])) { # ?value=Hello
      $options['value']=$value;
      do_goto($formatter,$options);
      return true;
    } else if (isset($goto[0])) { # ?goto=Hello
      $options['value']=$goto;
      do_goto($formatter,$options);
      return true;
    }
    if (!$page->exists()) {
      if (isset($options['retstr']))
        return false;
      if (!empty($DBInfo->auto_search) && $action!='show' && $p=getPlugin($DBInfo->auto_search)) {
        $action=$DBInfo->auto_search;
        break;
      }

      $msg_404='';
      $formatter->header("HTTP/1.1 404 Not found");
      if (empty($Config['no_404'])) $msg_404="Status: 404 Not found"; # for IE
      if (!empty($options['is_robot']) or !empty($Config['nofancy_404'])) {
        $formatter->header($msg_404);
        echo '<html><head></head><body><h1>'.$msg_404.'</h1></body></html>';
        return true;
      }
      $formatter->send_header($msg_404,$options);

      if (empty($DBInfo->metadb)) $DBInfo->initMetaDB();
      $twins=$DBInfo->metadb->getTwinPages($page->name,2);
      if ($twins) {
        $formatter->send_title('','',$options);
        $twins="\n".implode("\n",$twins);
        $formatter->send_page(_("See [TwinPages]: ").$twins);
        echo "<br />".
          $formatter->link_to("?action=edit",$formatter->icon['create']._("Create this page"));
      } else {
        $oldver='';
        if ($DBInfo->version_class) {
          $version = $DBInfo->lazyLoad('version', $DBInfo);
          $oldver = $version->rlog($formatter->page->name,'','','-z');
        }
        $button= $formatter->link_to("?action=edit",$formatter->icon['create']._("Create this page"));
        if ($oldver) {
          $formatter->send_title(sprintf(_("%s has saved revisions"),$page->name),"",$options);
          $searchval=_html_escape($options['page']);
          echo '<h2>'.sprintf(_("%s or click %s to fulltext search.\n"),$button,$formatter->link_to("?action=fullsearch&amp;value=$searchval",_("here"))).'</h2>';
          $options['info_actions']=array('recall'=>'view','revert'=>'revert');
          $options['title']='<h3>'.sprintf(_("Old Revisions of the %s"),_html_escape($page->name)).'</h3>';
          #if (empty($formatter->wordrule)) $formatter->set_wordrule();
          echo $formatter->macro_repl('Info','',$options);
        } else {
          $formatter->send_title(sprintf(_("%s is not found in this Wiki"),$page->name),"",$options);
          $searchval=_html_escape($options['page']);
          echo '<h2>'.sprintf(_("%s or click %s to fulltext search.\n"),$button,$formatter->link_to("?action=fullsearch&amp;value=$searchval",_("here"))).'</h2>';
          $err = array();
          echo $formatter->macro_repl('LikePages',$page->name,$err);
          if (!empty($err['extra']))
            echo $err['extra'];

          echo '<h2>'._("Please try to search with another word").'</h2>';
          $ret = array('call'=>1);
          $ret = $formatter->macro_repl('TitleSearch','',$ret);

          #if ($ret['hits'] == 0)
          echo "<div class='searchResult'>".$ret['form']."</div>";
        }

        echo "<hr />\n";
        $options['linkto']="?action=edit&amp;template=";
        $tmpls= macro_TitleSearch($formatter,$DBInfo->template_regex,$options);
        if ($tmpls) {
          echo sprintf(_("%s or alternativly, use one of these templates:\n"),$button);
          echo $tmpls;
        } else {
          echo "<h3>"._("You have no templates")."</h3>";
        }
        echo sprintf(_("To create your own templates, add a page with '%s' pattern.\n"),$DBInfo->template_regex);
      }

      $args['editable']=1;
      $formatter->send_footer($args,$options);
      return;
    }
    # display this page

    if (isset($_GET['redirect']) and !empty($DBInfo->use_redirect_msg) and $action=='show'){
      $options['msg']=
        '<h3>'.sprintf(_("Redirected from page \"%s\""),
          $formatter->link_tag($_GET['redirect'],'?action=show'))."</h3>";
    }

    if (empty($action)) $options['pi']=1; # protect a recursivly called #redirect

    if (!empty($DBInfo->control_read) and !$DBInfo->security->is_allowed('read',$options)) {
      do_invalid($formatter,$options);
      return;
    }

    $formatter->pi=$formatter->page->get_instructions();

    if (!empty($DBInfo->body_attr))
      $options['attr']=$DBInfo->body_attr;

    $ret = $formatter->send_header('', $options);

    if (empty($options['is_robot'])) {
      if ($DBInfo->use_counter)
        $DBInfo->counter->incCounter($pagename,$options);
      $formatter->send_title("","",$options);

      if (!empty($DBInfo->use_referer) and isset($_SERVER['HTTP_REFERER']))
        log_referer($_SERVER['HTTP_REFERER'],$pagename);
    }

    $formatter->write("<div id='wikiContent'>\n");
    if (isset($options['timer']) and is_object($options['timer'])) {
      $options['timer']->Check("init");
    }

    // force #nocache for #redirect pages
    if (isset($formatter->pi['#redirect'][0]))
      $formatter->pi['#nocache'] = 1;

    $options['pagelinks']=1;
    if (!empty($Config['cachetime']) and $Config['cachetime'] > 0 and empty($formatter->pi['#nocache'])) {
      $cache= new Cache_text('pages', array('ext'=>'html'));
      $mcache= new Cache_text('dynamic_macros');
      $mtime=$cache->mtime($pagename);
      $now=time();
      $check=$now-$mtime;
      $extra_out='';
      $_macros=null;
      if ($cache->mtime($pagename) < $formatter->page->mtime()) $formatter->refresh = 1; // force update

      $delay = !empty($DBInfo->default_delaytime) ? $DBInfo->default_delaytime : 0;

      if (empty($formatter->refresh) and $DBInfo->checkUpdated($mtime, $delay) and ($check < $Config['cachetime'])) {
        if ($mcache->exists($pagename))
          $_macros= $mcache->fetch($pagename);
        if (empty($_macros)) {
          $out = '';
          #$out = $cache->fetch($pagename);
          $cache->fetch($pagename, '', array('print'=>1));
        } else {
          $out = $cache->fetch($pagename);
        }
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
        $_macros=&$formatter->_dynamic_macros;
        if (!empty($_macros))
          $mcache->update($pagename,$_macros);
        $cache->update($pagename, $out);
      }
      if (!empty($_macros)) {
        $mrule=array();
        $mrepl=array();
        foreach ($_macros as $m=>$v) {
          if (!is_array($v)) continue;
          $mrule[]='@@'.$v[0].'@@';
          $options['mid']=$v[1];
          $mrepl[]=$formatter->macro_repl($m,'',$options); // XXX
        }
        echo $formatter->get_javascripts();
        $out=str_replace($mrule,$mrepl,$out);
      }
      echo $out,$extra_out;
      if ($options['id'] != 'Anonymous')
        $args['refresh']=1; // add refresh menu
    } else {
      $formatter->send_page('',$options);
    }

    // automatically set #dynamic PI
    if (empty($formatter->pi['#dynamic']) and !empty($formatter->_dynamic_macros)) {
      $pis = $formatter->pi;
      if (empty($pis['raw'])) {
        // empty PIs
        $pis = array();
      } else if (isset($pis['#format']) and !preg_match('/#format\s/', $pis['raw'])) {
        // #format not found in PIs
        unset($pis['#format']);
      }
      $pis['#dynamic'] = 1; // internal instruction

      $pi_cache = new Cache_text('PI');
      $pi_cache->update($formatter->page->name, $pis);
    } else if (empty($formatter->_dynamic_macros) and !empty($formatter->pi['#dynamic'])) {
      $pi_cache = new Cache_text('PI');
      $pi_cache->remove($formatter->page->name); // reset PI
      $mcache->remove($pagename); // remove macro cache
      $cache->update($pagename, $out); // update cache content
    }

    if (isset($options['timer']) and is_object($options['timer'])) {
      $options['timer']->Check("send_page");
    }
    $formatter->write("<!-- wikiContent --></div>\n");

    if (!empty($DBInfo->extra_macros) and
        $formatter->pi['#format'] == $DBInfo->default_markup) {
      if (!empty($formatter->pi['#nocomment'])) {
        $options['nocomment']=1;
        $options['notoolbar']=1;
      }
      $options['mid']='dummy';
      echo '<div id="wikiExtra">'."\n";
      $mout = '';
      $extra = array();
      if (is_array($DBInfo->extra_macros))
        $extra = $DBInfo->extra_macros;
      else
        $extra[] = $DBInfo->extra_macros; // XXX
      if (!empty($formatter->pi['#comment'])) array_unshift($extra,'Comment');

      foreach ($extra as $macro)
        $mout.= $formatter->macro_repl($macro,'',$options);
      echo $formatter->get_javascripts();
      echo $mout;
      echo '</div>'."\n";
    }

    $args['editable']=1;
    if (empty($options['is_robot']))
      $formatter->send_footer($args,$options);
    return;
  }

  if ($action) {
    $options['noindex'] = true;
    $options['custom']='';
    $options['help']='';
    $options['value']=$value;

    $a_allow=$DBInfo->security->is_allowed($action,$options);
    if (!empty($action_mode)) {
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
        echo "<div id='wikiHelper'>";
        echo call_user_func(array($DBInfo->security, $options['help']),$formatter,$options);
        echo "</div>\n";
      }

      $formatter->send_footer('',$options);
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

    $options['action_mode']='';
    if (!empty($action_mode) and in_array($action_mode,array('ajax','macro'))) {
      if ($_SERVER['REQUEST_METHOD']=="POST")
        $options=array_merge($_POST,$options);
      else
        $options=array_merge($_GET,$options);
      $options['action_mode']=$action_mode;
      if ($action_mode=='ajax')
        $formatter->ajax_repl($action,$options);
      else if (!empty($DBInfo->use_macro_as_action)) # XXX
        echo $formatter->macro_repl($action,$options['value'],$options);
      else
        do_invalid($formatter,$options);
      return;
    }

    // is it valid action ?
    $plugin = $pn = getPlugin($action);
    if ($plugin === '') // action not found
      $plugin = $action;
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
        $options['value']=isset($_GET['value'][0]) ? $_GET['value'] : '';
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
$Config = getConfig('config.php', array('init'=>1));
require_once("wikilib.php");
require_once("lib/win32fix.php");
require_once("lib/cache.text.php");
require_once("lib/timer.php");

$options = array();
if (class_exists('Timer')) {
  $timing = new Timer();
  $options['timer'] = &$timing;
  $options['timer']->Check("load");
}

$ccache = new Cache_text('settings', array('depth'=>0));
if (!($conf = $ccache->fetch('config'))) {
  require_once("lib/wikiconfig.php");
  $Config = wikiConfig($Config);
  $ccache->update('config', $Config, 0, array('deps'=>array('config.php', 'lib/wikiconfig.php')));
} else {
  $Config = &$conf;
}

$DBInfo= new WikiDB($Config);

if (isset($options['timer']) and is_object($options['timer'])) {
  $options['timer']->Check("load");
}

$lang= set_locale($DBInfo->lang,$DBInfo->charset);
init_locale($lang);
init_requests($options);
if (!isset($options['pagename'][0])) $options['pagename']= get_frontpage($lang);
$DBInfo->lang=$lang;
$options['lang'] = $lang;

if ($options['id'] == 'Anonymous') {
  $private = 'public';
} else if (session_id() == '' and empty($Config['nosession']) and is_writable(ini_get('session.save_path')) ) {
  $private = 'private';
  $prefix = !empty($DBInfo->session_seed) ? $DBInfo->session_seed : 'MONIWIKI';
  $myseed = getTicket($prefix, $_SERVER['REMOTE_ADDR']);
  $myid = $prefix . '-*-' . $myseed . '-*-' . $options['id'];

  session_set_cookie_params (isset($Config['session_lifetime']) ? $Config['session_lifetime'] : 3600, get_scriptname());

  // chceck some action and set expire
  session_cache_limiter('');

  session_name($myid);
  session_start();
} else {
  $private = 'private';
}

// set the s-maxage for proxy
$proxy_maxage = !empty($Config['proxy_maxage']) ? ', s-maxage='.$Config['proxy_maxage'] : '';

// set the real IP address for proxy
$_SERVER['REMOTE_ADDR'] = realIP();

if ($_SERVER['REQUEST_METHOD'] != 'GET' and
    $_SERVER['REQUEST_METHOD'] != 'HEAD')
  header('Cache-Control: '.$private.', max-age=0, must-revalidate, post-check=0, pre-check=0');
else {
  if (empty($_GET['action']) or $_GET['action'] == 'show')
    $tmp = $private.$proxy_maxage;
  else
    $tmp = $private;

  header('Cache-Control: '.$tmp.', max-age=0, must-revalidate, post-check=0, pre-check=0');
}

wiki_main($options);
endif;
// vim:et:sts=2:sw=2
?>
