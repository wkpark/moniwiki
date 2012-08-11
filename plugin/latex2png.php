<?php
// Copyright 2008 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a latex2png plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2008-12-01
// Name: Latex To PNG plugin
// Description: convert latex syntax to PNGs
// URL: MoniWiki:Latex2PngPlugin
// Version: $Revision: 1.5 $
// License: GPL
//
// Usage: ?action=latex2png&value=$\alpha$
//
// $Id: latex2png.php,v 1.5 2010/09/09 14:42:06 wkpark Exp $

function macro_latex2png($formatter,$value,$params=array()) {
    $png= $formatter->processor_repl('latex',$value, $params);
    return $png;
}

function do_latex2png($formatter,$options) {
    $retval = false;
    $opts = array();
    $opts['retval'] = &$retval;
    if (isset($options['dpi']) and $options['dpi'] > 120 and $options['dpi'] < 600)
        $opts['dpi']=$options['dpi'];
    $my= $formatter->processor_repl('latex',$options['value'],$opts);
    if (!empty($opts['retval']))
        $png = $opts['retval'];
    else
        $png = $my;

    $mtime = filemtime($png);
    $lastmod = gmdate('D, d M Y H:i:s \G\M\T', $mtime);
    $etag = md5($mtime);

    $headers = array();
    $headers[] = 'Pragma: cache';
    $maxage = 60*60*24*7;
    $headers[] = 'Cache-Control: private, max-age='.$maxage;
    $headers[] = 'Last-Modified: '.$lastmod;
    $headers[] = 'ETag: "'.$etag.'"';
    $need = http_need_cond_request($mtime, $lastmod, $etag);
    if (!$need)
        $headers[] = 'HTTP/1.0 304 Not Modified';
    foreach ($headers as $h)
        header($h);
    if (!$need) {
        @ob_end_clean();
        return;
    }

    if (file_exists($png)) {
        Header("Content-type: image/png");
        readfile($png);
    } else {
        // 43byte 1x1 transparent gif
        // http://stackoverflow.com/questions/2933251/code-golf-1x1-black-pixel
        // http://www.perlmonks.org/?node_id=7974
        $gif = base64_decode('R0lGODlhAQABAJAAAAAAAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw');
        Header("Content-type: image/gif");
        Header("Content-length: ".strlen($gif));
        header('Connection: Close');
        echo $gif;
        flush();
    }
}

// vim:et:sts=4:
?>
