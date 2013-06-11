<?php
// Copyright 2013 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Backlinks wrapper plugin
//

require_once('plugin/FullSearch.php');

function do_backlinks($formatter, $params = array()) {
    $params['action'] = 'fullsearch';
    $params['backlinks'] = 1;
    do_fullsearch($formatter, $params);
    return true;
}

// vim:et:sts=4:
