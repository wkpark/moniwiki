<?php
// Copyright 2008 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a korean indexer filter plugin for the MoniWiki
//
// $Id: indexer_ko.php,v 1.3 2010/07/29 15:51:13 wkpark Exp $
//

include_once(dirname(__FILE__).'/../../lib/stemmer.ko.php');

function filter_indexer_ko($formatter,$value,&$options) {
    $more_specific_len=1;
    $indexer=new KoreanStemmer();

    if ($options['value'])
        $value=$options['value'];

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
    $log='';
    $tag=array('+','-');
    foreach ($keys as $i=>$key) {
        $match=null;
        if ($stem=$indexer->getStem(trim($key),$match,$type)) {
            $log.= $key.'=>'.$stem.$tag[$type-1].'/'.$match[1]."\n";
            if ($type==1)
                $keys[$i]=$stem;
            else
                unset($keys[$i]);
        } else {
            $log.= '='.$keys[$i]."\n";
            $keys[$i]=$keys[$i];
        }
    }
    if ($options['debug']) {
        $options['timer']->Check("indexer");
        return $log."\n".$options['timer']->Write();
    }
    return implode("\n",array_unique($keys));
}

// vim:et:sts=4:sw=4:
?>
