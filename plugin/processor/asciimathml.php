<?php
// Copyright 2005-2022 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a asciimathml processor plugin by AnonymousDoner
//
// Author: Won-Kyu Park <wkpark@kldp.org> and AnonymousDoner
// Since: 2007-11-02
// LastModified: 2022-10-29
// Name: a AsciiMathML processor
// Description: It support AsciiMathML
// URL: MoniWiki:AsciiMathML
// Version: $Revision: 1.12 $
// License: GPL
//
// Firefox and Safari support mathML but Chrome does not.
// to use AsciiMathML with Chrome add the following javascript:
// https://cdnjs.cloudflare.com/ajax/libs/mathjax/2.7.4/MathJax.js?config=TeX-AMS-MML_HTMLorMML
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
    $formatter->register_javascripts('ASCIIMathML.js');

    if ($_add_func)
      $js=<<<AJS
<script type="text/javascript">
/*<![CDATA[*/
function translateById(objId,flag) {
  var isIE = (navigator.appName.slice(0,9)=="Microsoft");
  var AMbody = document.getElementById(objId);
  // for WikiWyg mode switching

  if (isIE) { // for WikiWyg in the iframe
    var nd = AMisMathMLavailable();
    AMnoMathML = nd != null;

    if (AMnoMathML) {
      AMbody.insertBefore(nd,AMbody.childNodes[0]);
    } else {
      AMbody.innerHTML=asciimath.parseMath(AMbody.innerHTML.replace(/\\$/g,''), true).innerHTML;
      //needed to match size and font of formula to surrounding text
      AMbody.getElementsByTagName('math')[0].update();
    }
  } else {
    //asciimath.processNodeR(AMbody, false, false);
    asciimath.AMprocessNode(AMbody, false, false);
  }
}

$bgcolor$fontfamily$fontcolor
if (window.MathJax) { mathfontfamily = ''; mathcolor = ''; }
// AMinitSymbols();
/*]]>*/
</script>
AJS;
    $formatter->register_javascripts($js);
  }

  $out = "<span><span class=\"AM\" id=\"AM-$id\">$value</span>" .
    "<script type=\"text/javascript\">translateById('AM-$id');".
    "</script></span>";
  return $out;
}

// vim:et:sts=2:sw=2
