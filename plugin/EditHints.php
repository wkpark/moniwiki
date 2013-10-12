<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// EditHints plugin
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Since: 2003-08-01
// Name: Edit Hints macro
// Description: Show some simple hints of wiki markups
// URL: MoniWiki:EditHintsMacro
// Version: $Revision: 1.1 $
// License: GPL
// Usage: [[EditHints]] or [[EditHints(js)]]
//
// $Id: EditHints.php,v 1.1 2010/08/13 18:55:08 wkpark Exp $

function macro_EditHints($formatter, $value = '') {
  global $DBInfo;

  $hints = "<div class=\"wikiHints\">\n";

  $opt = (empty($value) and !empty($DBInfo->wikihints_option)) ? $DBInfo->wikihints_option : $value;
  $help_page = 'WikiTutorial';

  if ($opt == 'js') {
    $wikihints_openbutton_onclick = <<<JS
document.getElementById("wikiHints_opened_head").style.display = "block";
document.getElementById("wikiHints_closed_head").style.display = "none";
document.getElementById("wikiHints_content").style.display = "block";
JS;

    $wikihints_closebutton_onclick = <<<JS
document.getElementById("wikiHints_opened_head").style.display = "none";
document.getElementById("wikiHints_closed_head").style.display = "block";
document.getElementById("wikiHints_content").style.display = "none";
JS;

    $hints.= "<div id='wikiHints_closed_head' class='head'>";
    $hints.= "<div class='open-button' onclick='$wikihints_openbutton_onclick'><span class='mark'>?</span><span class='message'> "._("Editing Hints")."</span></div><div class='clear'></div>";
    $hints.= "</div>";

    /* wikiHints_opened_head is hiding because wikiHints is closed basically. */
    $hints.= "<div id='wikiHints_opened_head' class='head' style='display: none'>";
    $hints.= "<div class='tutorial-link'>"._("See more help:").' '.$formatter->link_tag($help_page)."</div><div class='close-button' onclick='$wikihints_closebutton_onclick'><span class='mark'>&times;</span><span class='message'> "._("Close Editing Hints")."</span></div><div class='clear'></div>";
    $hints.= "</div>";
  
    $display = 'style="display: none"';
  } else {
    $display = '';
  }

  $hints.= "<p id='wikiHints_content' $display>";
  $hints.= _("<b>Emphasis:</b> '''<strong>bold</strong>''', ''<i>italics</i>''<br />\n<b>Headings:</b> ==<span class='space'> </span>Title 2<span class='space'> </span>==; ===<span class='space'> </span>Title 3<span class='space'> </span>===; ...<br />\n<b>Lists:</b> <span class='space'> </span>*<span class='space'> </span> space and one of * bullets; <span class='space'> </span>1.<span class='space'> </span>numbered items; <span class='space'> </span> space alone indents.<br />\n<b>Links:</b> [[double bracketed words]]; [bracketed words]; JoinCapitalizedWords; [\"brackets and double quotes\"];\nurl; [url]; [url label].<br />\n<b>Tables</b>: || cell text |||| cell text spanning two columns ||;\nno trailing white space allowed after tables or titles.<br />\n");
  $hints.= "</p>";
  $hints.= "</div>\n";
  return $hints;
}

