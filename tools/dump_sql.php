<?php
/**
 * dump SQL script
 *
 * @author wkpark at kldp.org
 * @since 2015/05/17
 * @license GPLv2
 */

define('INC_MONIWIKI', 1);
$topdir = realpath(dirname(__FILE__).'/../');
include_once($topdir.'/wiki.php');

// Start Main
$Config = getConfig($topdir.'/config.php');
require_once($topdir.'/wikilib.php');
require_once($topdir.'/lib/wikiconfig.php');
require_once($topdir.'/lib/timer.php');

include_once(dirname(__FILE__).'/utils.php');

$Config = wikiConfig($Config);
$DBInfo = new WikiDB($Config);

$tablename = 'documents';

$params = array();
if (class_exists('Timer')) {
    $timing = new Timer();
    $params['timer'] = &$timing;
    $params['timer']->Check("load");
}

if ($DBInfo->text_dir[0] != '/')
    $text_dir = $topdir.'/'.$DBInfo->text_dir;
else
    $text_dir = $DBInfo->text_dir;

// get args

$options = array();
$options[] = array("t", "type", "sql type\n\t\t\t(support 'mysql', 'sqlite')");
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

if (empty($args['t'])) {
    $type = 'mysql';
} else {
    $type = $args['t'];
}

echo 'Selected type : '.$type."\n";

$ans = ask('Are you sure ? [y/N]', 'n');
if ($ans == 'n') {
    exit;
}

// get remain $argv array
foreach($args as $k=>$v) {
    while($i = array_search('-'.$k, $argv)) {
        if ($i)
            unset($argv[$i]);
        if (preg_match("/^.*".$k.":.*$/i", $short_opts))
            unset($argv[$i + 1]);
    }
}

$argv = array_merge($argv);

$progress = array('\\','|','/','-');

function dump($str) {
    global $fp;
    fwrite($fp, $str);
}

set_time_limit(0);

$date = gmdate("Y-m-d-His", time());
$dumpfile = 'dump-'.$date.'.sql';
$fp = fopen($dumpfile, 'w');
if (!is_resource($fp)) {
    echo 'Unable to open '.$dumpfile,"\n";
    exit;
}

$date = gmdate("Y-m-d H:i:s", time()).' KST';
dump("-- dumped at $date\n");
$schema = make_sql(dirname(__FILE__).'/../lib/schemas/dump.sql', '', $type);
dump($schema);

dump("\n");

$files = array();
// check dump file list
if (is_file($argv[1])) {
    $handle = fopen($argv[1], 'r');
    if (!is_resource($handle)) {
        echo "Can't open $argv[1]\n";
        exit;
    }
    while (($name = fgets($handle, 2048)) !== false) {
        if ($name[0] == '#') continue;
        $name = rtrim($name, "\n");
        $key = $DBInfo->pageToKeyname($name);
        $pagefile = $text_dir.'/'.$key;
        if (file_exists(!$pagefile))
            continue;
        $files[] = $key;
    }
    fclose($handle);
} else {
    $handle = opendir($text_dir);
    if (!is_resource($handle)) {
        echo "Can't open $DBInfo->text_dir\n";
        exit;
    }
    while (($file = readdir($handle)) !== false) {
        if ($file[0] == '.' || in_array($file, array('RCS', 'CVS')))
            continue;
        $pagefile = $text_dir.'/'.$file;
        if (is_dir($pagefile))
            continue;
        $files[] = $file;
    }
    closedir($handle);
}

$idx = 0;
$buffer = array();

$j = 0;
echo ' ';
foreach ($files as $file) {
    $j++;
    print "".($progress[$j % 4]);
    $j++;
    if ($file[0] == '.' || in_array($file, array('RCS', 'CVS')))
        continue;
    $pagefile = $text_dir.'/'.$file;
    if (is_dir($pagefile))
        continue;

    $pagename = $DBInfo->keyToPagename($file);
    $body = file_get_contents($pagefile);
    //$body = str_replace("\n", "\\n", $body);
    $mtime = filemtime($pagefile);
    $idx++;

    $buffer[] = "('"._escape_string($type, $pagename)."','"._escape_string($type, $body)."',".$mtime.")";
    if ($idx > 100) {
        dump('INSERT INTO '.$tablename.' (title,body,`mtime`) VALUES '.implode(",\n", $buffer).";\n");
        $idx = 0;
        $buffer = array();
    }
}

if (sizeof($buffer) > 0) {
    dump('INSERT INTO '.$tablename.' (title,body,`mtime`) VALUES '.implode(",\n", $buffer).";\n");
}

fclose($fp);

echo "\t",$dumpfile,' generated',"\n";

$params['timer']->Check('done');
echo $params['timer']->Write();

// vim:et:sts=4:sw=4:
