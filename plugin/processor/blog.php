<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// Blog plugin for the MoniWiki
//
// Usage: {{{#!blog ID @date@ title
// Hello World
// }}}
// this processor is used internally by the Blog action
// $Id: blog.php,v 1.29 2010/08/23 09:20:34 wkpark Exp $

function processor_blog($formatter,$value="",$options) {
  static $date_anchor='';
  global $DBInfo;
  #static $tackback_list=array();

  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);

  $datetag = '';
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
      if (!$DBInfo->mask_hostname and $DBInfo->interwiki['Whois'])
        #$user=_("Anonymous")."[<a href='".$DBInfo->interwiki['Whois']."$user'>$user</a>]";
        $user="<a href='".$DBInfo->interwiki['Whois']."$user'>"._("Anonymous")."</a>";
      else
        $user=_("Anonymous");
    } else if ($DBInfo->hasPage($user)) {
      $user=$formatter->link_tag($user);
    }

    if ($date && $date[10] == 'T') {
      $date[10]=' ';
      $time=strtotime($date." GMT");
      $date= gmdate("m-d [h:i a]",$time+$formatter->tz_offset);
      $pagename=$formatter->page->name;
      $p=strrpos($pagename,'/');
      if ($p and preg_match('/(\d{4})(-\d{1,2})?(-\d{1,2})?/',substr($pagename,$p),$match)) {
        if ($match[3]) $anchor='';
        else if ($match[2]) $anchor= gmdate("d",$time);
        else if ($match[1]) $anchor= gmdate("md",$time);
      } else
        $anchor= gmdate("Ymd",$time);
      if ($date_anchor != $anchor) {
        $anchor_date_fmt=$DBInfo->date_fmt_blog;
        $datetag= "<div class='blog-date'>".date($anchor_date_fmt,$time)." <a name='$anchor'></a><a class='perma' href='#$anchor'>$formatter->perma_icon</a></div>";
        $date_anchor= $anchor;
      }
    }
    $md5sum=md5(substr($line,7));
  }

  $src= rtrim($value);

  if (!empty($src)) {
    $options['nosisters']=1;
    $options['nojavascript']=1;
    $tmp = explode("----\n",$src,2);
    $src = $tmp[0];
    if (!empty($tmp[1])) $comments = $tmp[1];

    $add_button= _("Add comment");
    if (!empty($comments)) {
      $count=sizeof(explode("----\n",$comments));

      if (!empty($options['noaction']) or !empty($DBInfo->blog_comments)) {
        $comments=preg_replace("/----\n/","[[HTML(</div></div><div class='separator'><hr /></div><div class='blog-comment'><div>)]]",$comments);
      } else {
        $comments='';
        $add_button=($count == 1) ? _("%s comment"):_("%s comments");
        $count_tag = '<span class="count">'.$count.'</span>';
        $add_button=sprintf($add_button,$count_tag);
      }
    }

    if (!empty($formatter->trackback_list[$md5sum])) $counter=' ('.$formatter->trackback_list[$md5sum].')';
    else $counter='';

    if (empty($options['noaction']) and $md5sum) {
      $action= $formatter->link_tag($formatter->page->urlname,"?action=blog&amp;value=$md5sum#BlogComment",$add_button);
      if (getPlugin('SendPing'))
        $action.= ' | '.$formatter->link_tag($formatter->page->urlname,"?action=trackback&amp;value=$md5sum",_("track back").$counter);
      if (!empty($DBInfo->use_rawblog))
        $action.= ' | '.$formatter->link_tag($formatter->page->urlname,"?action=rawblog&amp;value=$md5sum",_("raw"));
    }

    if (!empty($action))
      $action="<div class='blog-action'><span class='bullet'>&raquo;</span> ".$action."</div>\n";
    else
      $action='';

    $save=!empty($formatter->preview) ? $formatter->preview : '';
    $formatter->preview=1;
    ob_start();
    $formatter->send_page($src,$options);
    $msg= ob_get_contents();
    ob_end_clean();
    if (!empty($comments)) {
      ob_start();
      $formatter->send_page($comments,$options);
      $comments= "<div class='blog-comments'><div class='blog-comment'>".ob_get_contents()."</div></div>";
      ob_end_clean();
    } else
      $comments="";
    !empty($save) ? $formatter->preview=$save : null;
  }

  $out="$datetag<div class='blog'>";
  if (!empty($title)) {
    #$tag=normalize($title);
    $tag=$md5sum;
    if ($tag[0]=='%') $tag="n".$tag;
    $perma="<a class='perma' href='#$tag'>$formatter->perma_icon</a>";
    $title=preg_replace_callback("/(".$formatter->wordrule.")/",
            array(&$formatter, 'link_repl'),$title);
    $out.="<div class='blog-title'><a name='$tag'></a>$title $perma</div>\n";
  }
  $info = sprintf(_("Submitted by %s @ %s"), $user, $date);
  $out.="<div class='blog-user'>$info</div>\n".
    "<div class='blog-content'>$msg</div>$comments$action\n".
    "</div>\n";
  return $out;
}

// vim:et:sts=2:
?>
