<?php
// Copyright 2003-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
//
// Get editlog entries from the editlog
//
// Since:   2015/09/24
// Author:  Won-Kyu Park <wkpark@kldp.org>
//

$conf = new Stdclass;
$conf->text_dir = dirname(__FILE__).'/../data/text/';
$conf->use_namespace = 0;
require_once dirname(__FILE__).'/../lib/pagekey.compat.php';

$pagekey = new Pagekey_compat($conf);

function grep_editlog($filename, $upload = false, $editlog = 'data/editlog') {
    $keyname = $filename;
    if (substr($filename, -2) == ',v')
        $keyname = substr($filename, 0, -2);

    $expr = '^'.$keyname."\t";

    $fp = fopen($editlog, 'r');
    if (!is_resource($fp))
        return false;

    // chunk size
    $sz = 8192 * 10;
    $logs = array();

    while (!feof($fp)) {
        $chunk = fread($fp, $sz);
        if (isset($chunk[$sz - 1]) && $chunk[$sz - 1] != "\n") {
            while (($tmp = fgets($fp, 8192)) !== false && substr($tmp, -1) != "\n");
        }

        if (strstr("\n".$chunk, "\n".$keyname."\t")) {
            $lines = explode("\n", $chunk);
            $matches = preg_grep('%'.$expr.'%', $lines);
            if ($upload)
                $matches = preg_grep('%UPLOAD$%', $matches);
            else
                $matches = preg_grep('%UPLOAD$%', $matches, PREG_GREP_INVERT);
            array_splice($logs, sizeof($logs), 0, $matches);
        }
    }
    fclose($fp);

    return $logs;
}

// get args

$options = array();
$options[] = array('f', '', "\tformat timestamp");
$options[] = array('u', '', "\tupload only\n");
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
    print "Usage: $argv[0] [options] pagename [editlog]\n\n";
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

// set options
if (isset($args['f'])) {
    $format = true;;
} else {
    $format = false;;
}

if (isset($args['u'])) {
    $upload = true;;
} else {
    $upload = false;;
}

// get pagename 
if (empty($argvv[0])) {
   echo "Usage: ",$argv[0], "[options] pagename [editlog]\n";
   exit;
}


// start
$key = $pagekey->pageToKeyname($argvv[0]);

if (!empty($argvv[1]) && file_exists($argvv[1])) {
   $editlog = $argvv[1];
} else {
   $editlog = dirname(__FILE__).'/../data/editlog';
}

$logs = grep_editlog($key, $upload, $editlog);

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

echo implode("\n", $logs);
echo "\n";

// vim:et:sts=4:sw=4:
