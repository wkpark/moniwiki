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
// Param: stat_no_merge_ip_users = 0; // do not merge ip users contributions
// Param: stat_no_show_all=0; // do not show all users statistics info.
// Query: merge_ip_users=1 // merge ip users contributions
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
                else if (($p = strpos($user,' ')) !== false)
                    $user = substr($user, 0, $p);

                if (!isset($users[$user]))
                    $users[$user] = array('add'=>0,
                            'del'=>0,
                            'edit'=>0,
                            'rev'=>array(),
                            'ip'=>array());
                $users[$user]['add']+= $add;
                $users[$user]['del']+= $del;
                $users[$user]['rev'][] = 'r'.$rev;
                $users[$user]['edit']++;
                $revs[] = 'r'.$rev;

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
        // last user and last revision
        if (is_array($users[$k]['rev']) and in_array('r'.$rev, $users[$k]['rev'])) {
            $author = $k;
            break;
        }
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

function _calc_contrib_points($edits, $total_edits, $max_edits, $mean_edits, $adds, $dels, $total_lines, $mean_adds, $mean_dels) {
    $denorm = 1 + $mean_adds / $total_lines * 0.5 + $mean_dels / $total_lines * 0.2;
    $norm = $edits / $total_edits + $adds / $total_lines * 0.5 + $dels / $total_lines * 0.2;
    return $norm / $denorm;
}

function _render_stat($formatter, $retval, $params = array()) {
    global $DBInfo;

    $show_table = false;
    if (empty($DBInfo->stat_no_show_all) || in_array($params['id'], $DBInfo->members))
        $show_table = true;

    extract($retval);

    // basic information
    $total_lines = $formatter->page->lines();
    $orig_author = $author;
    $count_revs = count($revs);
    $count_users = count($users);

    // parameters
    $first_contribution_author_ratio = 85;

    //echo '<pre>';
    //print_r($users);

    $authors = array_keys($users);
    $edits = array();
    $user_adds = array();
    $user_dels = array();

    $orig_lines = $total_lines;
    $total_adds = 0;
    $total_dels = 0;
    foreach ($authors as $n) {
        $edits[$n] = $users[$n]['edit'];
        $user_adds[$n] = $users[$n]['add'];
        $user_dels[$n] = $users[$n]['del'];
        $total_adds+= $users[$n]['add'];
        $total_dels+= $users[$n]['del'];
    }
    $orig_lines-= $total_adds - $total_dels;

    // fix original author info.
    if ($rev === '1.1') {
        $users[$orig_author]['add'] += $orig_lines;
        $user_adds[$orig_author] += $orig_lines;
    }
    $adds[$count_revs - 1] = $orig_lines;
    $total_adds += $orig_lines;

    //echo "<pre>";
    //print_r($adds);
    //print_r($dels);
    //print_r($edits);
    //echo '</pre>';

    // sort by edits
    arsort($edits);

    $contribs = array();
    $total_edits = $count_revs;
    $mean_edits = $count_revs / $count_users;
    $max_edits = $users[$authors[0]]['edit'];
    $mean_adds = $total_adds / $count_users;
    $mean_dels = $total_dels / $count_users;
    foreach ($edits as $u=>$c) {
        $point = _calc_contrib_points($c, $total_edits, $max_edits, $mean_edits,
                $user_adds[$u], $user_dels[$u], $total_lines, $mean_adds, $mean_dels);
        $count = sprintf("%4.1f %%", round($point * 100, 1));
        $contribs[$u] = $count;
    }

    $authors = $others = array_keys($edits);
    array_shift($others);

    if (isset($params['retval'])) {
        $ret = array(
            'total_revs'=>$count_revs,
            'initial_rev'=>$rev,
            'original_author'=>$orig_author,
            'total_editors'=>$count_users,
            'contributions'=>$contribs
        );

        if ($count_users == 1)
            $ret['first_author'] = $orig_author;
        else if ($contribs[$authors[0]] > $first_contribution_author_ratio)
            $ret['first_author'] = $authors[0];

        $params['retval'] = $ret;
        return 0;
    }

    foreach ($others as $u) {
        $contribs[$u].= ' (<span class="diff-added">'.sprintf("%4.1f", round(($user_adds[$u] / $user_adds[$authors[0]] * 100), 2));
        $contribs[$u].= '</span>/';
        $contribs[$u].= '<span class="diff-removed">'.sprintf("%4.1f", round(($user_dels[$u] / $user_dels[$authors[0]] * 100), 2));
        $contribs[$u].= '</span>)';
    }

    $u = preg_match('/^([0-9]+\.){3}[0-9]+$/', $orig_author) ? _mask_hostname($orig_author) : $orig_author;

    $ou = $count_users == 1 ? $orig_author : $authors[0];
    $ou = preg_match('/^([0-9]+\.){3}[0-9]+$/', $ou) ? _mask_hostname($ou) : $ou;

    $out = "<div class='wikiInfo'><h2>"._("Revision Statistics")."</h2>\n";

    $out.= '<h3>'.sprintf(_("Total %d revisions"), $count_revs).', ';
    $out.= sprintf(_("Total %d editors"), sizeof($users)).'</h3>'."\n";
    $out.= '<table class="wiki center">';
    $out.= '<tr><th>'._("Initial revision").'</th><td>'.$rev.'</td></tr>'."\n";
    $out.= '<tr><th>'._("Original Author").'</th><td>'.$u.'</td></tr>'."\n";
    if ($count_users == 1 && $show_table)
        $out.= '<tr><th>'._("First Contribution Author").'</th><td>'.$ou.'</td></tr>'."\n";
    else if ($show_table && $contribs[$authors[0]] > $first_contribution_author_ratio)
        $out.= '<tr><th>'._("First Contribution Author").'</th><td>'.$ou.'</td></tr>'."\n";
    $out.= '</table> <br />'."\n";

    if ($show_table):
    $out.= '<table class="wiki center"><tr><th>'._("User").'</th><th>'._("Add").
        '</th><th>'._("Del").'</th><th>'._("Edit").'</th><th>'._("Contributions").'</th><th>'._("IP").'</th></tr>';
    foreach ($edits as $u=>$c) {
        $v = $users[$u];
        $ips = array_map('_mask_hostname', array_keys($v['ip']));
        $uu = preg_match('/^([0-9]+\.){3}[0-9]+$/', $u) ? _mask_hostname($u) : $u;
        $out.= '<tr><td>'.$uu.'</td><td><span class="diff-added">+'.$v['add'].'</span></td><td>'.
            '<span class="diff-removed"> -'.$v['del'].'</span></td><td> '.
            $v['edit']."</td>";
        $out.= '<td>'.$contribs[$u].'</td>';
        $out.= '<td>'.implode(', ', $ips)."</td></tr>\n";
    }
    $out.= '</table></div>';
    endif;

    // binning
    $c = sizeof($times);
    $min = intval($times[$c - 1] / 3600) * 3600;
    $max = intval($times[0] / 3600) * 3600;

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
    } else {
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

    for ($i = $c - 1; $i >= 0; $i--) {
        $j = intval(($times[$i] - $min) / $bin);
        $add_bins[$j]+= $adds[$i];
        $del_bins[$j]-= $dels[$i];
        $sum[$j]+= - $adds[$i] + $dels[$i];
    }

    $sum[$szbin] = $total_lines;
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
    return $out."<div class='info-stat'><canvas id='info-stat'></canvas>".$barchart.'</div>';
}

function macro_Stat($formatter, $value, $options = array()) {
    global $DBInfo;

    if (!empty($DBInfo->version_class)) {
        $cache = new Cache_Text('infostat');

        $retval = array();
        if (!$formatter->refresh and $cache->exists($formatter->page->name)) {
            $retval = $cache->fetch($formatter->page->name);
        }

        if (empty($retval)) {
            $version = $DBInfo->lazyLoad('version', $DBInfo);
            $out = $version->rlog($formatter->page->name, '', '', '-z');

            $retval = array();
            if (!isset($out[0])) {
                $msg = _("No older revisions available");
                $info = "<h2>$msg</h2>";
            } else {
                $params = array();
                $params['all'] = 1;
                $params['id'] = $options['id'];
                $params['retval'] = &$retval;
                $ret = _stat_rlog($formatter, $out, $params);
            }

            if (!empty($retval))
                $cache->update($formatter->page->name, $retval);
        }

        $merge_ip = true;
        if (!empty($DBInfo->stat_no_merge_ip_users) && empty($options['merge_ip_users']))
            $merge_ip = false;

        if ($merge_ip) {
            $users = &$retval['users'];
            foreach ($users as $k=>$v) {
                foreach ($v['ip'] as $ip=>$dummy) {
                    $users[$k]['add']+= $users[$ip]['add'];
                    $users[$k]['del']+= $users[$ip]['del'];
                    $users[$k]['edit']+= $users[$ip]['edit'];
                    $users[$k]['rev'] = array_merge($users[$k]['rev'], $users[$ip]['rev']);
                    unset($users[$ip]);
                }
            }
        }

        if (!empty($retval)) {
            if (isset($options['retval'])) {
                $info = _render_stat($formatter, $retval, $options);
                return $info;
            } else {
                $info = _render_stat($formatter, $retval, $options);
            }
            $formatter->register_javascripts('js/chart/Chart.min.js');
        }

        if (isset($options['retval']))
            return -1;
    } else {
        $msg = _("Version info is not available in this wiki");
        $info = "<h2>$msg</h2>";
    }
    if (isset($options['retval']))
        return -1;
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
