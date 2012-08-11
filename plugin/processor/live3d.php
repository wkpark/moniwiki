<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Live3D plugin for the MoniWiki
//
// http://www.vis.uni-stuttgart.de/~kraus/LiveGraphics3D
//
// $Id: live3d.php,v 1.2 2010/04/19 11:26:47 wkpark Exp $

function processor_live3d($formatter,$value="") {
    if ($value[0]=='#' and $value[1]=='!')
      list($line,$value)=explode("\n",$value,2);
    $body = $value;
    $args='';

    while ($body and $body[0] == '#') {
        # extract first line
        list($line, $body) = explode("\n",$body, 2);

        # skip comments (lines with two hash marks)
        if ($line[1] == '#') continue;

        #print $line."<Br>";
        # parse the PI
        list($verb, $args) = explode(' ',$line,2);
        $verb = strtolower($verb);
        $args = rtrim($args);

        if ($verb == "#dependent") $dependent= $args;
        else if ($verb == "#independent") $independent= $args;
    }

    $extra= '';
    if (!empty($dependent))
        $extra.='<param name="DEPENDENT_VARIABLES" value="'.$dependent.'" />';
    if (!empty($independent))
        $extra.='<param name="INDEPENDENT_VARIABLES" value="'.$independent.'" />';

    $pubpath = $formatter->url_prefix.'/applets/LiveGraphics3D';

    return <<<APP
<applet code='Live.class' height='200' width='200'
    archive='$pubpath/live.jar' codebase='$pubpath'>
    $extra
    <param name="INPUT"
           value="$body" />
        Loading a JavaView object.
</applet>
APP;
}

// vim:et:sts=4:sw=4:
?>
