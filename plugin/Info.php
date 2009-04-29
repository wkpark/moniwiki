<?php
// Copyright 2003-2004 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Info plugin for the MoniWiki
//
// $Id$

function _parse_rlog($formatter,$log,$options=array()) {
  global $DBInfo;

  $tz_offset=$formatter->tz_offset;
  if (is_array($DBInfo->wikimasters) and in_array($options['id'],$DBInfo->wikimasters)) $admin=1;

  if ($options['info_actions'])
    $actions=$options['info_actions'];
  else if ($DBInfo->info_actions)
    $actions=$DBInfo->info_actions;
  else
    $actions=array('recall'=>'view','raw'=>'raw');

  $state=0;
  $flag=0;

  $time_current=time();

  $simple=$options['simple'] ? 1:0;

  $url=$formatter->link_url($formatter->page->urlname);

  $diff_btn=_("Diff");
  $out = "<div class='wikiInfo'>\n";
  if ($options['title'])
    $out.=$options['title'];
  else
    $out.="<h2>"._("Revision History")."</h2>\n";
  $out.="<form id='infoform' method='post' action='$url'>";
  $out.="<table class='info' cellpadding='3' cellspacing='2'><tr>\n";
  $out.="<th class='info'>"._("ver.")."</th><th class='info'>"._("Date and Changes")."</th>".
       "<th class='info'>"._("Editor")."</th>".
       "<th class='info'><input type='submit' value='$diff_btn'></th>";
  if (!$simple) {
    $out.="<th class='info'>"._("actions")."</th>";
    if (isset($admin)) $out.= "<th class='info'>"._("admin.")."</th>";
  }
  $out.= "</tr>\n";

  $users=array();
  $rr=0;
 
  #foreach ($lines as $line) {
  $count=0;
  $showcount=($options['count']>5) ? $options['count']: 10;
  for(; !empty($line) or !empty($log); list($line,$log) = explode("\n",$log,2)) {
    if (!$state) {
      if (!preg_match("/^---/",$line)) { continue;}
      else {$state=1; continue;}
    }
    if ($state==1 and $ok==1) {
      $lnk=$formatter->link_to("?action=info&all=1",_("Show all revisions"));
      $out.='<tr><td colspan="2"></td><th colspan="4">'.$lnk.'</th></tr>';
      break;
    }
    
    switch($state) {
      case 1:
         $rr++;
         preg_match("/^revision ([0-9a-f\.]+)\s*/",$line,$match);
         $rev=$match[1];
         if (preg_match("/\./",$match[2])) {
            $state=0;
            break;
         }
         $state=2;
         break;
      case 2:
         $inf=preg_replace("/date:\s([0-9\/:\s]+)(;\s+author:.*;\s+state:.*;)?/","\\1",$line);
         list($inf,$change)=explode('lines:',$inf,2);

         if ($options['ago']) {
           if (preg_match('/^[0-9]+$/',$inf)) {
             $rrev='#'.$rr;
             $ed_time=$inf;
             $inf=gmdate("Y-m-d H:i:s",$ed_time+$tz_offset);
           } else {
             $ed_time=strtotime($inf.' GMT');
           }
           $time_diff=(int)($time_current - $ed_time)/60;
           if ($time_diff > 1440*31) {
             $inf=gmdate("Y-m-d H:i:s",strtotime($inf.' GMT')+$tz_offset);
           } else if (($time_diff=$time_diff/60) > 24) {
             $day=(int)($time_diff/24);
             if ($day==1) $inf=_("Yesterday");
             else $inf=sprintf(_("%s days ago"),(int)($time_diff/24));
           } else if ($time_diff > 1) {
             $inf=sprintf(_("%s hours ago"),(int)$time_diff);
           } else {
             $inf=sprintf(_("%s min ago"),$time_diff%60);
           }

         } else {
           if (preg_match('/^[0-9]+$/',$inf)) {
             $rrev='#'.$rr;
             $ed_time=$inf;
             $inf=gmdate("Y-m-d H:i:s",$inf+$tz_offset);
           } else {
             if ($tz_offset !='')
               $inf=gmdate("Y-m-d H:i:s",strtotime($inf.' GMT')+$tz_offset);
             else
               $inf=date("Y-m-d H:i:s",strtotime($inf)); // localtime
           }
         }
         $inf=$formatter->link_to("?action=recall&rev=$rev",$inf);

         $change=preg_replace("/\+(\d+)\s\-(\d+)/",
           "<span class='diff-added'>+\\1</span><span class='diff-removed'>-\\2</span>",$change);
         $state=3;
         break;
      case 3:
         $dummy=explode(';;',$line,3);
         $ip=$dummy[0];
         $user=$dummy[1];
         if ($user and $user!='Anonymous') {
           if (in_array($user,$users)) $ip=$users[$user];
           else if (strpos($user,' ') !== false) {
             $ip=$formatter->link_repl($user);
             $users[$user]=$ip;
           } else if (empty($DBInfo->use_hostname) or $DBInfo->hasPage($user)) {
             $ip=$formatter->link_tag($user);
             $users[$user]=$ip;
           } else if (!$DBInfo->mask_hostname and $DBInfo->interwiki['Whois']) {
             $ip="<a href='".$DBInfo->interwiki['Whois']."$ip'>$user</a>";
           }
         } else if ($DBInfo->mask_hostname) {
           $ip=_mask_hostname($ip);
         } else if ($user and $DBInfo->interwiki['Whois'])
           $ip="<a href='".$DBInfo->interwiki['Whois']."$ip'>$ip</a>";

         $comment=stripslashes($dummy[2]);
         $state=4;
         break;
      case 4:
         if (!$rev) break;
         $rowspan=1;
         if (!$simple and $comment) $rowspan=2;

         $rrev= $rrev ? $rrev:$rev;
         $out.="<tr>\n";
         $out.="<th class='rev' valign='top' rowspan=$rowspan>$rrev</th><td nowrap='nowrap'>$inf $change</td><td>$ip&nbsp;</td>";
         $rrev='';
         $achecked="";
         $bchecked="";
         if ($flag==1)
            $achecked="checked ";
         else if (!$flag)
            $bchecked="checked ";
         $onclick="onclick='ToggleRev(this)'";
         $out.="<th nowrap='nowrap'><input type='radio' name='rev' value='$rev' $achecked $onclick />";
         $out.="<input type='radio' name='rev2' value='$rev' $bchecked $onclick /></th>";

         if (!$simple):
         $out.="<td nowrap='nowrap'>";
         foreach ($actions as $k=>$v) {
           $k=is_numeric($k) ? $v:$k;
           $out.=$formatter->link_to("?action=$k&amp;rev=$rev",_($v)).' ';
         }
         if ($flag) {
            $out.= " ".$formatter->link_to("?action=diff&amp;rev=$rev",_("diff"));
            $out.="</td>";
            if (isset($admin))
              $out.=
                "<th><input type='checkbox' name='range[$flag]' value='$rev' /></th>";
         } else {
            $out.="</td>";
            if (isset($admin)) {
              $out.="<th><input type='image' src='$DBInfo->imgs_dir/smile/checkmark.png' onClick=\"ToggleAll('infoform');return false;\"/></th>";
            }
         }
         endif;
         $out.="</tr>\n";
         if (!$simple and $comment)
            $out.="<tr><td class='info' colspan='5'>$comment&nbsp;</td></tr>\n";
         $state=1;
         $flag++;
         $count++;
         if ($options['all']!=1 and $count >=$showcount) $ok=1;
         break;
     }
  }
  if (!$simple and $admin):
  $out.="<tr><td colspan='6' align='right'><input type='checkbox' name='show' checked='checked' />"._("show only").' ';
  if ($DBInfo->security->is_protected("rcspurge",$options)) {
    $out.="<input type='password' name='passwd'>";
  }
  $out.="<input type='submit' name='rcspurge' value='"._("purge")."'></td></tr>";
  endif;
  $out.="<input type='hidden' name='action' value='diff'/></form></table>\n";
  $out.="<script type='text/javascript' src='$DBInfo->url_prefix/local/checkbox.js'></script></div>\n";
  return $out; 
}

function macro_info($formatter,$value,$options=array()) {
  global $DBInfo;

  $value=$value ? $value:$DBInfo->info_options;
  $args=explode(',',$value);
  if (is_array($args)) {
    foreach ($args as $arg) {
      $arg=trim($arg);
      if ($arg=='simple') $options['simple']=1;
      else if ($arg=='ago') $options['ago']=1;
    }
  }

  if ($DBInfo->version_class) {
    getModule('Version',$DBInfo->version_class);
    $class="Version_".$DBInfo->version_class;
    $version=new $class ($DBInfo);
    $out= $version->rlog($formatter->page->name,'','','-z');

    if (!$out) {
      $msg=_("No older revisions available");
      $info= "<h2>$msg</h2>";
    } else {
      $info= _parse_rlog($formatter,$out,$options);
    }
  } else {
    $msg=_("Version info is not available in this wiki");
    $info= "<h2>$msg</h2>";
  }
  return $info;
}


function do_info($formatter,$options) {
  global $DBInfo;
  $formatter->send_header("",$options);
  $formatter->send_title('','',$options);

  print macro_info($formatter,'',$options);
  $formatter->send_footer($args,$options);
}

// vim:et:sts=2:
?>
