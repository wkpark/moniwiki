<?php
/**
 * Check broken RCS file
 * by wkpark at gmail.com 2013/05/08
 *
 * License: GPLv2. Please see COPYING
 */

require_once("config.php");

$rcs_dir = $text_dir.'/RCS/';

function pagename($key) {
    $key = strtr($key, '_', '%');
    return rawurldecode($key);
}

function checkRCSdir($dir, $usleep = 0) {
    $dir = rtrim($dir, '/');
    $handle = opendir($dir);
    if (!is_resource($handle))
      return false;

    set_time_limit(0);
    $count = 0;
    while (($file = readdir($handle)) !== false) {
        if ((($p = strpos($file, '.')) !== false or $file == 'RCS' or $file == 'CVS') and is_dir($dir.'/'.$file)) continue;
        if (substr($file, -2) != ',v') continue; // ignore non rcs files
        $fp = fopen($dir.'/'.$file, 'r');
        if (!is_resource($fp)) continue; // just ignore

        fseek($fp, -4, SEEK_END);
        $end = fread($fp, 4);
        fclose($fp);
        if (!preg_match("/@\n\s*$/", $end))
            echo "RCS file for page ".pagename($file).": $dir/$file is broken.\n";
        if ($usleep > 0) usleep($usleep);
    }
    closedir($handle);
}

set_time_limit(0); // no time limit
// checkRCSdir($dir, $usleep)
// set usleep for less overhead

$rcs_dir = 'broken';
checkRCSdir($rcs_dir, 1000);

// vim:et:sts=4:sw=4:
