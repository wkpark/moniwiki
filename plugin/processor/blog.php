<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// Blog plugin for the MoniWiki
//
// Usage: {{{#!blog ID @date@ title
// Hello World
// }}}
// this processor is used internally by the Blog action
// $Id$

function processor_blog($formatter,$value="",$options) {
  static $date_anchor='';
  global $DBInfo;
  #static $tackback_list=array();

  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);

  if ($date_anchor=='' and $DBInfo->use_trackback) {
    #read trackbacks and set entry counter
    $cache= new Cache_text('trackback');
    if ($cache->exists($formatter->page->name)) {
      $trackback_raw=$cache->fetch($formatter->page->name);

      $trackbacks=explode("\n",$trackback_raw);
      foreach ($trackbacks as $trackback) {
        list($dummy,$entry,$extra)=explode("\t",$trackback);
        if ($entry) {
          if($formatter->trackback_list[$entry]) $formatter->trackback_list[$entry]++;
          else $formatter->trackback_list[$entry]=1;
        }
      }
    }
  }
  #print($date_anchor);print_r($trackback_list);
  if ($line) {
    # get parameters
    list($tag, $user, $date, $title)=explode(" ",$line, 4);

    if (preg_match('/^[\d\.]+$/',$user)) {
      if ($DBInfo->interwiki['Whois'])
        $user=_("Anonymous")."[<a href='".$DBInfo->interwiki['Whois']."$user'>$user</a>]";
      else
        $user=_("Anonymous");
    } else if ($DBInfo->hasPage($user)) {
      $user=$formatter->link_tag($user);
    }

    if ($date && $date[10] == 'T') {
      $date[10]=' ';
      $time=strtotime($date." GMT");
      $date= "@ ".date("m-d [h:i a]",$time);
      $pagename=$formatter->page->name;
      $p=strrpos($pagename,'/');
      if ($p and preg_match('/(\d{4})(-\d{1,2})?(-\d{1,2})?/',substr($pagename,$p),$match)) {
        if ($match[3]) $anchor='';
        else if ($match[2]) $anchor= date("d",$time);
        else if ($match[1]) $anchor= date("md",$time);
      } else
        $anchor= date("Ymd",$time);
      if ($date_anchor != $anchor) {
        $datetag= "<div class='blog-date'>".date("M d, Y",$time)." <a name='$anchor'></a><a class='purple' href='#$anchor'>$formatter->purple_icon</a></div>";
        $date_anchor= $anchor;
      }
    }
    $md5sum=md5(substr($line,7));
  }

  $src= rtrim($value);

  if ($src) {
    $options['nosisters']=1;
    list($src,$comments)=explode("----\n",$src,2);

    $add_button= _("Add comment");
    if ($comments) {
      $count=sizeof(explode("----\n",$comments));

      if ($options['noaction'] or $DBInfo->blog_comments) {
        $comments=preg_replace("/----\n/","[[HTML(</div></div><div class='separator'><hr /></div><div class='blog-comment'><div>)]]",$comments);
      } else {
        $comments='';
        $add_button=($count == 1) ? _("%d comment"):_("%d comments");
        $add_button=sprintf($add_button,$count);
      }
    }

    if ($formatter->trackback_list[$md5sum]) $counter=' ('.$formatter->trackback_list[$md5sum].')';
    else $counter='';

    if (!$options['noaction'] and $md5sum) {
      $action= $formatter->link_tag($formatter->page->urlname,"?action=blog&amp;value=$md5sum",$add_button);
      if (getPlugin('SendPing'))
        $action.= ' | '.$formatter->link_tag($formatter->page->urlname,"?action=trackback&amp;value=$md5sum",_("track back").$counter);
    }

    if ($action)
      $action="<div class='blog-action'><span class='bullet'>&raquo;</span> ".$action."</div>\n";

    ob_start();
    $formatter->send_page($src,$options);
    $msg= ob_get_contents();
    ob_end_clean();
    if ($comments) {
      ob_start();
      $formatter->send_page($comments,$options);
      $comments= "<div class='blog-comments'><div class='blog-comment'>".ob_get_contents()."</div></div>";
      ob_end_clean();
    } else
      $comments="";
  }

  $out="$datetag<div class='blog'>";
  if ($title) {
    #$tag=normalize($title);
    $tag=$md5sum;
    if ($tag[0]=='%') $tag="n".$tag;
    $purple="<a class='purple' href='#$tag'>$formatter->purple_icon</a>";
    $title=preg_replace("/(".$formatter->wordrule.")/e",
                        "\$formatter->link_repl('\\1')",$title);
    $out.="<div class='blog-title'><a name='$tag'></a>$title $purple</div>\n";
  }
  $out.="<div class='blog-user'>Submitted by $user $date</div>\n".
    "<div class='blog-content'>$msg</div>$comments$action\n".
    "</div>\n";
  return $out;
}

// vim:et:sts=2:
?>
