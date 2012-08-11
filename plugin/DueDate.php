<?php
// Copyright 2003-2004 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// DueDate plugin for the MoniWiki
//
// Usage: DueDate([[YYYY]MM]DD)
//
// $Id: DueDate.php,v 1.4 2010/08/13 16:26:11 wkpark Exp $

function macro_DueDate($formatter,$value) {
  $time= localtime(time(),true);

  $day= $time['tm_mday'];
  $month= $time['tm_mon']+1;
  $year= $time['tm_year']+1900;
  $now_val= strtotime($year.sprintf("%02d%02d",$month,$day));

  $date_val=$value;

  if (strlen($value) == 2) {
    if ((int) $value < $time['tm_mday'])
      $month+=1;
    if ($month > 12) {
      $year+=1; $month=1;
    }
    $date_val=$year.sprintf("%02d%s",$month,$value);
  } else if (strlen($value) == 4) {
    if ($value < $month.$day)
      $year++;
    $date_val=$year.$value;
  } else if (strlen($value) != 8) {
    return "[[DueDate($value error!)]]";
  }

  $time_val= strtotime($date_val);
  $time_diff= (int) (($time_val - $now_val)/86400);
  
  $date = gmdate("Y-m-d", $time_val + $formatter->tz_offset);

  if  ($time_diff > 0)
     $msg=sprintf(_("%d day(s) left until %s."), $time_diff, $date);
  else if ($time_diff == 0)
     $msg=_("It's today.");
  else
     $msg=sprintf(_("%d day(s) passed from %s."), abs($time_diff), $date);

  return $msg;
// vim:et:sts=4:
}

?>
