<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// sample plugin for the MoniWiki
//
// Usage: [[Test]]
//
// $Id$
// vim:et:ts=2:

function do_Blog($formatter,$options) {
  global $DBInfo;
  global $HTTP_USER_AGENT;
  $COLS_MSIE = 80;
  $COLS_OTHER = 85;
  $cols = preg_match('/MSIE/', $HTTP_USER_AGENT) ? $COLS_MSIE : $COLS_OTHER;

  $rows=$options[rows] > 5 ? $options[rows]: 8;
  $cols=$options[cols] > 60 ? $options[cols]: $cols;

  $url=$formatter->link_url($formatter->page->urlname);
  $formatter->send_header("",$options);
  if (!$options[button_preview] && $options[savetext]) {
    $options[msg]=sprintf(_("Comment is added to \"%s\""),$options[page]);
    $formatter->send_title(sprintf(_("Add comment to \"%s\""),$options[page]),"",$options);
    $raw_body=$formatter->page->_get_raw_body();
    if ($options[id]=='Anonymous') $id=$_SERVER[REMOTE_ADDR];
    else $id=$options[id];
    $raw_body.="{{{#!chat $id @date@";
    if ($options[title])
      $raw_body.=" $options[title]";
    $raw_body.="\n$options[savetext]\n}}}\n\n";
    $formatter->page->write($raw_body);
    $DBInfo->savePage($formatter->page,"Add Blog entry",$options);

    $formatter->send_page();
  } else {
    $formatter->send_title(sprintf(_("Add comment to \"%s\""),$options[page]));
    print <<<EOS
<form method="post" action="$url">
Title: <input name="title" value='$options[title]' size="70" maxlength="70" style="width:200" /><br />
<textarea class="wiki" id="content" wrap="virtual" name="savetext"
 rows="$rows" cols="$cols" style="width:100%">$options[savetext]</textarea><br />
<input type="hidden" name="action" value="Blog" />
<input type="submit" value="Save" />&nbsp;
<input type="reset" value="Reset" />&nbsp;
<input type="submit" name="button_preview" value="Preview" />
$extra
</form>
EOS;
  }
  if ($options[button_preview] && $options[savetext]) {
    if ($options[title])
      $formatter->send_page("== $options[title] ==\n");
    $formatter->send_page($options[savetext]);
  }
  $formatter->send_footer("",$options);
  return;
}

?>
