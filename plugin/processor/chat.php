<?php
// Copyright 2003-2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a sample plugin for the MoniWiki
//
// Author: Your name <foobar@foo.bar>
// Date: 2006-01-01
// Name: Hello world2
// Description: Hello world2 Processor
// URL: to_plugin url/interwiki name etc.
// Version: $Revision: 1.6 $
// License: GPL
//
// Usage: {{{#!chat ID @date@ title
// Hello World
// }}}
//
// $Id: chat.php,v 1.6 2006/08/17 08:01:30 wkpark Exp $

function processor_chat($formatter,$value="") {
    if ($value[0]=='#' and $value[1]=='!')
        list($line,$value)=explode("\n",$value,2);
    if ($line) {
        # get parameters
        list($tag, $user, $date, $title)=explode(" ",$line, 4);

    if (preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/',$user))
        $user="Anonymous[$user]";

    if ($date && $date[10] == 'T') {
        $date[10]=' ';
            $time=strtotime($date." GMT");
            $date= "@ ".date("Y-m-d [h:i a]",$time);
        }
    }

    $src= $value;

    $formatter->set_wordrule();
    if ($src) {
        $options['nosisters']=1;
        ob_start();
        $formatter->send_page($src,$options);
        $msg= ob_get_contents();
        ob_end_clean();
    }

    $out="<table align='center' width='90%' border='0' class='wiki' cellpadding='4' cellspacing='0'>";
    if ($title) {
        $title = preg_replace_callback("/(".$formatter->wordrule.")/",
            array(&$formatter, 'link_repl'), $title);
        $out.="<tr><td><b>$title</b></td></tr>\n";
    }
    $out.="<tr><td><font size='-1'>Submitted by $user $date</font></td></tr>\n".
        "<tr><td class='wiki'>$msg</td></tr>\n".
        "</table>\n";
    return $out;
}

// vim:et:sts=4:
?>
