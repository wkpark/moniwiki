<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a UploadFile plugin for the MoniWiki
// vim:et:ts=2:
//
// $Id$
function do_uploadfile($formatter,$options) {
  global $DBInfo;

  $files=array();
  if (is_array($_FILES)) {
    if (($options['multiform'] > 1) or is_array($_FILES['upfile']['name'])) {
      $options['multiform']=$options['multiform'] ?
         $options['multiform']:sizeof($_FILES['upfile']['name']);
      $count=$options['multiform'];
      $files=&$_FILES;
    } else {
      $count=1;
      $files['upfile']['name'][]=&$_FILES['upfile']['name'];
      $files['upfile']['tmp_name'][]=&$_FILES['upfile']['tmp_name'];
      $options['rename']=array($options['rename']);
      $options['replace']=array($options['replace']);
    }
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

  for ($j=0;$j<$count;$j++) {

  # replace space and ':' strtr()
  $upfilename=str_replace(" ","_",$files['upfile']['name'][$j]);
  $upfilename=str_replace(":","_",$upfilename);

  preg_match("/(.*)\.([a-z0-9]{1,4})$/i",$upfilename,$fname);

  if (!$upfilename) continue;
  else if ($upfilename) $uploaded++;

  # upload file protection
  if ($DBInfo->pds_allowed)
     $pds_exts=$DBInfo->pds_allowed;
  else
     $pds_exts="png|jpg|jpeg|gif|mp3|zip|tgz|gz|txt|css|exe|hwp";
  if (!preg_match("/(".$pds_exts.")$/i",$fname[2]))
     $msg.=sprintf(_("%s does not allowed to upload"),$upfilename)."<br/>\n";

  $file_path= $newfile_path = $dir."/".$upfilename;
  $filename=$upfilename;
  if ($options['rename'][$j]) {
    # XXX
    $temp=explode("/",stripslashes($options['rename'][$j]));
    $upfilename= $temp[count($temp)-1];

    preg_match("/(.*)\.([a-z0-9]{1,4})$/i",$upfilename,$tname);
    # do not change the extention of the file.
    $fname[1]=$tname[1];
    $newfile_path = $dir."/".$tname[1].".$fname[2]";
  }

  # is file already exists ?
  $dummy=0;
  while (file_exists($newfile_path)) {
     $dummy=$dummy+1;
     $ufname=$fname[1]."_".$dummy; // rename file
     $upfilename=$ufname.".$fname[2]";
     $newfile_path= $dir."/".$upfilename;
  }
 
  $upfile=$files['upfile']['tmp_name'][$j];

  if ($options['replace'][$j]) {
    // backup
    if ($newfile_path) $test=@copy($file_path, $newfile_path);
    // replace
    $test=@copy($upfile, $file_path);
    $upfilename=$filename;
  } else {
    $test=@copy($upfile, $newfile_path);
  }
  if (!$test)
    $msg.=sprintf(_("Fail to copy \"%s\" to \"%s\""),$upfilename,$file_path);

  chmod($newfile_path,0644);

  $comment.="'$upfilename' ";

  $title.=sprintf(_("File \"%s\" is uploaded successfully"),$upfilename).'<br />';
  if ($key == 'UploadFile')
    $msg.= "<ins>Uploads:$upfilename</ins><br />";
  else {
    $msg.= "<ins>attachment:$upfilename</ins> or<br />";
    $msg.= "<ins>attachment:".$formatter->page->name.":$upfilename</ins><br />";
  }

  } // multiple upload

  $comment.="uploaded";
  $DBInfo->addLogEntry($key, $REMOTE_ADDR,$comment,"UPLOAD");
  
  $formatter->send_header("",$options);
  if ($uploaded < 2) {
    $formatter->send_title($title,"",$options);
    print $msg;
  } else {
    $msg=$title.$msg;
    $title=sprintf(_("Files are uploaded successfully"),$upfilename);
    $formatter->send_title($title,"",$options);
    print $msg;
  }
  $formatter->send_footer();
}

function macro_UploadFile($formatter,$value='',$options='') {
  $use_multi=1;
  $multiform='';
  if ($options['rename'] and !is_array($options['rename'])) {
    // rename option used by "attachment:" and it does not use multiple form.
    $rename=$options['rename'];
    $options['rename']=array();
    $options['rename'][0]=$rename;
    $use_multi=0;
  }
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
      $rename=stripslashes($options['rename'][$j]);
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

  return $form.$multiform;
}

?>
