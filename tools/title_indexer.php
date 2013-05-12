<?php
/**
 * Title indexer by wkpark at gmail.com
 * 2012/04/22
 */

define('INC_MONIWIKI',1);
include_once("wiki.php");

# Start Main
$Config=getConfig("config.php");
require_once("wikilib.php");
require_once("lib/win32fix.php");
require_once("lib/wikiconfig.php");
require_once("lib/cache.text.php");
require_once("lib/timer.php");
require_once("lib/indexer.DBA.php");

$Config = wikiConfig($Config);
$DBInfo= new WikiDB($Config);

$options=array();
if (class_exists('Timer')) {
    $timing = new Timer();
    $options['timer']=&$timing;
    $options['timer']->Check("load");
}

$indexer = new Indexer_DBA('titlesearch', 'w', $DBInfo->dba_type, 'new');
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
    print "* [$ii] $pagename ";
    $ii++;

    $words = getTokens($pagename);
    $k = get_key($pagename);

    print " - ($k) - ";
    print ' '.count($words)."\n";
    $words[] = "\010".$k;
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
