<?php
// Copyright 2004 Yoon, Hyunho <hhyoon@kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a home action plugin for the MoniWiki
//
// $Id$

function do_home($formatter, $options)
{
    global $DBInfo;

    if ($options['id'] and $DBInfo->hasPage($options['id']))
        $options['page'] = $options['id'];
    else
        $options['page'] = 'FrontPage';

    $options['value'] = $options['page'];
    do_goto($formatter, $options);
    return;
}
?>
