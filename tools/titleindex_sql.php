<?php
/**
 * TitleIndexer for SQL (MySQL, SQLite3)
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

$params = array();
if (class_exists('Timer')) {
    $timing = new Timer();
    $params['timer'] = &$timing;
    $params['timer']->Check("load");
}

$text_dir = $topdir.'/'.$DBInfo->text_dir;

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

$feedback = ''; // global error messege
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

function dump($str) {
    global $fp;
    fwrite($fp, $str);
}

set_time_limit(0);

$handle = opendir($text_dir);
if (!is_resource($handle)) {
    echo "Can't open $DBInfo->text_dir\n";
    exit;
}

$fp = fopen('titleindex_init.sql', 'w');
if (!is_resource($fp)) {
    echo 'Unable to open titleindex_init.sql',"\n";
    exit;
}

$date = gmdate("Y-m-d H:i:s", time()).' KST';
dump("-- titleindex dump at $date\n");
$schema = make_sql(dirname(__FILE__).'/../lib/schemas/titleindex.sql', '', $type);
dump($schema);
dump("\n");

$idx = 0;
$buffer = array();

$tablename = 'titleindex';
while (($file = readdir($handle)) !== false) {
    if ($file[0] == '.' || in_array($file, array('RCS', 'CVS')))
        continue;
    $pagefile = $text_dir.'/'.$file;
    if (is_dir($pagefile))
        continue;
    $mtime = filemtime($pagefile);

    $pagename = $DBInfo->keyToPagename($file);
    $idx++;

    $buffer[] = "('"._escape_string($type, $pagename)."',".$mtime.")";
    if ($idx > 100) {
        dump('INSERT INTO '.$tablename.' (title, `mtime`) VALUES '.implode(",\n", $buffer).";\n");
        $idx = 0;
        $buffer = array();
    }
}

if (sizeof($buffer) > 0) {
    dump('INSERT INTO '.$tablename.' (title, `mtime`) VALUES '.implode(",\n", $buffer).";\n");
}

fclose($fp);

closedir($handle);

echo "\t",'titleindex_init.sql generated',"\n";

$params['timer']->Check('done');
echo $params['timer']->Write();

// vim:et:sts=4:sw=4:
