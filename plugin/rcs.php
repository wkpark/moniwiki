<?php
// Copyright 2003-2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a rcs action plugin for the MoniWiki
//
// $Id$

function do_post_rcs($formatter,$options) {
  global $DBInfo;

  $supported=array('-kkv','-kk');
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
      system( "rcs $options[param] $key" );
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
  print "<option value='-kk'>-kk</option>\n";
  print "<option value='-kkv'>-kkv</option>\n</select>\n";
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
