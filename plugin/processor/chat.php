<?
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// sample plugin for the MoniWiki
//
// Usage: {{{#!chat ID @date@ title
// Hello World
// }}}
// this processor is used internally by the Blog action
// $Id$
// vim:et:ts=2:

function processor_chat($formatter,$value="") {
  $lines=explode("\n",$value);
  $tag=substr($lines[0],0,6);
  if ($tag=='#!chat') {
    # get parameters
    list($user, $date, $title)=explode(" ",substr($lines[0],7), 3);

    if (preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/',$user))
      $user="Anonymous[$user]";

    if ($date && $date[10] == 'T') {
      $date[10]=' ';
      $time=strtotime($date." GMT");
      $date= "@ ".date("Y-m-d [h:i a]",$time);
    }
    unset($lines[0]);
  }

  $src= join("\n",$lines);

  $options[nosisters]=1;
  ob_start();
  $formatter->send_page($src,$options);
  $msg= ob_get_contents();
  ob_end_clean();

  $out="<table align='center' width='90%' border='0' class='wiki' cellpadding='4' cellspacing='0'>";
  if ($title)
    $out.="<tr><td><b>$title</b></td></tr>\n";
  $out.="<tr><td><font size='-1'>Submitted by $user $date</font></td></tr>\n".
    "<tr><td class='wiki'>$msg</td></tr>\n".
    "</table>\n";
  return $out;
}

?>
