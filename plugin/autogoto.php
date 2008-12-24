<?php
// Copyright 2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// AutoGoto plugin
//
// Usage: set $auto_search='AutoGoto'; in the config.php
//
// $Id$

function do_AutoGoto($formatter,$options) {
    global $DBInfo;

    if ($DBInfo->autogoto_options) {
        $opts=explode(',',$DBInfo->autogoto_options);
        foreach ($opts as $opt) {
            $opt=trim($opt);
            if ($opt=='man') {
                $v=explode(' ',trim($formatter->page->name));
                if (strtolower($v[0])=='man') {
                    $options['url']=
                        $formatter->link_url('ManPage',
                        "?action=man_get&man=".$v[1]);
                    do_goto($formatter,$options);
                    return true;
                }
            }
        }
    }
   
    $npage=str_replace(' ','',$formatter->page->name);
    if ($DBInfo->hasPage($npage)) {
        $options['value']=$npage;
        do_goto($formatter,$options);
        return true;
    } else if (function_exists('iconv')) {
        if (strtolower($DBInfo->charset) != 'utf-8' ) {
            $t = @iconv('UTF-8',$DBInfo->charset,$formatter->page->name);
            if ($t and $DBInfo->hasPage($t)) {
                $options['value']=$t;
                do_goto($formatter,$options);
                return true;
            }
        } else if (!empty($DBInfo->url_encodings)) {
            $cs = explode(',',$DBInfo->url_encodings);
            foreach ($cs as $c) {
                $t = @iconv($c, $DBInfo->charset, $formatter->page->name);
                if ($t and $DBInfo->hasPage($t)) {
                    $options['value']=$t;
                    do_goto($formatter,$options);
                    return true;
                }
            }
        }
    }
    $options['value']=$formatter->page->name;
    $options['check']=1;
    if (do_titlesearch($formatter,$options))
        return true;
    $options['value']=$formatter->page->name;
    # do not call AutoGoto recursively
    $options['redirect']=1;
    do_goto($formatter,$options);
    return true;
}

// vim:et:sts=4:
?>
