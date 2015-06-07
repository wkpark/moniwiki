<?php
// Copyright 2013-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a stat plugin for the MoniWiki
//
// Since: 2013-04-20
// Name: StatPlugin
// Description: Stat Plugin
// URL: MoniWiki:StatPlugin
// Version: $Revision: 1.0 $
// Depend: 1.1.3
// License: GPLv2
//
// Usage: ?action=stat
//

function _stat_rlog($formatter, $log, $options = array()) {
    global $DBInfo;

    $tz_offset = $formatter->tz_offset;

    $state = 0;
    $flag = 0;

    $time_current = time();

    $simple = $options['simple'] ? 1:0;

    $url = $formatter->link_url($formatter->page->urlname);

    $users = array();
    $rr = 0;

    $adds = array();
    $dels = array();
    $times = array();
    $ok = 0;
    $count = 0;
    $showcount = ($options['count'] > 5) ? $options['count'] : 100;

    for(;!empty($line) or !empty($log); list($line,$log) = explode("\n", $log, 2)) {
        if (!$state) {
            if (!preg_match("/^---/",$line)) { continue; }
            else { $state = 1; continue; }
        }
        if ($state == 1 and $ok == 1) {
            break;
        }
        switch($state) {
            case 1:
                $rr++;
                preg_match("/^revision ([0-9a-f\.]+)\s*/", $line, $match);
                $rev = $match[1];
                if (preg_match("/\./", $match[2])) {
                    $state = 0;
                    break;
                }
                $state = 2;
                break;
            case 2:
                $inf = preg_replace("/date:\s([0-9\/:\s]+)(;\s+author:.*;\s+state:.*;)?/",
                        "\\1", $line);
                list($inf, $change) = explode('lines:', $inf, 2);

                if (preg_match('/^[0-9]+$/', $inf)) {
                    $rrev = '#'.$rr;
                    $ed_time = $inf;
                    $inf = gmdate("Y-m-d H:i:s", $ed_time + $tz_offset);
                } else {
                    $ed_time=strtotime($inf.' GMT');
                }

                preg_match("/\+(\d+)\s\-(\d+)/", $change, $match);
                if ($match) {
                    $adds[] = $add = $match[1];
                    $dels[] = $del = $match[2];
                    $times[] = $ed_time;
                } else {
                    $adds[] = 0;
                    $dels[] = 0;
                    $times[] = $ed_time;
                }

                $state = 3;
                break;
            case 3:
                $dummy = explode(';;', $line, 3);
                $user = $dummy[1];
                $ip = $dummy[0];
                if (substr($user, 0, 9) == 'Anonymous')
                    $user = $ip;
                if (!isset($users[$user]))
                    $users[$user] = array('add'=>0,
                            'del'=>0,
                            'edit'=>0,
                            'rev'=>array(),
                            'ip'=>array());
                $users[$user]['add']+= $add;
                $users[$user]['del']+= $del;
                $users[$user]['rev'][] = $rev;
                $users[$user]['edit']++;
                $revs[] = $rev;

                if ($user != $ip && !isset($users[$ip]))
                    $users[$ip] = array('add'=>0,
                            'del'=>0,
                            'edit'=>0,
                            'rev'=>array(),
                            'ip'=>array());
                if ($user != $ip)
                    $users[$user]['ip'][$ip] = true;

                $state = 4;
                break;
            case 4:
                if (!$rev) break;

                $rrev = $rrev ? $rrev : $rev;
                $rrev = '';

                $state = 1;
                $flag++;
                $count++;
                if ($options['all'] != 1 and $count >= $showcount) $ok = 1;
                break;
        }
    }

    foreach ($users as $k=>$v) {
        foreach ($v['ip'] as $ip=>$dummy) {
            $users[$k]['add']+= $users[$ip]['add'];
            $users[$k]['del']+= $users[$ip]['del'];
            $users[$k]['edit']+= $users[$ip]['edit'];
            $users[$k]['rev'] = array_merge($users[$k]['rev'], $users[$ip]['rev']);
            unset($users[$ip]);
        }

        // last user and last revision
        if (is_array($users[$k]['rev']) and in_array($rev, $users[$k]['rev']))
            $author = $k;
    }

    $options['retval'] = array('author'=>$author,
                'rev'=>$rev,
                'revs'=>$revs,
                'adds'=>$adds,
                'dels'=>$dels,
                'users'=>$users,
                'times'=>$times);

    return true;
}

function _render_stat($formatter, $retval) {
    extract($retval);

    $out = "<div class='wikiInfo'><h2>"._("Revision Statistics")."</h2>\n";

    $orig_author = $author;

    $out.= _("Original Author").': '.$orig_author.' '._("initial revision").':'.$rev.'<br />';
    $out.= sprintf(_("Total %d editors"), sizeof($users)).'<br />';
    $out.= '<table class="wiki center"><tr><th>'._("User").'</th><th>'._("Add").
        '</th><th>'._("Del").'</th><th>'._("Edit").'</th><th>'._("IP").'</th></tr>';
    foreach ($users as $u=>$v) {
        $ips = array_map('_mask_hostname', array_keys($v['ip']));
        $u = preg_match('/^([0-9]+\.){3}[0-9]+$/', $u) ? _mask_hostname($u) : $u;
        $out.= '<tr><td>'.$u.'</td><td><span class="diff-added">+'.$v['add'].'</span></td><td>'.
            '<span class="diff-removed"> -'.$v['del'].'</span></td><td> '.
            $v['edit']."</td>";
        $out.= '<td>'.implode(', ', $ips)."</td></tr>\n";
    }
    $out.= '</table>';

    // binning
    $c = sizeof($times);
    $min = $times[$c - 1];
    $max = $times[0];

    $range = $max - $min;

    if ($c > 50) {
        $bin = $range / 20;
        $szbin = 20;
    } else if ($c > 20) {
        $bin = $range / 10;
        $szbin = 10;
    } else if ($c > 5) {
        $bin = $range / 5;
        $szbin = 5;
    } else if ($c > 2) {
        $bin = $range / $c;
        $szbin = $c;
    }

    $add_bins = array();
    $del_bins = array();
    $sum = array();
    for ($i = 0; $i <= $szbin; $i++) {
        $add_bins[$i] = 0;
        $del_bins[$i] = 0;
        $sum[$i] = 0;
    }

    $line = $formatter->page->lines();
    for ($i = $c - 1; $i > 0; $i--) {
        $j = intval(($times[$i] - $min) / $bin);
        $add_bins[$j]+= $adds[$i];
        $del_bins[$j]-= $dels[$i];
        $sum[$j]+= - $adds[$i] + $dels[$i];
    }

    $sum[$szbin] = $line;
    for ($i = $szbin - 1; $i >= 0; $i--) {
        $sum[$i] += $sum[$i + 1];
    }

    $barchart = '';
    $labels = array();
    for ($j = 0; $j <= $szbin; $j++) {
        $labels[$j] = gmdate('Y-m-d', $j * $bin + $min);
    }
    $lab = '["'.implode('","', $labels).'"]';
    $add = '['.implode(',', $add_bins).']';
    $del = '['.implode(',', $del_bins).']';
    $sum = '['.implode(',', $sum).']';

    $barchart = <<<EOS
<script type='text/javascript'>
(function() {
var data = {
    labels : $lab,
    datasets : [
        {
            label : "added lines",
            fillColor : "rgba(0,220,0,0.0)",
            strokeColor : "rgba(0,220,0,0.8)",
            pointColor : "rgba(0,220,0,0.6)",
            highlightFill : "rgba(0,220,0,0)",
            highlightStroke : "rgba(0,220,0,1)",
            data : $add
        },
        {
            label : "deleted lines",
            fillColor : "rgba(220,0,0,0.0)",
            strokeColor : "rgba(220,0,0,0.8)",
            pointColor : "rgba(220,0,0,0.6)",
            highlightFill : "rgba(220,0,0,0.75)",
            highlightStroke : "rgba(220,0,0,1)",
            data : $del
        },
        {
            label : "total lines",
            fillColor : "rgba(0,220,220,0.0)",
            strokeColor : "rgba(0,220,220,0.8)",
            pointColor : "rgba(0,220,220,0.7)",
            highlightFill : "rgba(0,220,220,0)",
            highlightStroke : "rgba(0,220,220,1)",
            data : $sum
        }
    ]
};

// onload
var oldOnload = window.onload;
window.onload = function(ev) {
    try { oldOnload(); } catch(e) {};
    var ctx = document.getElementById("info-stat").getContext("2d");
    window.myBar = new Chart(ctx).Line(data,
        { scaleBeginAtZero:false, scaleIntegersOnly:false,responsive:true });
};
})();
</script>
EOS;
    $dump = '';
    for ($j = 0; $j < $szbin; $j++) {
        $dump.= 'add= '.$add_bins[$j].', del= '.$del_bins[$j].', sum= '.$sum[$j]."\n";
    }
    return $out."<div class='info-stat'><canvas id='info-stat'></canvas>".$barchart;
}

function macro_Stat($formatter, $value, $options = array()) {
    global $DBInfo;

    if (!empty($DBInfo->version_class)) {
        $cache = new Cache_Text('infostat');

        $retval = array();
        if (!$formatter->refresh and $cache->exists($formatter->page->name)) {
            $retval = $cache->fetch($formatter->page->name);
        }

        if (!$retval) {
            $version = $DBInfo->lazyLoad('version', $DBInfo);
            $out = $version->rlog($formatter->page->name, '', '', '-z');

            $retval = array();
            if (!isset($out[0])) {
                $msg = _("No older revisions available");
                $info = "<h2>$msg</h2>";
            } else {
                $options['all'] = 1;
                $options['retval'] = &$retval;
                $ret = _stat_rlog($formatter, $out, $options);
            }

            if (!empty($retval))
                $cache->update($formatter->page->name, $retval);
        }

        if (!empty($retval)) {
            $info = _render_stat($formatter, $retval);
            $formatter->register_javascripts('js/chart/Chart.min.js');
        }
    } else {
        $msg = _("Version info is not available in this wiki");
        $info = "<h2>$msg</h2>";
    }
    return $info;
}


function do_stat($formatter, $options) {
    global $DBInfo;

    $formatter->send_header('', $options);
    $formatter->send_title('', '', $options);

    print macro_stat($formatter, '', $options);
    echo $formatter->get_javascripts();
    $formatter->send_footer($args, $options);
}

// vim:et:sts=4:sw=4:
