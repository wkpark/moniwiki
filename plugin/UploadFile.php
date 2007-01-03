<?php
// Copyright 2003-2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a UploadFile plugin for the MoniWiki
//
// $Id$
function do_uploadfile($formatter,$options) {
  global $DBInfo;

  $files=array();

  if (isset($_FILES['upfile']) and is_array($_FILES)) {
    if (($options['multiform'] > 1) or is_array($_FILES['upfile']['name'])) {
      $options['multiform']=$options['multiform'] ?
         $options['multiform']:sizeof($_FILES['upfile']['name']);
      $count=$options['multiform'];
      $files=&$_FILES;
      if (!isset($options['rename'])) $options['rename']=array();
    } else {
      $count=1;
      $files['upfile']['name'][]=&$_FILES['upfile']['name'];
      $files['upfile']['tmp_name'][]=&$_FILES['upfile']['tmp_name'];
      $files['upfile']['type'][]=&$_FILES['upfile']['type'];
      $options['rename']=array($options['rename']);
      $options['replace']=array($options['replace']);
    }
  } else if (is_array($options['MYFILES'])) { // for SWFUpload action
    $count=sizeof($options['MYFILES']);
    $MYFILES=&$options['MYFILES'];
    $mysubdir=$options['mysubdir'];
    for ($i=0;$i<$count;$i++) {
      $myname=$MYFILES[$i];
      $files['upfile']['name'][]=$myname;
      $files['upfile']['tmp_name'][]=$DBInfo->upload_dir.'/.swfupload/'.$mysubdir.$myname; // XXX
      $files['rename'][]='';
      $files['replace'][]='';
    }
  }

  $js='';
  if ($options['uploadid'] or $options['MYFILES']) {
    $js=<<<EOF
<script type="text/javascript">
/*<![CDATA[*/
function delAllForm(id) {
  var fform = opener.document.getElementById(id);

  if (fform && fform.rows.length) { // for UploadForm
    for (var i=fform.rows.length;i>0;i--) {
      fform.deleteRow(i-1);
    }
  } else { // for SWFUpload
    var listing = opener.document.getElementById('mmUploadFileListing');
    if (listing) {
      var elem = listing.getElementsByTagName("li");
      listing.innerHTML='';
    }
  }
}

delAllForm('$options[uploadid]');
/*]]>*/
</script>\n
EOF;
  }

  $ok=0;
  if ($files) {
    foreach ($files['upfile']['name'] as $f) {
      if ($f) { $ok=1;break;}
    }
  }

  if (!$ok) {
    #$title="No file selected";
    $formatter->send_header("",$options);
    $formatter->send_title($title,"",$options);
    print macro_UploadFile($formatter,'',$options);
    if (!in_array('UploadedFiles',$formatter->actions))
      $formatter->actions[]='UploadedFiles';
    $formatter->send_footer("",$options);
    return;
  }

  $key=$DBInfo->pageToKeyname($formatter->page->name);
  if ($key != 'UploadFile')
    $dir= $DBInfo->upload_dir."/$key";
  else
    $dir= $DBInfo->upload_dir;
  if (!file_exists($dir)) {
    umask(000);
    mkdir($dir,0777);
    umask(02);
  }
  $REMOTE_ADDR=$_SERVER['REMOTE_ADDR'];
  $comment.="File ";

  $log_entry='';

  $protected_exts=$DBInfo->pds_protected ? $DBInfo->pds_protected :"pl|cgi|php";
  $protected=explode('|',$protected_exts);

  for ($j=0;$j<$count;$j++) {

  # replace space and ':' strtr()
  $upfilename=str_replace(" ","_",$files['upfile']['name'][$j]);
  $upfilename=str_replace(":","_",$upfilename);

  preg_match("/^(.*)\.([a-z0-9]{1,4})$/i",$upfilename,$fname);

  if (!$upfilename) continue;
  else if ($upfilename) $uploaded++;

  $no_ext=0;
  $type='';
  if (!$fname) {
    $no_ext=1;
    $fname[1]=$upfilename;
    $fname[2]='';
    // XXX
    if ($DBInfo->use_filetype) $type=$files['upfile']['type'] ? $files['upfile']['type']:'text/plain';
    else $fname[2]='txt'; // XXX
  }

  # upload file protection
  if ($DBInfo->pds_allowed)
     $pds_exts=$DBInfo->pds_allowed;
  else
     $pds_exts="png|jpg|jpeg|gif|mp3|zip|tgz|gz|txt|css|exe|pdf|hwp";
  if (!$no_ext and !preg_match("/(".$pds_exts.")$/i",$fname[2])) {
     $msg.=sprintf(_("%s is not allowed to upload"),$upfilename)."<br/>\n";
     continue;
  } else {
    # check extra extentions for the mod_mime
    $exts=explode('.',$fname[1]);
    $ok=0;
    for ($i=sizeof($exts);$i>0;$i--) {
      if (in_array(strtolower($exts[$i]),$protected)) {
        $exts[$i].='.txt';
        $ok=1;
        break;
      }
    }
    if ($ok) {
      $fname[1]=implode('.',$exts);
      $upfilename=$fname[1].'.'.$fname[2];
    }
  }

  $file_path= $newfile_path = $dir."/".$upfilename;
  $filename=$upfilename;
  if ($options['rename'][$j]) {
    # XXX
    $temp=explode("/",_stripslashes($options['rename'][$j]));
    $upfilename= $temp[count($temp)-1];

    preg_match("/^(.*)\.([a-z0-9]{1,4})$/i",$upfilename,$tname);
    $exts=explode('.',$tname[1]);
    $ok=0;
    for ($i=sizeof($exts);$i>0;$i--) {
      if (in_array(strtolower($exts[$i]),$protected)) {
        $exts[$i].='.txt';
        $ok=1;
        break;
      }
    }
    if ($ok) {
      $tname[1]=implode('.',$exts);
      $upfilename=$tname[1].'.'.$fname[2];
    }
    
    # check the extention of the new file name.
    $fname[1]=$tname[1];
    $newfile_path = $dir."/".$tname[1].".$fname[2]";
    if ($tname[2] != $fname[2]) {
      if (strtolower($tname[2])==strtolower($fname[2])) {
        # change the case of the file ext. is allowed
        $newfile_path = $dir."/".$tname[1].".$tname[2]";
      } else {
        $msg.=sprintf(_("It is not allowed to change file ext. \"%s\" to \"%s\"."),$fname[2],$tname[2]).'<br />';
      }
    }
  }

  # is file already exists ?
  $dummy=0;
  $myext=$fname[2] ? '.'.$fname[2]:'';
  while (@file_exists($newfile_path)) {
     $dummy=$dummy+1;
     $ufname=$fname[1]."_".$dummy; // rename file
     $upfilename=$ufname.$myext;
     $newfile_path= $dir."/".$upfilename;
  }
 
  $upfile=$files['upfile']['tmp_name'][$j];

  if ($options['replace'][$j]) {
    // backup
    if ($newfile_path != $file_path)
      $test=@copy($file_path, $newfile_path);
    // replace
    $test=@copy($upfile, $file_path);
    $upfilename=$filename;
  } else {
    $test=@copy($upfile, $newfile_path);
  }
  @unlink($upfile);
  if (!$test) {
    $msg.=sprintf(_("Fail to copy \"%s\" to \"%s\""),$upfilename,$file_path);
    $msg.='<br />'._("Please check your php.ini setting");
    $msg.='<br />'."<tt>upload_max_filesize=".ini_get('upload_max_filesize').'</tt><br />';
    continue;
  }

  chmod($newfile_path,0644);

  $comment.="'$upfilename' ";

  $title.=($title ? '<br />':'').
    sprintf(_("File \"%s\" is uploaded successfully"),$upfilename);

  $fullname=$formatter->page->name."/$upfilename";
  $upname=$upfilename;
  if (strpos($fullname,' ')!==false)
    $fullname='"'.$fullname.'"';
  if (strpos($upname,' ')!==false)
    $upname='"'.$upname.'"';

  if ($key == 'UploadFile') {
    $msg.= "<ins>Uploads:$upname</ins> or<br />";
    $msg.= "<ins>attachment:/$upname</ins><br />";
    $log_entry.=" * attachment:/$upname?action=deletefile . . . @USERNAME@ @DATE@\n";
  } else {
    $msg.= "<ins>attachment:$upname</ins> or<br />";
    $msg.= "<ins>attachment:$fullname</ins><br />";
    $log_entry.=" * attachment:$fullname?action=deletefile . . . @USERNAME@ @DATE@\n";
  }

  } // multiple upload

  $comment.="uploaded";
  if (!empty($DBInfo->upload_changes)) {
    $p=$DBInfo->getPage($DBInfo->upload_changes);
    $raw_body=$p->_get_raw_body();
    if ($raw_body and $raw_body[strlen($raw_body)-1] != "\n")
      $raw_body.="\n";
    $raw_body.=$log_entry;
    $p->write($raw_body);
    $DBInfo->savePage($p,$comment,$options);
  } else
    $DBInfo->addLogEntry($key, $REMOTE_ADDR,$comment,"UPLOAD");
  
  $formatter->send_header("",$options);
  if ($uploaded < 2) {
    $formatter->send_title($title,"",$options);
    print $msg;
  } else {
    $msg=$title.'<br />'.$msg;
    $title=sprintf(_("Files are uploaded successfully"),$upfilename);
    $formatter->send_title($title,"",$options);
    print $msg;
  }

  $formatter->send_footer();
  print $js;

  if (is_array($options['MYFILES']))
    session_destroy();
}

function macro_UploadFile($formatter,$value='',$options='') {
  if ($value=='js') {
    return $formatter->macro_repl('UploadForm');
  } else if ($value=='swf') {
    return $formatter->macro_repl('SWFUpload');
  }
  $use_multi=1;
  $multiform='';
  if ($options['rename']) {
    if (!is_array($options['rename'])) {
      // rename option used by "attachment:" and it does not use multiple form.
      $rename=$options['rename'];
      $options['rename']=array();
      $options['rename'][0]=$rename;
      $use_multi=0;
    }
  } else
    $options['rename']=array();
  if ($use_multi) {
    $multiform="<select name='multiform' />\n";
    for ($i=2;$i<=10;$i++)
      $multiform.="<option value='$i'>$i</option>\n";
    $multiform.="</select><input type='submit' value='Multi upload form' />\n";
  }

  $url=$formatter->link_url($formatter->page->urlname);

  $count= ($options['multiform'] > 1) ? $options['multiform']:1;

  $form="<form enctype='multipart/form-data' method='post' action='$url'>\n";
  $form.="<input type='hidden' name='action' value='UploadFile' />\n";
  for ($j=0;$j<$count;$j++) {
    if ($count > 1) $suffix="[$j]";
    if ($options['rename'][$j]) {
      $rename=_stripslashes($options['rename'][$j]);
      $extra="<input name='rename$suffix' value='$rename' />"._(": Rename")."<br />";
    } else $extra='';
    $form.= <<<EOF
   <input type='file' name='upfile$suffix' size='30' />
EOF;
    if ($count == 1) $form.="<input type='submit' value='Upload' />";
    $form.= <<<EOF
<br/>
   $extra
   <input type='radio' name='replace$suffix' value='1' />Replace original file<br />
   <input type='radio' name='replace$suffix' value='0' checked='checked' />Rename if it already exist<br />\n
EOF;
  }
  if ($count > 1) $form.="<input type='submit' value='Upload files' />";
  $form.="</form>\n";

  if ($use_multi) {
    $multiform= <<<EOF
<form enctype="multipart/form-data" method='post' action='$url'>
   <input type='hidden' name='action' value='UploadFile' />
   $multiform
</form>
EOF;
  }

  if (!in_array('UploadedFiles',$formatter->actions))
    $formatter->actions[]='UploadedFiles';
  if ($formatter->preview and !in_array('UploadFile',$formatter->actions)) {
    $form=$formatter->macro_repl('UploadedFiles(tag=1)').$form;
  }
  return $form.$multiform;
}

// vim:et:sts=2:sw=2:
?>
