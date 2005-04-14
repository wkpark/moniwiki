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
  return preg_replace("/([^a-z0-9\/\?\.\+~#&:;=%\-]{1})/ie","'%'.strtoupper(dechex(ord('\\1')))",$url);
}

function _stripslashes($str) {
  return get_magic_quotes_gpc() ? stripslashes($str):$str;
}

function qualifiedUrl($url) {
  if (substr($url,0,7)=="http://")
    return $url;
  return "http://$_SERVER[HTTP_HOST]$url";
}

function find_needle($body,$needle,$exclude='',$count=0) {
  if (!$body) return '';
  $lines=explode("\n",$body);
  $out="";
  $matches=preg_grep("/($needle)/i",$lines);
  if ($exclude)
    if (preg_grep("/($exclude)/i",$matches)) return '';

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

function normalize_word($word,$group='',$pagename='',$nogroup=0,$islink=1) {
  if ($word[0]=='[') $word=substr($word,1,-1);
  if ($word[0]=='"') $word=substr($word,1,-1);
  $page=$word;

  # User namespace extension
  if ($page[0]=='~' and ($p=strpos($page,'/'))) {
    # change ~User/Page to User~Page
    $main_page=$page;
    $page=$text=substr($page,1,$p-1).'~'.substr($page,$p+1);
    return array($page,$text,$main_page);
  }
    
  if ($page[0]=='.' and preg_match('/^(\.{1,2})\//',$page,$match)) {
    if ($match[1] == '..') {
      if (($pos = strrpos($pagename,'/')) > 0) {
        $upper=substr($pagename,0,$pos);
        $page=substr($page,2);
        if ($page == '/') $page=$upper;
        else $page=$upper.$page;
      } else {
        $page=substr($page,3);
        if ($page == '') $page=substr($pagename,strlen($group));
        else if ($group) $page=$group.$page;
      }
    } else {
      $page=substr($page,1);
      if ($page == '/') $page='';
      $page=$pagename.$page;
    }
    return array($page,$text,$main_page);
  }

  if ($page[0]=='/') { # SubPage
    $page=$pagename.$page;
  } else if ($islink && $tok=strtok($page,'.')) {
#    print $tok;
    if ($tok=='Main') {
      # Main.MoniWiki => MoniWiki
      $page=$text=strtok('');
      return array($page,$text,$main_page);
    } else if (strpos($tok,'~') === false and strpos($tok,'/') === false) {
      # Ko~Hello.World =x=> Ko~Hello~World
      # Ko.Hello => Ko~Hello

      #$page=preg_replace('/\./','~',$page,1);
      $npage=preg_replace('/(?<!\\\\)\./','~',$page,1);
      if ($npage == $page) $page=preg_replace('/(\\\.)/','.',$page,1);
      else $page=$npage;

      $text=$main_page=strtok('');
    }
  }
  if (!$nogroup and $group and !strpos($page,'~')) {
    # UserNameSpace pages: e.g.) Ko~MoniWiki etc.
    if ($page[0]=='/') {
      # /MoniWiki => MoniWiki
      $page=$text=substr($page,1);
    } else {
      $main_page=$text=$page;
      $page=$group.$page;
    }
  }

  if (preg_match("/^wiki:/", $page)) { # wiki:
    $text=$page=substr($page,6);
 
    if (strpos($page,' ')) { # have a space ?
      list($page,$text)= explode(' ',$page,2);
    }
 
    if ($page[0]=='/') $page= $pagename.$page;
  }

  return array($page,$text,$main_page);
}

function get_title($page) {
  global $DBInfo;
  if ($DBInfo->use_titlecache) {
    $cache=new Cache_text('title');
    if ($cache->exists($page)) $title=$cache->fetch($page);
    else $title=$page;
  } else
    $title=$page;

  #return preg_replace("/((?<=[a-z0-9]|[B-Z]{2}|A)([A-Z][a-z]|A))/"," \\1",$title);
  return preg_replace("/((?<=[a-z0-9]|[B-Z]{2})([A-Z][a-z]))/"," \\1",$title);
}

function getTicket($seed) {
  global $DBInfo;
  # make a ticket based on the variables in the config.php
  $config=getConfig("config.php");
  foreach ($config as $seed) {
    if (!is_array($seed))
      $md5.=md5($seed);
  }
  if ($DBInfo->strict_usercheck)
    # change user's ticket
    return md5($md5.$seed.time().$_SERVER['HTTP_HOST']);
  return md5($md5.$seed);
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

function toutf8($uni) {
  $utf[0]=0xe0 | ($uni >> 12);
  $utf[1]=0x80 | (($uni >> 6) & 0x3f);
  $utf[2]=0x80 | ($uni & 0x3f);
  return chr($utf[0]).chr($utf[1]).chr($utf[2]);
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
      if (preg_match('/^wu\-([^\.]+)$/', $file,$match))
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
                  "scrapped_pages","quicklinks","theme","ticket","eticket");

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

  function checkUser(&$user) {
    $tmp=$this->getUser($user->id);
    if ($tmp->info['ticket'] != $user->ticket) {
      $user->id='Anonymous';
      return 1;
    }
    $user=$tmp;
    return 0;
  }

  function getUser($id) {
    if ($this->_exists($id)) {
       $data=file("$this->user_dir/wu-$id");
    } else {
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
    if ($this->_exists($id)) {
       unlink("$this->user_dir/wu-$id");
    }
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
     $this->trail=_stripslashes($_COOKIE['MONI_TRAIL']);
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
     if ($this->id == "Anonymous") return false;
     $ticket=getTicket($this->id);
     $this->ticket=$ticket;
     # set the fake cookie
     $_COOKIE['MONI_ID']=$ticket.'.'.$this->id;
     return "Set-Cookie: MONI_ID=".$ticket.'.'.$this->id.'; expires='.gmdate('l, d-M-Y',time()+60*60*24*30).'; Path='.get_scriptname();
  }

  function unsetCookie() {
     # set the fake cookie
     $_COOKIE['MONI_ID']="Anonymous";
     return "Set-Cookie: MONI_ID=".$this->id."; expires=Tuesday, 01-Jan-1999 12:00:00 GMT; Path=".get_scriptname();
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

    if (strlen($passwd)<4)
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
    if (!$this->info['email'] or !$this->info['subscribed_pages']) return false;
    $page_list=_preg_search_escape($this->info['subscribed_pages']);
    if (!trim($page_list)) return false;
    $page_lists=explode("\t",$page_list);
    $page_rule='^'.join("$|^",$page_lists).'$';
    if (preg_match('/('.$page_rule.')/',$pagename))
      return true;
    return false;
  }
}


function do_highlight($formatter,$options) {

  $formatter->send_header("",$options);
  $formatter->send_title("","",$options);

  $expr= _stripslashes($options['value']);
#  $expr= implode('|',preg_split('/\s+/',$expr));

  $formatter->highlight=$expr;
  $formatter->send_page();
  $args['editable']=1;
  $formatter->send_footer($args,$options);
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
    $f=new Formatter($p);

    ob_start();
    $f->send_page(rtrim($form),$options);
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
    return;
  }
  $formatter->send_header("",$options);
  if ($options['section'])
    $sec=' (Section)';
  $formatter->send_title(sprintf(_("Edit %s"),$options['page']).$sec,"",$options);
  print macro_EditText($formatter,$value,$options);
  $formatter->send_footer($args,$options);
}

function _get_sections($body) {
  $chunks=preg_split("/(\{\{\{.+?\}\}\})/s",$body,-1, PREG_SPLIT_DELIM_CAPTURE);
  $sects=array();
  $sects[]='';
  for ($jj=0,$ii=0,$ss=count($chunks); $ii<$ss; $ii++) {
    if (($ii%2)) {
      $sec=array_pop($sects);
      $sects[]=$sec.$chunks[$ii];
      continue;
    }
    $parts=array();
    $parts=preg_split("/^(\s*={1,5}\s#?.*\s+={1,5}\s?)$/m",$chunks[$ii],
      -1, PREG_SPLIT_DELIM_CAPTURE);
    for ($j=0,$i=0,$s=count($parts); $i<$s; $i++) {
      if (!($i%2)) {
        $sec=array_pop($sects);
        $sects[]=$sec.$parts[$i];
        continue;
      }
      if (preg_match("/^\s*(={1,5})\s#?.*\s+\\1\s?/",$parts[$i])) {
        $sects[]=$parts[$i];
      } else {
        $sec=array_pop($sects);
        $sects[]=$sec.$parts[$i];
      }
    }
  }
  return $sects;
}

function macro_Edit($formatter,$value,$options='') {
  global $DBInfo;

  $COLS_MSIE= 80;
  $COLS_OTHER= 85;

  $edit_rows=$DBInfo->edit_rows ? $DBInfo->edit_rows: 16;
  $cols= preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT']) ? $COLS_MSIE : $COLS_OTHER;

  $rows= $options['rows'] > 5 ? $options['rows']: $edit_rows;
  $rows= $rows < 60 ? $rows: $edit_rows;
  $cols= $options['cols'] > 60 ? $options['cols']: $cols;

  $text= $options['savetext'];

  $args= explode(',',$value);
  if (in_array('nohints',$args)) $options['nohints']=1;
  if (in_array('nomenu',$args)) $options['nomenu']=1;

  $preview= $options['preview'];

  if (!$formatter->page->exists()) {
    $options['linkto']="?action=edit&amp;template=";
    $form = '<br />'._("Use one of the following templates as an initial release :\n");
    $form.= macro_TitleSearch($formatter,$DBInfo->template_regex,$options);
    $form.= _("To create your own templates, add a page with a 'Template' suffix.")."\n<br />\n";
  }

  if ($options['conflict'])
    $extra='<input type="submit" name="button_merge" value="Merge" />';
  if ($options['section'])
    $hidden='<input type="hidden" name="section" value="'.$options['section'].
            '" />';

  # make a edit form
  $form.= "<a id='editor' name='editor' />\n";

  if ($options['page'])
    $previewurl=$formatter->link_url(_rawurlencode($options['page']),'#preview');
  else
    $previewurl=$formatter->link_url($formatter->page->urlname,'#preview');

  $menu= '';
  if ($preview)
    $menu= $formatter->link_to('#preview',_("Skip to preview"));
  else {
    $menu= $formatter->link_to("?action=edit&amp;rows=".($rows-3),_("ReduceEditor"));
    $menu.= ' | '.$formatter->link_to("?action=edit&amp;rows=".($rows+3),_("EnlargeEditor"));
  }

  if (!$options['nomenu']) {
    $menu.= " | ".$formatter->link_tag('InterWiki',"",_("InterWiki"));
    $menu.= " | ".$formatter->link_tag('HelpOnEditing',"",_("HelpOnEditing"));
  }

  $form.=$menu;
  $form.= sprintf('<form name="editform" method="post" action="%s">', $previewurl);
  if ($text) {
    $raw_body = str_replace("\r\n", "\n", $text);
  } else if ($formatter->page->exists()) {
    $raw_body = str_replace("\r\n", "\n", $formatter->page->_get_raw_body());
    if (isset($options['section'])) {
      $sections= _get_sections($raw_body);
      if ($sections[$options['section']])
        $raw_body = $sections[$options['section']];
      #else ignore
    }
  } else if ($options['template']) {
    $p= new WikiPage($options['template']);
    $raw_body = str_replace("\r\n", "\n", $p->get_raw_body());
  } else {
    if (strpos($options['page'],' ') > 0) {
      $raw_body="#title $options[page]\n";
      $options['page']='['.$options['page'].']';
    } else $raw_body='';
    $raw_body.= sprintf(_("Describe %s here"), $options['page']);
  }


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

  if ($DBInfo->use_minoredit) {
    $user=new User(); # get from COOKIE VARS
    if ($DBInfo->owners and in_array($user->id,$DBInfo->owners)) {
      $extra_check=' '._('Minor edit')."<input type='checkbox' name='minor' />";
    }
  }

  $preview_msg=_("Preview");
  $save_msg=_("Save");
  $summary_msg=_("Summary of Change");
  if ($DBInfo->use_resizer) {
    $form.=<<<EOS
<script type="text/javascript" language='javascript'>
//<![CDATA[
<!--
function resize(obj,val) {
  rows= obj.savetext.rows;
  rows+=val;
  if (rows > 60) rows=16;
  else if (rows < 5) rows=16;
  obj.savetext.rows=rows;
}
//-->
//]]>
</script>
<input type='button' value='+' onclick='resize(this.form,3)' />
<input type='button' value='-' onclick='resize(this.form,-3)' />
EOS;
  }
  $form.=<<<EOS
<textarea id="content" wrap="virtual" name="savetext"
 rows="$rows" cols="$cols" class="wiki">$raw_body</textarea><br />
$summary_msg: <input name="comment" size="70" maxlength="70" style="width:200" />$extra_check<br />
<input type="hidden" name="action" value="savepage" />
<input type="hidden" name="datestamp" value="$datestamp" />
$hidden$select_category
<input type="submit" value="$save_msg" />&nbsp;
<!-- <input type="reset" value="Reset" />&nbsp; -->
<input type="submit" name="button_preview" value="$preview_msg" />
$extra
</form>
EOS;
  if (!$options['nohints'])
    $form.= macro_EditHints($formatter);
  $form.= "<a id='preview' name='preview'></a>";
  return $form;
}


function do_invalid($formatter,$options) {
  $formatter->send_header("Status: 406 Not Acceptable",$options);
  if ($options['title'])
    $formatter->send_title('',"",$options);
  else
    $formatter->send_title(_("406 Not Acceptable"),"",$options);
  if ($options['err']) {
    $formatter->send_page($options['err']);
  } else {
    if ($options['action'])
      $formatter->send_page("== ".sprintf(_("%s is not valid action"),$options['action'])." ==\n");
    else
      $formatter->send_page("== "._("Is it valid action ?")." ==\n");
  }
  $formatter->send_footer("",$options);
}

function do_post_DeleteFile($formatter,$options) {
  global $DBInfo;

  if ($_SERVER['REQUEST_METHOD']=="POST") {
    if ($options['value']) {
      $key=$DBInfo->pageToKeyname(urldecode($options['value']));
      $dir=$DBInfo->upload_dir."/$key";
    } else {
      $dir=$DBInfo->upload_dir;
    }
  } else {
    // GET with 'value=filename' query string
    $file=$options['value'];
  }

  if (isset($options['files']) or isset($options['file'])) {
    if (isset($options['file'])) {
      $options['files']=array();
      $options['files'][]=$options['file'];
    }
      
    if ($options['files']) {
      foreach ($options['files'] as $file) {
        $key=$DBInfo->pageToKeyname($file);

        if (!is_dir($dir."/".$file) && !is_dir($dir."/".$key)) {
          $fdir=$options['value'] ? $options['value'].':':'';
          if (@unlink($dir."/".$file))
            $log.=sprintf(_("File '%s' is deleted")."<br />",$fdir.$file);
          else
            $log.=sprintf(_("Fail to delete '%s'")."<br />",$fdir.$file);
        } else {
          if ($key != $file)
            $realfile = $key;
          if (@rmdir($dir."/".$realfile))
            $log.=sprintf(_("Directory '%s' is deleted")."<br />",$file);
          else
            $log.=sprintf(_("Fail to rmdir '%s'")."<br />",$file);
        }
      }
      $title = sprintf(_("Delete selected files"));
      $formatter->send_header("",$options);
      $formatter->send_title($title,"",$options);
      print $log;
      $formatter->send_footer('',$options);
      return;
    } else
      $title = sprintf(_("No files are selected !"));
  } else if ($file) {
    list($page,$file)=explode(':',$file);
    if (!$file) {
      $file=$page;
      $page=$formatter->page->name;
    }
    $link=$formatter->link_url($formatter->page->urlname);
    $out="<form method='post' action='$link'>";
    $out.="<input type='hidden' name='action' value='DeleteFile' />\n";
    if ($page)
      $out.="<input type='hidden' name='value' value='$page' />\n";
    $out.="<input type='hidden' name='file' value='$file' />\n<h2>";
    $out.=sprintf(_("Did you really want to delete '%s' ?"),$file).'</h2>';
    if ($DBInfo->security->is_protected("deletefile",$options))
      $out.=_("Password").": <input type='password' name='passwd' size='10' />\n";
    $out.="<input type='submit' value='"._("Delete")."' /></form>\n";
    $title = sprintf(_("Delete selected file"));
    $log=$out;
  } else {
    $title = sprintf(_("No files are selected !"));
  }
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  print $log;
  $formatter->send_footer('',$options);
  return;
}

function do_post_DeletePage($formatter,$options) {
  global $DBInfo;
  
  $page = $DBInfo->getPage($options['page']);

  #print $options['page'];
  if ($options['name']) $options['name']=urldecode($options['name']);
  $pagename= $formatter->page->urlname;
  if (_stripslashes($options['name']) == $options['page']) {
    $DBInfo->deletePage($page,$options);
    $title = sprintf(_("\"%s\" is deleted !"), $page->name);
    $formatter->send_header("",$options);
    $formatter->send_title($title,"",$options);
    $formatter->send_footer('',$options);
    return;
  } else if ($options['name']) {
    $options['msg'] = _("Please delete this file manually.");
  }
  $title = sprintf(_("Delete \"%s\" ?"), $page->name);
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  print "<form method='post'>
Comment: <input name='comment' size='80' value='' /><br />\n";
  if ($DBInfo->delete_history)
    print _("with revision history")." <input type='checkbox' name='history' />\n";
  if ($DBInfo->security->is_protected("DeletePage",$options))
    print "Password: <input type='password' name='passwd' size='20' value='' />
Only WikiMaster can delete this page<br />\n";
  print "
    <input type='hidden' name='action' value='DeletePage' />
    <input type='hidden' name='name' value='$pagename' />
    <input type='submit' value='Delete page' />
    </form>";
#  $formatter->send_page();
  $formatter->send_footer('',$options);
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

function do_raw($formatter,$options) {
  $formatter->send_header("Content-Type: text/plain",$options);
  print $formatter->page->get_raw_body($options);
}

function do_recall($formatter,$options) {
  $formatter->send_header("",$options);
  $formatter->send_title("Rev. ".$options['rev']." ".
                                 $options['page'],"",$options);
  $formatter->send_page("",$options);
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
     $url=_stripslashes($options['value']);
     $url=_rawurlencode($url);
     if ($options['redirect'])
       $url=$formatter->link_url($url,"?action=show");
     else
       $url=$formatter->link_url($url,"");
     $formatter->send_header(array("Status: 302","Location: ".$url),$options);
  } else if ($options['url']) {
    $url=$options['url'];

    if ($options['ie']) $from=strtoupper($options['ie']);
    else $from=strtoupper($DBInfo->charset);
    if ($options['oe']) $to=strtoupper($options['oe']);

    if ($to and $to != $from) {
      $url=urldecode($url);

      if (function_exists("iconv")) {
        $new=iconv($from,$to,$url);
        if ($new) $url=_urlencode($new);
      } else {
        $buf=exec(escapeshellcmd("echo ".$options[page])." | ".escapeshellcmd("iconv -f $DBInfo->charset -t $to"));
        $url=_urlencode($buf);
      }
    }
    $url=str_replace("&amp;","&",$url);
    if (!preg_match("/^(http:\/\/|ftp:\/\/)/",$options['url'])) {
       print <<<HEADER
<html>
  <head>
    <meta http-equiv="refresh" content="0;URL=$options[url]">
  </head>
  <body bgcolor="#FFFFFF" text="#000000">
  </body>
</html>
HEADER;
     } else {
       $formatter->send_header(array("Status: 302","Location: ".$url),$options);
     }
  } else {
     $title = _("Use more specific text");
     $formatter->send_header("",$options);
     $formatter->send_title($title,"",$options);
     $args['noaction']=1;
     $formatter->send_footer($args,$options);
  }
}


function do_titleindex($formatter,$options) {
  global $DBInfo;

  $pages = $DBInfo->getPageLists();

  sort($pages);
  header("Content-Type: text/plain");
  print join("\n",$pages);
}

function do_titlesearch($formatter,$options) {

  $out= macro_TitleSearch($formatter,$options['value'],$ret);

  if ($ret['hits']==1) {
    $options['value']=$ret['value'];
    do_goto($formatter,$options);
    return true;
  }
  if (!$ret['hits'] and $options['check']) return false;

  $formatter->send_header("",$options);
  $formatter->send_title($ret['msg'],$formatter->link_url("FindPage"),$options);

  if ($options['check']) {
    $button= $formatter->link_to("?action=edit",$formatter->icon['create']._
("Create this page"));
    print $button;
    print sprintf(_(" or click %s to fullsearch this page.\n"),$formatter->link_to("?action=fullsearch&amp;value=$options[page]",_("title")));
  }

  print $out;

  if ($options['value'])
    printf("Found %s matching %s out of %s total pages"."<br />",
	 $ret['hits'],
	($ret['hits'] == 1) ? 'page' : 'pages',
	 $ret['all']);
  $args['noaction']=1;
  $formatter->send_footer($args,$options);
  return true;
}

function do_post_savepage($formatter,$options) {
  global $DBInfo;
  if (!$DBInfo->security->writable($options)) {
    do_invalid($formatter,$options);
    return;
  }

  $savetext=$options['savetext'];
  $datestamp=$options['datestamp'];
  $button_preview=$options['button_preview'];
  $button_merge=$options['button_merge'];

  $formatter->send_header("",$options);

  $savetext=str_replace("\r", "", $savetext);
  $savetext=_stripslashes($savetext);
  $section_savetext='';
  if (isset($options['section'])) {
    if ($formatter->page->exists()) {
      $sections= _get_sections($formatter->page->get_raw_body());
      if ($sections[$options['section']])
        $sections[$options['section']]=$savetext;
      $section_savetext=$savetext;
      $savetext=implode('',$sections);
    }
  }

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
        $option['title']=sprintf(_("Preview of %s"),$formatter->link_tag($formatter->page->urlname,"",$options['page'],"class='wikiTitle'"));
        $formatter->send_title("","",$options);
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
      print $formatter->macro_repl('Diff','',array('text'=>$savetext));
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
    $options['title']=sprintf(_("Preview of %s"),$formatter->link_tag($formatter->page->urlname,"",htmlspecialchars($options['page']),"class='wikiTitle'"));
    $formatter->send_title("","",$options);
     
    $options['preview']=1; 
    $options['datestamp']=$datestamp; 
    $savetext=$section_savetext ? $section_savetext:$savetext;
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

    $options['minor'] = $DBInfo->use_minoredit ? $options['minor']:0;
    if ($options['minor']) {
      $user=new User(); # get from COOKIE VARS
      if ($DBInfo->owners and in_array($user->id,$DBInfo->owners)) {
        $options['minor']=1;
      } else {
        $options['minor']=0;
      }
    }

    $comment=_stripslashes($options['comment']);
    $formatter->page->write($savetext);
    $ret=$DBInfo->savePage($formatter->page,$comment,$options);
    if (($ret != -1) and $DBInfo->notify and ($options['minor'] != 1)) {
      $options['noaction']=1;
      if (!function_exists('mail')) {
        $options['msg']=sprintf(_("mail does not supported by default."))."<br />";
      } else {
        $ret2=wiki_notify($formatter,$options);
        if ($ret2)
          $options['msg']=sprintf(_("Sent notification mail."))."<br />";
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
    print "<div id='wikiContent'>\n";
    $formatter->send_page("",$opt);
    print "</div>\n";
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

    $title=_("Nobody subscribed to this page.");
    $formatter->send_header("",$options);
    $formatter->send_title($title,"",$options);
    print "Fail !";
    $formatter->send_footer("",$options);
    return;
  }

  $diff="";
  if ($DBInfo->version_class) {
    $class=getModule('Version',$DBInfo->version_class);
    $version=new $class ($DBInfo);
    $rev=$formatter->page->get_rev();
    $diff=$version->diff($formatter->page->name,$rev);
  }

  $mailto=join(", ",$subs);
  $subject="[".$DBInfo->sitename."] ".sprintf(_("%s page is modified"),$options[page]);
 
  $mailheaders = "Return-Path: $from\n";
  $mailheaders.= "From: $from\n";
  $mailheaders.= "X-Mailer: MoniWiki form-mail interface\n";

  $mailheaders.= "MIME-Version: 1.0\n";
  $mailheaders.= "Content-Type: text/plain; charset=$DBInfo->charset\n";
  $mailheaders.= "Content-Transfer-Encoding: 8bit\n\n";

  $body=sprintf(_("You have subscribed to this wiki page on \"%s\" for change notification.\n\n"),$DBInfo->sitename);
  $body.="-------- $options[page] ---------\n";
  
  $body.=$formatter->page->get_raw_body();
  if (!$options['nodiff']) {
    $body.="================================\n";
    $body.=$diff;
  }

  mail($mailto,$subject,$body,$mailheaders,'-f"'.$from.'"');

  if ($options['noaction']) return 1;

  $title=_("Send notification mails to all subscribers");
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  $msg= str_replace("@"," at ",$mailto);
  print "<h2>".sprintf(_("Mails are sent successfully"))."</h2>";
  printf(sprintf(_("mails are sent to '%s'"),$msg));
  $formatter->send_footer("",$options);
  return;
}


function wiki_sendmail($body,$options) {
  global $DBInfo;

  if ($options['id'])
    $from=$options['id'];
  else
    $from=$DBInfo->sitename;

  $email=$options['email'];
  $subject=$options['subject'];

  $mailheaders = "Return-Path: $from\n";
  $mailheaders.= "From: $from\n";
  $mailheaders.= "X-Mailer: MoniWiki form-mail interface\n";

  $mailheaders.= "MIME-Version: 1.0\n";
  $mailheaders.= "Content-Type: text/plain; charset=$DBInfo->charset\n";
  $mailheaders.= "Content-Transfer-Encoding: 8bit\n\n";

  mail($email,$subject,$body,$mailheaders,'-f"'.$from.'"');
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

function macro_RandomPage($formatter,$value='') {
  global $DBInfo;
  $pages = $DBInfo->getPageLists();

  $test=preg_match("/^(\d+)\s*,?\s?(simple|nobr)?$/",$value,$match);
  if ($test) {
    $count= intval($match[1]);
    $mode=$match[2];
  }
  if ($count <= 0) $count=1;
  $counter= $count;

  $max=sizeof($pages);
  $number=min($max,$counter);

  $selected=array_rand($pages,$number);

  if ($number==1)
    $selected=array($selected);

  foreach ($selected as $idx) {
    $item=$pages[$idx];
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

  $quote=$quotes[rand(0,$count-1)];

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

function macro_UserPreferences($formatter,$value,$options='') {
  global $DBInfo;

  $user=new User(); # get from COOKIE VARS
  if ($user->id != 'Anonymous') {
    $udb=new UserDB($DBInfo);
    $udb->checkUser($user);
  }
  $url=$formatter->link_url("UserPreferences");

  # setup form
  if ($user->id == 'Anonymous') {
    if ($options['login_id'])
      $idform="$options[login_id]<input type='hidden' name='login_id' value=\"$options[login_id]\" />";
    else
      $idform="<input type='text' size='20' name='login_id' value='' />";
  } else {
    $idform=$user->id;
  }
  $button=_("Login");
  if ($user->id == 'Anonymous' and !isset($options['login_id']) and $value!="simple")
    $login=<<<FORM
<form method="post" action="$url">
<input type="hidden" name="action" value="userform" />
<table border="0">
  <tr><td><b>ID</b>&nbsp;</td><td>$idform</td></tr>
  <tr>
     <td><b>Password</b>&nbsp;</td><td><input type="password" size="15" maxlength="12" name="password" value="" />
  <tr><td></td><td>
    <input type="submit" name="login" value="$button" /> &nbsp;
  </td></tr>
</table>
</form>
FORM;

  if ($user->id == 'Anonymous') {
    if (isset($options['login_id']) or $_GET['join'] or $value!="simple") {
      $passwd=$options['password'];
      $button=_("Make profile");
      $again="<b>"._("password again")."</b>&nbsp;<input type='password' size='15' maxlength='12' name='passwordagain' value='' /></td></tr>";
      $extra=<<<EXTRA
  <tr><td><b>Mail</b>&nbsp;</td><td><input type="text" size="40" name="email" value="$email" /></td></tr>
EXTRA;
    } else {
      $button=_("Login or Join");
    }
  } else {
    $button=_("Save");
    $css=$user->info['css_url'];
    $email=$user->info['email'];
    $again="<b>"._("New password")."</b>&nbsp;<input type='password' size='15' maxlength='12' name='passwordagain' value='' /></td></tr>";

    $extra=<<<EXTRA
  <tr><td><b>Mail</b>&nbsp;</td><td><input type="text" size="40" name="email" value="$email" /></td></tr>
  <tr><td><b>CSS URL </b>&nbsp;</td><td><input type="text" size="40" name="user_css" value="$css" /><br />("None" for disabling CSS)</td></tr>
EXTRA;
    $logout="<input type='submit' name='logout' value='"._("logout")."' /> &nbsp;";
  }

  return <<<EOF
$login
<form method="post" action="$url">
<input type="hidden" name="action" value="userform" />
<table border="0">
  <tr><td><b>ID</b>&nbsp;</td><td>$idform</td></tr>
  <tr>
     <td><b>Password</b>&nbsp;</td><td><input type="password" size="15" maxlength="12" name="password" value="$passwd" />
    $again
    $extra
  <tr><td></td><td>
    <input type="submit" name="login" value="$button" /> &nbsp;
    $logout
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
    $out.="<tr><td><tt><img src='$DBInfo->imgs_dir_interwiki/$icon' align='middle' alt='$wiki:' /><a href='$url'>$wiki</a></tt></td><td><tt>";
    $out.="<a href='$href'>$href</a></tt></td></tr>\n";
  }
  $out.="</table>\n";
  return $out;
}


function get_key($name) {
  global $DBInfo;
  if (preg_match('/[a-z0-9]/i',$name[0])) {
     return strtoupper($name[0]);
  }
  $utf="";
  $use_utf=strtolower($DBInfo->charset)=='utf-8';
  if (!$use_utf and function_exists ("iconv")) {
    # XXX php 4.1.x did not support unicode sting.
    $utf=iconv($DBInfo->charset,'UTF-8',$name);
    $name=$utf;
  }

  if ($utf or $use_utf) {
    if ((ord($name[0]) & 0xF0) == 0xE0) { # Now only 3-byte UTF-8 supported
       #$uni1=((ord($name[0]) & 0x0f) <<4) | ((ord($name[1]) & 0x7f) >>2);
       $uni1=((ord($name[0]) & 0x0f) <<4) | (($name[1] & 0x7f) >>2);
       $uni2=((ord($name[1]) & 0x7f) <<6) | (ord($name[2]) & 0x7f);

       $uni=($uni1<<8)+$uni2;
       # Hangul Syllables
       if ($uni>=0xac00 && $uni<=0xd7a3) {
         $ukey=0xac00 + (int)(($uni - 0xac00) / 588) * 588;
         $ukey=toutf8($ukey);
         if ($utf and !$use_utf)
           return iconv('UTF-8',$DBInfo->charset,$ukey);
         return $ukey;
       }
    }
    return 'Others';
  } else {
    if (preg_match('/[a-z0-9]/i',$name[0])) {
      return strtoupper($name[0]);
    }
    # if php does not support iconv(), EUC-KR assumed
    if (strtolower($DBInfo->charset) == 'euc-kr') {
      $korean=array('가','까','나','다','따','라','마','바','빠','사','싸','아',
                    '자','짜','차','카','타','파','하',"\xca");
      $lastPosition='Others';

      $letter=substr($name,0,2);
      foreach ($korean as $position) {
        if ($position > $letter)
          return $lastPosition;
        $lastPosition=$position;
      }
    }
    return 'Others';
  }
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
    $links=preg_replace("/(".$formatter->wordrule.")/e","\$formatter->link_repl('\\1')",$links);
    $out.=$links."</li>\n";
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
      $all_pages = $DBInfo->getPageLists();
#     $all_pages= array_unique(array_merge($meta_pages,$all_pages));
  }
#  $all_pages = $DBInfo->getPageLists($options);

#  print_r($all_pages);

  $hits=array();

  if ($options['date']) {
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

function macro_TitleIndex($formatter) {
  global $DBInfo;

  if ($formatter->group) {
    $group_pages = $DBInfo->getLikePages($formatter->group,1);
    foreach ($group_pages as $page)
      $all_pages[]=str_replace($formatter->group,'',$page);
  } else
    $all_pages = $DBInfo->getPageLists();
  #natcasesort($all_pages);
  #sort($all_pages,SORT_STRING);
  usort($all_pages, 'strcasecmp');

  $key=-1;
  $out="";
  $keys=array();

#  if ($DBInfo->use_titlecache)
#    $cache=new Cache_text('title');

  foreach ($all_pages as $page) {
    $p=ltrim($page);
    $pkey=get_key("$p");
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
      $title=get_title($page);

    #$out.= '<li>' . $formatter->word_repl('"'.$page.'"',$title,'',0,0);
    $urlname=_urlencode($page);
    $out.= '<li>' . $formatter->link_tag($urlname,'',$title);
    $keyname=$DBInfo->pageToKeyname(urldecode($page));
    if (is_dir($DBInfo->upload_dir."/$keyname"))
       $out.=' '.$formatter->link_tag($urlname,"?action=uploadedfiles",
         $formatter->icon['attach']);
    $out.="</li>\n";
  }
  $out.= "</ul>\n";

  $index='';
  foreach ($keys as $key) {
    $name=$key;
    if ($key == 'Others') $name=_("Others");
    $index.= "| <a href='#$key'>$name</a> ";
  }
  $index[0]=" ";
  
  return "<center><a name='top' />$index</center>\n$out";
}


function macro_HTML($formatter,$value) {
  return str_replace("&lt;","<",$value);
}

function macro_BR($formatter) {
  return "<br />\n";
}

function macro_FootNote(&$formatter,$value="") {
  if (!$value) {# emit all footnotes
    if (!$formatter->foots) return '';
    $foots=join("\n",$formatter->foots);
    $foots=preg_replace("/(".$formatter->wordrule.")/e","\$formatter->link_repl('\\1')",$foots);
    unset($formatter->foots);
    if ($foots)
      return "<div class='foot'><div class='separator'><tt class='wiki'>----</tt></div>\n$foots</div>";
    return '';
  }

  $formatter->foot_idx++;
  $idx=$formatter->foot_idx;

  $text="[$idx&#093";
  $fnidx="fn".$idx;
  if ($value[0] == '*') {
    if ($value[1] == '*') {
      # [** http://foobar.com] -> [*]
      # [*** http://foobar.com] -> [**]
      $p=strrpos($value,'*');
      $len=strlen(substr($value,1,$p));
      $text=str_repeat('*',$len);
      $value=substr($value,$p+1);
    } else if ($value[1] == '+') {
      $dagger=array('','&#x2020;',
                    '&#x2020;&#x2020;',
                    '&#x2020;&#x2020;&#x2020;',
                    '&#x2021;',
                    '&#x2021;&#x2021;',
                    '&#x2021;&#x2021;&#x2021;');
      $p=strrpos($value,'+');
      $len=strlen(substr($value,0,$p));
      $text=$dagger[$len];
      $value=substr($value,$p+1);
    } else if ($value[1] == ' ') {
      # [* http://c2.com] -> [1]
      $value=substr($value,2);
    } else {
      # [*ward http://c2.com] -> [ward]
      $text=strtok($value,' ');
      $value=strtok('');
      $fnidx=substr($text,1);
      $text[0]='[';
      $text=$text.'&#093;'; # make a text as [Alex77]
      if ($value) {
        $formatter->foot_idx--; # undo ++.
        if (0 === strcmp($fnidx , (int)$fnidx)) $fnidx="fn$fnidx";
      } else {
        if (0 === strcmp($fnidx , (int)$fnidx)) $fnidx="fn$fnidx";
        return "<tt class='foot'><a href='#$fnidx'>$text</a></tt>";
      }
    }
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
  return "<tt class='foot'><a id='r$fnidx' name='r$fnidx'/>".
    "<a href='#$fnidx' title='$value'>$text</a></tt>";
}

function macro_TableOfContents(&$formatter,$value="") {
 global $DBInfo;
 $head_num=1;
 $head_dep=0;
 $TOC="\n<div class='toc'><a name='toc' id='toc' /><dl><dd><dl>\n";

 $formatter->toc=1;
 $baseurl='';
 if ($value and $DBInfo->hasPage($value)) {
   $p=$DBInfo->getPage($value);
   $body=$p->get_raw_body();
   $baseurl=$formatter->link_url(_urlencode($value));
   $formatter->page=&$p;
 } else {
   $body=$formatter->page->get_raw_body();
 }
 $body=preg_replace("/\{\{\{.+?\}\}\}/s",'',$body);
 $lines=explode("\n",$body);
 foreach ($lines as $line) {
   $line=preg_replace("/\n$/", "", $line); # strip \n
   preg_match("/(?<!=)(={1,5})\s(#?)(.*)\s+\\1\s?$/",$line,$match);

   if (!$match) continue;

   $dep=strlen($match[1]);
   $head=str_replace("<","&lt;",$match[3]);
   # strip some basic wikitags
   # $formatter->baserepl,$head);
   #$head=preg_replace($formatter->baserule,"\\1",$head);
   # do not strip basic wikitags
   $head=preg_replace($formatter->baserule,$formatter->baserepl,$head);
   $head=preg_replace("/\[\[.*\]\]/","",$head);
   $head=preg_replace("/(".$formatter->wordrule.")/e","\$formatter->link_repl('\\1')",$head);

   if (!$depth_top) { $depth_top=$dep; $depth=1; }
   else {
     $depth=$dep - $depth_top + 1;
     if ($depth <= 0) $depth=1;
   }

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



function macro_TitleSearch($formatter="",$needle="",&$opts) {
  global $DBInfo;
  $type='o';

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

  $out="<${type}l>\n";
  $idx=1;
  foreach ($hits as $pagename) {
    if ($opts['linkto'])
      $out.= '<li>' . $formatter->link_to("$opts[linkto]$pagename",$pagename,"tabindex='$idx'")."</li>\n";
    else
      $out.= '<li>' . $formatter->link_tag(_rawurlencode($pagename),"",$pagename,"tabindex='$idx'")."</li>\n";
    $idx++;
  }

  $out.="</${type}l>\n";
  $opts['hits']= count($hits);
  if ($opts['hits']==1)
    $opts['value']=array_pop($hits);
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
