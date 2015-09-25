<?php
// Copyright 2003-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
//
// Get rlog entries from the RCS history of a selected file
//
// Since:   2015/09/24
// Author:  Won-Kyu Park <wkpark@kldp.org>
//

$conf = new Stdclass;
$conf->text_dir = dirname(__FILE__).'/../data/text/';
$conf->use_namespace = 0;
require_once dirname(__FILE__).'/../lib/pagekey.compat.php';

$pagekey = new Pagekey_compat($conf);

function get_rlog($file, $text_dir = 'data/text', $rcs_dir = 'RCS') {
    $filename = basename($file);
    $keyname = $filename;

    // normalize keyname, filename
    if (substr($filename, -2) == ',v')
        $keyname = substr($filename, 0, -2);
    if (substr($filename, -2) != ',v')
        $filename.= ',v';

    if (substr($rcs_dir, -1) != '/') {
        $rcs_dir.= '/';
    }
    if (substr($text_dir, -1) != '/') {
        $text_dir.= '/';
    }

    // check filename
    while (!file_exists($file)) {
        if (is_dir($text_dir)) {
            // text/foobar
            $f = $text_dir.$keyname;
            if (file_exists($f)) {
                $file = $f;
                break;
            }
            // text/RCS/foobar,v
            $f = $text_dir.$rcs_dir.$filename;
            if (file_exists($f)) {
                $file = $f;
                break;
            }
        } else if (is_dir($rcs_dir)) {
            // RCS/foobar,v
            $f = $rcs_dir.$filename;
            if (file_exists($f)) {
                $file = $f;
                break;
            }
        }

        break;
    }

    // call rlog
    $arg = escapeshellarg($file);
    exec('rlog '.$arg, $lines, $ret);

    if ($ret != 0) {
        return false;
    }

    // parse rlog
    $i = 0;
    // search the first line
    while (!preg_match('@^revision\s+\d@', $lines[$i])) $i++;

    $logs = array();
    for (; $i < count($lines); $i++) {
        if (preg_match('@^revision\s+(\d\.\d+)\b@', $lines[$i])) {
            if (preg_match('@^date:\s+(\d{4}/\d{2}/\d{2} \d{2}:\d{2}:\d{2});@', $lines[$i + 1], $m)) {
                $mtime = strtotime($m[1]);
                $tmp = explode(';;', $lines[$i + 2], 3);
                $ip = $tmp[0];
                $id = $tmp[1];
                $comment = $tmp[2];
                $logs[] = implode("\t", array($keyname, $ip, $mtime, $ip, $id, $comment, 'SAVE'));
                $i++;
            }
        }
    }

    // reverse order by default
    return array_reverse($logs);
}

// get args

$options = array();
$options[] = array('f', '', "\tformat timestamp");
$options[] = array('t:', '', "\ttext dir\n");
$options[] = array('r:', '', "\trcs dir\n");
$short_opts = ''; // list of short options.
foreach ($options as $item) {
    $opt = $item[0];
    if ($item[1]) { // if long option exists
        $opt .= ':';
    }
    $short_opts .= $opt;
}

$options[] = array('-help', '', "\tdisplay this message");

if (empty($argv[1]) || in_array('--help', $argv)) {
    print "Usage: $argv[0] [options] <a pagename or a RCS filename>\n\n";
    print "Options:\n";
    foreach($options as $message) {
        if ($message[1]) $value = "<$message[1]>";
        else $value = "";
        print "\t-$message[0] $value\t$message[2]\n";
    }
    exit;
}

$args = getopt($short_opts);

// check argv
$argvv = array();
for ($i = 1; $i < count($argv); $i++) {
    // short
    if (preg_match('#^-([a-z])(.*)#', $argv[$i], $m)) {
        if (($p = strpos($short_opts, $m[1])) !== false) {
            if (isset($short_opts[$p + 1]) && $short_opts[$p + 1] == ':') {
                if (empty($m[2])) {
                    $i++;
                }
            }
            continue;
        }
    }
    $argvv[] = $argv[$i];
}

if (isset($args['f'])) {
    $format = true;;
} else {
    $format = false;;
}

if (empty($args['t'])) {
    $text_dir = 'data/text';
} else {
    $text_dir = $args['t'];
}

if (empty($args['r'])) {
    $rcs_dir = 'RCS';
} else {
    $rcs_dir = $args['r'];
}

// get pagename 
if (empty($argvv[0])) {
   echo "Usage: ",$argv[0], "[options] pagename\n";
   exit;
}


// start
// is it a RCS file or pagename
if (!file_exists($argvv[0])) {
    $key = $pagekey->pageToKeyname($argvv[0]);
} else {
    $key = $argvv[0];
}

$logs = get_rlog($key, $text_dir, $rcs_dir);

if ($format) {
    foreach ($logs as $log) {
        $tmp = explode("\t", $log);
        $tmp[0] = $pagekey->keyToPagename($tmp[0]);
        if (isset($tmp[2]))
            $tmp[2] = date('Y-m-d H:i:s', $tmp[2]);
        else
            $tmp[1] = $line;
        echo implode("\t", $tmp),"\n";
    }

    exit;
}

if ($logs === false)
    exit;
echo implode("\n", $logs);
echo "\n";

// vim:et:sts=4:sw=4:
