<?php
# PHP Calendar - http://www.keithdevens.com/software/php_calendar/
#  see example at http://www.keithdevens.com/weblog/
#  (This code is actually live on my site *right now*)
#-------------------------------------------------------------------------------
#----Return a calendar as a string----------------------------------------------
#-----usage: generate_calendar(2001, 11, $day_func, 2);-------------------------
#------(last two arguments are optional)----------------------------------------
#-------------------------------------------------------------------------------
# $Id: Calendar.php,v 1.17 2010/04/19 11:26:46 wkpark Exp $

function calendar_get_dates($formatter,$date='',$page='') {
  global $DBInfo;

  $cache = new Cache_Text('blogchanges', array('hash'=>''));

  if (!$page) $page='.*';
  else $page=$DBInfo->pageToKeyname('.'.$page);

  if (!$date) $date=date('Ym');
  $rule="/^$date(\d{2})".$page."$/";

  $archives=array();
  $files = array();
  $cache->_caches($files);
  foreach ($files as $file) {
    if (preg_match($rule,$file,$match)) {
      $archives[intval($match[1])]=1;
    }
  }

#  return array_unique($archives);
  return $archives;
}

function macro_Calendar($formatter,$value="",$option="") {
	global $DBInfo;

	$date=!empty($_GET['date']) ? $_GET['date'] : '';

	$prev_tag='&laquo;';
	$next_tag='&raquo;';

	static $day_headings= array('Sunday','Monday','Tuesday','Wednesday',
		'Thursday','Friday','Saturday');
	$day_heading_length = 3;

	preg_match("/^(('|\")([^\\2]+)\\2)?,?((\d{4})-?(\d{2}))?,?\s*([a-z, ]+)?$/i",$value,$match);

	#print_r($match);
	/* GET argument has priority */
        $month = '';
        $year = '';
	if ($date) {
		preg_match("/^((\d{4})-?(\d{1,2}))$/i",$date,$match2);
		$year= $match2[2];
		$month= $match2[3];
	} else if (!empty($match[4])) {
		$year= $match[5];
		$month= $match[6];
	}
	/* Validate date. Use system date, if date is not validated */
	if ($month <1 || $month > 12) {
		$year= date('Y');
		$month= date('m');
	}
	$date=$year.$month;
	$month=intval($month);
	$year=intval($year);

	if (!empty($match[3]))
		$pagename=$match[3];
	else
		$pagename=$formatter->page->name;
	$urlpagename=_urlencode($pagename);

	$link_prefix=sprintf("%04d-%02d",$year,$month);

	$archives=array();
        $attr = '';
        $link = '';
	if (!empty($match[7])) {
		$args=explode(",",$match[7]);

		if (in_array ("nolink", $args)) $nolink=1;
		if (in_array ("blog", $args)) $mode='blog';
		if (in_array ("noweek", $args)) $day_heading_length=0;
		if (in_array ("center", $args)) $attr=' align="center"';
		if (in_array ("shortweek", $args)) $day_heading_length=1;
		if (in_array ("yearlink", $args)) $yearlink=1;
		if (in_array ("archive", $args)) {
			if (!empty($mode)) // blog mode
				$archives=calendar_get_dates($formatter,$date,$pagename.'/'.$link_prefix);
			else {
				$archives=calendar_get_dates($formatter,$date);
				$mode='archive';
			}
		}
	}

	$prev_month=date('Ym',mktime(0,0,0,$month - 1,1,$year));
	$next_month=date('Ym',mktime(0,0,0,$month + 1,1,$year));
	if (!empty($yearlink)) {
		$prev_year=date('Ym',mktime(0,0,0,$month,1,$year - 1));
		$next_year=date('Ym',mktime(0,0,0,$month,1,$year + 1));

		$year_prev_tag='&laquo;';
		$year_next_tag='&raquo;';
		$prev_tag='&lsaquo;';
		$next_tag='&rsaquo;';
	}

	$first_of_month = mktime (0,0,0, $month, 1, $year);
	#remember that mktime will automatically correct if invalid dates are entered
	# for instance, mktime(0,0,0,12,32,1997) will be the date for Jan 1, 1998
	# this provides a built in "rounding" feature to generate_calendar()

	# number of days in the month
	$maxdays= date('t', $first_of_month);
	# get info about the first day of the month
	$date_info= getdate($first_of_month);

	$month= $date_info['mon'];
	$year= $date_info['year'];
	$today= date("d");

	$calendar= "<table class=\"Calendar\"$attr>\n";
	#use the <caption> tag or just a normal table heading. Take your pick.
	#http://diveintomark.org/archives/2002/07/03.html#day_18_giving_your_calendar_a_real_caption
#	$calendar .= "<tr><th colspan=\"7\" class=\"month\">$date_info[month], $year</th></tr>\n";
##	$calendar.= "<caption class=\"month\">$date_info[month], $year</caption>\n";
	$calendar.= "<caption class=\"month\">";

        /* Adding previous month and year */
	if (!empty($yearlink))
	$calendar.= $formatter->link_tag($link,"?date=$prev_year",$year_prev_tag).'&nbsp;&nbsp;';
	$calendar.= $formatter->link_tag($link,"?date=$prev_month",$prev_tag).'&nbsp;&nbsp;';

	#$calendar.=substr($date_info[month],0,3).' '.$year;
	$calendar.=$date_info['month'].' '.$year;

	/* Adding next month and year */
	$calendar.= '&nbsp;&nbsp;'.$formatter->link_tag($link,"?date=$next_month",$next_tag);
	if (!empty($yearlink))
	$calendar.= '&nbsp;&nbsp;'.$formatter->link_tag($link,"?date=$next_year",$year_next_tag);
	$calendar.= "</caption>\n";

	# print the day headings "Mon", "Tue", etc.
	# if day_heading_length is 4, the full name of the day will be printed
	# otherwise, just the first n characters
	if($day_heading_length > 0 and $day_heading_length <= 4){
		$calendar .= '<tr>';
		foreach($day_headings as $day_heading){
			$calendar .= "<th abbr=\"$day_heading\" class=\"dayofweek\">" . 
				($day_heading_length != 4 ? substr($day_heading, 0, $day_heading_length) : $day_heading) .
			'</th>';
		}
		$calendar .= "</tr>\n";
	}
	$calendar .= '<tr>';

	$weekday = $date_info['wday']; #weekday (zero based) of the first day of the month
	$day = 1; #starting day of the month
	#take care of the first "empty" days of the month
	if($weekday > 0){$calendar .= "<td colspan=\"$weekday\">&nbsp;</td>";}

	#print the days of the month
        $action = '';
	if (!empty($mode) and $mode=='blog') {
		$link=$urlpagename."/$link_prefix";
		if (!$DBInfo->hasPage($link))
			$action="?action=blog";
	} else if (!empty($mode)) {
		$link=$urlpagename;
	}

	while ($day <= $maxdays){
		if($weekday == 7){ #start a new week
			$calendar .= "</tr>\n<tr>";
			$weekday = 0;
		}
		$daytext=$day;

		if ($day==$today and $month == date('m')) {
			$exists='today" bgcolor="white';
			$nonexists='today" bgcolor="white';
			$classes=$nonexists;
		} else {
			$exists='wiki';
			$nonexists='day" bgcolor="lightyellow';
			$classes=$nonexists;
		}

		if (empty($mode) and !isset($nolink)) {
			$link=$urlpagename."/".$link_prefix."-".sprintf("%02d",$day);
			if ($DBInfo->hasPage($link))
				$classes=$exists;
		} else if (!empty($mode)) {
			if (!empty($archives[$day])) {
				 $daytext='<span class="blogged"><b>'.$day.'</b></span>';
			}
			if ($mode == 'archive') {
				if (!empty($archives[$day])) {
                                        if ($day < 10)
                                          $anchor = '#'.$date.'0'.$day;
                                        else
                                          $anchor = '#'.$date.$day;
					$action='?action=blogchanges&amp;date='.$date.$anchor;
					$classes='day';
					$link=$urlpagename;
				} else {
					if ($day==$today)
						$link=$urlpagename;
					else
						$link='';
					$action='?action=blog';
				}
			} else if ($action[0] != '?')
				$action=sprintf("#%02d",$day);
		}

		$calendar.= '<td'.($classes ? " class=\"$classes\">" : '>').
			($link ? $formatter->link_tag($link,$action,$daytext) : $daytext).'</td>';

		$day++;
		$weekday++;
	}
	if($weekday != 7){
		$calendar .= '<td colspan="'. (7 - $weekday).'">&nbsp;</td>';
	}
	return $calendar . "</tr>\n</table>\n";
}

/*------------------------------------------------------------------------------
  A string to be 'eval'ed by generate_calendar
   potentially returns three values: $link, $classes, and $content
   if $link is returned, generate_calendar creates a hyperlink for the text
     of that day whose destination is the URL provided in $link
   if $classes is returned, generate_calendar associates the provided
     CSS classes with the current day.
   if $content is returned, generate calendar will display that content for
     the day rather than just the date
--------------------------------------------------------------------------------
   generate_calendar() can stand on its own.
   This function is only necessary if you want to modify generate_calendar's
   printing of each day with metadata
   I link days that have weblog entries to that particular day's worth
     of entries in my weblog
   See example at http://www.keithdevens.com/weblog/
--------------------------------------------------------------------------------
   Below is an example "day_func". Notice how it returns all three possible
     types of metadata, and gets the metadata from
     the global variables $days, $classes, and $content
   The function below that is the one that is actually in use in my weblog.
--------------------------------------------------------------------------------
$php_calendar_day_func = '
	if($GLOBALS["days"][$day-1]){ #adjust for the space in the array (actual day - 1 = space in the array)
		return array("/weblog/?" . sprintf("%04d-%02d-%02d", $year, $month, $day), &$GLOBALS["classes"][$day-1], &$GLOBALS["content"][$day-1]);
	}else{
		return false;
	}';
*/
#Below is the "eval function" that is currently in use on my site for my weblog
#global $php_calendar_day_func; #just here because of a weirdity in my current, in progress, CMS
#$php_calendar_day_func = '
#	if($GLOBALS["php_calendar_days"][$day-1]){ #adjust for the space in the array (actual day - 1 = space in the array)
#		return array("/weblog/?" . sprintf("%04d-%02d-%02d", $year, $month, $day), "linked-day");
#	}else{
#		return false;
#	}';
?>
