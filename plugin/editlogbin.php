<?php
// Copyright 2003-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a data binning plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark at kldp.org>
// Date: 2015-09-25
// Name: EditlogBin
// Description: Data binning plugin for editlog
// URL: MoniWiki:EditlogBinPlugin
// Version: $Revision: 1.0 $
// License: GPLv2
//
// Usage: ?action=editlogbin&q=foobar&start=2009/05/01
//

function _editlog_binning($fp, $seek = null, $start, $bin = 0, $params = array()) {
    if (empty($params['until']))
        $until = time();
    else
        $until = $params['until'];

    if (!empty($seek)) {
        fseek($fp, $seek);
    }

    if (empty($bin))
        $bin = 60*60*24; // 24 hours

    $iq = 0;
    $q = null;
    if (!empty($params['q'])) {
        $q = $params['q'];
        // FIXME check valid IP or ID
        if (preg_match('@^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$@', $q))
            $iq = 1; // IP
        else
            $iq = 4; // user ID
    }

    $j = 0;
    $data = array();
    $users = array();
    $stamp = -1;
    $has_query = !empty($q);
    do {
        $line = $tmp = fgets($fp, 4096);
        $oline = '';
        while (substr($tmp, -1) != "\n" && ($tmp = fgets($fp, 4096)) !== false) $oline.= $tmp;
        if (isset($oline[0])) $line.= $oline;

        $tmp = explode("\t", $line);
        if (!isset($tmp[2])) continue;
        $stamp = $tmp[2];
        if ($has_query && $tmp[$iq] != $q) {
            continue;
        } else {
            $id = $tmp[4];
            if ($id == 'Anonymous')
                $id = $tmp[1];
        }

        $idx = (int)(($stamp - $start) / $bin)*$bin + $start;
        if (!isset($data[$idx])) {
            $data[$idx] = 1;
            $users[$idx] = array();
        } else {
            $data[$idx]++;
            if (isset($users[$idx][$id]))
                $users[$idx][$id]++;
            else
                $users[$idx][$id] = 1;
        }

        $j++;
    } while (!feof($fp) && $stamp < $until);

    $user_count = array();
    foreach ($users as $idx=>$u) {
        $user_count[$idx] = count($u);
    }

    return array('total'=>$j, 'data'=>$data, 'user'=>$users, 'user_count'=>$user_count);
}

/**
 * seek timestamp
 *
 * @param string/resource editlog file
 * @param integer   timestamp
 * @param integer   timestamp column
 * @return integer  -1(FAIL) or seek position.
 */
function _editlog_seek($editlog, $timestamp = null, $column = 2) {
    $_chunk_size = 512; // average length of lines.
    $_maxtry = 25; // maximum seek tries
    $_debug = false;

    // default timestamp
    if (empty($timestamp)) {
        $t = strtotime( '-1 month', time());
        $date = date('Y-m-d 00:00:00', $t);
        $timestamp = strtotime($date);
    } else if (is_string($timestamp)) {
        $timestamp = strtotime($date);
    }

    $fp = null;
    if (is_string($editlog) && file_exists($editlog)) {
        $fp = fopen($editlog, 'r');
        if (!is_resource($fp))
            return -1; // not found
    } else if (is_resource($editlog)) {
        $fp = &$editlog;
    } else {
        return -1; // error
    }

    // get filesize
    fseek($fp, 0, SEEK_END);
    $fz = ftell($fp);

    $myseek = $fz >> 1; // initial pos
    $offset = 0;
    $lower = 0;
    $upper = $fz;
    $min_offset = 1024;

    // pseudo binary search
    $try = 0;
    while ($try < $_maxtry) {
        $try++;
        $myseek += $offset;
        $myseek = $myseek > $fz ? $fz : ($myseek < 0 ? 0 : $myseek);

        fseek($fp, $myseek);

        // trash last line
        while (($tmp = fgets($fp, 1024)) !== false && substr($tmp, -1) != "\n");
        $myseek = ftell($fp);

        // get line
        $line = $tmp = fgets($fp, 4096);
        $oline = '';
        while (substr($tmp, -1) != "\n" && ($tmp = fgets($fp, 4096)) !== false) $oline.= $tmp;
        if (isset($oline[0])) $line.= $oline;

        $tmp = explode("\t", $line);
        if (!isset($tmp[$column])) break;
        $laststamp = $tmp[$column];
        if ($timestamp > $laststamp) {
            $sign = 1;
            $gap = $upper - $myseek;
        } else {
            if ($timestamp === $laststamp) break;
            $sign = -1;
            $gap = $myseek - $lower;
        }

        $guess = intval($gap * 0.5);

        $min_offset = min($min_offset, $guess);
        if ($guess < $_chunk_size) $guess = $_chunk_size;

        $offset = $sign * $guess;
        if ($gap < $guess) {
            //$myseek = $upper;
            //if ($_debug)
            //    echo $try,"\t", "upper=", $upper, " lower=", $lower, " seek=", $myseek, " guess=", $guess,"\n";
            break;
        }
        if ($sign > 0)
            $lower = $myseek;
        else
            $upper = $myseek;

        if ($_debug)
            echo $try,"\t", $sign,"\t", $myseek,"\t", strlen($line),"\t",'guess=',$guess,"\t", 'offset=', $offset,"\n";
    }
    // FIXME check the laststamp

    if ($_debug)
        echo $try,"\t",$myseek,"\t",date('Y-m-d H:i:s', $timestamp),"\t", date('Y-m-d H:i:s', $laststamp),"\n";
    if (is_string($editlog))
        fclose($fp);
    return $myseek;
}

function cached_editlogbin($formatter, $params = array()) {
    global $Config, $DBInfo;

    $cache = new Cache_Text('editlogbin');

    if (!empty($params['title'])) {
        if (!$DBInfo->hasPage($params['title']))
            unset($params['title']);
    }

    $args = array();
    $user = !empty($params['q']) ? $params['q'] : '';
    $start = !empty($params['start']) ? $params['start'] : '';
    $domain = !empty($params['domain']) ? $params['domain'] : '';
    $title = !empty($params['title']) ? $params['title'] : '';

    // get timestamp
    $oldest_stamp = !empty($params['.oldest']) ?
            strtotime($params['.oldest']) : strtotime('-1 year');

    // round timestamp
    $tmp = date('Y-m-d 00:00:00', $oldest_stamp);
    $oldest_stamp = strtotime($tmp);

    if (!empty($start)) {
        if (is_numeric($start)) // timestamp
            $from = $start;
        else
            $from = strtotime($start); // convert to timestamp
    } else {
        $from = !empty($params['.max_range']) ?
            strtotime('-'.$params['.max_range']) : strtotime('-1 year');
        // restrict range
        $params['until'] = null; // reset
    }
    // round timestamp
    $tmp = date('Y-m-d 23:59:59', $from);
    $from = strtotime($tmp);

    $max_range = !empty($params['.max_range']) ?
        strtotime($params['.max_range'], $from) : strtotime('1 year', $from);
    $params['until'] = $max_range;
    if ($params['until'] > time())
        $params['until'] = null;

    //echo date('Y.m.d H.i.s', $params['until']);

    $mtime = $DBInfo->mtime();
    $key = $user.'.'.$from.'.'.$domain.'.'.$title.$mtime;

    if (empty($formatter->refresh) && ($data = $cache->fetch($key)) !== false) {
        return $data;
    }

    $fp = fopen($Config['editlog_name'], 'r');
    $seek = _editlog_seek($fp, $from);
    $data = _editlog_binning($fp, $seek, $from, 60*60*24, $params);
    fclose($fp);

    $cache->update($key, $data, 60*60*24); // TTL to 24 hour
    return $data;
}

function do_editlogbin($formatter, $params = array()) {
    global $Config;

    $params['.oldest'] = !empty($Config['editlogbin_datetime_oldest']) ?
        $Config['editlogbin_datetime_oldest'] : '-1 year';
    $params['.max_range'] = !empty($Config['editlogbin_datetime_max_range']) ?
        $Config['editlogbin_datetime_max_range'] : '1 year';
    $data = cached_editlogbin($formatter, $params);

    header('Content-Type: text/plain');
    if ($params['user_count'])
        echo json_encode($data['user_count']);
    else
        echo json_encode($data['data']);
    return;
}

// vim:et:sts=4:sw=4:
