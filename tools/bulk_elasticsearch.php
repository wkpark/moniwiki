<?php
/**
 * ElasticSearch indexer by wkpark at gmail.com
 * 2012/05/25
 */

define('INC_MONIWIKI',1);
include_once("wiki.php");

# Start Main
$Config=getConfig("config.php");
require_once("wikilib.php");
require_once("lib/win32fix.php");
require_once("lib/wikiconfig.php");
require_once("lib/cache.text.php");
require_once("lib/timer.php");
require_once("lib/JSON.php");

$Config = wikiConfig($Config);
$DBInfo= new WikiDB($Config);

$JSON = new Services_JSON();

$options=array();
if (class_exists('Timer')) {
    $timing = new Timer();
    $options['timer']=&$timing;
    $options['timer']->Check("load");
}

$handle = opendir($DBInfo->text_dir);
if (!is_resource($handle)) {
    echo "Can't open $DBInfo->text_dir\n";
    exit;
}

// make elastic search query string
$url = !empty($Config['elasticsearch_host_url']) ? $Config['elasticsearch_host_url'] : 'http://localhost:9200';
$url = rtrim($url, '/').'/';
$url.= !empty($Config['elasticsearch_index']) ? $Config['elasticsearch_index'] : 'moniwiki';

#exec('curl -XPUT '.$url); // make sure to create index.
$DBInfo->lazyLoad('titleindexer');

$url.= '/text';
$elastic_search_url = $url;

$bulk_size = 500;

$i = 0;
$ii = 0;
while (($file = readdir($handle)) !== false) {
    if (is_dir($DBInfo->text_dir."/".$file)) continue;
    $pagename = $DBInfo->keyToPagename($file);
    $i++;
    $ii++;
    print "* [$i] $pagename\n";

    #$_id = urlencode($pagename); // pagename is the "_id".
    $content = file_get_contents($DBInfo->text_dir.'/'.$file);
    $mtime = filemtime($DBInfo->text_dir.'/'.$file);
    $data = array();
    $data['title'] = $pagename;
    $data['body'] = $content;
    $data['date'] = gmdate("Y-m-d\TH:i:s", $mtime);
    $js = $JSON->encode($data);

    if (empty($tmpf))
        $tmpf = tempnam("/tmp", "JSON");

    $fp = fopen($tmpf, 'a+');
    if (is_resource($fp)) {
        fwrite($fp, "{ \"index\" : { \"_id\" : \"".
            str_replace(array('"', '/'), array('\"', '\\/'),
            $pagename)."\"} }\n");
        fwrite($fp, $js."\n");
        fclose($fp);
    }

    if ($ii % $bulk_size == 0) {
        $url = $elastic_search_url.'/_bulk';
        exec("curl $url --data-binary @$tmpf");
        unlink($tmpf);
        usleep(100);
        $ii = 0;
        $tmpf = '';
    }
}

if (file_exists($tmpf)) {
    $url = $elastic_search_url.'/_bulk';
    exec("curl $url --data-binary @$tmpf");
    unlink($tmpf);
}

closedir($handle);

// vim:et:sts=4:sw=4:
