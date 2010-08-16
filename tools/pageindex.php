<?php
// init pageindex cache

define('INC_MONIWIKI',1);
include_once("wiki.php");

# Start Main
$Config=getConfig("config.php");
include("wikilib.php");
include("lib/win32fix.php");
#include("lib/tokenizer.php");
include("lib/PageIndex.php");

$DBInfo= new WikiDB($Config);

$options=array();
$timing = &new Timer();
$options['timer']=&$timing;
$options['timer']->Check("load");

$indexer = new PageIndex($DBInfo);
$indexer->init();

// vim:et:sts=4:sw=4:
