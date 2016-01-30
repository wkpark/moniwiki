<?php
// Copyright 2003-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// pagesort plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2015-11-02
// Name: PageSort plugin
// Description: PageSort plugin
// URL: MoniWiki:PageSortPlugin
// Version: $Revision: 1.0 $
// License: GPLv2
//
// Usage: ?action=pagesort&reverse=1&sortby=date

define('PAGESIZE_SORTBY_SIZE', 0);
define('PAGESIZE_SORTBY_DATE', 1);
define('PAGESIZE_SORTBY_HITS', 2);

function macro_PageSort($formatter, $value = '', $params = array()) {
    global $DBInfo;

    $cache = new Cache_Text('persist', array('depth'=>0));

    $ismember = $DBInfo->user->is_member;
    $refresh = $formatter->refresh && $ismember;

    if (!empty($params['sortby']) && in_array($params['sortby'], array('date', 'hits'))) {
        if ($params['sortby'] == 'date') {
            $sortby = PAGESIZE_SORTBY_DATE;
            $cachekey = 'pagedate';
        } else {
            $sortby = PAGESIZE_SORTBY_HITS;
            $cachekey = 'pagehits';
        }
    } else {
        $sortby = PAGESIZE_SORTBY_SIZE;
        $cachekey = 'pagesize';
    }

    if ($refresh || ($info = $cache->fetch($cachekey)) === false) {
        set_time_limit(0);

        if ($sortby == PAGESIZE_SORTBY_HITS) {
            $cutoff = !empty($DBInfo->counter_cutoff) ? $DBInfo->counter_cutoff : 50;
            $hits = $DBInfo->counter->getPageHits(-1, 0, $cutoff);
            $pages = array();
            foreach ($hits as $k=>$v) {
                $pages[$k."\t".$v] = $v;
            }
        } else {
        $handle = opendir($DBInfo->text_dir);
        if (!is_resource($handle)) {
            return sprintf(_("Can't open %s\n"), $DBInfo->text_dir);
        }

        $j = 0;
        $cnt = 0;
        $pages = array();
        while (($file = readdir($handle)) !== false) {
            $j++;
            if ($file[0] == '.' || in_array($file, array('RCS', 'CVS')))
                continue;
            $pagefile = $DBInfo->text_dir.'/'.$file;
            if (is_dir($pagefile))
                continue;

            $pagename = $DBInfo->keyToPagename($file);
            $sz = $val = filesize($pagefile);
            if ($sortby == PAGESIZE_SORTBY_DATE)
                $val = filemtime($pagefile);
            $key = $pagename."\t".$val;
            if ($sz > 11 && $sz < 4096) {
                // check redirects
                $fp = fopen($pagefile, 'r');
                if (!is_resource($fp))
                    continue;
                $pi = fgets($fp, 11);
                if (isset($pi[0]) && $pi[0] == '#' && preg_match('@^#redirect\s@i', $pi)) {
                    fclose($fp);
                    continue;
                }
                fclose($fp);
            }
            $pages[$key] = $val;
            $cnt++;
        }
        closedir($handle);
        }

        // sort
        if ($sortby != PAGESIZE_SORTBY_SIZE) {
            arsort($pages);
        } else {
            asort($pages);
        }
        $keys = array_keys($pages);
        $raw = implode("\n", $keys);
        // save sorted page list
        $cache->update($cachekey.'.raw', $raw);

        // write index file
        $idxfile = $cache->cache_path.'/'.$cache->getKey($cachekey.'.idx');
        $fp = fopen($idxfile, 'a+b');
        ftruncate($fp, 0);
        $idx = '';
        $i = 0;
        $idx = pack('N', 0);
        $len = 0;
        for ($i = 1; $i < sizeof($keys); $i++) {
            $pos = strlen($keys[$i - 1]) + 1; // strlen($name) + strlen("\n");
            $len+= $pos;
            $idx.= pack('N', $len);
            if ($i % 500 == 0) {
                fwrite($fp, $idx);
                $idx = '';
            }
        }
        if (isset($idx[0])) {
            fwrite($fp, $idx);
        }
        fclose($fp);

        $info = array('count'=>$cnt); // save some info.
        $cache->update($cachekey, $info, 60*60*12); // set TTL
    }
    if (!empty($params['.call']))
        return $info;

    $offset = 0;
    $limit = 200;
    if (!empty($params['offset']))
        $offset = intval($params['offset']);

    $off = $offset;

    if (!empty($params['reverse'])) {
        $off = $info['count'] - $offset;
        $off-= $limit;
    }
    $seek = 0;
    $rawfile = $cache->cache_path.'/'.$cache->getKey($cachekey.'.raw');
    $fp = fopen($rawfile, 'r');

    while ($off > 0) {
        $idxfile = $cache->cache_path.'/'.$cache->getKey($cachekey.'.idx');
        $ip = fopen($idxfile, 'rb');
        if (!is_resource($ip))
            break; // ignore
        fseek($ip, $off * 4);
        $dum = unpack('N', fread($ip, 4));
        $seek = $dum[1];
        fclose($ip);
        fseek($fp, $seek);
        break;
    }

    $lst = array();
    for ($j = 0; $j < $limit; $j++) {
        $raw = fgets($fp, 2048);
        if ($raw === false) break;
        list($page, $val) = explode("\t", $raw, 2);
        $item = '<li>'.$formatter->link_tag($page);
        if ($sortby == PAGESIZE_SORTBY_DATE)
            $item.= ' ('.date('Y-m-d H:i', $val).')</li>'."\n";
        else if ($sortby == PAGESIZE_SORTBY_SIZE)
            $item.= ' ('.$val.' Bytes)</li>'."\n";
        else
            $item.= ' ('.$val.' Hits)</li>'."\n";
        $lst[] = $item;
    }
    fclose($fp);

    // next query string
    $q = '&amp;offset='.($offset + $limit);
    if (!empty($params['reverse'])) {
        $lst = array_reverse($lst);
        $q.= '&amp;reverse=1';
    }
    if ($sortby == PAGESIZE_SORTBY_DATE)
        $q.= '&amp;sortby=date';
    else if ($sortby == PAGESIZE_SORTBY_HITS)
        $q.= '&amp;sortby=hits';

    $out = '<ul>';
    $out.= implode($lst);
    $out.= '</ul>';

    $out.= $formatter->link_to("?action=pagesort$q", _("Show next page"));
    return $out;
}

function do_pagesort($formatter, $params = array()) {
    if (!is_numeric($params['offset']) or $params['offset'] <= 0)
        unset($params['offet']);

    $args = array();
    $sortby = 'size';
    if (isset($params['value'][0])) $args[] = $params['value'];
    if (isset($params['sortby'][0]) && in_array($params['sortby'], array('date', 'hits'))) {
        $args[] = $sortby = $params['sortby'];
    }
    $arg = implode(',', $args);
    $params['.title'] = sprintf(_("List of pages sorted by %s."), $sortby);
    $formatter->send_header('', $params);
    $formatter->send_title('', '', $params);
    echo macro_PageSort($formatter, $arg, $params);
    $formatter->send_footer('', $params);
}

// vim:et:sts=4:sw=4:
