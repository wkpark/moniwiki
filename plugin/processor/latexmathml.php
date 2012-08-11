<?php
// Copyright 2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a latexmathml processor plugin by AnonymousDoner
//
// modified version of the ASCIIMathML proocessor.
//
// Author: Won-Kyu Park <wkpark@kldp.org> and AnonymousDoner
// Since: 2010-08-16
// Name: a LaTeXMathML processor
// Description: support LaTeXMathML
// URL: MoniWiki:LatexMathML
// Version: $Revision: 1.2 $
// License: GPL
//
// to changes this processor as a default inline latex formatter:
// 1. set $inline_latex='latexmathml';
// 2. replace the latex processor: $processors=array('latex'=>'latexmathml');
//
// $Id: latexmathml.php,v 1.2 2010/08/16 11:07:19 wkpark Exp $

function processor_latexmathml($formatter,$value="") {
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
    $cid=&$GLOBALS['_transient']['latexmathml'];
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
    if ($formatter->register_javascripts('LaTeXMathML.js'));
      if (preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT']))
        $formatter->register_javascripts('<object id="mathplayer"'.
          ' classid="clsid:32F66A20-7614-11D4-BD11-00104BD3F987" width="1px" height="1px">'.
          '</object>'.
          '<?import namespace="mml" implementation="#mathplayer"?>'
      );

    if ($_add_func)
      $js=<<<AJS
<script type="text/javascript">
/*<![CDATA[*/
function LMtranslateById(objId,flag) {
  var LMbody = document.getElementById(objId);
  // for WikiWyg mode switching
  if (typeof math2latex != "undefined") math2latex(LMbody);
  if (isIE) { // for WikiWyg in the iframe
    var nd = LMisMathMLavailable();
    LMnoMathML = nd != null;

    if (LMnoMathML) {
      LMbody.insertBefore(nd,LMbody.childNodes[0]);
    } else {
      LMbody.innerHTML=LMparseMath(LMbody.innerHTML.replace(/\\$/g,'')).innerHTML;
      //needed to match size and font of formula to surrounding text
      LMbody.getElementsByTagName('math')[0].update();
    }
  } else {
    LMprocessNode(LMbody, false);
  }
}

$bgcolor$fontfamily$fontcolor
LMinitSymbols();
/*]]>*/
</script>
AJS;
    if ($js) $formatter->register_javascripts($js);
  }

  $out = "<span><span class=\"LM\" id=\"LM-$id\">$value</span>" .
    "<script type=\"text/javascript\">LMtranslateById('LM-$id');".
    "</script></span>";
  return $out;
}

// vim:et:sts=2:sw=2
