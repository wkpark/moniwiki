<?php
// Copyright 2003-2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a TrackBack send action plugin for the MoniWiki
//
// $Id: sendping.php,v 1.15 2010/08/22 08:00:21 wkpark Exp $

# trackback ping
function do_sendping($formatter,$options) {
  global $DBInfo, $_release;

  if (!$formatter->page->exists()) {
    $options['msg']=_("Error: Page Not found !");
    do_invalid($formatter,$options);
    return;
  }

  if (strtolower($DBInfo->charset) == 'utf-8')
    $checked='checked="checked"';

  if (!$options['trackback_url']) {
    $url=$formatter->link_url($formatter->page->urlname);

    $raw_body=$formatter->page->_get_raw_body();
    if ($options['value']) {

      $lines=explode("\n",$raw_body);
      $count=count($lines);
      # add comment
      for ($i=0;$i<$count;$i++) {
        if (preg_match("/^({{{)?#!blog (.*)$/",$lines[$i],$match)) {
          if (md5($match[2]) == $options['value']) {
            list($tag, $user, $date, $title) = explode(" ",$lines[$i],4);
            $found=1;
            if ($match[1]) $end_tag='}}}';
            break;
          }
        }
      }

      if ($found) { # a blog page with multiple entries
        $i++;
        if ($end_tag)
          for (;$i<$count;$i++) {
            if (preg_match("/^}}}$/",$lines[$i])) break;
            else if (preg_match("/^----$/",$lines[$i])) break;
            $excerpt.=$lines[$i]."\n";
          }
        else { # a blog page with a single entry
            list($dummy,$entry)=explode("\n",$raw_body,2);
            list($excerpt,$comments)=explode("\n----\n",$entry,2);
        }
      } else {
        $options['msg']=_("Error: No entry found!");
        do_invalid($formatter,$options);
        return;
      }
    } else { # a plain wiki page
      $excerpt=substr($raw_body,0,400);
      $title=$options['page'];
    }

    $COLS_MSIE = 80;
    $COLS_OTHER = 85;
    $cols = preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT']) ? $COLS_MSIE : $COLS_OTHER;

    $rows=$options['rows'] > 5 ? $options['rows']: 8;
    $cols=$options['cols'] > 60 ? $options['cols']: $cols;

    $formatter->send_header("",$options);
    $formatter->send_title(_("Send TrackBack ping"),"",$options);
    $msg1 = _("TrackBack Ping URL");
    print "<form method='post' action='$url'>\n";
    print "<b>$msg1</b>: <input name='trackback_url' size='60' maxlength='256' style='width:200' /><br />\n";
    if ($options['value']) {
      $options['value'] = _html_escape($options['value']);
      print "<input type='hidden' name='value' value=\"$options[value]\" />\n";
    }
    $msg2 = _("Title");
    $title = _html_escape($title);
    print "<b>$msg2</b>: <input name='title' value=\"$title\" size='70' maxlength='70' style='width:200' /><br />\n";
    if ($DBInfo->use_resizer > 1)
      echo <<<JS
<script type="text/javascript" src="$DBInfo->url_prefix/local/textarea.js"></script>
JS;
    print <<<FORM
<div class="resizable-textarea" style='position:relative'><!-- IE hack -->
<textarea class="wiki resizable" id="content" wrap="virtual" name="excerpt"
 rows="$rows" cols="$cols" class="wiki">$excerpt</textarea></div>
FORM;

    $mb_msg = _("mb encoded");
    $send_msg = _("Send ping");
    $reset = _("Reset");
    print <<<FORM2
<b>$mb_msg</b> <input type="checkbox" name="mbencode" $checked />&nbsp;
<input type="hidden" name="action" value="sendping" />
<span class="button"><input class="button" type="submit" value="$send_msg" /></span>&nbsp;
<span class="button"><input class="button" type="reset" value="$reset" /></span>&nbsp;
</form>
FORM2;
    $formatter->send_footer("",$options);

    return;
  }
  # send Trackback ping

  $trackback_url=$options['trackback_url'];
  $title= urlencode(_stripslashes($options['title']));
  $blog_name= urlencode($DBInfo->sitename.":$options[id]");

  $excerpt= _stripslashes($options['excerpt']);

  if ($options['mbencode']) {
    if ($checked and function_exists('iconv')
        and strtolower($DBInfo->charset) != 'utf-8')
      $excerpt=iconv($DBInfo->charset,'utf-8',$excerpt);
    if (function_exists('mb_encode_numericentity')) {
      $new=mb_encode_numericentity($excerpt,$DBInfo->convmap,'utf-8');
      if ($new) $excerpt=$new;
      $new=mb_encode_numericentity($title,$DBInfo->convmap,'utf-8');
      if ($new) $title=$new;
    } else {
      include_once('lib/compat.php');
      $new=utf8_mb_encode($excerpt);
      if ($new) $excerpt=$new;
      $new=utf8_mb_encode($title);
      if ($new) $title=$new;
    }
  }

  $excerpt= urlencode($excerpt);

  $url= $formatter->link_url($options['page'],"#$options[value]");
  $url= urlencode(qualifiedUrl($url));

  $query_string= "title=$title&url=$url&blog_name=$blog_name&excerpt=$excerpt";

  if (strstr($trackback_url, '?')) {
    $trackback_url.= "&".$query_string;;
    $fp= @fopen($trackback_url, 'r');
    $result= @fread($fp, 4096);
    @fclose($fp);
/* debug code
    $debug_file = 'trackback.log';
    $fp = fopen($debug_file, 'a');
    fwrite($fp, "\n*****\nTrackback URL query:\n\n$trackback_url\n\nResponse:\n\n");
    fwrite($fp, $result);
    fwrite($fp, "\n\n");
    fclose($fp);
*/
  } else {
    $trackback_url = parse_url($trackback_url);

    $http_request  = 'POST '.$trackback_url['path']." HTTP/1.0\r\n";
    $http_request .= 'Host: '.$trackback_url['host']."\r\n";
    $http_request .= 'Content-Type: application/x-www-form-urlencoded'."\r\n";
    $http_request .= 'Content-Length: '.strlen($query_string)."\r\n";
    $http_request .= "\r\n";
    $http_request .= $query_string;

    $fs = @fsockopen($trackback_url['host'], 80);
    @fputs($fs, $http_request);
/* debug code
    $debug_file = 'trackback.log';
    $fp = fopen($debug_file, 'a');
    fwrite($fp, "\n*****\nRequest:\n\n$http_request\n\nResponse:\n\n");
    while(!@feof($fs)) {
      fwrite($fp, @fgets($fs, 4096));
    }
    fwrite($fp, "\n\n");
    fclose($fp);
*/
    @fclose($fs);
  }

  $formatter->send_header("",$options);
  $formatter->send_title(_("Trackback sent"),"",$options);
  #$formatter->send_page("Return: $result");
  print "Return: $result";
  $formatter->send_footer("",$options);
  return;
}

// vim:et:sts=2:sw=2
?>
