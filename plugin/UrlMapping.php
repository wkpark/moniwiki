<?php
// Copyright 2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a UrlMapping plugin for the MoniWiki
//
// Usage: [[UrlMapping]]
//
// $Id: UrlMapping.php,v 1.6 2010/09/07 12:11:49 wkpark Exp $

function macro_UrlMapping($formatter,$value,$options=array()) {
    global $DBInfo;

    #$options['load']=1;

    $mapping_rule = '';
    while (!isset($DBInfo->url_mapping_rule) or $options['init']) { #or $options['load']) {
        $mappings=array();

        $cf=new Cache_text('settings');

        $force_init=0;
        if ($DBInfo->shared_url_mappings and $cf->mtime('urlmapping') < filemtime($DBInfo->shared_url_mappings) ) {
            $force_init=1;
        }
        if (!empty($formatter->refresh) and $cf->exists('urlmapping') and !$force_init) {
            $info=$cf->fetch('urlmapping');
            $DBInfo->url_mappings=$info['urlmapping'];
            $DBInfo->url_mapping_rule=$info['urlmappingrule'];

            break;
        }

        $DBInfo->url_mappings = array();
        $DBInfo->url_mapping_rule = '';
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
        $mappinginfo= array(
                'urlmapping'=>$DBInfo->url_mappings,
                'urlmappingrule'=>$DBInfo->url_mapping_rule
        );
        $cf->update('urlmapping',$mappinginfo);
        break;
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
