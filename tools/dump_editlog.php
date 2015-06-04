<?php
/**
 * dump editlog to SQL
 *
 * @author wkpark at kldp.org
 * @since 2015/06/01
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

$Config = wikiConfig($Config);
$DBInfo = new WikiDB($Config);

$tablename = 'editlog';

$params = array();
if (class_exists('Timer')) {
    $timing = new Timer();
    $params['timer'] = &$timing;
    $params['timer']->Check("load");
}

if ($DBInfo->data_dir[0] != '/')
    $editlog = $topdir.'/'.$DBInfo->data_dir.'/editlog';
else
    $editlog = $DBInfo->data_dir.'/editlog';

// get args

$options = array();
$options[] = array('t', 'type', "sql type\n\t\t\t(support 'mysql', 'sqlite')");
$options[] = array('c', '', "\tpagename numbering without dba support\n");
$short_opts = ''; // list of short options.
foreach ($options as $item) {
    $opt = $item[0];
    if ($item[1]) { // if long option exists
        $opt .= ':';
    }
    $short_opts .= $opt;
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

$compat = isset($args['c']) ? true : false;

echo 'Selected type : '.$type."\n";
echo ($compat ? 'Pagename numbering without DBA support' : 'Pagename numbering with DBA support')."\n";

$ans = ask('Are you sure ? [y/N]', 'n');
if ($ans == 'n') {
    exit;
}

$progress = array('\\','|','/','-');

function dump($str) {
    global $outfp;
    fwrite($outfp, $str);
}

function beginTransaction($type) {
    if ($type == 'mysql')
        dump("START TRANSACTION;\n");
    else
        dump("BEGIN TRANSACTION;\n");
}

function endTransaction($type) {
    if ($type == 'mysql')
        dump("COMMIT;\n");
    else
        dump("END TRANSACTION;\n");
}

function getPageID($pagename, $db) {
    return dba_fetch($pagename, $db);
}

function addPage($pagename, $id, $db, $mtime) {
    $pgid = dba_fetch($pagename, $db);
    if ($pgid !== false)
        return $pgid;

    $ret = dba_replace($pagename, $id, $db);

    if ($ret)
        return $id;
    return -1;
}

set_time_limit(0);

if (function_exists('dba_open')) {
    // pagename db
    $db_pages = '/var/tmp/wikipages.db';

    if (file_exists($db_pages)) {
        $ans = ask('Pagename DB found!'."\n".'Do you want to delete it [Y/n]', 'y');
        if ($ans == 'y') {
            echo "Pagename DB deleted!\n";
            unlink($db_pages);
        }
    }

    $db = dba_open($db_pages, 'n', 'db4');

    if (!is_resource($db)) {
        echo "Unable to open DB!\n";
        exit;
    }
} else {
    echo "No DBA support found!\n";
    $pages_dir = '/var/tmp/wikipages';

    if (is_dir($pages_dir)) {
        $ans = ask('Pagenames dir found!'."\n".'Do you want to delete it [Y/n]', 'y');
        echo 'rm -rf '.$pages_dir.' will be executed'."\n";
        if ($ans == 'y') {
            system('rm -rf '.$pages_dir);
            echo "Pagenames deleted!\n";
        }
    }
}

$fp = fopen($editlog, 'r');
if (!is_resource($fp)) {
    echo "Can't open $editlog\n";
    exit;
}

$date = gmdate("Y-m-d-Hi", time());
$dumpfile = 'editlog-'.$date.'.sql';
$outfp = fopen($dumpfile, 'w');
if (!is_resource($outfp)) {
    echo 'Unable to open '.$dumpfile,"\n";
    exit;
}

$date = gmdate("Y-m-d H:i:s", time()).' KST';
dump("-- dumped at $date\n");
$schema = make_sql(dirname(__FILE__).'/../lib/schemas/editlog.sql', '', $type);
dump($schema);

dump("\n");

$idx = 0;
$buffer = array();

$actions = array(
    'SAVE'=>0,
    'CREATE'=>1,
    'DELETE'=>2,
    'RENAME'=>3,
    'REVERT'=>4,
    'UPLOAD'=>8,
    'SAVESTRANGE'=>16);

beginTransaction($type);

$j = 0;
$curid = 1;
$time = '';
while (($line = fgets($fp, 65535)) !== false) {
    $line = str_replace("\t\t", "\t", $line);
    $parts = explode("\t", $line);

    $page_key = $parts[0];
    $addr = $parts[1];
    $mtime = $parts[2];
    $hostname = $parts[3];
    $user = $parts[4];
    if (sizeof($parts) == 6) {
        $comment = '';
	$action = trim($parts[5]);
    } else {
        $comment = $parts[5];
        $action = trim($parts[6]);
    }

    if (isset($actions[$action])) {
        $act = $actions[$action];
    } else {
        echo "FATAL: Fail to parse log line\n";
        print_r($parts);
	exit;
    }

    $newtime = gmdate("Y-m", $mtime);
    if ($time != $newtime) {
        echo "\r";
        echo $newtime."  ";
	$time = $newtime;
    }

    print "".($progress[$j % 4]);
    $j++;

    $user = $parts[4] == 'Anonymous' ? '' : $parts[4];

    $ipall = '';
    if (($p = strpos($addr, ',')) !== false) {
        $ip = substr($addr, 0, $p);
	$ipall = $addr;
    } else {
        $ip = $addr;
    }
    $ip2long = sprintf("%u", ip2long($ip));

    $page_id = 0;

    $pagename = $DBInfo->keyToPagename($page_key);

    if ($db) {
        if (($id = getPageID($pagename, $db)) !== false) {
            $page_id = $id;
        } else {
            $page_id = addPage($pagename, $curid, $db, $mtime); // page creation time

            if ($page_id < 0) {
                echo "FATAL: Unable to add page!\n";
                exit;
            }

            // page added successfully
            if ($page_id == $curid)
                $curid++;
        }
    } else {
        if (file_exists($pages_dir.'/'.$page_key)) {
            $tmp = file($pages_dir.'/'.$page_key);
            $page_id = $tmp[0];
        } else {
            file_put_contents($pages_dir.'/'.$page_key, $curid);
            touch($pages_dir.'/'.$page_key, $mtime);
            $page_id = $curid;
            $curid++;
        }
    }

    $buffer[] = "(".$page_id.",".$mtime.",'"._escape_string($type, $user)."',".
        "'".$ip."','".$ipall."',".$ip2long.",'"._escape_string($type, $comment)."',".$act.")";

    if ($idx > 100) {
        dump('INSERT INTO '.$tablename.' (page_id, `timestamp`, user, ip, ipall, ip2long, comment, action) VALUES '.implode(",\n", $buffer).";\n");
        $idx = 0;
        $buffer = array();

        if ($j % 10000 == 0) {
            endTransaction($type);
            beginTransaction($type);
        }
    }
    $idx++;
}

if (sizeof($buffer) > 0) {
    dump('INSERT INTO '.$tablename.' (page_id, `timestamp`, user, ip, ipall, ip2long, comment, action) VALUES '.implode(",\n", $buffer).";\n");
}

endTransaction($type);

fclose($outfp);
fclose($fp);

if ($db)
    dba_close($db);

echo "\t",$dumpfile,' generated',"\n";

$params['timer']->Check('done');
echo $params['timer']->Write();

// vim:et:sts=4:sw=4:
