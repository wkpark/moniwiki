<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a TrackBack send action plugin for the MoniWiki
// vim:et:ts=2:
//
// $Id$

# trackback ping
function do_sendping($formatter,$options) {
  global $DBInfo, $_release;

  if (!$formatter->page->exists()) {
    $options['msg']=_("Error: Page Not found !");
    do_invalid($formatter,$options);
    return;
  }

  if (!$options['trackback_url']) {
    $url=$formatter->link_url($formatter->page->urlname);

    $raw_body=$formatter->page->_get_raw_body();
    if ($options['value']) {

      $lines=explode("\n",$raw_body);
      $count=count($lines);
      # add comment
      for ($i=0;$i<$count;$i++) {
        if (preg_match("/^{{{#!blog .*$/",$lines[$i])) {
          if (md5(substr($lines[$i],10)) == $options['value']) {
            list($tag, $user, $date, $title) = explode(" ",$lines[$i],4);
            $found=1;
            break;
          }
        }
      }

      if ($found) {
        $i++;
        for (;$i<$count;$i++) {
          if (preg_match("/^}}}$/",$lines[$i])) break;
          else if (preg_match("/^----$/",$lines[$i])) break;
          $excerpt.=$lines[$i]."\n";
        }
      } else {
        $options['msg']=_("Error: No entry found!");
        do_invalid($formatter,$options);
        return;
      }
    } else {
      $excerpt=substr($raw_body,0,400);
      $title=$options['page'];
    }

    global $HTTP_USER_AGENT;
    $COLS_MSIE = 80;
    $COLS_OTHER = 85;
    $cols = preg_match('/MSIE/', $HTTP_USER_AGENT) ? $COLS_MSIE : $COLS_OTHER;

    $rows=$options['rows'] > 5 ? $options['rows']: 8;
    $cols=$options['cols'] > 60 ? $options['cols']: $cols;

    $formatter->send_header("",$options);
    $formatter->send_title(_("Send TrackBack ping"),"",$options);
    print "<form method='post' action='$url'>\n";
    print "<b>TrackBack Ping URL</b>: <input name='trackback_url' size='60' maxlength='100' style='width:200' /><br />\n";
    if ($options['value'])
      print "<input type='hidden' name='value' value='$options[value]' />\n";
    print "<b>Title</b>: <input name='title' value='$title' size='70' maxlength='70' style='width:200' /><br />\n";
    print <<<FORM
<textarea class="wiki" id="content" wrap="virtual" name="excerpt"
 rows="$rows" cols="$cols" class="wiki">$excerpt</textarea><br />
FORM;
    print <<<FORM2
<input type="hidden" name="action" value="sendping" />
<input type="submit" value="Send ping" />&nbsp;
<input type="reset" value="Reset" />&nbsp;
</form>
FORM2;
    $formatter->send_footer("",$options);

    return;
  }
  # send Trackback ping

  $trackback_url=$options['trackback_url'];

	$title= urlencode(stripslashes($options['title']));
	$excerpt= urlencode(stripslashes($options['excerpt']));
	$blog_name= urlencode($DBInfo->sitename);

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
  $formatter->send_title(_("Trackback sented"),"",$options);
  $formatter->send_page("Return: $result");
  $formatter->send_footer("",$options);
  return;
}

?>
