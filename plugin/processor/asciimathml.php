<?php
// Copyright 2005 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a asciimathml processor plugin by AnonymousDoner
//
// please see http://kldp.net/forum/message.php?msg_id=9419
//
// download the following javascript in the local/ dir to enable this processor:
//  http://www1.chapman.edu/~jipsen/mathml/ASCIIMathML.js
//  and add small code:
//-----x8-----
// function translateById(objId) {
//   AMbody = document.getElementById(objId);
//   AMprocessNode(AMbody, false);
//   if (isIE) { //needed to match size and font of formula to surrounding text
//     var frag = document.getElementsByTagName('math');
//     for (var i=0;i<frag.length;i++) frag[i].update()
//   }
// }
//   AMinitSymbols();
//-----x8-----
// to changes this processor as a default inline latex formatter:
// 1. set $inline_latex='asciimathml';
// 2. replace the latex processor: $processors=array('latex'=>'asciimathml');
//
// $Id$

function processor_asciimathml($formatter,$value="") {
  global $DBInfo;

  $flag = 0;
  $id=&$GLOBALS['_transient']['asciimathml'];
  if ( !$id ) { $flag = 1; $id = 1; }
  if ($value[0]=='#' and $value[1]=='!')
  list($line,$value)=explode("\n",$value,2);

  if ($line)
  list($tag,$args)=explode(' ',$line,2);

  if ( $flag ) {
    $out .= "<script type=\"text/javascript\" src=\"" .
    $DBInfo->url_prefix ."/local/ASCIIMathML.js\"></script>";
  }

  $out .= "<div id=\"asciimathml" . $id . "\">$value</div>" .
    "<script type=\"text/javascript\">translateById('asciimathml" . $id.
    "');</script>";
  $id++;
  return $out;
}
