<?php
// Copyright 2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a 'Keywords' plugin for the MoniWiki
//
// $Id$

function macro_Keywords($formatter,$value,$options='') {
    global $DBInfo;

    $common= <<<EOF
i am an a b c d e f g h i j k l m n o p q r s t u v w x y z
if on in by it at up down over into for from to of he his she her back
is are be or nor also and each all
too any with here
so such since because but however ever
it its the this that what where how when
you your will shall may might we us our
get got
EOF;
    if (!$value) $value=$formatter->page->name;
    $page=$DBInfo->getPage($value);
    if (!$page->exists()) return '';
    $raw=$page->get_raw_body();

    $raw=preg_replace("/([;\"',`\\\\\/\.:@\$%\^&\*\(\)\{\}\[\]\-_\+=\|])/",' ',
        $raw.' '.$value);
    $raw=preg_replace("/((?<=[a-z0-9]|[B-Z]{2})([A-Z][a-z]))/"," \\1",$raw);
    $raw=strtolower($raw);
    $words=preg_split("/[ ]+|\n/",$raw);
    $counts=array_count_values($words);
    $words=array_diff(array_keys($counts),preg_split("/[ ]|\n/",$common));

    if ($options['all']) {
        $cache=new Cache_text('keywords');
        if ($cache->exists($value)) {
            $keytext=$cache->fetch($value);
            $keys=explode("\n",rtrim($keytext));
        } else
            $keys=array();
        foreach ($keys as $key) {
            $counts[$key]=50; // give weight to all selected keywords
            $words[]=$key;
        }
    }

    if (function_exists('array_intersect_key')) {
        $words=array_intersect_key($counts,$words);
    } else {
        $ret = array();
        foreach($words as $key) {
            if(array_key_exists($key, $counts))
                $ret[$key] = $counts[$key];
        }
        $words=&$ret;
    }

    arsort($words);
    unset($words['']);

    $link=$formatter->link_url(_rawurlencode($value),'');
    $out="<form method='post' action='$link'><ul>\n";
    $out.="<input type='hidden' name='action' value='keywords' />";
    foreach ($words as $key=>$val) {
        if ($val > 1) {
            $checked='';
            if ($val >= 50) {$checked='checked="checked"'; $ok=1;}
            $out.=" <li><input type='checkbox' $checked name='key[]' ".
                "value='$key' />".
                $formatter->link_tag(_rawurlencode($key),
                    '?action=fullsearch&amp;keywords=1&amp;value='.$key,$key).
                    ' ('.$val.')</li>';
        }
    }
    if ($options['add']) {
        $msg=_("manually add keywords");
        $inp="<li><input type='text' name='keywords' size='20' />: $msg</li>";
    }
    if ($ok)
        $btn=_("Update keywords");
    else
        $btn=_("Add keywords");
    return $out."$inp</ul><input type='submit' value='$btn'/></form>\n";
}

function do_keywords($formatter,$options) {
    global $DBInfo;

    define(LOCAL_KEYWORDS,'LocalKeywords');

    $page=$formatter->page->name;

    $formatter->send_header('',$options);

    if (is_array($options['key']) or $options['keywords']) {
        if ($options['keywords']) {
            // following keyword list are acceptable separated with spaces.
            // Chemistry "Physical Chemistry" "Bio Chemistry" ...
            $ws=preg_split('/((?<!\S)(["\'])[^\2]+?\2(?!\S)|\S+)/',
                $options['keywords'],-1,
                PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
            $ws=array_flip(array_unique($ws));
            unset($ws['"']); // delete delims
            unset($ws["'"]);
            unset($ws[' ']);
            $ws=array_flip($ws);
            
            $ws= array_map(create_function('$a',
                'return preg_replace("/^([\"\'])(.*)\\\\1$/","\\\\2",$a);'),
                $ws); // delete ",'
            if (!is_array($options['key'])) $options['key']=array();
            $options['key']=array_merge($options['key'],$ws);
        }
        $cache=new Cache_text('keywords');
        $keys=$options['key'];
        $keys=array_flip($keys);
        unset($keys['']);
        $cache->update($page,$keys);

        $raw="#format plain"; 
        $p=$DBInfo->getPage(LOCAL_KEYWORDS);
        if (!$p->exists()) $dict=array();
        else {
            $raw=$p->get_raw_body();
            $raw=rtrim($raw);
            $lines=explode("\n",$raw);
            $body='';
            foreach ($lines as $line) {
                if ($line[0]=='#') continue;
                $body.=$line."\n";
            }
            $body=rtrim($body);
            $dict=explode("\n",$body);
        }
        $nkeys=array_diff(array_values($options['key']),$dict);
        if (!empty($nkeys)) {
            $raw.="\n".implode("\n",$nkeys);
            $p->write($raw);
            $DBInfo->savePage($p,"New keywords are added",$options);
        }

        $formatter->send_title(sprintf(_("Keywords for %s are updated"),
            $options['page']),'', $options);
        $ret='';
        foreach ($keys as $key=>$val) {
            $ret.=$key.',';
        }
        $ret=substr($ret,0,strlen($ret)-1);
        print "<tt>#keywords $ret</tt>\n";
        if ($DBInfo->use_keywords or $options['update']) {
            $body=$formatter->page->get_raw_body();
            $pi=$formatter->get_instructions($dum);
            if ($pi['#keywords']) {
                $nbody= preg_replace('/#keywords\s+'.$pi['#keywords'].'/',
                    '#keywords '.$ret,$body,1);
                if ($nbody!=$body) $ok=1;
            } else {
                $nbody='#keywords '.$ret."\n".$body;
                $ok=2;
            }
            if ($ok) {
                if ($ok==1) $comment="Keywords are updated";
                else $comment="Keywords are added";
                $formatter->page->write($nbody);
                $DBInfo->savePage($formatter->page,$comment,$options);
                print "<h2>"._("Keywords are updated")."</h2>";
            } else {
                print "<h2>"._("There are no changes found")."</h2>";
            }
        } else {
            $link=$formatter->link_url(_rawurlencode($page),'');
            $btn=_("Update with these Keywords"); 
            $form="<form method='post' action='$link'>";
            $form.='<input type="hidden" name="action" value="keywords" />';
            $form.='<input type="hidden" name="update" value="1" />';
            $form.='<input type="hidden" name="keywords" value="'.$ret.'" />';
            $form.="<input type='submit' value='$btn' />\n";
            $form.="</form>";
            print $form;
        }
        $formatter->send_footer($args,$options);
        return;
    }
    
    $formatter->send_title(sprintf(_("Select keywords for %s"),
        $options['page']),'', $options);

    $options['all']=1;
    $options['add']=1;

    print macro_KeyWords($formatter,$options['page'],$options);
    //$args['editable']=1;
    $formatter->send_footer($args,$options);
}
// vim:et:sts=4:st=4
?>
