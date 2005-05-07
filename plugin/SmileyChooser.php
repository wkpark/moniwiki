<?php
// Copyright 2003 by iolo
// All rights reserved. Distributable under GPL see COPYING
// a SmileyChooser macro plugin for the MoniWiki
//
// Usage: [[SmileyChooser]]
//
// $Id$

function macro_SmileyChooser($formatter,$value) {
  global $DBInfo;

  if (!$DBInfo->use_smileys) return '';
  $form=$value ? $value:'editform';

  $chooser=<<<EOS
<script language="javascript" type="text/javascript">
<!--
// from wikibits.js
function appendText(myText)
{
  var txtarea = document.$form.savetext;
  if(document.selection && document.all) {
    var theSelection = document.selection.createRange().text;
    txtarea.focus();
    if(theSelection.charAt(theSelection.length - 1) == " "){
      // exclude ending space char, if any
      theSelection = theSelection.substring(0, theSelection.length - 1);
      document.selection.createRange().text = theSelection + myText + " ";
    } else {
      document.selection.createRange().text = theSelection + myText + " ";
    }
  }
  // Mozilla
  else if(txtarea.selectionStart || txtarea.selectionStart == '0') {
    var startPos = txtarea.selectionStart;
    var endPos = txtarea.selectionEnd;
    var scrollTop=txtarea.scrollTop;
    txtarea.value = txtarea.value.substring(0, startPos) + myText + " " +
      txtarea.value.substring(endPos, txtarea.value.length);
    txtarea.focus();

    var cPos=startPos+(myText.length+1);

    txtarea.selectionStart=cPos;
    txtarea.selectionEnd=cPos;
    txtarea.scrollTop=scrollTop;
  } else { // All others
    txtarea.value += myText + " "; 
    txtarea.focus();
  }
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
      $chooser.= "<span onclick='appendText(\"$skey\")'>".$formatter->smiley_repl($key)."</span>&shy;";
      $last_img = $value[3];
      $idx++;
    }
  }
  $chooser.= "</div>\n";
  return $chooser;
}

// vim:et:sts=2:
?>
