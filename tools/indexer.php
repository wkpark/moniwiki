<?php

define('INC_MONIWIKI',1);
include_once("wiki.php");

# Start Main
$Config=getConfig("config.php");
include("wikilib.php");
include("lib/win32fix.php");
#include("lib/tokenizer.php");
include("lib/indexer.DBA.php");

$DBInfo= new WikiDB($Config);

$options=array();
$timing = &new Timer();
$options['timer']=&$timing;
$options['timer']->Check("load");

$indexer = new Indexer_DBA('fullsearch', 'w', $DBInfo->dba_type, 'new');
#$indexer->test();
#exit;

$handle = opendir($DBInfo->text_dir);
if (!is_resource($handle)) {
    echo "Can't open $DBInfo->text_dir\n";
    exit;
}

$ii = 1;
while (($file = readdir($handle)) !== false) {
    if (is_dir($DBInfo->text_dir."/".$file)) continue;
    $pagename = $DBInfo->keyToPagename($file);
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
$indexer->packWords();

$indexer->close();
closedir($handle);

// vim:et:sts=4:sw=4:
