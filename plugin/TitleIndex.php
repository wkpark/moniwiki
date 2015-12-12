<?php
// Copyright 2003-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a TitleIndex plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2003-04-02
// Name: TitleIndex plugin
// Description: show TitleIndex
// URL: MoniWiki:TitleIndexPlugin
// Version: $Revision: 1.0 $
// License: GPLv2
//
// Usage: [[TitleIndex]]

function macro_TitleIndex($formatter, $value, $options = array()) {
    global $DBInfo;

    $pc = !empty($DBInfo->titleindex_pagecount) ? intval($DBInfo->titleindex_pagecount) : 100;
    if ($pc < 1) $pc = 100;

    $pg = empty($options['p']) ? 1 : intval($options['p']);
    if ($pg < 1) $pg = 1;

    $group=$formatter->group;

    $key=-1;
    $keys=array();

    if ($value=='' or $value=='all') $sel='';
    else $sel = ucfirst($value);

    // get all keys
    $all_keys = get_keys();

    if (isset($sel[0])) {
        if (!isset($all_keys[$sel]))
            $sel = key($all_keys); // default
    }

    if (@preg_match('/'.$sel.'/i','')===false) $sel='';

    $titleindex = array();

    // cache titleindex
    $kc = new Cache_text('titleindex');
    $delay = !empty($DBInfo->default_delaytime) ? $DBInfo->default_delaytime : 0;

    $uid = '';
    if (function_exists('posix_getuid'))
        $uid = '.'.posix_getuid();

    $index_lock = 'titleindex'.$uid;
    $locked = $kc->exists($index_lock);

    if ($locked or ($kc->exists('key') and $DBInfo->checkUpdated($kc->mtime('key'), $delay))) {
        if (!empty($formatter->use_group) and $formatter->group) {
            $keys = $kc->fetch('key.'.$formatter->group);
            $titleindex = $kc->fetch('titleindex.'.$formatter->group);
        } else {
            $keys = $kc->fetch('key');
            $titleindex = $kc->fetch('titleindex'.$sel);
        }
        if (isset($sel[0]) and isset($titleindex[$sel])) {
            $all_pages = $titleindex[$sel];
        }
        if (empty($titleindex) and $locked) {
            // no cache found
            return _("Please wait...");
        }
    }

    if (!isset($sel[0])) {
        $indexer = $DBInfo->lazyLoad('titleindexer');
        $total = $indexer->pageCount();

        // too many pages. check $sel
        if ($total > 10000) {
            $sel = ''.key($all_keys); // select default key
        }
    }

    if (empty($all_pages)) {

        $all_pages = array();
        if (empty($indexer))
            $indexer = $DBInfo->lazyLoad('titleindexer');
        if (!empty($formatter->use_group) and $formatter->group) {
            $group_pages = $indexer->getLikePages('^'.$formatter->group);
            foreach ($group_pages as $page)
                $all_pages[]=str_replace($formatter->group,'',$page);
        } else {
            $all_pages = $indexer->getLikePages('^'.$all_keys[$sel], 0);
        }

        #natcasesort($all_pages);
        #sort($all_pages,SORT_STRING);
        //usort($all_pages, 'strcasecmp');
        $pages = array_flip($all_pages);
        if (!empty($formatter->use_group)) {
            array_walk($pages,'_setpagekey');
        } else {
            array_walk($pages, create_function('&$p, $k', '$p = $k;'));
        }
        $all_pages = array_flip($pages);
        uksort($all_pages, 'strcasecmp');
    }

    if (empty($keys) or empty($titleindex)) {
        $kc->update($index_lock, array('dummy'), 30); // 30 sec lock
        foreach ($all_pages as $page=>$rpage) {
            $p = ltrim($page);
            $pkey = get_key("$p");
            if ($key != $pkey) {
                $key = $pkey;
                //$keys[] = $pkey;
                if (!isset($titleindex[$pkey]))
                    $titleindex[$pkey] = array();
            }
            $titleindex[$pkey][$page] = $rpage;
        }

        $keys = array_keys($all_keys);
        if (!empty($tlink))
            $keys[]='all';

        if (!empty($formatter->use_group) and $formatter->group) {
            $kc->update('key.'.$formatter->group, $keys);
            $kc->update('titleindex.'.$formatter->group, $titleindex);
        } else {
            $kc->update('key', $keys);
            $kc->update('titleindex'.$sel, $titleindex);
        }

        if (isset($sel[0]) and isset($titleindex[$sel]))
            $all_pages = $titleindex[$sel];
        $kc->remove($index_lock);
    }

    $pnut = null;
    if (isset($sel[0]) and count($all_pages) > $pc) {
        $pages_number = intval(count($all_pages) / $pc);
        if (count($all_pages) % $pc)
            $pages_number++;

        $pages = array_keys($all_pages);
        $pages = array_splice($pages, ($pg - 1) * $pc, $pc);
        $selected = array();
        foreach ($pages as $p) {
            $selected[$p] = $all_pages[$p];
        }
        $pages = $selected;

        $pnut = get_pagelist($formatter, $pages_number,
                '?action=titleindex&amp;sec='.$sel.
                '&amp;p=', !empty($pg) ? $pg : 1);
    } else {
        $pages = &$all_pages;
    }
    //print count($all_pages);
    //exit;
    $out = '';
    #  if ($DBInfo->use_titlecache)
    #    $cache=new Cache_text('title');
    $key = '';
    foreach ($pages as $page=>$rpage) {
        $p=ltrim($page);
        $pkey=get_key("$p");
        if ($key != $pkey) {
            $key = $pkey;
            if (isset($sel[0]) and !preg_match('/^'.$sel.'/i',$pkey)) continue;
            if (!empty($out)) $out.="</ul></div>";
            $out.= "<a name='$key'></a><h3><a href='#top'>$key</a></h3>\n";
            $out.= '<div class="index-group">';
            $out.= "<ul>";
        }
        if (isset($sel[0]) and !preg_match('/^'.$sel.'/i',$pkey)) continue;
        #
        #    if ($DBInfo->use_titlecache and $cache->exists($page))
        #      $title=$cache->fetch($page);
        #    else
        $title=get_title($rpage,$page);

        #$out.= '<li>' . $formatter->word_repl('"'.$page.'"',$title,'',0,0);
        $urlname=_urlencode($group.$rpage);
        $out.= '<li>' . $formatter->link_tag($urlname,'',_html_escape($title));
        $keyname=$DBInfo->pageToKeyname(urldecode($rpage));
        if (is_dir($DBInfo->upload_dir."/$keyname") or
                (!empty($DBInfo->use_hashed_upload_dir) and
                 is_dir($DBInfo->upload_dir.'/'.get_hashed_prefix($keyname).$keyname)))
            $out.=' '.$formatter->link_tag($urlname,"?action=uploadedfiles",
                    $formatter->icon['attach']);
        $out.="</li>\n";
    }
    $out.= "</ul></div>\n";
    if (!empty($pnut)) {
        $out.='<div>'. $pnut .'</div>'."\n";
    }

    $index='';
    $tlink='';
    if (isset($sel[0])) {
        $tlink=$formatter->link_url($formatter->page->urlname,'?action=titleindex&amp;sec=');
    }

    $index = array();
    foreach ($keys as $key) {
        $name = strval($key);
        $tag='#'.$key;
        $link=!empty($tlink) ? preg_replace('/sec=/','sec='._urlencode($key),$tlink):'';
        if ($name == 'Others') $name=_("Others");
        else if ($name == 'all') $name=_("Show all");
        $index[] = "<a href='$link$tag'>$name</a>";
    }
    $str = implode(' | ', $index);

    return "<center><a name='top'></a>$str</center>\n$out";
}

function do_titleindex($formatter,$options) {
    global $DBInfo, $Config;

    if (isset($options['q'])) {
        if (!$options['q']) { print ''; return; }
        #if (!$options['q']) { print "<ul></ul>"; return; }
        $limit = isset($options['limit']) ? intval($options['limit']) : 100;
        $limit = min(100, $limit);

        $val='';
        $rule='';
        while ($DBInfo->use_hangul_search) {
            include_once("lib/unicode.php");
            $val=$options['q'];
            if (strtoupper($DBInfo->charset) != 'UTF-8' and function_exists('iconv')) {
                $val=iconv($DBInfo->charset,'UTF-8',$options['q']);
            }
            if (!$val) break;

            $rule=utf8_hangul_getSearchRule($val, !empty($DBInfo->use_hangul_lastchar_search));

            $test=@preg_match("/^$rule/",'');
            if ($test === false) $rule=$options['q'];
            break;
        }
        if (!$rule) $rule=trim($options['q']);

        $test = validate_needle('^'.$rule);
        if (!$test)
            $rule = preg_quote($rule);

        $indexer = $DBInfo->lazyLoad('titleindexer');
        $pages = $indexer->getLikePages($rule, $limit);

        sort($pages);
        //array_unshift($pages, $options['q']);
        $ct = "Content-Type: text/plain";
        $ct.= '; charset='.$DBInfo->charset;

        header($ct);
        $maxage = 60 * 10;
        header('Cache-Control: public, max-age='.$maxage.',s-maxage='.$maxage.', post-check=0, pre-check=0');
        if ($pages) {
            $ret= implode("\n",$pages);
            #$ret= "<ul>\n<li>".implode("</li>\n<li>",$pages)."</li>\n</ul>\n";
        } else {
            #$ret= "<ul>\n<li>".$options['q']."</li></ul>";
            $ret= '';
            #$ret= "<ul>\n</ul>";
        }
        if (strtoupper($DBInfo->charset) != 'UTF-8' and function_exists('iconv')) {
            $val=iconv('UTF-8',$DBInfo->charset,$ret);
            if ($val) { print $val; return; }
        }
        print $ret;
        return;
    } else if ($options['sec'] =='') {
        if (!empty($DBInfo->no_all_titleindex))
            return;

        $tc = new Cache_text('persist', array('depth'=>0));

        // all pages
        $mtime = $DBInfo->mtime();
        $lastmod = gmdate('D, d M Y H:i:s \G\M\T', $mtime);
        $etag = md5($mtime.$DBInfo->etag_seed);
        $options['etag'] = $etag;
        $options['mtime'] = $mtime;

        // set the s-maxage for proxy
        $date = gmdate('Y-m-d-H-i-s', $mtime);
        $proxy_maxage = !empty($Config['proxy_maxage']) ? ', s-maxage='.$Config['proxy_maxage'] : '';
        $header[] = 'Content-Type: text/plain';
        $header[] = 'Cache-Control: public'.$proxy_maxage.', max-age=0, must-revalidate';
        $need = http_need_cond_request($mtime, $lastmod, $etag);
        if (!$need)
            $header[] = 'HTTP/1.0 304 Not Modified';
        else
            $header[] = 'Content-Disposition: attachment; filename="titleindex-'.$date.'.txt"';
        $formatter->send_header($header, $options);
        if (!$need) {
            @ob_end_clean();
            return;
        }

        if (($out = $tc->fetch('titleindex', 0, array('print'=>1))) === false) {
            $args = array('all'=>1);
            $pages = $DBInfo->getPageLists($args);

            sort($pages);

            $out = join("\n", $pages);
            $ttl = !empty($DBInfo->titleindex_ttl) ? $DBInfo->titleindex_ttl : 60*60*24;
            $tc->update('titleindex', $out, $ttl);
            echo $out;
        }

        return;
    }
    $formatter->send_header("",$options);
    $formatter->send_title("","",$options);
    print macro_TitleIndex($formatter,$options['sec'], $options);
    $formatter->send_footer($args,$options);
}

// vim:et:sts=4:sw=4:
