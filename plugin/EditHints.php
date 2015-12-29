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

function macro_EditHints($formatter, $value = '', $params = array()) {
    global $Config;

  $hints = "<div class=\"wikiHints\">\n";

  $opt = (empty($value) and !empty($Config['wikihints_option'])) ? $Config['wikihints_option'] : $value;
    $help_page = 'WikiTutorial';
    if (!empty($Config['wikihints_page']))
        $help_page = $Config['wikihints_page'];

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

    $hints .= "<p id='wikiHints_content' $display>";
    $hints .= _("<strong>Emphasis:</strong>");
    $hints .= _("'''<strong>bold</strong>''', ''<em>italics</em>''<br>\n<strong>Headings:</strong> ==<span class='space'> </span>Title 2<span class='space'> </span>==; ===<span class='space'> </span>Title 3<span class='space'> </span>===; ...<br />\n<strong>Lists:</strong> <span class='space'> </span>*<span class='space'> </span> space and one of * bullets; <span class='space'> </span>1.<span class='space'> </span>numbered items; <span class='space'> </span> space alone indents.")."<br />\n";
    $hints .= _("<strong>Links:</strong> [[Page Title]]; [[Page Title|label]]");
    $hints .= '; '._("[bracketed words]");
    if (!empty($Config['use_camelcase']))
        $hints .= '; '._("JoinCapitalizedWords");
    $hints .= '; '._("[\"brackets and double quotes\"]");
    $hints .= "<br />\n";
    $hints .= _("<strong>URLs:</strong> http://url");
    $hints .= '; '._("[http://url]; [http://url<span class='space'> </span>label]");
    $hints .= '; '._("[[http://url]]; [[http://url|label]]");
    $hints .= "<br />\n";
    $hints .= _("<strong>Tables:</strong> || cell text |||| cell text spanning two columns ||")."<br />\n";
    $hints .= _("<strong>No WikiTag:</strong> <code>{{{no wiki}}}</code>")."<br />\n";
    $hints .= _("no trailing white space allowed after tables or titles.");
    $hints .= "</p>";
    $hints .= "</div>\n";
  return $hints;
}

