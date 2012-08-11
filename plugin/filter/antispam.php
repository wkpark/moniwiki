<?php
// Copyright 2005-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Antispam filter plugin for the MoniWiki
//
// $Id: antispam.php,v 1.5 2010/04/19 11:26:47 wkpark Exp $

function filter_antispam($formatter,$value,$options) {
    $blacklist_pages=array('BadContent','LocalBadContent');
    $whitelist_pages=array('GoodContent','LocalGoodContent');
    if (! in_array($formatter->page->name,$blacklist_pages) and
        ! in_array($formatter->page->name,$whitelist_pages)) {

        $badcontent = '';
        foreach ($blacklist_pages as $list) {
            $p=new WikiPage($list);
            if ($p->exists()) $badcontent.=$p->get_raw_body();
        }
        if (!$badcontent) return $value;
        $badcontents=explode("\n",$badcontent);
        $pattern[0]='';
        $i=0;
        foreach ($badcontents as $line) {
            if (isset($line[0]) and $line[0]=='#') continue;
            $line=preg_replace('/[ ]*#.*$/','',$line);
            $test=@preg_match("/$line/i","");
            if ($test === false) $line=preg_quote($line,'/');
            if ($line) $pattern[$i].=$line.'|';
            if (strlen($pattern[$i])>4000) {
                $i++;
                $pattern[$i]='';
            }
        }
        for ($k=0;$k<=$i;$k++)
            $pattern[$k]='/('.substr($pattern[$k],0,-1).')/i';

        #foreach ($whitelist_pages as $list) {
        #    $p=new WikiPage($list);
        #    if ($p->exists()) $goodcontent.=$p->get_raw_body();
        #}
        #$goodcontents=explode("\n",$goodcontent);

        return preg_replace($pattern,"''''''[[HTML(<span class='blocked'>)]]\\1[[HTML(</span>)]]''''''",$value);
    }
    return $value;
}
// vim:et:sts=4:
?>
