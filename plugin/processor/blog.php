<?
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// Blog plugin for the MoniWiki
//
// Usage: {{{#!blog ID @date@ title
// Hello World
// }}}
// this processor is used internally by the Blog action
// $Id$

function processor_blog($formatter,$value="") {
  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);
  if ($line) {
    # get parameters
    list($tag, $user, $date, $title)=explode(" ",$line, 4);

    if (preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/',$user))
      $user="Anonymous[$user]";

    if ($date && $date[10] == 'T') {
      $date[10]=' ';
      $time=strtotime($date." GMT");
      $date= "@ ".date("Y-m-d [h:i a]",$time);
    }
    $md5sum=md5($line);
    $purple="<a class='purple' href='#$md5sum'>#</a>";
    $comment_action="<div class='blog_user'>&raquo; ".$formatter->link_tag($formatter->page->urlname,"?action=blog&amp;value=$md5sum",_("Add comment"))."</div>\n";
  }

  $src= $value;

  if ($src) {
    $options['nosisters']=1;
    $temp=explode("----\n",$src);
    $comments="";
    if ($comments=array_slice($temp,1)) {
      $comments=join("[[BR]]-''''''---[[BR]]",$comments);
      $src=$temp[0];
    }
    ob_start();
    $formatter->send_page($src,$options);
    $msg= ob_get_contents();
    ob_end_clean();
    if ($comments) {
      ob_start();
      $formatter->send_page($comments,$options);
      $comments= "<div class='blog_comments'>".ob_get_contents()."</div>";
      ob_end_clean();
    } else
      $comments="";
  }

  $out="<div class='blog'>";
  if ($title) {
    $title=preg_replace("/(".$formatter->wordrule.")/e",
                        "\$formatter->link_repl('\\1')",$title);
    $out.="<div class='blog_title'><a name='$md5sum' id='$md5sum'>$title$purple</div>\n";
  }
  $out.="<div class='blog_user'>Submitted by $user $date</div>\n".
    "<div class='blog_comment'></a>$msg$comments$comment_action</div>\n".
    "</div>\n";
  return $out;
}

// vim:et:ts=2:
?>
