<?php
// Copyright 2005-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a asciimathml processor plugin by AnonymousDoner
//
// Author: Won-Kyu Park <wkpark@kldp.org> and AnonymousDoner
// Date: 2007-11-02
// Name: a AsciiMathML processor
// Description: It support AsciiMathML
// URL: MoniWiki:AsciiMathML
// Version: $Revision: 1.11 $
// License: GPL
//
// please see http://kldp.net/forum/message.php?msg_id=9419
//
// download the following javascript in the local/ dir to enable this processor:
//  http://www1.chapman.edu/~jipsen/mathml/ASCIIMathML.js
//  and add small code or set $_add_func=1;
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
// $Id: asciimathml.php,v 1.11 2010/08/16 11:07:19 wkpark Exp $

function processor_asciimathml($formatter,$value="") {
  global $DBInfo;

  if ($value[0]=='#' and $value[1]=='!')
  list($line,$value)=explode("\n",$value,2);

  if (!empty($line) and strpos($line, ' ') !== FALSE)
    list($tag,$args)=explode(' ',$line,2);

  # 1 or 0
  $_add_func=1;
  # customizable variables
  $edit_mathbgcolor='yellow';
  $myfontfamily='Palatino Linotype'; # or serif
  $myfontcolor='#2171B1'; # red(default), black etc.

  #
  $flag = 0;
  if (empty($formatter->wikimarkup)) {
    // use a md5 tag with a wikimarkup action
    $cid=&$GLOBALS['_transient']['asciimathml'];
    if ( !$cid ) { $flag = 1; $cid = 1; }
    $id=$cid;
    $cid++;

    # wikimarkup specific settings
    $bgcolor = '';
    $fontcolor="mathcolor='$myfontcolor';\n";
  } else {
    $flag = 1;
    $id=md5($value.'.'.microtime());

    # normal settings
    $bgcolor="mathbgcolor='$edit_mathbgcolor';\n";
  }
  $fontfamily="mathfontfamily='$myfontfamily';\n";

  if ( $flag ) {
    if ($formatter->register_javascripts('ASCIIMathML.js'));

    if ($_add_func)
      $js=<<<AJS
<script type="text/javascript">
/*<![CDATA[*/
function translateById(objId,flag) {
  var AMbody = document.getElementById(objId);
  // for WikiWyg mode switching
  if (typeof math2ascii != "undefined") math2ascii(AMbody);
  if (isIE) { // for WikiWyg in the iframe
    var nd = AMisMathMLavailable();
    AMnoMathML = nd != null;

    if (AMnoMathML) {
      AMbody.insertBefore(nd,AMbody.childNodes[0]);
    } else {
      AMbody.innerHTML=AMparseMath(AMbody.innerHTML.replace(/\\$/g,'')).innerHTML;
      //needed to match size and font of formula to surrounding text
      AMbody.getElementsByTagName('math')[0].update();
    }
  } else {
    AMprocessNode(AMbody, false);
  }
}

$bgcolor$fontfamily$fontcolor
if (window.MathJax) { mathfontfamily = ''; mathcolor = ''; }
// AMinitSymbols();
/*]]>*/
</script>
AJS;
    if ($js) $formatter->register_javascripts($js);
  }

  $out = "<span><span class=\"AM\" id=\"AM-$id\">$value</span>" .
    "<script type=\"text/javascript\">translateById('AM-$id');".
    "</script></span>";
  return $out;
}

// vim:et:sts=2:sw=2
