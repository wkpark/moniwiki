<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// Blog action plugin for the MoniWiki
//
// Usage: ?action=Blog
//
// $Id$
// vim:et:ts=2:

function do_Blog($formatter,$options) {
  global $DBInfo;
  global $HTTP_USER_AGENT;
  $COLS_MSIE = 80;
  $COLS_OTHER = 85;
  $cols = preg_match('/MSIE/', $HTTP_USER_AGENT) ? $COLS_MSIE : $COLS_OTHER;

  $rows=$options['rows'] > 5 ? $options['rows']: 8;
  $cols=$options['cols'] > 60 ? $options['cols']: $cols;

  $url=$formatter->link_url($formatter->page->urlname);
  $formatter->send_header("",$options);

  $savetext="";
  if ($options['savetext']) {
    $savetext=stripslashes($options['savetext']);
    $savetext=str_replace("}}}","\}}}",$savetext);
    $savetext=str_replace("\r","",$savetext);
    $savetext=str_replace("----\n","-''''''---\n",$savetext);
  }
  if (!$options['button_preview'] && $savetext) {
    $options['title']=stripslashes($options['title']);
    $url=$formatter->link_tag($formatter->page->urlname,"",$options['page']);
    $options['msg']=sprintf(_("\"%s\" is updated"),$url);

    $raw_body=$formatter->page->_get_raw_body();
    $lines=explode("\n",$raw_body);
    $count=count($lines);

    if ($options['id']=='Anonymous') $id=$_SERVER['REMOTE_ADDR'];
    else $id=$options['id'];

    if ($options['value']) {
      # add comment
      for ($i=0;$i<$count;$i++) {
        if (preg_match("/^{{{#!blog .*$/",$lines[$i])) {
          if (md5(substr($lines[$i],3)) == $options['value']) {
            list($tag, $user, $date, $title) = explode(" ",$lines[$i],4);
            $found=1;
            break;
          }
        }
      }

      if ($found) {
        for (;$i<$count;$i++) {
          if (preg_match("/^}}}$/",$lines[$i])) {
            $found=1; 
            break;
          }
        }
        $lines[$i]="----\n$savetext -- $id @DATE@\n}}}";
        $raw_body=join("\n",$lines);
      } else {
        $formatter->send_title("Error: No entry found!","",$options);
        return;
      }
    } else { # Blog entry
      $raw_body.="\n{{{#!blog $id @date@";
      if ($options['title'])
        $raw_body.=" ".$options['title'];
      $raw_body.="\n$savetext\n}}}\n";
    }

    if ($options['value']) {
      $formatter->send_title(sprintf(_("Comment added to \"%s\""),$title),"",$options);
      $log="Add Comment to \"$title\"";
    } else {
      $formatter->send_title(sprintf(_("Blog entry added to \"%s\""),$options['page']),"",$options);
      $log="Add Blog entry \"$options[title]\"";
    }
    
    $formatter->page->write($raw_body);
    $DBInfo->savePage($formatter->page,$log,$options);

    $formatter->send_page();
  } else {
    if ($options['value']) {
      $raw_body=$formatter->page->_get_raw_body();
      $lines=explode("\n",$raw_body);
      $count=count($lines);
      for ($i=0;$i<$count;$i++) {
        if (preg_match("/^{{{#!blog .*$/",$lines[$i])) {
          if (md5(substr($lines[$i],3)) == $options['value']) {
            list($tag, $user, $date, $title) = explode(" ",$lines[$i],4);
            $found=1;
            break;
          }
        }
      }
      if (!$title) $title=$options['page'];
      if (!$found) {
        $formatter->send_title("Error: No entry found!","",$options);
        return;
      }
      $formatter->send_title(sprintf(_("Add Comment to \"%s\""),$title),"",$options);
    } else {
      $formatter->send_title(sprintf(_("Add Blog entry to \"%s\""),$options['page']),"",$options);
    }
    print "<form method='post' action='$url'>\n";
    if ($options['value'])
      print "<input type='hidden' name='value' value='$options[value]' />\n";
    else
      print "<b>Title</b>: <input name='title' value='$options[title]' size='70' maxlength='70' style='width:200' /><br />\n";
    print <<<FORM
<textarea class="wiki" id="content" wrap="virtual" name="savetext"
 rows="$rows" cols="$cols" style="width:100%">$savetext</textarea><br />
<input type="hidden" name="action" value="Blog" />
<input type="submit" value="Save" />&nbsp;
<input type="reset" value="Reset" />&nbsp;
<input type="submit" name="button_preview" value="Preview" />
$extra
</form>
FORM;
  }
  $formatter->show_hints();
  print "<div class='hint'>"._("<b>horizontal rule</b> ---- does not applied on the blog mode.")."</div>";
  if ($options['button_preview'] && $options['savetext']) {
    if ($options['title'])
      $formatter->send_page("== $options[title] ==\n");
    $formatter->send_page($savetext);
  }
  $formatter->send_footer("",$options);
  return;
}

?>
