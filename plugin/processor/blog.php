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

function processor_blog($formatter,$value="",$options) {
  static $date_anchor="";
  global $DBInfo;

  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);
  if ($line) {
    # get parameters
    list($tag, $user, $date, $title)=explode(" ",$line, 4);

    if (preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/',$user))
      $user="Anonymous[$user]";
    else if ($DBInfo->hasPage($user)) {
      $user=$formatter->link_tag($user);
    }

    if ($date && $date[10] == 'T') {
      $date[10]=' ';
      $time=strtotime($date." GMT");
      $date= date("m-d [h:i a]",$time);
      $anchor= date("d",$time);
      if ($date_anchor != $anchor) {
        $datetag= "<div class='blog-date'>".date("M d, Y",$time)." <a name='$anchor' id='$anchor'></a><a class='purple' href='#$anchor'>#</a></div>";
        $date_anchor= $anchor;
      }
    }
    $md5sum=md5(substr($line,7));
    if (!$options['noaction'])
    $comment_action="<div class='blog-user'>&raquo; ".$formatter->link_tag($formatter->page->urlname,"?action=blog&amp;value=$md5sum",_("Add comment"))."</div>\n";
  }

  $src= rtrim($value);

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
      $comments= "<div class='blog-comments'>".ob_get_contents()."</div>";
      ob_end_clean();
    } else
      $comments="";
  }

  $out="$datetag<div class='blog'>";
  if ($title) {
    #$tag=normalize($title);
    $tag=$md5sum;
    if ($tag[0]=='%') $tag="n".$tag;
    $purple="<a class='purple' href='#$tag'>#</a>";
    $title=preg_replace("/(".$formatter->wordrule.")/e",
                        "\$formatter->link_repl('\\1')",$title);
    $out.="<div class='blog-title'><a name='$tag' id='$tag'></a>$title $purple</div>\n";
  }
  $out.="<div class='blog-user'>Submitted by $user @ $date</div>\n".
    "<div class='blog-comment'>$msg$comments$comment_action</div>\n".
    "</div>\n";
  return $out;
}

// vim:et:ts=2:
?>
