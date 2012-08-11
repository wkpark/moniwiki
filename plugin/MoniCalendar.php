<?php
// Copyright 2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a sample plugin for the MoniWiki
//
// Usage: [[Test]]
//
// $Id: MoniCalendar.php,v 1.7 2008/12/10 15:15:55 wkpark Exp $

function _parseDays($formatter,$page,$options=array()) {
    $color_table=
        array('{*}'=>'red','/!\\'=>'blue','(!)'=>'green',
            '<!>'=>'yellow',':('=>'purple','(V)'=>'gray',
            'red'=>'red','blue'=>'blue','green'=>'green','purple'=>'purple',
            'yellow'=>'yellow','gray'=>'gray');
    $color_rule=implode('|',array_map('preg_quote',array_keys($color_table)));
    $month=$options['month'] ? $options['month']:
        gmdate('m',time()+$formatter->tz_offset);

    $week_table=array('sun'=>0,'mon'=>1,'tue'=>2,'wed'=>3,'thu'=>4,'fri'=>5,
        'sat'=>6);

    $colorbar_id=1;

    $lines=explode("\n",$page);
    foreach ($lines as $line) {
        if ($line=='' or $line[0]!=' ') continue;
        if (preg_match('/^\s+\*\s((\d{2}|\d{4})[-\/,])?(\d{1,2})[-\/,](\d{1,2})\s(.*)$/',$line,$match)) {
            if ($match[3] >= 1 and $match[3] <=12 and $match[4]<=31) {
                $mon=sprintf("%02d",$match[3]);
                $day=sprintf("%d",$match[4]);
                $info[$mon][$day][]=preg_replace('/</','&lt;',$match[5]).' ';
            }
            #if (preg_match('/(\{\*\}|\(\!\)|\/\!\\\\|<!>|:\()/',$match[5],$m)) {
            if (preg_match('@('.$color_rule.')@',$match[5],$m)) {
                if ($color_table[$m[1]]) {
                    $infocolor[$mon][$day][]=$color_table[$m[1]];
                }
            }
        // if it is range
        } else if (preg_match('/^\s+\*\s'.
            '(\d{1,2})[-\/,](\d{1,2})\-(\d{1,2})[-\/,](\d{1,2})\s(.*)$/',
            $line,$match)) {
            if ($match[1] >= 1 and $match[1] <=12 and $match[2]<=31 and
                $match[3] >= 1 and $match[3] <=12 and $match[4]<=31) {
                if ($match[1]>$match[3]) continue;
                if ($match[2]>=$match[4]) continue;
                #if (preg_match('/(\{\*\}|\(\!\)|\/\!\\\\|<!>|:\(|#[a-f0-9]{3}|#[a-f0-9]{6})\s/i',$match[5],$m)) {
                if (preg_match('@('.$color_rule.'|#[a-f0-9]{3}|#[a-f0-9]{6})\s@i',$match[5],$m)) {
                    if ($m[1][0]=='#') {
                        $color='colorbar';
                        $custcolor=' style="background-color:'.$m[1].'"';
                        $text=substr($match[5],strlen($m[1]));
                    } else {
                        if (preg_match('/^(red|blue|gray|purple|yellow|green)/',$m[1])) {
                            $text=substr($match[5],strlen($m[1]));
                        } else
                            $text=$match[5];
                        $color='colorbar '.$color_table[$m[1]];
                        $custcolor='';
                    }
                } else {
                    $color='colorbar gray';
                    $text=$match[5];
                    $custcolor='';
                }
                    $mon0=sprintf("%02d",$match[1]);
                    $mon1=sprintf("%02d",$match[3]);
                    if ($mon0<$month or $mon1>$month) continue;
                    $start_tag=$match[1].' '.$match[2];
                    $start_tag=$start_tag.' '.md5($start_tag.$text);
                    $text=preg_replace('/</','&lt;',$text);
                    $coloring[$month][$match[2]][$start_tag]=
                        $color." start\"$custcolor><div class='text'>$text</div></li>";
                    // XX month for
                    for ($i=$match[2]+1;$i<$match[4];$i++) {
                        $coloring[$month][$i][$start_tag]=$color."\"$custcolor></li>";
                    }
                    // $extra_class
                    $coloring[$month][$i][$start_tag]=
                        $color." end\"$custcolor></li>";
                #}
            }
        } else if (preg_match('/^\s+\*\s'.
            '(sun|mon|tue|wed|thu|fri|sat)\s(.*)$/i',$line,$match)) {

            $week=$week_table[strtolower($match[1])];
            $info['week'][$week][]=
                preg_replace('/</','&lt;',$match[2]).' ';
        }
    }
    return array($info,$infocolor,$coloring);
}

function macro_MoniCalendar($formatter,$value) {
    global $DBInfo;
    $color_table=
        array('{*}'=>'red','/!\\'=>'blue','(!)'=>'green',
            '<!>'=>'yellow',':('=>'purple');
    static $day_headings= array('Sunday','Monday','Tuesday','Wednesday',
        'Thursday','Friday','Saturday');

    $date=$_GET['date'];
    $tz_offset=&$formatter->tz_offset;

    $prev_tag='&laquo;';
    $next_tag='&raquo;';
    $now=time();

    preg_match("/^(('|\")([^\\2]+)\\2)?,?((\d{4})-?(\d{2}))?,?\s*(.+)?$/i",$value,$match);

    #print_r($match);
    // GET argument has priority
    if ($date) {
        preg_match("/^((\d{4})-?(\d{2})-?(\d{2})?)$/i",$date,$match2);
        $year= $match2[2];
        $month= $match2[3];
        $day= $match2[4];
    } else if ($match[4]) {
        $year= $match[5];
        $month= $match[6];
    }
    /* Validate date. Use system date, if date is not validated */
    if ($month <1 || $month > 12) {
        $year= gmdate('Y',$tz_offset+$now);
        $month= gmdate('m',$tz_offset+$now);
    }
    $date=$year.$month;
    $month=intval($month);
    $year=intval($year);

    if ($match[3])
        $pagename=$match[3];
    else
        $pagename=$formatter->page->name;
    if ($match[7]) {
        $args=explode(',',$match[7]);

        if (in_array ("blog", $args)) $mode='blog';
        if (in_array ("noweek", $args)) $day_heading_length=0;
        if (in_array ("shortweek", $args)) $day_heading_length=1;
        if (in_array ("yearlink", $args)) $yearlink=1;
        if (in_array ("onlyweek", $args)) $oneweek=1;
        if (in_array ("oneweek", $args)) $oneweek=1;
        if (in_array ("column", $args)) {$oneweek=1;$column=1;}
        $pages=array_diff($args,array('blog','noweek','shortweek','yearlink','onlyweek','oneweek','column'));
        $pglist=array();
        if ($pages) {
            foreach ($pages as $pg) {
                if ($DBInfo->hasPage($pg)) $pglist[]=$pg;
            }
        }
    }
    $pglist[]=$pagename;
    $body='';
    foreach ($pglist as $pg) {
        if ($DBInfo->hasPage($pg)) {
            $p=$DBInfo->getPage($pg);
            $body.=$p->_get_raw_body()."\n";
        }
    }

    $prev_month=gmdate('Ym',mktime(0,0,0,$month - 1,1,$year)+$tz_offset);
    $next_month=gmdate('Ym',mktime(0,0,0,$month + 1,1,$year)+$tz_offset);
    if ($yearlink) {
        $prev_year=gmdate('Ym',mktime(0,0,0,$month,1,$year - 1)+$tz_offset);
        $next_year=gmdate('Ym',mktime(0,0,0,$month,1,$year + 1)+$tz_offset);

        $year_prev_tag='&laquo;';
        $year_next_tag='&raquo;';
        $prev_tag='&lsaquo;';
        $next_tag='&rsaquo;';
    }

    list($today,$weektoday)= explode(';',gmdate('d;w',$now+$tz_offset));
    $day = $day ? $day:$today;
    $day = $oneweek ? $day:1;

    if ($oneweek) {
        $prev_week=gmdate('Ymd',mktime(0,0,0,$month,$day-7,$year)+$tz_offset);
        $next_week=gmdate('Ymd',mktime(0,0,0,$month,$day+7,$year)+$tz_offset);
    }

    $start_day = mktime (0,0,0, $month, $day, $year);
    // get info about the first day of the month or first day of the week
    $date_info= explode(';',gmdate('Y;m;w;F',$start_day+$tz_offset));
    $weekday= $oneweek ? 0:$date_info[2];
    if ($oneweek) {
        $day=$day-$date_info[2]; // adjust to start day of the week
        if ($day < 0) {
            $day=gmdate('d',mktime(0,0,0,$month,$day,$year)+$tz_offset);
        }
        $maxcheckday=gmdate('t', $start_day + $tz_offset);
    }
    // number of days in the month
    $maxdays= $oneweek ? $day+6:gmdate('t', $start_day + $tz_offset);
    // weekday (zero based) of the first day of the month

    $month= $date_info[1];
    $year= $date_info[0];

    list($dayinfo,$infocolor,$coloring)=
        _parseDays($formatter,$body);
    $cal.= "<caption class=\"month\">";

    // Adding previous month and year
    if ($yearlink)
        $cal.= $formatter->link_tag($link,"?date=$prev_year",$year_prev_tag).
            '&nbsp;&nbsp;';
    $cal.= $formatter->link_tag($link,"?date=$prev_month",$prev_tag).
        '&nbsp;&nbsp;';
    if ($oneweek)
        $cal.= $formatter->link_tag($link,"?date=$prev_week",$prev_tag).
            '&nbsp;&nbsp;';

    #$calendar.=substr($date_info[month],0,3).' '.$year;
    $cal.=$date_info[3].' '.$year;

    // Adding next month and year
    $cal.= '&nbsp;&nbsp;';
    if ($oneweek)
    $cal.= $formatter->link_tag($link,"?date=$next_week",$next_tag).
        '&nbsp;&nbsp;';
    $cal.=$formatter->link_tag($link,"?date=$next_month",$next_tag);
    if ($yearlink)
        $cal.= '&nbsp;&nbsp;'.
            $formatter->link_tag($link,"?date=$next_year",$year_next_tag);
    $cal.= "</caption>\n";

    if (!$column) {
        $cal.="<tr class='weekhead'>\n";
        foreach ($day_headings as $d) {
            $cal.="<th>".substr($d,0,3)."</th>\n";
        }
        $cal.="</tr>\n";
    }
    $cal.="<tr class='week'>\n";
    $wd=$weekday;

    #take care of the first "empty" days of the month
    while ($wd > 0){
        $wd--;
        $cal .= "<td class='day'>&nbsp;</td>\n";
        if($column) $cal.="</tr>\n</tr>";
    }
 
    $save=$formatter->sister_on;
    $formatter->sister_on=0;
    $colorkeys=array();
    while ($day <= $maxdays){
        if($column or $weekday == 7){ #start a new week
            $cal .= "</tr><tr class='week'>";
            if ($weekday==7) {
                $weekday = 0;
                $colorkeys=array();
            }
        }
        for ($j=0;$j<7-$weekday;$j++) {
            if (is_array($coloring[$month][$day+$j])) {
                $keys=array_keys($coloring[$month][$day+$j]);
                $colorkeys=array_merge($colorkeys,$keys);
            }
        }
        $colorkeys=array_unique($colorkeys);
        natsort($colorkeys);
        $colkeys=array_flip($colorkeys);
        #print_r($colkeys);
        foreach ($colkeys as $k=>$v)
            $colkeys[$k]=' colorbar blank">';

        $daytext=($day > $maxcheckday) ? $day-$maxcheckday:$day;
        if ($column)
            $daytext.='</h6><br /><h6 class="week">'.substr($day_headings[$weekday],0,3);
        if ($day==$today and $month == date('m')) {
            $exists='today';
            $nonexists='today';
            $classes=$nonexists;
        } else {
            $exists='wiki';
            $nonexists='day';
            $classes=$nonexists;
        }
        if ($coloring[$month][$day]) {
            #print_r($coloring[$month][$day]);
            $colorings=array_merge($colkeys,$coloring[$month][$day]);
            #print_r($colorings);
            #$colorings=array_reverse($coloring[$month][$day]);
            #$colorings=&$coloring[$month][$day];
            #$classes=implode(' ',$coloring[$month][$day]).' '.$classes;
            $colorbar='<ul class="colorbar"><li class="'.implode('<li class="',$colorings).'</ul>';
        } else {
            $colorbar='';
        }
        $todo='';
        $info=$dayinfo[$month][$day];
        if (is_array($info) and isset($dayinfo['week'][$weekday]))
            $info = array_merge($info,$dayinfo['week'][$weekday]);
        if (is_array($info)) {
            if (sizeof($infocolor[$month][$day])>1) {
                for ($i=0,$sz=sizeof($info);$i<$sz;$i++) {
                    if (preg_match('/(\{\*\}|\(\!\)|\/\!\\\\|<!>|:\()/',
                        $info[$i],$m)) {
                        if ($color_table[$m[1]]) {
                            $info[$i]=
                                "<span class='".$color_table[$m[1]]." text'>".
                                $info[$i].'</span>';
                        }
                    }
                }
            } else {
                $classes=$infocolor[$month][$day][0].' '.$classes;
            }
                
            $todo='<ul><li>'.implode("</li>\n<li>",$info);
            $todo.="</li>\n</ul>\n";
            $todo=preg_replace($formatter->baserule,$formatter->baserepl,$todo);
            $todo=preg_replace('/&lt;([^>]+)>/','<\\1>',$todo);
        }
        $dayclasses=$column ? "dayhead":$classes;

        $cal.= '<td'.($dayclasses ? " class=\"$dayclasses\">" : '>').'<h6>'.
            ($link ? $formatter->link_tag($link,$action,$daytext):
                 $daytext)."</h6>";
        if ($column) {
            $classes.=' fullday';
            $cal.="</td><td ".($classes ? " class=\"$classes\">":'>');
        }
        $cal.="$colorbar$todo</td>";

        $day++;
        $weekday++;
    }
    $formatter->sister_on=$save;
    #$cal=preg_replace("/(<[^>]+>|".$formatter->wordrule.")/e",
    #    "\$formatter->link_repl('\\1')",$cal);

    while ($weekday < 7){
        $cal .= "<td class='day'>&nbsp;</td>";
        if ($column) $cal.="</tr>\n<tr>\n";
        $weekday++;
    }
    $cal.='</tr>';

    return "<div class='MC'><table cellspacing='0'>".$cal."</table></div>\n";
}

// vim:et:sts=4:
?>
