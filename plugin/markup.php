<?php
// Copyright 2003-2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a markup plugin for the MoniWiki
//
// $Id$

function do_markup($formatter,$options) {
    $formatter->section_edit=0;
    $formatter->sister_on=0;
    $formatter->perma_icon='';

    //$options['fixpath']=1;
    $formatter->postfilters=array('fiximgpath');
    if (!$options['all']) $formatter->wikimarkup=1;
    if ($options['value']) {
        $formatter->send_page(_stripslashes($options['value']),$options);
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
    return;
}

// vim:et:sts=4:
?>
