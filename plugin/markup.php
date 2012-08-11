<?php
// Copyright 2003-2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a markup plugin for the MoniWiki
//
// $Id: markup.php,v 1.10 2010/04/17 12:07:26 wkpark Exp $

function do_markup($formatter,$options) {
    $formatter->section_edit=0;
    $formatter->sister_on=0;
    $formatter->perma_icon='';

    $formatter->get_javascripts(); // trash default javascripts

    //$options['fixpath']=1;
    $formatter->send_header("",$options);
    $formatter->postfilters=array('fiximgpath');
    if (empty($options['all'])) $formatter->wikimarkup=1;
    if (!empty($options['value'])) {
        $val=_stripslashes($options['value']);
        $val= preg_replace('/(\r\n|\n|\r)/',"\n",$val); // Win32 fix
        $formatter->send_page($val,$options);
    } else {
        if (isset($options['section'])) {
            $formatter->section_edit=1;
            $formatter->sect_num=$options['section'] - 1;
            $raw_body=$formatter->page->get_raw_body($options);
            $sections= _get_sections($raw_body);
            if ($sections[$options['section']]) {
                $raw_body = $sections[$options['section']];
                $formatter->send_page($raw_body,$options);
            }
        } else {
            $formatter->section_edit=1;
            $formatter->send_page('',$options);
        }
        #else ignore
    }
    print $formatter->get_javascripts();
    return;
}

// vim:et:sts=4:
?>
