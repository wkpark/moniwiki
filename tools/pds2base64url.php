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

$src_dir = $topdir.'/'.$DBInfo->upload_dir;
$dest_dir = $topdir.'/pds.new';

if (!file_exists($dest_dir)) {
    _mkdir_p($dest_dir);
}

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
    echo "Can't open $DBInfo->upload_dir\n";
    exit;
}

echo "#!/bin/sh\n";
echo "CP='cp -ap '\n";

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
            $pagename = $compat->keyToPagename($key);
            $newname = $base64->pageToKeyname($pagename);
            //echo ' * ',$pagename,"\n";

            if (!empty($DBInfo->use_hashed_upload_dir)) {
                $prefix = get_hashed_prefix($newname);
                $newname = $prefix.$newname;
            }
            echo '$CP ', $dirs[$i],' ', $dest_dir,'/',$newname,"\n";
        }
    }
}

closedir($handle);
$options['timer']->Check("done");
echo $options['timer']->Write();

// vim:et:sts=4:sw=4:
