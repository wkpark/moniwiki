<?php
// Copyright 2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a sample plugin for the MoniWiki
//
// Usage: [[Test]]
//
// $Id: bts.php,v 1.1 2008/01/08 15:01:19 wkpark Exp $

include_once("lib/metadata.php");

function _get_btsConfig($raw) {
    $meta='';
    $body=&$raw;
    while(true) {
        list($line,$body)=explode("\n",$body,2);
        if ($line[0]=='#') continue;
        if (strpos($line,':')===false or trim($line)=='') break;
        $meta.=$line."\n";
    }

    return getMetadata($meta,1);
}

function do_bts($formatter,$options) {
    global $DBInfo;

    $fields=array('Type','Priority','Product','Severity','Summary','Keywords','Submit');

    $basic=<<<EOF
 * Type: Bug,Enhancement,Task,Support
EOF;

#    if ($DBInfo->hasPage($options['page'])) {
#        $p=new WikiPage($options['page']);
#        $bts_raw=$p->get_raw_body();
#        $meta=_get_btsConfig($bts_raw);
#    }
    $bts_conf='BugTrack/Config';
    if ($DBInfo->hasPage($bts_conf)) {
        $p=new WikiPage($bts_conf);
        $config_raw=$p->get_raw_body();
        if (substr($basic,-1,1)!="\n") $basic.="\n";
        $confs=_get_btsConfig($basic.$config_raw);
        #print_r($confs);
    }

    $myform='';
    foreach ($fields as $field) {
        if (isset($confs[$field]))
            $myform.=':'.$field.':'.$confs[$field]."\n";
        else if ($field=='Submit') {
            $myform.="hidden:action:bts\n";
            $myform.="hidden:mode:write\n";
            $myform.='submit::'.$field."\n";
        } else
            $myform.='input:'.$field."\n";
    }
    #header("Content-Type:text/plain");
    #print '<pre>';
    #print $myform;
    #print '</pre>';
    $formatter->send_header('',$options);

    $formatter->send_title('','',$options);
    print $formatter->processor_repl('form',$myform,$options);

#    # parse metadata
#    $meta='';
#    while(true) {
#        list($line,$body)=explode("\n",$body,2);
#        if ($line[0]=='#') continue;
#        if (strpos($line,':')===false or !trim($line)) break;
#        $meta.=$line."\n";
#    }
#    print "<pre>";
#    print_r($options);
#    print "</pre>";
#
#
#    foreach ($meta as $k=>$v) {
#        if (!empty($options[$k])) $meta[$k]=$options[$k];
#    }
#    print "<pre>";
#    print_r($meta);
#    print "</pre>";


    $formatter->send_footer('',$options);
    return;
}

// vim:et:sts=4:sw=4:
?>
