<?php
/**
 * editlog converter
 *
 * @author  wkpark at kldp.org
 * @since   2015/05/14
 * @date    2015/05/16
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
    echo 'Usage: ',$argv[0],' fromenc toenc editlog new_editlog',"\n";
    echo "\t",'eg) ',$argv[0], ' compat utf8fs data/editlog data/editlog.new',"\n";
}

if ($argc < 3) {
    usage();
    exit;
}

$data_dir = $topdir.'/'.$DBInfo->data_dir;
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

if (isset($argv[3]) and file_exists($argv[3])) {
    // check
    $fp = fopen($argv[3], 'r');
    if (!is_resource($fp)) {
        echo 'Unable to open editlog',"\n";
        exit;
    }

    // is it valid editlog ?
    $line = fgets($fp, 1024);
    $tmp = explode("\t", $line);
    if (sizeof($tmp) != 7 ) {
        echo 'Invalid editlog',"\n";
        fclose($fp);
        exit;
    }
    fclose($fp);
} else {
    // default editlog
    $editlog = $data_dir.'/editlog';
}

if (isset($argv[4])) {
    if (is_dir($argv[4]))
        $editlog_new = $argv[4].'/editlog';
    else
        $editlog_new = $argv[4];

    if ($editlog == $editlog_new)
        $editlog_new.= '.new';
} else {
    $editlog_new = $data_dir.'/editlog.new';
}

echo "\t* editlog  = ", $editlog,"\n";
echo "\t* dest dir = ", $editlog_new,"\n";

$ans = ask('Are you sure ? [y/N]', 'n');
if ($ans == 'n') {
    exit;
}

if (file_exists($argv[4])) {
    $ans = ask('Are you sure to overwrite '.$argv[4].' ? [y/N]', 'n');
    if ($ans == 'n')
        exit;
}

require_once($topdir."/lib/pagekey.$fromenc.php");
require_once($topdir."/lib/pagekey.$toenc.php");

$from_class = 'PageKey_'.$fromenc;
$to_class = 'PageKey_'.$toenc;

$from = new $from_class($DBInfo);
$to = new $to_class($DBInfo);

set_time_limit(0);

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
    $name = $from->keyToPagename($tmp[0]);
    $name = $to->pageToKeyname($name);
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
