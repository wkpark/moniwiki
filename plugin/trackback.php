<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a TrackBack receive action plugin for the MoniWiki
// vim:et:ts=2:
//
// $Id$

function send_error($error=0,$error_message='') {
  if ($error) {
    header("Content-type: text/xml");
    echo '<?xml version="1.0" encoding="iso-8859-1"?'.">\n";
    echo "<response>\n";
    echo "<error>1</error>\n";
    echo "<message>$error_message</message>\n";
    echo "</response>";
  } else {
    header("Content-type: text/xml");
    echo '<?xml version="1.0" encoding="iso-8859-1"?'.">\n";
    echo "<response>\n";
    echo "<error>0</error>\n";
    echo "</response>";
  }
  die(); 
}

# trackback - receive
function do_trackback($formatter,$options) {
  global $DBInfo, $_release;

  if (!$DBInfo->use_trackback)
    send_error(1,"TrackBack is not enabled"); 
  if (!$formatter->page->exists()) {
    $options['msg']=_("Error: Page Not found !");
    send_error(1,$options['msg']);
  }

  if (!$options['url']) {
    $formatter->send_header("",$options);

    $ping_url= qualifiedUrl($formatter->link_url($formatter->page->urlname,"?action=trackback"));
    $tb_cache=new Cache_text('trackback');
    if ($tb_cache->exists($options['page'])) {
      $formatter->send_title(sprintf(_("TrackBack list of %s"),$options['page']),"",$options);
      $trackbacks= explode("\n",$tb_cache->fetch($options['page']));

      unset ($trackbacks[sizeof($trackbacks)-1]); # trim the last empty line
      print "<div class='trackback-url'><b>TrackBack URL for this page:</b> $ping_url<br /><br /></div>\n";
      foreach ($trackbacks as $trackback) {
        list($url,$date,$sitename,$title,$excerpt)= explode("\t",$trackback);
        $date[10]=" ";
        # 2003-07-11T12:08:33+09:00
        # $time=strtotime($date);
        $time=strtotime($date);
        $date=date("@ m-d [h:i a]",$time);
        print "<div class='blog'>\n";
        print "<div class='blog-title'><a href='$url'>$title</a></div>\n";
        print "<div class='blog-user'>Submitted by <a href='$url'>$sitename</a> $date</div>\n";
        print "<div class='blog-comment'>$excerpt</div>\n</div><br />\n";
      }
    } else {
      $formatter->send_title(sprintf(_("No TrackBack entry found for %s"),$options['page']),"",$options);
      print "<div class='trackback-url'><b>TrackBack URL for this page:</b> $ping_url<br /></div>\n";
    }
    $formatter->send_footer("",$options);
    return;
  } 

  if (!$options['title'] or !$options['excerpt'] or !$options['blog_name'] or !$options['url']) send_error(1,"Invalid TrackBack Ping");
  # receivie Trackback ping

  # strip \n
	$title= strtr(stripslashes($options['title']),"\t\n"," \r");
	$excerpt= strtr(stripslashes($options['excerpt']),"\t\n"," \r");
	$blog_name= strtr(stripslashes($options['blog_name']),"\t\n"," \r");
	$url= strtr(stripslashes($options['url']),"\t\n"," \r");

  $date= gmdate("Y-m-d\TH:i:s",time());

  $receive= $url."\t".$date."\t".$blog_name."\t".$title."\t".$excerpt."\n";

  $tb_cache= new Cache_text('trackback');

  $old= $tb_cache->fetch($options['page']);
  $ret= $tb_cache->update($options['page'],$old.$receive,time());
  if ($ret === false)
    send_error(0,"Can't update Trackback list. Please try again");

  send_error(0,'Successfully added');
}

?>
