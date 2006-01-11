<?php
// Copyright 2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a 'Keywords' plugin for the MoniWiki
//
// $Id$

define(LOCAL_KEYWORDS,'LocalKeywords');

function macro_Keywords($formatter,$value,$options='') {
    global $DBInfo;
define(MAX_FONT_SZ,24);
define(MIN_FONT_SZ,10);
    $supported_lang=array('ko');

    $limit=$options['limit'] ? $options['limit']:40;
    $opts=explode(',',$value);
    foreach ($opts as $opt) {
        $opt=trim($opt);
        if ($opt == 'delicious' or $opt == 'del.icio.us')
            $tag_link='http://del.icio.us/tag/$TAG';
        else if ($opt == 'technorati')
            $tag_link='http://www.technorati.com/tag/$TAG';
        else if ($opt == 'flickr')
            $tag_link='http://www.flickr.com/photos/tags/$TAG';
        else if (($p=strpos($opt,'='))!==false) {
            $k=substr($opt,0,$p);
            $v=substr($opt,$p+1);
            if ($k=='limit') $limit=$v;
            else if ($k=='sort' and in_array($v,array('freq','alpha')))
                $sort=$v;
            else if ($k=='type' and in_array($v,array('full','title')))
                $search_type=$v;
            else if ($k=='url') {
                $tag_link=$v;
                if (preg_match('/\$TAG/',$tag_link)===false) $tag_link.='$TAG';
            }
            // else ignore
        } else {
            $page=$opt;
        }
    }

    $common= <<<EOF
am an a b c d e f g h i j k l m n o p q r s t u v w x y z
0 1 2 3 4 5 6 7 8 9
if on in by it at up as down over into for from to of he his him she her back
is are be being been or no not nor and all through under until
these there the top
with here only has had both did faw few little most almost much off on out
also each were was too any very more within then
across before behind beneath beyond after again against around among
so such since because but yet however ever during
it its the this that what where how when who whoever which their them
you your will shall may might we us our
get got would could have
can't won't didn't don't
aiff arj arts asp au avi bin biz css cgi com doc edu exe firm gif gz gzip
htm html info jpeg jpg js jsp mp3 mpeg mpg mov
nom pdf php pl qt ra ram rec shop sit tar tgz tiff txt wav web zip
one two three four five six seven eight nine ten eleven twelve
ftp http https www web net org or kr co us de
EOF;
    if (!$pagename) $pagename=$formatter->page->name;
    $page=$DBInfo->getPage($pagename);
    if (!$page->exists()) return '';
    $raw=$page->get_raw_body();$raw=rtrim($raw);

    // strip macros, entities
    $raw=preg_replace("/&[^;\s]+;|\[\[[^\[]+\]\]/",' ',$raw);
    $raw=preg_replace("/([;\"',`\\\\\/\.:@#\!\?\$%\^&\*\(\)\{\}\[\]\-_\+=\|<>])/",
        ' ', strip_tags($raw.' '.$pagename)); // pagename also
    $raw=preg_replace("/((?<=[a-z0-9]|[B-Z]{2})([A-Z][a-z]))/"," \\1",$raw);
    $raw=strtolower($raw);
    $raw=preg_replace("/\b/",' ',$raw);
    //$raw=preg_replace("/\b([0-9a-zA-Z'\"])\\1+\s*/",' ',$raw);
    $words=preg_split("/\s+|\n/",$raw);

    // remove common words
    $common_word_page0=LOCAL_KEYWORDS.'/CommonWords';
    $lines0=array();
    if ($DBInfo->hasPage($common_word_page0)) {
        $p=$DBInfo->getPage($common_word_page0);
        $lines0=explode("\n",($p->get_raw_body()));
    }

    $lang=$formatter->pi['#language'] ? $formatter->pi['#language']:
        $DBInfo->default_language;
    if ($lang and in_array($lang,$supported_lang)) {
        $common_word_page=LOCAL_KEYWORDS.'/CommonWords'.ucfirst($lang);
        if ($DBInfo->hasPage($common_word_page)) {
            $p=$DBInfo->getPage($common_word_page);
            $lines=explode("\n",($p->get_raw_body()));
            $lines=array_merge($lines,$lines0);
            foreach ($lines as $line) {
                if ($line[0]=='#') continue;
                $common.=$line."\n";
            }
            $common=rtrim($common);
        }
    }
    $words=array_diff($words,preg_split("/\s+|\n/",$common));

    $preword='';
    $bigwords=array();
    foreach ($words as $word) {
        if (strlen($word) > 2 and strlen($preword) > 2) {
            if ($word == $preword) continue;
            $key= $preword.' '.$word;
            $rkey= $word.' '.$preword;
            if ($bigwords[$key]) $bigwords[$key]++;
            else if ($bigwords[$rkey]) $bigwords[$rkey]++;
            else $bigwords[$key]++;
        }
        $preword= $word;
    }

    $words=array_count_values($words);
    unset($words['']);
    $ncount=array_sum($words); // total count

/*   
    $words=array_diff(array_keys($counts),preg_split("/\s+|\n/",$common));

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
*/
    if ($bigwords) {
        // 
        $bigwords=array_filter($bigwords,create_function('$a','return ($a != 1);'));
        $words=array_merge($words,$bigwords);
    }

    arsort($words);

    $max=current($words); // get max hit number

    $nwords=array();
    if ($options['all']) {
        $cache=new Cache_text('keywords');
        if ($cache->exists($pagename)) {
            $keys=$cache->fetch($pagename);
            $keys=unserialize($keys);
        } else
            $keys=array();
        foreach ($keys as $key) {
            $nwords[$key]=$max;
            // give weight to all selected keywords
        }
    }

    if ($nwords)
        $words=array_merge($words,$nwords);
    if ($limit and ($sz=sizeof($words))>$limit) {
        arsort($words);
        $words=array_slice($words,0,$limit);
    }
    // make criteria list
    $fact=array();
    $weight=$max; // $ncount
    #print 'max='.$max.' ratio='.$weight/$ncount.':';
    $test=array(0.8, 0.6, 0.4, 0.5, 0.5, 0.5); // six level
    for ($i=0;$i<6 and $weight>0;$i++) {
        $weight=(int)($weight*$test[$i]);
        if ($weight>0) $fact[]=$weight;
        #print $weight.'--';
    }
    $max=current($fact);
    $min=max(1,end($fact));
    // make font-size style
    $fz=max(sizeof($fact),2);
    $sty=array();
    $fsh=(MAX_FONT_SZ-MIN_FONT_SZ)/($fz-1);
    $fs=MAX_FONT_SZ; // max font-size:24px;
    for ($i=0;$i<$fz;$i++) {
        $ifs=(int)($fs+0.5);
        $sty[]= "style='font-size:${ifs}px'";
        #print '/'.$ifs;
        $fs-=$fsh;
        $fs=max($fs,9); // min font-size:9px
    }
    if ($sort!='freq') ksort($words);

    $link=$formatter->link_url(_rawurlencode($pagename),'');
    if (!isset($tag_link)) {
        if ($search_type=='full') $search='fullsearch';
        else if ($search_type=='title') $search='titlesearch';
        else $search='fullsearch&amp;keyword=1';
        $tag_link=$formatter->link_url(_rawurlencode($pagename),
            '?action='.$search.'&amp;value=$TAG');
    }
    $out='';
    if ($options['add']) {
        $out="<form method='post' action='$link'>\n";
        $out.="<input type='hidden' name='action' value='keywords' />\n";
    }
    $out.='<ul>';
    foreach ($words as $key=>$val) {
        $style=$sty[$fz-1];
        for ($i=0;$i<$fz;$i++) {
            if ($val>$fact[$i]) {
                $style=$sty[$i];
                break;
            }
        }
        if ($val > $min) {
            $checked='';
            if ($val >= $max) {$checked='checked="checked"'; $ok=1;}
            if ($options['add'])
                $checkbox="<input type='checkbox' $checked name='key[]' ".
                    "value='$key' />";
            $out.=" <li>$checkbox"."<a href='".str_replace('$TAG',$key,$tag_link).
                "' $style title=\"$val "._("hits").'">'.$key."</a></li>\n";
        }
    }
    if ($options['add']) {
        $msg=_("add keywords");
        $inp="<li><input type='text' name='keywords' size='12' />: $msg</li>";
        if ($ok)
            $btn=_("Update keywords");
        else
            $btn=_("Add keywords");
        $btn1=_("Add as common words"); 
        $btn2=_("Unselect all"); 
        $form_close="<input type='submit' value='$btn'/>\n";
        $form_close.="<input type='submit' name='common' value='$btn1' />\n";
        $form_close.="<input type='button' value='$btn2' onClick='UncheckAll(this)' />\n";
        $form_close.="<select name='lang'><option>----</option>\n";
        foreach ($supported_lang as $l) {
            $form_close.="<option value='$l'>$l</option>\n";
        }
        $form_close.="</select>\n</form>\n";
        $form_close.=<<<EOF
<script type='text/javascript' src='$DBInfo->url_prefix/local/checkbox.js'>
</script>
EOF;
    }
    return "<div class='cloudView'>".$out."$inp</ul></div>$form_close";
}

function do_keywords($formatter,$options) {
    global $DBInfo;
    $supported_lang=array('ko');

    $page=$formatter->page->name;
    if (!$DBInfo->hasPage($page)) {
        $options['err']=_("You are not able to add keywords.");
        $options['title']=_("Page does not exists");
        do_invalid($formatter,$options);
        return;
    }
    if ($options['refresh']) {
        $lk=$DBInfo->getPage(LOCAL_KEYWORDS);
        if (!$lk->exists()) {
            return 'not found';
        }
        $raw=$lk->get_raw_body();

        # update keylinks of LocalKeywords
        $kc=new Cache_text('keylinks');
        $lines=explode("\n",$raw);
        $formatter->send_header("Content-type: text/plain");
        foreach ($lines as $l) {
            $l=trim($l);
            if ($l[0] == '#' or !$l) continue;
            $ws=preg_split('/((?<!\S)(["\'])[^\2]+?\2(?!\S)|\S+)/',
                $l,-1,
                PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
            $ws=array_flip(array_unique($ws));
            unset($ws['"']); // delete delims
            unset($ws["'"]);
            unset($ws[' ']);
            $ws=array_flip($ws);
            $ws= array_map(create_function('$a',
                'return preg_replace("/^([\"\'])(.*)\\\\1$/","\\\\2",$a);'),
                $ws); // delete ",'
            $ws=array_unique($ws);
            foreach ($ws as $k) {
                $rels=array_diff($ws,array($k));
                $krels=unserialize($kc->fetch($k));
                if (is_array($krels)) {
                    if (($nrels=array_diff($rels,$krels))) {
                        $rs=array_unique(array_merge($nrels,$krels));
                        $kc->update($k,serialize($rs));
                        print "***** updated $k\n";
                    }
                } else {
                    if (is_array($rels)) {
                        $kc->update($k,serialize($rels));
                        print "***** save $k\n";
                    }
                }
            }
        }
        print "OK";
        return;
    }

    $formatter->send_header('',$options);

    if (is_array($options['key']) or $options['keywords']) {
        if ($options['keywords']) {
            // following keyword list are acceptable separated with spaces.
            // Chemistry "Physical Chemistry" "Bio Chemistry" ...
            $keywords=_stripslashes($options['keywords']);
            $ws=preg_split('/((?<!\S)(["\'])[^\2]+?\2(?!\S)|\S+)/',
                $keywords,-1,
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

        if ($options['common']) {
            $raw="#format plain"; 
            $lang=$formatter->pi['#language'] ? $formatter->pi['#language']:'';
            $lang=$options['lang'] ? $options['lang']:$lang;
            if (in_array($lang,$supported_lang))
                $common_word_page=LOCAL_KEYWORDS.'/CommonWords'.ucfirst($lang);
            else
                $common_word_page=LOCAL_KEYWORDS.'/CommonWords';

            if ($DBInfo->hasPage($common_word_page)) {
                $p=$DBInfo->getPage($common_word_page);
                if (!$p->exists()) $dict=array();
                else {
                    $raw=$p->get_raw_body();
                    $raw=rtrim($raw);
                    $lines=explode("\n",$raw);
                    $body='';
                    foreach ($lines as $line) {
                        if ($line[0]=='#' or $line=='') continue;
                        $body.=$line."\n";
                    }
                    $body=rtrim($body);
                    $dict=explode("\n",$body);
                }
                $commons=array_diff(array_values($options['key']),$dict);
            } else {
                $p=$DBInfo->getPage($common_word_page);
                $commons=$options['key'];
            }
            if (!empty($commons)) {
                sort($commons);
                $raw.="\n".implode("\n",$commons);
                $p->write($raw);
                $DBInfo->savePage($p,"Common words are added",$options);
            }
            $formatter->send_title(sprintf(_("Common words are updated"),
                $options['page']),'', $options);
            $formatter->send_footer($args,$options);
            return;
        }

        $cache=new Cache_text('keywords');
        $keys=$options['key'];
        $keys=array_flip($keys);
        unset($keys['']);
        $cache->update($page,serialize($keys));

        # update 'keylinks' caches
        $kc=new Cache_text('keylinks');
        foreach ($options['key'] as $k) {
            $kv=unserialize($kc->fetch($k));
            if (!in_array($page,$kv)) {
                $kv[]=$page;
                $kc->update($k,serialize($kv));
            }
        }

        $raw="#format plain"; 
        $lk=$DBInfo->getPage(LOCAL_KEYWORDS);
        if (!$lk->exists()) $dict=array();
        else {
            $raw=$lk->get_raw_body();
            $raw=rtrim($raw);
            $lines=explode("\n",$raw);
            $body='';
            foreach ($lines as $line) {
                if ($line[0]=='#' or $line=='') continue;
                $body.=$line."\n";
            }
            $body=rtrim($body);
            $dict=explode("\n",$body);
        }
        $nkeys=array_diff(array_values($options['key']),$dict);
        $modi=0;
        if (!empty($nkeys)) {
            sort($nkeys);
            $raw.="\n".implode("\n",$nkeys)."\n";
            $lk->write($raw);
            $DBInfo->savePage($lk,"New keywords are added",$options);
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
                $tag=preg_quote($pi['#keywords']);
                $nbody= preg_replace('/^#keywords\s+'.$tag.'/',
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
            $keys=explode(',',$ret);
            $ret='';
            foreach ($keys as $key) {
                if ($key and strpos($key,' ')!==false) {
                    $key='"'.$key.'"';
                }
                $ret.=$key.' ';
            }
            $btn=_("Update with these Keywords"); 
            $form="<form method='post' action='$link'>";
            $form.='<input type="hidden" name="action" value="keywords" />';
            $form.='<input type="hidden" name="update" value="1" />';
            $form.='<input type="hidden" name="keywords" value=\''.$ret.'\' />';
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
