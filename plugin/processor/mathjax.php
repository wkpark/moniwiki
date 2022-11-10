<?php
// Copyright 2022 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a mathjax processor plugin
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2022-10-29
// Name: a MathJAX processor
// Description: support MathJAX
// URL: MoniWiki:MathJaxProcessor
// Version: $Revision: 1.0 $
// License: GPL
//
// to changes this processor as a default inline latex formatter:
// 1. set $inline_latex='mathjax';
// 2. replace the latex processor: $processors=array('latex'=>'mathjax');
//

function processor_mathjax($formatter, $value = '') {
    global $DBInfo;

    if ($value[0] == '#' and $value[1] == '!')
        list($line, $value) = explode("\n", $value, 2);

    if (!empty($line) and strpos($line, ' ') !== FALSE)
        list($tag, $args) = explode(' ', $line, 2);

    $flag = 0;
    if (empty($formatter->wikimarkup)) {
        // use a md5 tag with a wikimarkup action
        $cid = &$GLOBALS['_transient']['mathjax'];
        if (!$cid) { $flag = 1; $cid = 1; }
        $id = $cid;
        $cid++;
    } else {
        $flag = 1;
    }

    if ($flag) {
        $mathjax = <<<CONF
<script type="text/x-mathjax-config">
MathJax.Hub.Config({
  tex2jax: {inlineMath: [['$','$'], ['\\\\(','\\\\)']]}
});
</script>
CONF;
        $formatter->register_javascripts('//cdnjs.cloudflare.com/ajax/libs/mathjax/2.7.4/MathJax.js?config=TeX-AMS_CHTML');
        $formatter->register_javascripts($mathjax);
    }

    $out = "<span><span class=\"AM\" id=\"AM-$id\">$value</span>" .
        "</span>";
    return $out;
}

// vim:et:sts=4:sw=4
