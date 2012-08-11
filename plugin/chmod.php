<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a chmod action plugin for the MoniWiki
// vim:et:ts=2:
//
// $Id: chmod.php,v 1.5 2010/08/23 15:14:10 wkpark Exp $
function do_chmod($formatter,$options) {
  global $DBInfo;
  
  if (isset($options['read']) or isset($options['write'])) {
    if ($DBInfo->hasPage($options['page'])) {
      $perms= $DBInfo->getPerms($options['page']);
      $perms&= 0077; # clear user perms
      if (!empty($options['read'])) $perms|=0400;
      if (!empty($options['write'])) $perms|=0200;
      $DBInfo->setPerms($options['page'],$perms);
      $title = sprintf(_("Permission of \"%s\" changed !"), $options['page']);
      $formatter->send_header("",$options);
      $formatter->send_title($title,"",$options);
      $formatter->send_footer("",$options);
      return;
    } else {
      $title = sprintf(_("Fail to chmod \"%s\" !"), $options['page']);
      $formatter->send_header("",$options);
      $formatter->send_title($title,"",$options);
      $formatter->send_footer("",$options);
      return;
    }
  }
  $perms= $DBInfo->getPerms($options['page']);

  $form=form_permission($perms);

  $title = sprintf(_("Change permission of \"%s\""), $options['page']);
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
#<tr><td align='right'><input type='checkbox' name='show' checked='checked' />show only </td><td><input type='password' name='passwd'>
  print "<form method='post'>
<table border='0'>
$form
</table>\n";
  if ($DBInfo->security->is_protected("chmod",$options))
    print "
Password:<input type='password' name='passwd' />
Only WikiMaster can change the permission of this page\n";
  print "
<input type='submit' name='button_chmod' value='change' /><br />
<input type=hidden name='action' value='chmod' />
</form>";
#  $formatter->send_page();
  $formatter->send_footer('',$options);
}

?>
