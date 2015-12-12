<?php
// Copyright 2003-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a TitleSearch plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2003-04-02
// Name: TitleSearch plugin
// Description: show TitleSearch form
// URL: MoniWiki:TitleSearchPlugin
// Version: $Revision: 1.0 $
// License: GPLv2
//
// Usage: [[TitleSearch]]
//

function macro_TitleSearch($formatter="",$needle="",&$opts) {
    global $DBInfo;
    $type='o';

    $url=$formatter->link_url($formatter->page->urlname);
    $hneedle = _html_escape($needle);

    $msg = _("Go");
    $form="<form method='get' action='$url'>
        <input type='hidden' name='action' value='titlesearch' />
        <input name='value' size='30' value=\"$hneedle\" />
        <span class='button'><input type='submit' class='button' value='$msg' /></span>
        </form>";

    if (!isset($needle[0])) {
        $opts['msg'] = _("Use more specific text");
        if (!empty($opts['call'])) {
            $opts['form']=$form;
            return $opts;
        }
        return $form;
    }

    $opts['form'] = $form;
    $opts['msg'] = sprintf(_("Title search for \"%s\""), $hneedle);
    $cneedle=_preg_search_escape($needle);

    if ($opts['noexpr'])
        $needle = preg_quote($needle);
    else if (validate_needle($cneedle) === false) {
        $needle = preg_quote($needle);
    } else {
        // good expr
        $needle = $cneedle;
    }

    // return the exact page or all similar pages
    $noexact = true;
    if (isset($opts['noexact']))
        $noexact = $opts['noexact'];

    $limit = !empty($DBInfo->titlesearch_page_limit) ? $DBInfo->titlesearch_page_limit : 100;
    if (isset($opts['limit']))
        $limit = $opts['limit'];

    $indexer = $DBInfo->lazyLoad('titleindexer');
    $pages = $indexer->getLikePages($needle, $limit);

    $opts['all'] = $DBInfo->getCounter();
    if (empty($DBInfo->alias)) $DBInfo->initAlias();
    $alias = $DBInfo->alias->getAllPages();

    $pages = array_merge($pages, $alias);
    $hits=array();
    $exacts = array();

    if ($noexact) {
        // return all search results
        foreach ($pages as $page) {
            if (preg_match("/".$needle."/i", $page)) {
                $hits[]=$page;
            }
        }
    } else {
        // return exact pages
        foreach ($pages as $page) {
            if (preg_match("/^".$needle."$/i", $page)) {
                $hits[] = $page;
                $exacts[] = $page;
                if (empty($DBInfo->titlesearch_exact_all)) {
                    $hits = $exacts;
                    break;
                }
            }
        }
    }

    if (empty($hits) and empty($exacts)) {
        // simple title search by ignore spaces
        $needle2 = str_replace(' ', "[ ]*", $needle);
        $ws = preg_split("/([\x{AC00}-\x{D7F7}])/u", $needle2, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $needle2 = implode("[ ]*", $ws);
        $hits = $indexer->getLikePages($needle2);
        foreach ($alias as $page) {
            if (preg_match("/".$needle2."/i", $page))
                $hits[]=$page;
        }
    }

    sort($hits);

    $idx=1;
    if (!empty($opts['linkto'])) $idx=10;
    $out='';
    foreach ($hits as $pagename) {
        $pagetext=_html_escape(urldecode($pagename));
        if (!empty($opts['linkto']))
            $out.= '<li>' . $formatter->link_to("$opts[linkto]$pagename",$pagetext,"tabindex='$idx'")."</li>\n";
        else
            $out.= '<li>' . $formatter->link_tag(_rawurlencode($pagename),"",$pagetext,"tabindex='$idx'")."</li>\n";
        $idx++;
    }

    if ($out) $out="<${type}l>$out</${type}l>\n";
    $opts['hits']= count($hits);
    if ($opts['hits']==1)
        $opts['value']=array_pop($hits);
    if (!empty($exacts)) $opts['exact'] = 1;
    if (!empty($opts['call'])) {
        $opts['out']=$out;
        return $opts;
    }
    return $out;
}

function do_titlesearch($formatter,$options) {
    global $DBInfo;

    $ret = array();
    if (isset($options['noexact'])) $ret['noexact'] = $options['noexact'];
    if (isset($options['noexpr'])) $ret['noexpr'] = $options['noexpr'];
    $out = macro_TitleSearch($formatter,$options['value'],$ret);

    if ($ret['hits']==1 and (empty($DBInfo->titlesearch_noredirect) or !empty($ret['exact']))) {
        $options['value']=$ret['value'];
        $options['redirect']=1;
        do_goto($formatter,$options);
        return true;
    }
    if (!$ret['hits'] and !empty($options['check'])) return false;

    if ($ret['hits'] == 0) {
        $ret2['form']=1;
        $out2= $formatter->macro_repl('FullSearch',$options['value'],$ret2);
    }

    $formatter->send_header("",$options);
    $options['msgtype']='search';
    $formatter->send_title($ret['msg'],$formatter->link_url("FindPage"),$options);

    if (!empty($options['check'])) {
        $page = $formatter->page->urlname;
        $button= $formatter->link_to("?action=edit",$formatter->icon['create']._
                ("Create this page"));
        print "<h2>".$button;
        print sprintf(_(" or click %s to fullsearch this page.\n"),$formatter->link_to("?action=fullsearch&amp;value=$page",_("title")))."</h2>";
    }

    print $ret['form'];

    if (!empty($ret['hits']))
        print $out;

    if ($ret['hits'])
        printf(_("Found %s matching %s out of %s total pages")."<br />",
                $ret['hits'],
                ($ret['hits'] == 1) ? _("page") : _("pages"),
                $ret['all']);

    if ($ret['hits'] == 0) {
        print '<h2>'._("Please try to fulltext search")."</h2>\n";
        print $out2;
    } else {
        $value = _urlencode($options['value']);
        print '<h2>'.sprintf(_("You can also click %s to fulltext search.\n"),
                $formatter->link_to("?action=fullsearch&amp;value=$value",_("here")))."</h2>\n";
    }

    $args['noaction']=1;
    $formatter->send_footer($args,$options);
    return true;
}

// vim:et:sts=4:sw=4:
