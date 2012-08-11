<?php
// Copyright 2007 by keizie <keizie at gmail.com>
// All rights reserved. Distributable under GPL see COPYING
// a rawblog plugin for the MoniWiki
//
// Author: Keizie <keizie@gmail.com>
// Date: 2007-08-01
// Name: Raw blog action plugin
// Description: Raw blog action Plugin to extract a blog entry from a Blog page.
// URL: MoniWiki:RawBlogPlugin
// Version: $Revision: 1.3 $
// License: GPL
//
// Usage: ?action=rawblog
//
// $Id: rawblog.php,v 1.3 2010/08/23 09:20:34 wkpark Exp $

function do_rawblog($formatter,$options) {
  global $DBInfo;
  global $HTTP_USER_AGENT;
  $COLS_MSIE = 80;
  $COLS_OTHER = 85;
  $cols = preg_match('/MSIE/', $HTTP_USER_AGENT) ? $COLS_MSIE : $COLS_OTHER;

  $rows=(!empty($options['rows']) and $options['rows'] > 5) ? $options['rows']: 8;
  $cols=(!empty($options['cols']) and $options['cols'] > 60) ? $options['cols']: $cols;

  $url=$formatter->link_url($formatter->page->urlname);
  $formatter->send_header("",$options);

  $quote = '';
  { #
    $lines = array();
    $title = '';
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
          if (preg_match("/^}}}$/",$lines[$i])) {
            unset($lines[$i]);
            break;
          }
          $quote.=$lines[$i]."\n";
          unset($lines[$i]);
        }
        $quote=str_replace('\}}}','}}}',$quote);
      } else {
        $formatter->send_title("Error: No entry found!","",$options);
        $formatter->send_footer("",$options);
        return;
      }
      if (!isset($title[0])) $title=$options['page'];
      $formatter->send_title(sprintf(_("Delete Blog entry \"%s\""),$title),"",$options);
    }
    $options['noaction']=1;

    print <<<FORM
<textarea class="wiki" id="content" wrap="virtual" name="savetext"
 rows="$rows" cols="$cols" class="wiki">$quote</textarea><br />
FORM;
  }
  $options['savetext']=implode("\n",$lines);
  $options['editlog']=sprintf(_("Delete Blog entry \"%s\""),$title);
  echo macro_Edit($formatter,"",$options);
  $formatter->send_footer("",$options);
  return;
}

// vim:et:sts=2:
?>
