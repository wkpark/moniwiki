<?php
// Copyright 2003-2007 Won-Kyu Park <wkpark at kldp.org> all rights reserved.
// distributable under GPL see COPYING 
// $Id$

function _stripslashes($str) {
  return get_magic_quotes_gpc() ? stripslashes($str):$str;
}

class MoniConfig {
  function MoniConfig($configfile="config.php") {
    if (file_exists($configfile)) {
      $this->config=$this->_getConfig($configfile);
      $this->rawconfig=$this->_rawConfig($configfile);
      $this->configdesc=$this->_getConfigDesc($configfile);
    } else {
      $this->config=array();
      $this->rawconfig=array();
    }
  }

  function getDefaultConfig() {
    $this->config=$this->_getConfig("config.php.default");

    $hostconfig=$this->_getHostConfig();
    $this->rawconfig=array_merge($this->_rawConfig("config.php.default"),$hostconfig);
    while (list($key,$val)=each($this->rawconfig)) {
      eval("\$$key=$val;");
      eval("\$this->config[\$key]=$val;");
    }

  }
  function _getHostConfig() {
    print '<div class="check">';
    if (function_exists("dba_open")) {
      print '<h3>'._("Check a dba configuration").'</h3>';
      $tempnam="/tmp/".time();
      if ($db=@dba_open($tempnam,"n","db4"))
        $config['dba_type']="'db4'";
      else if ($db=@dba_open($tempnam,"n","db3"))
        $config['dba_type']="'db3'";
      else if ($db=@dba_open($tempnam,"n","db2"))
        $config['dba_type']="'db2'";
      else if ($db=@dba_open($tempnam,"n","gdbm"))
        $config['dba_type']="'gdbm'";

      if ($db) dba_close($db);
      print '<ul><li><b>'.$config['dba_type'].'</b> is selected.</li></ul>';
    }
    preg_match("/Apache\/2\.0\./",$_SERVER['SERVER_SOFTWARE'],$match);

    if ($match) {
      $config['query_prefix']='"?"';
      while (ini_get('allow_url_fopen')) {
        print '<h3>'._("Check a AcceptPathInfo setting for Apache 2.x.xx").'</h3>';
        print '<ul>';
        $fp=@fopen('http://'.$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME'].'/pathinfo?action=pathinfo','r');
        $out='';
        if ($fp) {
          while (!feof($fp)) $out.=fgets($fp,2048);
        } else {
          print "<li><b><a href='http://moniwiki.sf.net/wiki.php/AcceptPathInfo'>AcceptPathInfo</a> <font color='red'>"._("Off")."</font></b><li>\n";
          print '</ul>';
          break;
        }
        fclose($fp);
        if ($out[0] == '*') {
          print "<li><b><a href='http://moniwiki.sf.net/wiki.php/AcceptPathInfo'>AcceptPathInfo</a> <font color='red'>"._("Off")."</font></b></li>\n";
        } else {
          print "<li><b>AcceptPathInfo <font color='blue'>"._("On")."</font></b></li>\n";
          $config['query_prefix']='"/"';
        }
        print '</ul>';
        break;
      }
    }

    $url_prefix= preg_replace("/\/([^\/]+)\.php$/","",$_SERVER['SCRIPT_NAME']);
    $config['url_prefix']="'".$url_prefix."'";

    $user = getenv('LOGNAME');
    $user = $user ? $user : get_current_user();
    $config['rcs_user']="'".$user."'";

    if(getenv("OS")=="Windows_NT") {
      $config['timezone']="'-09-09'";
      // http://kldp.net/forum/message.php?msg_id=7675
      // http://bugs.php.net/bug.php?id=22418
      //$config['version_class']="'RcsLite'";
      $config['path']="./bin;c:/program files/vim/vimXX'";
    }

    if (!file_exists('wikilib.php')) {
      $config['include_path']="'.:/usr/local/share/moniwiki:/usr/share/moniwiki'";
    }
    print '</div>';
    return $config;
  }

  function setConfig($config) {
    $this->config=$config;
  }

  function setRawConfig($config) {
    $this->rawconfig=$config;
  }

  function _getConfig($configfile) {
    if (!file_exists($configfile))
      return array();

    $org=array();
    $org=get_defined_vars();
    include($configfile);
    $new=get_defined_vars();

    return array_diff($new,$org);
  }

  function _rawConfig($configfile) {
    $lines=file($configfile);
    $key='';
    foreach ($lines as $line) {
      $line=rtrim($line)."\n"; // for Win32
      if (!$key and $line[0] != '$') continue;
      if ($key) {
        $val.=$line;
        if (!preg_match('/\s*;(\s*#.*)?$/',$line)) continue;
      } else {
        list($key,$val)=explode('=',substr($line,1),2);
        if (!preg_match('/\s*;(\s*#.*)?$/',$val)) {
          if (substr($val,0,3)== '<<<') $tag=substr($val,3);
          continue;
        }
      }

      if ($key) {
	$val=rtrim($val);
        $val=preg_replace('/\s*;(\s*#.*)?$/','',$val);
        $config[$key]=$val;
      }
      $key='';
      $tag='';
    }
    return $config;
  }

  function _getConfigDesc($configfile) {
    $lines=file($configfile);
    $key='';
    $desc=array();
    foreach ($lines as $line) {
      $line=rtrim($line)."\n"; // for Win32
      if (!$key and $line[0] != '$') continue;
      if ($key) {
        $val.=$line;
        if (!preg_match('/\s*;\s*(#.*)?$/',$line)) continue;
      } else {
        list($key,$val)=explode('=',substr($line,1),2);
        if (!preg_match('/\s*;\s*(#.*)?$/',$val)) {
          if (substr($val,0,3)== '<<<') $tag=substr($val,3);
          continue;
        }
      }

      if ($key) {
        preg_match('/\s*;\s*#(.*)?$/',rtrim($val),$match);
        if (!empty($match[1])) $desc[$key]=$match[1];
      }
      $key='';
      $tag='';
    }
    return $desc;
  }

  function _getFormConfig($config,$mode=0) {
    $conf=array();
    while (list($key,$val) = each($config)) {
      $val=_stripslashes($val);
      $val=str_replace(array("\r\n","\r"),array("\n","\n"),$val);
      if (!isset($val)) $val="''";
      if (!$mode) {
        @eval("\$dum=$val;");
        @eval("\$$key=$val;");
        $conf[$key]=$dum;
      } else {
        $conf[$key]=$val;
      }
      #print("$mode:\$$key=$val;<br/>");
    }
    return $conf;
  }

  function _genRawConfig($config) {
    $lines=array("<?php\n","# automatically generated by monisetup\n");
    while (list($key,$val) = each($config)) {
      if ($key=='admin_passwd' or $key=='purge_passwd')
         $val="'".crypt($val,md5(time()))."'";
      if (preg_match("/^<<<([A-Za-z0-9]+)\s[^(\\1)]*\s\\1$/",$val,$m)) {
         $save_val=$val;
         $val=str_replace("$m[1]",'',substr($val,3));
         $val=preg_quote($val,'"');
         $t=@eval("\$$key=\"$val\";");
         $val=$save_val;
      } else
         $t=@eval("\$$key=$val;");
      if ($t === NULL)
        $lines[]="\$$key=$val;\n";
      else
        print "<font color='red'>ERROR:</font> <tt>\$$key=$val;</tt><br/>";
    }
    $lines[]="?>\n";
    if (!empty($config['dba_type'])) {
      if (!file_exists('data/counter.db'))
        $db=dba_open('data/counter.db','n',substr($config['dba_type'],1,-1));
      if ($db) dba_close($db);
    }
    return $lines;
  }
}

function checkConfig($config) {
  umask(011);
  $dir=getcwd();

  if (!file_exists("config.php") && !is_writable(".")) {
     print "<h3><font color='red'>".
	_("Please change the permission of some directories writable on your server to initialize your Wiki.")."</font></h3>\n";
     print "<pre class='console'>\n<font color='green'>$</font> chmod <b>777</b> $dir/data/ $dir\n</pre>\n";
     print sprintf(_("If you want a more safe wiki, try to change the permission of directories with %s."),
		"<font color='red'>2777(setgid).</font>\n");
     print "<pre class='console'>\n<font color='green'>$</font> chmod <b>2777</b> $dir/data/ $dir\n</pre>\n";
     print _("or use <tt>monisetup.sh</tt> and select 777 or <font color='red'>2777</font>");
     print "<pre class='console'>\n<font color='green'>$</font> sh monisetup.sh</pre>\n";
     print _("After execute one of above two commands, just <a href='monisetup.php'>reload this <tt>monisetup.php</tt></a> would make a new initial <tt>config.php</tt> with detected parameters for your wiki.")."\n<br/>";
     print "<h2><a href='monisetup.php'>"._("Reload")."</a></h2>";
     exit;
  } else if (file_exists("config.php")) {
     print "<p class='notice'><span class='warn'>"._("WARN").":</span> ".
	_("Please execute the following command after you have completed your configuration.")."</p>\n";
     print "<pre class='console'>\n<font color='green'>$</font> sh secure.sh\n</pre>\n";
  }

  if (file_exists("config.php")) {
    if (!is_writable($config['data_dir'])) {
      if (02000 & fileperms(".")) # check sgid
        $datadir_perm = 0775;
      else
        $datadir_perm = 0777;
      $datadir_perm = decoct($datadir_perm);
      print "<h3><font color=red>".sprintf(_("FATAL: %s directory is not writable"),$config['data_dir'])."</font></h3>\n";
      print "<h4>"._("Please execute the following command.")."</h4>";
      print "<pre class='console'>\n".
            "<font color='green'>$</font> chmod $datadir_perm $config[data_dir]\n</pre>\n";
      exit;
    }

    $data_sub_dir=array("cache","user","text");
    if (02000 & fileperms($config['data_dir']))
      $DPERM=0775;
    else
      $DPERM=0777;

    foreach($data_sub_dir as $dir) {
       if (!file_exists("$config[data_dir]/$dir")) {
           umask(000);
           mkdir("$config[data_dir]/$dir",$DPERM);
           if ($dir == 'text')
             mkdir($config['data_dir']."/$dir/RCS",$DPERM);
       } else if (!is_writable("$config[data_dir]/$dir")) {
           print "<h4><font color=red>".sprintf(_("%s directory is not writable"),$dir )."</font></h4>\n";
           print "<pre class='console'>\n".
             "<font color='green'>$</font> chmod a+w $config[$file]\n</pre>\n";
       }
    }
    if (is_dir('imgs') and !file_exists('imgs/.htaccess')) {
      $fp=fopen('imgs_htaccess','w');
      fwrite($fp,'ErrorDocument 404 '.$config['url_prefix'].'/imgs/moni/inter.png'."\n");
      fclose($fp);
    }

    $writables=array("upload_dir","editlog_name");

    print '<div class="check">';
    foreach($writables as $file) {
      if (!is_writable($config[$file])) {
        if (file_exists($config[$file])) {
          print "<h3><font color=red>".sprintf(_("%s is not writable"),$config[$file])."</font> :( </h3>\n";
          print "<pre class='console'>\n".
              "<font color='green'>$</font> chmod a+w $config[$file]\n</pre>\n";
        } else {
          if (preg_match("/_dir/",$file)) {
            umask(000);
            mkdir($config[$file],$DPERM);
            print "<h3>&nbsp;&nbsp;<font color=blue>".sprintf(_("%s is created now"),$config[$file])."</font> :)</h3>\n";
          } else {
            $fp=@fopen($config[$file],"w+");
            if ($fp) {
              chmod($config[$file],0666);
              fclose($fp);
              print "<h4><font color='green'>".sprintf(_("%s is created now"),$config[$file])."</font> ;) </h4>\n";
            } else {
              print "<pre class='console'>\n".
              "<font color='green'>$</font> touch $config[$file]\n".
              "<font color='green'>$</font> chmod a+w $config[$file]\n</pre>\n";
            }
          }
        }
        $error=1;
      } else
        print "<h3><font color=blue>".sprintf(_("%s is writable"),$config[$file])."</font> :)</h3>\n";
    }
    if (is_dir($config['upload_dir'])
      and !file_exists($config['upload_dir'].'/.htaccess')) {
      $fp=fopen('pds_htaccess','w');
      fwrite($fp,'#Options NoExecCGI'."\n");
      fwrite($fp,'AddType text/plain .sh .cgi .pl .py .php .php3 .php4 .phtml .html'."\n");
      fclose($fp);
    }
    print "</div>\n";
  }
}

function keyToPagename($key) {
#  return preg_replace("/_([a-f0-9]{2})/e","chr(hexdec('\\1'))",$key);
  $pagename=preg_replace("/_([a-f0-9]{2})/","%\\1",$key);
#  $pagename=str_replace("_","%",$key);
#  $pagename=strtr($key,"_","%");
  return rawurldecode($pagename);
}

function pagenameToKey($pagename) {
  return preg_replace("/([^a-z0-9]{1})/ie","'_'.strtolower(dechex(ord('\\1')))",$pagename);
}

function show_wikiseed($config,$seeddir='wikiseed') {
  $path='.:/usr/share/moniwiki:/usr/local/share/moniwiki';
  $pages= array();
  foreach (explode(':',$path) as $dir) {
    $handle= @opendir($dir.'/'.$seeddir);
    if ($handle) {
      $seeddir=$dir.'/'.$seeddir;
      break;
    }
  }
  while ($file = readdir($handle)) {
    if (is_dir($seeddir."/".$file)) continue;
    $pagename = keyToPagename($file);
    $pages[$pagename] = $pagename;
  }
  closedir($handle);
#  sort($pages);
  $idx=1;

  $num=sizeof($pages);

  #
  $SystemPages="FrontPage|RecentChanges|TitleIndex|FindPage|WordIndex|".
  "FortuneCookies|Pages$|".
  "SystemPages|TwinPages|WikiName|SystemInfo|UserPreferences|".
  "InterMap|IsbnMap|WikiSandBox|SandBox|UploadFile|UploadedFiles|".
  "InterWiki|SandBox|".
  "BadContent|BlogChanges|HotDraw|OeKaki";

  $WikiTag='DeleteThisPage';
  #
  $seed_filters= array(
    "HelpPages"=>array('/^Help.*/',1),
    "Category pages"=>array('/^Category.*/',1),
    "Macro pages"=>array('/Macro$/',1),
    "MoniWiki pages"=>array('/MoniWiki.*|Moni/',1),
    "MoinMoin pages"=>array('/MoinMoin.*/',1),
    "Templates"=>array('/Template$/',1),
    "SystemPages"=>array("/($SystemPages)/",1),
    "WikiTags"=>array("/($WikiTag)/",1),
    "Wiki etc."=>array('/Wiki/',1),
    "Misc."=>array('//',0),
  );

  $wrap=1;

  print "<h3>Total $num pages found</h3>\n";
  print "<form method='post' action=''>\n";
  while (list($filter_name,$filter) = each($seed_filters)) {
    print "<h4>$filter_name</h4>\n";
    foreach ($pages as $pagename) {
      if (preg_match($filter[0],$pagename)) {
        print "<input type='checkbox' name='seeds[$idx]' value='$pagename'";
        if ($filter[1])
          print "checked='checked' />$pagename ";
        else
          print " />$pagename ";
        $idx++;
        if ($wrap++ % 4 == 0) print "<br />\n";
        unset($pages[$pagename]);
      }
    }
    $wrap=1;
  }
  print "<input type='hidden' name='action' value='sow_seed' />\n";
  print "<br /><input type='submit' value='sow WikiSeeds'></form>\n";
}

function sow_wikiseed($config,$seeddir='wikiseed',$seeds) {
  $path='.:/usr/share/moniwiki:/usr/local/share/moniwiki';
  $pages= array();
  foreach (explode(':',$path) as $dir) {
    if (is_dir($dir.'/'.$seeddir)) {
      $seeddir=$dir.'/'.$seeddir;
      break;
    }
  }
  umask(0133);
  print "<pre class='console'>\n";
  foreach($seeds as $seed) {
    $key=pagenameToKey($seed);
    $cmd="cp $seeddir/$key $config[text_dir]";
    #system(escapeshellcmd($cmd));
    copy("$seeddir/$key", $config['text_dir']."/$key");
    print $cmd."\n";
  }
  print "</pre>\n";
}

print <<<EOF
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head><title>Moni Setup</title>
<style type="text/css">
<!--
html { background: #707070; }
.body {
  background:#fff;
  font-family: "Trebuchet MS", Tahoma,"Times New Roman", Times, serif;
  margin-left: 10%;
  margin-right: 10%;
  padding: 0.1em 1.5em;
}
.header {
  background:#909090 url("imgs/setup-bg.png");
  margin-left: 10%;
  margin-right: 10%;
  color: white;
  padding-left:1em;
}


h1 { display:inline;
  font-size:40px;
  font-family: Tahoma, "Times New Roman", Times, serif;
}

h2 {
  font-size:1.5em;
}

h2,h3,h4,h5 {
  font-family:"Trebuchet MS",sans-serif;
/* background-color:#E07B2A; */
  padding-left:6px;
/*  border-bottom:1px solid #eee; */
}
table.wiki {
/* border-collapse: collapse; */
  border: 0px outset #E2ECE5;
  font-family:"bitstream vera sans mono",monospace;
}

pre.console {
  background-color:#000;
  padding: 1em 0.5em 0.5em 1em;
  border: 1px inset #eeeeee;
  color:white;
  width:80%;
}

td.wiki {
  background-color:#E2ECE2;
/* border-collapse: collapse; */
  border: 0px inset #E2ECE5;
  font-family:sans-serif;
}

table.wiki td {
  border: 0px inset #E2ECE5;
  background-color:#ffffff;
}

table.wiki td.preview {
  font-size:12px;
  font-family:"bitstream vera sans mono",monospace;
  background-color:#E6E6E6;
  font-weight:bold;
}

td.option {
  font-size:12px;
  font-family:bitstream vera sans mono,monospace;
  background-color:#E6E6E6;
  font-weight:bold;
  color:black;
}

.newset table input {
  background-color:#ffffff;
  border:1px solid #c0c0c0;
}

td.desc {
  font-family:Trebuchet MS,sans-serif;
  background-color:#E6E6E6;
  text-align:right;
  padding:5px;
}

span.warn {
  color:red;
}

.notice {
  font-size:18px;
  color: #4BD548;
}

.check {
  background: #f2f2f2;
  margin-left:2em;
  margin-right:2em;
  padding:0.5em;
}

.oldset {
  height: 300px;
  overflow-y: scroll;
  background: #f2f2f2;
}

.newset {
  height: 300px;
  overflow-y: scroll;
  background: #f2f2f2;
}

.step {
  text-align: right;
}

.step input {
  font-size: 2em;
  font-weight:bold;
  font-family: Trebuchet MS, "Times New Roman", Times, serif;
}

-->
</style>
</head>
<body>
EOF;

print "<div class='header'><h1><img src='imgs/moniwiki-logo.png' style='vertical-align: middle'/> "._("MoniWiki")."</h1></div><div class='body'>\n";

if (file_exists("config.php") && !is_writable("config.php")) {
  print "<h2><font color='red'>"._("'config.php' is not writable !")."</font></h2>\n";
  print _("Please execute <tt>'monisetup.sh'</tt> or <tt>chmod a+w config.php</tt> first to change your settings.")."<br />\n";

  return;
}

$Config=new MoniConfig();

$config=isset($_POST['config']) ? $_POST['config']:'';
$update=isset($_POST['update']) ? $_POST['update']:'';
$action=isset($_GET['action']) ? $_GET['action']:(isset($_POST['action']) ? $_POST['action']:'');
$newpasswd=isset($_POST['newpasswd']) ? $_POST['newpasswd']:'';
$oldpasswd=isset($_POST['oldpasswd']) ? $_POST['oldpasswd']:'';

if (!empty($_GET['action']) and $_GET['action'] =='pathinfo') {
  print $_SERVER['PATH_INFO'].'****';
  return;
}

if ($_SERVER['REQUEST_METHOD']=="POST" && $config) {
  $conf=$Config->_getFormConfig($config);
  $rawconfig=$Config->_getFormConfig($config,1);
  $config=$conf;

  if (!empty($Config->config['admin_passwd'])) {
    if (crypt($oldpasswd,$Config->config['admin_passwd']) != 
      $Config->config['admin_passwd']) {
        if ($update=='Update') {
        print "<h2><font color='red'>"._("Invalid password error !")."</font></h2>\n";
        print _("If you can't remember your admin password, delete password entry in the 'config.php' and restart 'monisetup'")."<br />\n";
        }
        $invalid=1;
    } else {
        $rawconfig['admin_passwd']=$newpasswd;
    }
  } else {
    if ($newpasswd)
       $rawconfig['admin_passwd']=$newpasswd;
  }

  if ($update == 'Update') {
    if ($rawconfig['charset'] && $rawconfig['sitename']) {
      if (function_exists('iconv')) {
        $ncharset=strtoupper($rawconfig['charset']);

        # check and translate to the supported charset names
        $charset_map=array('X-WINDOWS-949'=>'UHC');

        $dummy=explode(';',$_SERVER['HTTP_ACCEPT_CHARSET'],2);
        $charsets=array_map('strtoupper',explode(',',$dummy[0]));
        #print_r($charsets);
        $charset=$charsets[0];
        if (!empty($charset_map[$charset])) $charset=$charset_map[$charset];

        # convert sitename to proper encoding
        if (isset($ncharset) and $charset != $ncharset)
          $out=iconv($charset,$ncharset,$rawconfig['sitename']);
        if ($out) $rawconfig['sitename']=$out;
      }
    }
    if (!empty($invalid))
      print "<h2>".sprintf(_("Updated Configutations for this %s"),$config['sitename'])."</h2>\n";
    $lines=$Config->_genRawConfig($rawconfig);
    print "<pre class='console'>\n";
    $rawconf=join("",$lines);
    #
    ob_start();
    highlight_string($rawconf);
    $highlighted= ob_get_contents();
    ob_end_clean();
    #print str_replace("<","&lt;",$rawconf);
    print $highlighted;
    print "</pre>\n";

    if (empty($invalid) && (is_writable("config.php") || !file_exists("config.php"))) {
      umask(000);
      $fp=fopen("config.php","w");
      fwrite($fp,$rawconf);
      fclose($fp);
      @chmod("config.php",0666);
      print "<h2><font color='blue'>"._("Configurations are saved successfully")."</font></h2>\n";
      print "<h3><font color='green'>"._("WARN: Please check <a href='monisetup.php'> your saved configurations</a>")."</font></h3>\n";
      print _("If all is good, change 'config.php' permission as 644.")."<br />\n";
    } else {
      if ($invalid) {
        print "<h3><font color='red'>You Can't write this settings to 'config.php'</font></h3>\n";
      }
    }
  }
  # print "<h2>Read current settings for this $config[sitename]</h2>\n";
} else {
  # read settings

  if (!$Config->config) {
    print "<h2>"._("Welcome to MoniWiki ! This is your first installation")."</h2>\n";
    $Config->getDefaultConfig();
    $config=$Config->config;

    checkConfig($config);

    $rawconfig=$Config->rawconfig;
    print "<h3 color='blue'>"._("Default settings are loaded...")."</h3>\n";

    $lines=$Config->_genRawConfig($rawconfig);
    $rawconf=implode("",$lines);
    umask(000);
    $fp=fopen("config.php","w");
    fwrite($fp,$rawconf);
    fclose($fp);
    @chmod("config.php",0666);
    print "<h2><font color='blue'>"._("Initial configurations are saved successfully.")."</font></h2>\n";
    print "<h3><font color='red'>"._("Goto <a href='monisetup.php'>MoniSetup</a> again to configure details")."</font></h3>\n";
    exit;
  } else {
    $config=$Config->config;
    checkConfig($config);
    $rawconfig=&$Config->rawconfig;
    $configdesc=&$Config->configdesc;
  }
}

if ($_SERVER['REQUEST_METHOD']=="POST") {
  $seeds=isset($_POST['seeds']) ? $_POST['seeds']:'';
  $action=isset($_POST['action']) ? $_POST['action']:'';
  if ($action=='sow_seed' && $seeds) {
    sow_wikiseed($config,'wikiseed',$seeds);
    print "<h2>WikiSeeds are sowed successfully</h2>";
    if (file_exists('wiki.php'))
      print "<h2>".sprintf(_("goto %s"),"<a href='wiki.php'>$config[sitename]</a>")."</h2>";
    else
      print "<h2>".sprintf(_("goto %s"),"<a href='".$config[url_prefix]."'>$config[sitename]</a>")."</h2>";
    exit;
  } else if ($action=='sow_seed' && !$seeds) {
    print "<h2><font color='red'>"._("No WikiSeeds are selected")."</font></h2>";
    exit;
  }
} else {
  if ($action=='seed') {
    show_wikiseed($config,'wikiseed');
    exit;
  }
}

  if ($update == 'Preview')
  print "<h2>".sprintf(_("Preview current settings for this %s"),$config['sitename'])."</h2>\n";
  else
  print "<h2>".sprintf(_("Read current settings for this %s"),$config['sitename'])."</h2>\n";
  print "<div class='oldset'>";
  print"<table class='wiki' align='center' border='1' cellpadding='2' cellspacing='2'>";
  print "\n";
  while (list($key,$val) = each($config)) {
    if ($key != "admin_passwd" && $key != "purge_passwd")
    if (is_string($val) and !preg_match('/<img /',$val))
      $val=str_replace(array('<',"\n"),array('&lt;',"<br />\n"),$val);
    else if (is_array($val)) {
      $o=array();
      foreach ($val as $k=>$v) {
        if (is_numeric($k)) {
          if (is_string($v)) { $o[]='"'.$v.'"'; }
          else $o[]=$v;
        } else if (is_string($k)) {
          // XXX
          if (is_string($v)) {$o[]='"'.$k.'"=>"'.$v.'"';}
          else $o[]='"'.$k.'"=>'.$v;
        }
        $val='array('.implode(',',$o).')'; 
      }
      $val=str_replace(array('<',"\n"),array('&lt;',"<br />\n"),$val); // XXX
    }
    print "<tr><td class='preview'>\$$key</td><td>$val</td></tr>\n";
  }
  print "</table>\n</div>\n";

if ($_SERVER['REQUEST_METHOD']!="POST") {
  print "<h2>"._("Change your settings")."</h2>\n";
  if (empty($config['admin_passwd']))
  print "<h3><font color='red'>"._("WARN: You have to enter your Admin Password")."</h3>\n";
  else if (file_exists('config.php') && !file_exists($config['data_dir']."/text/RecentChanges")) {
    print "<h3><font color='red'>".sprintf(_("WARN: You have no WikiSeed on your %s"),$config['sitename'])."</font></h3>\n";
    print "<h2>".sprintf(_("If you want to put wikiseeds on your wiki %s now"),
      "<a href='?action=seed'>"._("Click here")."</a>")."</h2>";
  }
  print "<form method='post' action=''>\n";
  print "<div class='newset'>\n";
  print "<table align='center' border='0' cellpadding='2' cellspacing='2'>\n";
  while (list($key,$val) = each($rawconfig)) {
    if ($key != "admin_passwd") {
      print "<tr><td class='option'>$$key</td>";
      if (strpos($val,"\n")) $type="textarea";
      else $type="input";

      if ($type=='input') {
        $val=str_replace('"',"&#34;",$val);
        print "<td class='option'><$type type='text' name='config[$key]' value=\"$val\" size='60'></td></tr>\n";
      } else {
        print "<td><$type name='config[$key]' rows='4' cols='60'>".$val."</$type></td></tr>\n";
      }
      if (!empty($configdesc[$key]))
        print "<td class='desc' colspan='2'>".$configdesc[$key]."</td></tr>\n";
    }
  }

  if (empty($config['admin_passwd'])) {
    print "<tr><td><b>\$admin_passwd</b></td>";
    print "<td><input type='password' name='newpasswd' size='60'></td></tr>\n";
  } else  {
    print "<tr><td><b>Old password</b></td>";
    print "<td><input type='password' name='oldpasswd' size='60'></td></tr>\n";
    print "<tr><td><b>New password</b></td>";
    print "<td><input type='password' name='newpasswd' size='60'></td></tr>\n";
  }
  print "</table></div>";
  print "<div class='step'>";
  print "<input type='submit' name='update' value='"._("Preview")."' /> ";
  if (empty($config['admin_passwd']))
  print "<input type='submit' name='update' value='"._("Update")."' />\n";
  else
  print "<input type='submit' name='update' value='"._("Update")."' />\n";
  print "</div></form>\n";

  if (file_exists('config.php') && !file_exists($config['data_dir']."/text/RecentChanges")) {
    print "<h3><font color='red'>".sprintf(_("WARN: You have no WikiSeed on your %s"),$config['sitename'])."</font></h3>\n";
    print "<h2>".sprintf(_("If you want to put wikiseeds on your wiki %s now"),
      "<a href='?action=seed'>"._("Click here")."</a>")."</h2>";
  } else {
    if (file_exists('wiki.php'))
      print "<h2>".sprintf(_("goto %s"),"<a href='wiki.php'>".$config['sitename'])."</a></h2>";
    else
      print "<h2>".sprintf(_("goto %s"),"<a href='".$config['url_prefix']."'>$config[sitename]")."</a></h2>";
  }
}
  print "</div></body></html>";

?>
