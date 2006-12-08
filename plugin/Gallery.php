<?php
// Copyright 2003-2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// Gallery plugin for the MoniWiki
//
// Usage: [[Gallery]]
//
// $Id$

function get_pagelist($formatter,$pages,$action,$curpage=1,$listcount=10,$bra="[",$cat="]",$sep="|",$prev="&#171;",$next="&#187;",$first="",$last="",$ellip="...") {

  if ($curpage >=0)
    if ($curpage > $pages)
      $curpage=$pages;
  if ($curpage <= 0)
    $curpage=1;

  $startpage=intval(($curpage-1) / $listcount)*$listcount +1;

  $pnut="";
  if ($startpage > 1) {
    $prevref=$startpage-1;
    if (!$first) {
      $prev_l=$formatter->link_tag('',$action.$prevref,$prev);
      $prev_1=$formatter->link_tag('',$action."1","1");
      $pnut="$prev_l".$bra.$prev_1.$cat.$ellip.$bar;
    }
  } else {
    $pnut=$prev.$bra."";
  }

  for ($i=$startpage;$i < ($startpage + $listcount) && $i <=$pages; $i++) {
    if ($i != $startpage)
      $pnut.=$sep;
    if ($i != $curpage) {
      $link=$formatter->link_tag('',$action.$i,$i);
      $pnut.=$link;
    } else
      $pnut.="<b>$i</b>";
  }

  if ($i <= $pages) {
    if (!$last) {
      $next_l=$formatter->link_tag('',$action.$pages,$pages);
      $next_i=$formatter->link_tag('',$action.$i,$next);

      $pnut.=$cat.$ellip.$bra.$next_l.$cat.$next_i;
    }
  } else {
    $pnut.="".$cat.$next;
  }
  return $pnut;
}

function macro_Gallery($formatter,$value,&$options) {
  global $DBInfo;

  # add some actions at the bottom of the page
  if (!$value and !in_array('UploadFile',$formatter->actions)) {
    $formatter->actions[]='UploadFile';
    $formatter->actions[]='UploadedFiles';
  }
  $default_column=3;
  $default_row=4;
  $col=$options['col'] > 0 ? (int)$options['col']:0;
  $row=$options['row'] > 0 ? (int)$options['row']:0;
  $sort=$options['sort'] ? $options['sort']:'';
  $nocomment=$options['nocomment'] ? $options['nocomment']:'';

  if ($DBInfo->gallery_use_lightbox and $DBInfo->use_lightbox) {
    $use_lightbox=1;
    $href_attr=' rel="lightbox[gallery]" ';
  }

  // parse args
  preg_match("/^(('|\")([^\\2]+)\\2)?,?(\s*,?\s*.*)?$/",
    $value,$match);
  $opts=explode(',',$match[4]);
  foreach ($opts as $opt) {
    if ($opt == 'showall') $show_all=1;
    else if ($opt=='nocomment') $nocomment=1;
    else if (($p=strpos($opt,'='))!==false) {
      $k=substr($opt,0,$p);
      $v=substr($opt,$p+1);
      if ($k=='col') $col=$v;
      else if ($k=='row') $row=$v;
      else if ($k=='sort') $sort=$v;
    } else {
      if ($opt=='sort') $sort=1;
    }
  }

  if (!in_array($sort,array(0,1,'name','date'))) {
    $sort=0;
  }

  $img_default_width=150;
  if ($col > 1) {
    $col_td_width=(int) (100/$col);
    $col_td_width=' width="'.$col_td_width.'%"';
    $img_default_width=(int) (100/$col)*5; // XXX assume 500px
  }

  $default_width=$DBInfo->gallery_img_width ? $DBInfo->gallery_img_width:600;
  $img_class="gallery-img";

  $col=($col<=0 or $col>10) ? $default_column:$col;
  $row=($row<=0 or $row>7) ? $default_row:$row;
  $perpage=$col*$row;

  if ($col == 1) $img_style=' style="float:left"';

  if ($match[3])
    # arg has a pagename
    $value=$match[3];
  else
    $value=$formatter->page->name;

  $key=$DBInfo->pageToKeyname($value);
  if ($key != $value)
    $prefix=$formatter->link_url(_rawurlencode($value),"?action=download&amp;value=");
  $dir=$DBInfo->upload_dir."/$key";
  if (!$prefix) $prefix=$DBInfo->url_prefix."/".$dir."/";

  if (!file_exists($dir)) {
    umask(000);
    mkdir($dir,0777);
  }

  $upfiles=array();
  $comments=array();
  if (file_exists($dir."/list.txt")) {
    $cache=file($dir."/list.txt");
    foreach ($cache as $line) {
      list($name,$mtime,$comment)=explode("\t",rtrim($line),3);
      $upfiles[$name]=$mtime;
      $comments[$name]=$comment;
    }
  }
  if ($sort) {
    if ($sort ==1) {
     arsort($upfiles);
    } elseif ($sort=='name') {
     ksort($upfiles);
    }
  }
  else asort($upfiles);

  if ($options['value'])
    $file=urldecode($options['value']);

  if ($file and $upfiles[$file] and $options['comments']) {
    // admin: edit all comments
    $comment=_stripslashes($options['comments']);
    $comment=str_replace("<","&lt;",$comment);
    $comment=str_replace("\r","",$comment);
    $comment=preg_replace("/\n----\n/","\t",$comment);
    $comment=str_replace("\n","\\n",$comment);
    $comments[$file]=$comment;
    $update=1;
  } else if ($file and $upfiles[$file] and $options['comment']) {
    // add new comment
    $comment=$text=_stripslashes($options['comment']);

    // spam filtering
    $fts=preg_split('/(\||,)/',$DBInfo->spam_filter);
    foreach ($fts as $ft) {
      $text=$formatter->filter_repl($ft,$text,$options);
    }
    if ($text != $comment) {
      $options['err'] = _("Sorry, can not save page because some messages are blocked in this wiki.");
    } else {
      if ($options['id']=='Anonymous') $name=$_SERVER['REMOTE_ADDR'];
      else $name=$options['id'];
      if ($options['name']) $name=$options['name'];
      $date=date("(Y-m-d H:i:s) ");

      $comment=str_replace("\r","",$comment);
      $comment=str_replace("\n","\\n",$comment);
      $comment=str_replace("\t"," ",$comment);
      $comment=str_replace("<","&lt;",$comment);
      $comment.=" -- $name $date";
      $comments[$file]=$comment."\t".$comments[$file];
      $update=1;
    }
  } else if ($file and $upfiles[$file]) {
    // show comments of the selected item
    $mtime=$upfiles[$file];
    $comment=$comments[$file];

    $values=array_keys($upfiles);
    $prev_value=$values[array_search($file,$values)-1];
    $next_value=$values[array_search($file,$values)+1];
    unset($values);

    $upfiles=array();
    $comments=array();
    $upfiles[$file]=$mtime;
    $comments[$file]=$comment;
    $selected=1;
    $img_class="gallery-sel";
    if ($prev_value) {
      $prev_link="<div class='gallery-prev-link'><a href='".$formatter->link_url($formatter->page->urlname,"?action=gallery&amp;value=$prev_value")."'><span class='gallery-prev-text'>&#171;Prev</span></a></div>";
    } else
      $prev_link='';
    if ($next_value) {
      $next_link="<div class='gallery-next-link'><a href='".$formatter->link_url($formatter->page->urlname,"?action=gallery&amp;value=$next_value")."'><span class='gallery-next-text'>Next&#187;</span></a></div>";
    } else
      $next_link='';
    if ($next_link or $prev_link) {
      $top_link="<div class='gallery-top-link'>$prev_link$next_link</div>";
      $bot_link="<div class='gallery-bottom-link'>$prev_link$next_link</div>";
    }
  }
  $width=$selected ? $default_width:$img_default_width;

  $mtime=file_exists($dir."/list.txt") ? filemtime($dir."/list.txt"):0;
  if ((filemtime($dir) > $mtime) or $update) {
    unset($upfiles);

    $handle= opendir($dir);
    $cache='';
    $cr='';
    while ($file= readdir($handle)) {
      if ($file[0]=='.' or $file=='list.txt' or is_dir($dir."/$file")) continue;
      $mtime=filemtime($dir."/".$file);
      $cache.=$cr.$file."\t".$mtime;
      $upfiles[$file]= $mtime;
      if ($comments[$file] != '') $cache.="\t".$comments[$file];
      $cr="\n";
    }
    closedir($handle);
    $fp=@fopen($dir."/list.txt",'w');
    if ($fp) {
      fwrite($fp,$cache);
      fclose($fp);
    }
  }

  if (!$upfiles) return "<h3>"._("No files found")."</h3>\n";

  $out.="<table width='100%' border='0' cellpadding='2'>\n<tr>\n";
  $idx=1;

  $pages= intval(sizeof($upfiles) / $perpage);
  if (sizeof($upfiles) % $perpage)
    $pages++;

  if ($options['p'] > 1) {
    $slice_index=$perpage*(intval($options['p'] - 1));
    $upfiles=array_slice($upfiles,$slice_index);
  }

  $extra=$sort ? "&amp;sort=".$sort:'';
  $extra.=$nocomment ? "&amp;nocomment=1":'';

  if ($pages > 1)
    $pnut=get_pagelist($formatter,$pages,
      '?action=gallery&amp;col='.$col.'&amp;row='.$row.$extra.
      '&amp;p=',$options['p'],$perpage);

  if (!file_exists($dir."/thumbnails")) @mkdir($dir."/thumbnails",0777);

  while (list($file,$mtime) = each ($upfiles)) {
    $size=filesize($dir."/".$file);
    $id=rawurlencode($file);
    $linksrc=($key == $value) ? $prefix.$id:
      str_replace('value=','value='.$id,$prefix);
    $link=($selected or $use_lightbox) ? $linksrc:$formatter->link_url($formatter->page->urlname,"?action=gallery$extra&amp;value=$id");
    $date=date("Y-m-d",$mtime);
    if (preg_match("/\.(jpg|jpeg|gif|png)$/i",$file)) {
      if ($DBInfo->use_convert_thumbs and !file_exists($dir."/thumbnails/".$file)) {
        if (function_exists('gd_info')) {
          $fname=$dir.'/'.$file;
          list($w, $h) = getimagesize($fname);
          if ($w > $width) {
            $nh=$width*$h/$w;
            $thumb= imagecreatetruecolor($width,$nh);
            // XXX only jpeg for testing now.
            if (preg_match("/\.(jpg|jpeg)$/i",$file))
              $imgtype= 'jpeg';
            else if (preg_match("/\.png$/i",$file))
              $imgtype= 'png';
            else if (preg_match("/\.gif$/i",$file))
              $imgtype= 'gif';

            $myfunc='imagecreatefrom'.$imgtype;
            $source= $myfunc($fname);
            imagecopyresized($thumb, $source, 0,0,0,0, $width, $nh, $w, $h);
            $myfunc='image'.$imgtype;
            $myfunc($thumb, $dir.'/thumbnails/'.$file);
          }
        } else {
          $fp=popen("convert -scale ".$width." ".$dir."/".$file." ".$dir."/thumbnails/".$file.
          $formatter->NULL,'r');
          @pclose($fp);
        }
      }
      if (!$selected and file_exists($dir."/thumbnails/".$file)) {
        $thumb=($key == $value) ? $prefix.'thumbnails/'.$id:
          str_replace('value=','value=thumbnails/'.$id,$prefix);
        $object="<img class='imgGallery' src='$thumb' alt='$file' />";
      } else {
        $nwidth=$width;
        if (function_exists('getimagesize')) {
          list($nwidth, $height, $type, $attr) = getimagesize($dir.'/'.$file);
          $nwidth=($nwidth > $width) ? $width:$nwidth;
        }
        $object="<img class='imgGallery' src='$linksrc' width='$nwidth' alt='$file' />";
      }
    }
    else
      $object=$file;

    $unit=array('Bytes','KB','MB','GB','TB');
    $i=0;
    for (;$i<4;$i++) {
      if ($size <= 1024) {
        $size= round($size,2).' '.$unit[$i];
        break;
      }
      $size=$size/1024;
    }
#    $size=round($size,2).' '.$unit[$i];

    $comment='';
    $comment_btn='';
    $comment_btn=$nocomment ? '':_("add comment");
    $imginfo=(!$nocomment or $selected) ? "$date ($size) ":'';
    if ($comments[$file] != '' and $options['value']) {
      $comment=$comments[$file];
      $comment=str_replace("\\n","\n",$comment);
      $options['comments']=str_replace("\t","\n----\n",$comment);
      $comment=str_replace("\t","<div class='separator'><hr /></div>",$comment);
      $comment=str_replace("\n","<br/>\n",$comment);
    } else if ((!$nocomment or $selected) and !empty($comments[$file])) {
      if (empty($show_all)) {
        $comment_btn=_("show comments");
        list($comment,$dum)=explode("\t",$comments[$file],2);
      } else {
        $comment_btn=_("add comment");
        $comment=str_replace("\t","<div class='separator'><hr /></div>\n",$comments[$file]);
      }
      $comment=str_replace("\\n","<br/>\n",$comment);
    }
    $out.="<td $col_td_width align='center' valign='top'>$top_link<div class='$img_class' $img_style><a href='$link'$href_attr>$object</a><br />".$imginfo;
    if ($comment_btn)
      $out.='['.$formatter->link_tag($formatter->page->urlname,"?action=gallery&amp;value=$id",$comment_btn)."]<br />\n";
    $out.='</div>'.$bot_link;
    if ($comment) $out.="<div class='gallery-comments' $comment_style>$comment</div>";
    $out.="</td>\n";
    if ($idx % $col == 0) $out.="</tr>\n<tr>\n";
    $idx++;
    if ($idx > $perpage) break;
  }
  $idx--;
  $out.="</tr></table>\n";

  return $pnut.'<div class="gallery">'.$out.'</div>'.$pnut;
}

function do_gallery($formatter,$options='') {
  global $DBInfo;
  $cols = preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT']) ? $COLS_MSIE : $COLS_OTHER;
                                                                                
  $rows=$options['rows'] > 5 ? $options['rows']: 4;
  $cols=$options['cols'] > 60 ? $options['cols']: $cols;

  if ($options['comments'] and !$DBInfo->security->is_valid_password($options['passwd'],$options)) {
    $title= sprintf('Invalid password !');
    $formatter->send_header("",$options);
    $formatter->send_title($title);
    $formatter->send_footer();
    return;
  }

  $ret=macro_Gallery($formatter,'',$options);

  if (isset($options['passwd']) and $options['comments']) {
    $options['msg']=sprintf(_("Go back or return to %s"),$formatter->link_tag($formatter->page->urlname,"",$options['page']));
    $options['title']=_("Comments are edited");
  } else if ($options['comment']) {
    if (!$options['err']) {
      $options['msg']=sprintf(_("Go back or return to %s"),$formatter->link_tag($formatter->page->urlname,"",$options['page']));
      $options['title']=_("Comments is added");
    } else
      $options['msg']=&$options['err'];
  }

  if (!$options['value']) {
    $formatter->send_header("",$options);
    $formatter->send_title("","",$options);
    print $ret;
  } else
  if ($options['comment'] or ($options['comments'] and $options['passwd'])) {
    $myrefresh='';
    if (!$options['err'] and $DBInfo->use_save_refresh) {
      $sec=$DBInfo->use_save_refresh;
      $lnk=$formatter->link_url($formatter->page->urlname,"?action=show");
      $myrefresh='Refresh: '.$sec.'; url='.qualifiedURL($lnk);
    }
    $formatter->send_header($myrefresh,$options);
    $formatter->send_title("","",$options);
    #$formatter->send_page('',$options);
  } else
  if ($options['comments'] and $options['admin'] and !$options['passwd']) {
    // admin form
    $rows+=5;
    $formatter->send_header("",$options);
    $formatter->send_title("","",$options);
    print $ret;
    $url=$formatter->link_url($formatter->page->urlname);
    $form = "<form method='post' action='$url'>\n";
    $form.= <<<FORM
<textarea class="wiki" id="content" wrap="virtual" name="comments"
 rows="$rows" cols="$cols" class="wiki">
FORM;
    $form.=$options['comments'];
    $form.='</textarea><br />';
    $form.= <<<FORM2
<input type="hidden" name="action" value="gallery" />
<input type="hidden" name="value" value="$options[value]" />
password: <input type='password' name='passwd' />
<input type="submit" value="Save" />&nbsp;
<input type="reset" value="Reset" />&nbsp;
</form>
FORM2;
    print $form;
  } else if (!$options['comment']) {
    // add comment form
    $formatter->send_header("",$options);
    $formatter->send_title("","",$options);
    print $ret;
    $url=$formatter->link_url($formatter->page->urlname);
                                                                                
    $form = "<form method='post' action='$url'>\n";
    $form.= "<input name='admin' type='submit' value='Admin' /><br />\n";
    $form.= "<b>Name or Email</b>: <input name='name' size='30' maxlength='30' style='width:200' /><br />\n";
    $form.= <<<FORM
<textarea class="wiki" id="content" wrap="virtual" name="comment"
 rows="$rows" cols="$cols" class="wiki"></textarea><br />
FORM;
    $form.= <<<FORM2
<input type="hidden" name="action" value="gallery" />
<input type="hidden" name="value" value="$options[value]" />
<input type="submit" value="Save" />&nbsp;
<input type="reset" value="Reset" />&nbsp;
</form>
FORM2;
    print $form;
  }

  $formatter->send_footer("",$options);
  return;
}

// vim:et:sts=2:
?>
