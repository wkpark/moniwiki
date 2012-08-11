<?php
// Copyright 2008 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a texturize posfilter plugin for the MoniWiki
//
// Author: Michel Valdrighi
// Description: wp/b2 texturize postfilter plugin
// URL: http://trac.wordpress.org/browser/trunk/wp-includes/functions.php?rev=601
// License: GPLv2
// 
// $Id: texturize.php,v 1.1 2008/12/30 09:03:02 wkpark Exp $

function postfilter_texturize($formatter, $text, $options) {
    $output = '';
    // Capture tags and everything inside them
    $textarr = preg_split("/(<[^>]+>)/s", $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    $stop = count($textarr); $next = true; // loop stuff
    for ($i = 0; $i < $stop; $i++) {
        $curl = $textarr[$i];

        if (isset($curl{0}) && '<' != $curl{0} && $next) { // If it's not a tag
            $curl = str_replace('---', '&#8212;', $curl);
            $curl = str_replace(' -- ', ' &#8212; ', $curl);
            $curl = str_replace('--', '&#8211;', $curl);
            $curl = str_replace('xn&#8211;', 'xn--', $curl);
            $curl = str_replace('...', '&#8230;', $curl);
            $curl = str_replace('``', '&#8220;', $curl);

            // This is a hack, look at this more later. It works pretty well though.
            $cockney = array("'tain't","'twere","'twas","'tis","'twill","'til","'bout","'nuff","'round","'cause");
            $cockneyreplace = array("&#8217;tain&#8217;t","&#8217;twere","&#8217;twas","&#8217;tis","&#8217;twill","&#8217;til","&#8217;bout","&#8217;nuff","&#8217;round","&#8217;cause");
            $curl = str_replace($cockney, $cockneyreplace, $curl);

            $curl = preg_replace("/'s/", '&#8217;s', $curl);
            $curl = preg_replace("/'(\d\d(?:&#8217;|')?s)/", "&#8217;$1", $curl);
            $curl = preg_replace('/(\s|\A|")\'/', '$1&#8216;', $curl);
            $curl = preg_replace('/(\d+)"/', '$1&#8243;', $curl);
            $curl = preg_replace("/(\d+)'/", '$1&#8242;', $curl);
            $curl = preg_replace("/(\S)'([^'\s])/", "$1&#8217;$2", $curl);
            $curl = preg_replace('/(\s|\A)"(?!\s)/', '$1&#8220;$2', $curl);
            $curl = preg_replace('/"(\s|\S|\Z)/', '&#8221;$1', $curl);
            $curl = preg_replace("/'([\s.]|\Z)/", '&#8217;$1', $curl);
            $curl = preg_replace("/ \(tm\)/i", ' &#8482;', $curl);
            $curl = str_replace("''", '&#8221;', $curl);
            
            $curl = preg_replace('/(\d+)x(\d+)/', "$1&#215;$2", $curl);

        } elseif (strstr($curl, '<code') || strstr($curl, '<pre') || strstr($curl, '<kbd' || strstr($curl, '<style') || strstr($curl, '<script'))) {
            // strstr is fast
            $next = false;
        } else {
            $next = true;
        }
        $curl = preg_replace('/&([^#])(?![a-z12]{1,8};)/', '&#038;$1', $curl);
        $output .= $curl;
    }
    return $output;
}
// vim:et:sts=4:
?>
