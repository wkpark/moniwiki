<?php
// Copyright 2003-2004 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Info plugin for the MoniWiki
//
// $Id$

function _parse_rlog($formatter,$log) {
  global $DBInfo;
  $state=0;
  $flag=0;

  $url=$formatter->link_url($formatter->page->urlname);

  $out="<h2>"._("Revision History")."</h2>\n";
  $out.="<table class='info' border='0' cellpadding='3' cellspacing='2'>\n";
  $out.="<form method='post' action='$url'>";
  $out.="<th class='info'>#</th><th class='info'>Date and Changes</th>".
       "<th class='info'>Editor</th>".
       "<th><input type='submit' value='diff'></th>".
       "<th class='info'>actions</th>".
       "<th class='info'>admin.</th>";
       #"<th><input type='submit' value='admin'></th>";
  $out.= "</tr>\n";

  $users=array();
 
  #foreach ($lines as $line) {
  for($line = strtok($log, "\n"); $line !== false; $line = strtok("\n")) {
    if (!$state) {
      if (!preg_match("/^---/",$line)) { continue;}
      else {$state=1; continue;}
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
         $inf=date("Y-m-d H:i:s",strtotime($inf)); // localtime XXX

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
           } else if ($DBInfo->interwiki['Whois']) {
             $ip="<a href='".$DBInfo->interwiki['Whois']."$ip'>$user</a>";
           }
         } else if ($user and $DBInfo->interwiki['Whois'])
           $ip="<a href='".$DBInfo->interwiki['Whois']."$ip'>$ip</a>";

         $comment=stripslashes($dummy[2]);
         $state=4;
         break;
      case 4:
         if (!$rev) break;
         $rowspan=1;
         if ($comment) $rowspan=2;
         $out.="<tr>\n";
         $out.="<th valign='top' rowspan=$rowspan>r$rev</th><td nowrap='nowrap'>$inf $change</td><td>$ip&nbsp;</td>";
         $achecked="";
         $bchecked="";
         if ($flag==1)
            $achecked="checked ";
         else if (!$flag)
            $bchecked="checked ";
         $out.="<td nowrap='nowrap'><input type='radio' name='rev' value='$rev' $achecked/>";
         $out.="<input type='radio' name='rev2' value='$rev' $bchecked/>";

         $out.="<td nowrap='nowrap'>".$formatter->link_to("?action=recall&rev=$rev","view").
               " ".$formatter->link_to("?action=raw&rev=$rev","raw");
         if ($flag) {
            $out.= " ".$formatter->link_to("?action=diff&rev=$rev","diff");
            $out.="</td><th>";
            $out.="<input type='checkbox' name='range[$flag]' value='$rev' />";
         } else
            $out.="</td><th>";
         $out.="</th></tr>\n";
         if ($comment)
            $out.="<tr><td class='info' colspan='5'>$comment&nbsp;</td></tr>\n";
         $state=1;
         $flag++;
         break;
     }
  }
  $out.="<tr><td colspan='6' align='right'><input type='checkbox' name='show' checked='checked' />show only ";
  if ($DBInfo->security->is_protected("rcspurge",array())) {
    $out.="<input type='password' name='passwd'>";
  }
  $out.="<input type='submit' name='rcspurge' value='purge'></td></tr>";
  $out.="<input type='hidden' name='action' value='diff'/></form></table>\n";
  return $out; 
}


function do_info($formatter,$options) {
  global $DBInfo;
  $formatter->send_header("",$options);
  $formatter->send_title(sprintf(_("Info. for %s"),$options['page']),"",$options);

  if ($DBInfo->version_class) {
    getModule('Version',$DBInfo->version_class);
    $class="Version_".$DBInfo->version_class;
    $version=new $class ($DBInfo);
    $out= $version->rlog($formatter->page->name,'','','-zLT');

    if (!$out) {
      $msg=_("No older revisions available");
      print "<h2>$msg</h2>";
    } else {
      print _parse_rlog($formatter,$out);
    }
  } else {
    $msg=_("Version info does not available in this wiki");
    print "<h2>$msg</h2>";
  }

  $formatter->send_footer($args,$options);
}

// vim:et:sts=2:
?>
