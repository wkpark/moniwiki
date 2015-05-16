<?php
/**
 * convert old pds to base64url encoded pds
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
    $src_dir = $argv[3];
} else {
    $src_dir = $topdir.'/'.$DBInfo->upload_dir;
}

if (isset($argv[4])) {
    if (is_dir($argv[4])) {
        echo 'Dest dir already exists!',"\n";
        exit;
    }
    $dest_dir = $argv[4];
} else {
    $dest_dir = $src_dir.'.new';
}

//
set_time_limit(0);

if (!file_exists($dest_dir)) {
    _mkdir_p($dest_dir);
}

echo "\t* src dir  = ", $src_dir,"\n";
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

function get_sub_dir($dir) {
    $dh = opendir($dir);
    if (!is_resource($dh)) {
        return array();
    }
    $dirs = array();
    while (($file = readdir($dh)) !== false) {
        if ($file[0] == '.' || in_array($file, array('RCS', 'CVS', 'thumbnails')))
            continue;
        if (is_dir($dir.'/'.$file)) {
            $subdir = $dir.'/'.$file;
            $subdirs = get_sub_dir($subdir);
            if (sizeof($subdirs) > 0)
                $dirs = array_merge($dirs, $subdirs);
        }
    }
    closedir($dh);

    // no subdirs. OK. its real pagename
    if (sizeof($dirs) == 0)
        return array($dir);
    return $dirs;
}

$handle = opendir($src_dir);
if (!is_resource($handle)) {
    echo "Can't open $src_dir\n";
    exit;
}

$fp = fopen('conv.sh', 'w');
if (!is_resource($fp)) {
    echo "Can't open conv.sh\n";
    exit;
}

fwrite($fp, "#!/bin/sh\n");
fwrite($fp, "CP='cp -ap '\n");

$idx = 0;
while (($file = readdir($handle)) !== false) {
    if ($file[0] == '.' || in_array($file, array('RCS', 'CVS')))
        continue;

    $subdir = $src_dir.'/'.$file;
    if (!is_dir($subdir))
        continue;

    $idx++;

    $dirs = get_sub_dir($subdir);
    if (sizeof($dirs)) {
        for ($i = 0; $i < sizeof($dirs); $i++) {
            $key = basename($dirs[$i]);
            $pagename = $from->keyToPagename($key);
            $newname = $to->pageToKeyname($pagename);
            //echo ' * ',$pagename,"\n";

            if (!empty($DBInfo->use_hashed_upload_dir)) {
                $prefix = get_hashed_prefix($newname);
                $newname = $prefix.$newname;
            }
            fwrite($fp, '$CP '.$dirs[$i].' '. $dest_dir.'/'.$newname."\n");
        }
    }
}

fclose($fp);
echo "conv.sh generated!\n";

closedir($handle);
$options['timer']->Check("done");
echo $options['timer']->Write();

// vim:et:sts=4:sw=4:
