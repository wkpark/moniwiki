<?php
// Copyright 2005-2006 Won-Kyu Park <wkpark at kldp.org>
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

    $limit=isset($options['limit']) ? $options['limit']:40;
    $opts=explode(',',$value);
    foreach ($opts as $opt) {
        $opt=trim($opt);
        if ($opt == 'delicious' or $opt == 'del.icio.us')
            $tag_link='http://del.icio.us/tag/$TAG';
        else if ($opt == 'technorati')
            $tag_link='http://www.technorati.com/tag/$TAG';
        else if ($opt == 'flickr')
            $tag_link='http://www.flickr.com/photos/tags/$TAG';
        else if ($opt=='all') { $options['all']=1; $limit=0; }
        else if ($opt=='random') {
            $options['random']=$options['all']=1; }
        else if ($opt=='suggest') $options['suggest']=1;
        else if ($opt=='tour') $options['tour']=1;
        else if ($opt=='freq') $sort='freq';
        else if (($p=strpos($opt,'='))!==false) {
            $k=substr($opt,0,$p);
            $v=substr($opt,$p+1);
            if ($k=='limit') $limit=$v;
            else if ($k=='random') {
                $options['all']=1;
                $v=(int)$v;
                $v=($v > 0) ? $v:1;
                $options['random']=$v;
            }
            else if ($k=='sort' and in_array($v,array('freq','alpha')))
                $sort=$v;
            else if ($k=='type' and in_array($v,array('full','title')))
                $search=$v.'search';
            else if ($k=='url') {
                $tag_link=$v;
                if (preg_match('/\$TAG/',$tag_link)===false) $tag_link.='$TAG';
            }
            // else ignore
        } else {
            $pagename=$opt;
        }
    }

    if ($options['random'] and !$limit) $limit=0;
    if ($options['sort']=='freq') $sort= 'freq';

    if (!$pagename) $pagename=$formatter->page->name;

    if ($options['all']) $pages=$DBInfo->getPageLists();
    else $pages=array($pagename);

    # get cached keywords
    $cache=new Cache_text('keywords');

    $mykeys=array();
    foreach ($pages as $pn) {
        if ($cache->exists($pn)) {
            $keys=$cache->fetch($pn);
            $keys=unserialize($keys);
        } else {
            $keys=array();
        }
        if ($keys) $mykeys=array_merge($mykeys,$keys);
    }
    if ($options['all']) {
        $use_sty=1;
        $words=array_count_values($mykeys);
        unset($words['']);
        $ncount=array_sum($words); // total count
        arsort($words);
        $max=current($words); // get max hit number

        if ($options['random']) {
            $rws=array();
            $selected=array_rand($words,min($options['random'],count($words)));
            foreach($selected as $k) {
                $rws[$k]=$words[$k];
            }
            $words=&$rws;
        }
        if ($sort!='freq') ksort($words);
        #sort($words);
        #print $sort." $value";
        #print "<pre>";
        #print_r($words);
        #print "</pre>";
    } else {
        $max=3; // default weight
        $words=array();
        foreach ($mykeys as $key) {
            $words[$key]=$max;
            // give weight to all selected keywords
        }
    }

    # automatically generate list of keywords
    if (!$options['all'] and (!$words or $options['suggest'])):

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
    if ($options['merge']) {
        foreach ($mykeys as $key) {
            $nwords[$key]=$max;
            // give weight to all selected keywords
        }
    }

    if ($nwords) $words=array_merge($words,$nwords);
    $use_sty=1;

    endif;
    //

    if ($limit and ($sz=sizeof($words))>$limit) {
        arsort($words);
        $words=array_slice($words,0,$limit);
    }
    // make criteria list

    if ($use_sty):
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
    $min=$limit ? max(1,end($fact)):0;
    // make font-size style
    $fz=max(sizeof($fact),2);
    $sty=array();
    $fsh=(MAX_FONT_SZ-MIN_FONT_SZ)/($fz-1);
    $fs=MAX_FONT_SZ; // max font-size:24px;
    for ($i=0;$i<$fz;$i++) {
        $ifs=(int)($fs+0.5);
        $sty[]= " style='font-size:${ifs}px'";
        #print '/'.$ifs;
        $fs-=$fsh;
        $fs=max($fs,9); // min font-size:9px
    }
    endif;

    if ($sort!='freq') ksort($words);

    $link=$formatter->link_url(_rawurlencode($pagename),'');
    if (!isset($tag_link)) {
        if (!$search) $search='fullsearch&amp;keywords=1';
        if ($options['tour'])
            $search='tour&amp;arena=keylinks';
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
            $out.=" <li class=\"tag-item\"";
            if ($use_sty) {
                $out.=" $style title=\"$val "._("hits").'"';
            }
            $out.=">$checkbox"."<a href='".str_replace('$TAG',$key,$tag_link).
                "'>".$key."</a></li>\n";
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
        $btnc=_("Suggest new Keywords"); 
        $form_close="<input type='submit' value='$btn'/>\n";
        $form_close.="<input type='submit' name='suggest' value='$btnc' />\n";
        $form_close.="<input type='submit' name='common' value='$btn1' />\n";
        $form_close.="<input type='button' value='$btn2' onClick='UncheckAll(this)' />\n";
        $form_close.="<select name='lang'><option>---</option>\n";
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

    if ($options['update'] or $options['refresh']) {
        $lk=$DBInfo->getPage(LOCAL_KEYWORDS);
        $force_charset='';
        if ($DBInfo->force_charset)
            $force_charset = '; charset='.$DBInfo->charset;
        $formatter->send_header("Content-type: text/plain".
            $force_charset);
        if (!$lk->exists()) {
            print sprintf(_("%s is not found."),LOCAL_KEYWORDS);
            return;
        }
        $raw=$lk->get_raw_body();

        # update keylinks of LocalKeywords
        $kc=new Cache_text('keylinks');
        $lines=explode("\n",$raw);

        $all_keys=array();
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
            $all_keys=array_merge($all_keys,$ws);
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
                    if (sizeof($rels) > 1 and is_array($rels)) {
                        $kc->update($k,serialize($rels));
                        print "***** save $k\n";
                    }
                }
            }
        }
        #print_r(array_unique($all_keys));
        $handle= opendir("$DBInfo->cache_dir/keylinks");
        while ($fcache= readdir($handle)) {
            if ($fcache[0] == '.') continue;
            $pgname=$DBInfo->keyToPagename($fcache);
            if (!in_array($fcache,$all_keys))
                print 'X "'.$pgname."\"\n";
            else
                print 'O "'.$pgname."\"\n";
        }
        print "OK";
        return;
    }

    $formatter->send_header('',$options);

    if (!$options['suggest'] and
        (is_array($options['key']) or $options['keywords'])) {
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
        $cache->update($page,serialize(array_keys($keys)));

        # update 'keylinks' caches
        #$kc=new Cache_text('keylinks');
        #foreach ($options['key'] as $k) {
        #    // XXX
        #    $kv=unserialize($kc->fetch($k));
        #    if (!in_array($page,$kv)) {
        #        $kv[]=$page;
        #        $kc->update($k,serialize($kv));
        #    }
        #}

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
        }
        if ($options['key']) {
            // XXX
            $ks= array_map(create_function('$a',
                'return (strpos($a," ") !== false) ? "\"$a\"":$a;'),
                $options['key']);
            $raw.="\n".implode(' ',$ks)."\n";
            $lk->write($raw);
            $DBInfo->savePage($lk,"Keywords are added",$options);
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
            # auto update the page with selected keywords.
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
            # user confirmation
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

    if ($options['all']) {
        if ($options['sort']=='freq') $sort= 'freq';
        $formatter->send_title('','', $options);
        $myq='?'.$_SERVER['QUERY_STRING'];
        $myq=preg_replace('/&sort=[^&]+/i','',$myq);
        if ($sort != 'freq') {
            $myq.='&sort=freq';
            $txt=_("alphabetically");
            $ltxt=_("by frequency");
        } else {
            $txt=_("by size");
            $ltxt=_("alphabetically");
        }
        $link=$formatter->link_tag(_rawurlencode($page),$myq,$ltxt);
        
        print "<h2>";
        print sprintf(_("Keywords list %s (or %s)"),$txt,$link);
        print "</h2>\n";
        if (!$options['limit'])
            $options['limit']=0;
    } else {
        $formatter->send_title(sprintf(_("Select keywords for %s"),
            $options['page']),'', $options);

        $options['merge']=1;
        $options['add']=1;
    }

    print macro_KeyWords($formatter,$options['page'],$options);
    //$args['editable']=1;
    $formatter->send_footer($args,$options);
}
// vim:et:sts=4:st=4
?>
