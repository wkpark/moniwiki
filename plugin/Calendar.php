<?php
# PHP Calendar - http://www.keithdevens.com/software/php_calendar/
#  see example at http://www.keithdevens.com/weblog/
#  (This code is actually live on my site *right now*)
#-------------------------------------------------------------------------------
#----Return a calendar as a string----------------------------------------------
#-----usage: generate_calendar(2001, 11, $day_func, 2);-------------------------
#------(last two arguments are optional)----------------------------------------
#-------------------------------------------------------------------------------
function macro_Calendar($formatter,$value) {
	function day_func($year,$month,$day,$pagename) {
		global $DBInfo;
		static $today;
      if (!$today) $today=date("d");
		if ($day==$today) {
			$exists='today" bgcolor="white';
			$nonexists='today" bgcolor="white';
		} else {
			$exists='wiki';
			$nonexists='day" bgcolor="lightyellow';
		}

		$link="$pagename/".sprintf("%04d-%02d-%02d", $year, $month, $day);
		if ($DBInfo->hasPage($link))
		  return array($link, $exists, $day);
		else
		  return array($link, $nonexists, $day);
	}
#	$year, $month;
   $year= date('Y');
   $month= date('m');
#   $day_func = NULL;
#   $day_func = '
#	if($GLOBALS["days"][$day-1]){
#		return array("/" . sprintf("%04d-%02d-%02d", $year, $month, $day), &$GLOBALS["classes"][$day-1], &$GLOBALS["content"][$day-1]);
#	}else{
#		return false;
#	}';
   $day_heading_length = 3;
	$first_of_month = mktime (0,0,0, $month, 1, $year);
	#remember that mktime will automatically correct if invalid dates are entered
	# for instance, mktime(0,0,0,12,32,1997) will be the date for Jan 1, 1998
	# this provides a built in "rounding" feature to generate_calendar()

	static $day_headings = array('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday');
	$maxdays   = date('t', $first_of_month); #number of days in the month
	$date_info = getdate($first_of_month);   #get info about the first day of the month
	$month     = $date_info['mon'];
	$year      = $date_info['year'];

	$calendar  = "<table class=\"calendar\">\n";

	#use the <caption> tag or just a normal table heading. Take your pick.
	#http://diveintomark.org/archives/2002/07/03.html#day_18_giving_your_calendar_a_real_caption
#	$calendar .= "<tr><th colspan=\"7\" class=\"month\">$date_info[month], $year</th></tr>\n";
	$calendar .= "<caption class=\"month\">$date_info[month], $year</caption>\n";

	#print the day headings "Mon", "Tue", etc.
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
	while ($day <= $maxdays){
		if($weekday == 7){ #start a new week
			$calendar .= "</tr>\n<tr>";
			$weekday = 0;
		}
		#if a linking function is provided
		if(function_exists('day_func')){
			list($link, $classes, $content) = day_func($year,$month,$day,$formatter->page->name);
			$calendar .= '<td' . ($classes ? " class=\"$classes\">" : '>') .
				($link ? $formatter->link_tag($link,"",$day) : '') .
#"<a href=\"$formatter->prefix/$link\">" : '') . 
#				(isset($content) ? $content : $day) .
#				($link ? '</a>' : '') .
				'</td>';
		}else{
			$calendar .= "<td>$day</td>";
		}
		$day++;
		$weekday++;
	}
	if($weekday != 7){
		$calendar .= '<td colspan="' . (7 - $weekday) . '">&nbsp;</td>';
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
