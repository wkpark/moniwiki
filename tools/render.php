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
require_once($topdir.'/lib/cache.text.php');

include_once(dirname(__FILE__).'/utils.php');

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

if (empty($args['d'])) {
    if ($Config['text_dir'][0] != '/')
        $text_dir = $topdir.'/'.$Config['text_dir'];
    else
        $text_dir = $Config['text_dir'];
} else {
    $text_dir = $args['d'];
}

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

// get pagename
$pagename = $argv[1];

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
echo render($pagename, $type, $opts);

$params['timer']->Check('done');
#echo $params['timer']->Write();

// vim:et:sts=4:sw=4:
