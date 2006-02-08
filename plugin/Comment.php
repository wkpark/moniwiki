<?php
// Copyright 2004-2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Wiki comment plugin for the MoniWiki
//
// Usage: [[Comment]], ?action=comment
//
//
// $Id$

function macro_Comment($formatter,$value,$options=array()) {
  global $DBInfo;
  if (!$options['page']) $options['page']=$formatter->page->name;

  if ($value) {
    $args=explode(',',$value);
    if (in_array('usemeta',$args)) $use_meta=1;
  }

  if ($options['usemeta'] or $use_meta) {
    $hidden="<input type='hidden' name='usemeta' value=1 />\n";
  }

  if ($options['nocomment']) return '';
  #if (!$DBInfo->_isWritable($options['page'])) return '';
  if (!$DBInfo->security->writable($options)) return '';

  $COLS_MSIE = 80;
  $COLS_OTHER = 85;
  $cols = preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT']) ? $COLS_MSIE : $COLS_OTHER;

  $rows=$options['rows'] > 5 ? $options['rows']: 5;
  $cols=$options['cols'] > 60 ? $options['cols']: $cols;

  if ($options['datestamp'])
    $datestamp= $options['datestamp'];
  else
    $datestamp= $formatter->page->mtime();
  $savetext=$options['savetext'];
  $savetext= str_replace(array("&","<"),array("&amp;","&lt;"),$savetext);

  if (!$options['id']) {
    $user=new User(); # get from COOKIE VARS
    $options['id']=$user->id;
  }

  $url=$formatter->link_url($formatter->page->urlname);


  if ($value)
    $hidden.='<input type="hidden" name="comment_id" value="'.$value.'" />';
  $form = "<form name='editform' method='post' action='$url'>\n";
  $form.= <<<FORM
<textarea class="wiki" name="savetext"
 rows="$rows" cols="$cols">$savetext</textarea><br />
FORM;
  if ($options['id'] == 'Anonymous')
    $sig=_("Username").": <input name='name' value='$options[name]' />";
  else if (!$use_meta)
    $sig="<input name='nosig' type='checkbox' />"._("Don't add a signature");
  $comment=_("Comment");
  $preview=_("Preview");
  $form.= <<<FORM2
$hidden
$sig
<input type="hidden" name="action" value="comment" />
<input type="hidden" name="datestamp" value="$datestamp" />
<input type="submit" value="$comment" />
<input type="submit" name="button_preview" value="$preview" />
</form>
FORM2;

  return '<div class="commentForm">'.$form.'</div>';
}

function do_comment($formatter,$options=array()) {
  global $DBInfo;

  if (!$DBInfo->security->writable($options)) {
    $formatter->preview=1;
    $options['title']=_("Page is not writable");
    do_invalid($formatter,$options);
    return;
  } else if (!$DBInfo->hasPage($options['page'])) {
    $options['err']=_("You are not allowed to add a comment.");
    $options['title']=_("Page does not exists");
    do_invalid($formatter,$options);
    return;
  }

  if ($options['usemeta']) $use_meta=1;

  $COLS_MSIE = 80;
  $COLS_OTHER = 85;
  $cols = preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT']) ? $COLS_MSIE : $COLS_OTHER;

  $rows=$options['rows'] > 5 ? $options['rows']: 8;
  $cols=$options['cols'] > 60 ? $options['cols']: $cols;

  $url=$formatter->link_url($formatter->page->urlname);

  $button_preview=$options['button_preview'];

  if ($options['savetext']) {
    $savetext=_stripslashes($options['savetext']);
    $savetext=str_replace("\r","",$savetext);
    $savetext=rtrim($savetext);
    #$savetext=str_replace("<","&lt;",$savetext);
  }

  if ($savetext and !$button_preview and $DBInfo->spam_filter) {
    $text=$savetext;
    $fts=preg_split('/(\||,)/',$DBInfo->spam_filter);
    foreach ($fts as $ft) {
      $text=$formatter->filter_repl($ft,$text,$options);
    }
    if ($text != $savetext) {
      $button_preview=1;
      $options['msg'] = _("Sorry, can not save page because some messages are blocked in this wiki.");
    }
  }
  if ($button_preview && $options['savetext']) {
    $formatter->send_header("",$options);
    $formatter->send_title(_("Preview comment"),"",$options);
    $formatter->send_page($savetext."\n----");
    $options['savetext']=$savetext;
    print macro_Comment($formatter,'',$options);
    print $formatter->macro_repl('EditHints');
    $formatter->send_footer("",$options);
    return;
  } else if (!$savetext) {
    $formatter->send_header("",$options);
    $formatter->send_title(_("Add comment"),"",$options);
    print macro_Comment($formatter,'',$options);
    print $formatter->macro_repl('EditHints');
    $formatter->send_footer("",$options);
    return;
  }

  $datestamp= $options['datestamp'];
  if ($formatter->page->mtime() > $datestamp) {
    $options['msg']='';
    $formatter->send_header('',$options);
    $formatter->send_title(_("Error: Don't make a clone!"),'',$options);
    $formatter->send_footer('',$options);
    return;
  }

  $body=$formatter->page->get_raw_body();

  if ($options['id']=='Anonymous')
    $id=$options['name'] ?
      _stripslashes($options['name']):$_SERVER['REMOTE_ADDR'];
  else $id=$options['id'];

  if ($use_meta) {
    $date=gmdate('Y-m-d H:i:s').' GMT';

    $idx=1;
    if (preg_match_all('/-{4,}\nComment-Id:\s*(\d+)\n/m',$body,$m)) {
      $idx=$m[1][sizeof($m[1])-1]+1;
    }

    if ($options['id']!='Anonymous') $id='@USERNAME@';
    $meta=<<<META
Comment-Id: $idx
From: $id
Date: $date
META;
    $savetext="----\n$meta\n\n$savetext\n";
  } else {
    if ($options['nosig']) $savetext="----\n$savetext\n";
    else if($options['id']=='Anonymous')
      $savetext="----\n$savetext -- $id @DATE@\n";
    else
      $savetext="----\n$savetext @SIG@\n";
  }

  if ($options['comment_id'] and preg_match("/^\[\[Comment\(".$options['comment_id']."\)\]\]/m",$body)) {
    $str="[[Comment($options[comment_id])]]";
    $body= preg_replace('/'.preg_quote($str).'/',$savetext.$str,$body,1);
  } else if (preg_match("/\n##Comment\n/i",$body)) {
    $body= preg_replace("/\n##Comment\n/i","\n##Comment\n$savetext",$body,1);
  } else if (preg_match("/^\[\[Comment(\([^\)]*\))?\]\]/m",$body)) {
    $body= preg_replace("/^(\[\[Comment(\([^\)]*\))?\]\])/m",$savetext."\\1",$body,1);
  } else
    $body.=$savetext;

  $formatter->page->write($body);
  $DBInfo->savePage($formatter->page,"Comment added",$options);
  $options['msg']=sprintf(_("%s is commented successfully"),$formatter->link_tag($formatter->page->urlname,"?action=show",$options['page']));
  $title=_("Comment added successfully");

  $formatter->send_header('',$options);
  $formatter->send_title($title,'',$options);

  $opt['pagelinks']=1;
  # re-generates pagelinks
  $formatter->send_page('',$opt);
  $formatter->send_footer('',$options);

  return;
}

// vim:et:sts=2:sw=2
?>
