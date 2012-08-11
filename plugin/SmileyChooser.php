<?php
// Copyright 2003 by iolo
// All rights reserved. Distributable under GPL see COPYING
// a SmileyChooser macro plugin for the MoniWiki
//
// Usage: [[SmileyChooser]]
//
// $Id: SmileyChooser.php,v 1.16 2009/09/26 05:35:48 wkpark Exp $

function do_smileychooser($formatter,$params=array()) {
  $list=macro_SmileyChooser($formatter,$params['page'],$params);

  $formatter->send_header("",$params);
  $formatter->send_title("","",$params);

  print $list;
  $args['editable']=0;
  if (!in_array('UploadFile',$formatter->actions))
    $formatter->actions[]='UploadFile';

  $formatter->send_footer($args,$params);
  return;
}

function macro_SmileyChooser($formatter,$value) {
  global $DBInfo;

  if (!$DBInfo->use_smileys) return '';
  $form=$value ? $value:'editform';

  $chooser=<<<EOS
<script language="javascript" type="text/javascript">
/*<![CDATA[*/
// from wikibits.js
function mySmiley(myText)
{
  var is_ie = document.selection && document.all;
  var ef = document.getElementById('$form');
  if (ef)
    var txtarea = ef.savetext;
  else {
    // some alternate form? take the first one we can find
    var areas = document.getElementsByTagName('textarea');
    var txtarea = areas[0];
  }

  // check WikiWyg
  var my=document.getElementById('editor_area');
  
  while (my.style && my.style.display == 'none') { // wikiwyg hack
    txtarea = document.getElementById('wikiwyg_wikitext_textarea');

    // get iframe and check visibility.
    var myframe = document.getElementsByTagName('iframe')[0];
    // hack. check wrapper also
    if (myframe.style.display == 'none' || myframe.parentNode.style.display == 'none') break;

    var postdata = 'action=markup/ajax&value=' + encodeURIComponent(myText);
    var myhtml='';
    myhtml= HTTPPost(self.location, postdata);

    // check the old wiki-engine or the new monimarkup
    var m = myhtml.match(/<div>(.*)\\n<\/div>/i) || myhtml.match(/<p class="[^"]+">(.*)<\/p>/i); // strip div tag
    if (m) {
      var html = m[1] + ' ';
      if (is_ie) {
        var range = myframe.contentWindow.document.selection.createRange();
        if (range.boundingTop == 2 && range.boundingLeft == 2)
          return;
        range.pasteHTML(html);
        range.collapse(false);
        range.select();
      } else {
        myframe.contentWindow.document.execCommand('inserthtml', false, html);
      }
    }

    return;
  }

  txtarea.focus();
  if(is_ie) {
    var theSelection = document.selection.createRange().text;
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

    var cPos=startPos+(myText.length+1);

    txtarea.selectionStart=cPos;
    txtarea.selectionEnd=cPos;
    txtarea.scrollTop=scrollTop;
  } else { // All others
    txtarea.value += myText + " "; 
    txtarea.focus();
  }
}
/*]]>*/
</script>
EOS;
  $chooser.= "<div id=\"smileyChooser\">\n";
  $last_img = '';
  $idx=0;
  while (list($key,$value) = each($formatter->smileys)) {
    if ($last_img != $value[3]) {
      $skey=str_replace(array("\\","'"),array("\\\\","&#39;"),$key);
      $chooser.= "<span onclick='mySmiley(\"$skey\")'>".$formatter->smiley_repl($key)."</span>&shy;";
      $last_img = $value[3];
      $idx++;
    }
  }
  $chooser.= "</div>\n";
  return $chooser;
}

// vim:et:sts=2:sw=2:
?>
