<?php
/**
 * Pagelinks and Backlinks generator by wkpark at gmail.com
 *
 * @since 2012/05/17
 * @license GPLv2
 */

define('INC_MONIWIKI',1);
include_once("wiki.php");

# Start Main
$Config = getConfig("config.php");
require_once("wikilib.php");
require_once("lib/win32fix.php");
require_once("lib/wikiconfig.php");
require_once("lib/cache.text.php");
require_once("lib/timer.php");

$Config = wikiConfig($Config);
$DBInfo = new WikiDB($Config);

$p = $DBInfo->getPage('FrontPage');
$formatter = new Formatter($p);
if (empty($formatter->wordrule)) $formatter->set_wordrule();

$options=array();
if (class_exists('Timer')) {
    $timing = new Timer();
    $options['timer']=&$timing;
    $options['timer']->Check("load");
}

$handle = opendir($DBInfo->text_dir);
if (!is_resource($handle)) {
    echo "Can't open $DBInfo->text_dir\n";
    exit;
}

set_time_limit(0);

$cache = new Cache_Text('pagelinks');
$bc = new Cache_Text('backlinks');

$ii = 1;
while (($file = readdir($handle)) !== false) {
    if (is_dir($DBInfo->text_dir."/".$file)) continue;
    $pagename = $DBInfo->keyToPagename($file);
    print "* [$ii] $pagename ";
    $ii++;

    $p = $DBInfo->getPage($pagename);
    $formatter->page = $p;

    $raw = $p->_get_raw_body();
    $pagelinks = get_pagelinks($formatter, $raw);
    $cache->update($pagename, $pagelinks);
    foreach ($pagelinks as $a) {
        if (!isset($a[0])) continue;
        $bl = $bc->fetch($a);
        if (!is_array($bl)) $bl = array();
        $bl = array_merge($bl, array($pagename));
        $bc->update($a, $bl);
    }

    print ' '.count($pagelinks)."\n";
    //if ($ii == 1000) break;
}
closedir($handle);
$options['timer']->Check("done");
echo $options['timer']->Write();

// vim:et:sts=4:sw=4:
