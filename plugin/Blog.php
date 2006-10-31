<?php
// Copyright 2003-2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// Blog action plugin for the MoniWiki
//
// Usage: ?action=Blog
//
// $Id$

function updateBlogList($formatter) {
  global $DBInfo;
  $cache=new Cache_text("blog");
  $changecache=new Cache_text("blogchanges");

  $rule="/^(\d*)".$DBInfo->pageToKeyname('.'.$formatter->page->name).'$/';

  $handle = @opendir($DBInfo->cache_dir."/blogchanges");
  if ($handle) {
    while (($file = readdir($handle)) !== false) {
      if (preg_match($rule,$file,$match)) {
        $fname=$DBInfo->cache_dir."/blogchanges/".$file;
        if (is_dir($fname)) continue;
        #print $fname;
        unlink($fname);
      }
    }
    closedir($handle);
  }

  $body=$formatter->page->get_raw_body();
  $lines=explode("\n",$body);

  $date=0;
  $entries=array();
  $log='';
  $logs='';
  foreach ($lines as $line) {
    if (preg_match("/^##norss/i",$line)) {
      #XXX $changecache->_del($key);
      return;
    }
    if (preg_match("/^({{{)?#!blog (.*)$/",$line,$match)) {
      list($dummy,$datestamp,$dummy)=explode(' ',$match[2],3);

      $datestamp[10]=' ';
      $time= strtotime($datestamp." GMT");
      $datestamp= date("Ymd",$time);
      if (!$date) $date=$datestamp;
      if ($datestamp != $date) {
        if ($date) {
          $log=join("\n",$entries)."\n";
          $logs.=$log;
          $changecache->update($date.'.'.$formatter->page->name,$log);
          $entries=array();
        }
        $date=$datestamp;
      }

      $entries[]=$match[2];
    }
  }
  $log=join("\n",$entries)."\n";
  if ($datestamp)
    $changecache->update($datestamp.'.'.$formatter->page->name,$log);

  $logs.=$log;
  $cache->update($formatter->page->name,$logs);
  return;
}

function do_Blog($formatter,$options) {
  global $DBInfo;
  $COLS_MSIE = 80;
  $COLS_OTHER = 85;
  $cols = preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT']) ? $COLS_MSIE : $COLS_OTHER;

  $rows=$options['rows'] > 5 ? $options['rows']: 8;
  $cols=$options['cols'] > 60 ? $options['cols']: $cols;

  $url=$formatter->link_url($formatter->page->urlname);
  $formatter->send_header("",$options);

  if ($formatter->refresh or $options['button_refresh']) {
    updateBlogList($formatter);
    $options['msg']=sprintf(_("Blog cache of \"%s\" is refreshed"),$formatter->page->name);
  }

  $savetext="";
  if ($options['savetext']) {
    $savetext=_stripslashes($options['savetext']);
    $savetext=str_replace("\r","",$savetext);
    $savetext=str_replace("----\n","-''''''---\n",$savetext);
    $savetext=rtrim($savetext);
    #$savetext=str_replace("<","&lt;",$savetext);
  }

  # for conflict check
  if ($options['datestamp'])
     $datestamp= $options['datestamp'];
  else
     $datestamp= $formatter->page->mtime();

  if ($options['title'])
    $options['title']=_stripslashes($options['title']);
  if (!$options['button_preview'] && $savetext) {
    $savetext=preg_replace("/(?<!\\\\)}}}/","\}}}",$savetext);

    $url=$formatter->link_tag($formatter->page->urlname,"",$options['page']);
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

    if ($options['value']) {
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

      if ($found) {
        if ($endtag)
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
        if ($options['nosig'])
          $lines[$i]="----\n$savetext\n$endtag";
        else
          $lines[$i]="----\n$savetext @SIG@\n$endtag";
        $raw_body=join("\n",$lines);
      } else {
        $formatter->send_title(_("Error: No blog entry found!"),"",$options);
        $formatter->send_footer("",$options);
        return;
      }
    } else { # Blog entry
      // check timestamp
      if ($formatter->page->mtime() > $datestamp) {
        $options['msg']='';
        $formatter->send_title(_("Error: Don't make a clone!"),"",$options);
        $formatter->send_footer("",$options);
        return;
      }

      $entry="{{{#!blog $id @date@";
      if ($options['title'])
        $entry.=" ".$options['title'];
      $entry.="\n$savetext\n}}}\n\n";

      if (preg_match("/\n##Blog\n/i",$raw_body))
        $raw_body=preg_replace("/\n##Blog\n/i","\n##Blog\n$entry",$raw_body,1);
      else
        $raw_body.=$entry;
    }

    if ($options['value']) {
      $formatter->send_title(sprintf(_("Comment added to \"%s\""),$title),"",$options);
      $log="Add Comment to \"$title\"";
    } else {
      $formatter->send_title(sprintf(_("Blog entry added to \"%s\""),$options['page']),"",$options);
      $log="Blog entry \"$options[title]\" added";
    }
    
    $formatter->page->write($raw_body);
    $DBInfo->savePage($formatter->page,$log,$options);
    updateBlogList($formatter);

    $formatter->send_page();
  } else { # add entry or comment
    if ($options['value']) {
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

      if ($found) {
        for (;$i<$count;$i++) {
          if (preg_match("/^}}}$/",$lines[$i])) break;
          $quote.=$lines[$i]."\n";
        }
      }
      if (!$title) $title=$options['page'];
      if (!$found) {
        $formatter->send_title("Error: No entry found!","",$options);
        $formatter->send_footer("",$options);
        return;
      }
      $formatter->send_title(sprintf(_("Add Comment to \"%s\""),$title),"",$options);
    } else {
      $formatter->send_title(sprintf(_("Add Blog entry to \"%s\""),$options['page']),"",$options);
    }
    $options['noaction']=1;
    if ($quote) {
      $quote=str_replace('\}}}','}}}',$quote);
      print $formatter->processor_repl('blog',$quote,$options);
      #print $formatter->send_page($quote,$options);
    }
    if ($options['id'] != 'Anonymous')
      $extra='<div style="text-align:right">'.'
        <input type="submit" name="button_refresh" value="Refresh" /></div>';

    if ($options['value'])
      print "<a name='BlogComment'></a>";
    print "<form method='post' action='$url'>\n";
    if ($options['id'] == 'Anonymous')
      print '<b>'._("Name")."</b>: <input name='name' size='15' maxlength='15' value='$options[name]' />\n";
    if ($options['value'])
      print "<input type='hidden' name='value' value='$options[value]' />\n";
    else
      print '<b>'._("Title")."</b>: <input name='title' value='$options[title]' size='70' maxlength='70' style='width:300px' /><br />\n";
    print <<<FORM
<textarea class="wiki" id="content" wrap="virtual" name="savetext"
 rows="$rows" cols="$cols" class="wiki">$savetext</textarea><br />
FORM;
    if ($options['value'])
      print "<input name='nosig' type='checkbox' />"._("Don't add a signature")."<br />";
    print <<<FORM2
<input type="hidden" name="action" value="Blog" />
<input type="hidden" name="datestamp" value="$datestamp" />
<input type="submit" value="Save" />&nbsp;
<input type="submit" name="button_preview" value="Preview" />
$extra
</form>
FORM2;
  }
  if (!$savetext) {
    #print $formatter->macro_repl('SmileyChooser');
    print macro_EditHints($formatter);
    print "<div class='wikiHints'>"._("<b>horizontal rule</b> ---- is not applied on the blog mode.")."</div>\n";
  }
  if ($options['button_preview'] && $options['savetext']) {
    if ($options['title'])
      $formatter->send_page("== $options[title] ==\n");
    $formatter->send_page($savetext);
  }
  $formatter->send_footer("",$options);
  return;
}

function macro_Blog($formatter,$value) {
  $COLS_MSIE = 80;
  $COLS_OTHER = 85;
  $cols = preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT']) ? $COLS_MSIE : $COLS_OTHER;

  $rows=$options['rows'] > 5 ? $options['rows']: 8;
  $cols=$options['cols'] > 60 ? $options['cols']: $cols;

  $url=$formatter->link_url($formatter->page->urlname);
  $datestamp= $formatter->page->mtime();

  if (!$options['id']) {
    $user=new User(); # get from COOKIE VARS
    $options['id']=$user->id;
  }
  if ($options['id'] != 'Anonymous')
    $extra='<div style="text-align:right">'.'
      <input type="submit" name="button_refresh" value="Refresh" /></div>';

  $form = "<form method='post' action='$url'>\n";
  if ($options['id'] == 'Anonymous')
    $form.='<b>'._("Name")."</b>: <input name='name' size='15' maxlength='15' value='$options[name]' />\n";
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
FORM2;

  return $form;
}

// vim:et:sts=2:
?>
