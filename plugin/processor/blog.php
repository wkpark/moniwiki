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
      $date= "@ ".date("m-d [h:i a]",$time);
      $anchor= date("d",$time);
      if ($date_anchor != $anchor) {
        $datetag= "<div class='blog-date'>".date("M d, Y",$time)." <a name='$anchor' id='$anchor'></a><a class='purple' href='#$anchor'>$formatter->purple_icon</a></div>";
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

      if ($options['noaction'] or $DBInfo->show_comments)
        $comments=preg_replace("/----\n/","[[BR]]-''''''---[[BR]]",$comments);
      else {
        $comments='';
        $add_button=($count == 1) ? _("%d comment"):_("%d comments");
        $add_button=sprintf($add_button,$count);
      }
    }

    if (!$options['noaction'] and $md5sum) {
      $action= $formatter->link_tag($formatter->page->urlname,"?action=blog&amp;value=$md5sum",$add_button);
      if (getPlugin('SendPing'))
        $action.= ' | '.$formatter->link_tag($formatter->page->urlname,"?action=sendping&amp;value=$md5sum",_("send ping"));
    }

    if ($action)
      $action="<div class='blog-action'>&raquo; ".$action."</div>\n";

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
    $purple="<a class='purple' href='#$tag'>$formatter->purple_icon</a>";
    $title=preg_replace("/(".$formatter->wordrule.")/e",
                        "\$formatter->link_repl('\\1')",$title);
    $out.="<div class='blog-title'><a name='$tag' id='$tag'></a>$title $purple</div>\n";
  }
  $out.="<div class='blog-user'>Submitted by $user $date</div>\n".
    "<div class='blog-comment'>$msg$comments$action</div>\n".
    "</div>\n";
  return $out;
}

// vim:et:ts=2:
?>
