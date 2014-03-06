<?php
// Copyright 2003-2007 Won-Kyu Park <wkpark at kldp.org> all rights reserved.
// distributable under GPL see COPYING 
// $Id$

function _stripslashes($str) {
  return get_magic_quotes_gpc() ? stripslashes($str):$str;
}

// from gforge perl snippet
function randstr($num) {
    for ($i = 0, $bit = "!", $key = ""; $i < $num; $i++) {
        while (!preg_match('/^[0-9A-Za-z]$/', $bit)) {
            $bit = chr(rand(0, 90) + 32);
        }
        $key .= $bit;
        $bit = "!";
    }
    return $key;
}

class MoniConfig {
  function MoniConfig($configfile="config.php") {
    if (file_exists($configfile)) {
      $url_prefix= preg_replace("/\/([^\/]+)\.php$/","",$_SERVER['SCRIPT_NAME']);
      $config['url_prefix']=$url_prefix;
      $this->config=$this->_getConfig($configfile,$config);
      $this->rawconfig=$this->_rawConfig($configfile);
      $this->configdesc=$this->_getConfigDesc($configfile);
    } else {
      $this->config=array();
      $this->rawconfig=array();
    }
  }

  function getDefaultConfig($configfile = 'config.php.default') {
    $hostconfig=$this->_getHostConfig();
    $this->config=$this->_getConfig($configfile,$hostconfig);

    $hostconf = $this->_quote_config($hostconfig);
    $this->rawconfig=array_merge($this->_rawConfig($configfile),$hostconf);
    while (list($key,$val)=each($hostconf)) {
      eval("\$$key=$val;");
      eval("\$this->config[\$key]=$val;");
    }
  }
  function _getHostConfig() {
    print '<div class="check">';
    if (function_exists("dba_open")) {
      print '<h3>'._t("Check a dba configuration").'</h3>';
      $dbtypes = dba_handlers(true);
      $dbtests = array('db4', 'db3', 'db2', 'gdbm', 'flatfile');
      foreach ($dbtests as $mydb) {
        if (isset($dbtypes[$mydb])) {
          $config['dba_type'] = $mydb;
          break;
        }
      }

      if (!empty($config['dba_type'])) {
        print '<ul><li>'.sprintf(_t("%s is selected."),"<b>$config[dba_type]</b>").'</li></ul>';
      } else {
        print '<p>'.sprintf(_t("No \$dba_type selected.")).'</p>';
      }
    }
    // set random seed for security
    $config['seed'] = randstr(64);

    preg_match("/Apache\/2\./",$_SERVER['SERVER_SOFTWARE'],$match);
    if (preg_match('/^\d+\.\d+\.\d+\.\d+$/', $_SERVER['SERVER_ADDR'])) {
      $host = $_SERVER['SERVER_ADDR'];
    } else {
      $host = $_SERVER['SERVER_NAME'];
    }

    if (empty($match)) {
      $config['query_prefix']='?';
      while (ini_get('allow_url_fopen')) {
        print '<h3>'._t("Check a AcceptPathInfo setting for Apache 2.x.xx").'</h3>';
        print '<ul>';
        $fp=@fopen('http://'.$host.':'.$_SERVER['SERVER_PORT'].$_SERVER['SCRIPT_NAME'].'/pathinfo?action=pathinfo','r');
        $out='';
        if ($fp) {
          while (!feof($fp)) $out.=fgets($fp,2048);
        } else {
          print "<li><b><a href='http://moniwiki.kldp.net/wiki.php/AcceptPathInfo'>AcceptPathInfo</a> <font color='red'>"._t("Off")."</font></b><li>\n";
          print '</ul>';
          break;
        }
        fclose($fp);
        if ($out[0] == '*') {
          print "<li><b><a href='http://moniwiki.kldp.net/wiki.php/AcceptPathInfo'>AcceptPathInfo</a> <font color='red'>"._t("Off")."</font></b></li>\n";
        } else {
          print "<li><b>AcceptPathInfo <font color='blue'>"._t("On")."</font></b></li>\n";
          $config['query_prefix']='/';
        }
        print '</ul>';
        break;
      }
    }

    $url_prefix= preg_replace("/\/([^\/]+)\.php$/","",$_SERVER['SCRIPT_NAME']);
    $config['url_prefix']=$url_prefix;

    $user = getenv('LOGNAME');
    $user = $user ? $user : 'root';
    $config['rcs_user']=$user;

    if(getenv("OS")=="Windows_NT") {
      $config['timezone']="'-09-09'";
      // http://kldp.net/forum/message.php?msg_id=7675
      // http://bugs.php.net/bug.php?id=22418
      //$config['version_class']="'RcsLite'";
      $config['path'] = './bin;';
      if (is_dir('../bin')) $config['path'].= '../bin;'; // packaging for win32
      $config['path'].= 'c:/program files/vim/vimXX';
      $config['vartmp_dir']="c:/tmp/";
    } else {
      $config['rcs_user']='root'; // XXX
    }

    if (!file_exists('wikilib.php')) {
      $checkfile = array('plugin','locale');
      $dir='';
      foreach ($checkfile as $f) {
        if (is_link($f)) {
          $dir = dirname(readlink($f));
        }
      }
      $config['include_path']=".:$dir";
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

  function _getConfig($configfile, $options = array()) {
    $myconfig = basename($configfile);
    if (!file_exists($myconfig))
      return array();

    extract($options);
    unset($options);
    include($myconfig);
    unset($configfile);
    unset($myconfig);
    $config=get_defined_vars();

    return $config;
  }

  function _rawConfig($configfile, $options = array()) {
    $lines=file($configfile);
    $key='';
    foreach ($lines as $line) {
      $line=rtrim($line)."\n"; // for Win32

      if (!$key and $line[0] != '$') continue;
      if ($key) {
        $val.=$line;
        if (!preg_match("/$tag\s*;(\s*#.*)?\s*$/",$line)) continue;
      } else {
        list($key,$val)=explode('=',substr($line,1),2);
        if (!preg_match('/\s*;(\s*#.*)?$/',$val)) {
          if (substr($val,0,3)== '<<<') $tag='^'.substr(rtrim($val),3);
          else {
            $val = ltrim($val);
            $tag = '';
          }
          continue;
        }
      }

      if ($key) {
      	$val=preg_replace(array('@<@','@>@'),array('&lt;','&gt;'),$val);
        #print $key.'|=='.preg_quote($val);
	$val=rtrim($val);
        $val=preg_replace('/\s*;(\s*#.*)?$/','',$val);
        $config[$key]=$val;
      	$key='';
      	$tag='';
      }
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

  function _quote_config($config) {
    foreach ($config as $k=>$v) {
      if (is_string($v)) {
        $v='"'.$v.'"'; // XXX need to check quotes
      } else if (is_bool($v)) {
        if ($v) $nline="true";
        else $v="false";
      }
      $config[$k] = $v;
    }
    return $config;
  }

  function _genRawConfig($newconfig, $mode = 0, $configfile='config.php', $default='config.php.default') {
    if (!empty($newconfig['admin_passwd']))
      $newconfig['admin_passwd']=crypt($newconfig['admin_passwd'],md5(time()));
    if (!empty($newconfig['purge_passwd']))
      $newconfig['purge_passwd']=crypt($newconfig['purge_passwd'],md5(time()));

    if ($mode == 1) {
      $newconfig = $this->_quote_config($newconfig);
    } else {
      if (isset($newconfig['admin_passwd']))
        $newconfig['admin_passwd']="'".$newconfig['admin_passwd']."'";
      if (isset($newconfig['purge_passwd']))
        $newconfig['purge_passwd']="'".$newconfig['purge_passwd']."'";
    }

    if (file_exists($configfile))
      $conf_file = $configfile;
    else if (file_exists($default))
      $conf_file = $default;
    else
      return $this->_genRawConfigSimple($newconfig);
  
    $lines = file($conf_file);

    $config = array();
    $nlines='';
    $key='';
    $tag='';
    foreach ($lines as $line) {
      $line=rtrim($line)."\n"; // for Win32

      if (!$key) {
        // first line
        if ($line{0} == '<' and $line{1} == '?') {
          $date = date('Y-m-d h:i:s');
          $nlines[]='<'.'?php'."\n";
          $nlines[]=<<<HEADER
# This is a config.php file for the MoniWiki
# automatically detect your environment and set some default variables.
# $date by monisetup.php\n
HEADER;
          continue;
        } else if (preg_match('/^(#{1,}\s*)?\$[a-zA-Z][a-zA-Z0-9_]*\s*=/', $line, $m)) {
          $marker = isset($m[1]) ? $m[1] : '';
          if ($marker != '')
            $mre = '#{1,}';
          else
            $mre = '';
          $mlen = strlen($marker.'$');
        } else {
          $nlines[]=$line;
          continue;
        }
      }

      if ($key) {
        $val.=$line;
        if (!preg_match("/$tag\s*;(\s*(?:#|\/\/).*)?\s*$/",$line,$m)) continue;
        $mre = '';
        $desc[$key] = isset($m[1]) ? rtrim($m[1]) : '';
      } else {
        list($key,$val)=explode('=',substr($line,$mlen),2);
        $key = trim($key);
        if (!preg_match('/(\s*;(\s*(?:#|\/\/).*)?)$/',$val,$match)) {
          if (substr($val,0,3)== '<<<') {
            $tag='^'.$mre.substr(rtrim($val),3);
          } else {
            $val = ltrim($val);
            $tag = '';
          }
          continue;
        } else {
          $val = substr($val,0,-strlen($match[1])-1);
          #$val .= '##########X'.$val.'==='.$match[1].'XX####';
          if (isset($match[2])) {
            $desc[$key] = rtrim($match[2]);
          } else {
            $desc[$key] = '';
          }
        }
      }

      if (trim($key)) {
        $t = true;
        if (isset($newconfig[$key])) {
          if (!isset($config[$key])) {
            $val=$newconfig[$key];
            $newconfig[$key] = NULL;
            $marker = ''; # uncomment marker
          }
        } else {
          $val=preg_replace(array('@<@','@>@'),array('&lt;','&gt;'),$val);
          #print $key.'|=='.preg_quote($val);
          $val=rtrim($val);
          if (empty($marker))
            $val=preg_replace('/\s*;(\s*(?:#|\/\/).*)?$/','',$val);
        }
        $val = str_replace(array('&lt;','&gt;'),array('<','>'),$val);
        if (isset($config[$key])) {
          $val = rtrim($val);
          $val = str_replace("\n", "\n#", $val);
          if (!$marker) $marker = '#';
          $nline=$marker."\$$key=$val;"; # XXX
          if ($desc[$key]) $nline .= $desc[$key];
          $nline .= "\n";
          $t=NULL;
        } else if (empty($marker) and preg_match("/^<{3}([A-Za-z0-9]+)\s.*\\1\s*$/s",$val,$m)) {
          $config[$key] = $val;
          $save_val=$val;
          $val=str_replace("$m[1]",'',substr($val,3));
          $val=str_replace('"','\"',$val);
          $t=eval("\$$key=\"$val\";");
          $val=$save_val;
          $nline="\$$key=$val;\n";
        } else if ($marker) {
          $val = str_replace('&gt;','>',$val);
          $nline=$marker."\$$key=$val";
          if (empty($tag)) $nline .=';';
          if ($desc[$key]) $nline .= $desc[$key];
          $nline .= "\n";
          $config[$key] = $val;
          $t=NULL;
        } else if (is_string($val)) {
          $val = str_replace('&gt;','>',$val);
          if (strpos($val,"\n")===false) {
            $t=eval("\$$key=$val;");
          } else {
            $t=@eval("\$$key=$val;");
          }
          $nline="\$$key=$val;";
          if ($desc[$key]) $nline .= $desc[$key];
          $nline .= "\n";
        } else {
          $t=@eval("\$$key=$val;");
        }
        if ($t === NULL) {
          $nlines[]=$nline;
          $config[$key] = $val;
        }
        else
          print "ERROR: \$$key =$val;\n";
        $key = '';
        $tag = '';
      }
    }
    if (!empty($newconfig)) {
      foreach ($newconfig as $k=>$v) {
        if ($v != NULL)
        $nlines[] = '$'.$k.'='.$v.";\n";
      }
    }

    return join('',$nlines);
  }

  function _genRawConfigSimple($config) {
    $lines=array("<?php\n","# automatically generated by monisetup\n");
    while (list($key,$val) = each($config)) {
      if ($key=='admin_passwd' or $key=='purge_passwd')
         $val="'".crypt($val,md5(time()))."'";
      $val = str_replace('&lt;','<',$val);
      if (preg_match("/^<{3}([A-Za-z0-9]+)\s.*\\1\s*$/s",$val,$m)) {
         $save_val=$val;
         $val=str_replace("$m[1]",'',substr($val,3));
         $val=preg_quote($val,'"');
         $t=@eval("\$$key=\"$val\";");
         $val=$save_val;
      } else if (is_string($val)) {
         $val = str_replace('&gt;','>',$val);
         if (strpos($val,"\n")===false) {
           $t=eval("\$$key=$val;");
         } else {
           $t=@eval("\$$key=$val;");
         }
      } else {
         $t=@eval("\$$key=$val;");
      }
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
    return implode('',$lines);
  }
}

function checkConfig($config) {
  umask(011);
  $dir=getcwd();

  if (!file_exists("config.php") && !is_writable(".")) {
     print "<h3 class='warn'>".
	_t("Please change the permission of some directories writable on your server to initialize your Wiki.")."</h3>\n";
     print "<pre class='console'>\n<font color='green'>$</font> chmod <b>777</b> $dir/data/ $dir\n</pre>\n";
     print sprintf(_t("If you want a more safe wiki, try to change the permission of directories with %s."),
		"<font color='red'>2777(setgid).</font>\n");
     print "<pre class='console'>\n<font color='green'>$</font> chmod <b>2777</b> $dir/data/ $dir\n</pre>\n";
     print _t("or use <tt>monisetup.sh</tt> and select 777 or <font color='red'>2777</font>");
     print "<pre class='console'>\n<font color='green'>$</font> sh monisetup.sh</pre>\n";
     print _t("After execute one of above two commands, just <a href='monisetup.php'>reload this <tt>monisetup.php</tt></a> would make a new initial <tt>config.php</tt> with detected parameters for your wiki.")."\n<br/>";
     print "<h2><a href='monisetup.php?step=agree'>"._t("Reload")."</a></h2>";
     exit;
  } else if (file_exists("config.php")) {
     print "<p class='notice'><span class='warn'>"._t("WARN").":</span> ".
	_t("Please execute the following command after you have completed your configuration.")."</p>\n";
     print "<pre class='console'>\n<font color='green'>$</font> sh secure.sh\n</pre>\n";
     if (is_writable('config.php')) {
       if (empty($config['admin_passwd'])) {
         print "<h2 class='warn'>"._t("WARN: You have to enter your Admin Password")."</h2>\n";
       } else {
         $owner = fileowner('.');
         print "<h2 class='warn'>"._t("WARN: If you have any permission to execute 'secure.sh'. press the following button")."</h2>\n";
         
         $msg = _t("Protect my config.php now!");
         echo <<<FORM
<form method='post' action=''>
<div class='protect'><input type='hidden' name='action' value='protect' /><input type='submit' name='protect' value='$msg' /></div>
</form>
FORM;
       }
     }
  }

  if (file_exists("config.php")) {
    if (!is_writable($config['data_dir'])) {
      if (02000 & fileperms(".")) # check sgid
        $datadir_perm = 02777;
      else
        $datadir_perm = 0777;
      $datadir_perm = decoct($datadir_perm);
      print "<h3 class='error'>".sprintf(_t("FATAL: %s directory is not writable"),$config['data_dir'])."</h3>\n";
      print "<h4>"._t("Please execute the following command.")."</h4>";
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
           print "<h4 class='warn'>".sprintf(_t("%s directory is not writable"),$dir )."</h4>\n";
           print "<pre class='console'>\n".
             "<font color='green'>$</font> chmod a+w $config[$file]\n</pre>\n";
       }
    }

    $writables=array("upload_dir",'cache_public_dir',"editlog_name");

    $is_apache = preg_match('/apache/i', $_SERVER['SERVER_SOFTWARE']);
    $port= ($_SERVER['SERVER_PORT'] != 80) ? $_SERVER['SERVER_PORT']:80;
    $path = preg_replace('/monisetup\.php/','',$_SERVER['SCRIPT_NAME']);
    if (preg_match('/^\d+\.\d+\.\d+\.\d+$/', $_SERVER['SERVER_ADDR'])) {
      $host = $_SERVER['SERVER_ADDR'];
    } else {
      $host = $_SERVER['SERVER_NAME'];
    }

    print '<div class="check">';
    foreach($writables as $file) {
      if (empty($config[$file])) continue;
      if (!is_writable($config[$file])) {
        if (file_exists($config[$file])) {
          print "<h3 class='warn'>".sprintf(_t("%s is not writable"),$config[$file])." :( </h3>\n";
          print "<pre class='console'>\n".
              "<font color='green'>$</font> chmod a+w $config[$file]\n</pre>\n";
        } else {
          if (preg_match("/_dir/",$file)) {
            umask(000);
            mkdir($config[$file],$DPERM);
            print "<h3>&nbsp;&nbsp;<font color=blue>".sprintf(_t("%s is created now"),$config[$file])."</font> :)</h3>\n";
          } else {
            $fp=@fopen($config[$file],"w+");
            if ($fp) {
              chmod($config[$file],0666);
              fclose($fp);
              print "<h4><font color='green'>".sprintf(_t("%s is created now"),$config[$file])."</font> ;) </h4>\n";
            } else {
              print "<pre class='console'>\n".
              "<font color='green'>$</font> touch $config[$file]\n".
              "<font color='green'>$</font> chmod a+w $config[$file]\n</pre>\n";
            }
          }
        }
        $error=1;
      } else
        print "<h3><font color=blue>".sprintf(_t("%s is writable"),$config[$file])."</font> :)</h3>\n";
    }
    if ($is_apache && is_dir($config['upload_dir'])) {
      echo "<div class='helpicon'><a href='http://moniwiki.kldp.net/wiki.php/.htaccess'>?</a></div>";
      $chk=array(
          'AddType'=>"AddType text/plain .sh .cgi .pl .py .php .php3 .php4 .phtml .html\n",
          'ForceType'=>"<Files ~ '\.(php.?|pl|py|cgi)$'>\nForceType text/plain\n</Files>\n",
          'php_value'=>"AddType text/plain .php\nphp_value engine off\n",
          'NoExecCGI'=>"#Options NoExecCGI\n",
          );
      $re = array(
          '@^HTTP/1.1\s+\d+\s+OK$@'=>1, //
          '@^HTTP/1.1\s+500\s+@'=>-1, // Fail
          '@^Content-Type: text/plain@'=> 2, // OK
          '@^Content-Type: text/html@'=> 0, // BAD
          );

      if (file_exists($config['upload_dir'].'/.htaccess')) {
        print '<h3>'.sprintf(_t("If you want to check .htaccess file please delete '%s' file and reload it."),
            $config['upload_dir'].'/.htaccess').'</h3>';
      } else {
        print '<h3>'._t("Security check for 'upload_dir'.").'</h3>';

        $fp = fopen('pds/test.php','w');
        if (is_resource($fp)) {
          fwrite($fp,"<?php echo 'HelloWorld';");
          fclose($fp);
        }

        echo "<div class='log'>";
        $work = check_htaccess($chk, $re, $host, $port,
          $path.'/'.$config['upload_dir'].'/test.php',
          $config['upload_dir']);
        echo "</div>";

        $fp = @fopen('pds_htaccess','w');
        if (is_resource($fp)) {
          fwrite($fp,implode('',$work));
          fclose($fp);
        } else {
          echo _t("Unable to open .htaccess");
        }
        @unlink('pds/test.php');
      }
      # 
      $chk2=array(
          'ErrorDocument'=>
            "#ErrorDocument 404 ".$config['url_prefix'].'/imgs/moni/inter.png'."\n",
          );
      $re = array(
          '@^HTTP/1.1\s+\d+\s+OK$@'=>1, //
          '@^HTTP/1.1\s+500\s+@'=>-1, // Fail
          '@^HTTP/1.1\s+404\s+@'=>2, // OK
          '@^Content-Type: text/png@'=> 2, // OK
          );
      if (is_dir('imgs') and
          !file_exists($config['upload_dir'].'/.htaccess')) {
        print '<h3>'._t(".htaccess for 'imgs_dir'.").'</h3>';
        echo "<div class='log'>";
        $work = check_htaccess($chk2, $re, $host, $port,
          $path.'/'.$config['upload_dir'].'/nonexists.png',
          $config['upload_dir']);
        echo "</div>";

        $fp=@fopen('imgs_htaccess','w');
        if (is_resource($fp)) {
          fwrite($fp,implode('',$work));
          fclose($fp);
        } else {
          echo _t("Unable to open .htaccess");
        }
      }
    }
    print "</div>\n";
  }
}

function check_htaccess($chk, $re, $host, $port, $path, $dir) {
  $work = array();

  echo "<ul>";
  foreach ($chk as $c=>$v) {
    $fp = fopen($dir.'/.htaccess','w');
    if (is_resource($fp)) {
      fwrite($fp,preg_replace('/^#/','',$v));
      fclose($fp);

      $fp = fsockopen($host, $port, $errno, $errstr, 10);

      if (is_resource($fp)) {
        $send = "GET $path HTTP/1.1\r\n";
        $send.= "Host: $host\r\n";
        $send.= "Connection: Close\r\n\r\n";
        fwrite($fp, $send);

        $out='';
        $ok = false;
        while(!feof($fp)) {
          $line = fgets($fp,1024);
          $line = rtrim($line);
        
          foreach ($re as $kk=>$vv) {
            if ($vv > 0 and preg_match($kk,$line)) {
              $ok = $vv;
              $out .= $line."\n";
              if ($vv == 1) continue;
              if ($vv == 2) break 2;
            } else if ($vv <= 0 and preg_match($kk,$line)) {
              $ok = $vv;
              $out .= $line."\n";
              if ($vv == 0) continue;
              if ($vv == -1) break 2;
              break;
            }
          }
        }
        fclose($fp);

        print "<pre>".$out."</pre>";
        if ($ok > 0) {
          print "<li>$c => <span style='color:blue'>Good</span></li>\n";
          $v = preg_replace('/^#/','',$v);
        } else if ($ok == 0) {
          print "<li>$c => <span style='color:red'>BAD</span></li>\n";
        } else {
          print "<li>$c => <span style='color:red'>Fail</span></li>\n";
          $v = preg_replace('/^## /','',$v);
        }
        $work[$c]=$v;
      } else {
        echo "<li>$c => "._t("Fail")."<br />\n";
        echo "$errstr ($errno)<br />\n";
        echo "</li>";
        $work[$c]='## '.$v;
      }
    } else {
      print "<li>"._t("Unable to write .htaccess")."</li>";
      break;
    }
  }
  @unlink($dir.'/.htaccess');
  echo "</ul>";
  return $work;
}

// moinmoin 1.0.x style internal encoding
function _pgencode($m) {
  return '_'.sprintf("%02s", strtolower(dechex(ord(substr($m[1],-1)))));
}

function keyToPagename($key) {
#  return preg_replace("/_([a-f0-9]{2})/e","chr(hexdec('\\1'))",$key);
  $pagename=preg_replace("/_([a-f0-9]{2})/","%\\1",$key);
#  $pagename=str_replace("_","%",$key);
#  $pagename=strtr($key,"_","%");
  return rawurldecode($pagename);
}

function pagenameToKey($pagename) {
  return preg_replace_callback("/([^a-z0-9]{1})/i",'_pgencode', $pagename);
}

function show_wikiseed($config,$seeddir='wikiseed') {
  if (!empty($config['include_path']))
    $path = $config['include_path'];
  else
    $path='.:/usr/share/moniwiki:/usr/local/share/moniwiki';
  $pages= array();
  foreach (explode(':',$path) as $dir) {
    if (is_dir($dir.'/'.$seeddir)) {
      $seeddir=$dir.'/'.$seeddir;
      break;
    } else if (is_dir($dir.'/data/text') and file_exists($dir.'/data/text/FrontPage')) {
      $seeddir=$dir.'/data/text';
      break;
    }
  }
  $handle= @opendir($seeddir);
  if (is_resource($handle)) {
    while ($file = readdir($handle)) {
      if (is_dir($seeddir."/".$file)) continue;
      $pagename = keyToPagename($file);
      $pages[$pagename] = $pagename;
    }
    closedir($handle);
  }
#  sort($pages);
  $idx=1;

  $num=sizeof($pages);

  #
  $SystemPages="FrontPage|RecentChanges|TitleIndex|FindPage|WordIndex|".
  "EditTextForm|AliasPageNames|InterIconMap|".
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

  $js=<<<JS
<script type='text/javascript'>
function Toggle(obj) {
   var p=document.getElementById(obj);
   var n=p.getElementsByTagName('input');
   for (var i=0;i<n.length;i++)
     if (n[i].checked) n[i].checked=false;
     else n[i].checked=true;
   var p=document.getElementById('systemseed');
   var n=p.getElementsByTagName('input');
   for (var i=0;i<n.length;i++)
     n[i].checked=true;
}
function deselect(obj) {
   var p=document.getElementById(obj);
   var n=p.getElementsByTagName('input');
   for (var i=0;i<n.length;i++)
     n[i].checked=false;
   var p=document.getElementById('systemseed');
   var n=p.getElementsByTagName('input');
   for (var i=0;i<n.length;i++)
     n[i].checked=true;
}
</script>
JS;

  print $js;
  print "<h3>Total $num pages found</h3>\n";
  print "<h4><a href='#' onclick='Toggle(\"seedall\")' >"._t("Click here to toggle all")."</a> / ";
  print "<a href='#' onclick='deselect(\"seedall\")' >"._t("Deselect all")."</a></h4>\n";
  print "<form id='seedall' method='post' action=''>\n";
  $ii=1;
  while (list($filter_name,$filter) = each($seed_filters)) {
    if ($filter_name == 'SystemPages') {
    	print "<h4>$filter_name ("._t("Please be careful to deselect these pages").")</h4>\n";
    	print "<div id='systemseed'>\n";
    } else {
    	print "<h4>$filter_name <a href='#' onclick='Toggle(\"set$ii\")' >(toggle)</a></h4>\n";
    	print "<div id='set$ii'>\n";
    }
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
    print "</div>\n";
    $ii++;
    $wrap=1;
  }
  print "<input type='hidden' name='action' value='sow_seed' />\n";
  print "<br /><input type='submit' value='sow WikiSeeds'></form>\n";
}

function sow_wikiseed($config,$seeddir='wikiseed',$seeds) {
  if (!empty($config['include_path']))
    $path = $config['include_path'];
  else
    $path='.:/usr/share/moniwiki:/usr/local/share/moniwiki';
  $pages= array();
  foreach (explode(':',$path) as $dir) {
    if (is_dir($dir.'/'.$seeddir)) {
      $seeddir=$dir.'/'.$seeddir;
      break;
    } else if (is_dir($dir.'/data/text') and file_exists($dir.'/data/text/FrontPage')) {
      $seeddir=$dir.'/data/text';
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

# setup the locale like as the phpwiki style
# from wiki.php
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

$_locale = array();

function initlocale($lang,$charset) {
  global $_Config,$_locale,$locale;
  if (!@include_once('locale/'.$lang.'/LC_MESSAGES/moniwiki.php') and
     @include_once('locale/'.substr($lang,0,2).'/LC_MESSAGES/moniwiki.php')) {
    if (!empty($_locale)) {
      function _t($text) {
        global $_locale;
        if (!empty ($_locale[$text]))
          return $_locale[$text];
        return $text;
      }
      return;
    }
  }
  function _t($text) {
    return gettext($text);
  }
  
  if (substr($lang,0,2) == 'en') {
    $test=setlocale(LC_ALL, $lang);
  } else {
    if ($_Config['include_path']) $dirs=explode(':',$_Config['include_path']);
    else $dirs=array('.');

    $domain='moniwiki';

    $test=setlocale(LC_ALL, $lang);
    foreach ($dirs as $dir) {
      $ldir=$dir.'/locale';
      if (is_dir($ldir)) {
        bindtextdomain($domain, $ldir);
        textdomain($domain);
        break;
      }
    }
    if (function_exists('bind_textdomain_codeset'))
      bind_textdomain_codeset ($domain, $charset);
  }
}

$_Config['include_path']='';
$_Config['charset']='UTF-8';

if (empty($_GET['lang']))
  $lang = 'auto';
else
  $lang = $_GET['lang'];

$lang = set_locale($lang,$_Config['charset']);
initlocale($lang,$_Config['charset']);

if (function_exists('date_default_timezone_set')) {
  // suppress date() warnings for PHP5.x
  date_default_timezone_set(@date_default_timezone_get());
}

if (!empty($_GET['action']) and $_GET['action'] =='pathinfo') {
  print $_SERVER['PATH_INFO'].'****';
  return;
}

print <<<EOF
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head><title>Moni Setup</title>
<meta http-equiv="Content-Type" content="text/html; charset=$_Config[charset]" /> 
<meta name='viewport' content='width=device-width' />
<style type="text/css">
<!--
body { background-color: #e0e0e0; }
.body {
  background:#fff;
  font-family: Tahoma,"Times New Roman", Times, sans-serif;
  margin-left: 10%;
  margin-right: 10%;
  margin-top: 1em;
  box-shadow: 0 2px 6px rgba(100, 100, 100, 0.3);
  border-radius: 3px;
  padding-bottom: 2em;
  font-size: 84%;
}

.header {
  border-radius: 1px;
  font-size: 0.8em;
  background: #3e3e3e;
  width: 100%;
  /* color: #4A6071; /* */
  color: #e0e0e0; /* */
  text-shadow: #000000 1px 1px 1px;
}

.main {
  padding: 0.1em 1.5em;
}

h1 {
  display:inline;
  font-family: "Trebuchet MS", Tahoma,"Times New Roman", Times, sans-serif;
  padding-left: 5px;
  font-size: 20px;
}

h2 {
  font-size:1.2em;
}

h3 {
  font-size:1em;
}

h2,h3,h4,h5 {
  font-family:"Trebuchet MS",sans-serif;
/* background-color:#E07B2A; */
  padding-left:6px;
  border-left:4px solid #3366ff;
  border-bottom: 2px solid #e0e0e0;
}

.check h2, .check h3, .check h4, .check h5 {
  border: none;
}

table.wiki {
/* border-collapse: collapse; */
  border: 0px outset #E2ECE5;
  font-family:"bitstream vera sans mono",monospace;
}

div.log {
  padding: 5px;
  color: black;
  background-color: #e0e0e0;
  border-radius: 3px;
  box-shadow: 0 -2px -2px rgba(100, 100, 100, 0.4);
}

pre.license {
  border-radius: 4px;
  font-family:"bitstream vera sans mono",monospace;
  background-color:#eee;
  border-radius: 4px;
  padding: 5px;
  height: 300px;
  overflow-y: auto;
  }

pre.console {
  background-color:#000;
  padding: 1em 0.5em 0.5em 1em;
  border: 1px inset #eeeeee;
  color:white;
  width:80%;
  font-size: 12px;
  border-radius: 4px;
  font-family:"bitstream vera sans mono",monospace;
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
  font-family:monospace;
  background-color:#E6E6E6;
  font-weight:bold;
  text-shadow: #c0c0c0 1px 1px 1px;
}

td.option {
  font-size:12px;
  font-family:monospace;
  background-color:#E6E6E6;
  text-shadow: #c0c0c0 1px 1px 1px;
  font-weight:bold;
  color:black;
}

.newset table input {
  background-color:#ffffff;
  border:0px solid #c0c0c0;
}

td.desc {
  font-family:Trebuchet MS,sans-serif;
  text-align:right;
  padding:10px;
}

span.warn {
  color:red;
}

.warn { color:#aa0000; }
.error { color:#ff0000; }

.notice {
  font-size:18px;
  color: #4B0000;
  font-weight: bold;
}

.check {
  background: #f2f2f2;
  margin-left:2em;
  margin-right:2em;
  padding:0.5em;
  border-radius: 3px;
  box-shadow: 0 2px 6px rgba(100, 100, 100, 0.3);
}

.oldset {
  height: 300px;
  overflow-y: scroll;
  background: #f2f2f2;
}

.newset {
  height: 300px;
  overflow-y: scroll;
  border:0px;
}

.step {
  text-align: right;
}

.step input {
  font-size: 1.5em;
  font-weight:bold;
  font-family: Trebuchet MS, "Times New Roman", Times, sans-serif;
}

.protect {
  font-size: 1.5em;
  font-family: Trebuchet MS, "Times New Roman", Times, serif;
}

.protect input {
  font-size: 1em;
  font-weight:bold;
  font-family: Trebuchet MS, "Times New Roman", Times, serif;
}

input[type="submit"] {
  background-color: #444444;
  background-image: -webkit-linear-gradient(top, #969696, #444444);
  border: none;
  color: #f0f0f0;
  font-weight: bold;
  border-radius: 3px;
  padding: 10px;
}

#lang {
  float: right;
  padding-right: 6px;
}
.helpicon {
  display: inline-block;
  float: right;
  border-radius: 4px;
  color: red;
  border: 1px solid gray;
  padding: 0 5px 0 5px;
}
.helpicon a {
  text-decoration: none;
}
-->
</style>
</head>
<body>
EOF;

print "<div class='body'><div class='header'>";
echo "<div id='lang'>";
echo "<form action=''>";
echo "<select name='lang' onchange='submit()'>";
$ls = array('ko'=>'korean',
            'en'=>'english',
            'fr'=>'france');

$sel = '';
if (!empty($lang) and $lang != 'auto' and isset($ls[$lang]))
  $sel = $lang;
echo "<option value='auto'>--"._t("Select") ."--</option>";
foreach ($ls as $k=>$l) {
  if ($sel == $k)
    $selected = 'selected="selected" ';
  else
    $selected = '';
  echo "<option value='$k' $selected>".ucfirst($l).
      "</option>\n";
}
echo "</select></form></div>";
echo "<h1><img src='imgs/moniwiki-48.png' style='vertical-align: middle'/> "._t("MoniWiki Setup")."</h1></div><div class='main'>\n";

if (empty($_POST['action']) && file_exists("config.php") && !is_writable("config.php")) {
  print "<h2 class='warn'>"._t("'config.php' is not writable !")."</h2>\n";
  print _t("Please execute <tt>'monisetup.sh'</tt> or <tt>chmod a+w config.php</tt> first to change your settings.")."<br />\n";

  $msg = _t("Unprotect my config.php");
  echo "<form method='post' action=''>";
  echo "<div class='protect'>";
  echo "<table><tr><td><strong>Password</strong></td>";
  echo "<td><input type='password' name='oldpasswd' size='10'></td></tr>\n";
  echo "</table>";
  echo <<<FORM
<input type='hidden' name='action' value='protect' /><input type='submit' name='protect' value='$msg' /></div>
</form>
FORM;
  return;
}

$Config=new MoniConfig();

$config=isset($_POST['config']) ? $_POST['config']:'';
$update=isset($_POST['update']) ? $_POST['update']:'';
$action=isset($_GET['action']) ? $_GET['action']:(isset($_POST['action']) ? $_POST['action']:'');
$newpasswd=isset($_POST['newpasswd']) ? $_POST['newpasswd']:'';
$oldpasswd=isset($_POST['oldpasswd']) ? $_POST['oldpasswd']:'';

if ($_SERVER['REQUEST_METHOD']=="POST" && ($config or $action == 'protect')) {

  if ($action == 'protect') {
    if (is_writable('config.php')) {
      $old = 0222 & fileperms("config.php"); # check permission
      if ($old) {
        chmod('config.php',0444);
        print "<h2 class='warn'>"._t("config.php is protected now !")."</h2>\n";
      }
    } else if (!empty($Config->config['admin_passwd'])) {
      if (crypt($oldpasswd,$Config->config['admin_passwd']) != 
          $Config->config['admin_passwd']) {
        print "<h2 class='error'>"._t("Invalid password error !")."</h2>\n";
        print _t("If you can't remember your admin password, delete password entry in the 'config.php' and restart 'monisetup'")."<br />\n";
        $invalid=1;
        return;
      }
      chmod('config.php',0644);
      print "<h2 class='warn'>"._t("config.php is unprotected now !")."</h2>\n";
    }
    return;
  }

  $conf=$Config->_getFormConfig($config);
  $rawconfig=$Config->_getFormConfig($config,1);
  $config=$conf;

  if (!empty($Config->config['admin_passwd'])) {
    if (crypt($oldpasswd,$Config->config['admin_passwd']) != 
      $Config->config['admin_passwd']) {
        if ($update=='Update') {
        print "<h2 class='error'>"._t("Invalid password error !")."</h2>\n";
        print _t("If you can't remember your admin password, delete password entry in the 'config.php' and restart 'monisetup'")."<br />\n";
        }
        $invalid=1;
    } else {
        $rawconfig['admin_passwd']=$newpasswd;
    }
  } else {
    if ($newpasswd)
       $rawconfig['admin_passwd']=$newpasswd;
  }

  if ($update == _t('Update')) {
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
      print "<h2>".sprintf(_t("Updated Configutations for this %s"),$config['sitename'])."</h2>\n";
    $rawconf=$Config->_genRawConfig($rawconfig);
    print "<pre class='console'>\n";
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
      print "<h2><font color='blue'>"._t("Configurations are saved successfully")."</font></h2>\n";
      print "<h3><font color='green'>"._t("WARN: Please check <a href='monisetup.php'> your saved configurations</a>")."</font></h3>\n";
      print _t("If all is good, change 'config.php' permission as 644.")."<br />\n";
    } else {
      if ($invalid) {
        print "<h3 class='error'>You Can't write this settings to 'config.php'</h3>\n";
      }
    }
  }
  # print "<h2>Read current settings for this $config[sitename]</h2>\n";
} else {
  # read settings

  if (!$Config->config) {
    print "<h2>"._t("Welcome to MoniWiki ! This is your first installation")."</h2>\n";

    if (empty($_GET['step'])) {
      if (file_exists('COPYING')) {
        echo "<h1>"._t("License")."</h1>";
        echo "<pre class='license'>";
        echo file_get_contents("COPYING");
        echo "</pre>";
        echo "<form method='get'>";
        echo "<input type='submit' name='step' value='"._t("Agree")."' /> ";
        echo "</form>";
      }
      exit;
    } else {
    $initconfig = 'config.php.default';
    if (!empty($_GET['init']) and file_exists($_GET['init']))
      $initconfig = $_GET['init'];
    $Config->getDefaultConfig($initconfig);
    $config=$Config->config;

    checkConfig($config);

    $rawconfig=$Config->rawconfig;
    print "<h3 color='blue'>"._t("Default settings are loaded...")."</h3>\n";

    $rawconf=$Config->_genRawConfig($rawconfig, 0, 'config.php', $initconfig);
    umask(000);
    $fp=fopen("config.php","w");
    fwrite($fp,$rawconf);
    fclose($fp);
    @chmod("config.php",0666);
    print "<h2><font color='blue'>"._t("Initial configurations are saved successfully.")."</font></h2>\n";
    print "<h3 class='warn'>"._t("Goto <a href='monisetup.php'>MoniSetup</a> again to configure details")."</h3>\n";
    exit;
    }
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
      print "<h2>".sprintf(_t("goto %s"),"<a href='wiki.php'>$config[sitename]</a>")."</h2>";
    else
      print "<h2>".sprintf(_t("goto %s"),"<a href='".$config[url_prefix]."'>$config[sitename]</a>")."</h2>";
    exit;
  } else if ($action=='sow_seed' && !$seeds) {
    print "<h2 class='warn'>"._t("No WikiSeeds are selected")."</h2>";
    exit;
  }
} else {
  if ($action=='seed') {
    show_wikiseed($config,'wikiseed');
    exit;
  }
}

  if ($update == _t('Preview'))
  print "<h2>".sprintf(_t("Preview current settings for this %s"),$config['sitename'])."</h2>\n";
  else
  print "<h2>".sprintf(_t("Read current settings for this %s"),$config['sitename'])."</h2>\n";
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
  print "<h2>"._t("Change your settings")."</h2>\n";
  if (empty($config['admin_passwd']))
  print "<h3 class='warn'>"._t("WARN: You have to enter your Admin Password")."</h3>\n";
  else if (file_exists('config.php') && !file_exists($config['data_dir']."/text/RecentChanges")) {
    print "<h3 class='warn'>".sprintf(_t("WARN: You have no WikiSeed on your %s"),$config['sitename'])."</h3>\n";
    print "<h2>".sprintf(_t("If you want to put wikiseeds on your wiki %s now"),
      "<a href='?action=seed'>"._t("Click here")."</a>")."</h2>";
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
    print "<tr><td class='option'><b>\$admin_passwd</b></td>";
    print "<td class='option'><input type='password' name='newpasswd' size='60'></td></tr>\n";
  } else  {
    print "<tr><td><b>Old password</b></td>";
    print "<td><input type='password' name='oldpasswd' size='60'></td></tr>\n";
    print "<tr><td><b>New password</b></td>";
    print "<td><input type='password' name='newpasswd' size='60'></td></tr>\n";
  }
  print "</table></div>";
  print "<div class='step'>";
  print "<input type='submit' name='update' value='"._t("Preview")."' /> ";
  if (empty($config['admin_passwd']))
  print "<input type='submit' name='update' value='"._t("Update")."' />\n";
  else
  print "<input type='submit' name='update' value='"._t("Update")."' />\n";
  print "</div></form>\n";

  if (file_exists('config.php') && !file_exists($config['data_dir']."/text/RecentChanges")) {
    print "<h3 class='warn'>".sprintf(_t("WARN: You have no WikiSeed on your %s"),$config['sitename'])."</h3>\n";
    print "<h2>".sprintf(_t("If you want to put wikiseeds on your wiki %s now"),
      "<a href='?action=seed'>"._t("Click here")."</a>")."</h2>";
  } else {
    if (file_exists('wiki.php'))
      print "<h2>".sprintf(_t("goto %s"),"<a href='wiki.php'>".$config['sitename'])."</a></h2>";
    else
      print "<h2>".sprintf(_t("goto %s"),"<a href='".$config['url_prefix']."'>$config[sitename]")."</a></h2>";
  }
}
  print "</div></div></body></html>";

// vim:et:sts=2:sw=2:
?>
