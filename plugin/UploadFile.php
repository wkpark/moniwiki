<?php
// Copyright 2003-2009 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a UploadFile plugin for the MoniWiki
//
// $Id: UploadFile.php,v 1.52 2010/08/23 15:14:10 wkpark Exp $

function _upload_err_msg($error_code) {
    switch ($error_code) { 
    case UPLOAD_ERR_INI_SIZE:
        return _("The uploaded file exceeds the upload_max_filesize directive in php.ini");
    case UPLOAD_ERR_FORM_SIZE:
        return _("The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form");
    case UPLOAD_ERR_PARTIAL:
        return _("The uploaded file was only partially uploaded");
    case UPLOAD_ERR_NO_FILE:
        return _("No file was uploaded");
    case UPLOAD_ERR_NO_TMP_DIR:
        return _("Missing a temporary folder");
    case UPLOAD_ERR_CANT_WRITE:
        return _("Failed to write file to disk");
    case UPLOAD_ERR_EXTENSION:
        return _("File upload stopped by extension");
    default:
        return _("Unknown upload error");
    } 
} 

function do_uploadfile($formatter,$options) {
  global $DBInfo;

  $files=array();
  $title = '';

  if (isset($options['data'])) {
    if (substr($options['data'], 0, 5) == 'data:') {
      $data = substr($options['data'], 5);
    } else {
      $data = $options['data'];
    }
    $err = _("Fail to parse data string");
    while (preg_match('@^(image/(gif|jpe?g|png));base64,(.*)$@', $data, $match)) {
      $ret = base64_decode($match[3]);
      if ($ret === false) {
        $err = _("Fail to decode base64 data string.");
        break;
      } else {
        $name = isset($options['name'][0]) ? $options['name'] : 'unnamed';
        $name.= '.'.$match[2];

        $tmpfile = tempnam($DBInfo->vartmp_dir, 'DATA');
        $fp = fopen($tmpfile, 'wb');
        if (!is_resource($fp)) {
          $err = _("Fail to open file.\n");
          break;
        }
        fwrite($fp, $ret);
        fclose($fp);

        $count = 1;
        $files['upfile']['name'][] = $name;
        $files['upfile']['tmp_name'][] = $tmpfile;
        $files['upfile']['error'][] = '';
        $files['upfile']['type'][] = $match[1];
        $err = '';
        break;
      }
    }
  }
  if (!empty($err)) {
    echo $err;
    return;
  }

  if (isset($_FILES['upfile']) and is_array($_FILES)) {
    if ((!empty($options['multiform']) and $options['multiform'] > 1) or is_array($_FILES['upfile']['name'])) {
      $options['multiform']=!empty($options['multiform']) ?
         $options['multiform']:sizeof($_FILES['upfile']['name']);
      $count=$options['multiform'];
      $files=&$_FILES;
      if (!isset($options['rename'])) $options['rename']=array();
    } else {
      $count=1;
      $files['upfile']['name'][]=&$_FILES['upfile']['name'];
      $files['upfile']['tmp_name'][]=&$_FILES['upfile']['tmp_name'];
      $files['upfile']['error'][]=&$_FILES['upfile']['error'];
      $files['upfile']['type'][]=&$_FILES['upfile']['type'];
      $options['rename']=array($options['rename']);
      $options['replace']=array($options['replace']);
    }
  } else if (isset($options['MYFILES']) and is_array($options['MYFILES'])) { // for SWFUpload action
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
  // Set upload err msg func.
  if (!empty($DBInfo->upload_err_func) and function_exists($DBInfo->upload_err_func))
    $upload_err_func = $DBInfo->upload_err_func;
  else
    $upload_err_func = '_upload_err_msg';
  $msg = array();
  $err_msg = array();
  $upload_ok = array();

  $js='';
  $uploadid = !empty($options['uploadid']) ? $options['uploadid'] : '';
  if (!empty($uploadid) or !empty($options['MYFILES'])) {
    $js=<<<EOF
<script type="text/javascript">
/*<![CDATA[*/
function delAllForm(id) {
  if (!opener) return;
  if (id == '') return;
  var fform = opener.document.getElementById(id);

  if (fform && fform.rows && fform.rows.length) { // for UploadForm
    for (var i=fform.rows.length;i>0;i--) {
      fform.deleteRow(i-1);
    }
  } else { // for SWFUpload
    var listing = opener.document.getElementById('mmUploadFileListing');
    if (listing) {
      var elem = listing.getElementsByTagName("li");
      listing.innerHTML='';
    } else if (fform) {
      fform.reset();
    }
  }
}

delAllForm('$uploadid');
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
    if (isset($options['retval'])) return false; // ignore
    #$title="No file selected";
    $formatter->send_header("",$options);
    $formatter->send_title($title,"",$options);
    print macro_UploadFile($formatter,'',$options);
    if (!in_array('UploadedFiles',$formatter->actions))
      $formatter->actions[]='UploadedFiles';
    $formatter->send_footer("",$options);
    return false;
  }

  $key=$DBInfo->pageToKeyname($formatter->page->name);

  if ($key != 'UploadFile') {
    $dir= $DBInfo->upload_dir.'/'.$key;
    // support hashed upload_dir
    if (!is_dir($dir) and !empty($DBInfo->use_hashed_upload_dir)) {
      $prefix = get_hashed_prefix($key);
      $dir = $DBInfo->upload_dir.'/'.$prefix.$key;
    }
  } else {
    $dir= $DBInfo->upload_dir;
  }
  if (!file_exists($dir)) {
    umask(000);
    _mkdir_p($dir,0777);
    umask(02);
  }
  $REMOTE_ADDR=$_SERVER['REMOTE_ADDR'];
  $comment = "File ";
  $uploaded = '';

  $log_entry='';

  $protected_exts=!empty($DBInfo->pds_protected) ? $DBInfo->pds_protected :"pl|cgi|php";
  $safe_exts=!empty($DBInfo->pds_safe) ? $DBInfo->pds_safe :"txt|gif|png|jpg|jpeg";
  $protected=explode('|',$protected_exts);
  $safe=explode('|',$safe_exts);

  # upload file protection
  if (!empty($DBInfo->pds_allowed))
    $pds_exts=$DBInfo->pds_allowed;
  else
    $pds_exts="png|jpg|jpeg|gif|mp3|zip|tgz|gz|txt|css|exe|pdf|hwp";

  $allowed=0;
  if (isset($DBInfo->upload_masters) and in_array($options['id'],$DBInfo->upload_masters)) {
    // XXX WARN!!
    $pds_exts='.*';
    $allowed=1;
  }
  $safe_types=array('text'=>'','media'=>'','image'=>'','audio'=>'','application'=>'bin');

  for ($j=0;$j<$count;$j++) {

  # replace space and ':' strtr()
  $upfilename=str_replace(" ","_",$files['upfile']['name'][$j]);
  $upfilename=str_replace(":","_",$upfilename);

  preg_match("/^(.*)\.([a-z0-9]{1,5})$/i",$upfilename,$fname);

  if (!$upfilename) continue;
  else if ($upfilename) $uploaded++;

  $no_ext=0;
  if (empty($fname[2])) {
    $fname[1]=$upfilename;
    $fname[2]='';
    $no_ext=1;
  }

  if (!$allowed) {
    if (!empty($DBInfo->use_filetype)) {
      $type='';
      $type=$files['upfile']['type'][$j] ? $files['upfile']['type'][$j]:'text/plain';
      list($mtype,$xtype)=explode('/',$type);

      if (!empty($mtype) and array_key_exists($mtype,$safe_types)) {
        $allowed=1;
        $fname[2]= $fname[2] ? $fname[2]:$safe_types[$mtype];
      } else if ($no_ext) {
        $err_msg[]=sprintf(_("The %s type of %s is not allowed to upload"),$type,$upfilename);
        continue;
      }
    } else {
      $fname[2]=$fname[2] ? $fname[2]:'txt';
      $no_ext=0;
    }
  }

  $upfilename=preg_replace('/\.$/','',implode('.',array($fname[1],$fname[2])));

  if (!$allowed) {
    if (!$no_ext and !preg_match("/(".$pds_exts.")$/i",$fname[2])) {
      if ($DBInfo->use_filetype and !empty($type))
        $err_msg[]=sprintf(_("The %s type of %s is not allowed to upload"),$type,$upfilename);
      else
        $err_msg[]=sprintf(_("%s is not allowed to upload"),$upfilename);
      continue;
    } else if ($fname[2] and in_array(strtolower($fname[2]),$safe)) {
      $upfilename=$fname[1].'.'.$fname[2];
    } else {
      # check extra extentions for the mod_mime
      $exts=explode('.',$fname[1]);
      $ok=0;
      for ($i=sizeof($exts);$i>0;$i--) {
        if (in_array(strtolower($exts[$i - 1]),$safe)) {
          $ok=1;
          break;
        } else if (in_array(strtolower($exts[$i - 1]),$protected)) {
          $exts[$i].='.txt'; # extra check for mod_mime: append 'txt' extension: my.pl.hwp => my.pl.txt.hwp
          $ok=1;
          break;
        }
      }
      if ($ok) {
        $fname[1]=implode('.',$exts);
        $upfilename=$fname[1].'.'.$fname[2];
      }
    }
  }

  $file_path= $newfile_path = $dir."/".$upfilename;
  $filename=$upfilename;
  if (!empty($options['rename'][$j])) {
    # XXX
    $temp=explode("/",_stripslashes($options['rename'][$j]));
    $upfilename= $temp[count($temp)-1];

    preg_match("/^(.*)\.([a-z0-9]{1,5})$/i",$upfilename,$tname);
    $exts=explode('.',$tname[1]);
    $ok=0;
    for ($i=sizeof($exts);$i>0;$i--) {
      if (in_array(strtolower($exts[$i - 1]),$protected)) {
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
        $err_msg[]=sprintf(_("It is not allowed to change file ext. \"%s\" to \"%s\"."),$fname[2],$tname[2]);
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
  if (!empty($files['upfile']['error'][$j]) and
      $files['upfile']['error'][$j] != UPLOAD_ERR_OK) {
    $err_msg[]=_("ERROR:").' <tt>'.$upload_err_func($files['upfile']['error'][$j]).' : '.$upfilename .'</tt>';
    if ($files['upfile']['error'][$j] == UPLOAD_ERR_INI_SIZE)
      $err_msg[]="<tt>upload_max_filesize=".ini_get('upload_max_filesize').'</tt>';
    continue;
  }

  $_l_path=_l_filename($file_path);
  $new_l_path=_l_filename($newfile_path);

  if (!empty($options['replace'][$j])) {
    // backup
    if ($newfile_path != $file_path)
      $test=@copy($_l_path, $new_l_path);
    // replace
    $test=@copy($upfile, $_l_path);
    $upfilename=$filename;
  } else {
    $test=@copy($upfile, $new_l_path);
  }
  @unlink($upfile);
  if (!$test) {
    $err_msg[]=sprintf(_("Fail to copy \"%s\" to \"%s\""),$upfilename,$file_path);
    if ($files['upfile']['error'][$j] == UPLOAD_ERR_INI_SIZE)
      $err_msg[]="<tt>upload_max_filesize=".ini_get('upload_max_filesize').'</tt>';
    continue;
  }

  chmod($new_l_path,0644);

  $comment.="'$upfilename' ";

  $title.=(!empty($title) ? "\\n":'').
    sprintf(_("File \"%s\" is uploaded successfully"),$upfilename);

  $fullname=_html_escape($formatter->page->name)."/$upfilename";
  $upname=$upfilename;
  if (strpos($fullname,' ')!==false)
    $fullname='"'.$fullname.'"';
  if (strpos($upname,' ')!==false)
    $upname='"'.$upname.'"';

  if ($key == 'UploadFile') {
    $msg[]= "<ins>attachment:/$upname</ins>";
    $upload_ok[] = '/'.$upname;
    $log_entry.=" * attachment:/$upname?action=deletefile . . . @USERNAME@ @DATE@\n";
  } else {
    $msg[]= "<ins>attachment:$upname</ins> or";
    $msg[]= "<ins>attachment:$fullname</ins>";
    $upload_ok[] = $upname;
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

  if (!empty($options['action_mode']) and $options['action_mode'] == 'ajax') {
    $err = implode("\\n", $err_msg);
    $err = strip_tags($err);
    if ($err) $err .= "\\n";

    $formatter->header('Content-type: text/html; charset='.$DBInfo->charset);
    $scr = '';
    if (!empty($options['domain']) and preg_match('/^[a-z][a-z0-9]+(\.[a-z][a-z0-9]+)*$/i', $options['domain'])) {
        $scr = '<script type="text/javascript">document.domain="'.$options['domain'].'";</script>';
    }
    echo $scr.'
    {"title": "' . str_replace(array('"','<'), array("'",'&lt;'), $title) . '",
     "msg": ["' . $err.strip_tags(implode("\\n", $msg )) . '"],
     "uploaded":' . $uploaded.',
     "files": ["' . implode("\"\n,\"", $upload_ok ) . '"]
    }';
    return true;
  }

  $msgs = implode("<br />\n", $err_msg);
  $msgs.= implode("<br />\n", $msg);
  if (isset($options['retval'])) {
    $retval = array('title'=>$title, 'msg'=>$msgs, 'uploaded'=>$uploaded, 'files'=>$upload_ok);
    $ret = &$options['retval'];
    $ret = $retval;
    return true;
  } 
  $formatter->send_header("",$options);
  if ($uploaded < 2) {
    $formatter->send_title($title,"",$options);
    print $msgs;
  } else {
    $msg=$title.'<br />'.$msg;
    $title=sprintf(_("Files are uploaded successfully"),$upfilename);
    $formatter->send_title($title,"",$options);
    print $msgs;
  }

  print $js;
  $formatter->send_footer('', $options);

  if (isset($options['MYFILES']) and is_array($options['MYFILES']) and session_id() != '')
    session_destroy();
  return true;
}

function macro_UploadFile($formatter,$value='',$options='') {
  global $DBInfo;
  if ($value=='js') {
    return $formatter->macro_repl('UploadForm');
  } else if ($value=='swf') {
    return $formatter->macro_repl('SWFUpload');
  }
  $use_multi=1;
  $multiform='';
  if (!empty($options['rename'])) {
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
    $multiform.="</select><button type='submit'><span>"._("Multi upload form")."</span></button>\n";
  }

  $url=$formatter->link_url($formatter->page->urlname);

  $count= (!empty($options['multiform']) and $options['multiform'] > 1) ? $options['multiform']:1;

  $mode = '';
  if (!empty($options['action_mode']) and $options['action_mode'] == 'ajax') {
    $mode = '/ajax';
  }
  $form="<form enctype='multipart/form-data' method='post' action='$url'>\n";
  $form.="<input type='hidden' name='action' value='UploadFile$mode' />\n";
  $msg1=_("Replace original file");
  $msg2=_("Rename if it already exist");
  $suffix = '';
  for ($j=0;$j<$count;$j++) {
    if ($count > 1) $suffix="[$j]";
    if (!empty($options['rename'][$j])) {
      $rename=_stripslashes($options['rename'][$j]);
      $rename = _html_escape($rename);
      $extra="<input name='rename$suffix' value=\"$rename\" />: "._("Rename")."<br />";
    } else $extra='';
    $form.= <<<EOF
   <input type='file' name='upfile$suffix' size='30' />
EOF;
    if ($count == 1) $form.="<button type='submit'><span>"._("Upload") ."</span></button>";

    if ($DBInfo->flashupload)
      $form.=' '.sprintf(_("or %s."),$formatter->link_to('?action='.$DBInfo->flashupload,_("Multiple Upload files")));
    $form.= <<<EOF
<br/>
   $extra
   <input type='radio' name='replace$suffix' value='1' />$msg1<br />
   <input type='radio' name='replace$suffix' value='0' checked='checked' />$msg2<br />\n
EOF;
  }
  if ($count > 1) $form.="<button type='submit'><span>"._("Upload files")."</span></button>";
  $form.="</form>\n";

  if ($use_multi) {
    $multiform= <<<EOF
<form enctype="multipart/form-data" method='post' action='$url'>
   <input type='hidden' name='action' value='UploadFile$mode' />
   $multiform
</form>
EOF;
  }

  if (!in_array('UploadedFiles',$formatter->actions))
    $formatter->actions[]='UploadedFiles';
  if (!empty($formatter->preview) and !in_array('UploadFile',$formatter->actions)) {
    if (!empty($DBInfo->use_preview_uploads)) {
      $keyname=$DBInfo->pageToKeyname($formatter->page->name);
      if (is_dir($DBInfo->upload_dir.'/'.$keyname))
        $form=$formatter->macro_repl('UploadedFiles(tag=1)').$form;
    }
  }
  return $form.$multiform;
}

// vim:et:sts=4:sw=4:
?>
