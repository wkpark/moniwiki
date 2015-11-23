<?php
/**
 * import SQL dump to text
 *
 * @author wkpark at kldp.org
 * @since 2015/11/19
 * @license GPLv2
 */

define('INC_MONIWIKI', 1);
$topdir = realpath(dirname(__FILE__).'/../');
include_once($topdir.'/wiki.php');

// Start Main
$Config = getConfig($topdir.'/config.php');
require_once($topdir.'/wikilib.php');
require_once($topdir.'/lib/wikiconfig.php');
require_once($topdir.'/lib/timer.php');

include_once(dirname(__FILE__).'/utils.php');

// simple parser to convert MySQL dump string to array
function get_values($line, &$offset) {
    // ('PageName' 'Hello World',timestamp),('Title','Hello World',timestamp),...
    $pos = $offset;
    $flag = -1;
    $out = array();
    $buff = '';
    while (isset($line[$pos])) {
        $ch = $line[$pos];
        if ($flag < 0) {
            if ($ch == '(') {
                $pos++;
                continue;
            }
            if ($ch == ')' && $line[$pos + 1] == ',') {
                if (isset($buff[0]))
                    $out[] = $buff;
                $buff = '';
                $pos+= 2;
                continue;
            }

            if ($ch == ',') {
                if (isset($buff[0]))
                    $out[] = $buff;
                $buff = '';
                $pos++;
                continue;
            }
            if ($ch == "'") {
                if (isset($buff[0]))
                    $out[] = $buff;
                $buff = '';
                $flag = 0;
                $pos++;
                continue;
            }
            $buff.= $ch;
            $pos++;
            continue;
        }

        if ($flag >= 0) {
            if ($ch == '\\') {
                $ch = $line[$pos + 1];
                if ($ch == 'n')
                    $buff.= "\n";
                else
                    $buff.= $ch;
                $pos++;
            } else if($line[$pos] == "'") {
                $out[] = $buff;
                $buff = '';
                $flag = -1;
            } else {
                $buff.= $ch;
            }
        }
        $pos++;
    }
    return $out;
}

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
$options[] = array("t", "type", "sql type\n\t\t\t(support 'mysql', 'sqlite')");
$options[] = array("d", "dir", "dest dir");
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
    $type = 'mysql';
} else {
    $type = $args['t'];
}

if (empty($args['d'])) {
    $dest_dir = 'temp_dir';
} else {
    $dest_dir = $args['d'];
}

echo 'Selected type : '.$type."\n";

$progress = array('\\','|','/','-');

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
set_time_limit(0);

if (!is_dir($dest_dir))
    mkdir($dest_dir);

$dumpfile = $argv[1];
if (!is_file($dumpfile)) {
    print "Usage: $argv[0] [option]...\n\n";
    print "Options:\n";
    foreach($options as $message) {
        if ($message[1]) $value = "<$message[1]>";
        else $value = "";
        print "\t-$message[0] $value\t$message[2]\n";
    }

    exit;
}

$fp = fopen($dumpfile, 'r');
$lp = fopen('dump.lst', 'w');
if (!is_resource($fp) || !is_resource($lp)) {
    echo 'Unable to open '.$dumpfile,"\n";
    exit;
}

$idx = 0;
$buffer = array();

define('DUMP_TITLE', 0);
define('DUMP_BODY', 1);
define('DUMP_TIMESTAMP', 2);
$fields = 3;

$j = 0;
while (($line = fgets($fp, 8192)) !== false) {
    while (substr($line, -1) != "\n" && ($tmp = fgets($fp, 8192)) !== false) {
        $line.= $tmp;
    }
    if (preg_match('@^INSERT\s@', $line)) {
        $line = preg_replace('@^INSERT.*?VALUES\s@', '', $line);
    } else if ($line[0] != '(') {
        continue;
    }

    print "\r".($progress[$j % 4]);
    $line = rtrim($line, "\n;");
    $pos = strpos($line, '(');
    if ($pos !== false) {
        $line = substr($line, $pos);
        $offset = 0;
        $arr = get_values($line, $offset);

        for ($i = 0; $i < sizeof($arr); $i+= $fields) {
            // pagename
            $pagename = $arr[$i + DUMP_TITLE];
            // filename
            $filename = $DBInfo->pageToKeyname($pagename);
            // mtime
            $mtime = $arr[$i + DUMP_TIMESTAMP];

            fwrite($lp, $pagename."\t".$filename."\n");
            $body = $arr[$i + DUMP_BODY];
            echo "\r",$pagename,"\n";
            if (strlen($filename) < 255) {
                if (!file_exists($dest_dir.'/'.$filename))
                    file_put_contents($dest_dir.'/'.$filename, $body);
                if ($mtime > 0)
                    @touch($dest_dir.'/'.$filename, $mtime);
            } else {
                echo 'ERR: long name: ', $pagename, "\n";
            }
            
        }
    }
    $j++;
}

fclose($fp);
fclose($lp);

$params['timer']->Check('done');
echo $params['timer']->Write();

// vim:et:sts=4:sw=4:
