<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a rename action plugin for the MoniWiki
//
// $Id: rename.php,v 1.10 2005/08/30 16:25:30 wkpark Exp $

function do_post_rename($formatter,$options) {
  global $DBInfo;

  // check full permission to edit
  $full_permission = true;
  if (!empty($DBInfo->no_full_edit_permission) or
      ($options['id'] == 'Anonymous' && !empty($DBInfo->anonymous_no_full_edit_permission)))
    $full_permission = false;

  // members always have full permission to edit
  if (in_array($options['id'], $DBInfo->members))
    $full_permission = true;

  if (!$full_permission) {
    $formatter->send_header('', $options);
    $title = _("You do not have permission to rename this page on this wiki.");
    $formatter->send_title($title, '',$options);
    $formatter->send_footer('', $options);
    return;
  }

  $options['name'] = trim($options['name']);
  $new = $options['name'];
  if (!empty($DBInfo->use_namespace) and $new[0] == '~' and ($p = strpos($new, '/')) !== false) {
    // Namespace renaming ~foo/bar -> foo~bar
    $dummy=substr($new,1,$p-1);$dummy2=substr($new,$p+1);
    $options['name']=$dummy.'~'.$dummy2;
  } 
  if (isset($options['name']) and trim($options['name'])) {
    if ($DBInfo->hasPage($options['page']) && !$DBInfo->hasPage($options['name'])) {
      $formatter->send_header("",$options);

      $ret = 0;
      if (!$options['show_only'])
        $ret = $DBInfo->renamePage($options['page'],$options['name'],$options);

      if ($ret == 0) {
        $title = sprintf(_("\"%s\" is renamed !"), _html_escape($options['page']));
        $msgid = _("'%s' is renamed as '%s' successfully.");
      } else {
        $title = sprintf(_("Failed to rename \"%s\" !"), _html_escape($options['page']));
        $msgid = _("Failed to rename '%s' as '%s'.");
      }

      $formatter->send_title($title,"",$options);
      $new_encodedname=_rawurlencode($options['name']);
      print sprintf($msgid,
        _html_escape($options['page']),
        $formatter->link_tag($new_encodedname,
          "?action=highlight&amp;value=".$new_encodedname, _html_escape($options['name'])));

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

  $obtn=_("Old name:");
  $nbtn=_("New name:");
  $pgname = _html_escape($options['page']);
  print "<form method='post'>
<table border='0'>
<tr><td align='right'>$obtn </td><td><b>$pgname</b></td></tr>
<tr><td align='right'>$nbtn </td><td><input name='name' /></td></tr>\n";
  $rename_button=_("Rename");
  if ($DBInfo->security->is_protected("rename",$options))
    print "<tr><td align='right'>"._("Password").": </td><td><input type='password' name='passwd' /> ".
    _("Only WikiMaster can rename this page")."</td></tr>\n";
  if (empty($DBInfo->rename_with_history))
    print "<tr><td colspan='2'><input type='checkbox' name='history' />"._("with revision history")."</td></tr>\n";
  print "<tr><td align='right'>"._("Summary").": </td><td><input name='comment' value='' size='80' /></div></td></tr>\n";
  print "<tr><td></td><td><span class='button'><input type='submit' class='button' name='button_rename' value='$rename_button' /></span>".
    " <input type='checkbox' name='show_only' checked='checked' />"._("show only");
  print "</td></tr>\n";
  print "
</table>
    <input type=hidden name='action' value='rename' />
    </form>";
  $formatter->send_footer("",$options);
}
