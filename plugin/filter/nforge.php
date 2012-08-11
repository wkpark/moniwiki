<?php
// Copyright 2008 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a simple filter plugin for the nforge
//
// Usage: set $filters='nforge'; in the config.php
//   $filters='abbr,nforge';
//
// $Id: nforge.php,v 1.2 2009/01/02 19:50:36 wkpark Exp $

function filter_nforge($formatter,$value,$options) {
    global $Config;

    preg_match("@\/([^\/]+)$@", $formatter->url_prefix, $proj_name);
    $group_id = $Config['group_id'];

    $issue = qualifiedUrl('/tracker/index.php?func=detail&group_id='.$group_id.'&aid=');
    $svn = qualifiedUrl('/scm/viewvc.php/?root='.$proj_name[1].'&view=rev&revision=');

    $_rule=array(
        # link to an issue #210
        '/(?<![a-zA-Z])\!?\#([0-9]+)/',
        # link to an revision r452
        "/(?<![a-zA-Z])\!?r([0-9]+)/",
    );
    $_repl=array(
        "[^$issue".'\\1 #\\1]',
        "[^$svn".'\\1 r\\1]',
    );
    return preg_replace($_rule,$_repl,$value);
}

function postfilter_nforge($formatter,$value,$options) {
    global $Config;

    preg_match("@\/([^\/]+)$@", $formatter->url_prefix, $proj_name);
    $group_id = $Config['group_id'];
    $issue = qualifiedUrl('/tracker/index.php?func=detail&group_id='.$group_id.'&aid=');
    $svn = qualifiedUrl('/scm/viewvc.php/?root='.$proj_name[1].'&view=rev&revision=');

    $_rule=array(
        # link to an issue #210
        '/(?<![a-zA-Z&])\!?\#([0-9]+)/',
        # link to an revision r452
        "/(?<![a-zA-Z&])\!?r([0-9]+)/",
    );
    $_repl=array(
        "<a href='$issue".'\\1\'>#\\1</a>',
        "<a href='$svn".'\\1\'>r\\1</a>',
    );

    $chunks=preg_split('/(<[^>]+>)/',$value,-1, PREG_SPLIT_DELIM_CAPTURE);
    for ($i=0,$sz=count($chunks); $i<$sz; $i++) {
        if ($i % 2 == 0) {
            $chunks[$i] = preg_replace($_rule,$_repl,$chunks[$i]);
        }
    }
    return implode('',$chunks);
}
// vim:et:sts=4:sw=4:
?>
