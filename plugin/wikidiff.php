<?php
// Copyright 2003-2016 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a WikiDiff plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@gmail.com>
// Date: 2015-11-26
// Name: WikiDiff plugin
// Description: WikiDiff Plugin
// URL: MoniWiki:WikiDiffPlugin
// Version: $Revision: 1.0 $
// License: GPLv2
//
// Usage: ?action=wikidiff
//

function do_wikidiff($formatter, $params = array()) {
    global $Config;

    $supported = array(
        'default'=>'%0%2?action=raw',
        'namuwiki'=>'%1raw/%2',
    );

    if (!empty($Config['wikidiff_sites'])) {
        $wikis = $Config['wikidiff_sites'];
    } else {
        $wikis = array(
            'kowikipedia'=>'https://ko.wikipedia.org/wiki/',
            'librewiki'=>'http://librewiki.net/wiki/',
            'namuwiki'=>'https://namu.wiki/raw/',
        );
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST' &&
            isset($_POST['wiki']) && isset($wikis[$_POST['wiki']])) {
        require_once(dirname(__FILE__).'/../lib/HTTPClient.php');

        $wiki = $_POST['wiki'];
        if (isset($supported[$wiki]))
            $format_url = $supported[$wiki];
        else
            $format_url = $supported['default'];

        $url = $wikis[$wiki];
        $parsed = parse_url($url);

        if (isset($_POST['value'][0]))
            $pagename = rawurlencode($_POST['value']);
        else
            $pagename = $formatter->page->urlname;

        // translate table.
        $trs = array(
            '%0'=>$url,
            '%1'=>$parsed['scheme'].'://'.$parsed['host'].'/',
            '%2'=>$pagename,
        );

        $request_url = strtr($format_url, $trs);

        $save = ini_get('max_execution_time');
        set_time_limit(0);

        $http = new HTTPClient;
        $http->timeout = 15; // set timeout

        // support proxy
        if (!empty($Config['proxy_host'])) {
            $http->proxy_host = $Config['proxy_host'];
            if (!empty($Config['proxy_port']))
                $http->proxy_port = $Config['proxy_port'];
        }

        $http->sendRequest($request_url, array(), 'GET');
        set_time_limit($save);

        $formatter->send_header('', $params);
        if ($http->status != 200) {
            $params['.title'] = sprintf(_("Fail to connect %s"), $http->status);
            $diff = null;
        } else {
            $diff = $formatter->get_diff($http->resp_body);
            $params['.title'] = sprintf(_("Difference between this wiki and %s."), $wiki);
        }
        $formatter->send_title('', '', $params);

        if (isset($diff[0])) {
            echo "<div id='wikiDiffPreview'>\n";
            echo $formatter->processor_repl('diff', $diff, $params);
            echo "</div>\n";
        } else {
            if ($http->status != 200) {
                echo sprintf(_("Status: %s"), $http->status);
            } else {
                echo _("No difference found.");
            }
        }
        $formatter->send_footer('', $params);

        return;
    }

    $select = '<select name="wiki">';
    $select .= '<option>'._("-- Select Wiki --").'</option>';
    foreach ($wikis as $w=>$url) {
        $select .= '<option value="'.$w.'">'.$w.'</option>'."\n";
    }
    $select .= '</select>';

    $name = isset($_GET['value'][0]) ? $_GET['value'] : '';
    $default = _html_escape($formatter->page->name);
    $optional = '<br />'._("Page name:").
            ' <input type="text" name="value" placeholder="'.$default.'" value="'._html_escape($name).'" /><br />';
    //$optional .= _("Reverse order:")." <input type='checkbox' name='reverse' /> ";

    $params['.title'] = _("Show difference between wikis.");
    $button = _("Diff");

    $formatter->send_header('', $params);
    $formatter->send_title('', '', $params);
    echo <<<FORM
<form method='post'>
$select
$optional
<input type='submit' value='$button' />
<input type='hidden' name='action' value='wikidiff' />
</form>
FORM;

    $formatter->send_footer('', $params);

    return;
}

// vim:et:sts=4:sw=4:
