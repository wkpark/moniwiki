<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Attachment macro plugin for the MoniWiki
//
// Date: 2006-12-15
// Name: Attachment
// Description: Attachment Plugin
// URL: MoniWiki:AttachmentPlugin
// Version: $Revision: 1.46 $
// Depend: 1.1.3
// License: GPL
//
// Usage: [[Attachment(filename)]]
//
// $Id: Attachment.php,v 1.46 2010/08/23 09:15:23 wkpark Exp $

function macro_Attachment($formatter,$value,$options=array()) {
  global $DBInfo;

  if (!is_array($options) and $options==1) $options=array('link'=>1); // compatible

  if ($formatter->_macrocache and empty($options['call']))
    return $formatter->macro_cache_repl('Attachment', $value);

  $attr='';
  if (!empty($DBInfo->force_download)) $force_download=1;
  if (!empty($DBInfo->download_action)) $mydownload=$DBInfo->download_action;
  else $mydownload='download';
  $extra_action='';

  $text='';
  $caption='';
  $cap_bra='';
  $cap_ket='';
  $bra = '';
  $ket = '';

  if ($options and !$DBInfo->security->is_allowed($mydownload,$options))
    return $text;

  if (!empty($formatter->wikimarkup) and empty($options['nomarkup'])) {
    $ll=$rr='';
    if (strpos($value,' ') !==false) { $ll='['; $rr=']'; }
    $bra= "<span class='wikiMarkup'><!-- wiki:\n${ll}attachment:$value$rr\n-->";
    $ket= '</span>';
  }

#  if ($value[0]=='"' and ($p2=strpos(substr($value,1),'"')) !== false)
#    $value=substr($value,1,$p2); # attachment:"my image.png" => my image.png
# FIXME attachment:"hello.png" => error
  if (($p = strpos($value,' ')) !== false and (strpos(substr($value,0,$p),','))=== false) {
    // [[Attachment(my.png,width=100,height=200,caption="Hello(space)World")]]
    // [attachment:my.ext(space)hello]
    // [attachment:my.ext(space)attachment:my.png]
    // [attachment:my.ext(space)http://url/../my.png]
    if ($value[0]=='"' and ($p2=strpos(substr($value,1),'"')) !== false) {
      $text=$ntext=substr($value,$p2+3);
      $dummy=substr($value,1,$p2); # "my image.png" => my image.png
      $args=substr($value,$p2+2);
      $value=$dummy.$args; # append query string
    } else {
      $text=$ntext=substr($value,$p+1);
      $value=substr($value,0,$p);
    }
    if (substr($text,0,11)=='attachment:') {
      $fname=substr($text,11);
      $ntext=macro_Attachment($formatter,$fname,array('link'=>1));
    }
    if (preg_match("/\.(png|gif|jpeg|jpg|bmp)$/i",$ntext)) {
      $_l_ntext=_l_filename($ntext);
      if (!file_exists($_l_ntext)) {
        $fname=preg_replace('/^"([^"]*)"$/',"\\1",$fname);
        $mydownload='UploadFile&amp;rename='.$fname;
        $text=sprintf(_("Upload new Attachment \"%s\""),$fname);
        $text=str_replace('"','\'',$text);
      }
      $ntext=qualifiedUrl($DBInfo->url_prefix.'/'.$ntext);
      $img_link='<img src="'.$ntext.'" alt="'.$text.'" border="0" />';
    } else {
      if (($q=strpos($ntext,','))!== false) {
        $alt=substr($ntext,0,$q);
        $caption=substr($ntext,$q+1);
      } else {
        $alt=$ntext;
      }
    }
  } else {
    $value=str_replace('%20',' ',$value);
  }

  $lightbox_attr='';
  if (($dummy=strpos($value,'?'))) {
    # for attachment: syntax
    parse_str(substr($value,$dummy+1),$attrs);
    $value=substr($value,0,$dummy);
    foreach ($attrs as $name=>$val) {
      if ($name=='action') {
        if ($val == 'deletefile') $extra_action=$val;
        else $mydownload=$val;
      } else {
        if (in_array($name,array('width','height'))) {
          $attr.="$name=\"$val\" ";
          if (!empty($DBInfo->use_lightbox)) $lightbox_attr=' rel="lightbox" ';
        } else if (in_array($name,array('thumb','thumbwidth','thumbheight'))){
          $use_thumb=1;
          $thumb[$name]=$val;
        }
      }
    }

    if ($attrs['align']) $attr.='class="img'.ucfirst($attrs['align']).'" ';
  }

  if (preg_match('/^data:image\/(png|jpg|jpeg);base64,/',$value)) {
    // need to hack for IE ?
    return "<img src='".$value."' $attr />";
  }

  $imgalign = '';
  if (!$attr and ($dummy=strpos($value,','))) {
    # for Attachment macro
    $args=explode(',',substr($value,$dummy+1));
    $value=substr($value,0,$dummy);
    foreach ($args as $arg) {
      //list($k,$v)=split('=',trim($arg),2);
      $tmp = explode('=',trim($arg),2);
      $k = $tmp[0];
      $v = !empty($tmp[1]) ? $tmp[1] : '';
      if ($v) {
        if (in_array($k,array('width','height'))) {
          $attrs[trim($k)]=$v;
          $attr.="$arg ";
        } else if ($k=='align') {
          $imgalign='img'.ucfirst($v);
          $align='class="'.$imgalign.'" ';
        } else if (in_array($k,array('caption','alt','title'))) {
          // XXX
          $caption=preg_replace("/^([\"'])([^\\1]+)\\1$/","\\2",trim($v));
          #$caption=preg_replace('/^"([^"]*)"$/',"\\1",trim($v));
        } else if (in_array($k,array('thumb','thumbwidth','thumbheight'))){
          $use_thumb=1;
          $thumb[$k]=$v;
        }
      }
    }
  }

  $attr.=$lightbox_attr;

  if (($p=strpos($value,':')) !== false or ($p=strrpos($value,'/')) !== false) {
    $subpage=substr($value,0,$p);
    $file=substr($value,$p+1);
    $value=$subpage.'/'.$file; # normalize page arg
    if ($subpage and is_dir($DBInfo->upload_dir.'/'.$DBInfo->pageToKeyname($subpage))) {
      $pagename=$subpage;
      $key=$DBInfo->pageToKeyname($subpage);
      $value=$file;
    } else {
      $pagename='';
      $key='';
    }
    $dir=$key ? $DBInfo->upload_dir.'/'.$key:$DBInfo->upload_dir;
  } else {
    $pagename=$formatter->page->name;
    $key=$DBInfo->pageToKeyname($formatter->page->name);
    $dir=$DBInfo->upload_dir.'/'.$key;
    $file=$value;
  }
  // check file name XXX
  if (!$file) return $bra.'attachment:/'.$ket;

  $upload_file=$dir.'/'.$file;
  if (!empty($options['link']) and $options['link'] == 1) return $upload_file;

  if (!$text) $text=$file;

  $_l_file=_l_filename($file);
  $_l_upload_file=$dir.'/'.$_l_file;

  if (file_exists($_l_upload_file)) {
    $file_ok=1;
  } else if (!empty($formatter->wikimarkup) and empty($options['nomarkup'])) {
    if (!empty($DBInfo->swfupload_depth) and $DBInfo->swfupload_depth > 2) {
      $depth=$DBInfo->swfupload_depth;
    } else {
      $depth=2;
    }

    if (!empty($DBInfo->nosession)) { // ip based
      $myid=md5($_SERVER['REMOTE_ADDR'].'.'.'MONIWIKI'); // FIXME
    } else {
      $myid=session_id();
    }
    $prefix=substr($myid,0,$depth);
    $mydir=$DBInfo->upload_dir.'/.swfupload/'.$prefix.'/'.$myid;
    if (file_exists($mydir.'/'.$_l_file)) {
      if (!$img_link && preg_match("/\.(png|gif|jpeg|jpg|bmp)$/i",$upload_file)) {
        $ntext=qualifiedUrl($DBInfo->url_prefix.'/'.$mydir.'/'.$text);
        $img_link='<img src="'.$ntext.'" alt="'.$text.'" border="0" />';
        return $bra."<span class=\"attach\">$img_link</span>".$ket;
      } else {
        $sz=filesize($mydir.'/'.$_l_file);
        $unit=array('Bytes','KB','MB','GB','TB');
        for ($i=0;$i<4;$i++) {
          if ($sz <= 1024) {
            #$sz= round($sz,2).' '.$unit[$i];
            break;
          }
          $sz=$sz/1024;
        }
        $info=' ('.round($sz,2).' '.$unit[$i].') ';

        return $bra."<span class=\"attach\">".$formatter->icon['attach'].$text.'</span>'.$info.$ket;
      }
    }
  }

  if (!empty($file_ok)) {

    $imgcls='imgAttach';

    if ($imgalign == 'imgCenter' or ($caption && empty($imgalign))) {
      if (!$attrs['width']) {
        $size=getimagesize($_l_upload_file); // XXX
        $attrs['width']=$size[0];
      }
    }

    $img_width='';
    if (!empty($attrs['width'])) $img_width=' style="width:'.$attrs['width'].'px"';

    if ($caption) {
      $cls=$imgalign ? 'imgContainer '.$imgalign:'imgContainer'; 
      $caption='<div class="imgCaption">'.$caption.'</div>';
      $cap_bra='<div class="'.$cls.'"'.$img_width.'>';
      $cap_ket='</div>';
      $img_width='';
    } else {
      $imgcls=$imgalign ? 'imgAttach '.$imgalign:'imgAttach';
    }

    $sz=filesize($_l_upload_file);
    $unit=array('Bytes','KB','MB','GB','TB');
    for ($i=0;$i<4;$i++) {
      if ($sz <= 1024) {
        #$sz= round($sz,2).' '.$unit[$i];
        break;
      }
      $sz=$sz/1024;
    }
    $info=' ('.round($sz,2).' '.$unit[$i].') ';

    if (!in_array('UploadedFiles',$formatter->actions))
      $formatter->actions[]='UploadedFiles';

    if (empty($img_link) && preg_match("/\.(png|gif|jpeg|jpg|bmp)$/i",$upload_file)) {
      // thumbnail
      if (!empty($DBInfo->use_convert_thumbs) and !empty($use_thumb)) {
        $thumb_width=$thumb['thumbwidth'] ? $thumb['thumbwidth']:150;
        if (!file_exists($dir."/thumbnails/".$_l_file)) {
          if (!file_exists($dir."/thumbnails")) @mkdir($dir."/thumbnails",0777);
          if (function_exists('gd_info')) {
            $fname=$dir.'/'.$_l_file;
            list($w, $h) = getimagesize($fname);
            //print $w.'x'.$h;
            if ($w > $thumb_width) {
              $nh=intval($thumb_width*$h/$w);
              $thumb= imagecreatetruecolor($thumb_width,$nh);
              if (preg_match("/\.(jpg|jpeg)$/i",$file))
                $imgtype= 'jpeg';
              else if (preg_match("/\.png$/i",$file))
                $imgtype= 'png';
              else if (preg_match("/\.gif$/i",$file))
                $imgtype= 'gif';

              $myfunc='imagecreatefrom'.$imgtype;
              $source= $myfunc($fname);
              //imagecopyresized($thumb, $source, 0,0,0,0, $thumb_width, $nh, $w, $h);
              imagecopyresampled($thumb, $source, 0,0,0,0, $thumb_width, $nh, $w, $h);
              $myfunc='image'.$imgtype;
              $myfunc($thumb, $dir.'/thumbnails/'.$_l_file);
            }
          } else {
            $fp=popen("convert -scale ".$thumb_width." ".$dir."/".$_l_file." ".
              $dir."/thumbnails/".$_l_file.
            $formatter->NULL,'r');
            @pclose($fp);
          }
        }
      }

      $alt=!empty($alt) ? $alt:$file;
      if ($key != $pagename || !empty($force_download)) {
        $val=_urlencode($value);
        if (!empty($use_thumb)) {
          $thumbdir='thumbnails/';
          if (($p=strrpos($val,'/')) !== false)
            $val=substr($val,0,$p).'/thumbnails'.substr($val,$p);
          $extra_action='download';
        }
        $url=$formatter->link_url(_urlencode($pagename),"?action=$mydownload&amp;value=".$val);
      } else {
        if (!empty($use_thumb)) {
          $url=$DBInfo->upload_dir_url.'/thumbnails/'._urlencode($_l_file);
        } else {
          $_my_file=str_replace($DBInfo->upload_dir, $DBInfo->upload_dir_url,$dir . '/' . $file);
          $url=_urlencode($_my_file);
        }
      }

      $img="<img src='$url' title='$alt' alt='$alt' style='border:0' $attr/>";

      if ($extra_action) {
        $url=$formatter->link_url(_urlencode($pagename),"?action=$extra_action&amp;value=".urlencode($value));
        $img="<a href='$url'>$img</a>";
      } else if (preg_match('@^(https?|ftp)://@',$alt))
        $img="<a href='$alt'>$img</a>";

      return $bra.$cap_bra."<div class=\"$imgcls\"$img_width>$img$caption</div>".$cap_ket.$ket;
      #return $bra.$cap_bra."<span class=\"$cls\">$img$caption</span>".$cap_ket.$ket;
    } else {
      $mydownload= $extra_action ? $extra_action:$mydownload;
      $link=$formatter->link_url(_urlencode($pagename),"?action=$mydownload&amp;value=".urlencode($value),$text);
      if (!empty($img_link))
        return $bra."<span class=\"attach\"><a href='$link'>$img_link</a></span>".$ket;

      return $bra."<span class=\"attach\">".$formatter->icon['attach'].'<a href="'.$link.'">'.$text.'</a></span>'.$info.$ket;
    }
  }

  $paste='';
  if (!empty($DBInfo->use_clipmacro) and preg_match('/^(.*)\.png$/i',$file,$m)) {
    $now=time();
    $url=$formatter->link_url($pagename,"?action=clip&amp;value=$m[1]&amp;now=$now");
    $paste=" <a href='$url'>"._("or paste a new png picture")."</a>";
  }
  if (!empty($DBInfo->use_drawmacro) and preg_match('/^(.*)\.gif$/i',$file,$m)) {
    $now=time();
    $url=$formatter->link_url($pagename,"?action=draw&amp;mode=attach&amp;value=$m[1]&amp;now=$now");
    $paste=" <a href='$url'>"._("or draw a new gif picture")."</a>";
  }
  if ($pagename == $formatter->page->name)
    return $bra.'<span class="attach">'.$formatter->link_to("?action=UploadFile&amp;rename=".urlencode($file),sprintf(_("Upload new Attachment \"%s\""),$file)).$paste.'</span>'.$ket;

  if (!$pagename) $pagename='UploadFile';
  return $bra.'<span class="attach">'.$formatter->link_tag($pagename,"?action=UploadFile&amp;rename=".urlencode($file),sprintf(_("Upload new Attachment \"%s\" on the \"%s\""),$file, $pagename)).$paste.'</span>'.$ket;
}

// vim:et:sts=2:
?>
