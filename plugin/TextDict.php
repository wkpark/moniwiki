<?php
// Copyright 2008 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a sample plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2008-05-02
// Name: TextDict
// Description: A Simple Text-Dictionary search plugin
// URL: MoniWiki:TextDict etc.
// Version: $Revision: 1.3 $
// License: GPL
//
// Usage: [[TextDict(word)]]
//
// $Id: TextDict.php,v 1.3 2008/11/27 08:43:06 wkpark Exp $
//

include_once(dirname(__FILE__).'/../lib/dict.text.php');
define('TEXT_DICT',dirname(__FILE__).'/../data/dict/word.txt.utf-8');

function macro_TextDict($formatter,$value,$params=array()) {
    global $Config;

    $fp=fopen(TEXT_DICT,'r');
    if (!is_resource($fp)) return '';
    $fs=fstat($fp);
    $fz=$fs['size'];

    $klen=0;

    list($l,$min_seek,$max_seek,$scount)=
        _fuzzy_bsearch_file($fp,$value,0,$fz/2,$klen,$fz);

    list($c,$buf,$last)=
        _file_match($fp,$value,$min_seek,$max_seek,$fz,$klen,false,'UTF-8');
    fclose($fp);
    #print 'scount='.$scount;
    #print $buf;
    return $buf;
}

function do_textdict($formatter,$options) {
    global $Config;

    $_debug=$options['debug'] ? $options['debug']:0;

    $formatter->send_header('',$options);
    $formatter->send_title('','',$options);

    if ($options['value'])
        $value=$options['value'];
    else
        $value=$formatter->page->get_raw_body($options);

    $delims=",.\|\n\r\s\(\)\[\]{}!@#\$%\^&\*\-_\+=~`';:'\"\?<>\/";

    # un-wikify CamelCase, change "WikiName" to "Wiki Name"
    $value=preg_replace("/((?<=[a-z0-9]|[B-Z]{2})([A-Z][a-z]))/"," \\1",$value);
    # separate alphanumeric and local characters
    $value=preg_replace("/((?<=[a-z0-9])([^a-z0-9]+))/i"," \\1",$value);

    $keys=preg_split("/[$delims]+/",$value);
    # must be longer than $more_specific_len.
    if ($more_specific_len > 0) {
        for ($i=0,$s=sizeof($keys);$i<$s;$i++)
            if (strlen($keys[$i])<=$more_specific_len) unset($keys[$i]);
    }

    sort($keys);$keys=array_unique($keys);

    $fp=fopen(TEXT_DICT,'r');
    if (!is_resource($fp)) return '';
    $fs=fstat($fp);
    $fz=$fs['size'];
    if ($_debug) $options['timer']->Check("read");

    foreach ($keys as $i=>$key) {
        list($l,$min_seek,$max_seek,$scount)= _fuzzy_bsearch_file($fp,$key,0,$fz/3,0,$fz);
        if ($_debug) $options['timer']->Check("seek");
        list($c,$buf,$last)=
            _file_match($fp,$key,$min_seek,$max_seek,$fz,0,true,'UTF-8');
        if ($_debug) {
            $options['timer']->Check("find");
            print 'found='.$c."<br />\n";
            print 'scount='.$scount."<br />\n";
            if ($last) print 'last='.$last."<br />\n";
            if ($_debug>50)
                if (!empty($buf)) print $buf."<br />\n";
            $options['timer']->Check("log");
        }
    }
    fclose($fp);

    if ($_debug) {
        print "total ".sizeof($keys)." words searched<br />\n";
        $options['timer']->Check("dict");
        print "<pre>";
        print $options['timer']->Write();
        print "</pre>";
    }
    $formatter->send_footer('',$options);
    return;
}

// vim:et:sts=4:sw=4:
?>
