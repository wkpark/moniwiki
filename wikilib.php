<?php
// Copyright 2003 by Won-Kyu Park <wkpark@kldp.org> all rights reserved.
// distributable under GPL see COPYING
//
// many codes are imported from the MoinMoin
// some codes are reused from the Phiki
//
// * MoinMoin is a python based wiki clone based on the PikiPiki
//    by Jurgen Herman
// * PikiPiki is a python based wiki clone by MartinPool
// * Phiki is a php based wiki clone based on the MoinMoin
//    by Fred C. Yankowski <fcy@acm.org>
//
// $Id$

function find_needle($body,$needle,$count=-1) {
  if (!$body) return '';
  $lines=explode("\n",$body);
  $out="";
  $matches=preg_grep("/($needle)/i",$lines);
  foreach ($matches as $line) {
     $line=preg_replace("/($needle)/i","<strong>\\1</strong>",$line);
     $out.="<br />\n&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".$line;
     $count--;
     if ($count==0) break;
  }
  return $out;
}


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
    $this->saveUser($user);
    return true;
  }

  function saveUser($user) {
    $config=array("css_url","datatime_fmt","email","language",
                  "name","password","wikiname_add_spaces");

    $data="# Data saved \n";

    foreach ($config as $key) {
       $data.="$key=".$user->info[$key]."\n";
    }

    #print $data;

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
    if ($this->_exists($id))
       $data=file("$this->user_dir/wu-$id");
    else
       return "";
    $info=array();
    foreach ($data as $line) {
       #print "$line<br/>";
       if ($line[0]=="#" and $line[0]==" ") continue;
       $p=strpos($line,"=");
       if ($p === false) continue;
       $key=substr($line,0,$p);
       $val=substr($line,$p+1,-1);
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

function do_highlight($formatter,$options) {

  $formatter->send_header("",$title);
  $formatter->send_title($title);

  $formatter->highlight=$options[value];
  $formatter->send_page($title);
  $args[editable]=1;
  $formatter->send_footer($args,$options[timer]);
}

function do_DeletePage($formatter,$options) {
  global $DBInfo;
  
  $page = $DBInfo->getPage($options[page]);

  if ($options[passwd]) {
    $check=$DBInfo->admin_passwd==crypt($options[passwd],$DBInfo->admin_passwd);
    if ($check) {
      $DBInfo->deletePage($page);
      $title = sprintf('"%s" is deleted !', $page->name);
      $formatter->send_header("",$title);
      $formatter->send_title($title);
      $formatter->send_footer();
      return;
    } else {
      $title = sprintf('Fail to delete "%s" !', $page->name);
      $formatter->send_header("",$title);
      $formatter->send_title($title);
      $formatter->send_footer();
      return;
    }
  }
  $title = sprintf('Delete "%s" ?', $page->name);
  $formatter->send_header("",$title);
  $formatter->send_title($title);
  print "<form method='post'>
Comment: <input name=comment size=80 value='' /><br />
Password: <input type=password name=passwd size=20 value='' />
Only WikiMaster can delete this page<br />
    <input type=hidden name=action value='DeletePage' />
    <input type=submit value='Delete' />
    </form><hr class='wiki' />";
  $formatter->send_page($title);
  $formatter->send_footer();
}

function do_fullsearch($formatter,$options) {

  $ret=$options;

  $title= sprintf('Full text search for "%s"', $options[value]);
  $formatter->send_header("",$title);
  $formatter->send_title($title);

  $out= macro_FullSearch($formatter,$options[value],&$ret);
  print $out;

  if ($options[value])
    printf("Found %s matching %s out of %s total pages<br />",
	 $ret[hits],
	($ret[hits] == 1) ? 'page' : 'pages',
	 $ret[all]);
  $args[noaction]=1;
  $formatter->send_footer($args,$options[timer]);
}

function do_goto($formatter,$options) {

  if ($options[value])
     $formatter->send_header(array("Status: 302","Location: ".$options[value]));
  else {
     $title = 'Use more specific text';
     $formatter->send_header("",$title);
     $formatter->send_title($title);
     $args[noaction]=1;
     $formatter->send_footer($args);
  }
}

function do_LikePages($formatter,$options) {

  $opts[metawiki]=$options[metawiki];
  $out= macro_LikePages($formatter,$options[page],&$opts);
  
  $title = $opts[msg];
  $formatter->send_header("",$title);
  $formatter->send_title($title);
  print $opts[extra];
  print $out;
  print $opts[extra];
  $formatter->send_footer("",$options[timer]);
}

function do_rss_rc($formatter,$options) {
  global $DBInfo;

  $lines= $DBInfo->editlog_raw_lines(2000,1);
    
  $time_current= time();
  $secs_per_day= 60*60*24;
  $days_to_show= 30;
  $time_cutoff= $time_current - ($days_to_show * $secs_per_day);

  $URL=qualifiedURL($formatter->prefix);
  $img_url=qualifiedURL($DBInfo->logo_img);

  $head=<<<HEAD
<?xml version="1.0" encoding="euc-kr"?>
<!--<?xml-stylesheet type="text/xsl" href="/wiki/css/rss.xsl"?>-->
<rdf:RDF xmlns:wiki="http://purl.org/rss/1.0/modules/wiki/"
         xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:xlink="http://www.w3.org/1999/xlink"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         xmlns="http://purl.org/rss/1.0/">\n
HEAD;
  $channel=<<<CHANNEL
<channel rdf:about="$URL">
  <title>$DBInfo->sitename</title>
  <link>$URL/RecentChanges</link>
  <description>
    RecentChanges at $DBInfo->sitename
  </description>
  <image rdf:resource="$img_url"/>
  <items>
  <rdf:Seq>
CHANNEL;
  $items="";

#          print('<description>'."[$data] :".$chg["action"]." ".$chg["pageName"].$comment.'</description>'."\n");
#          print('</rdf:li>'."\n");
#        }

  $ratchet_day= FALSE;
  foreach ($lines as $line) {
    $parts= explode("\t", $line);
    $page_name= $DBInfo->keyToPagename($parts[0]);
    $addr= $parts[1];
    $ed_time= $parts[2];
    $user= $parts[4];
    $act= rtrim($parts[6]);

    if ($ed_time < $time_cutoff)
      break;

    $day = date('Y/m/d', $ed_time);
#    if ($day != $ratchet_day) {
#      $out.=sprintf("<br /><font size='+1'>%s :</font><br />\n", date($DBInfo->date_fmt, $ed_time));
#      $ratchet_day = $day;
#      unset($edit);
#    }
    $channel.='    <rdf:li rdf:resource="'.$URL.'/'.$page_name.'"/>'."\n";

    $items.='     <item rdf:about="'.$URL.'/'.$page_name.'">'."\n";
    $items.='     <title>'.$page_name.'</title>'."\n";
    $items.='     <link>'.$URL.'/'.$page_name.'</link>'."\n";
    $items.='     </item>'."\n";

#    $out.= "&nbsp;&nbsp;".$formatter->link_tag("$page_name");
#    if (! empty($DBInfo->changed_time_fmt))
#       $out.= date($DBInfo->changed_time_fmt, $ed_time);
#
#    if ($DBInfo->show_hosts) {
#      $out.= ' . . . . '; # traditional style
#      #$logs[$page_name].= '&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; ';
#      if ($user)
#        $out.= $user;
#      else
#        $out.= $addr;
#    }
  }
  $channel.= <<<FOOT
    </rdf:Seq>
  </items>
</channel>
<image rdf:about="$img_url">
<title>$DBInfo->sitename</title>
<link>$URL/FrontPage</link>
<url>$img_url</url>
</image>
FOOT;

  $form=<<<FORM
<textinput>
<title>Search</title>
<link>$URL/FindPage</link>
<name>goto</name>
</textinput>
FORM;
  header("Content-Type: text/xml");
  print $head;
  print $channel;
  print $items;
  print $form;
  print "</rdf:RDF>";
}

function do_titleindex($formatter,$options) {
  global $DBInfo;

  $pages = $DBInfo->getPageLists();

  sort($pages);
  header("Content-Type: text/plain");
  print join("\n",$pages);
}

function do_titlesearch($formatter,$options) {

  $out= macro_TitleSearch($formatter,$options[value],&$ret);

  $formatter->send_header("",$ret[msg]);
  $formatter->send_title($ret[msg]);
  print $out;

  if ($options[value])
    printf("Found %s matching %s out of %s total pages<br />",
	 $ret[hits],
	($ret[hits] == 1) ? 'page' : 'pages',
	 $ret[all]);
  $args[noaction]=1;
  $formatter->send_footer($args,$options[timer]);
}

function do_uploadfile($formatter,$options) {
  global $DBInfo;
  global $HTTP_POST_FILES;

  # replace space and ':'
  $upfilename=str_replace(" ","_",$HTTP_POST_FILES['upfile']['name']);
  $upfilename=str_replace(":","_",$upfilename);

  preg_match("/(.*)\.([a-z0-9]{1,4})$/i",$upfilename,$fname);

  # upload file protection
  if ($DBInfo->pds_allowed)
     $pds_exts=$DBInfo->pds_allowed;
  else
     $pds_exts="png|jpg|jpeg|gif|mp3|zip|tgz|gz|txt|hwp";
  if (!preg_match("/(".$pds_exts.")$/i",$fname[2])) {
     $title="$fname[2] extension does not allowed to upload";
     $formatter->send_header("",$title);
     $formatter->send_title($title);
     exit;
  }

  $file_path= $DBInfo->upload_dir."/".$upfilename;

  # is file already exists ?
  $dummy=0;
  while (!$options[replace] && file_exists($file_path)) {
     $dummy=$dummy+1;
     $ufname=$fname[1]."_".$dummy; // rename file
     $upfilename=$ufname.".$fname[2]";
     $file_path= $DBInfo->upload_dir."/".$upfilename;
  }
 
  $temp=explode("/",$HTTP_POST_FILES['upfile']['tmp_name']);
  $upfile="/tmp/".$temp[count($temp)-1];
  // Tip at http://phpschool.com

  $test=@copy($upfile, $file_path);
  if (!$test) {
     $title="Fail to copy \"$upfilename\" to \"$file_path\"";
     $formatter->send_header("",$title);
     $formatter->send_title($title);
     exit;
  }
  chmod($file_path,0644); 
  
  $title='File "'.$upfilename.'" is uploaded successfully';
  $formatter->send_header("",$title);
  $formatter->send_title($title);
  print "<ins>Uploads:$upfilename</ins>";
  $formatter->send_footer();
}

function do_userform($formatter,$options) {

  $user=new User(); # get cookie
  $id=$options[login_id];

  if ($user->id == "Anonymous" and $id and $options[login_passwd]) {
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
    $user->unsetCookie();
    $title= "Cookie deleted!";
  } else if ($user->id=="Anonymous" and $options[username] and $options[password] and $options[passwordagain]) {

    $id=$user->getID($options[username]);
    $user->setID($id);

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
  } else if ($user->id != "Anonymous") {
    $udb=new UserDB();
    $userinfo=$udb->getUser($user->id);
    if ($options[css_url])
       $userinfo->info[css_url]=$options[css_url];
    if ($options[username])
       $userinfo->info[name]=$options[username];
    $udb->saveUser($userinfo);
  }
  
  $formatter->send_header("",$title);
  $formatter->send_title($title,"",$msg);
  $formatter->send_page($title);
  $formatter->send_footer();
}

function macro_UploadFile($formatter,$value="") {
   global $DBInfo;
   $form= <<<EOF
<form enctype="multipart/form-data" method='post' action=''>
   <input type='hidden' name='action' value='UploadFile' />
   <input type='file' name='upfile' size='30' />
   <input type='submit' value='Upload' /><br />
   <input type='radio' name='replace' value='1' />Replace original file<br />
   <input type='radio' name='replace' value='0' checked='checked' />Rename if already exist file<br />
   </form>
EOF;

   return $form;
}

function macro_UploadedFiles($formatter,$value="") {
   global $DBInfo;

   $pages= array();
   $handle= opendir($DBInfo->upload_dir);

   while ($file= readdir($handle)) {
      if (is_dir($DBInfo->upload_dir."/".$file)) continue;
      $upfiles[]= $file;
   }
   closedir($handle);

   $out="<form method='post' ><table border='0' cellpadding='2'>";
   $idx=1;
   $prefix=$DBInfo->url_prefix."/".$DBInfo->upload_dir;
   foreach ($upfiles as $file) {
      $link=$prefix."/".rawurlencode($file);
      $out.="<tr><td class='wiki'><input type='checkbox' value='$file'></td><td class='wiki'><a href='$link'>$file</a></td><td class='wiki'>xx</td><td class='wiki'>xx</td></tr>\n";
      $idx++;
   }
   $out.="<tr><th colspan='2'>Total files</th><td></td><td></td></tr>\n";
   $out.="</table><input type='submit' value='Delete selected files'></form>\n";
   return $out;
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
  <tr><td><b>Password</b>&nbsp;</td><td><input type="password" size="20" maxlength="12" name="login_passwd" /></td></tr>

  <tr><td></td><td><input type="submit" name="login" value="Login" /></td></tr>
        
  <tr><td><b>ID</b>&nbsp;</td><td><input type="text" size="40" name="username" value="" /></td></tr>
  <tr>
     <td><b>Password</b>&nbsp;</td><td><input type="password" size="20" maxlength="12" name="password" value="" />
     <b>Password again</b>&nbsp;<input type="password" size="20" maxlength="12" name="passwordagain" value="" /></td></tr>
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
   $name=$user->info[name];
   return <<<EOF
<form method="post" action="$prefix/UserPreferences">
<input type="hidden" name="action" value="userform" />
<table border="0">
  <tr><td>&nbsp;</td></tr>
  <tr><td><b>ID</b>&nbsp;</td><td>$user->id</td></tr>
  <tr><td><b>Name</b>&nbsp;</td><td><input type="text" size="40" name="username" value="$name" /></td></tr>
  <tr>
     <td><b>Password</b>&nbsp;</td><td><input type="password" size="20" maxlength="8" name="password" value="" />
     <b>New password</b>&nbsp;<input type="password" size="20" maxlength="8" name="passwordagain" value="" /></td></tr>
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

function macro_LikePages($formatter="",$args="",$opts=array()) {
  global $DBInfo;

  $pname=preg_escape($args);

  $metawiki=$opts[metawiki];

  if (strlen($pname) < 3) {
     $opts[msg] = 'Use more specific text';
     return '';
  }

  $s_re="^[A-Z][a-z0-9]+";
  $e_re="[A-Z][a-z0-9]+$";

  if ($metawiki)
     $pages = $DBInfo->metadb->getAllPages();
  else
     $pages = $DBInfo->getPageLists();

  $count=preg_match("/(".$s_re.")/",$pname,$match);
  if ($count) {
    $start=$match[1];
    $s_len=strlen($start);
  }
  $count=preg_match("/(".$e_re.")/",$pname,$match);
  if ($count) {
    $end=$match[1];
    $e_len=strlen($end);
  }

  if (!$start && !$end) {
    preg_match("/^(.{2,4})/",$pname,$match);
    $start=$match[1];
    $s_len=strlen($start);
  }

  if (!$end) {
    $end=substr($pname,$s_len);
    preg_match("/(.{2,6})$/",$end,$match);
    $end=$match[1];
    $e_len=strlen($end);
    if ($e_len < 2) $end="";
  }

  $starts=array();
  $ends=array();
  $likes=array();
  
  if ($start) {
    foreach ($pages as $page) {
      preg_match("/^$start/",$page,$matches);
      if ($matches)
        $starts[$page]=1;
    }
  }

  if ($end) {
    foreach ($pages as $page) {
      preg_match("/$end$/",$page,$matches);
      if ($matches)
        $ends[$page]=1;
    }
  }

  if ($start || $end) {
    if (!$end) $similar_re=$start;
    else $similar_re="$start|$end";
    foreach ($pages as $page) {
      preg_match("/($similar_re)/i",$page,$matches);
      if ($matches && !$starts[$page] && !$ends[$page])
        $likes[$page]=1;
    }
  }

  $idx=1;
  $hits=0;
  $out="";
  if ($likes) {
    arsort($likes);

    $out.="<h3>These pages share a similar word...</h3>";
    $out.="<ol>\n";
#    foreach ($likes as $pagename) {
    while (list($pagename,$i) = each($likes)) {
      $p = new WikiPage($pagename);
      $h = new Formatter($p);
      $out.= '<li>' . $h->link_to("","","tabindex='$idx'")."</li>\n";
      $idx++;
    }
    $out.="</ol>\n";
    $hits=count($likes);
  }
  if ($starts || $ends) {
    arsort($starts);

    $out.="<h3>These pages share an initial or final title word...</h3>";
    $out.="<table border='0' width='100%'><tr><td width='50%' valign='top'>\n<ol>\n";
#    foreach ($starts as $pagename) {
    while (list($pagename,$i) = each($starts)) {
      $p = new WikiPage($pagename);
      $h = new Formatter($p);
      $out.= '<li>' . $h->link_to("","","tabindex='$idx'")."</li>\n";
      $idx++;
    }
    $out.="</ol></td>\n";

    arsort($ends);

    $out.="<td width='50%' valign='top'><ol>\n";
#    foreach ($ends as $pagename) {
    while (list($pagename,$i) = each($ends)) {
      $p = new WikiPage($pagename);
      $h = new Formatter($p);
      $out.= '<li>' . $h->link_to("","","tabindex='$idx'")."</li>\n";
      $idx++;
    }
    $out.="</ol>\n</td></tr></table>\n";
    $opts[extra]="If you can't find this page, ";
    $hits+=count($starts) + count($ends);
  }

  if (!$hits) {
    $out.="<h3>No similar pages found</h3>";
    $opts[extra]="You are strongly recommened to find it in MetaWikis. ";
  }

  $opts[msg] = "Like \"$pname\"";

  $prefix=get_scriptname();
  $opts[extra].="<a href='$prefix/".$pname."?action=LikePages&amp;metawiki=1'>Search all MetaWikis</a> (Slow Slow)<br />";

  return $out;
}


function macro_PageCount($formatter="") {
  global $DBInfo;

  return $DBInfo->getCounter();
}

function macro_PageLinks($formatter="",$options="") {
  global $DBInfo;
  $pages = $DBInfo->getPageLists();

  $out="<ul>\n";
  $cache=new Cache_text("pagelinks");
  foreach ($pages as $page) {
    $p= new WikiPage($page);
    $f= new Formatter($p);
    $out.="<li>".$f->link_to().": ";
    $links=$f->get_pagelinks();
    $out.=$links."</li>\n";
  }
  $out.="</ul>\n";
  return $out;
}


function macro_PageList($formatter="",$arg="") {
  global $DBInfo;

  $test=@preg_match("/$arg/","",$match);
  if ($test === false) {
     return "[[PageList(<font color='red'>Invalid \"$arg\"</font>)]]";
  }

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

function macro_Icon($formatter="",$value="",$extra="") {
  global $DBInfo;

  $out=$DBInfo->imgs_dir."/$value";
  $out="<img src='$out' border='0' alt='icon' align='middle' />";
  return $out;
}

function macro_QuickChanges($formatter="") {
  global $DBInfo;

  $lines= $DBInfo->editlog_raw_lines(4000,1);
    
  $time_current= time();
  $secs_per_day= 60*60*24;
  $days_to_show= 30;
  $time_cutoff= $time_current - ($days_to_show * $secs_per_day);

  $out="";
  $ratchet_day= FALSE;
  foreach ($lines as $line) {
    $parts= explode("\t", $line);
    $page_name= $DBInfo->keyToPagename($parts[0]);
    $addr= $parts[1];
    $ed_time= $parts[2];
    $user= $parts[4];
    $act= rtrim($parts[6]);

    if ($ed_time < $time_cutoff)
      break;

    $day = date('Y/m/d', $ed_time);
    if ($day != $ratchet_day) {
      $out.=sprintf("<br /><font size='+1'>%s :</font><br />\n", date($DBInfo->date_fmt, $ed_time));
      $ratchet_day = $day;
      unset($edit);
    }

    if ($act == "DEL")
       $out.= "&nbsp;&nbsp; ".$formatter->link_tag("$page_name?action=diff",$DBInfo->icon[del]);
    else
       $out.= "&nbsp;&nbsp; ".$formatter->link_tag("$page_name?action=diff",$DBInfo->icon[diff]);

    $out.= "&nbsp;&nbsp;".$formatter->link_tag("$page_name");
    if (! empty($DBInfo->changed_time_fmt))
       $out.= date($DBInfo->changed_time_fmt, $ed_time);

    if ($DBInfo->show_hosts) {
      $out.= ' . . . . '; # traditional style
      #$logs[$page_name].= '&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; ';
      if ($user)
        $out.= $user;
      else
        $out.= $addr;
    }
    $out.= '<br />';
  }
  return $out;
}

function macro_RecentChanges($formatter="") {
  global $DBInfo;

  $lines= $DBInfo->editlog_raw_lines();

  $time_current= time();
  $secs_per_day= 60*60*24;
  $days_to_show= 30;
  $time_cutoff= $time_current - ($days_to_show * $secs_per_day);

  $out="";
  $ratchet_day= FALSE;
#  while (list($_, $line) = each($lines)) {
  foreach ($lines as $line) {
    if (!$line) continue;
    $parts= explode("\t", $line);
    $page_name= $DBInfo->keyToPagename($parts[0]);
    $addr= $parts[1];
    $ed_time= $parts[2];
    $user= $parts[4];
    $act= rtrim($parts[6]);

    if ($ed_time < $time_cutoff)
      break;

    if (!empty($logs[$page_name])) {
      $edit[$page_name]++; continue;
    }
    $edit[$page_name] = 1;

    $day = date('Y/m/d', $ed_time);
    if ($day != $ratchet_day) {
      if ($logs) {
         while (list($name, $log) = each($logs)) {
            if ($edit[$name]>1)
               $count=" [".$edit[$name]." changes]";
            else
               $count="";
            $out.=str_replace("[@]",$count,$log);
         }
      }
      $out.=sprintf("<br /><font size='+1'>%s :</font><br />\n", date($DBInfo->date_fmt, $ed_time));
      $ratchet_day = $day;
      unset($logs);
      unset($edit);
    }

    if ($act == "DEL")
       $logs[$page_name]= "&nbsp;&nbsp; ".$formatter->link_tag("$page_name?action=diff",$DBInfo->icon[del]);
    else
       $logs[$page_name]= "&nbsp;&nbsp; ".$formatter->link_tag("$page_name?action=diff",$DBInfo->icon[diff]);

    $logs[$page_name].= "&nbsp;&nbsp;".$formatter->link_tag("$page_name");
    if (! empty($DBInfo->changed_time_fmt))
      $logs[$page_name].= date($DBInfo->changed_time_fmt, $ed_time);

#    $count=$DBInfo->pageCounter($page_name);
#    if ($count)
#       $logs[$page_name].= " ($count)";

    if ($DBInfo->show_hosts) {
      $logs[$page_name].= ' . . . . '; # traditional style
      #$logs[$page_name].= '&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; ';
      if ($user)
        $logs[$page_name].= $user;
      else
        $logs[$page_name].= $addr;
    }
    $logs[$page_name].= ' [@]<br />';
  }
  return $out;
}

function macro_HTML($formatter,$value) {
  return str_replace("&lt;","<",$value);
}

function macro_BR($formatter) {
  return "<br />\n";
}

function macro_FootNote($formatter,$value="") {
  if (!$value) {# emit all footnotes
    $foots=join("\n",$formatter->foots);
    $foots=preg_replace("/(".$formatter->wordrule.")/e","\$formatter->link_repl('\\1')",$foots);
    unset($formatter->foots);
    return "<br/><tt class='wiki'>----</tt><br/>\n$foots";
  }

  $formatter->foot_idx++;
  $idx=$formatter->foot_idx;

  $text="[$idx]";
  $idx="fn".$idx;
  if ($value[0] == "*") {
#    $dum=explode(" ",$value,2); XXX
    $p=strrpos($value,'*')+1;
    $text=substr($value,0,$p);
    $value=substr($value,$p);
  } else if ($value[0] == "[") {
    $dum=explode("]",$value,2);
    if (trim($dum[1])) {
       $text=$dum[0]."&#093;"; # make a text as [Alex77]
       $idx=substr($dum[0],1);
       $formatter->foot_idx--; # undo ++.
       if (0 === strcmp($idx , (int)$idx)) $idx="fn$idx";
       $value=$dum[1]; 
    } else if ($dum[0]) {
       $text=$dum[0]."]";
       $idx=substr($dum[0],1);
       $formatter->foot_idx--; # undo ++.
       if (0 === strcmp($idx , (int)$idx)) $idx="fn$idx";
       return "<tt class='foot'><sup><a href='#$idx'>$text</a></sup></tt>";
    }
  }
  $formatter->foots[]="<tt class='foot'><sup>&#160;&#160&#160;".
                      "<a name='$idx'/>".
                      "<a href='#r$idx'>$text</a>&#160;</sup></tt> ".
                      "$value<br/>";
  return "<tt class='foot'><a name='r$idx'/><sup><a href='#$idx'>$text</a></sup></tt>";
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

function macro_FullSearch($formatter="",$value="", $opts=array()) {
  global $DBInfo;
  $needle=$value;

   $form= <<<EOF
<form method='get' action=''>
   <input type='hidden' name='action' value='fullsearch' />
   <input name='value' size='30' value='$needle' />
   <input type='submit' value='Go' /><br />
   <input type='checkbox' name='context' value='20' checked='checked' />Display context of search results<br />
   <input type='checkbox' name='pagelinks' value='1' checked='checked' />Search PageLinks only<br />
   <input type='checkbox' name='case' value='1' />Case-sensitive searching<br />
   </form>
EOF;

  if (!$needle) { # or blah blah
     $opts[msg] = 'No search text';
     return $form;
  }
  $test=@preg_match("/$needle/","",$match);
  if ($test === false) {
     $opts[msg] = sprintf('Invalid search expression "%s"', $needle);
     return $form;
  }

  $hits = array();
  $pages = $DBInfo->getPageLists();
  $pattern = '/'.$needle.'/';
  if ($opts['case']) $pattern.="i";

  if ($opts['pagelinks']) {
     $cache=new Cache_text("pagelinks");
     foreach ($pages as $page_name) {
       $links==-1;
       $links=$cache->fetch($page_name);
       if ($links==-1) {
          $p= new WikiPage($page_name);
          $f= new Formatter($p);
          $links=$f->get_pagelinks();
       }
       $count= preg_match_all($pattern, $links, $matches);
       if ($count) {
         $hits[$page_name] = $count;
       }
     }
  } else {
     while (list($_, $page_name) = each($pages)) {
       $p = new WikiPage($page_name);
       if (!$p->exists()) continue;
       $body= $p->_get_raw_body();
       $count = count($matches=preg_split($pattern, $body))-1;
       #$count = preg_match_all($pattern, $body, $matches);
       #$count = count(explode($needle, $body)) - 1;
       #$count = preg_match($pattern, $body);
       if ($count) {
         $hits[$page_name] = $count;
       }
     }
  }
  arsort($hits);

  $out.= "<ul>";
  reset($hits);
  $idx=1;
  while (list($page_name, $count) = each($hits)) {
    $p = new WikiPage($page_name);
    $h = new Formatter($p);
    $out.= '<li>'.$h->link_to("?action=highlight&amp;value=$needle",
                             $page_name,"tabindex='$idx'");
    $out.= ' . . . . ' . $count . (($count == 1) ? ' match' : ' matches');
    if ($opts[context])
       $out.= find_needle($p->_get_raw_body(),$needle,$opts[context]);
    $out.= "</li>\n";
    $idx++;
  }
  $out.= "</ul>\n";

  $opts[msg]= sprintf('Full text search for "%s"', $needle);
  $opts[hits]= count($hits);
  $opts[all]= count($pages);
  return $out;
}

function macro_ISBN($formatter="",$value="") {
  $ISBN_MAP="ISBNMap";
  $DEFAULT=<<<EOS
Amazon http://www.amazon.com/exec/obidos/ISBN= http://images.amazon.com/images/P/\$ISBN.01.MZZZZZZZ.gif
Aladdin http://www.aladdin.co.kr/catalog/book.asp?ISBN= http://www.aladdin.co.kr/Cover/\$ISBN_1.gif
EOS;

  $DEFAULT_ISBN="Amazon";
  $re_isbn="/([0-9\-]{9,}[xX]?)(?:\s*,\s*)?([A-Z][a-z]*)?(?:\s*,\s*)?(noimg)?/";

  $test=preg_match($re_isbn,$value,$match);
  if ($test === false)
     return "<p><strong class=\"error\">Invalid ISBN \"%value\"</strong></p>";

  $isbn2=$match[1];
  $isbn=str_replace("-","",$isbn2);

  if ($match[2] && strtolower($match[2][0])=="k")
     $lang="Aladdin";
  else
     $lang=$DEFAULT_ISBN;

  $list= $DEFAULT;
  $map= new WikiPage($ISBN_MAP);
  if ($map->exists)
     $list.=$map.get_raw_body();

  $lists=explode("\n",$list);
  $ISBN_list=array();
  foreach ($lists as $line) {
     if (!$line or !preg_match("/[a-z]/i",$line[0])) continue;
     $dum=explode(" ",$line);
     if (sizeof($dum) == 2)
        $dum[]=$ISBN_list[$DEFAULT_ISBN][0];
     else if (sizeof($dum) !=3) continue;

     $ISBN_list[$dum[0]]=array($dum[1],$dum[2]);
  }

  if ($ISBN_list[$lang]) {
     $booklink=$ISBN_list[$lang][0];
     $imglink=$ISBN_list[$lang][1];
  } else {
     $booklink=$ISBN_list[$DEFAULT_ISBN][0];
     $imglink=$ISBN_list[$DEFAULT_ISBN][1];
  }

  if (strpos($booklink,'$ISBN') === false)
     $booklink.=$isbn;
  else {
     if (strpos($booklink,'$ISBN2') === false)
        $booklink=str_replace('$ISBN',$isbn,$booklink);
     else
        $booklink=str_replace('$ISBN2',$isbn2,$booklink);
  }

  if (strpos($imglink, '$ISBN') === false)
        $imglink.=$isbn;
  else {
     if (strpos($imglink, '$ISBN2') === false)
        $imglink=str_replace('$ISBN', $isbn, $imglink);
     else
        $imglink=str_replace('$ISBN2', $isbn2, $imglink);
  }

  if ($match[3] && $match[3] == 'noimg')
     return $DBInfo->icon[www]."[<a href='$booklink'>ISBN-$isbn2</a>]";
  else
     return "<a href='$booklink'><img src='$imglink' border='1' title='$lang".
       ": ISBN-$isbn' alt='[ISBN-$isbn2]'></a>";
}

function macro_TitleSearch($formatter="",$needle="",$opts=array()) {
  global $DBInfo;

  if (!$needle) {
    $opts[msg] = 'Use more specific text';
    return "<form method='get' action=''>
      <input type='hidden' name='action' value='titlesearch' />
      <input name='value' size='30' value='$needle' />
      <input type='submit' value='Go' />
      </form>";
  }
  $test=@preg_match("/$needle/","",$match);
  if ($test === false) {
    $opts[msg] = sprintf('Invalid search expression "%s"', $needle);
    return "<form method='get' action=''>
      <input type='hidden' name='action' value='titlesearch' />
      <input name='value' size='30' value='$needle' />
      <input type='submit' value='Go' />
      </form>";
  }
  $pages= $DBInfo->getPageLists();
  $hits=array();
  foreach ($pages as $page) {
     preg_match("/".$needle."/i",$page,$matches);
     if ($matches)
        $hits[]=$page;
  }

  sort($hits);

  $out="<ul>\n";
  $idx=1;
  foreach ($hits as $pagename) {
    $p = new WikiPage($pagename);
    $h = new Formatter($p);
    if ($opts[linkto])
      $out.= '<li>' . $formatter->link_to("$opts[linkto]$pagename",$pagename,"tabindex='$idx'")."</li>\n";
    else
      $out.= '<li>' . $h->link_to("","","tabindex='$idx'")."</li>\n";
    $idx++;
  }

  $out.="</ul>\n";
  $opts[hits]= count($hits);
  $opts[all]= count($pages);
  return $out;
}

function macro_GoTo($formatter="",$value="") {
  return "<form method='get' action=''>
    <input type='hidden' name='action' value='goto' />
    <input name='value' size='30' value='$value' />
    <input type='submit' value='Go' />
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
   $html=substr($formatter->pre_line,6);
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
  $php=substr($formatter->pre_line,5);
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
  return $log."<img src='/wiki/$cache_dir/$uniq.png' alt='gnuplot' />";
}

?>
