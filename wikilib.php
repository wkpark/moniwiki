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

function find_needle($body,$needle,$count=0) {
  if (!$body) return '';
  $lines=explode("\n",$body);
  $out="";
  $matches=preg_grep("/($needle)/i",$lines);
  if (count($matches) > $count) $matches=array_slice($matches,0,$count);
  foreach ($matches as $line) {
    $line=preg_replace("/($needle)/i","<strong>\\1</strong>",str_replace("<","&lt;",$line));
    $out.="<br />\n&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".$line;
  }
  return $out;
}

function normalize($title) {
  if (strpos($title," "))
    #return preg_replace("/[\?!$%\.\^;&\*()_\+\|\[\] ]/","",ucwords($title));
    return str_replace(" ","",ucwords($title));
  return $title;
}

function get_title($page) {
  global $DBInfo;
  if ($DBInfo->use_titlecache) {
    $cache=new Cache_text('title');
    if ($cache->exists($page)) $title=$cache->fetch($page);
  } else
    $title=$page;

  return preg_replace("/((?<=[A-Za-z0-9])[A-Z][a-z0-9])/"," \\1",$title);
}

function getTicket() {
  global $DBInfo;
  $config=getConfig("config.php");
  foreach ($config as $seed) {
    if (!is_array($seed))
      $md5.=md5($seed);
  }
  return md5($md5.time().$_SERVER['HTTP_HOST']);
}

function log_referer($referer,$page) {
  global $DBInfo;
  if (!$referer) return;

  $ignore=array("http://".$_SERVER['HTTP_HOST']);

  foreach ($ignore as $str)
    if (($p=strpos($referer,$str)) !== false) return;

  if (!file_exists($DBInfo->cache_dir."/referer")) {
    umask(000);
    mkdir($DBInfo->cache_dir."/referer",0777);
    umask(011);
    touch($DBInfo->cache_dir."/referer/referer.log");
  }

  $fp=fopen($DBInfo->cache_dir."/referer/referer.log",'a');
  $date=gmdate("Y-m-d\TH:i:s",time());
  fwrite($fp,"$date\t$page\t$referer\n");
  fclose($fp);
}

class UserDB {
  var $users=array();
  function UserDB($WikiDB) {
    $this->user_dir=$WikiDB->data_dir.'/user';
  }

  function getUserList() {
    if ($this->users) return $this->users;

    $users = array();
    $handle = opendir($this->user_dir);
    while ($file = readdir($handle)) {
      if (is_dir($this->user_dir."/".$file)) continue;
      if (preg_match('/^wu-([^\.]+)$/', $file,$match))
        #$users[$match[1]] = 1;
        $users[] = $match[1];
    }
    closedir($handle);
    $this->users=$users;
    return $users; 
  }

  function getPageSubscribers($pagename) {
    $users=$this->getUserList();
    $subs=array();
    foreach ($users as $id) {
      $usr=$this->getUser($id);
      if ($usr->isSubscribedPage($pagename)) $subs[]=$usr->info['email'];
    }
    return $subs;
  }

  function addUser($user) {
    if ($this->_exists($user->id))
      return false;
    $this->saveUser($user);
    return true;
  }

  function isNotUser($user) {
    if ($this->_exists($user->id))
      return false;
    return true;
  }

  function saveUser($user) {
    $config=array("css_url","datatime_fmt","email","bookmark","language",
                  "name","password","wikiname_add_spaces","subscribed_pages",
                  "quicklinks","theme","ticket","eticket");

    $date=date('Y/m/d', time());
    $data="# Data saved $date\n";

    if ($user->ticket)
      $user->info['ticket']=$user->ticket;

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

  function checkUser($user) {
    $tmp=$this->getUser($user->id);
    if ($tmp->info['ticket'] != $user->ticket) {
      $user->id='Anonymous';
      return 1;
    }
    $user=$tmp;
    return 0;
  }

  function getUser($id) {
    if ($this->_exists($id))
       $data=file("$this->user_dir/wu-$id");
    else {
       $user=new User('Anonymous');
       return $user;
    }
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
     if ($id) {
        $this->setID($id);
        return;
     }
     list($this->ticket,$id)=explode(".",$_COOKIE['MONI_ID'],2);

     $this->setID($id);
     $this->css=$_COOKIE['MONI_CSS'];
     $this->theme=$_COOKIE['MONI_THEME'];
     $this->bookmark=$_COOKIE['MONI_BOOKMARK'];
     $this->trail=stripslashes($_COOKIE['MONI_TRAIL']);
  }

  function setID($id) {
     if ($this->checkID($id)) {
        $this->id=$id;
        return true;
     }
     $this->id='Anonymous';
     $this->ticket='';
     return false;
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
     $ticket=getTicket();
     $this->ticket=$ticket;
     if ($this->id == "Anonymous") return false;
     setcookie("MONI_ID",$ticket.'.'.$this->id,time()+60*60*24*30,get_scriptname());
     # set the fake cookie
     $_COOKIE['MONI_ID']=$ticket.'.'.$this->id;
  }

  function unsetCookie() {
     header("Set-Cookie: MONI_ID=".$this->id."; expires=Tuesday, 01-Jan-1999 12:00:00 GMT; Path=".get_scriptname());
     # set the fake cookie
     $_COOKIE[MONI_ID]="Anonymous";
  }

  function setPasswd($passwd,$passwd2="") {
     if (!$passwd2) $passwd2=$passwd;
     $ret=$this->validPasswd($passwd,$passwd2);
     if ($ret > 0)
        $this->info['password']=crypt($passwd);
#     else
#        $this->info[password]="";
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
     if (crypt($passwd,$this->info['password']) == $this->info['password'])
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

  function isSubscribedPage($pagename) {
    if (!$this->info['email'] or !$this->info['subscribed_pages']) return 0;
    $page_list=explode("\t",$this->info['subscribed_pages']);
    $page_rule=join("|",$page_list);
    if (preg_match('/('.$page_rule.')/',$pagename)) {
      return true;
    }
    return false;
  }
}


function do_highlight($formatter,$options) {

  $formatter->send_header("",$options);
  $formatter->send_title("","",$options);

  $formatter->highlight=stripslashes($options['value']);
  $formatter->send_page();
  $args['editable']=1;
  $formatter->send_footer($args,$options);
}

function do_diff($formatter,$options="") {
  global $DBInfo;

  $range=$options['range'];
  $date=$options['date'];
  $rev=$options['rev'];
  $rev2=$options['rev2'];
  if ($options['rcspurge']) {
    if (!$range) $range=array();
    $rr='';
    $dum=array();
    foreach (array_keys($range) as $r) {
      if (!$rr) $rr=$range[$r];
      if ($range[$r+1]) continue;
      else
        $rr.=":".$range[$r];
      $dum[]=$rr;$rr='';
    }
    $options['range']=$dum;
    do_RcsPurge($formatter,$options);
    return;
  }
  $formatter->send_header("",$options);
  $formatter->send_title("Diff for $rev ".$options['page'],"",$options);
  if ($date)
    print $formatter->get_diff($date);
  else
    print $formatter->get_diff($rev,$rev2);
  if (!$DBInfo->diffonly) {
    print "<br /><hr />\n";
    $formatter->send_page();
  }
  $formatter->send_footer($args,$options);
  return;
}

  function macro_EditHints($formatter) {
    $hints = "<div class=\"hint\">\n";
    $hints.= _("<b>Emphasis:</b> ''<i>italics</i>''; '''<b>bold</b>'''; '''''<b><i>bold italics</i></b>''''';\n''<i>mixed '''<b>bold</b>''' and italics</i>''; ---- horizontal rule.<br />\n<b>Headings:</b> = Title 1 =; == Title 2 ==; === Title 3 ===;\n==== Title 4 ====; ===== Title 5 =====.<br />\n<b>Lists:</b> space and one of * bullets; 1., a., A., i., I. numbered items;\n1.#n start numbering at n; space alone indents.<br />\n<b>Links:</b> JoinCapitalizedWords; [\"brackets and double quotes\"];\n[bracketed words];\nurl; [url]; [url label].<br />\n<b>Tables</b>: || cell text |||| cell text spanning two columns ||;\nno trailing white space allowed after tables or titles.<br />\n");
    $hints.= "</div>\n";
    return $hints;
  }

function macro_EditText($formatter,$value,$options='') {
  global $DBInfo;
  if ($DBInfo->hasPage('EditTextForm')) {
    $p=$DBInfo->getPage('EditTextForm');
    $form=$p->get_raw_body();

    ob_start();
    $formatter->send_page(rtrim($form),$options);
    $form= ob_get_contents();
    ob_end_clean();

    $editform= macro_Edit($formatter,'nohints,nomenu',$options);
    $new=str_replace("\n#editform",$editform,$form);
    if ($form == $new) $form.=$editform;
    else $form=$new;
  } else {
    $form = macro_Edit($formatter,$value,$options);
  }
  return $form;
}

function do_edit($formatter,$options) {
  global $DBInfo;
  if (!$DBInfo->security->writable($options)) {
    do_invalid($formatter,$options);
  }
  $formatter->send_header("",$options);
  $formatter->send_title("Edit ".$options['page'],"",$options);
  print macro_EditText($formatter,$value,$options);
  $formatter->send_footer($args,$options);
}

function macro_Edit($formatter,$value,$options='') {
  global $DBInfo;

  $COLS_MSIE= 80;
  $COLS_OTHER= 85;
  $cols= preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT']) ? $COLS_MSIE : $COLS_OTHER;

  $rows= $options['rows'] > 5 ? $options['rows']: 16;
  $cols= $options['cols'] > 60 ? $options['cols']: $cols;

  $text= $options['savetext'];

  $args= explode(',',$value);
  if (in_array('nohints',$args)) $options['nohints']=1;
  if (in_array('nomenu',$args)) $options['nomenu']=1;

  $preview= $options['preview'];

  if (!$formatter->page->exists()) {
    $options['linkto']="?action=edit&amp;template=";
    $form = _("Use one of the following templates as an initial release :\n");
    $form.= macro_TitleSearch($formatter,".*Template",$options);
    $form.= _("To create your own templates, add a page with a 'Template' suffix.\n"."<br />\n");
  }

  if ($options['conflict'])
    $extra='<input type="submit" name="button_merge" value="Merge" />';

  # make a edit form
  $form.= "<a id='editor' name='editor' />\n";

  if ($options['page'])
    $previewurl=$formatter->link_url(_urlencode($options['page']),'#preview');
  else
    $previewurl=$formatter->link_url($formatter->page->urlname,'#preview');

  $menu= '';
  if ($preview)
    $menu= $formatter->link_to('#preview',_("Skip to preview"));
  else
    $menu= $formatter->link_to("?action=edit&amp;rows=".($rows-3),_("ReduceEditor"));

  if (!$options['nomenu']) {
    $menu.= " | ".$formatter->link_tag('InterWiki',"",_("InterWiki"));
    $menu.= " | ".$formatter->link_tag('HelpOnEditing',"",_("HelpOnEditing"));
  }

  $form.=$menu;
  $form.= sprintf('<form name="editform" method="post" action="%s">', $previewurl);
  if ($text) {
    $raw_body = str_replace('\r\n', '\n', $text);
  } else if ($formatter->page->exists()) {
    $raw_body = str_replace('\r\n', '\n', $formatter->page->_get_raw_body());
  } else if ($options['template']) {
    $p= new WikiPage($options['template']);
    $raw_body = str_replace('\r\n', '\n', $p->get_raw_body());
  } else
    $raw_body = sprintf(_("Describe %s here"), $options['page']);

  # for conflict check
  if ($options['datestamp'])
     $datestamp= $options['datestamp'];
  else
     $datestamp= $formatter->page->mtime();

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
  $form.=<<<EOS
<textarea class="wiki" id="content" wrap="virtual" name="savetext"
 rows="$rows" cols="$cols" class="wiki">$raw_body</textarea><br />
$summary_msg: <input name="comment" size="70" maxlength="70" style="width:200" /><br />
<input type="hidden" name="action" value="savepage" />
<input type="hidden" name="datestamp" value="$datestamp" />
$select_category
<input type="submit" value="$save_msg" />&nbsp;
<!-- <input type="reset" value="Reset" />&nbsp; -->
<input type="submit" name="button_preview" value="$preview_msg" />
$extra
</form>
EOS;
  if (!$options['nohints'])
    $form.= macro_EditHints($formatter);
  $form.= "<a id='preview' name='preview' />";
  return $form;
}


function do_info($formatter,$options) {
  $formatter->send_header("",$options);
  $formatter->send_title(sprintf(_("Info. for %s"),$options['page']),"",$options);
  $formatter->show_info();
  $formatter->send_footer($args,$options);
}

function do_invalid($formatter,$options) {
  $formatter->send_header("Status: 406 Not Acceptable",$options);
  if ($options['title'])
    $formatter->send_title('',"",$options);
  else
    $formatter->send_title(_("406 Not Acceptable"),"",$options);
  if ($options['action'])
    $formatter->send_page("== ".sprintf(_("%s is not valid action"),$options['action'])." ==\n");
  else
    $formatter->send_page("== "._("Is it valid action ?")." ==\n");
  $formatter->send_footer("",$options);
}

function do_post_DeleteFile($formatter,$options) {
  global $DBInfo;

  if ($options['value']) {
    $key=$DBInfo->pageToKeyname($options['value']);
    $dir=$DBInfo->upload_dir."/$key";
  } else {
    $dir=$DBInfo->upload_dir;
  }

  if (isset($options['files'])) {
    if ($options['files']) {
      foreach ($options['files'] as $file) {
        if (!is_dir($dir."/".$file)) {
          if (@unlink($dir."/".$file))
            $log.=sprintf(_("File '%s' is deleted")."<br />",$file);
          else
            $log.=sprintf(_("Fail to delete '%s'")."<br />",$file);
        } else {
          if (@rmdir($dir."/".$file))
            $log.=sprintf(_("Directory '%s' is deleted")."<br />",$file);
          else
            $log.=sprintf(_("Fail to rmdir '%s'")."<br />",$file);
        }
      }
      $title = sprintf(_("Delete selected files"));
      $formatter->send_header("",$options);
      $formatter->send_title($title,"",$options);
      print $log;
      $formatter->send_footer();
      return;
    } else
      $title = sprintf(_("No files are selected !"));
  } else {
    $title = sprintf(_("No files are selected !"));
  }
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  print $log;
  $formatter->send_footer();
  return;
}

function do_DeletePage($formatter,$options) {
  global $DBInfo;
  
  $page = $DBInfo->getPage($options['page']);

  if (stripslashes($options['value']) == $options['page']) {
    $DBInfo->deletePage($page);
    $title = sprintf(_("\"%s\" is deleted !"), $page->name);
    $formatter->send_header("",$options);
    $formatter->send_title($title,"",$options);
    $formatter->send_footer();
    return;
  } else if ($options['value']) {
    $options['msg'] = _("Please delete this file manually.");
  }
  $title = sprintf(_("Delete \"%s\" ?"), $page->name);
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  print "<form method='post'>
Comment: <input name='comment' size='80' value='' /><br />\n";
  if ($DBInfo->security->is_protected("DeletePage",$options))
    print "Password: <input type='password' name='passwd' size='20' value='' />
Only WikiMaster can delete this page<br />\n";
  print "
    <input type='hidden' name='action' value='DeletePage' />
    <input type='hidden' name='value' value='$options[page]' />
    <input type='submit' value='Delete page' />
    </form>";
#  $formatter->send_page();
  $formatter->send_footer();
}

function form_permission($mode) {
  if ($mode & 0400)
     $read="checked='checked'";
  if ($mode & 0200)
     $write="checked='checked'";
  $form= "<tr><th>read</th><th>write</th></tr>\n";
  $form.= "<tr><td><input type='checkbox' name='read' $read /></td>\n";
  $form.= "<td><input type='checkbox' name='write' $write /></td></tr>\n";
  return $form;
}

function do_chmod($formatter,$options) {
  global $DBInfo;
  
  if (isset($options['read']) or isset($options['write'])) {
    if ($DBInfo->hasPage($options['page'])) {
      $perms= $DBInfo->getPerms($options['page']);
      $perms&= 0077; # clear user perms
      if ($options[read])
         $perms|=0400;
      if ($options[write])
         $perms|=0200;
      $DBInfo->setPerms($options['page'],$perms);
      $title = sprintf(_("Permission of \"%s\" changed !"), $options['page']);
      $formatter->send_header("",$options);
      $formatter->send_title($title,"",$options);
      $formatter->send_footer("",$options);
      return;
    } else {
      $title = sprintf(_("Fail to chmod \"%s\" !"), $options['page']);
      $formatter->send_header("",$options);
      $formatter->send_title($title,"",$options);
      $formatter->send_footer("",$options);
      return;
    }
  }
  $perms= $DBInfo->getPerms($options['page']);

  $form=form_permission($perms);

  $title = sprintf(_("Change permission of \"%s\""), $options['page']);
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
#<tr><td align='right'><input type='checkbox' name='show' checked='checked' />show only </td><td><input type='password' name='passwd'>
  print "<form method='post'>
<table border='0'>
$form
</table>\n";
  if ($DBInfo->security->is_protected("deletefile",$options))
    print "
Password:<input type='password' name='passwd' />
<input type='submit' name='button_chmod' value='change' /><br />
Only WikiMaster can change the permission of this page\n";
  print "
<input type=hidden name='action' value='chmod' />
</form>";
#  $formatter->send_page();
  $formatter->send_footer();
}

function do_raw($formatter,$options) {
  $formatter->send_header("Content-Type: text/plain",$options);
  print $formatter->page->get_raw_body();
}

function do_recall($formatter,$options) {
  $formatter->send_header("",$options);
  $formatter->send_title("Rev. ".$options['rev']." ".
                                 $options['page'],"",$options);
  $formatter->send_page();
  $formatter->send_footer($args,$options);
}

function do_RcsPurge($formatter,$options) {
  global $DBInfo;

  # XXX 
  if (!$options['show'] and 
     $DBInfo->security->is_protected("rcspurge",$options) and
     !$DBInfo->security->is_valid_password($options['passwd'],$options)) {

    $title= sprintf('Invalid password to purge "%s" !', $options['page']);
    $formatter->send_header("",$options);
    $formatter->send_title($title);
    $formatter->send_footer();
    return;
  }
     
  $title= sprintf(_("RCS purge \"%s\""),$options['page']);
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  if ($options['range']) {
    foreach ($options['range'] as $range) {
       printf("<h3>range '%s' purged</h3>",$range);
       if ($options['show'])
         print "<tt>rcs -o$range ".$options['page']."</tt><br />";
       else {
         #print "<b>Not enabled now</b> <tt>rcs -o$range  data_dir/".$options[page]."</tt><br />";
         print "<tt>rcs -o$range ".$options['page']."</tt><br />";
         system("rcs -o$range ".$formatter->page->filename);
       }
    }
  } else {
    printf("<h3>No version selected to purge '%s'</h3>",$options['page']);
  }
  $args['noaction']=1;
  $formatter->send_footer($args,$options);
}

function do_fullsearch($formatter,$options) {

  $ret=$options;

  if ($options['backlinks'])
    $title= sprintf(_("BackLinks search for \"%s\""), $options['value']);
  else
    $title= sprintf(_("Full text search for \"%s\""), $options['value']);
  $out= macro_FullSearch($formatter,$options['value'],&$ret);
  $options['msg']=$ret['msg'];
  $formatter->send_header("",$options);
  $formatter->send_title($title,$formatter->link_url("FindPage"),$options);

  print $out;

  if ($options['value'])
    printf(_("Found %s matching %s out of %s total pages")."<br />",
	 $ret['hits'],
	($ret['hits'] == 1) ? 'page' : 'pages',
	 $ret['all']);
  $args['noaction']=1;
  $formatter->send_footer($args,$options);
}

function do_goto($formatter,$options) {
  global $DBInfo;
  if (preg_match("/^(http:\/\/|ftp:\/\/)/",$options['value'])) {
     $options['url']=$options['value'];
     unset($options['value']);
  } else if (preg_match("/^(".$DBInfo->interwikirule."):(.*)/",$options[value],$match)) {
    $url=$DBInfo->interwiki[$match[1]];
    if ($url) {
      $page=trim($match[2]);

      if (strpos($url,'$PAGE') === false)
        $url.=$page;
      else
        $url=str_replace('$PAGE',$page,$url);
      $options['url']=$url;
      unset($options['value']);
    }
  }
  if ($options['value']) {
     $url=stripslashes($options['value']);
     $url=_rawurlencode($url);
     if ($options['redirect'])
       $url=$formatter->link_url($url,"?action=show");
     else
       $url=$formatter->link_url($url,"");
     $formatter->send_header(array("Status: 302","Location: ".$url),$options);
  } else if ($options['url']) {
     $url=str_replace("&amp;","&",$options['url']);
     $formatter->send_header(array("Status: 302","Location: ".$url),$options);
  } else {
     $title = _("Use more specific text");
     $formatter->send_header("",$options);
     $formatter->send_title($title,"",$options);
     $args['noaction']=1;
     $formatter->send_footer($args,$options);
  }
}

function do_LikePages($formatter,$options) {

  $opts['metawiki']=$options['metawiki'];
  $out= macro_LikePages($formatter,$options['page'],&$opts);
  
  $title = $opts['msg'];
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  print $opts['extra'];
  print $out;
  print $opts['extra'];
  $formatter->send_footer("",$options);
}

function do_titleindex($formatter,$options) {
  global $DBInfo;

  $pages = $DBInfo->getPageLists();

  sort($pages);
  header("Content-Type: text/plain");
  print join("\n",$pages);
}

function do_titlesearch($formatter,$options) {

  $out= macro_TitleSearch($formatter,$options['value'],&$ret);

  $formatter->send_header("",$options);
  $formatter->send_title($ret['msg'],$formatter->link_url("FindPage"),$options);
  print $out;

  if ($options['value'])
    printf("Found %s matching %s out of %s total pages"."<br />",
	 $ret['hits'],
	($ret['hits'] == 1) ? 'page' : 'pages',
	 $ret['all']);
  $args['noaction']=1;
  $formatter->send_footer($args,$options);
}

function do_post_savepage($formatter,$options) {
  global $DBInfo;
  if (!$DBInfo->security->writable($options)) {
    do_invalid($formatter,$options);
  }

  $savetext=$options['savetext'];
  $datestamp=$options['datestamp'];
  $button_preview=$options['button_preview'];
  $button_merge=$options['button_merge'];

  $formatter->send_header("",$options);

  $savetext=str_replace("\r", "", $savetext);
  $savetext=stripslashes($savetext);
  if ($savetext and $savetext[strlen($savetext)-1] != "\n")
    $savetext.="\n";

  $new=md5($savetext);

  $menu = $formatter->link_to("#editor",_("Goto Editor"));

  if ($formatter->page->exists()) {
    # check difference
    $body=$formatter->page->get_raw_body();
    $body=str_replace("\r", "", $body);
    $orig=md5($body);
    # check datestamp
    if ($formatter->page->mtime() > $datestamp) {
      $options['msg']=sprintf(_("Someone else saved the page while you edited %s"),$formatter->link_tag($formatter->page->urlname,"",$options['page']));
      $options['preview']=1; 
      $options['conflict']=1; 
      $options['datestamp']=$datestamp; 
      if ($button_merge) {
        $title=sprintf(_("Preview of %s"),$formatter->link_tag($formatter->page->urlname,"",$options['page'],"class='title'"));
        $formatter->send_title($title,"",$options);
        $options['conflict']=0; 
        $merge=$formatter->get_merge($savetext);
        if ($merge) $savetext=$merge;
        unset($options['datestamp']); 
      } else
        $formatter->send_title(_("Conflict error!"),"",$options);
      $options['savetext']=$savetext;
      print macro_EditText($formatter,$value,$options); # XXX

      print $menu;
      print "<div id='wikiPreview'>\n";
      $formatter->get_diff("","",$savetext);
      print "</div>\n";
      $formatter->send_footer();
      return;
    }
  }

  if (!$button_preview && $orig == $new) {
    $options['msg']=sprintf(_("Go back or return to %s"),$formatter->link_tag($formatter->page->urlname,"",$options['page']));
    $formatter->send_title(_("No difference found"),"",$options);
    $formatter->send_footer();
    return;
  }
  $formatter->page->set_raw_body($savetext);

  if ($button_preview) {
    $title=sprintf(_("Preview of %s"),$formatter->link_tag($formatter->page->urlname,"",$options['page'],"class='title'"));
    $formatter->send_title($title,"",$options);
     
    $options['preview']=1; 
    $options['datestamp']=$datestamp; 
    $options['savetext']=$savetext;
    print macro_EditText($formatter,$value,$options); # XXX
    print $DBInfo->hr;
    print $menu;
    print "<div id='wikiPreview'>\n";
    $formatter->preview=1;
    $formatter->send_page($savetext);
    $formatter->preview=0;
    print $DBInfo->hr;
    print "</div>\n";
    print $menu;
  } else {
    if ($options['category'])
      $savetext.="----\n$options[category]\n";

    $comment=stripslashes($options['comment']);
    $formatter->page->write($savetext);
    $ret=$DBInfo->savePage($formatter->page,$comment,$options);
    if ($DBInfo->notify) {
      $options['noaction']=1;
      if (!function_exists('mail')) {
        $options['msg']=sprintf(_("mail does not supported by default."))."<br />";
      } else {
        $ret2=wiki_notify($formatter,$options);
        if ($ret2)
          $options['msg']=sprintf(_("Mail notifications are sented."))."<br />";
        else
          $options['msg']=sprintf(_("No subscribers found."))."<br />";
      }
    }
      
    if ($ret == -1)
      $options['msg'].=sprintf(_("%s is not editable"),$formatter->link_tag($formatter->page->urlname,"",$options['page']));
    else
      $options['msg'].=sprintf(_("%s is saved"),$formatter->link_tag($formatter->page->urlname,"?action=show",$options['page']));
    $formatter->send_title("","",$options);
    $opt['pagelinks']=1;
    # re-generates pagelinks
    $formatter->send_page("",$opt);
  }
  $args['editable']=0;
  $formatter->send_footer($args,$options);
}

function wiki_notify($formatter,$options) {
  global $DBInfo;

  $from= $options['id'];
#  if ($options[id] != 'Anonymous')
#

  $udb=new UserDB($DBInfo);
  $subs=$udb->getPageSubscribers($options['page']);
  if (!$subs) {
    if ($options['noaction']) return 0;

    $title=_("Nobody subscribed to this page, no mail sented.");
    $formatter->send_header("",$options);
    $formatter->send_title($title,"",$options);
    print "Fail !";
    $formatter->send_footer("",$options);
    return;
  }

  $diff="";
  $option="-r".$formatter->page->get_rev();
  $fp=popen("rcsdiff -x,v/ -u $option ".$formatter->page->filename,"r");
  if (!$fp)
    $diff="";
  else {
    while (!feof($fp)) {
      $line=fgets($fp,1024);
      $diff.= $line;
    }
    pclose($fp);
  }

  $mailto=join(", ",$subs);
  $subject="[".$DBInfo->sitename."] ".sprintf(_("%s page is modified"),$options[page]);
 
  $mailheaders = "Return-Path: $from\r\n";
  $mailheaders.= "From: $from\r\n";
  $mailheaders.= "X-Mailer: MoniWiki form-mail interface\r\n";

  $mailheaders.= "MIME-Version: 1.0\r\n";
  $mailheaders.= "Content-Type: text/plain; charset=$DBInfo->charset\r\n";
  $mailheaders.= "Content-Transfer-Encoding: 8bit\r\n\r\n";

  $body=sprintf(_("You have subscribed to this wiki page on \"%s\" for change notification.\n\n"),$DBInfo->sitename);
  $body.="-------- $options[page] ---------\n";
  
  $body.=$formatter->page->get_raw_body();
  if (!$options['nodiff']) {
    $body.="================================\n";
    $body.=$diff;
  }

  mail($mailto,$subject,$body,$mailheaders);

  if ($options['noaction']) return 1;

  $title=_("Send mail notification to all subscribers");
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  $msg= str_replace("@"," at ",$mailto);
  print "<h2>".sprintf(_("Mail sented successfully"))."</h2>";
  printf(sprintf(_("mail sented to '%s'"),$msg));
  $formatter->send_footer("",$options);
  return;
}

function do_uploadfile($formatter,$options) {
  global $DBInfo;

  # replace space and ':' strtr()
  $upfilename=str_replace(" ","_",$_FILES['upfile']['name']);
  $upfilename=str_replace(":","_",$upfilename);

  preg_match("/(.*)\.([a-z0-9]{1,4})$/i",$upfilename,$fname);

  if (!$upfilename) {
     #$title="No file selected";
     $formatter->send_header("",$options);
     $formatter->send_title($title,"",$options);
     print macro_UploadFile($formatter,'',$options);
     $formatter->send_footer("",$options);
     return;
  }
  # upload file protection
  if ($DBInfo->pds_allowed)
     $pds_exts=$DBInfo->pds_allowed;
  else
     $pds_exts="png|jpg|jpeg|gif|mp3|zip|tgz|gz|txt|css|exe|hwp";
  if (!preg_match("/(".$pds_exts.")$/i",$fname[2])) {
     $title="$fname[2] extension does not allowed to upload";
     $formatter->send_header("",$options);
     $formatter->send_title($title,"",$options);
     $formatter->send_footer("",$options);
     return;
  }
  $key=$DBInfo->pageToKeyname($formatter->page->name);
  if ($key != 'UploadFile')
    $dir= $DBInfo->upload_dir."/$key";
  else
    $dir= $DBInfo->upload_dir;
  if (!file_exists($dir)) {
    umask(000);
    mkdir($dir,0777);
    umask(02);
  }

  $file_path= $newfile_path = $dir."/".$upfilename;
  if ($options['rename']) {
    # XXX
    $temp=explode("/",stripslashes($options['rename']));
    $upfilename= $temp[count($temp)-1];

    preg_match("/(.*)\.([a-z0-9]{1,4})$/i",$upfilename,$tname);
    # do not change the extention of the file.
    $fname[1]=$tname[1];
    $newfile_path = $dir."/".$tname[1].".$fname[2]";
  }

  # is file already exists ?
  $dummy=0;
  while (file_exists($newfile_path)) {
     $dummy=$dummy+1;
     $ufname=$fname[1]."_".$dummy; // rename file
     $upfilename=$ufname.".$fname[2]";
     $newfile_path= $dir."/".$upfilename;
  }
 
  $upfile=$_FILES['upfile']['tmp_name'];
  //$temp=explode("/",$_FILES['upfile']['tmp_name']);
  //$upfile="/tmp/".$temp[count($temp)-1];
  // Tip at http://phpschool.com

  if ($options['replace']) {
    if ($newfile_path) $test=@copy($file_path, $newfile_path);
    $test=@copy($upfile, $file_path);
  } else {
    $test=@copy($upfile, $newfile_path);
  }
  if (!$test) {
     $title=sprintf(_("Fail to copy \"%s\" to \"%s\""),$upfilename,$file_path);
     $formatter->send_header("",$options);
     $formatter->send_title($title,"",$options);
     return;
  }
  chmod($newfile_path,0644);

  $REMOTE_ADDR=$_SERVER['REMOTE_ADDR'];
  $comment="File '$upfilename' uploaded";
  $DBInfo->addLogEntry($key, $REMOTE_ADDR,$comment,"UPLOAD");
  
  $title=sprintf(_("File \"%s\" is uploaded successfully"),$upfilename);
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  print "<ins>Uploads:$upfilename</ins>";
  $formatter->send_footer();
}


function do_new($formatter,$options) {
  $title=_("Create a new page");
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  $url=$formatter->link_url($formatter->page->urlname);

  $msg=_("Enter a page name");
  print <<<FORM
<form method='get' action='$url'>
    $msg: <input type='hidden' name='action' value='goto' />
    <input name='value' size='30' />
    <input type='submit' value='Create' />
    </form>
FORM;

  $formatter->send_footer();
}

function do_bookmark($formatter,$options) {
  global $DBInfo;
  global $_COOKIE;

  $user=new User(); # get cookie
  if ($user->id != 'Anonymous') {
    $udb=new UserDB($DBInfo);
    $udb->checkUser(&$user);
  }

  if (!$options['time']) {
     $bookmark=time();
  } else {
     $bookmark=$options['time'];
  }
  if (0 === strcmp($bookmark , (int)$bookmark)) {
    if ($user->id == "Anonymous") {
      setcookie("MONI_BOOKMARK",$bookmark,time()+60*60*24*30,get_scriptname());
      # set the fake cookie
      $_COOKIE['MONI_BOOKMARK']=$bookmark;
      $options['msg'] = 'Bookmark Changed';
    } else {
      $user->info['bookmark']=$bookmark;
      $udb->saveUser($user);
      $options['msg'] = 'Bookmark Changed';
    }
  } else
    $options['msg']="Invalid bookmark!";
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  $formatter->send_page();
  $formatter->send_footer("",$options);
}

function wiki_sendmail($body,$options) {
  global $DBInfo;

  if ($options['id'])
    $from=$options['id'];
  else
    $from=$DBInfo->sitename;

  $email=$options['email'];
  $subject=$options['subject'];

  $mailheaders = "Return-Path: $from\r\n";
  $mailheaders.= "From: $from\r\n";
  $mailheaders.= "X-Mailer: MoniWiki form-mail interface\r\n";

  $mailheaders.= "MIME-Version: 1.0\r\n";
  $mailheaders.= "Content-Type: text/plain; charset=$DBInfo->charset\r\n";
  $mailheaders.= "Content-Transfer-Encoding: 8bit\r\n\r\n";

  mail($email,$subject,$body,$mailheaders);
}

function do_userform($formatter,$options) {
  global $DBInfo;

  $user=new User(); # get cookie
  if ($user->id != 'Anonymous') { # XXX
    $udb=new UserDB($DBInfo);
    $udb->checkUser(&$user);
  }
  $id=$options['login_id'];

  # e-mail conformation
  if ($options['ticket'] and $id and $id!='Anonymous') {
    $userdb=new UserDB($DBInfo);
    if ($userdb->_exists($id)) {
       $user=$userdb->getUser($id);
       if ($user->info['eticket']==$options['ticket']) {
         list($dummy,$email)=explode('.',$options['ticket'],2);
         $user->info['email']=$email;
         $user->info['eticket']='';
         $userdb->saveUser($user);
         $title=_("Successfully confirmed");
         $options['msg']=_("Your e-mail address is confirmed successfully");
       } else {
         $title=_("Confirmation missmatched !");
         $options['msg']=_("Please try again to register your e-mail address");
       }
    } else {
      $title=_("ID does not exists !");
      $options['msg']=_("Please try again to register your e-mail address");
    }
    $formatter->send_header("",$options);
    $formatter->send_title($title,"",$options);

    $formatter->send_footer("",$options);
    return '';
  }

  if ($user->id == "Anonymous" and $id and $options['login_passwd']) {
    # login
    $userdb=new UserDB($DBInfo);
    if ($userdb->_exists($id)) {
       $user=$userdb->getUser($id);
       if ($user->checkPasswd($options['login_passwd'])=== true) {
          $options['msg'] = sprintf(_("Successfully login as '%s'"),$id);
          $user->setCookie();
          $userdb->saveUser($user); # XXX
       } else {
          $title = sprintf(_("Invalid password !"));
       }
    } else
       $title= _("Please enter a valid user ID !");
  } else if ($options['logout']) {
    # logout
    $user->unsetCookie();
    $title= _("Cookie deleted !");
  } else if ($user->id=="Anonymous" and $options['username'] and $options['password'] and $options['passwordagain']) {
    # create profile

    $id=$user->getID($options['username']);
    $user->setID($id);

    if ($user->id != "Anonymous") {
       $ret=$user->setPasswd($options['password'],$options['passwordagain']);
       if ($ret <= 0) {
           if ($ret==0) $title= _("too short password!");
           else if ($ret==-1) $title= _("mismatch password!");
           else if ($ret==-2) $title= _("not acceptable character found in the password!");
       } else {
           if ($ret < 8)
              $options['msg']=_("Your password is too simple to use as a password !");
           $udb=new UserDB($DBInfo);
           if ($options['email']) {
             if (preg_match('/^[a-z][a-z0-9_]+@[a-z0-9_]+(\.[a-z0-9_]+)+$/i',$options['email'])) {
               #$user->info['email']=$options['email'];
             } else
               $options['msg'].='<br/>'._("Your email address is not valid");
           }

           if ($udb->isNotUser($user)) {
              $title= _("Successfully added!");
              $ticket=md5(time().$user->id.$options['email']);
              $user->info['eticket']=$ticket.".".$options['email'];
              $user->setCookie();
              $ret=$udb->addUser($user);

              # XXX
              if ($options['email'] and preg_match('/^[a-z][a-z0-9_]+@[a-z0-9_]+(\.[a-z0-9_]+)+$/i',$options['email'])) {
                $options['subject']="[$DBInfo->sitename] "._("E-mail confirmation");
                $body=qualifiedUrl($formatter->link_url('',"?action=userform&login_id=$user->id&ticket=$ticket.$options[email]"));
                $body=_("Please confirm your email address")."\n".$body;
                wiki_sendmail($body,$options);
                $options['msg'].='<br/>'._("E-mail confirmation mail sented");
              }
           } else {# already exist user
              $user=$udb->getUser($user->id);
              if ($user->checkPasswd($options['password'])=== true) {
                  $options['msg'].= sprintf(_("Successfully login as '%s'"),$id);
                  $user->setCookie();
                  $udb->saveUser($user); # XXX
              } else {
                  $title = _("Invalid password !");
              }
           }
       }
    } else
       $title= _("Invalid username !");
  } else if ($user->id != "Anonymous") {
    # save profile
    $udb=new UserDB($DBInfo);
    $userinfo=$udb->getUser($user->id);

    if ($options['password'] and $options['passwordagain']) {
      if ($userinfo->checkPasswd($options['password'])=== true) {
        $ret=$userinfo->setPasswd($options['passwordagain']);

        if ($ret <= 0) {
          if ($ret==0) $title= _("too short password!");
          else if ($ret==-1)
            $title= _("mismatch password !");
          else if ($ret==-2)
            $title= _("not acceptable character found in the password!");
          $options['msg']= _("Password is not changed !");
        } else {
          $title= _("Password is changed !");
          if ($ret < 8)
            $options['msg']=_("Password is too simple to use as a password !");
        }
      } else {
        $title= _("Invalid password !");
        $options['msg']=_("Password is not changed !");
      }
    }
    if (isset($options['user_css']))
      $userinfo->info['css_url']=$options['user_css'];
    if ($options['email'] and ($options['email'] != $userinfo->info['email'])) {
      if (preg_match('/^[a-z][a-z0-9_]+@[a-z0-9_]+(\.[a-z0-9_]+)+$/i',$options['email'])) {
        $ticket=md5(time().$userinfo->info['id'].$options['email']);
        $userinfo->info['eticket']=$ticket.".".$options['email'];
        $options['subject']="[$DBInfo->sitename] "._("E-mail confirmation");
        $body=qualifiedUrl($formatter->link_url('',"?action=userform&login_id=$user->id&ticket=$ticket.$options[email]"));
        $body=_("Please confirm your email address")."\n".$body;
        wiki_sendmail($body,$options);
        $options['msg']=_("E-mail confirmation mail sented");
      } else {
        $options['msg']=_("Your email address is not valid");
      }
    }
    if ($options['username'])
      $userinfo->info['name']=$options['username'];
    $udb->saveUser($userinfo);
    #$options['css_url']=$options['user_css'];
    if (!isset($options['msg']))
      $options['msg']=_("Profiles are saved successfully !");
  }

  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  if (!$title)
    $formatter->send_page();
  else
    $formatter->send_page("Goto UserPreferences");
  $formatter->send_footer("",$options);
}

function macro_Include($formatter,$value="") {
  global $DBInfo;
  static $included=array();

  $savelinks=$formatter->pagelinks; # don't update pagelinks with Included files

  preg_match("/([^'\",]+)(?:\s*,\s*)?(\"[^\"]*\"|'[^']*')?$/",$value,$match);
  if ($match) {
    $value=trim($match[1]);
    if ($match[2])
      $title="=== ".substr($match[2],1,-1)." ===\n";
  }

  if ($value and !in_array($value, $included) and $DBInfo->hasPage($value)) {
    $ipage=$DBInfo->getPage($value);
    $ibody=$ipage->_get_raw_body();
    $opt['nosisters']=1;
    ob_start();
    $formatter->send_page($title.$ibody,$opt);
    $out= ob_get_contents();
    ob_end_clean();
    $formatter->pagelinks=$savelinks;
    return $out;
  } else {
    return "[[Include($value)]]";
  }
}

function do_RandomPage($formatter,$options='') {
  global $DBInfo;
  $pages= $DBInfo->getPageLists();
  $max=sizeof($pages)-1;
  $rand=rand(0,$max);
  $options['value']=$pages[$rand];
  do_goto($formatter,$options);
  return;
}

function macro_RandomPage($formatter,$value="") {
  global $DBInfo;
  $pages = $DBInfo->getPageLists();

  $test=preg_match("/^(\d+)\s*,?\s*(simple|nobr)?$/",$value,$match);
  if ($test) {
    $count= (int) $match[1];
    $mode=$match[2];
  }
  #$count= (int) $value;
  if ($count <= 0) $count=1;
  $counter= $count;

  $max=sizeof($pages)-1;

  while ($counter > 0) {
    $rand=rand(0,$max--);
    $selected[]=$pages[$rand];
    unset($pages[$rand]);
    
    $counter--;
  }

  foreach ($selected as $item) {
    $selects[]=$formatter->link_tag(_rawurlencode($item),"",$item);
  }

  if ($count > 1) {
    if (!$mode)
      return "<ul>\n<li>".join("</li>\n<li>",$selects)."</li>\n</ul>";
    if ($mode=='simple')
      return join("<br />\n",$selects)."<br />\n";
    if ($mode=='nobr')
      return join(" ",$selects);
  }
  return join("",$selects);
}

function macro_RandomQuote($formatter,$value="") {
  global $DBInfo;
  define(QUOTE_PAGE,'FortuneCookies');

  if ($value and $DBInfo->hasPage($value))
    $fortune=$value;
  else
    $fortune=QUOTE_PAGE;

  $page=$DBInfo->getPage($fortune);
  if (!$page->exists()) return '';
  $raw=$page->get_raw_body();
 
  $lines=explode("\n",$raw);

  foreach($lines as $line) {
    if (preg_match("/^\s\* (.*)$/",$line,$match))
      $quotes[]=$match[1];
  }

  if (!($count=sizeof($quotes))) return '';

  $quote=$quotes[rand(1,$count)];

  $quote=str_replace("<","&lt;",$quote);
  $quote=preg_replace($formatter->baserule,$formatter->baserepl,$quote);
  $quote=preg_replace("/(".$formatter->wordrule.")/e","\$formatter->link_repl('\\1')",
         $quote);
#  ob_start();
#  $options['nosisters']=1;
#  $formatter->send_page($quote,$options);
#  $out= ob_get_contents();
#  ob_end_clean();
#  return $out;
  return $quote;
}

function macro_UploadFile($formatter,$value='',$options='') {
  if ($options['rename']) {
    $rename=stripslashes($options['rename']);
    $extra="<input name='rename' value='$rename' />"._(": Rename")."<br />";
  }

  $url=$formatter->link_url($formatter->page->urlname);
  $form= <<<EOF
<form enctype="multipart/form-data" method='post' action='$url'>
   <input type='hidden' name='action' value='UploadFile' />
   <input type='file' name='upfile' size='30' />
   <input type='submit' value='Upload' /><br />
   $extra
   <input type='radio' name='replace' value='1' />Replace original file<br />
   <input type='radio' name='replace' value='0' checked='checked' />Rename if it already exist<br />
</form>
EOF;

   if (!in_array('UploadedFiles',$formatter->actions))
     $formatter->actions[]='UploadedFiles';

   return $form;
}

function do_uploadedfiles($formatter,$options) {
  $list=macro_UploadedFiles($formatter,$options['page'],$options);

  $formatter->send_header("",$options);
  $formatter->send_title("","",$options);

  print $list;
  $args['editable']=0;
  $formatter->send_footer($args,$options);
  return;
}

function macro_UploadedFiles($formatter,$value="",$options="") {
   global $DBInfo;

   $download='download';
   $needle="//";
   if ($options['download']) $download=$options['download'];
   if ($options['needle']) $needle=$options['needle'];

   if ($value and $value!='UploadFile') {
      $key=$DBInfo->pageToKeyname($value);
      if ($options['download'] or $key != $value)
        $prefix=$formatter->link_url($value,"?action=$download&amp;value=");
      $dir=$DBInfo->upload_dir."/$key";
   } else {
      $value=$formatter->page->urlname;
      $key=$DBInfo->pageToKeyname($formatter->page->name);
      if ($options['download'] or $key != $formatter->page->name)
        $prefix=$formatter->link_url($formatter->page->urlname,"?action=$download&amp;value=");
      $dir=$DBInfo->upload_dir."/$key";
   }
   if ($value!='UploadFile' and file_exists($dir))
      $handle= opendir($dir);
   else {
      $key='';
      $value='UploadFile';
      $dir=$DBInfo->upload_dir;
      $handle= opendir($dir);
   }

   $upfiles=array();
   $dirs=array();

   while ($file= readdir($handle)) {
      if ($file[0]=='.') continue;
      if (!$options['nodir'] and is_dir($dir."/".$file)) {
        if ($value =='UploadFile')
          $dirs[]= $DBInfo->keyToPagename($file);
      } else if (preg_match($needle,$file))
        $upfiles[]= $file;
   }
   closedir($handle);
   if (!$upfiles and !$dirs) return "<h3>No files uploaded</h3>";
   sort($upfiles); sort($dirs);

   $link=$formatter->link_url($formatter->page->urlname);
   $out="<form method='post' action='$link'>";
   $out.="<input type='hidden' name='action' value='DeleteFile' />\n";
   if ($key)
     $out.="<input type='hidden' name='value' value='$value' />\n";
   $out.="<table border='0' cellpadding='2'>\n";
   $out.="<tr><th colspan='2'>File name</th><th>Size(byte)</th><th>Date</th></tr>\n";
   $idx=1;
   foreach ($dirs as $file) {
      $link=$formatter->link_url($file,"?action=uploadedfiles",$file);
      $date=date("Y-m-d",filemtime($dir."/".$DBInfo->pageToKeyname($file)));
      $out.="<tr><td class='wiki'><input type='checkbox' name='files[$idx]' value='$file' /></td><td class='wiki'><a href='$link'>$file/</a></td><td align='right' class='wiki'>&nbsp;</td><td class='wiki'>$date</td></tr>\n";
      $idx++;
   }

   if (!$options['nodir'] and !$dirs) {
      $link=$formatter->link_tag('UploadFile',"?action=uploadedfiles&amp;value=top","..");
      $date=date("Y-m-d",filemtime($dir."/.."));
      $out.="<tr><td class='wiki'>&nbsp;</td><td class='wiki'>$link</td><td align='right' class='wiki'>&nbsp;</td><td class='wiki'>$date</td></tr>\n";
   }

   if (!$prefix) $prefix=$DBInfo->url_prefix."/".$dir."/";

   $down_mode=substr($prefix,strlen($prefix)-1) === '=';
   foreach ($upfiles as $file) {
      if ($down_mode)
        $link=str_replace("value=","value=".rawurlencode($file),$prefix);
      else
        $link=$prefix.rawurlencode($file);
      $size=filesize($dir."/".$file);
      $date=date("Y-m-d",filemtime($dir."/".$file));
      $out.="<tr><td class='wiki'><input type='checkbox' name='files[$idx]' value='$file' /></td><td class='wiki'><a href='$link'>$file</a></td><td align='right' class='wiki'>$size</td><td class='wiki'>$date</td></tr>\n";
      $idx++;
   }
   $idx--;
   $out.="<tr><th colspan='2'>Total $idx files</th><td></td><td></td></tr>\n";
   $out.="</table>\n";
   if ($DBInfo->security->is_protected("deletefile",$options))
     $out.="Password: <input type='password' name='passwd' size='10' />\n";
   $out.="<input type='submit' value='Delete selected files' /></form>\n";

   if (!$value and !in_array('UploadFile',$formatter->actions))
     $formatter->actions[]='UploadFile';
   return $out;
}

function macro_Date($formatter,$value) {
  global $DBInfo;

  $fmt=&$DBInfo->date_fmt;
  if (!$value) {
    return date($fmt);
  }
  if ($value[10]== 'T') {
    $value[10]=' ';
    $time=strtotime($value." GMT");
    return date($fmt,$time);
  }
  return date($fmt);
}

function macro_DateTime($formatter,$value) {
  global $DBInfo;

  $fmt=&$DBInfo->datetime_fmt;

  if (!$value) {
    return date($fmt);
  }
  if ($value[10]== 'T') {
    $value[10]=' ';
    $time=strtotime($value." GMT");
    return date($fmt,$time);
  }
  return date("Y/m/d\TH:i:s");
}

function macro_UserPreferences($formatter="") {
  global $DBInfo;

  $user=new User(); # get from COOKIE VARS
  if ($user->id != 'Anonymous') {
    $udb=new UserDB($DBInfo);
    $udb->checkUser(&$user);
  }
  $url=$formatter->link_url("UserPreferences");

  if ($user->id == "Anonymous")
     return <<<EOF
<form method="post" action="$url">
<input type="hidden" name="action" value="userform" />
<table border="0">
  <tr><td><b>ID</b>&nbsp;</td><td><input type="text" size="20" name="login_id" /></td></tr>
  <tr><td><b>Password</b>&nbsp;</td><td><input type="password" size="20" maxlength="12" name="login_passwd" /></td></tr>

  <tr><td></td><td><input type="submit" name="login" value="Login" /></td></tr>
        
  <tr><td><b>ID</b>&nbsp;</td><td><input type="text" size="20" name="username" value="" /></td></tr>
  <tr>
     <td><b>Password</b>&nbsp;</td><td><input type="password" size="10" maxlength="12" name="password" value="" />
     <b>Password again</b>&nbsp;<input type="password" size="10" maxlength="12" name="passwordagain" value="" /></td></tr>
  <tr><td><b>Mail</b>&nbsp;</td><td><input type="text" size="40" name="email" value="" /></td></tr>
  <tr><td></td><td>
    <input type="submit" name="save" value="make profile" /> &nbsp;
  </td></tr>
</table>
</form>
EOF;

   #$udb=new UserDB($DBInfo);
   #$user=$udb->getUser($user->id);
   $css=$user->info['css_url'];
   $name=$user->info['name'];
   $email=$user->info['email'];
   return <<<EOF
<form method="post" action="$url">
<input type="hidden" name="action" value="userform" />
<table border="0">
  <tr><td><b>ID</b>&nbsp;</td><td>$user->id</td></tr>
  <tr><td><b>Name</b>&nbsp;</td><td><input type="text" size="40" name="username" value="$name" /></td></tr>
  <tr>
     <td><b>Password</b>&nbsp;</td><td><input type="password" size="15" maxlength="8" name="password" value="" />
     <b>New password</b>&nbsp;<input type="password" size="15" maxlength="8" name="passwordagain" value="" /></td></tr>
  <tr><td><b>Mail</b>&nbsp;</td><td><input type="text" size="40" name="email" value="$email" /></td></tr>
  <tr><td><b>CSS URL </b>&nbsp;</td><td><input type="text" size="40" name="user_css" value="$css" /><br />("None" for disable CSS)</td></tr>
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

  $out="<table border='0' cellspacing='2' cellpadding='0'>";
  foreach (array_keys($DBInfo->interwiki) as $wiki) {
    $href=$DBInfo->interwiki[$wiki];
    if (strpos($href,'$PAGE') === false)
      $url=$href.'RecentChanges';
    else {
      $url=str_replace('$PAGE','index',$href);
      #$href=$url;
    }
    $icon=strtolower($wiki)."-16.png";
    $out.="<tr><td><tt><img src='$DBInfo->imgs_dir/$icon' align='middle' alt='$wiki:'><a href='$url'>$wiki</a></tt></td><td><tt>";
    $out.="<a href='$href'>$href</a></tt></td></tr>\n";
  }
  $out.="</table>\n";
  return $out;
}


function toutf8($uni) {
  $utf[0]=0xe0 | ($uni >> 12);
  $utf[1]=0x80 | (($uni >> 6) & 0x3f);
  $utf[2]=0x80 | ($uni & 0x3f);
  return chr($utf[0]).chr($utf[1]).chr($utf[2]);
}

function get_key($name) {
  global $DBInfo;
  if (preg_match('/[a-z0-9]/i',$name[0])) {
     return strtoupper($name[0]);
  }
  $utf="";
  if (function_exists ("iconv")) {
    # XXX php 4.1.x did not support unicode sting.
    $utf=iconv($DBInfo->charset,'utf-8',$name);
    $name=$utf;
  }

  if ($utf or $DBInfo->charset=='utf-8') {
    if ((ord($name[0]) & 0xF0) == 0xE0) { # Now only 3-byte UTF-8 supported
       #$uni1=((ord($name[0]) & 0x0f) <<4) | ((ord($name[1]) & 0x7f) >>2);
       $uni1=((ord($name[0]) & 0x0f) <<4) | (($name[1] & 0x7f) >>2);
       $uni2=((ord($name[1]) & 0x7f) <<6) | (ord($name[2]) & 0x7f);

       $uni=($uni1<<8)+$uni2;
       # Hangul Syllables
       if ($uni>=0xac00 && $uni<=0xd7a3) {
         $ukey=0xac00 + (int)(($uni - 0xac00) / 588) * 588;
         $ukey=toutf8($ukey);
         if ($utf)
           return iconv('utf-8',$DBInfo->charset,$ukey);
         return $ukey;
       }
    }
    return '~';
  } else {
    if (preg_match('/[a-z0-9]/i',$name[0])) {
       return strtoupper($name[0]);
    }
    # php does not have iconv() EUC-KR assumed
    # (from NoSmoke monimoin)
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

  $pname=_preg_escape($args);

  $metawiki=$opts['metawiki'];

  if (strlen($pname) < 3) {
    $opts['msg'] = _('Use more specific text');
    return '';
  }

  $s_re="^[A-Z][a-z0-9]+";
  $e_re="[A-Z][a-z0-9]+$";

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
    preg_match("/^(.{2,4})/",$args,$match);
    $s_len=strlen($match[1]);
    $start=_preg_escape($match[1]);
  }

  if (!$end) {
    $end=substr($args,$s_len);
    preg_match("/(.{2,6})$/",$end,$match);
    $end=$match[1];
    $e_len=strlen($end);
    if ($e_len < 2) $end="";
    else $end=_preg_escape($end);
  }

  $starts=array();
  $ends=array();
  $likes=array();

  if (!$metawiki) {
    $pages = $DBInfo->getPageLists();
  } else {
    if (!$end) $needle=$start;
    else $needle="$start|$end";
    $pages = $DBInfo->metadb->getLikePages($needle);
  }
  
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
    ksort($likes);

    $out.="<h3>"._("These pages share a similar word...")."</h3>";
    $out.="<ol>\n";
    while (list($pagename,$i) = each($likes)) {
      $pageurl=_rawurlencode($pagename);
      $out.= '<li>' . $formatter->link_tag($pageurl,"",$pagename,"tabindex='$idx'")."</li>\n";
      $idx++;
    }
    $out.="</ol>\n";
    $hits=count($likes);
  }
  if ($starts || $ends) {
    ksort($starts);

    $out.="<h3>"._("These pages share an initial or final title word...")."</h3>";
    $out.="<table border='0' width='100%'><tr><td width='50%' valign='top'>\n<ol>\n";
    while (list($pagename,$i) = each($starts)) {
      $pageurl=_rawurlencode($pagename);
      $out.= '<li>' . $formatter->link_tag($pageurl,"",$pagename,"tabindex='$idx'")."</li>\n";
      $idx++;
    }
    $out.="</ol></td>\n";

    ksort($ends);

    $out.="<td width='50%' valign='top'><ol>\n";
    while (list($pagename,$i) = each($ends)) {
      $pageurl=_rawurlencode($pagename);
      $out.= '<li>' . $formatter->link_tag($pageurl,"",$pagename,"tabindex='$idx'")."</li>\n";
      $idx++;
    }
    $out.="</ol>\n</td></tr></table>\n";
    $opts['extra']=_("If you can't find this page, ");
    $hits+=count($starts) + count($ends);
  }

  if (!$hits) {
    $out.="<h3>"._("No similar pages found")."</h3>";
    $opts['extra']=_("You are strongly recommened to find it in MetaWikis. ");
  }

  $opts['msg'] = sprintf(_("Like \"%s\""),$args);

  $tag=$formatter->link_to("?action=LikePages&amp;metawiki=1",_("Search all MetaWikis"));
  $opts['extra'].="$tag (Slow Slow)<br />";

  return $out;
}


function macro_PageCount($formatter="") {
  global $DBInfo;

  return $DBInfo->getCounter();
}

function macro_PageHits($formatter="",$value) {
  global $DBInfo;

  if (!$DBInfo->use_counter) return "[[PageHits is not activated. set \$use_counter=1; in the config.php]]";

  $pages = $DBInfo->getPageLists();
  sort($pages);
  $hits= array();
  foreach ($pages as $page) {
    $hits[$page]=$DBInfo->counter->pageCounter($page);
  }

  if ($value=='reverse' or $value[0]=='r') asort($hits);
  else arsort($hits);
  while(list($name,$hit)=each($hits)) {
    if (!$hit) $hit=0;
    $name=$formatter->link_tag(_rawurlencode($name),"",$name);
    $out.="<li>$name . . . . [$hit]</li>\n";
  }
  return "<ol>\n".$out."</ol>\n";
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
    $links=preg_replace("/(".$formatter->wordrule.")/e","\$formatter->link_repl('\\1')",$links);
    $out.=$links."</li>\n";
  }
  $out.="</ul>\n";
  return $out;
}

function macro_WantedPages($formatter="",$options="") {
  global $DBInfo;
  $pages = $DBInfo->getPageLists();

  $cache=new Cache_text("pagelinks");
  foreach ($pages as $page) {
    $p= new WikiPage($page);
    $f= new Formatter($p);
    $links=$f->get_pagelinks();
    if ($links) {
      $lns=explode("\n",$links);
      foreach($lns as $link) {
        if (!$link or $DBInfo->hasPage($link)) continue;
        if ($link and !$wants[$link])
          $wants[$link]="[\"$page\"]";
        else $wants[$link].=" [\"$page\"]";
      }
    }
  }

  asort($wants);
  $out="<ul>\n";
  while (list($name,$owns) = each($wants)) {
    $owns=preg_replace("/(".$formatter->wordrule.")/e","\$formatter->link_repl('\\1')",$owns);
    $out.="<li>".$formatter->link_repl($name). ": $owns</li>";
  }
  $out.="</ul>\n";
  return $out;
}


function macro_PageList($formatter,$arg="") {
  global $DBInfo;

  preg_match("/([^,]*)(\s*,\s*)?(.*)?$/",$arg,$match);
  if ($match[1]=='date') {
    $options['date']=1;
    $arg='';
  } else if ($match) {
    $arg=$match[1];
    $options=array();
    if ($match[3]) $options=explode(",",$match[3]);
    if (in_array('date',$options)) $options['date']=1;
    else if ($arg and (in_array('metawiki',$options) or in_array('m',$options)))
      $options['metawiki']=1;
  }
  $needle=_preg_search_escape($arg);

  $test=@preg_match("/$needle/","",$match);
  if ($test === false) {
    # show error message
    return "[[PageList(<font color='red'>Invalid \"$arg\"</font>)]]";
  }

  if ($options['date']) {
    $all_pages = $DBInfo->getPageLists($options);
  } else {
    if ($options['metawiki'])
      $all_pages = $DBInfo->metadb->getLikePages($needle);
    else
      $all_pages = $DBInfo->getLikePages($needle);
#     $all_pages= array_unique(array_merge($meta_pages,$all_pages));
  }
#  $all_pages = $DBInfo->getPageLists($options);

  $hits=array();

  if ($options[date]) {
    if ($needle) {
      while (list($pagename,$mtime) = @each ($all_pages)) {
        preg_match("/$needle/",$pagename,$matches);
        if ($matches) $hits[$pagename]=$mtime;
      }
    } else $hits=$all_pages;
    arsort($hits);
    while (list($pagename,$mtime) = @each ($hits)) {
      $out.= '<li>'.$formatter->link_tag(_rawurlencode($pagename),"",$pagename).". . . . [".date("Y-m-d",$mtime)."]</li>\n";
    }
    $out="<ol>\n".$out."</ol>\n";
  } else {
    foreach ($all_pages as $page) {
      preg_match("/$needle/",$page,$matches);
      if ($matches) $hits[]=$page;
    }
    sort($hits);
    foreach ($hits as $pagename) {
      $out.= '<li>' . $formatter->link_tag(_rawurlencode($pagename),"",$pagename)."</li>\n";
    }
    $out="<ul>\n".$out."</ul>\n";
  }

  return $out;
}

function macro_TitleIndex($formatter="") {
  global $DBInfo;

  if ($formatter->group) {
    $group_pages = $DBInfo->getLikePages($formatter->group,1);
    foreach ($group_pages as $page)
      $all_pages[]=str_replace($formatter->group,"",$page);
  } else
    $all_pages = $DBInfo->getPageLists();
  natcasesort($all_pages);

  $key=-1;
  $out="";
  $keys=array();

#  if ($DBInfo->use_titlecache)
#    $cache=new Cache_text('title');

  foreach ($all_pages as $page) {
    $pkey=get_key($page);
#   $key=strtoupper($page[0]);
    if ($key != $pkey) {
       if ($key !=-1)
          $out.="</ul>";
       $key=$pkey;
       $keys[]=$key;
       $out.= "<a name='$key' /><h3><a href='#top'>$key</a></h3>\n";
       $out.= "<ul>";
    }

    #
#    if ($DBInfo->use_titlecache and $cache->exists($page))
#      $title=$cache->fetch($page);
#    else
      $title=$page;

    $out.= '<li>' . $formatter->word_repl($page,$title)."</li>\n";
  }
  $out.= "</ul>\n";

  $index="";
  foreach ($keys as $key)
    $index.= "| <a href='#$key'>$key</a> ";
  $index[0]=" ";
  
  return "<center><a name='top' />$index</center>\n$out";
}

function macro_Icon($formatter,$value='',$extra='') {
  global $DBInfo;

  $out=$DBInfo->imgs_dir."/$value";
  $out="<img src='$out' border='0' alt='icon' align='middle' />";
  return $out;
}

function do_RecentChanges($formatter,$options='') {
  $formatter->send_header("",$options);
  print "<div id='wikiBody'>";
  print macro_RecentChanges($formatter,'nobookmark,moztab',array('target'=>'_content'));
  print "</div></body></html>";
  return;
}

function macro_RecentChanges($formatter,$value='',$options='') {
  global $DBInfo;
  define(MAXSIZE,10000);
  define(DEFSIZE,6000);
  $checknew=1;

  $template_bra="";
  $template=
  '$out.= "$icon&nbsp;&nbsp;$title $date . . . . $user $count $extra<br />\n";';
  $template_cat="";
  $use_day=1;

  if ($options['target']) $target="target='$options[target]'";

  $date_fmt='D d M Y';

  preg_match("/(\d+)?(?:\s*,\s*)?(.*)?$/",$value,$match);
  if ($match) {
    $size=(int) $match[1];
    $args=explode(",",$match[2]);

    if (preg_match("/^[\/\-:aABdDFgGhHiIjmMOrSTY]+$/",$args[0]))
      $date_fmt=$args[0];

    if (in_array ("quick", $args)) $quick=1;
    if (in_array ("nonew", $args)) $checknew=0;
    if (in_array ("showhost", $args)) $showhost=1;
    if (in_array ("comment", $args)) $comment=1;
    if (in_array ("nobookmark", $args)) $nobookmark=1;
    if (in_array ("simple", $args)) {
      $use_day=0;
      $template=
  '$out.= "$icon&nbsp;&nbsp;$title @ $day $date by $user $count $extra<br />\n";';
    }
    if (in_array ("moztab", $args)) {
      $use_day=1;
      $template=
  '$out.= "<li>$title $date</li>\n";';
    }
    if (in_array ("table", $args)) {
      $bra="<table border='0' cellpadding='0' cellspading='0' width='100%'>";
      $template=
  '$out.= "<tr><td nowrap=\'nowrap\' width=\'2%\'>$icon</td><td width=\'40%\'>$title</td><td width=\'15%\'>$date</td><td>$user $count $extra</td></tr>\n";';
      $cat="</table>";
      $cat0="";
    }
  }
  if ($size > MAXSIZE) $size=DEFSIZE;

  $user=new User(); # retrive user info
  if ($user->id != 'Anonymous') {
    $udb=new UserDB($DBInfo);
    $udb->checkUser(&$user);
  }

  if ($user->id == 'Anonymous')
    $bookmark= $user->bookmark;
  else {
    $bookmark= $user->info['bookmark'];
  }
  if (!$bookmark) $bookmark=time();

  if ($quick)
    $lines= $DBInfo->editlog_raw_lines($size,1);
  else
    $lines= $DBInfo->editlog_raw_lines($size);
    
  $time_current= time();
  $secs_per_day= 60*60*24;
  $days_to_show= 30;
  $time_cutoff= $time_current - ($days_to_show * $secs_per_day);

  foreach ($lines as $line) {
    $parts= explode("\t", $line,3);
    $page_key= $parts[0];
    $ed_time= $parts[2];

    $day = date('Ymd', $ed_time);
    if ($day != $ratchet_day) {
      $ratchet_day = $day;
      unset($logs);
    }

    if ($editcount[$page_key]) {
      if ($logs[$page_key]) {
        $editcount[$page_key]++;
        continue;
      }
      continue;
    }
    $editcount[$page_key]= 1;
    $logs[$page_key]= 1;
  }
  unset($logs);

  $out="";
  $ratchet_day= FALSE;
  $br="";
  foreach ($lines as $line) {
    $parts= explode("\t", $line);
    $page_key=$parts[0];

    if ($logs[$page_key]) continue;

    $page_name= $DBInfo->keyToPagename($parts[0]);
    $addr= $parts[1];
    $ed_time= $parts[2];
    $user= $parts[4];
    $log= stripslashes($parts[5]);
    $act= rtrim($parts[6]);

    if ($ed_time < $time_cutoff)
      break;

    if ($formatter->group and !preg_match("/^$formatter->group/",$page_name))
      continue;

    $day = date('Y-m-d', $ed_time);
    if ($use_day and $day != $ratchet_day) {
      $out.=$cat0;
      $out.=sprintf("%s<font size='+1'>%s </font> <font size='-1'>",
            $br, date($date_fmt, $ed_time));
      if (!$nobookmark)
        $out.='['.$formatter->link_tag($formatter->page->urlname,
                                 "?action=bookmark&amp;time=$ed_time",
                                 _("set bookmark"))."]</font><br />\n";
      $ratchet_day = $day;
      $br="<br />";
      $out.=$bra;
      $cat0=$cat;
    } else
      $day=$formatter->link_to("?action=bookmark&amp;time=$ed_time",$day);

    $pageurl=_rawurlencode($page_name);

    if (!$DBInfo->hasPage($page_name))
      $icon= $formatter->link_tag($pageurl,"?action=diff",$formatter->icon[del]);
    else if ($ed_time > $bookmark) {
      $icon= $formatter->link_tag($pageurl,"?action=diff&amp;date=$bookmark",$formatter->icon[updated]);
      if ($checknew) {
        $p= new WikiPage($page_name);
        $v= $p->get_rev($bookmark);
        if (!$v)
          $icon=
            $formatter->link_tag($pageurl,"?action=info",$formatter->icon['new']);
      }
    } else
      $icon= $formatter->link_tag($pageurl,"?action=diff",$formatter->icon[diff]);

    $title= preg_replace("/((?<=[a-z0-9])[A-Z][a-z0-9])/"," \\1",$page_name);
    $title= $formatter->link_tag($pageurl,"",$title,$target);

    if (! empty($DBInfo->changed_time_fmt))
      $date= date($DBInfo->changed_time_fmt, $ed_time);

    if ($DBInfo->show_hosts) {
      if ($showhost && $user == 'Anonymous')
        $user= $addr;
      else {
        if ($DBInfo->hasPage($user)) {
          $user= $formatter->link_tag(_rawurlencode($user),"",$user);
        } else
          $user= $user;
      }
    }
    $count=""; $extra="";
    if ($editcount[$page_key] > 1)
      $count=" [".$editcount[$page_key]." changes]";
    if ($comment && $log)
      $extra="&nbsp; &nbsp; &nbsp; <font size='-1'>$log</font>";

    eval($template);

    $logs[$page_key]= 1;
  }
  return $out.$cat0;
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
    if ($foots)
      return "<br/><tt class='wiki'>----</tt><br/>\n$foots";
    return '';
  }

  $formatter->foot_idx++;
  $idx=$formatter->foot_idx;

  $text="[$idx&#093";
  $fnidx="fn".$idx;
  if ($value[0] == "*") {
#    $dum=explode(" ",$value,2); XXX
    $p=strrpos($value,'*')+1;
    $text=substr($value,0,$p);
    $value=substr($value,$p);
  } else if ($value[0] == "[") {
    $dum=explode("]",$value,2);
    if (trim($dum[1])) {
       $text=$dum[0]."&#093;"; # make a text as [Alex77]
       $fnidx=substr($dum[0],1);
       $formatter->foot_idx--; # undo ++.
       if (0 === strcmp($fnidx , (int)$fnidx)) $fnidx="fn$fnidx";
       $value=$dum[1]; 
    } else if ($dum[0]) {
       $text=$dum[0]."]";
       $fnidx=substr($dum[0],1);
       $formatter->foot_idx--; # undo ++.
       if (0 === strcmp($fnidx , (int)$fnidx)) $fnidx="fn$fnidx";
       return "<tt class='foot'><a href='#$fnidx'>$text</a></tt>";
    }
  }
  $formatter->foots[]="<tt class='foot'>&#160;&#160;&#160;".
                      "<a id='$fnidx' name='$fnidx'/>".
                      "<a href='#r$fnidx'>$text</a>&#160;</tt> ".
                      "$value<br/>";
  return "<tt class='foot'><a id='r$fnidx' name='r$fnidx'/><a href='#$fnidx'>$text</a></tt>";
}

function macro_TableOfContents($formatter="",$value="") {
 global $DBInfo;
 $head_num=1;
 $head_dep=0;
 $TOC="\n<div class='toc'><a name='toc' id='toc' /><dl><dd><dl>\n";

 $formatter->toc=1;
 $baseurl='';
 if ($value and $DBInfo->hasPage($value)) {
   $p=$DBInfo->getPage($value);
   $lines=explode("\n",$p->get_raw_body());
   $baseurl=$formatter->link_url(_urlencode($value));
   $formatter->page=&$p;
 } else
   $lines=explode("\n",$formatter->page->get_raw_body());
 foreach ($lines as $line) {
   $line=preg_replace("/\n$/", "", $line); # strip \n
   preg_match("/(?<!=)(={1,5})\s(#?)(.*)\s+(={1,5})$/",$line,$match);

   if (!$match) continue;

   $dep=strlen($match[1]);
   if ($dep != strlen($match[4])) continue;
   $head=str_replace("<","&lt;",$match[3]);
   # strip some basic wikitags
   # $formatter->baserepl,$head);
   $head=preg_replace($formatter->baserule,"\\1",$head);
   $head=preg_replace("/\[\[.*\]\]/","",$head);
   $head=preg_replace("/(".$formatter->wordrule.")/e","\$formatter->link_repl('\\1')",$head);

   if (!$depth_top) { $depth_top=$dep; $depth=1; }
   else {
     $depth=$dep - $depth_top + 1;
     if ($depth <= 0) $depth=1;
   }

#   $depth=$dep;
#   if ($dep==1) $depth++; # depth 1 is regarded same as depth 2
#   $depth--;

   $num="".$head_num;
   $odepth=$head_dep;
   $open="";
   $close="";

   if ($match[2]) {
      # reset TOC numberings
      $dum=explode(".",$num);
      $i=sizeof($dum);
      for ($j=0;$j<$i;$j++) $dum[$j]=1;
      $dum[$i-1]=0;
      $num=join($dum,".");
      if ($prefix) $prefix++;
      else $prefix=1;
   }

   if ($odepth && ($depth > $odepth)) {
      $open.="<dd><dl>\n";
      $num.=".1";
   } else if ($odepth) {
      $dum=explode(".",$num);
      $i=sizeof($dum)-1;
      while ($depth < $odepth && $i > 0) {
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

   if ($baseurl)
     $TOC.=$close.$open."<dt><a href='$baseurl#s$prefix-$num'>$num</a> $head</dt>\n";
   else
     $TOC.=$close.$open."<dt><a id='toc$prefix-$num' name='toc$prefix-$num' /><a href='#s$prefix-$num'>$num</a> $head</dt>\n";

  }

  if ($TOC) {
     $close="";
     $depth=$head_dep;
     while ($depth>1) { $depth--;$close.="</dl></dd>\n"; };
     return $TOC.$close."</dl></dd></dl>\n</div>\n";
  }
  else return "";
}

function macro_FullSearch($formatter="",$value="", $opts=array()) {
  global $DBInfo;
  $needle=$value;
  if ($value === true) {
    $needle = $value = $formatter->page->name;
  } else {
    # for MoinMoin compatibility with [[FullSearch("blah blah")]]
    $needle = preg_replace("/^(\'|\")(.*)(\'|\")/","\\2",$value);
  }

  $url=$formatter->link_url($formatter->page->urlname);
  $fneedle=str_replace('"',"&#34;",$needle); # XXX

  $form= <<<EOF
<form method='get' action='$url'>
   <input type='hidden' name='action' value='fullsearch' />
   <input name='value' size='30' value='$fneedle' />
   <input type='submit' value='Go' /><br />
   <input type='checkbox' name='context' value='20' checked='checked' />Display context of search results<br />
   <input type='checkbox' name='backlinks' value='1' />Search BackLinks only<br />
   <input type='checkbox' name='case' value='1' />Case-sensitive searching<br />
   </form>
EOF;

  if (!$needle) { # or blah blah
     $opts['msg'] = 'No search text';
     return $form;
  }
  $needle=_preg_search_escape($needle);
  $test=@preg_match("/$needle/","",$match);
  if ($test === false) {
     $opts['msg'] = sprintf(_("Invalid search expression \"%s\""), $needle);
     return $form;
  }

  $hits = array();
  $pages = $DBInfo->getPageLists();
  $pattern = '/'.$needle.'/';
  if ($opts['case']) $pattern.="i";

  if ($opts['backlinks']) {
     $opts['context']=0; # turn off context-matching
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
       #$count = count(preg_split($pattern, $body))-1;
       $count = preg_match_all($pattern, $body,$matches);
       if ($count) {
         $hits[$page_name] = $count;
         # search matching contexts
         $contexts[$page_name] = find_needle($body,$needle,$opts['context']);
       }
     }
  }
  arsort($hits);

  $out.= "<ul>";
  reset($hits);
  $idx=1;
  while (list($page_name, $count) = each($hits)) {
    $out.= '<li>'.$formatter->link_tag(_rawurlencode($page_name),
          "?action=highlight&amp;value=$value",
          $page_name,"tabindex='$idx'");
    $out.= ' . . . . ' . $count . (($count == 1) ? ' match' : ' matches');
    $out.= $contexts[$page_name];
    $out.= "</li>\n";
    $idx++;
  }
  $out.= "</ul>\n";

  $opts['hits']= count($hits);
  $opts['all']= count($pages);
  return $out;
}


function macro_TitleSearch($formatter="",$needle="",$opts=array()) {
  global $DBInfo;

  $url=$formatter->link_url($formatter->page->urlname);

  if (!$needle) {
    $opts['msg'] = _("Use more specific text");
    return "<form method='get' action='$url'>
      <input type='hidden' name='action' value='titlesearch' />
      <input name='value' size='30' value='$needle' />
      <input type='submit' value='Go' />
      </form>";
  }
  $opts['msg'] = sprintf(_("Title search for \"%s\""), $needle);
  $needle=_preg_search_escape($needle);
  $test=@preg_match("/$needle/","",$match);
  if ($test === false) {
    $opts['msg'] = sprintf(_("Invalid search expression \"%s\""), $needle);
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
    if ($opts['linkto'])
      $out.= '<li>' . $formatter->link_to("$opts[linkto]$pagename",$pagename,"tabindex='$idx'")."</li>\n";
    else
      $out.= '<li>' . $formatter->link_tag(_rawurlencode($pagename),"",$pagename,"tabindex='$idx'")."</li>\n";
    $idx++;
  }

  $out.="</ul>\n";
  $opts['hits']= count($hits);
  $opts['all']= count($pages);
  return $out;
}

function macro_GoTo($formatter="",$value="") {
  $url=$formatter->link_url($formatter->page->urlname);
  return "<form method='get' action='$url'>
    <input type='hidden' name='action' value='goto' />
    <input name='value' size='30' value='$value' />
    <input type='submit' value='Go' />
    </form>";
}

function macro_SystemInfo($formatter="",$value="") {
  global $_revision,$_release;

  $version=phpversion();
  $uname=php_uname();
  list($aversion,$dummy)=explode(" ",$_SERVER['SERVER_SOFTWARE'],2);

  $pages=macro_PageCount($formatter);
   
  return <<<EOF
<table border='0' cellpadding='5'>
<tr><th width='200'>PHP Version</th> <td>$version ($uname)</td></tr>
<tr><th>MoniWiki Version</th> <td>Release $_release [$_revision]</td></tr>
<tr><th>Apache Version</th> <td>$aversion</td></tr>
<tr><th>Number of Pages</th> <td>$pages</td></tr>
</table>
EOF;
}

function processor_html($formatter="",$value="") {
  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);
  return $value;
}

function processor_plain($formatter,$value) {
  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);
  $value=str_replace('<','&lt;',$value);
  return "<pre class='code'>$value</pre>";
}


function processor_php($formatter="",$value="") {
  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);
  $php=$value;
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

?>
