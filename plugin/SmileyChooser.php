<?php
// Copyright 2003 by iolo
// All rights reserved. Distributable under GPL see COPYING
// a SmileyChooser macro plugin for the MoniWiki
// vim:et:ts=2:
//
// Usage: [[SmileyChooser]]
//
// $Id$

function macro_SmileyChooser($formatter,$value) {
  global $DBInfo;

  if (!$DBInfo->use_smileys) return '';

  $chooser=<<<EOS
<script language="javascript" type="text/javascript">
<!--
function append_smiley(smiley)
{
  var textarea = document.editform.savetext;
  textarea.value += smiley + " "; 
  textarea.focus();
}
//-->
</script>
EOS;
  $chooser.= "<div id=\"smileyChooser\">\n";
  $last_img = '';
  $idx=0;
  while (list($key,$value) = each($DBInfo->smileys)) {
    if ($last_img != $value[3]) {
      $skey=str_replace("\\","\\\\",$key);
      $chooser.= "<a href='#' onclick='append_smiley(\"$skey\");return false;'>".$formatter->smiley_repl($key)."</a>";
      $last_img = $value[3];
      $idx++;
    }
  }
  $chooser.= "</div>\n";
  return $chooser;
}

?>
