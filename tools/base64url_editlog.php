<?php
/**
 * base64url editlog converter
 *
 * @author wkpark at gmail.com
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

$data_dir = $topdir.'/'.$DBInfo->data_dir;
$editlog = $data_dir.'/editlog';
$editlog_new = $data_dir.'/editlog.new';

$fp = fopen($editlog, 'r');

if (!is_resource($fp)) {
    echo "Can't open $editlog\n";
    exit;
}

$np = fopen($editlog_new, 'w');
if (!is_resource($np)) {
    echo "Can't open $editlog\n";
    fclose($fp);
    exit;
}

$ii = 0;
$buffer = '';
while (($line = fgets($fp, 4096)) !== false) {
    $tmp = explode("\t", $line);
    $name = $compat->keyToPagename($tmp[0]);
    $name = $base64->pageToKeyname($name);
    $tmp[0] = trim($name);
    $new = implode("\t", $tmp);
    $ii++;
    $buffer.= $new;

    if ($ii > 500) {
        fwrite($np, $buffer);
        $ii = 0;
        $buffer = '';
    }
}
fclose($fp);

if (isset($buffer[0]))
    fwrite($np, $buffer);

fclose($np);

$options['timer']->Check("done");
echo $options['timer']->Write();

// vim:et:sts=4:sw=4:
