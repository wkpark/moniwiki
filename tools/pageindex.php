<?php
// init pageindex cache

define('INC_MONIWIKI',1);
include_once("wiki.php");

# Start Main
$Config=getConfig("config.php");
require_once("wikilib.php");
require_once("lib/win32fix.php");
require_once("lib/wikiconfig.php");
require_once("lib/cache.text.php");
require_once("lib/PageIndex.php");

$Config = wikiConfig($Config);
$DBInfo= new WikiDB($Config);

$options=array();

if (class_exists('Timer')) {
    $timing = new Timer();
    $options['timer']=&$timing;
    $options['timer']->Check("load");
}

$indexer = new PageIndex();
$indexer->init();

// vim:et:sts=4:sw=4:
