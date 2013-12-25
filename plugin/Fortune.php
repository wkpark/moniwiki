<?php
// Copyright 2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Fortune plugin for the MoniWiki
//
// Usage: [[Fortune(science)]]
//
// $Id: Fortune.php,v 1.5 2010/08/18 16:45:51 wkpark Exp $

function macro_FortuneSystem($formatter, $value, $options) {
    // dynamic macro
    if ($formatter->_macrocache and empty($options['call']))
        return $formatter->macro_cache_repl('Fortune', $value);
    $formatter->_dynamic_macros['@Fortune'] = 1;

    $ret= exec(escapeshellcmd("/usr/bin/fortune $value"),$log);
    $out= str_replace("_",'',join("\n",$log));
    return $out;
}

define('DEFAULT_FORTUNE','art');

function do_fortune($formatter, $options) {
    $value = $options['value'];
    $ret = macro_Fortune($formatter, $value, array());
    echo $ret;
}

function macro_Fortune($formatter,$value,$options) {
    global $DBInfo;
    global $_Static;

    if (!isset($_Static['_id_fortune'])) $_Static['_id_fortune'] = 1;
    $id = &$_Static['_id_fortune'];
    $ii = $id;

    // check js option
    $use_js = false;
    if (!empty($value) and preg_match('/^js\s*(?:,\s*)?/', $value, $m)) {
        $use_js = true;
        $value = substr($value, strlen($m[0]));
    }

    if ($use_js) {
        if ($id == 1) {
            $js = <<<JS
<script type="text/javascript">
/*<![CDATA[*/
function wikiFortune(el, cat) {
    var wrapper = document.getElementById(el);
    if (wrapper == null) return;
    if (typeof cat == 'undefined') cat = '';

    // get a FortuneCookie
    var qp = '?'; // query_prefix
    var loc = location.protocol + '//' + location.host;
    if (location.port) loc+= ':' + location.port;
    loc+= location.pathname + qp + 'action=fortune/ajax&value=' + cat;

    var ret = HTTPGet(loc);
    if (ret) {
        wrapper.innerHTML = ret;
    }
}
/*]]>*/
</script>\n
JS;
            $formatter->register_javascripts($js);
        }

        $js1 = <<<JS
<script type="text/javascript">
/*<![CDATA[*/
addLoadEvent(function() { wikiFortune('fortune-$id', '$value'); });
/*]]>*/
</script>\n
JS;
        $formatter->register_javascripts($js1);
        $id++;
        return "<div id='fortune-$ii'></div>";
    }

    // dynamic macro
    if (!$use_js) {
        if ($formatter->_macrocache and empty($options['call']))
            return $formatter->macro_cache_repl('Fortune', $value);
        $formatter->_dynamic_macros['@Fortune'] = 1;
    }

    $cat=$value;
    $dir='/usr/share/games/fortune';
    if (!empty($DBInfo->fortune_dir)) $dir = $DBInfo->fortune_dir;
    if ($cat=='') $cat=DEFAULT_FORTUNE;

    $files=array();
    if ($cat == '*' and ($hd=opendir($dir))) {
        while (($f = readdir($hd)) !== false) {
            if (is_dir($dir."/$f")) continue;
            $files[]=$f;
        }
        closedir($hd);
        $files=preg_grep('/.dat$/',$files,PREG_GREP_INVERT);
        sort($files);
        $icat=rand(0,sizeof($files)-1);
        if (!file_exists($dir.'/'.$files[$icat].'.dat'))
            return 'Not found '.$files[$icat].'.dat';
        $cat=$files[$icat];
    } else if (!file_exists($dir.'/'.$cat.'.dat'))
        return 'Not found '.$cat.'.dat';

    if (!file_exists($dir.'/'.$cat)) return 'Not found '.$cat;

    // get number of quotes
    $fd= fopen($dir.'/'.$cat.'.dat', "rb");
    fseek($fd,4); // skip version
    $sz=unpack('N1N',fread($fd,4));
    $irand=rand(0,$sz['N']-1);

    // real index of quotes
    fseek($fd, 24 + 4 * $irand);
    $a=unpack('N1N',fread($fd,4));
    $iseek=$a['N'];
    fclose($fd);

    $fd=fopen($dir.'/'.$cat,'r');
    fseek($fd, $iseek);
    $out= '';
    while (!feof($fd)) {
        $line=fgets($fd, 1024);
        if ($line[0]=='%') break;
        $out.=$line;
    }
    fclose($fd);

    if (!empty($options['action_mode']) and $options['action_mode']=='macro') {
        $formatter->header('Content-Type: text/plain');
        return $out;
    }
    return '<div class="wikiFortune">'.$out.'</div>';
}
    
// vim:et:sts=4:
