<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Vote plugin for the MoniWiki
// vim:et:ts=2:
//
// Usage: [[Vote(Hello 10, World 20,Wow 1)]]
//
// $Id$

function macro_Vote($formatter,$value) {
  global $DBInfo;

  $imgdir=$DBInfo->imgs_dir;

  $md5=md5($value);
  $temps=explode(",",$value);
  $total=0;
  foreach ($temps as $item) {
    if (trim($item)=='off') {
      $vote_off=1;
      continue;
    }
    $test=preg_match("/(^.+)\s+(\d+)$/",$item,$match);
    if (!$test) return "[[Vote(<font color='red'>error !</font>$value)]]";
    $votes[$match[1]]=$match[2];
    $total+=$match[2];
  }

  $bra_bar="<img src='$imgdir/leftbar.gif'>";
  $cat_bar="<img src='$imgdir/rightbar.gif'>";

  $out='';
  if (!$vote_off)
    $out.="<form method='post'>
<input type='hidden' name='ticket' value='$md5' />
<input type='hidden' name='action' value='vote' />";
  $out.="<table class='vote'>\n";
  while (list($item,$count)= each($votes)) {
    if ($total > 0) $ratio= 100 * $count/$total;
    $bar_width=intval($ratio);

    $bar=$bra_bar.
         "<img width='$bar_width' height='14' src='$imgdir/mainbar.gif'>".
         $cat_bar;
    $md5=md5($item);
    $out.="<tr><td>$item </td><td nowrap='nowrap'>$bar</td><td>".
         sprintf("%3d (%3.2f %%)",$count,$ratio);
    if (!$vote_off)
      $out.="<input type='radio' name='vote' value='$md5' />";
    $out.="</td></tr>\n";
  }
  $out.="<tr><td colspan='2' align='right'><b>Total votes</b></td><td align='center'>$total";
  if (!$vote_off)
    $out.="<input type='submit' value='Vote' /></td></tr>\n</table></form>\n";
  else
    $out.="</td></tr>\n</table>\n";

  return $out;
}

function do_vote($formatter,$options) {
  global $DBInfo;

  if ($options['id'] == 'Anonymous') {
    $options['msg'].="\n"._("Please Login or make your ID on this Wiki ;)");
    do_invalid($formatter,$options);
    return;
  }
  if (!$options['ticket'] and !$options['vote'])
    return '<html><h1>Error</h1></html>';
  $body=$formatter->page->get_raw_body();

  $lines=explode("\n",$body);

  $count=count($lines);
  for ($i=0;$i<$count;$i++) {
    if($test=preg_match_all("/\[\[Vote\(([^\]]+)\)\]\]/",$lines[$i],$tickets)) {
      foreach ($tickets[1] as $ticket) {
        if (md5($ticket) == $options[ticket]) {
          $save=$ticket;
          $temps=explode(",",$ticket);
          foreach ($temps as $item) {
            preg_match("/(^.+)\s+(\d+)$/",$item,$match);
            $votes[$match[1]]=(int) $match[2];
            if (md5($match[1]) == $options[vote]) {
              $votes[$match[1]]++;
              $voted=1;
            }
          }

          if ($voted) {
            while (list($item,$count)=each($votes))
              $args.="$item $count,";

            $args=substr($args,0,-1);
            $lines[$i]=
              str_replace("[[Vote($save)]]","[[Vote($args)]]",$lines[$i]);
            break;
          }
        }
      }
    }
  }

  if ($voted) {
    $formatter->page->write(join("\n",$lines));
    $DBInfo->savePage($formatter->page,"Vote",$options);
    $options[msg]=_("Voted successfully");
  }

  $formatter->send_header("",$options);
  $formatter->send_title("","",$options);

  $formatter->send_page();
  $formatter->send_footer("",$options);
  return;
}

?>
