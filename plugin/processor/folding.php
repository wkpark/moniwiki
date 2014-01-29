<?php
// Copyright 2003-2014 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a folding area processor plugin for the MoniWiki
//
// Author: Dongsu Jang <iolo at hellocity.net>
// Since: 2007-05-15
// Date: 2014-01-29
// Name: Folding processor
// Description: Folding Area Processor
// URL: MoniWiki:FoldingProcessor
// Version: $Revision: 1.2 $
// License: GPLv2
//
// Usage: {{{#!folding [[+|-],[class-name],]title
// Hello World
// }}}
// $Id: folding.php,v 1.1 2007/05/15 11:18:40 iolo Exp $

function processor_folding($formatter,$value="",$options=array()) {
    // unique id of the folding area
    $id = isset($GLOBALS['_folding_id_']) ? $GLOBALS['_folding_id_'] : 0;
    $id++;
    $GLOBALS['_folding_id_'] = $id;

    if ($value[0] == '#' and $value[1] == '!')
        list($line, $value) = explode("\n", $value, 2);

    $init_state = 'none';
    $title = _("More");
    $class = '';
    $opened = '';

    // parse args
    if (isset($line[0]) and ($p = strpos($line, ' ')) !== false) {
        $tag = substr($line, 0, $p);
        $args = substr($line, $p + 1);

        if (preg_match("/^(?:(open|\+))?(?(1)[ ]*,[ ]*)?((?:(?:[a-zA-Z][a-z0-9_-]+)[ ]*)*)?(?(2)[ ]*,[ ]*)?/", $args, $matches)) {
            $class = isset($matches[2][0]) ? ' '.$matches[2] : '';
            $tmp = substr($args, strlen($matches[0]));
            if (isset($tmp[0])) $title = $tmp;

            if ($matches[1] == 'open' or $matches[1] == '+') {
                $init_state = 'block';
                $opened = ' class="opened"';
            }
        }
    }

    // allow wiki syntax in folding content
    ob_start();
    $params = array('notoc'=>1);
    $params['nosisters'] = 1;
    $formatter->send_page($value, $params);
    $out = ob_get_contents();
    ob_end_clean();

    $onclick = " onclick=\"var f=document.getElementById('folding_$id');var s=f.style.display=='block';".
        "f.style.display=s?'none':'block';this.className=s?'':'opened';\"";

    return <<<HERE
<div class="folding-area$class">
<dl class="folding">
<dt$onclick$opened>$title</dt>
<dd id="folding_$id" style="display:$init_state;">$out</dd>
</dl>
</div>
HERE;
}

// vim:et:sts=4:sw=4:
