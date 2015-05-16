<?php
/**
 * data/text convert script
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

include_once(dirname(__FILE__).'/utils.php');

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

$supported = array('compat', 'utf8fs', 'base64url', 'utf8', 'base64');
$alias = array('utf8'=>'utf8fs', 'base64'=>'base64url');

$program = $argv[0];

function usage() {
    global $argv;
    echo 'Usage: ',$argv[0],' fromenc toenc srcdata destdata',"\n";
    echo "\t",'eg) ',$argv[0], ' compat utf8fs data data.new',"\n";
}

if ($argc < 3) {
    usage();
    exit;
}

$text_dir = $topdir.'/'.$DBInfo->text_dir;

$fromenc = $argv[1];
$toenc = $argv[2];

// check encoding names
if (in_array($fromenc, $supported))
    if (isset($alias[$fromenc]))
        $fromenc = $alias[$fromenc];

if (in_array($toenc, $supported))
    if (isset($alias[$toenc]))
        $toenc = $alias[$toenc];

if ($fromenc == $toenc) {
    usage();
    exit;
}
echo "\t* ",$fromenc, ' => ', $toenc,"\n";

// check src/dest dirs
if (isset($argv[3]) && is_dir($argv[3])) {
    if (is_dir($argv[3].'/text/RCS')) {
        $text_dir = $argv[3].'/text';
    } else if (is_dir($argv[3].'/RCS')) {
        $text_dir = $argv[3];
    }
} else {
    $text_dir = $topdir.'/'.$DBInfo->text_dir;
    $dest_dir = $topdir.'/data.new/text';
}

if (isset($argv[4]) && $argv[3] != $argv[4]) {
    if (!file_exists($argv[4])) {
        _mkdir_p($argv[4].'/text/RCS');
    } else if (!file_exists($argv[4].'/text') && !file_exists($argv[4].'/text/RCS')) {
        _mkdir_p($argv[4].'/text/RCS');
    } else if (!is_dir($argv[4].'/text') or !is_dir($argv[4].'/text/RCS')) {
        usage();
        exit;
    }

    $dest_dir = $argv[4].'/text';
} else {
    $dest_dir = $topdir.'/data.new';
    if (!file_exists($dest_dir)) {
        _mkdir_p($dest_dir.'/RCS');
    }
}

echo "\t* src dir  = ", $text_dir,"\n";
echo "\t* dest dir = ", $dest_dir,"\n";

$ans = ask('Are you sure ? [y/N]', 'n');
if ($ans == 'n') {
    exit;
}

require_once($topdir."/lib/pagekey.$fromenc.php");
require_once($topdir."/lib/pagekey.$toenc.php");

$from_class = 'PageKey_'.$fromenc;
$to_class = 'PageKey_'.$toenc;

$from = new $from_class($DBInfo);
$to = new $to_class($DBInfo);

set_time_limit(0);

$handle = opendir($text_dir);
if (!is_resource($handle)) {
    echo "Can't open $DBInfo->text_dir\n";
    exit;
}

$fp = fopen('conv.sh', 'w');
if (!is_resource($fp)) {
    echo 'Unable to open conv.sh script',"\n";
    exit;
}

fwrite($fp, '#!/bin/sh'."\n");
fwrite($fp, "CP='cp -a '\n");

$idx = 0;
while (($file = readdir($handle)) !== false) {
    if ($file[0] == '.' || in_array($file, array('RCS', 'CVS')))
        continue;
    $pagefile = $text_dir.'/'.$file;
    if (is_dir($pagefile))
        continue;

    $pagename = $from->keyToPagename($file);
    $newname = $to->pageToKeyname($pagename);
    $check = $to->keyToPagename($newname);
    $idx++;

    $oldrcs = $text_dir.'/RCS/'.$file.',v';
    $newrcs = $dest_dir.'/RCS/'.$newname.',v';

    fwrite($fp, '$CP '.$text_dir.'/'.$file.' '.$dest_dir.'/'.$newname."\n");
    if ($check != $pagename)
        fwrite($fp, '# '.$check.",".$pagename."\n");

    if (file_exists($oldrcs)) {
        fwrite($fp, '$CP '.$oldrcs.' '.$newrcs."\n");
    }
}
closedir($handle);
fclose($fp);

echo "\t",'conv.sh generated',"\n";

$options['timer']->Check("done");
echo $options['timer']->Write();

// vim:et:sts=4:sw=4:
