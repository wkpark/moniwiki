<?php
// Copyright 2008 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a simple Google Translation macro for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2007-09-30
// Name: GoogleTransMacro
// Description: Google Translation Macro
// URL: MoniWiki:GoogleTransMacro
// Version: $Revision: 1.1 $
// License: GPL
//
// Usage: [[GoogleTrans(en)]]
//
// $Id: GoogleTrans.php,v 1.1 2008/12/09 14:04:41 wkpark Exp $

function macro_GoogleTrans($formatter,$value) {
    global $DBInfo;

    $url=qualifiedUrl($formatter->link_url($formatter->page->name));
    $from = empty($value) ? substr($DBInfo->lang,0,2):$value;
    $enc = strtolower($DBInfo->charset);

    $img_dir=$DBInfo->imgs_dir.'/interwiki';

    $supported=array('en'=>'English','fr'=>'Francais','de'=>'Deutsch','es'=>'Espanol',
        'it'=>'Italiano','pt'=>'Portugues','zh'=>'Chinese',
        'ru'=>'Russian','ja'=>'Japanese','ko'=>'Korean');

    if (array_key_exists($from,$supported)) {
        unset($supported[$from]);
    } else
        $from = 'ko';

    $out='';
    foreach ($supported as $k=>$v)
         $out.= "<a target='_top' href='http://www.google.com/translate?hl=$from&amp;ie=$enc&amp;langpair=$from%7C"
             .$k."&amp;u=$url' title='"._($v)."'><img src='$img_dir/".$k."-16.png' style='border:0' /></a>";

    return $out;
}

// vim:et:sts=4:
?>
