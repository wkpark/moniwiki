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
// Param: thumb_width=320; # default thumb width
// Param: use_convert_thumbs; # automatic generate thumb
// Param: force_download=0; # always use download action
// Param: download_action=''; # custom download action
// Param: thumb_widths=array() or array('320', '480', ... # allowed thumb widths
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
  if (!empty($DBInfo->force_download) or !empty($DBInfo->pull_url)) $force_download=1;
  if (!empty($DBInfo->download_action)) $mydownload=$DBInfo->download_action;
  else $mydownload='download';
  $extra_action='';

  $pull_url = $fetch_url = '';
  if (!empty($DBInfo->pull_url)) {
    $pull_url = $DBInfo->pull_url;
    if (empty($formatter->fetch_action))
      $fetch_url = $formatter->link_url('', '?action=fetch&url=');
    else
      $fetch_url = $formatter->fetch_action;
  }

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
  $imgalign = '';

  // allowed thumb widths.
  $thumb_widths = isset($DBInfo->thumb_widths) ? $DBInfo->thumb_widths :
      array('120', '240', '320', '480', '600', '800', '1024');

  // parse query string of macro arguments
  if (($dummy=strpos($value,'?'))) {
    # for attachment: syntax
    parse_str(substr($value,$dummy+1),$attrs);
    $value=substr($value,0,$dummy);
  } else if (($dummy = strpos($value, ',')) !== false) {
    # for Attachment macro
    $tmp = substr($value, $dummy+1);
    $tmp = preg_replace('/,+\s*/', ',', $tmp);
    $tmp = preg_replace('/\s*=\s*/', '=', $tmp);
    $tmp = str_replace(',', '&', $tmp);
    parse_str($tmp, $attrs);
    $value=substr($value,0,$dummy);
  }

  if (!empty($attrs)) {
    if (!empty($attrs['action'])) {
      // check extra_action
      if ($attrs['action'] == 'deletefile') $extra_action = $attrs['action'];
      else $mydownload = $attrs['action'];
      unset($attrs['action']);
    }
    foreach ($attrs as $k=>$v) {
      if (in_array($k, array('width', 'height'))) {
        $attr.= "$k=\"$v\" ";
        if (!empty($DBInfo->use_lightbox))
          $lightbox_attr = ' rel="lightbox" ';
      } else if ($k == 'align') {
        $imgalign = 'img'.ucfirst($v);
      } else if (in_array($k, array('caption', 'alt', 'title'))) {
        $caption = preg_replace("/^([\"'])([^\\1]+)\\1$/", "\\2", $v);
        $caption = trim($caption);
      } else if (in_array($k, array('thumb', 'thumbwidth', 'thumbheight'))){
        if ($k == 'thumbwidth' || $k == 'thumbheight') {
          if (!empty($thumb_widths)) {
            if (in_array($v, $thumb_widths))
              $thumb[$k] = $v;
          } else {
            $thumb[$k] = $v;
          }
        } else {
          $thumb[$k] = $v;
        }
      }
    }
    if (!empty($thumb)) $use_thumb = true;
  }

  if (preg_match('/^data:image\/(png|jpg|jpeg);base64,/',$value)) {
    // need to hack for IE ?
    return "<img src='".$value."' $attr />";
  }

  $attr.=$lightbox_attr;
  $info = '';

  if (($p=strpos($value,':')) !== false or ($p=strrpos($value,'/')) !== false) {
    $subpage=substr($value,0,$p);
    $file=substr($value,$p+1);
    $value=$subpage.'/'.$file; # normalize page arg
    if (isset($subpage[0])) {
      $pagename=$subpage;
      $key=$DBInfo->pageToKeyname($subpage);
      $value=$file;
    } else {
      $pagename='';
      $key='';
    }
  } else {
    $pagename=$formatter->page->name;
    $key=$DBInfo->pageToKeyname($formatter->page->name);
    $file=$value;
  }

  if (isset($key[0])) {
    $dir = $DBInfo->upload_dir.'/'.$key;
    // support hashed upload_dir
    if (!is_dir($dir) and !empty($DBInfo->use_hashed_upload_dir)) {
      $pre = get_hashed_prefix($key);
      $dir = $DBInfo->upload_dir.'/'.$pre.$key;
      if (!is_dir($dir))
        $dir = $DBInfo->upload_dir;
    }
  } else {
    $dir = $DBInfo->upload_dir;
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
  } else if (!empty($pull_url)) {
    if (isset($subpage[0])) {
      $pagename = $subpage;
      $val = _urlencode($file);
    } else {
      $val = _urlencode($value);
    }

    $url = $pull_url._urlencode($pagename).
        "?action=$mydownload&value=".$val;

    $hsz = $formatter->macro_repl('ImageFileSize', $url);
    $info = ' ('.$hsz.')';

    $url = $fetch_url.
        str_replace(array('&', '?'), array('%26', '%3f'), $url);
    // check url to retrieve the size of file
    if (empty($formatter->preview) or floatval($sz) != 0)
      $file_ok = 2;
  }
  if (empty($file_ok) and !empty($formatter->wikimarkup) and empty($options['nomarkup'])) {
    if (!empty($DBInfo->swfupload_depth) and $DBInfo->swfupload_depth > 2) {
      $depth=$DBInfo->swfupload_depth;
    } else {
      $depth=2;
    }

    if (session_id() == '') { // ip based
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
      if ($file_ok == 1 and !$attrs['width']) {
        $size=getimagesize($_l_upload_file); // XXX
        $attrs['width']=$size[0];
      }
    }

    $img_width='';
    if (!empty($attrs['width'])) $img_width=' style="width:'.$attrs['width'].'px"';

    if ($caption) {
      $cls=$imgalign ? 'imgContainer '.$imgalign:'imgContainer'; 
      $cap_bra='<div class="'.$cls.'"'.'>';
      $cap_ket='</div>';
      $img_width='';
    } else {
      $imgcls=$imgalign ? 'imgAttach '.$imgalign:'imgAttach';
    }

    if ($file_ok == 1) {
      $sz=filesize($_l_upload_file);
      $unit=array('Bytes','KB','MB','GB','TB');
      for ($i=0;$i<4;$i++) {
        if ($sz <= 1024) {
          break;
        }
        $sz=$sz/1024;
      }
      $info=' ('.round($sz,2).' '.$unit[$i].')';
    }

    if (!in_array('UploadedFiles',$formatter->actions))
      $formatter->actions[]='UploadedFiles';

    if (empty($img_link) && preg_match("/\.(png|gif|jpeg|jpg|bmp)$/i",$upload_file, $m)) {
      // get the extension of the image
      $ext = $m[1];
      $type = strtoupper($m[1]);
      if (!empty($caption))
        $caption = '<div class="caption">'.$caption.' <span>['.$type.' '._("image").$info.']</span></div>';
      else
        $caption = '<div><span>['.$type.' '._("image").$info.']</span></div>';

      if ($file_ok == 1 and !empty($use_thumb)) {
        $thumb_width = !empty($DBInfo->thumb_width) ? $DBInfo->thumb_width : 320;
        if (!empty($thumb['thumbwidth']))
          $thumb_width = $thumb['thumbwidth'];

        // guess thumbnails
        $thumbfiles = array();
        $thumbfiles[] = $_l_file;
        $thumbfiles[] = preg_replace('@'.$ext.'$@', 'w'.$thumb_width.'.'.$ext, $_l_file);

        $thumb_ok = false;
        foreach ($thumbfiles as $thumbfile) {
          if (file_exists($dir.'/thumbnails/'.$thumbfile)) {
            $thumb_ok = true;
            break;
          }
        }

        // auto generate thumbnail
        if (!empty($DBInfo->use_convert_thumbs) and !$thumb_ok) {
          if (!file_exists($dir."/thumbnails")) @mkdir($dir."/thumbnails",0777);

          $fname=$dir.'/'.$_l_file;
          list($w, $h) = getimagesize($fname);

          // generate thumbnail using the gd func or the ImageMagick(convert)
          if ($w > $thumb_width) {
            require_once('lib/mediautils.php');
            resize_image($ext, $fname, $dir.'/thumbnails/'.$thumbfile, $w, $h, $thumb_width);
            $thumb_ok = true;
          }
        }
      }

      $alt=!empty($alt) ? $alt:$file;
      if ($key != $pagename || !empty($force_download)) {
        $val=_urlencode($value);
        if ($thumb_ok and !empty($use_thumb)) {
          if (($p=strrpos($val,'/')) !== false)
            $val=substr($val,0,$p).'/thumbnails'.substr($val,$p);
          else
            $val = 'thumbnails/'.$thumbfile;
          $extra_action='download';
        }
        if ($file_ok == 2 and !empty($pull_url)) {
          if (isset($subpage[0])) {
            $pagename = $subpage;
            $val = _urlencode($file);
          }
          $url = $fetch_url.str_replace(array('&', '?'), array('%26', '%3f'),
                  $pull_url.urlencode(_urlencode($pagename))."?action=$mydownload&value=".$val);
          if ($use_thumb and isset($thumb['thumb']))
            $url.='&thumb='.$thumb['thumb'];
        } else {
          $url = $formatter->link_url(_urlencode($pagename),"?action=$mydownload&amp;value=".$val);
        }
      } else {
        if ($thumb_ok and !empty($use_thumb)) {
          $url=$DBInfo->upload_dir_url.$dir.'/thumbnails/'._urlencode($thumbfile);
        } else {
          $_my_file=str_replace($DBInfo->upload_dir, $DBInfo->upload_dir_url,$dir . '/' . $file);
          $url=_urlencode($_my_file);
        }
      }

      $img="<img src='$url' title='$alt' alt='$alt' style='border:0' $attr/>";

      if ($extra_action) {
        $url=$formatter->link_url(_urlencode($pagename),"?action=$extra_action&amp;value=".urlencode($value));
        if ($file_ok == 2 and !empty($pull_url)) {
          if (isset($subpage[0])) $pagename = $subpage;
          $url = $fetch_url.str_replace(array('&', '?'), array('%26', '%3f'),
                  $pull_url.urlencode(_urlencode($pagename))."?action=$mydownload&value=".$val);
        }
        $img="<a href='$url'>$img</a>";
      } else if (preg_match('@^(https?|ftp)://@',$alt))
        $img="<a href='$alt'>$img</a>";

      return $bra.$cap_bra."<div class=\"$imgcls\"><div>$img$caption</div></div>".$cap_ket.$ket;
      #return $bra.$cap_bra."<span class=\"$cls\">$img$caption</span>".$cap_ket.$ket;
    } else {
      $mydownload= $extra_action ? $extra_action:$mydownload;
      $link=$formatter->link_url(_urlencode($pagename),"?action=$mydownload&amp;value=".urlencode($value),$text);
      if (!empty($img_link))
        return $bra."<span class=\"attach\"><a href='$link'>$img_link</a></span>".$ket;

      return $bra."<span class=\"attach\">".$formatter->icon['attach'].'<a href="'.$link.'">'.$text.'</a></span>'.$info.$ket;
    }
  }

  // no attached file found.
  $formatter->_dynamic_macros['@Attachment'] = 1;

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
