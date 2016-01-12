<?php
/**
 * metaparse.php
 *
 * @author wkpark at kldp.org
 * @since 2015/12/09
 * @license GPLv2
 */

define('INC_MONIWIKI', 1);
$topdir = realpath(dirname(__FILE__).'/../');
include_once($topdir.'/wiki.php');

// Start Main
// check a local config file config.local.php
if (file_exists($topdir.'/config.local.php'))
    $Config = getConfig($topdir.'/config.local.php');
else
    $Config = getConfig($topdir.'/config.php');
require_once($topdir.'/wikilib.php');
require_once($topdir.'/lib/wikiconfig.php');
require_once($topdir.'/lib/timer.php');
require_once($topdir.'/lib/win32fix.php');
require_once($topdir.'/lib/cache.text.php');

include_once(dirname(__FILE__).'/utils.php');

// setup some variables
$Config = wikiConfig($Config);
$DBInfo = new WikiDB($Config);

$params = array();
if (class_exists('Timer')) {
    $timing = new Timer();
    $params['timer'] = &$timing;
    $params['timer']->Check("load");
}

// get args
$options = array();
$options[] = array("i", "interwiki", "interwiki name");
$options[] = array('t', 'type', "metadb type (compat)");
$options[] = array('d', 'dbname', "metadb file name");
$options[] = array("w", '', "overwrite");
$short_opts = ''; // list of short options.
foreach ($options as $item) {
    $opt = $item[0];
    if ($item[1]) { // if long option exists
        $opt.= ':';
    }
    $short_opts.= $opt;
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

$overwrite = false;
if (isset($args['w'])) {
    $overwrite = true;
}

if (empty($args['i'])) {
    $interwiki = 'KoWikiPedia';
} else {
    $interwiki = $args['i'];
}

if (!empty($args['t'])) {
    $type = $args['t'];
} else {
    $type = 'compact';
}

if (!empty($args['d'])) {
    $dbname = $args['d'];
} else {
    $dbname = null;
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

set_time_limit(0);

// check titleindex list
$titleindex = $argv[1];
if (!is_file($titleindex)) {
    echo "Titleindex file not found\n";
    exit;
}

if (empty($dbname)) {
    if (empty($DBInfo->metadb)) $DBInfo->initMetaDB();
    $metadb = $DBInfo->metadb;
} else {
    require_once(dirname(__FILE__).'/../lib/metadb.'.$type.'.php');
    $class = 'MetaDB_'.$type;
    $metadb = new $class($dbname, $Config['dba_type']);
}

if (!method_exists($metadb, 'parse')) {
    echo "ERROR: 'parse' method is not found\n";
    exit;
}

$ret = $metadb->parse($titleindex, $interwiki);
if ($ret === false) {
    echo "ERROR: parse error\n";
    echo "$titleindex, $interwiki\n";
    exit;
}

$params['timer']->Check('done');
echo $params['timer']->Write();

// vim:et:sts=4:sw=4:
