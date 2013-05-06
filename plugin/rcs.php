<?php
// Copyright 2003-2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a rcs action plugin for the MoniWiki
//
// $Id: rcs.php,v 1.6 2006/07/07 12:59:57 wkpark Exp $

function do_post_rcs($formatter,$options) {
  global $DBInfo;

  $supported=array('-kkv','-kk', '-l -M');
  if ($DBInfo->version_class!='RCS') {
    $title = sprintf(_("%s does not support rcs options."), $DBInfo->version_class);
    $formatter->send_header("",$options);
    $formatter->send_title($title,"",$options);
    $formatter->send_footer("",$options);

    return;
  }
  
  if (isset($options['param'])) {
    if ($DBInfo->hasPage($formatter->page->name) and in_array($options['param'],$supported)) {
      $key=$DBInfo->getPageKey($formatter->page->name);
      $fp=popen( "rcs $options[param] $key".$formatter->NULL,'r');
      pclose($fp);
      $title = sprintf(_("Change options for \"%s\""), $formatter->page->name);
      $formatter->send_header("",$options);
      $formatter->send_title($title,"",$options);
      $formatter->send_footer("",$options);
      return;
    } else {
      $title = sprintf(_("Fail to rcs \"%s\" !"), $formatter->page->name);
    }
  }
  if (!$title)
    $title = sprintf(_("Change options for \"%s\" ?"), $options['page']);
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  print "<form method='post'>
<table border='0'>\n";
  print "<tr>";
  print "<td valign='top'><b>rcs</b></td><td><select name='param' />\n";
  foreach ($supported as $opt) {
    print "<option value='$opt'>$opt</option>\n";
  }
  if ($DBInfo->security->is_protected("rcs",$options))
    print " <input type='password' name='passwd' /> ".
    _("Only WikiMaster can execute rcs");
  print "</tr><tr><td colspan='2'><input type='submit' name='button_rcs' value='apply' /></td>";
  print "</td></tr>\n";

  print "
</table>
    <input type=hidden name='action' value='rcs' />
    </form>";
#  $formatter->send_page();
  $formatter->send_footer("",$options);
}
?>
