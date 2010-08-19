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
include(dirname(__FILE__) . '/../wikilib.php');
include(dirname(__FILE__) . '/../lib/win32fix.php');

$DBInfo = new WikiDB($Config);

$options = array();
$timing = new Timer();
$options['timer'] = &$timing;
$options['timer']->Check("load");

//
$cache_arenas = array('fullsearch', 'macro', 'dynamicmacros');
$check_date = 30;

//
$checktime = time() - $check_date * 60 * 60;

foreach ($cache_arenas as $arena) {
    echo '** cleanup '.$arena."\n";
    $dir = $DBInfo->cache_dir.'/'.$arena;
    $count = clean_dir($dir, $cachetime);
    echo '** ' . $arena. ': Total ' . $count . " files are deleted! \n";
}

function clean_dir($dir, $cachetime = 0) {
    $handle = opendir($dir);
    if (!is_resource($handle)) return 0;

    echo '*** ' . $dir . "\n";
    $count = 0;
    while(($file = readdir($handle)) !== false) {
        if ((($p = strpos($file, '.')) !== false or $file == 'RCS' or $file == 'CVS') and is_dir($dir . '/' . $file)) continue;
        if (is_dir($dir . '/' . $file)) {
            $count+= clean_dir($dir . '/' . $file, $cachetime);
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
