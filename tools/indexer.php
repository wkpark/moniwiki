<?php

define('INC_MONIWIKI',1);
include_once("wiki.php");

# Start Main
$Config=getConfig("config.php");
include("wikilib.php");
include("lib/win32fix.php");
include("lib/search.DBA.php");

$DBInfo= new WikiDB($Config);

$options=array();
$timing = &new Timer();
$options['timer']=&$timing;
$options['timer']->Check("load");

$indexer = new IndexDB_DBA('fullsearch', 'w', $DBInfo->dba_type);
#$indexer->test();
#exit;

$pages = $DBInfo->getPageLists();
$ii = 1;
foreach ($pages as $pagename) {
    $p = $DBInfo->getPage($pagename);
    print "* [$ii] $pagename ";
    $ii++;
    if (!$p->exists()) continue;

    $raw = $p->_get_raw_body();
    $words = getTokens($raw);

    print ' '.count($words)."\n";
    $indexer->addWordCache($pagename, $words);

    if (count($indexer->wordcache) > 10000)
        $indexer->flushWordCache(false);
    #$indexer->addWords($pagename, $words);
}
$indexer->flushWordCache();

$indexer->close();

// vim:et:sts=4:sw=4:
