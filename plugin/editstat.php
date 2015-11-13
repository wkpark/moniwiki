<?php
// Copyright 2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a editstat plugin for the MoniWiki
//
// Since: 2015-11-07
// Name: EditStatPlugin
// Description: EditStat Plugin
// URL: MoniWiki:EditStatPlugin
// Version: $Revision: 1.0 $
// Depend: 1.2.5
// License: GPLv2
//
// Usage: ?action=editstat&type=
//

require_once(dirname(__FILE__).'/editlogbin.php');

function macro_EditStat($formatter, $value = '', $params = array()) {
    $q = '?action=editstat';
    if (!empty($value)) {
        // parse args
        $tmp = preg_replace('@\s*(=|,)\s*@', '\1', $value);
        $tmp = trim($tmp, ',');
        $tmp = preg_replace('@,@', '&', $tmp);
        parse_str($tmp, $args);

        foreach ($args as $k=>$v) {
            if (in_array($k, array('h', 'days')))
                $q.= '&amp;'.$k.'='.intval($v);
            else if ($k == 'user')
                $q.= '&amp;type=user';
        }
    }
    return '<img src="'.$formatter->link_url('', $q).'" />';
}

function do_editstat($formatter, $params = array()) {
    global $Config;

    $opts = array();
    $opts['.oldest'] = !empty($Config['editstat_datetime_oldest']) ?
        $Config['editstat_datetime_oldest'] : '-1 year';

    if (!empty($params['days']))
        $days = intval($params['days']);
    else
        $days = 50; // default 50 days
    if ($days > 200) $days = 200;

    $opts['.max_range'] = $days.' day';

    // image height
    if (!empty($params['h']))
        $height = intval($params['h']);
    else
        $height = 30; // default image height
    if ($height > 200) $height = 200;

    // check request paramters
    if (isset($params['type']) && $params['type'] == 'user')
        $type = 'user_count';
    else
        $type = 'data';

    // round timestamp
    $tmp = time();
    $tmp = date('Y-m-d 00:00:00', $tmp);
    $time = strtotime($tmp);

    // setup headers
    $lastmod = substr(gmdate('r', $time), 0, -5).'GMT';
    $tag = $type.','.$time.','.$days.','.$height;
    $etag = md5($tag);
    $need = http_need_cond_request($time, $lastmod, $etag);
    if (!$need) {
        header('HTTP/1.0 304 Not Modified');
        @ob_end_clean();
        return;
    }

    // get editlogbin
    $data = cached_editlogbin($formatter, $opts);

    // check max
    $j = 0;
    $max = 0;
    foreach ($data[$type] as $idx=>$c) {
        if ($c > $max) $max = $c;
        if (++$j > 30) break;
    }

    // graph parameters
    $wpen = 3; // pen width
    $gap = 1; // margin
    // $days + today
    $width = ($days + 1) * ($wpen + $gap) + $gap;
    $nolab = false;

    // make transparent image
    $canvas = imagecreatetruecolor($width, $height + ($nolab ? 0 : 8) + $gap * 2);
    imagealphablending($canvas, false);
    $bg = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
    imagefill($canvas, 0, 0, $bg);
    imagesavealpha($canvas, true);
    $pens = array();
    $r = 128;
    $g = 128;
    $b = 128;
    $pens[0] = imagecolorallocate($canvas, $r, $g, $b);
    $r = 185;
    $g = 180;
    $b = 184;
    $pens[1] = imagecolorallocate($canvas, $r, $g, $b);
    if (!$nolab) {
        $lab = $type[0] == 'u' ? 'Edit users' : 'Editstat';
        imagestring($canvas, 1, 2, $height + 1, $lab.' '.date('Y-m-d H:i'), $pens[0]);
    }

    $x = $gap;
    $tmp = strtotime('-'.$days.' days'); // week
    $time = strtotime(date('Y-m-d 00:00:00', $tmp));
    $n = date('w', $time) - 1;
    $pen = 0;
    foreach ($data[$type] as $idx=>$c) {
        $h = (int)($c/$max * $height + 0.5);
        if ($n >= 7) {
            // change pen color for each weeks
            $pen++;
            $pen = $pen % 2;
            $n = 0;
        }
        imagefilledrectangle($canvas, $x, $height, $x + $wpen - 1, $height - $h, $pens[$pen]);
        $x+= $wpen + $gap;
        $n++;
    }

    // setup expires
    $time = strtotime('+1 day');
    $tmp = strtotime(date('Y-m-d 00:00:00', $time));
    $expires = gmdate("D, d M Y H:i:s", $tmp).' GMT';

    header('Content-Type: image/png');
    $maxage = 60*60*24;
    header('Cache-Control: public, s-maxage='.$maxage.', max-age='.$maxage);
    header('Last-Modified: '.$lastmod);
    header('Expires: '.$expires);
    header('ETag:"'.$etag.'"');
    imagepng($canvas);
    imagedestroy($canvas);
}

// vim:et:sts=4:sw=4:
