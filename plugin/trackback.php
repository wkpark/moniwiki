<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a TrackBack receive action plugin for the MoniWiki
//
// $Id: trackback.php,v 1.10 2010/09/07 12:11:49 wkpark Exp $

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

  $entry='';
  if (!$formatter->page->exists()) {
    $pos=strrpos($formatter->page->name,'/');
    if ($pos > 0) {
      $entry=substr($formatter->page->name,$pos+1);
      $pagename=substr($formatter->page->name,0,$pos);
      $page=new WikiPage($pagename);
      $formatter=new Formatter($page,$options);
      $options['page']=$pagename;
    } else {
      $options['msg']=_("Error: Page Not found !");
      send_error(1,$options['msg']);
    }
  }

  if (empty($options['url'])) {
    $anchor = '';
    if ($options['value']) $anchor='/'.$options['value'];

    $formatter->send_header("",$options);

    if ($DBInfo->use_trackback)
      $ping_url= qualifiedUrl($formatter->link_url($formatter->page->urlname.$anchor,"?action=trackback"));
    else
      $ping_url=_("TrackBack is not activated !");
    $sendping_action= $formatter->link_tag($formatter->page->urlname,"?action=sendping&amp;value=$options[value]",_("send ping"));
    $tb_cache= new Cache_text('trackback');
    if ($tb_cache->exists($options['page'])) {
      $formatter->send_title(sprintf(_("TrackBack list of %s"),$options['page']),"",$options);
      $trackbacks= explode("\n",$tb_cache->fetch($options['page']));

      unset ($trackbacks[sizeof($trackbacks)-1]); # trim the last empty line
      print "<div class='trackback-hint'><b>"._("TrackBack URL for this page:")."</b><br />\n$ping_url<br /><br />\n";
      print "<b>"._("Send TrackBack Ping to another Blog:")."</b> $sendping_action</div>\n<br />";
      foreach ($trackbacks as $trackback) {
        list($dummy,$entry,$url,$date,$sitename,$title,$excerpt)= explode("\t",$trackback);
        if ($anchor and '/'.$entry!=$anchor) continue;
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
      print "<div class='trackback-hint'><b>"._("TrackBack URL for this page:")."</b><br />\n$ping_url<br /><br />\n";
      print "<b>"._("Send TrackBack Ping to another Blog:")."</b> $sendping_action</div>\n";
    }
    $formatter->send_footer("",$options);
    return;
  } 
  if (!$DBInfo->use_trackback)
    send_error(1,"TrackBack is not enabled"); 

  if (empty($options['title']) or empty($options['excerpt']) or empty($options['blog_name']) or empty($options['url'])) send_error(1,"Invalid TrackBack Ping");
  # receivie Trackback ping

  # strip \n
	$title= strtr(_stripslashes($options['title']),"\t\n"," \r");
	$excerpt= strtr(_stripslashes($options['excerpt']),"\t\n"," \r");
	$blog_name= strtr(_stripslashes($options['blog_name']),"\t\n"," \r");
	$url= strtr(_stripslashes($options['url']),"\t\n"," \r");

  $timestamp=time();
  $date= gmdate("Y-m-d\TH:i:s",$timestamp);

  $receive= $timestamp."\t".$entry."\t".$url."\t".$date."\t".$blog_name."\t".$title."\t".$excerpt."\n";

  $tb_cache = new Cache_text('trackback');

  $old= $tb_cache->fetch($options['page']);
  $ret= $tb_cache->update($options['page'],$old.$receive,time());
  if ($ret === false)
    send_error(0,"Can't update Trackback list. Please try again");

  send_error(0,'Successfully added');
}

class TrackBack_text {
  function get_trackbacks() {
    global $DBInfo;

    $handle = @opendir($DBInfo->cache_dir."/trackback");
    if (!$handle) return array();

    while ($file = readdir($handle)) {
      if ($file[0]=='.' or is_dir($DBInfo->cache_dir."/trackback/".$file)) continue;
      $blogs[] = $file;
    }
    closedir($handle);
    return $blogs;
  }

  function get_all() {
    global $DBInfo;
    $all=TrackBack_text::get_trackbacks();
    $lines=array();
    foreach ($all as $blog) {
      $name=$DBInfo->cache_dir."/trackback/".$blog;
      $pagename=$DBInfo->keyToPagename($blog);
      $items=file($name);
      foreach ($items as $line) $lines[]=$pagename."\t".substr($line,0,255);
    }
    return $lines;
  }
}

function TrackBackCompare($a,$b) {
  # second field is a unix timestamp
  if ($a[1] > $b[1]) return -1;
  if ($a[1] < $b[1]) return 1;
  return 0;
}

function macro_trackback($formatter,$value) {
  preg_match('/(\d+)?(\s*,?\s*.*)?$/',$value,$match);
  $opts=explode(",",$match[2]);
  if ($match[1]) $limit=$match[1];
  else $limit=10;
#  if (in_array('all',$opts))
  $lines=TrackBack_text::get_all();

  $date_fmt="m-d [h:i a]";
  $template_bra='';
  $template= '$out.= "<a href=\"$link\">$title</a>&nbsp;&nbsp;<span class=\"blog-user\">by <a href=\"$url\">$site</a> @ $date</span><br />\n";';
  $template_ket='';
  if (in_array('simple',$opts)) {
    $template_bra='<ul>';
    $template= '$out.= "<li><span class=\"blog-user\"><a href=\"$link\">$title</a></span><br/><span class=\"blog-user\">by <a href=\"$url\">$site</a> @ $date</span></li>\n";';
    $template_bra='</ul>';
    $date_fmt="m-d";
  }

  $logs=array();
  foreach ($lines as $line) $logs[]=explode("\t",$line,8);
  usort($logs,'TrackBackCompare');

  $out = '';
  foreach ($logs as $log) {
    if ($limit <= 0) break;
    list($page, $dum, $entry,$url,$date,$site,$title,$dum2)= $log;

    if (!$title) continue;
    if ($entry) $entry='&amp;value='.$entry;
    $link=$formatter->link_url(_urlencode($page),'?action=trackback'.$entry);

    $date[10]=' ';
    $time=strtotime($date." GMT");
    $date= date($date_fmt,$time);
    $tmp = explode(':',$site);
    $wiki = $tmp[0];
    if (!empty($tmp[1])) {
      $user = $tmp[1];
      $site = $tmp[1];
    }

    #$out.=$page."<a href='$url'>$title</a> @ $date from $site<br />\n";
    #$out.="<a href='$url'>$title</a> @ $date from $site<br />\n";
    eval($template);
    $limit--;
  }
  return $template_bra.$out.$template_ket;
}

// vim:et:sts=2:
?>
