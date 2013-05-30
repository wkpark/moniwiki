<?php
/**
 * Delete caches
 *
 * @since     2010/08/19
 * @author    Won-Kyu Park <wkpark@kldp.org>
 * @revision  $Id$
 */

define('INC_MONIWIKI',1);
include_once(dirname(__FILE__) . '/../wiki.php');

// Start Main
$Config = getConfig("config.php");
require_once('wikilib.php');
require_once('lib/win32fix.php');
require_once("lib/wikiconfig.php");
require_once("lib/timer.php");

$Config = wikiConfig($Config);
$DBInfo = new WikiDB($Config);

$options = array();
if (class_exists('Timer')) {
    $timing = new Timer();
    $options['timer'] = &$timing;
    $options['timer']->Check("load");
}

//
$cache_arenas = array('fullsearch', 'macro', 'dynamicmacros', 'dynamic_macros', 'rclogs', 'wordindex');
$check_date = 30;

//
$checktime = time() - $check_date * 60 * 60;

foreach ($cache_arenas as $arena) {
    echo '** cleanup '.$arena."\n";
    $dir = $DBInfo->cache_dir.'/'.$arena;
    $count = clean_dir($dir, $checktime);
    echo '** ' . $arena. ': Total ' . $count . " files are deleted! \n";
}

function clean_dir($dir, $checktime = 0) {
    $handle = @opendir($dir);
    if (!is_resource($handle)) return 0;

    echo '*** ' . $dir . "\n";
    $count = 0;
    while(($file = readdir($handle)) !== false) {
        if ($file[0] == '.') continue; // hidden files
        if ((($p = strpos($file, '.')) !== false or $file == 'RCS' or $file == 'CVS') and is_dir($dir . '/' . $file)) continue;
        if (is_dir($dir . '/' . $file)) {
            $count+= clean_dir($dir . '/' . $file, $checktime);
            continue;
        }
        $mtime = filemtime($dir . '/' . $file);
        #print $dir . '/' . $file . ' ' . $mtime . "\n";
        if ($mtime < $checktime) {
            $count++;
            #print $file . ' ' . "\n";
            unlink($dir . '/' . $file);
        }
    }
    closedir($handle);
    return $count; // return the number of deleted files
}

// vim:et:sts=4:sw=4:
