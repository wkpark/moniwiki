<?php
// Copyright 2003-2013 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// Blog action plugin for the MoniWiki
//
// Usage: ?action=Blog
//
// $Id: Blog.php,v 1.38 2010/08/23 09:20:34 wkpark Exp $

function updateBlogList($formatter) {
  global $DBInfo;

  $cache = new Cache_Text('blog', array('hash'=>''));
  $changecache = new Cache_Text('blogchanges', array('hash'=>''));

  $rule="@/(\d*)".$DBInfo->pageToKeyname('.'.$formatter->page->name).'$@';

  $files = array();
  $changecache->_caches($files);

  foreach ($files as $file) {
    if (preg_match($rule, $file, $match)) {
      print $fname;
      #unlink($fname);
    }
  }

  $body=$formatter->page->get_raw_body();
  $lines=explode("\n",$body);

  $date=0;
  $entries=array();
  $log='';
  $logs='';
  $key = $DBInfo->pageToKeyname('.'.$formatter->page->name);
  foreach ($lines as $line) {
    if (preg_match("/^##norss/i",$line)) {
      #XXX $changecache->_del($key);
      return;
    }
    if (preg_match("/^(?:{{{)?#!blog\s+(.*)\s+(\d{4}-\d{2}-\d{2}T[^ ]+)\s*(.*)?$/", $line, $match)) {
      list($author, $datestamp, $title) = array($match[1], $match[2], $match[3]);
      $datestamp[10] = ' ';
      $time = strtotime($datestamp.' GMT');
      $stamp = date('Ymd', $time);

      if (empty($date)) $date = $stamp;
      if ($stamp != $date) {
        $log = join("\n", $entries)."\n";
        $logs.= $log;
        $changecache->update($date.$key, $log);
        $entries=array();
        $date = $stamp;
      }

      $entries[] = $date."\t".$time."\t".$author."\t".$datestamp."\t".$title;
    }
  }
  $log=join("\n",$entries)."\n";
  if ($stamp)
    $changecache->update($stamp.$key,$log);

  $logs.=$log;
  $cache->update($DBInfo->pageToKeyname($formatter->page->name), $logs);
  return;
}

function do_Blog($formatter,$options) {
  global $DBInfo;
  $COLS_MSIE = 80;
  $COLS_OTHER = 85;
  $cols = preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT']) ? $COLS_MSIE : $COLS_OTHER;

  $rows=(!empty($options['rows']) and $options['rows'] > 5) ? $options['rows']: 8;
  $cols=(!empty($options['cols']) and $options['cols'] > 60) ? $options['cols']: $cols;

  $name = !empty($options['name']) ? $options['name'] : '';

  $url=$formatter->link_url($formatter->page->urlname);
  $pagename = _html_escape($formatter->page->name);

  if (!empty($formatter->refresh) or !empty($options['button_refresh'])) {
    updateBlogList($formatter);
    $options['msg']=sprintf(_("Blog cache of \"%s\" is refreshed"),$pagename);
  }

  $savetext="";
  if (!empty($options['savetext'])) {
    $savetext=_stripslashes($options['savetext']);
    $savetext=str_replace("\r","",$savetext);
    $savetext=str_replace("----\n","-''''''---\n",$savetext);
    $savetext=rtrim($savetext);
    #$savetext=str_replace("<","&lt;",$savetext);
  }

  # for conflict check
  if (!empty($options['datestamp']))
     $datestamp= $options['datestamp'];
  else
     $datestamp= $formatter->page->mtime();

  if (!empty($options['title']))
    $options['title']=_stripslashes($options['title']);
  else
    $options['title'] = '';

  $options['title'] = _html_escape($options['title']);

  $button_preview = $options['button_preview'];
  if (!empty($savetext)) {
    $ok_ticket=0;
    if (empty($use_any) and !empty($DBInfo->use_ticket) and $options['id'] == 'Anonymous') {
      if ($options['__seed'] and $options['check']) {
        $mycheck=getTicket($options['__seed'],$_SERVER['REMOTE_ADDR'],4);
        if ($mycheck==$options['check'])
          $ok_ticket=1;
        else {
          $options['msg']= _("Invalid ticket !");
          $button_preview=1;
        }
      } else {
        if (!$button_preview)
          $options['msg']= _("You need a ticket !");
        $button_preview=1;
      }
    } else {
      $ok_ticket=1;
    }
  }

  if (empty($button_preview) && !empty($savetext)) {
    //$savetext=preg_replace("/(?<!\\\\)}}}/","\}}}",$savetext);

    $url=$formatter->link_tag($formatter->page->urlname, '', $pagename);
    $options['msg']=sprintf(_("\"%s\" is updated"),$url);

    if ($formatter->page->exists())
      $raw_body=$formatter->page->_get_raw_body();
    else
      $raw_body="#action Blog "._("Add Blog")."\n##Blog\n";
    $lines=explode("\n",$raw_body);
    $count=count($lines);

    if ($options['id'] == 'Anonymous') {
      $id=$options['name'] ?
        _stripslashes($options['name']):$_SERVER['REMOTE_ADDR'];
    } else $id=$options['id'];

    if (!empty($options['value'])) {
      # add comment
      for ($i=0;$i<$count;$i++) {
        if (preg_match("/^({{{)?#!blog (.*)$/",$lines[$i],$match)) {
          if (md5($match[2]) == $options['value']) {
            list($tag, $user, $date, $title) = explode(" ",$lines[$i],4);
            $found=1;
            if ($match[1]) $endtag='}}}';
            break;
          }
        }
      }

      if (!empty($found)) {
        if (!empty($endtag))
          for (;$i<$count;$i++) {
            if (preg_match("/^}}}$/",$lines[$i])) {
              $found=1; 
              break;
            }
          }
        else { # XXX
          $lines=explode("\n",rtrim($raw_body));
          $i=count($lines);
        }
        if (!empty($options['nosig']))
          $lines[$i]="----\n$savetext\n$endtag";
        else
          $lines[$i]="----\n$savetext @SIG@\n$endtag";
        $raw_body=join("\n",$lines);
      } else {
        $formatter->send_header("",$options);
        $formatter->send_title(_("Error: No blog entry found!"),"",$options);
        $formatter->send_footer("",$options);
        return;
      }
    } else { # Blog entry
      // check timestamp
      if ($formatter->page->mtime() > $datestamp) {
        $options['msg']='';
        if ($options['action_mode']=='ajax') {
          print "false\n";
          print _("Error: Don't make a clone!");
        } else {
          $formatter->send_title(_("Error: Don't make a clone!"),"",$options);
          $formatter->send_footer("",$options);
        }
        return;
      }

      $entry="{{{#!blog $id @date@";
      if (!empty($options['title']))
        $entry.=" ".$options['title'];
      $entry.="\n$savetext\n}}}\n\n";

      if (preg_match("/\n##Blog\n/i",$raw_body))
        $raw_body=preg_replace("/\n##Blog\n/i","\n##Blog\n$entry",$raw_body,1);
      else
        $raw_body.=$entry;
    }

    $myrefresh='';
    if (!empty($DBInfo->use_save_refresh)) {
       $sec=$DBInfo->use_save_refresh - 1;
       $lnk=$formatter->link_url($formatter->page->urlname,"?action=show");
       $myrefresh='Refresh: '.$sec.'; url='.qualifiedURL($lnk);
    }
    $formatter->send_header($myrefresh,$options);

    if (!empty($options['value'])) {
      $formatter->send_title(sprintf(_("Comment added to \"%s\""),$title),"",$options);
      $log="Add Comment to \"$title\"";
    } else {
      $formatter->send_title(sprintf(_("Blog entry added to \"%s\""),$pagename),"",$options);
      if (!empty($options['title']))
        $log=sprintf(_("Blog entry \"%s\" added"),$options['title']);
      else
        $log=_("Blog entry added");
    }
    
    $formatter->page->write($raw_body);
    $DBInfo->savePage($formatter->page,$log,$options);
    updateBlogList($formatter);

    if ($options['action_mode']=='ajax') {
      print "true\n";
      print $options['msg'];
    } else
      $formatter->send_page();
  } else { # add entry or comment
    $formatter->send_header("",$options);
    if (!empty($options['value'])) {
      $raw_body=$formatter->page->_get_raw_body();
      $lines=explode("\n",$raw_body);
      $count=count($lines);
      for ($i=0;$i<$count;$i++) {
        if (preg_match("/^({{{)?#!blog (.*)$/",$lines[$i],$match)) {
          if (md5($match[2]) == $options['value']) {
            list($tag, $user, $date, $title) = explode(" ",$lines[$i],4);
            $found=1;
            $lines[$i]='#!blog '.$match[2];
            break;
          }
        }
      }

      if (!empty($found)) {
        $quote = '';
        for (;$i<$count;$i++) {
          if (preg_match("/^}}}$/",$lines[$i])) break;
          $quote.=$lines[$i]."\n";
        }
      }
      if (empty($title)) $title = $pagename;
      if (empty($found)) {
        $formatter->send_title("Error: No entry found!","",$options);
        $formatter->send_footer("",$options);
        return;
      }
      $formatter->send_title(sprintf(_("Add Comment to \"%s\""),$title),"",$options);
    } else {
      $formatter->send_title(sprintf(_("Add Blog entry to \"%s\""),$pagename),"",$options);
    }
    $options['noaction']=1;
    if (!empty($quote)) {
      $quote=str_replace('\}}}','}}}',$quote);
      print $formatter->processor_repl('blog',$quote,$options);
      #print $formatter->send_page($quote,$options);
    }
    $extra = '';
    $btn = _("Refresh");
    if ($options['id'] != 'Anonymous')
      $extra='<div style="text-align:right">'.'
        <span class="button"><input type="submit" class="button" name="button_refresh" value="'.$btn.'" /></span></div>';

    if (!empty($options['value']))
      print "<a name='BlogComment'></a>";
    print '<div id="editor_area">';
    print "<form method='post' action='$url'>\n";
    $myinput='';
    if ($options['id'] == 'Anonymous')
      $myinput.='<b>'._("Name")."</b>: <input name='name' size='15' maxlength='15' value=\"$name\" />\n";
    if (empty($options['value']))
      $myinput.='<b>'._("Title")."</b>: <input name='title' value=\"$options[title]\" size='70' maxlength='70' style='width:300px' /><br />\n";
    else
      print "<input type='hidden' name='value' value='$options[value]' />\n";
    print '<div class="editor_area_extra">'.$myinput."</div>\n";
    $savetext=$savetext ? $savetext:'Enter blog entry';
    if (!empty($DBInfo->use_wikiwyg)) {
      $wysiwyg_msg=_("GUI");
      $wysiwyg_btn='&nbsp;<span class="button"><input class="button" type="button" tabindex="7" value="'.$wysiwyg_msg.
        '" onclick="javascript:sectionEdit(null,null,null)" /></span>';
    }
    if ($DBInfo->use_resizer > 1)
      echo <<<JS
<script type="text/javascript" src="$DBInfo->url_prefix/local/textarea.js"></script>
JS;
    print <<<FORM
<div class="resizable-textarea" style='position:relative'><!-- IE hack -->
<textarea class="wiki resizable" id="content" wrap="virtual" name="savetext"
 rows="$rows" cols="$cols" class="wiki">$savetext</textarea></div>
FORM;
    if (!empty($options['value']))
      print "<input name='nosig' type='checkbox' />"._("Don't add a signature")."<br />";

    $save_msg = _("Save");
    $preview_msg = _("Preview");
    if (empty($use_any) and !empty($DBInfo->use_ticket) and $options['id'] == 'Anonymous') {
      $seed=md5(base64_encode(time()));
      $ticketimg=$formatter->link_url($formatter->page->urlname,'?action=ticket&amp;__seed='.$seed);
      $captcha=<<<EXTRA
  <div class='captcha'><span class='captchaImg'><img src="$ticketimg" alt="captcha" /></span><input type="text" size="10" name="check" />
<input type="hidden" name="__seed" value="$seed" /></div>
EXTRA;
    }
    print <<<FORM2
$captcha
<input type="hidden" name="action" value="Blog" />
<input type="hidden" name="datestamp" value="$datestamp" />
<span class="button"><input type="submit" class="button" value="$save_msg" /></span>&nbsp;
<span class="button"><input type="submit" class="button" name="button_preview" value="$preview_msg" /></span>
$wysiwyg_btn$extra
</form>
</div>
FORM2;
    if (!empty($DBInfo->use_wikiwyg) and $DBInfo->use_wikiwyg>=3)
      print <<<JS
<script type='text/javascript'>
/*<![CDATA[*/
sectionEdit(null,null,null);
/*]]>*/
</script>
JS;
  }
  if (empty($savetext)) {
    #print $formatter->macro_repl('SmileyChooser');
    print macro_EditHints($formatter);
    print "<div class='wikiHints'>"._("<b>horizontal rule</b> ---- is not applied on the blog mode.")."</div>\n";
  }
  if (!empty($options['button_preview']) && !empty($options['savetext'])) {
    if (!empty($options['title']))
      $formatter->send_page("== $options[title] ==\n");
    $formatter->send_page($savetext);
  }
  $formatter->send_footer("",$options);
  return;
}

function macro_Blog($formatter, $value, $options = array()) {
  global $DBInfo;
  $COLS_MSIE = 80;
  $COLS_OTHER = 85;
  $cols = preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT']) ? $COLS_MSIE : $COLS_OTHER;

  $rows=(!empty($options['rows']) and $options['rows'] > 5) ? $options['rows']: 8;
  $cols=(!empty($options['cols']) and $options['cols'] > 60) ? $options['cols']: $cols;

  $url=$formatter->link_url($formatter->page->urlname);
  $datestamp= $formatter->page->mtime();

  $name = !empty($options['name']) ? $options['name'] : '';

  if (empty($options['id']))
    $options['id']=$DBInfo->user->id;

  $btn = _("Refresh");
  if ($options['id'] != 'Anonymous')
    $extra='<div style="text-align:right">'.'
      <span class="button"><input type="submit" class="button" name="button_refresh" value="'.$btn.'" /></span></div>';

  $form = '<div id="editor_area">';
  $form.= "<form method='post' action='$url'>\n";
  if ($options['id'] == 'Anonymous')
    $form.='<b>'._("Name")."</b>: <input name='name' size='15' maxlength='15' value='$name' />\n";
  $form.= '<b>'._("Title")."</b>: <input name='title' size='70' maxlength='70' style='width:200' /><br />\n";
  $form.= <<<FORM
<textarea class="wiki" id="content" wrap="virtual" name="savetext"
 rows="$rows" cols="$cols" class="wiki"></textarea><br />
FORM;
  $form.= <<<FORM2
<input type="hidden" name="action" value="Blog" />
<input type="hidden" name="datestamp" value="$datestamp" />
<input type="submit" value="Save" />&nbsp;
<input type="submit" name="button_preview" value="Preview" />
$extra
</form>
</div>
FORM2;
  if (!empty($DBInfo->use_wikiwyg) and $DBInfo->use_wikiwyg >=3)
    $JS=<<<JS
<script type='text/javascript'>
/*<![CDATA[*/
sectionEdit(null,null,null);
/*]]>*/
</script>
JS;

  return $form.$JS;
}

// vim:et:sts=2:
?>
