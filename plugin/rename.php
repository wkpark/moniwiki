<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a rename action plugin for the MoniWiki
//
// $Id$

function do_post_rename($formatter,$options) {
  global $DBInfo;
  
  if (isset($options['name'])) {
    if ($DBInfo->hasPage($options['page']) && !$DBInfo->hasPage($options['name'])) {
      $DBInfo->renamePage($options['page'],$options['name'],$options);
      $title = sprintf(_("\"%s\" is renamed !"), $options['page']);
      $formatter->send_header("",$options);
      $formatter->send_title($title,"",$options);
      $formatter->send_footer("",$options);
      return;
    } else {
      $title = sprintf(_("Fail to rename \"%s\" !"), $options['page']);
      $formatter->send_header("",$options);
      $formatter->send_title($title,"",$options);
      $formatter->send_footer("",$options);
      return;
    }
  }
  $title = sprintf(_("Rename \"%s\" ?"), $options['page']);
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
#<tr><td align='right'><input type='checkbox' name='show' checked='checked' />show only </td><td><input type='password' name='passwd'>
  print "<form method='post'>
<table border='0'>
<tr><td align='right'>Old name: </td><td><b>$options[page]</b></td></tr>
<tr><td align='right'>New name: </td><td><input name='name' /></td></tr>\n";
  if ($DBInfo->security->is_protected("rename",$options))
    print "<tr><td align='right'>"._("Password").": </td><td><input type='password' name='passwd' /> ".
    _("Only WikiMaster can rename this page");
  print "<tr>";
  print "<td>"._("with revision history")."<input type='checkbox' name='history' /></td>";
  print "<td><input type='submit' name='button_rename' value='rename' /></td>";
  print "</td></tr>\n";

  print "
</table>
    <input type=hidden name='action' value='rename' />
    </form>";
#  $formatter->send_page();
  $formatter->send_footer("",$options);
}
?>
