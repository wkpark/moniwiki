<?php
// Copyright 2003 by Won-Kyu Park <wkpark@kldp.org> all rights reserved.
// distributable under GPL see COPYING
//
// Copyright 1999-2002 by Fred C. Yankowski <fcy@acm.org>, all rights reserved.
//
// $Id$
// wkpark@kldp.org 2003


class UserDB {
  function UserDB() {
     $this->user_dir='data/user';
  }

  function getUserList() {
    $users = array();
    $handle = opendir($this->user_dir);
    while ($file = readdir($handle)) {
       if (is_dir($this->user_dir."/".$file)) continue;
       if (preg_match('/^wu-([^\.]+)$/', $file,$match))
          $users[$match[1]] = 1;
    }
    closedir($handle);
    return $users; 
  }

  function addUser($user) {
    if ($this->_exists($user->id))
       return false;
    $this->_saveUser($user);
    return true;
  }

  function _saveUser($user) {
    $config=array("css_url","datatime_fmt","email","language",
                  "name","password","wikiname_add_spaces");

    $data="# Data saved \n";

    foreach ($config as $key) {
       $data.="$key=".$user->info[$key]."\n";
#       print "$key";
    }

    $fp=fopen($this->user_dir."/wu-".$user->id,"w+");
    fwrite($fp,$data);
    fclose($fp);
  }

  function _exists($id) {
    if (file_exists("$this->user_dir/wu-$id"))
       return true;
    return false;
  }

  function getUser($id) {
    $type=array(""=>"");
    if ($this->_exists($id))
       $data=file("$this->user_dir/wu-$id");
    else
       return "";
    $info=array();
    foreach ($data as $line) {
       if ($line[0]=="#" and $line[0]==" ") continue;
       $p=strpos($line,"=");
       if ($p === false) continue;
       $key=substr($line,0,$p);
       $val=substr($line,$p+1,strlen($line)-$p-2);
       $info[$key]=$val;
    }
    $user=new User($id);
    $user->info=$info;
    return $user;
  }


  function delUser($id) {

  }
}

class User {

  function User($id="") {
     global $HTTP_COOKIE_VARS;
     if ($id) {
        $this->setID($id);
        return;
     }
     $this->setID($HTTP_COOKIE_VARS[MOIN_ID]);
  }

  function setID($id) {
     if ($this->checkID($id)) {
        $this->id=$id;
        return true;
     }
     $this->id='Anonymous';
  }

  function getID($name) {
     if (strpos($name," ")) {
        $dum=explode(" ",$name);
        $new=array_map("ucfirst",$dum);
        return join($new,"");
     }
     return $name;
  }

  function setCookie() {
     global $HTTP_COOKIE_VARS;
     if ($this->id == "Anonymous") return false;
     setcookie("MOIN_ID",$this->id,time()+60*60*24*30,get_scriptname());
     # set the fake cookie
     $HTTP_COOKIE_VARS[MOIN_ID]=$this->id;
  }

  function unsetCookie() {
     global $HTTP_COOKIE_VARS;
     header("Set-Cookie: MOIN_ID=".$this->id."; expires=Tuesday, 01-Jan-1999 12:00:00 GMT; Path=".get_scriptname());
     # set the fake cookie
     $HTTP_COOKIE_VARS[MOIN_ID]="Anonymous";
  }

  function setPasswd($passwd,$passwd2) {
     $ret=$this->validPasswd($passwd,$passwd2);
     if ($ret > 0)
        $this->info[password]=crypt($passwd);
     else
        $this->info[password]="";
     return $ret;
  }

  function checkID($id) {
     $SPECIAL='\\,\.;:\-_#\+\*\?!"\'\?%&\/\(\)\[\]\{\}\=';
     preg_match("/[$SPECIAL]/",$id,$match);
     if (!$id || $match)
        return false;
     return true;
  }

  function checkPasswd($passwd) {
     if (strlen($passwd) < 3)
        return false;
     if (crypt($passwd,$this->info[password]) == $this->info[password])
        return true;
     return false;
  }

  function validPasswd($passwd,$passwd2) {

    if (strlen($passwd)<6)
       return 0;
    if ($passwd2!="" and $passwd!=$passwd2)
       return -1;
    $LOWER='abcdefghijklmnopqrstuvwxyz';
    $UPPER='ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $DIGIT='0123456789';
    $SPECIAL=',.;:-_#+*?!"\'?%&/()[]{}\=~^|$@`';

    $VALID=$LOWER.$UPPER.$DIGIT.$SPECIAL;

    $ok=0;

    for ($i=0;$i<strlen($passwd);$i++) {
       if (strpos($VALID,$passwd[$i]) === false)
          return -2;
       if (strpos($LOWER,$passwd[$i]))
          $ok|=1;
       if (strpos($UPPER,$passwd[$i]))
          $ok|=2;
       if (strpos($DIGIT,$passwd[$i]))
          $ok|=4;
       if (strpos($SPECIAL,$passwd[$i]))
          $ok|=8;

    }
    return $ok;
  }
}

function do_DeletePage($options) {
  global $DBInfo;
  
  $page = $DBInfo->getPage($options[page]);
  $html = new Formatter($page);

  if ($options[passwd]) {
    $check=$DBInfo->admin_passwd==crypt($options[passwd],$DBInfo->admin_passwd);
    if ($check) {
      $DBInfo->deletePage($page);
      $title = sprintf('"%s" is deleted !', $page->name);
      $html->send_header("",$title);
      $html->send_title($title);
      return;
    } else {
      $title = sprintf('Fail to delete "%s" !', $page->name);
      $html->send_header("",$title);
      $html->send_title($title);
      return;
    }
  }
  $title = sprintf('Delete "%s" ?', $page->name);
  $html->send_header("",$title);
  $html->send_title($title);
  print "<form method='post'>
Comment: <input name=comment size=80 value='' /><br />
Password: <input type=password name=passwd size=20 value='' />
Only WikiMaster can delete this page<br />
    <input type=hidden name=action value='DeletePage' />
    <input type=submit value='Delete' />
    </form><hr>";
  $html->send_page($title);
  $html->send_footer();
}

function do_fullsearch($needle) {
  global $DBInfo;
  $page = new WikiPage("FullSearch");
  $html = new Formatter($page);
  $title = sprintf('Full text search for "%s"', $needle);
  $html->send_header("",$title);
  $html->send_title($title);

  $hits = array();
  $all_pages = $DBInfo->getPageLists();
  $pattern = '/'.$needle.'/i';

  while (list($_, $page_name) = each($all_pages)) {
    $p = new WikiPage($page_name);
    $body = $p->get_raw_body();
    $count = preg_match_all($pattern, $body, $matches);
    if ($count)
      $hits[$page_name] = $count;
  }
  arsort($hits);

  print "<ul>";
  reset($hits);
  while (list($page_name, $count) = each($hits)) {
    $p = new WikiPage($page_name);
    $h = new Formatter($p);
    print '<li>' . $h->link_to();
    print ' . . . . ' . $count . (($count == 1) ? ' match' : ' matches');
    print "</li>\n";
  }
  print "</ul>\n";

  printf("Found %s matching %s out of %s total pages<br />",
	 count($hits),
	 (count($hits) == 1) ? 'page' : 'pages',
	 count($all_pages));
  $html->send_footer();
}

function do_goto($options) {
  $html = new Formatter();
  $html->send_header(array("Status: 302","Location: ".$options[value]));
}

function do_titlesearch($options) {
  global $DBInfo;
  $page = new WikiPage("FindPage");
  $html = new Formatter($page);
  $title = sprintf('Title Search for "%s"', $options[value]);
  $html->send_header("",$title);
  $html->send_title($title);

  $all_pages = $DBInfo->getPageLists();
  $hits=array();
  foreach ($all_pages as $page) {
     preg_match("/".$options[value]."/i",$page,$matches);
     if ($matches)
        $hits[]=$page;
  }

  sort($hits);

  $out="<ul>\n";
  foreach ($hits as $pagename) {
    $p = new WikiPage($pagename);
    $h = new Formatter($p);
    $out.= '<li>' . $h->link_to()."</li>\n";
  }

  print $out."</ul>\n";
  printf("Found %s matching %s out of %s total pages<br />",
	 count($hits),
	 (count($hits) == 1) ? 'page' : 'pages',
	 count($all_pages));
  $args[noaction]=1;
  $html->send_footer($args);
}

function do_userform($options) {
  $page = new WikiPage("UserPreferences");
  $html = new Formatter($page);

  $id=$options[login_id];
  if ($id || $options[login_passwd]) {
    $userdb=new UserDB();
    if ($userdb->_exists($id)) {
       $user=$userdb->getUser($id);
       if ($user->checkPasswd($options[login_passwd])=== true) {
          $title = sprintf("Successfully login as '$id'");
          $user->setCookie();
       } else {
          $title = sprintf("??Invalid password !");
       }
    } else
       $title= "Please enter a valid user ID!";
  } else if ($options[logout]) {
    $user=new User(); # get cookie
    $user->unsetCookie();
    $title= "Cookie deleted!";
  } else if ($options[username] and $options[password] and $options[passwordagain]) {
    $user=new User(); # get cookie
    if ($user->id == "Anonymous") {
       $id=$user->getID($options[username]);
       $user->setID($id);
    }

    if ($user->id != "Anonymous") {
       $ret=$user->setPasswd($options[password],$options[passwordagain]);
       if ($ret <= 0) {
           if ($ret==0) $title= "too short password!";
           else if ($ret==-1) $title= "mismatch password!";
           else if ($ret==-2) $title= "not acceptable character found in the password!";
       } else {
           if ($ret < 8) $msg="Password is too simple to use as a password!";
           $udb=new UserDB();
           $ret=$udb->addUser($user);
           if ($ret) {
              $title= "Successfully added!";
              $user->setCookie();
           } else {# already exist user
              $user=$udb->getUser($user->id);
              if ($user->checkPasswd($options[password])=== true) {
                  $title = sprintf("Successfully login as '$id'");
                  $user->setCookie();
              } else {
                  $title = sprintf("Invalid password !");
              }
           }
       }
    } else
       $title= "Invalid username!";
  }
  $html->send_header("",$title);
  $html->send_title($title,"",$msg);
  $html->send_page($title);
  $html->send_footer();
}

function macro_UserPreferences($formatter="") {
  global $HTTP_COOKIE_VARS;
  $prefix=get_scriptname();

#  print $HTTP_COOKIE_VARS[MOIN_ID];
#  print $user->id;

  $user=new User(); # get from COOKIE VARS

  if ($user->id == "Anonymous")
     return <<<EOF
<form method="post" action="$prefix/UserPreferences">
<input type="hidden" name="action" value="userform" />
<table border="0">
  <tr><td>&nbsp;</td></tr>
  <tr><td><b>ID</b>&nbsp;</td><td><input type="text" size="40" name="login_id" /></td></tr>
  <tr><td><b>Password</b>&nbsp;</td><td><input type="password" size="20" maxlength="8" name="login_passwd" /></td></tr>

  <tr><td></td><td><input type="submit" name="login" value="Login" /></td></tr>
        
  <tr><td><b>ID</b>&nbsp;</td><td><input type="text" size="40" name="username" value="" /></td></tr>
  <tr>
     <td><b>Password</b>&nbsp;</td><td><input type="password" size="20" maxlength="8" name="password" value="" />
     <b>Password again</b>&nbsp;<input type="password" size="20" maxlength="8" name="passwordagain" value="" /></td></tr>
  <tr><td><b>Mail</b>&nbsp;</td><td><input type="text" size="60" name="email" value="" /></td></tr>
  <tr><td></td><td>
    <input type="submit" name="save" value="make profile" /> &nbsp;
  </td></tr>
</table>
</form>
EOF;

   $udb=new UserDB();
   $user=$udb->getUser($user->id);
   $css=$user->info[css_url];
   return <<<EOF
<form method="post" action="$prefix/UserPreferences">
<input type="hidden" name="action" value="userform" />
<table border="0">
  <tr><td>&nbsp;</td></tr>
  <tr><td><b>ID</b>&nbsp;</td><td>$user->id</td></tr>
  <tr><td><b>Name</b>&nbsp;</td><td><input type="text" size="40" name="username" value="" /></td></tr>
  <tr>
     <td><b>Password</b>&nbsp;</td><td><input type="password" size="20" maxlength="8" name="password" value="" />
     <b>Password again</b>&nbsp;<input type="password" size="20" maxlength="8" name="passwordagain" value="" /></td></tr>
  <tr><td><b>Mail</b>&nbsp;</td><td><input type="text" size="60" name="email" value="" /></td></tr>
  <tr><td><b>CSS URL </b>&nbsp;</td><td><input type="text" size="60" name="css_url" value="$css" /><br />("None" for disable CSS)</td></tr>
  <tr><td></td><td>
    <input type="submit" name="save" value="save profile" /> &nbsp;
    <input type="submit" name="logout" value="logout" /> &nbsp;
  </td></tr>
</table>
</form>
EOF;
}

function macro_InterWiki($formatter="") {
  global $DBInfo;

  $out="<table border=0 cellspacing=2 cellpadding=0>";
  foreach (array_keys($DBInfo->interwiki) as $wiki) {
    $href=$DBInfo->interwiki[$wiki];
    $out.="<tr><td><tt><a href='$href"."RecentChanges'>$wiki</a></tt><td><tt>";
    $out.="<a href='$href'>$href</a></tt></tr>\n";
  }
  $out.="</table>\n";
  return $out;
}

if (!function_exists ("iconv")) {
  function get_key($name) {
     return '?';
  }
} else {
  function get_key($name) {
    if (preg_match('/[a-z0-9]/i',$name[0])) {
       return strtoupper($name[0]);
    }
    # else EUC-KR
    $korean=array('가','나','다','라','마','바','사','아',
                  '자','차','카','타','파','하',"\xca");
    $lastPosition='~';

    $letter=substr($name,0,2);
    foreach ($korean as $position) {
       if ($position > $letter)
           return $lastPosition;
       $lastPosition=$position;
    }
    return '~';
  }
}

function macro_PageCount($formatter="") {
  global $DBInfo;

  return $DBInfo->getCounter();
}


function macro_PageList($formatter="",$arg="") {
  global $DBInfo;


  $all_pages = $DBInfo->getPageLists();
  $hits=array();
  foreach ($all_pages as $page) {
     preg_match("/$arg/",$page,$matches);
     if ($matches)
        $hits[]=$page;
  }

  sort($hits);

  $out="<ul>\n";
  foreach ($hits as $pagename) {
    $p = new WikiPage($pagename);
    $h = new Formatter($p);
    $out.= '<li>' . $h->link_to()."</li>\n";
  }

  return $out."</ul>\n";
}

function macro_TitleIndex($formatter="") {
  global $DBInfo;

  $all_pages = $DBInfo->getPageLists();
  sort($all_pages);

  $key=-1;
  $out="";
  $keys=array();
  foreach ($all_pages as $page) {
    $pkey=get_key($page);
#       $key=strtoupper($page[0]);
    if ($key != $pkey) {
       if ($key !=-1)
          $out.="</UL>";
       $key=$pkey;
       $keys[]=$key;
       $out.= "<a name='$key' /><h3><a href='#top'>$key</a></h3>\n";
       $out.= "<UL>";
    }
    
    $p = new WikiPage($page);
    $h = new Formatter($p);
    $out.= '<LI>' . $h->link_to();
  }
  $out.= "</UL>";

  $index="";
  foreach ($keys as $key)
    $index.= "|<a href='#$key'>$key</a>";
  $index[0]="";
  
  return "<center><a name='top' />$index</center>\n$out";
}

function macro_Icon($formatter="",$value="") {
  global $DBInfo;

  $out=$DBInfo->imgs_dir."/$value";
  $out="<img src='$out' border='0' align='absmiddle' />";
  return $out;
}

function macro_RecentChanges($formatter="") {
  global $DBInfo;

  $lines= $DBInfo->editlog_raw_lines();
  $lines= reverse($lines);
    
  $time_current= time();
  $secs_per_day= 60*60*24;
  $days_to_show= 30;
  $time_cutoff= $time_current - ($days_to_show * $secs_per_day);

  $out="";
  $ratchet_day= FALSE;
  $done_words= array();
#  while (list($_, $line) = each($lines)) {
  foreach ($lines as $line) {
    if (!$line) continue;
    $parts= explode("\t", $line);
    $page_name= $DBInfo->keyToPagename($parts[0]);
    $addr= $parts[1];
    $ed_time= $parts[2];
    $user= $parts[4];

    if ($ed_time < $time_cutoff)
      break;

    if (! empty($done_words[$page_name]))
      continue;			// reported this page already
    $done_words[$page_name] = TRUE;

    $day = date('Y/m/d', $ed_time);
    if ($day != $ratchet_day) {
      $out.=sprintf("<br /><font size='+1'>%s :</font><br />\n", date($DBInfo->date_fmt, $ed_time));
      $ratchet_day = $day;
    }

    $p = new WikiPage($page_name);
    $h = new Formatter($p);
    $out.= "&nbsp;&nbsp; ".$h->link_to("?action=diff",$DBInfo->icon[diff]);
    $out.= "&nbsp;&nbsp;".$h->link_to();

    if (! empty($DBInfo->changed_time_fmt))
      $out.= date($DBInfo->changed_time_fmt, $ed_time);

    if ($DBInfo->show_hosts) {
      #$out.= ' . . . . '; # traditional style
      $out.= '&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; ';
       if ($user)
          $out.= $user;
       else
          $out.= $addr;
    }
    $out.= '<br />';
  }
  return $out;
}

function reverse($arrayX) {
  $out = array();
  $size = count($arrayX);
  for ($i = $size - 1; $i >= 0; $i--)
    $out[] = $arrayX[$i];
  return $out;
}

function macro_HTML($formatter="",$value="") {
  return str_replace("&lt;","<",$value);
}

function macro_BR($formatter="") {
  return "<br />\n";
}

function macro_TableOfContents($formatter="") {
 $head_num=1;
 $head_dep=0;
 $TOC="\n<a name='toc' id='toc' /><dl><dd><dl>";

 $formatter->toc=1;
 $lines=explode("\n",$formatter->page->get_raw_body());
 foreach ($lines as $line) {
   $line=preg_replace("/\n$/", "", $line); # strip \n
   preg_match("/(?<!=)(={1,5})\s+(.*)\s+(={1,5})$/",$line,$match);

   if (!$match) continue;

   $dep=strlen($match[1]);
   if ($dep != strlen($match[3])) continue;
   $head=$match[2];

   $depth=$dep;
   if ($dep==1) $depth++; # depth 1 is regarded same as depth 2
   $depth--;

   $num="".$head_num;
   $odepth=$head_dep;
   $open="";
   $close="";

   if ($odepth && ($depth > $odepth)) {
      $open.="<dd><dl>\n";
      $num.=".1";
   } else if ($odepth) {
      $dum=explode(".",$num);
      $i=sizeof($dum)-1;
      while ($depth < $odepth) {
         unset($dum[$i]);
         $i--;
         $odepth--;
         $close.="</dl></dd>\n";
      }
      $dum[$i]++;
      $num=join($dum,".");
   }
   $head_dep=$depth; # save old
   $head_num=$num;

   $TOC.=$close.$open."<dt><a id='toc$num' name='toc$num' /><a href='#s$num'>$num</a> $head</dt>\n";

#   print $TOC;
  }

  if ($TOC) {
     $close="";
     $depth=$head_dep;
     # XXX ???
     while ($depth>0) { $depth--;$close.="</dl></dd></dl>\n"; };
     return $TOC.$close;
  }
  else return "";
}

function macro_FullSearch($formatter="",$value="") {
  return "<form method='get'>
    <input type=hidden name=action value='fullsearch'>
    <input name=value size=30 value='$value'>
    <input type=submit value='Go'><br />
    <input type='checkbox' name='context' value='20' checked>Display context of search results<br />
    <input type='checkbox' name='case' value='1'>Case-sensitive searching<br />

    </form>";
}

function macro_TitleSearch($formatter="",$value="") {
  return "<form method='get'>
    <input type=hidden name=action value='titlesearch'>
    <input name=value size=30 value='$value'>
    <input type=submit value='Go'>
    </form>";
}

function macro_GoTo($formatter="",$value="") {
  return "<form method='get'>
    <input type=hidden name=action value='goto'>
    <input name=value size=30 value='$value'>
    <input type=submit value='Go'>
    </form>";
}

function macro_SystemInfo($formatter="",$value="") {

   $version=phpversion();
   $uname=php_uname();
  return <<<EOF
<table border=0 cellpadding=5>
<tr><th>PHP Version</th> <td>$version ($uname)</td></tr>
</table>
EOF;
}

function processor_html($formatter="",$value="") {
   $html=substr($formatter->pre_line,6,strlen($formatter->pre_line));
   return $html;
}

function processor_latex($formatter="",$value="") {
  global $DBInfo;
  # site spesific variables
  $latex="/usr/bin/latex ";
  $dvips="dvips ";
  $convert="convert ";
  $vartmp_dir="/var/tmp";
  $cache_dir="pds";
  $option='-interaction=batchmode ';

  $lines=explode("\n",$formatter->pre_line);
  # get parameters
  unset($lines[0]);

  $tex=join($lines,"\n");

  $uniq=md5($tex);

  $src="\documentclass[10pt,notitlepage]{article}
\usepackage{amsmath}
\usepackage{amsfonts}
%%\usepackage[all]{xy}
\\begin{document}
\pagestyle{empty}
$tex
\end{document}
";

  if ($formatter->refresh || !file_exists("$cache_dir/$uniq.png")) {
     $fp= fopen("$vartmp_dir/$uniq.tex", "w");
     fwrite($fp, $src);
     fclose($fp);

     $outpath="$cache_dir/$uniq.png";

     $cmd= "cd $vartmp_dir; $latex $option $uniq.tex >/dev/null";
     system($cmd);

     $cmd= "cd $vartmp_dir; $dvips -D 600 $uniq.dvi -o $uniq.ps";
     system($cmd);

     $cmd= "$convert -crop 0x0 -density 120x120 $vartmp_dir/$uniq.ps $outpath";
     system($cmd);

     system("rm $vartmp_dir/$uniq.*");
  }
  return "<img src='$DBInfo->url_prefix/$cache_dir/$uniq.png' alt='tex'".
         "title=\"$tex\" />";
}

function processor_php($formatter="",$value="") {
  $php=substr($formatter->pre_line,5,strlen($formatter->pre_line));
  ob_start();
  highlight_string($php);
  $highlighted= ob_get_contents();
  ob_end_clean();
#  $highlighted=preg_replace("/<code>/","<code style='background-color:#c0c0c0;'>",$highlighted);
#  $highlighted=preg_replace("/<\/?code>/","",$highlighted);
#  $highlighted="<pre style='color:white;background-color:black;'>".
#               $highlighted."\n</pre>";
  return $highlighted;
}

function processor_gnuplot($formatter="",$value="") {
  #$gnuplot="/usr/local/bin/gnuplot_pm3d ";
  #$gnuplot="gnuplot ";
  $gnuplot="/usr/local/bin/gnuplot_pm3d ";
  $vartmp_dir="/var/tmp";
  $cache_dir="pds";

  #
  $plt=$formatter->pre_line;
  #$lines=explode("\n",$formatter->pre_line);
  # get parameters
  #unset($lines[0]);
  #$plt=join($lines,"\n");

# a sample for debugging
#  $plt='
#set term gif
#!  ls
#plot sin(x)
#';

  # normalize plt
  $plt="\n".$plt."\n";
  $plt=preg_replace("/\n\s*![^\n]+\n/","\n",$plt); # strip shell commends
  $plt=preg_replace("/[ ]+/"," ",$plt);
  $plt=preg_replace("/\nset?\s+(t|o|si).*\n/", "\n",$plt);
  
  #print "<pre>$plt</pre>";
  
  $uniq=md5($plt);

  $outpath="$cache_dir/$uniq.png";

  $src="
  set size 0.5,0.6
set term png
set out '$outpath'
$plt
";

  #if (1 || $formatter->refresh || !file_exists("$cache_dir/$uniq.png")) {
  if ($formatter->refresh || !file_exists("$cache_dir/$uniq.png")) {

     $flog=tempnam($vartmp_dir,"GNUPLOT");

     $cmd= "$gnuplot 2>$flog";
     $fp=popen($cmd,"w");
     fwrite($fp,$src);
  
#   while($s = fgets($fp, 1024)) {
#     $log.= $s;
#   }
     pclose($fp);
     $log=join(file($flog),"");
     unlink($flog);
  
     if ($log)
        $log ="<pre style='background-color:black;color:gold'>$log</pre>\n";
  }
  return $log."<img src='/wiki/$cache_dir/$uniq.png' alt='gnuplot'/>";
}

?>
