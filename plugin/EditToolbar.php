<?php
// Copyright 2004 Won-Kyu Park <wkpark at kldp.org>
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

   $iconset='mediawiki';
   $imgdir=$DBInfo->imgs_dir.'/plugin/EditToolbar/'.$iconset;
   $script=<<<EOS
<script type="text/javascript" src="$DBInfo->url_prefix/local/wikibits.js"></script>
<script language="JavaScript" type='text/javascript'>
/*<![CDATA[*/
document.writeln("<div id='toolbar'>");
addButton('$imgdir/button_bold.png','Bold text','\'\'\'','\'\'\'','Bold text');
addButton('$imgdir/button_italic.png','Italic text','\'\'','\'\'','Italic text');
addButton('$imgdir/button_link.png','Internal link','[',']','Link title');
addButton('$imgdir/button_extlink.png','External link (remember http:// prefix)','[',']','http://www.example.com link title');
addButton('$imgdir/button_headline.png','Level 2 headline','\\n== ',' ==\\n','Headline text');
addButton('$imgdir/button_image.png','Embedded image','attachment:','','Example.jpg');
addButton('$imgdir/button_media.png','Media file link','[[Media(',')]]','Example.mp3');
addButton('$imgdir/button_math.png','Mathematical formula (LaTeX)','\$\$ ',' \$\$ ','Insert formula here');
addButton('$imgdir/button_nowiki.png','Ignore wiki formatting','{{{','}}}','Insert non-formatted text here');

addButton('$imgdir/button_sig.png','Your signature with timestamp','@SIG@','','');
addButton('$imgdir/button_hr.png','Horizontal line (use sparingly)','\\n----\\n','','');
addInfobox('Click a button to get an example text','Please enter the text you want to be formatted.\\\\n It will be shown in the infobox for copy and pasting.\\\\nExample:\\\\n\$1\\\\nwill become:\\\\n\$2');
document.writeln("</div>");
/*]]>*/
</script>
EOS;

    return $script;
}

// vim:et:sts=4:
?>
