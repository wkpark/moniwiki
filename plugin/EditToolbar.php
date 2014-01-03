<?php
// Copyright 2004-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a EditToolbar plugin for the MoniWiki
//
// Usage: [[EditToolbar]]
//
// This feature is imported from the MediaWiki
//
// $Id: EditToolbar.php,v 1.16 2010/08/21 08:34:50 wkpark Exp $

function macro_EditToolbar($formatter,$value, $options=array()) {
    global $DBInfo;
    if (!empty($options['notoolbar'])) return '';

    $default = array('bold','italic','link','extlink','headline',
        'math','nowiki','hr','image','media','smiley','sig','infobox');

    $simple = array('bold','italic','link','extlink',
        'math','nowiki','image','media','smiley','sig','infobox');

    $btnset = 'default';
    if ($value == 'simple')
        $btnset = $value;

    $iconset=!empty($DBInfo->toolbar_iconset) ? $DBInfo->toolbar_iconset:
        'moniwiki';
    $imgdir=$DBInfo->imgs_dir.'/plugin/EditToolbar/'.$iconset;

    $buttons = array(
        'bold'=>
            "addButton('$imgdir/button_bold.png',N_('Bold text'),'\'\'\'','\'\'\'',N_('Bold text'));\n",
        'italic'=>
            "addButton('$imgdir/button_italic.png',N_('Italic text'),'\'\'','\'\'',N_('Italic text'));\n",
        'link'=>
            "addButton('$imgdir/button_link.png',N_('Internal link'),'[[',']]',N_('Link title'));\n",
        'extlink'=>
            "addButton('$imgdir/button_extlink.png',N_('External link (remember http:// prefix)'),'[[',']]',N_('http://www.example.com link title'));\n",
        'headline'=>
            "addButton('$imgdir/button_headline.png',N_('Level 2 headline'),'\\n== ',' ==\\n',N_('Headline text'));\n",
        'math'=>
            "addLinkButton('$imgdir/button_math.png',N_('Mathematical formula (LaTeX)'),'\$ ',' \$',N_('Insert latex formula here'),'mathChooser');\n",
        'nowiki'=>
            "addButton('$imgdir/button_nowiki.png',N_('Ignore wiki formatting'),'{{{','}}}',N_('Insert non-formatted text here'));\n",
        'hr'=>
            "addButton('$imgdir/button_hr.png',N_('Horizontal line (use sparingly)'),'\\n----\\n','','');\n",
        'image'=>
            "addButton('$imgdir/button_image.png',N_('Embedded image'),'attachment:','','Example.jpg');\n",
        'media'=>
            "addButton('$imgdir/button_media.png',N_('Media file link'),'[[Media(',')]]','Example.mp3');\n",
        'smiley'=>
            "addLinkButton('$imgdir/button_smiley.png',N_('Smiley'),'','',':)','smileyChooser',true);\n",
        'sig'=>
            "addButton('$imgdir/button_sig.png',N_('Your signature with timestamp'),'@SIG@','','');\n",
        'infobox'=>
            "addInfobox(N_('Click a button to get an example text'),N_('Please enter the text you want to be formatted.\\\\n It will be shown in the infobox for copy and pasting.\\\\nExample:\\\\n\$1\\\\nwill become:\\\\n\$2'));\n",
    );
    $formatter->register_javascripts("wikibits.js");
    $fcss= $DBInfo->imgs_real_dir.'/plugin/EditToolbar/'.$iconset.'/toolbar.css';
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

EOS;
    foreach (${$btnset} as $btn)
        $script.= $buttons[$btn];

    $script.=<<<EOS
document.writeln("</span></div>");
/*]]>*/
</script>
EOS;

    return $script;
}

// vim:et:sts=4:
?>
