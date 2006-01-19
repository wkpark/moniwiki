<?php
// Copyright 2003-2004 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Info plugin for the MoniWiki
//
// $Id$

function _parse_rlog($formatter,$log,$options=array()) {
  global $DBInfo;

  $user=new User(); # get cookie
  if ($user->id != 'Anonymous') { # XXX
    $udb=new UserDB($DBInfo);
    $udb->checkUser($user);
    $tz_offset=$user->info['tz_offset'];
  } else {
    $tz_offset=$options['tz_offset'];
  }
  $state=0;
  $flag=0;

  $time_current=time();

  $simple=$options['simple'] ? 1:0;

  $url=$formatter->link_url($formatter->page->urlname);

  $out="<h2>"._("Revision History")."</h2>\n";
  $out.="<table class='info' border='0' cellpadding='3' cellspacing='2'>\n";
  $out.="<form id='infoform' method='post' action='$url'>";
  $out.="<th class='info'>ver.</th><th class='info'>Date and Changes</th>".
       "<th class='info'>Editor</th>".
       "<th class='info'><input type='submit' value='diff'></th>";
  if (!$simple) {
    $out.="<th class='info'>actions</th>".
       "<th class='info'>admin.</th>";
  }
  $out.= "</tr>\n";

  $users=array();
 
  #foreach ($lines as $line) {
  $count=0;
  $showcount=($options['count']>5) ? $options['count']: 10;
  for($line = strtok($log, "\n"); $line !== false; $line = strtok("\n")) {
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
         preg_match("/^revision ([0-9]\.([0-9\.]+))\s*/",$line,$match);
         $rev=$match[1];
         if (preg_match("/\./",$match[2])) {
            $state=0;
            break;
         }
         $state=2;
         break;
      case 2:
         $inf=preg_replace("/date:\s(.*);\s+author:.*;\s+state:.*;/","\\1",$line);
         list($inf,$change)=explode('lines:',$inf,2);

         if ($options['ago']) {
           $ed_time=strtotime($inf.' GMT');
           $time_diff=(int)($time_current - $ed_time)/60;
           if ($time_diff > 1440*14) {
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
           if ($tz_offset !='')
             $inf=gmdate("Y-m-d H:i:s",strtotime($inf.' GMT')+$tz_offset);
           else
             $inf=date("Y-m-d H:i:s",strtotime($inf)); // localtime
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
           else if ($DBInfo->hasPage($user)) {
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
         $out.="<tr>\n";
         $out.="<th valign='top' rowspan=$rowspan>r$rev</th><td nowrap='nowrap'>$inf $change</td><td>$ip&nbsp;</td>";
         $achecked="";
         $bchecked="";
         if ($flag==1)
            $achecked="checked ";
         else if (!$flag)
            $bchecked="checked ";
         $out.="<th nowrap='nowrap'><input type='radio' name='rev' value='$rev' $achecked/>";
         $out.="<input type='radio' name='rev2' value='$rev' $bchecked/></th>";

         if (!$simple) {
         $out.="<td nowrap='nowrap'>".$formatter->link_to("?action=recall&rev=$rev","view").
               " ".$formatter->link_to("?action=raw&rev=$rev","raw");
         if ($flag) {
            $out.= " ".$formatter->link_to("?action=diff&rev=$rev","diff");
            $out.="</td><th>";
            $out.="<input type='checkbox' name='range[$flag]' value='$rev' />";
         } else {
            $out.="</td><th>";
            $out.="<input type='image' src='$DBInfo->imgs_dir/smile/checkmark.png' onClick=\"ToggleAll('infoform');return false;\"/>";
         }
         }
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
  if (!$simple) {
  $out.="<tr><td colspan='6' align='right'><input type='checkbox' name='show' checked='checked' />show only ";
  if ($DBInfo->security->is_protected("rcspurge",$options)) {
    $out.="<input type='password' name='passwd'>";
  }
  $out.="<input type='submit' name='rcspurge' value='purge'></td></tr>";
  }
  $out.="<input type='hidden' name='action' value='diff'/></form></table>\n";
  if (!$simple) {
  $out.="<script type='text/javascript' src='$DBInfo->url_prefix/local/checkbox.js'></script>\n";
  }
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
  $formatter->send_title(sprintf(_("Info. for %s"),$options['page']),"",$options);

  print macro_info($formatter,'',$options);
  $formatter->send_footer($args,$options);
}

// vim:et:sts=2:
?>
