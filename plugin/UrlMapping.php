<?php
// Copyright 2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a UrlMapping plugin for the MoniWiki
//
// Usage: [[UrlMapping]]
//
// $Id$

function macro_UrlMapping($formatter,$value,$options=array()) {
    global $DBInfo;

    #$options['load']=1;

    if (!isset($DBInfo->url_mapping_rule) or $options['init']) { #or $options['load']) {
        $mappings=array();

        if (file_exists($DBInfo->shared_url_mappings)) {
            $map=file($DBInfo->shared_url_mappings);

            for ($i=0,$sz=sizeof($map);$i<$sz;$i++) {
                $line=rtrim($map[$i]);
                if (!$line || $line[0]=='#' || $line[0]==' ') continue;
                if (preg_match("/^(http|ftp|mailto):/",$line)) {
                    $url=strtok($line,' ');$val=strtok('');
                    $mappings[$url]=trim($val);
                    $mapping_rule.=preg_quote($url,'/').'|';
                }
            }
            $mapping_rule=substr($mapping_rule,0,-1);
            $DBInfo->url_mappings=array_merge($DBInfo->url_mappings,$mappings);
            $DBInfo->url_mapping_rule.=$DBInfo->url_mapping_rule ?
                '|'.$mapping_rule:$mapping_rule;
        }
    }

    if ($options['init'] or !$DBInfo->url_mappings) return '';

    $out=array();
    foreach ($DBInfo->url_mappings as $k=>$v) {
        if (preg_match('/^(http|ftp|mailto)/',$v))
            $v='<a href="'.$v.'">'.$v.'</a>';
        $out[]='<tr><td>'.$k.'</td><td>'.$v.'</td></tr>';
    }

    return "<table class='urlMapping'>\n".implode("\n",$out)."</table>\n";
}

// vim:et:sts=4:
?>
