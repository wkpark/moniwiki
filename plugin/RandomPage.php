<?php
// Copyright 2003-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a RandomPage plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2003-05-10
// Name: RandomPage macro plugin
// Description: show RandomPages
// URL: MoniWiki:RandomPagePlugin
// Version: $Revision: 1.0 $
// License: GPLv2
//
// Usage: [[RandomPage]] or ?action=randompage
//

function macro_RandomPage($formatter, $value = '', $params = array()) {
    global $DBInfo;

    $count = '';
    $mode = '';
    if (!empty($value)) {
        $vals = get_csv($value);
        if (!empty($vals)) {
            foreach ($vals as $v) {
                if (is_numeric($v)) {
                    $count = $v;
                } else if (in_array($v, array('simple', 'nobr', 'js'))) {
                    $mode = $v;
                }
            }
        }
    }

    if ($formatter->_macrocache and empty($options['call']) and $mode != 'js')
        return $formatter->macro_cache_repl('RandomPage', $value);
    $formatter->_dynamic_macros['@RandomPage'] = 1;

    if ($count <= 0) $count=1;
    $counter= $count;

    $max = $DBInfo->getCounter();

    if (empty($max))
        return '';

    $number=min($max,$counter);

    if ($mode == 'js') {
        static $id = 1;
        $myid = sprintf("randomPage%02d", $id);
        $id++;
        $url = $formatter->link_url('', "?action=randompage/ajax&value=".$number);
        return <<<EOF
<div id='$myid'>
</div>
<script type='text/javascript'>
/*<![CDATA[*/
(function () {
   var msg = HTTPGet("$url");
   var ret;
   if (msg != null && (ret = eval(msg))) {
      var div = document.getElementById("$myid");
      var ul = document.createElement('UL');
      for(var i = 0; i < ret.length; i++) {
        var li = document.createElement('LI');
        li.innerHTML = ret[i];
        ul.appendChild(li);
      }
      div.appendChild(ul);
   }
})();
/*]]>*/
</script>
EOF;
    }

    // select pages
    $selected = array();
    for ($i = 0; $i < $number; $i++) {
        $selected[] = rand(0, $max - 1);   
    }
    $selected = array_unique($selected);
  
    sort($selected);
    $sel_count = count($selected);

    $indexer = $DBInfo->lazyLoad('titleindexer');
    $sel_pages = $indexer->getPagesByIds($selected);

    $selects = array();
    foreach ($sel_pages as $item) {
        $selects[]=$formatter->link_tag(_rawurlencode($item),"",_html_escape($item));
    }

    if (isset($params['call']))
        return $selects;

    if ($count > 1) {
        if (!$mode)
            return "<ul>\n<li>".join("</li>\n<li>",$selects)."</li>\n</ul>";
        if ($mode=='simple')
            return join("<br />\n",$selects)."<br />\n";
        if ($mode=='nobr')
            return join(" ",$selects);
    }
    return join("",$selects);
}

function do_RandomPage($formatter,$options='') {
    global $DBInfo;

    if (!empty($options['action_mode']) and $options['action_mode'] == 'ajax') {
        $val = !empty($options['value']) ? intval($options['value']) : '';

        $params = $options;
        $params['call'] = 1;
        $ret = macro_RandomPage($formatter, $val, $params);
        if (function_exists('json_encode')) {
            echo json_encode($ret);
        } else {
            require_once(dirname(__FILE__).'/../lib/JSON.php');
            $json = new Services_JSON();
            echo $json->encode($ret);
        }
        return;
    }

    $max = $DBInfo->getCounter();
    $rand = rand(1,$max);

    $indexer = $DBInfo->lazyLoad('titleindexer');
    $sel_pages = $indexer->getPagesByIds(array($rand));
    $options['value'] = $sel_pages[0];
    do_goto($formatter,$options);
    return;
}

// vim:et:sts=4:sw=4:
