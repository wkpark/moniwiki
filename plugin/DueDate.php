<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// DueDate plugin for the MoniWiki
//
// Usage: DueDate([[YYYY]MM]DD)
//
// $Id$
// vim:et:ts=2:

function macro_DueDate($formatter,$value) {
  $time= localtime(time(),true);

  $day= $time[tm_mday];
  $month= $time[tm_mon]+1;
  $year= $time[tm_year]+1900;

  $date_val=$value;

  if (strlen($value) == 2) {
    if ((int) $value < $time[tm_mday])
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
  $time_diff= ($time_val - time())/86400;
  
  $msg=strftime("%x",$time_val);

  if  ($time_diff > 0)
     $msg.=sprintf("까지  %d일 남았습니다.", $time_diff);
  else if ($time_diff == 0)
     $msg="오늘입니다.";
  else
     $msg.=sprintf("로부터 %d일 지났습니다.",$time_diff);

  return $msg;
}

?>
