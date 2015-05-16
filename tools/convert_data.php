<?php
/**
 * old % encoding to base64url encoding convert script generator
 *
 * @author wkpark at kldp.org
 * @since 2015/05/14
 * @license GPLv2
 */

define('INC_MONIWIKI',1);
$topdir = realpath(dirname(__FILE__).'/../');
include_once($topdir."/wiki.php");

# Start Main
$Config = getConfig($topdir."/config.php");
require_once($topdir."/wikilib.php");
require_once($topdir."/lib/win32fix.php");
require_once($topdir."/lib/wikiconfig.php");
require_once($topdir."/lib/cache.text.php");
require_once($topdir."/lib/timer.php");

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

require_once($topdir."/lib/pagekey.compat.php");
require_once($topdir."/lib/pagekey.base64url.php");

$compat = new PageKey_compat($DBInfo);
$base64 = new PageKey_base64url($DBInfo);

set_time_limit(0);

$text_dir = $topdir.'/'.$DBInfo->text_dir;
$dest_dir = $topdir.'/data.new/text';

if (!file_exists($dest_dir)) {
    _mkdir_p($dest_dir.'/RCS');
}

$handle = opendir($text_dir);
if (!is_resource($handle)) {
    echo "Can't open $DBInfo->text_dir\n";
    exit;
}

$idx = 0;
while (($file = readdir($handle)) !== false) {
    if ($file[0] == '.' || in_array($file, array('RCS', 'CVS')))
        continue;
    $pagefile = $text_dir.'/'.$file;
    if (is_dir($pagefile))
        continue;

    $pagename = $compat->keyToPagename($file);
    $newname = $base64->pageToKeyname($pagename);
    $idx++;

    $oldrcs = $text_dir.'/RCS/'.$file.',v';
    $newrcs = $dest_dir.'/RCS/'.$newname.',v';

    echo 'cp ',$text_dir,'/',$file,' ',$dest_dir,'/',$newname,"\n";
    if (file_exists($oldrcs)) {
        echo 'cp ',$oldrcs,' ',$newrcs,"\n";
    }
}
closedir($handle);
$options['timer']->Check("done");
echo $options['timer']->Write();

// vim:et:sts=4:sw=4:
