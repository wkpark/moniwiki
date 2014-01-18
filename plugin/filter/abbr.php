<?php
// Copyright 2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a abbr postfilter plugin for the MoniWiki
//
// $Id: abbr.php,v 1.1 2005/08/31 11:34:28 wkpark Exp $

function postfilter_abbr($formatter,$value,$options) {
    global $DBInfo;
    $abbrs=array();
    if (!$DBInfo->local_abbr or !$DBInfo->hasPage($DBInfo->local_abbr))
        return $value;

    $p=$DBInfo->getPage($DBInfo->local_abbr);
    $raw=$p->get_raw_body();
    $lines=explode("\n",$raw);
    foreach ($lines as $line) {
        $line=trim($line);
        if ($line[0]=='#' or $line=='') continue;
        $word=strtok($line,' ');
        $abbrs[$word]=strtok('');
    }
    $dict=new SimpleDict($abbrs);
    $rule=implode('|',array_keys($abbrs));
    $chunks=preg_split('/(<[^>]*>)/',$value,-1, PREG_SPLIT_DELIM_CAPTURE);
    for ($i=0,$sz=count($chunks); $i<$sz; $i++) {
        if ($chunks[$i][0]=='<') continue;
        $dumm=preg_replace_callback('/\b('.$rule.')\b/',
            array($dict, 'get'), $chunks[$i]);
        $chunks[$i]=$dumm;
    }

    return implode('',$chunks);
}

class SimpleDict {
    var $dicts=array();
    function SimpleDict($dicts) {
        $this->dicts=$dicts;
    }
    function get($word) {
        if (is_array($word))
            $word = $word[1];
        #if ($word[0]=='<') return $word;
        if (isset($this->dicts[$word]))
            return '<abbr title="'.$this->dicts[$word].'">'.$word.'</abbr>';
        return $word;
    }
}

#function _abbr_repl($abbrs,$word) {
#    if ($abbrs[$word] != '')
#        return '<abbr title="'.$abbrs[$word].'">'.$word.'</abbr>';
#    return $word;
#}
// vim:et:sts=4:
?>
