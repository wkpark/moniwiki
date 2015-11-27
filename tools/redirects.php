<?php
/**
 * check redirect caches and update invert indices
 * @author  wkpark at gmail.com
 * @since   2012/11/26
 * @license GPLv2
 */

define('INC_MONIWIKI', 1);
$topdir = realpath(dirname(__FILE__).'/../');
include_once($topdir."/wiki.php");

// Start Main
$Config = getConfig($topdir.'/config.php');
require_once($topdir.'/wikilib.php');
require_once($topdir.'/lib/win32fix.php');
require_once($topdir.'/lib/wikiconfig.php');
require_once($topdir.'/lib/cache.text.php');
require_once($topdir.'/lib/timer.php');

include_once(dirname(__FILE__).'/utils.php');

$Config = wikiConfig($Config);
$DBInfo = new WikiDB($Config);

$p = $DBInfo->getPage('FrontPage');
$formatter = new Formatter($p);
if (empty($formatter->wordrule)) $formatter->set_wordrule();

$params = array();
if (class_exists('Timer')) {
    $timing = new Timer();
    $params['timer'] = &$timing;
    $params['timer']->Check("load");
}

$options = array();
$options[] = array('f', '', "force update redirect caches");
$options[] = array('d', '', "debug");
$short_opts = ''; // list of short options.
foreach ($options as $item) {
    $opt = $item[0];
    if ($item[1]) { // if long option exists
        $opt .= ':';
    }
    $short_opts .= $opt;
}

$options[] = array('-help', '', "\tdisplay this message");

if (!empty($argv) && in_array('--help', $argv)) {
    print "Usage: $argv[0] [option]...\n\n";
    print "Options:\n";
    foreach($options as $message) {
        if ($message[1]) $value = "<$message[1]>";
        else $value = "";
        print "\t-$message[0] $value\t$message[2]\n";
    }
    exit;
}

$args = getopt($short_opts);

$force_update = false;
if (isset($args['f']))
    $force_update = true;

$debug = false;
if (isset($args['d']))
    $debug = true;

if ($DBInfo->text_dir[0] != '/')
    $text_dir = $topdir.'/'.$DBInfo->text_dir;
else
    $text_dir = $DBInfo->text_dir;

$handle = opendir($text_dir);
if (!is_resource($handle)) {
    echo "Can't open $text_dir\n";
    exit;
}

set_time_limit(0);

$rd = new Cache_Text('redirect');
$rds = new Cache_Text('redirects');

$ret = array();
$retval = array();
$ret['retval'] = &$retval;

// check all redirect caches
echo 'Check redirect caches',"\n";
$files = array();
$rd->_caches($files, array('prefix'=>1));
echo ' * redirect = ', count($files),"\n";

$progress = array('\\','|','/','-');
$redirects = array();

$j = 1;
// remove old legacy code
$found_old = false;
foreach ($files as $f) {
    // low level _fetch(), _remove()
    $info = $rd->_fetch($f, 0, $ret);
    echo "\r".($progress[$j % 4]);
    $j++;

    // simply remove old case
    if (is_string($info)) {
        echo "\r",'remove old redirect: ', $info,"\n";
        $rd->_remove($f);
        $found_old = true;
    } else if (!$found_old) {
        $redirect = $retval['id'];
        $dest = $info[0];
        if (!isset($redirects[$dest]))
            $redirects[$dest] = array();
        $redirects[$dest][] = $redirect;
    }
}

if ($debug)
    var_dump($redirects);

// check redirect invert indices
echo "\r", 'Check invert redirect indices',"\n";
$rds = new Cache_Text('redirects');
$files = array();
$rds->_caches($files, array('prefix'=>1));
echo "\r",' * invert redirect indices = ', count($files),"\n";
$update_redirects = true;
$j = 0;
foreach ($files as $f) {
    echo "\r".($progress[$j % 4]);
    $j++;
    // low level _fetch(), _remove()
    $info = $rds->_fetch($f, 0, $ret);
    $id = $retval['id'];
    if (!$found_old && !isset($redirects[$id])) {
        // already removed
        $rds->_remove($f);
        $update_redirects = false;
        echo "\r",'remove deleted redirect: ', $id,"\n";
    }
}

if (!$force_update && $update_redirects) {
    foreach ($redirects as $k=>$v) {
        $rds->update($k, $v);
    }

    echo "\r",'Invert redirect indices are updated.',"\n";
    $params['timer']->Check("done");
    echo $params['timer']->Write();
    exit;
}

$j = 1;
while (($file = readdir($handle)) !== false) {
    if ($file[0] == '.' || is_dir($text_dir.'/'.$file)) continue;
    $pagename = $DBInfo->keyToPagename($file);

    $fp = fopen($text_dir.'/'.$file, 'r');
    if (!is_resource($fp))
        continue;
    $pi = fgets($fp, 2048);
    fclose($fp);

    if (isset($pi[0]) && $pi[0] == '#' && preg_match('@^#redirect\s@i', $pi)) {
        echo "\r".($progress[$j % 4]);
        //echo "* [$j] $pagename ","\n";
        $j++;
        $redirect = substr($pi, 10);
        $redirect = rtrim($redirect, "\n");

        if (($pos = strpos($redirect, 'http://')) === 0) {
            $fixed = rawurldecode($fixed);
            echo "\r",$pagename.' : '.$fixed.' - '.$redirect,"\n";
            continue;
        }
        if (($pos = strpos($redirect, '#')) > 0) {
            $redirect = substr($redirect, 0, $pos);
        }
        $rd->update($pagename, array($redirect));
    }
}
closedir($handle);

echo "\n",'Done',"\n";
$params['timer']->Check("done");
echo $params['timer']->Write();

// vim:et:sts=4:sw=4:
