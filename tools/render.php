<?php
/**
 * rendering a wiki text to html or mdict compact html
 *
 * @author wkpark at kldp.org
 * @since 2015/09/02
 * @license GPLv2
 */

define('INC_MONIWIKI', 1);
$topdir = realpath(dirname(__FILE__).'/../');
include_once($topdir.'/wiki.php');

// Start Main
// check a local config file config.local.php
if (file_exists($topdir.'/config.local.php'))
    $Config = getConfig($topdir.'/config.local.php');
else
    $Config = getConfig($topdir.'/config.php');
require_once($topdir.'/wikilib.php');
require_once($topdir.'/lib/wikiconfig.php');
require_once($topdir.'/lib/timer.php');
require_once($topdir.'/lib/win32fix.php');
require_once($topdir.'/lib/cache.text.php');

include_once(dirname(__FILE__).'/utils.php');

// setup some variables
$Config['fetch_imagesize'] = 0;
$Config['fetch_images'] = 0;
$Config['nonexists'] = 'nolink';
$Config['fetch_action'] = 'http://fetch_action/';
$Config['pull_url'] = 'http://rigvedawiki.net/w/';
$Config['media_url_mode'] = 1;

$Config = wikiConfig($Config);
$DBInfo = new WikiDB($Config);

$params = array();
if (class_exists('Timer')) {
    $timing = new Timer();
    $params['timer'] = &$timing;
    $params['timer']->Check("load");
}

// get args
$options = array();
$options[] = array("t", "type", "render type\n\t\t\t(support 'mdict', 'html')");
$options[] = array("d", "dir", "directory of text data");
$options[] = array("n", '', "namu markup");
$options[] = array("o", "out", "output directory");
$options[] = array("w", '', "overwrite");
$short_opts = ''; // list of short options.
foreach ($options as $item) {
    $opt = $item[0];
    if ($item[1]) { // if long option exists
        $opt.= ':';
    }
    $short_opts.= $opt;
}

$options[] = array('-help', '', "\tdisplay this message");

if (!empty($argv) && in_array('--help', $argv)) {
    print "Usage: $argv[0] [option]...\n\n";
    print "Options:\n";
    foreach($options as $message) {
        if ($message[1]) $value = "<$message[1]>";
        else $value = "";
        print "\t-$message[0] $value\t$message[2]\n";
    }
    exit;
}

$feedback = ''; // global error messege
$args = getopt($short_opts);

if (empty($args['t'])) {
    $type = 'mdict';
} else {
    $type = $args['t'];
}

if (empty($args['o'])) {
    $output_dir = 'temp_dir';
} else {
    $output_dir = $args['o'];
}

$overwrite = false;
if (isset($args['w'])) {
    $overwrite = true;
}

if (empty($args['d'])) {
    if ($Config['text_dir'][0] != '/')
        $text_dir = $topdir.'/'.$Config['text_dir'];
    else
        $text_dir = $Config['text_dir'];
} else {
    $text_dir = $args['d'];
}

// Formatter options
$opts = array();
if (isset($args['n']))
    $opts = array('filters'=>array('namumarkup'));

// set $text_dir
$DBInfo->text_dir = $text_dir;
$Config['text_dir'] = $text_dir;

// setup locale, $lang
$lang = set_locale('ko_KR', $Config['charset']);
init_locale($lang);
$Config['lang'] = $lang;
$DBInfo->lang = $lang;

// get remain $argv array
foreach($args as $k=>$v) {
    while($i = array_search('-'.$k, $argv)) {
        if ($i)
            unset($argv[$i]);
        if (preg_match("/^.*".$k.":.*$/i", $short_opts))
            unset($argv[$i + 1]);
    }
}
$argv = array_merge($argv);

function render($pagename, $type, $params = array()) {
    global $DBInfo;

    $p = $DBInfo->getPage($pagename);

    $opts = array();
    // parameters for mdict
    if ($type == 'mdict')
        $opts = array('prefix'=>'entry:/');
    $formatter = new Formatter($p, $opts);
    if (isset($params['filters']))
        $formatter->filters = $params['filters'];

    // trash javascripts
    $formatter->get_javascripts();

    // init wordrule
    if (empty($formatter->wordrule)) $formatter->set_wordrule();

    // render
    ob_start();
    $formatter->send_page();
    flush();
    $out = ob_get_contents();
    ob_end_clean();

    // filter for mdict
    if ($type == 'mdict')
        return $formatter->postfilter_repl('mdict', $out);
    else
        return $out;
}

// render
set_time_limit(0);

$progress = array('\\','|','/','-');

// get pagename
$source = $argv[1];
if (is_dir($source)) {
    $DBInfo->text_dir = $text_dir = $argv[1];
    $handle = opendir($text_dir);
    if (!is_resource($handle)) {
        echo "Can't open $source\n";
        exit;
    }

    $files = array();
    while (($file = readdir($handle)) !== false) {
        if ($file[0] == '.' || in_array($file, array('RCS', 'CVS')))
            continue;
        $pagefile = $text_dir.'/'.$file;
        if (is_dir($pagefile))
            continue;
        $files[] = $file;
    }
    closedir($handle);
} else if (is_file($source)) {
    $fp = fopen($source, 'r');
    if (!is_resource($fp)) {
        echo "Can't open $source\n";
        exit;
    }

    echo "Get file list...\n";

    $files = array();
    while (($name = fgets($fp, 2048)) !== false) {
        if ($name[0] == '#')
            continue;

        $name = rtrim($name, "\n");
        $file = $DBInfo->pageToKeyname($name);
        $files[] = $file;
    }
    fclose($fp);
    echo "Done...\n";
}

if (count($files) > 0) {
    // mkdir output dir
    if (!$overwrite && is_dir($output_dir)) {
        echo "ERROR: Output dir '$output_dir' already exists\nPlease rename it and try again\n";
        exit;
    }
    @mkdir($output_dir);

    $j = 0;
    foreach ($files as $file) {
        $j++;
        echo "\r".($progress[$j % 4]);
        $pagefile = $text_dir.'/'.$file;
        if (!file_exists($pagefile))
            continue;

        if (file_exists($output_dir.'/'.$file))
            continue;
        $pagename = $DBInfo->keyToPagename($file);
        echo "\r",$pagename,"\n";
        $html = render($pagename, $type, $opts);
        file_put_contents($output_dir.'/'.$file, $html);
    }
} else {
    $html = render($source, $type, $opts);
    // overwrite output file
    if ($overwrite) {
        $file = $DBInfo->pageToKeyname($source);
        file_put_contents($output_dir.'/'.$file, $html);
    } else {
        echo $html;
    }
}

$params['timer']->Check('done');
#echo $params['timer']->Write();

// vim:et:sts=4:sw=4:
