<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a UploadFile plugin for the MoniWiki
// vim:et:ts=2:
//
// $Id$
function do_uploadfile($formatter,$options) {
  global $DBInfo;

  # replace space and ':' strtr()
  $upfilename=str_replace(" ","_",$_FILES['upfile']['name']);
  $upfilename=str_replace(":","_",$upfilename);

  preg_match("/(.*)\.([a-z0-9]{1,4})$/i",$upfilename,$fname);

  if (!$upfilename) {
     #$title="No file selected";
     $formatter->send_header("",$options);
     $formatter->send_title($title,"",$options);
     print macro_UploadFile($formatter,'',$options);
     $formatter->send_footer("",$options);
     return;
  }
  # upload file protection
  if ($DBInfo->pds_allowed)
     $pds_exts=$DBInfo->pds_allowed;
  else
     $pds_exts="png|jpg|jpeg|gif|mp3|zip|tgz|gz|txt|css|exe|hwp";
  if (!preg_match("/(".$pds_exts.")$/i",$fname[2])) {
     $title="$fname[2] extension does not allowed to upload";
     $formatter->send_header("",$options);
     $formatter->send_title($title,"",$options);
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

  $file_path= $newfile_path = $dir."/".$upfilename;
  if ($options['rename']) {
    # XXX
    $temp=explode("/",stripslashes($options['rename']));
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
 
  $upfile=$_FILES['upfile']['tmp_name'];
  //$temp=explode("/",$_FILES['upfile']['tmp_name']);
  //$upfile="/tmp/".$temp[count($temp)-1];
  // Tip at http://phpschool.com

  if ($options['replace']) {
    if ($newfile_path) $test=@copy($file_path, $newfile_path);
    $test=@copy($upfile, $file_path);
  } else {
    $test=@copy($upfile, $newfile_path);
  }
  if (!$test) {
     $title=sprintf(_("Fail to copy \"%s\" to \"%s\""),$upfilename,$file_path);
     $formatter->send_header("",$options);
     $formatter->send_title($title,"",$options);
     return;
  }
  chmod($newfile_path,0644);

  $REMOTE_ADDR=$_SERVER['REMOTE_ADDR'];
  $comment="File '$upfilename' uploaded";
  $DBInfo->addLogEntry($key, $REMOTE_ADDR,$comment,"UPLOAD");
  
  $title=sprintf(_("File \"%s\" is uploaded successfully"),$upfilename);
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  print "<ins>Uploads:$upfilename</ins>";
  $formatter->send_footer();
}

function macro_UploadFile($formatter,$value='',$options='') {
  if ($options['rename']) {
    $rename=stripslashes($options['rename']);
    $extra="<input name='rename' value='$rename' />"._(": Rename")."<br />";
  }

  $url=$formatter->link_url($formatter->page->urlname);
  $form= <<<EOF
<form enctype="multipart/form-data" method='post' action='$url'>
   <input type='hidden' name='action' value='UploadFile' />
   <input type='file' name='upfile' size='30' />
   <input type='submit' value='Upload' /><br />
   $extra
   <input type='radio' name='replace' value='1' />Replace original file<br />
   <input type='radio' name='replace' value='0' checked='checked' />Rename if it already exist<br />
</form>
EOF;

   if (!in_array('UploadedFiles',$formatter->actions))
     $formatter->actions[]='UploadedFiles';

   return $form;
}

?>
