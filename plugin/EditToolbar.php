<?php
// Copyright 2004-2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a EditToolbar plugin for the MoniWiki
//
// Usage: [[EditToolbar]]
//
// This feature is imported from the MediaWiki
//
// $Id$

function macro_EditToolbar($formatter,$value) {
    global $DBInfo;

    $iconset=!empty($DBInfo->toolbar_iconset) ? $DBInfo->toolbar_iconset:
        'moniwiki';
    $imgdir=$DBInfo->imgs_dir.'/plugin/EditToolbar/'.$iconset;
    $formatter->register_javascripts("wikibits.js");
    $fcss= 'imgs/plugin/EditToolbar/'.$iconset.'/toolbar.css';
    $css='';
    if (file_exists($fcss))
        $css="<style type='text/css'>
@import url('$imgdir/toolbar.css');
</style>";
    $script=<<<EOS
$css
<script language="JavaScript" type='text/javascript'>
/*<![CDATA[*/
document.writeln("<div id='toolbar'><span>");
addButton('$imgdir/button_bold.png','Bold text','\'\'\'','\'\'\'','Bold text');
addButton('$imgdir/button_italic.png','Italic text','\'\'','\'\'','Italic text');
addButton('$imgdir/button_link.png','Internal link','[',']','Link title');
addButton('$imgdir/button_extlink.png','External link (remember http:// prefix)','[',']','http://www.example.com link title');
addButton('$imgdir/button_headline.png','Level 2 headline','\\n== ',' ==\\n','Headline text');
addLinkButton('$imgdir/button_math.png','Mathematical formula (LaTeX)','mathChooser');
addButton('$imgdir/button_nowiki.png','Ignore wiki formatting','{{{','}}}','Insert non-formatted text here');
addButton('$imgdir/button_hr.png','Horizontal line (use sparingly)','\\n----\\n','','');
addButton('$imgdir/button_image.png','Embedded image','attachment:','','Example.jpg');
addButton('$imgdir/button_media.png','Media file link','[[Media(',')]]','Example.mp3');
addLinkButton('$imgdir/button_smiley.png','Smiley','smileyChooser');
addButton('$imgdir/button_sig.png','Your signature with timestamp','@SIG@','','');
addInfobox('Click a button to get an example text','Please enter the text you want to be formatted.\\\\n It will be shown in the infobox for copy and pasting.\\\\nExample:\\\\n\$1\\\\nwill become:\\\\n\$2');
document.writeln("</span></div><div style='clear:both'></div>");
/*]]>*/
</script>
EOS;

    return $script;
}

// vim:et:sts=4:
?>
