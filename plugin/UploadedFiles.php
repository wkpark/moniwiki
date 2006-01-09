<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a UploadedFiles plugin for the MoniWiki
// vim:et:sts=4:
//
// $Id$

function do_uploadedfiles($formatter,$options) {
  $list=macro_UploadedFiles($formatter,$options['page'],$options);

  $formatter->send_header("",$options);
  $formatter->send_title("","",$options);

  print $list;
  $args['editable']=0;
  $formatter->send_footer($args,$options);
  return;
}

function macro_UploadedFiles($formatter,$value="",$options="") {
   global $DBInfo;

   $use_preview=$DBInfo->use_preview_uploads ? $DBInfo->use_preview_uploads:0;
   $preview_width=64;

   $use_preview=0;
   $js_tag=0;
   $js_script='';
   $uploader='';

   $iconset='gnome';
   $icon_dir=$DBInfo->imgs_dir.'/plugin/UploadedFiles/'.$iconset;

   $args=explode(',',$value);
   $value='';

   if ($formatter->preview) {
     $js_tag=1;$use_preview=1;
     $uploader='UploadForm';
   } else if ($options['preview']) {
     $use_preview=1;
   }

   if ($options['tag']) {
     $js_tag=1;$use_preview=1;
   }
   foreach ($args as $arg) {
      $arg=trim($arg);
      if (($p=strpos($arg,'='))!==false) {
         $k=substr($arg,0,$p);
         $v=substr($arg,$p+1);
         if ($k=='preview') { $use_preview=$v; }
         else if ($k == 'tag') {
           $js_tag=1; $use_preview=1;
         }
      } else {
         $value=$arg;
      }
   }
   if ($js_tag) {
      $form='editform';
      $js_script=<<<EOS
      <script language="javascript" type="text/javascript">
/*<![CDATA[*/
// based on wikibits.js in the MediaWiki
// small fix to use opener in the dokuwiki.

function insertTags(tagOpen,tagClose,myText,replaced)
{
  if (document.$form)
    var txtarea = document.$form.savetext;
  else {
    // some alternate form? take the first one we can find
    var areas = document.getElementsByTagName('textarea');
    if (areas.length > 0) {
        var txtarea = areas[0];
    } else if (opener.document.$form.savetext) {
        var txtarea = opener.document.$form.savetext;
    }
  }

  if(document.selection && document.all) {
    var theSelection = document.selection.createRange().text;
    txtarea.focus();
    if(theSelection.charAt(theSelection.length - 1) == " "){
      // exclude ending space char, if any
      theSelection = theSelection.substring(0, theSelection.length - 1);
      document.selection.createRange().text = theSelection + tagOpen + myText + tagClose + " ";
    } else {
      document.selection.createRange().text = theSelection + tagOpen + myText + tagClose + " ";
    }
  }
  // Mozilla
  else if(txtarea.selectionStart || txtarea.selectionStart == '0') {
		//var replaced = false;
		var startPos = txtarea.selectionStart;
		var endPos = txtarea.selectionEnd;
		if (!replaced && endPos-startPos)
			replaced = true;
		var scrollTop = txtarea.scrollTop;

		if (myText.charAt(myText.length - 1) == " ") { // exclude ending space char, if any
			subst = tagOpen + myText.substring(0, (myText.length - 1)) + tagClose + " ";
		} else {
			subst = tagOpen + myText + tagClose;
		}
		txtarea.value = txtarea.value.substring(0, startPos) + subst +
			txtarea.value.substring(endPos, txtarea.value.length);
		txtarea.focus();
		//set new selection
		if (replaced) {
			var cPos = startPos+(tagOpen.length+myText.length+tagClose.length);
			txtarea.selectionStart = cPos;
			txtarea.selectionEnd = cPos;
		} else {
			txtarea.selectionStart = startPos+tagOpen.length;   
			txtarea.selectionEnd = startPos+tagOpen.length+myText.length;
		}	
		txtarea.scrollTop = scrollTop;
  } else { // All others
    txtarea.value += tagOpen + myText + tagClose + " ";
    txtarea.focus();
  }
}
/*]]>*/
</script>
EOS;
   }

   if ($DBInfo->download_action) $mydownload=$DBInfo->download_action;
   else $mydownload='download';
   $checkbox='checkbox';
   $needle="//";
   if ($options['download'] || $DBInfo->force_download)
     $force_download=1;
   if ($options['needle']) $needle=$options['needle'];
   if ($options['checkbox']) $checkbox=$options['checkbox'];

   if (!in_array('UploadFile',$formatter->actions))
     $formatter->actions[]='UploadFile';

   if ($value and $value!='UploadFile') {
      $key=$DBInfo->pageToKeyname($value);
      if ($force_download or $key != $value)
        $prefix=$formatter->link_url(_rawurlencode($value),"?action=$mydownload&amp;value=");
      $dir=$DBInfo->upload_dir."/$key";
   } else {
      $value=$formatter->page->urlname;
      $key=$DBInfo->pageToKeyname($formatter->page->name);
      if ($force_download or $key != $formatter->page->name)
        $prefix=$formatter->link_url($formatter->page->urlname,"?action=$mydownload&amp;value=");
      $dir=$DBInfo->upload_dir."/$key";
   }

   if ($formatter->preview and $formatter->page->name == $value) { 
     $opener='';
   } else {
     $opener=$value.':';
   }

   if ($value!='UploadFile' and file_exists($dir))
      $handle= opendir($dir);
   else {
      $key='';
      $value='UploadFile';
      $prefix.= ($prefix ? '/':'');
      $dir=$DBInfo->upload_dir;
      $handle= opendir($dir);
      $opener='/';
   }

   $upfiles=array();
   $dirs=array();

   while ($file= readdir($handle)) {
      if ($file[0]=='.') continue;
      if (!$options['nodir'] and is_dir($dir."/".$file)) {
        if ($value =='UploadFile')
          $dirs[]= $DBInfo->keyToPagename($file);
      } else if (preg_match($needle,$file))
        $upfiles[]= $file;
   }
   closedir($handle);
   if (!$upfiles and !$dirs) return "<h3>No files uploaded</h3>";
   sort($upfiles); sort($dirs);

   $link=$formatter->link_url($formatter->page->urlname);
   $out="<form method='post' action='$link'>";
   $out.="<input type='hidden' name='action' value='DeleteFile' />\n";
   if ($key)
     $out.="<input type='hidden' name='value' value='$value' />\n";
   $out.="<table border='0' cellpadding='2'>\n";
   $out.="<tr><th colspan='2'>File name</th><th>Size</th><th>Date</th></tr>\n";
   $idx=1;

   if ($js_tag) {
     $attr=' target="_blank"';
     $extra='&amp;tag=1';
   } else {
     $attr='';
     $extra='';
   }
   foreach ($dirs as $file) {
      $link=$formatter->link_url($file,"?action=uploadedfiles$extra",$file,$attr);
      $date=date("Y-m-d",filemtime($dir."/".$DBInfo->pageToKeyname($file)));
      $out.="<tr><td class='wiki'><input type='$checkbox' name='files[$idx]' value='$file' /></td><td class='wiki'><a href='$link'>$file/</a></td><td align='right' class='wiki'>&nbsp;</td><td class='wiki'>$date</td></tr>\n";
      $idx++;
   }

   if (!$options['nodir'] and !$dirs) {
      if ($js_tag) {
        $attr=' target="_blank"';
        $extra='&amp;tag=1';
      }
      $link=$formatter->link_tag('UploadFile',"?action=uploadedfiles&amp;value=top$extra","..",$attr);
      $date=date("Y-m-d",filemtime($dir."/.."));
      $out.="<tr><td class='wiki'>&nbsp;</td><td class='wiki'>$link</td><td align='right' class='wiki'>&nbsp;</td><td class='wiki'>$date</td></tr>\n";
   }

   if (!$prefix) $prefix=$DBInfo->url_prefix."/".$dir."/";

   $unit=array('Bytes','KB','MB','GB','TB');

   $down_mode=substr($prefix,strlen($prefix)-1) === '=';
   foreach ($upfiles as $file) {
      if ($down_mode)
        $link=str_replace("value=","value=".rawurlencode($file),$prefix);
      else
        $link=$prefix.rawurlencode($file);
      $size=filesize($dir.'/'.$file);

      $i=0;
      for (;$i<4;$i++) {
         if ($size <= 1024) {
            $size= round($size,2).' '.$unit[$i];
            break;
         }
         $size=$size/1024;
      }
      $size=round($size,2).' '.$unit[$i];

      $date=date('Y-m-d',filemtime($dir.'/'.$file));
      $fname=$file;
      $attr='';
      if ($use_preview or $js_tag) {
        $tag_open='attachment:'; $tag_close='';
        if ($opener != $value)
            $tag_open.=$opener;
        $alt="$tag_open$file$tag_close";
        preg_match("/\.(.{1,4})$/",$fname,$m);
        $ext=strtolower($m[1]);
        if ($ext and stristr('gif,png,jpeg,jpg',$ext)) {
          $fname="<img src='$link' width='$preview_width' $alt />";
        } else {
          if (preg_match('/^(wmv|avi|mpeg|mpg|swf|wav|mp3|ogg|midi|mid|mov)$/',$ext)) {
            $tag_open='[[Media('; $tag_close=')]]';
            $alt="$tag_open$file$tag_close";
          } else if (!preg_match('/^(bmp|c|h|java|py|bak|diff|doc|css|php|xml|html|mod|'.
              'rpm|deb|pdf|ppt|xls|tgz|gz|bz2|zip)$/',$ext)) {
            $ext='unknown';
          }
          $fname="<img src='$icon_dir/$ext.png' $alt />";
        }
        if ($js_tag) {
          //if (strpos($file,' '))
          $tag="insertTags('$tag_open','$tag_close','$file',true)";
          $link="javascript:$tag";
        }
      }
      $out.="<tr><td class='wiki'><input type='$checkbox' name='files[$idx]' value='$file' /></td><td class='wiki'><a href=\"$link\"$attr>$fname</a></td><td align='right' class='wiki'>$size</td><td class='wiki'>$date</td></tr>\n";
      $idx++;
   }
   $idx--;
   $out.="<tr><th colspan='2'>Total $idx files</th><td></td><td></td></tr>\n";
   $out.="</table>\n";
   if ($DBInfo->security->is_protected("deletefile",$options))
     $out.=_("Password").": <input type='password' name='passwd' size='10' />\n";
   $out.="<input type='submit' value='"._("Delete selected files")."' /></form>\n";

   if (!$value and !in_array('UploadFile',$formatter->actions))
     $formatter->actions[]='UploadFile';

   if ($uploader and !in_array('UploadedFiles',$formatter->actions)) {
     $out.=$formatter->macro_repl($uploader);
   }
   return $js_script.$out;
}

?>
