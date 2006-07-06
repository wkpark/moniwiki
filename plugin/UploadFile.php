<?php
// Copyright 2003-2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a UploadFile plugin for the MoniWiki
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
      if (!isset($options['rename'])) $options['rename']=array();
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

  # upload file protection
  if ($DBInfo->pds_allowed)
     $pds_exts=$DBInfo->pds_allowed;
  else
     $pds_exts="png|jpg|jpeg|gif|mp3|zip|tgz|gz|txt|css|exe|pdf|hwp";
  if (!preg_match("/(".$pds_exts.")$/i",$fname[2])) {
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
  while (file_exists($newfile_path)) {
     $dummy=$dummy+1;
     $ufname=$fname[1]."_".$dummy; // rename file
     $upfilename=$ufname.".$fname[2]";
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
  if (!$test) {
    $msg.=sprintf(_("Fail to copy \"%s\" to \"%s\""),$upfilename,$file_path);
    $msg.='<br />'._("Please check your php.ini setting");
    continue;
  }

  chmod($newfile_path,0644);

  $comment.="'$upfilename' ";

  $title.=($title ? '<br />':'').
    sprintf(_("File \"%s\" is uploaded successfully"),$upfilename);
  if ($key == 'UploadFile') {
    $msg.= "<ins>Uploads:$upfilename</ins> or<br />";
    $msg.= "<ins>attachment:/$upfilename</ins><br />";
    $log_entry.=" * attachment:/$upfilename?action=deletefile . . . @USERNAME@ @DATE@\n";
  } else {
    $msg.= "<ins>attachment:$upfilename</ins> or<br />";
    $msg.= "<ins>attachment:".$formatter->page->name."/$upfilename</ins><br />";
    $log_entry.=" * attachment:".$formatter->page->name."/$upfilename?action=deletefile . . . @USERNAME@ @DATE@\n";
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
}

function macro_UploadFile($formatter,$value='',$options='') {
  if ($value=='js') {
    return $formatter->macro_repl('UploadForm');
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
