<?php
// Copyright 2004-2014 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Wiki comment plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Since: 2004-08-16
// Modified: 2014/02/22 19:33:00 $
// Name: Comment plugin
// Description: Comment Plugin
// URL: MoniWiki:CommentPlugin
// Version: $Revision: 1.41 $
// License: GPL
// Usage: [[Comment]], ?action=comment
//
// $Id: Comment.php,v 1.41 2010/09/08 15:46:09 wkpark Exp $

function macro_Comment($formatter,$value,$options=array()) {
  global $DBInfo;

  if (!empty($options['nocomment'])) return '';

  // set as dynamic macro or not.
  if ($formatter->_macrocache and empty($options['call']))
    return $formatter->macro_cache_repl('Comment', $value);

  $user=$DBInfo->user; # get from COOKIE VARS
  $options['id']=$user->id;

  $use_any=0;
  if (!empty($DBInfo->use_textbrowsers)) {
    if (is_string($DBInfo->use_textbrowsers))
      $use_any= preg_match('/'.$DBInfo->use_textbrowsers.'/',
        $_SERVER['HTTP_USER_AGENT']) ? 1:0;
    else
      $use_any= preg_match('/Lynx|w3m|links/',
        $_SERVER['HTTP_USER_AGENT']) ? 1:0;
  }
  $captcha='';
  if (empty($use_any) and !empty($DBInfo->use_ticket) and $options['id'] == 'Anonymous') {
     $seed=md5(base64_encode(time()));
     $ticketimg=$formatter->link_url($formatter->page->urlname,'?action=ticket&amp;__seed='.$seed);
     $captcha=<<<EXTRA
  <div class='captcha'><span class='captchaImg'><img src="$ticketimg" alt="captcha" /></span><input type="text" size="10" name="check" />
<input type="hidden" name="__seed" value="$seed" /></div>
EXTRA;
  }

  $hidden = '';
  if (empty($options['page'])) $options['page']=$formatter->page->name;
  if (empty($options['action']) || $options['action'] == 'show') $action='comment';
  else $action=$options['action'];
  if (!empty($options['mode']))
    $hidden.="<input type='hidden' name='mode' value='".$options['mode']."' />\n";
  if (!empty($options['no']))
    $hidden.="<input type='hidden' name='no' value='".$options['no']."' />\n";
  if (!empty($options['p']))
    $hidden.="<input type='hidden' name='p' value='".$options['p']."' />\n";

  if ($value) {
    $args=explode(',',$value);
    if (in_array('usemeta',$args)) $use_meta=1;
    if (in_array('oneliner',$args)) $oneliner=1;
  }

  if (!empty($options['usemeta']) or !empty($use_meta)) {
    $hidden.="<input type='hidden' name='usemeta' value='1' />\n";
  }
  if (!$DBInfo->security->writable($options)) return '';

  if (!empty($options['mid'])) $mymid=$options['mid'];
  else $mymid=$formatter->mid;
  $emid=base64_encode($mymid.',Comment,'.$value);

  $mid=$mymid;

  $cols = get_textarea_cols();

  $rows = (!empty($options['rows']) and $options['rows'] > 5) ? $options['rows']: 5;
  $cols = (!empty($options['cols']) and $options['cols'] > 60) ? $options['cols']: $cols;

  if (!empty($options['datestamp']))
    $datestamp= $options['datestamp'];
  else
    $datestamp= $formatter->page->mtime();
  $savetext=!empty($options['savetext']) ? $options['savetext'] : '';
  $savetext= str_replace(array("&","<"),array("&amp;","&lt;"),$savetext);

  $url=$formatter->link_url($formatter->page->urlname);

  if ($emid) $hidden.='<input type="hidden" name="comment_id" value="'.$emid.'" />';
  $form = "<form id='editform' method='post' action='$url'>\n<div>";
  if (!empty($use_meta))
    $form.="<a id='add_comment' name='add_comment'></a>";

  $comment=_("Comment");
  $preview_btn=_("Preview");
  $preview = '';
  $savetext = _html_escape($savetext);
  if (!empty($oneliner)) {
    $form.=<<<FORM
<input class='wiki' size='$cols' name="savetext" value="$savetext" />&nbsp;
FORM;
  } else {
    if (empty($options['nopreview']))
    $preview='<span class="button"><input type="submit" class="button" name="button_preview" value="'.$preview_btn.'" /></span>';
    $form.= <<<FORM
<textarea class="wiki" name="savetext"
 rows="$rows" cols="$cols">$savetext</textarea><br />
FORM;
  }
  $sig = '';
  if ($options['id'] == 'Anonymous') {
    $name = !empty($options['name']) ? $options['name'] : '';
    $name = _html_escape($name);
    $sig=_("Username").": <input name='name' value=\"$name\" size='10' />";
  }
  else if (empty($use_meta))
    $sig="<input name='nosig' type='checkbox' />"._("Don't add a signature");
  $form.= <<<FORM2
$hidden
$captcha
$sig
<input type="hidden" name="action" value="$action" />
<input type="hidden" name="datestamp" value="$datestamp" />
<span class="button"><input type="submit" class="button" value="$comment" /></span>
$preview
</div>
</form>
FORM2;

  return '<div class="commentForm">'.$form.'</div>';
}

function do_comment($formatter,$options=array()) {
  global $DBInfo;

  if (!$DBInfo->security->writable($options)) {
    $formatter->preview=1;
    $options['title']=_("Page is not writable");
    return do_invalid($formatter,$options);
  } else if (!$DBInfo->hasPage($options['page'])) {
    $options['err']=_("You are not allowed to add a comment.");
    $options['title']=_("Page does not exists");
    return do_invalid($formatter,$options);
  }

  if (!empty($options['usemeta'])) $use_meta=1;

  $cols = get_textarea_cols();

  $rows=(!empty($options['rows']) and $options['rows'] > 5) ? $options['rows']: 8;
  $cols=(!empty($options['cols']) and $options['cols'] > 60) ? $options['cols']: $cols;

  $url=$formatter->link_url($formatter->page->urlname);

  $button_preview=!empty($options['button_preview']) ? $options['button_preview'] : 0;


  $use_any=0;
  if (!empty($DBInfo->use_textbrowsers)) {
    if (is_string($DBInfo->use_textbrowsers))
      $use_any= preg_match('/'.$DBInfo->use_textbrowsers.'/',
        $_SERVER['HTTP_USER_AGENT']) ? 1:0;
    else
      $use_any= preg_match('/Lynx|w3m|links/',
        $_SERVER['HTTP_USER_AGENT']) ? 1:0;
  }

  $ok_ticket=0;
  if (empty($use_any) and !empty($DBInfo->use_ticket) and $options['id'] == 'Anonymous') {
    if ($options['__seed'] and $options['check']) {
      $mycheck=getTicket($options['__seed'],$_SERVER['REMOTE_ADDR'],4);
      if ($mycheck==$options['check'])
        $ok_ticket=1;
      else {
        $options['msg']= _("Invalid ticket !");
        $button_preview=1;
      }
    } else {
      if (!$button_preview)
        $options['msg']= _("You need a ticket !");
      $button_preview=1;
    }
  } else {
    $ok_ticket=1;
  }

  if ($options['savetext']) {
    $savetext=_stripslashes($options['savetext']);
    $savetext=str_replace("\r","",$savetext);
    $savetext=rtrim($savetext);
    #$savetext=str_replace("<","&lt;",$savetext);
  }

  if (!empty($savetext) and empty($button_preview) and !empty($DBInfo->spam_filter)) {
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
  if (!empty($button_preview) && !empty($options['savetext'])) {
    if (empty($options['action_mode']) or $options['action_mode'] != 'ajax') {
      $formatter->send_header("",$options);
      $formatter->send_title(_("Preview comment"),"",$options);
      $formatter->send_page($savetext."\n----");
      $options['savetext']=$savetext;
      print macro_Comment($formatter,'',$options);
      print $formatter->macro_repl('EditHints');
      $formatter->send_footer("",$options);
    }
    return false;
  } else if (empty($savetext)) {
    if (empty($options['action_mode']) or $options['action_mode'] != 'ajax') {
      $formatter->send_header("",$options);
      $formatter->send_title(_("Add comment"),"",$options);
      print macro_Comment($formatter,'',$options);
      print $formatter->macro_repl('EditHints');
      $formatter->send_footer("",$options);
    }
    return false;
  }

  $datestamp= $options['datestamp'];
  if ($formatter->page->mtime() > $datestamp) {
    $options['msg']='';
    if (empty($options['action_mode']) or $options['action_mode'] != 'ajax') {
      $formatter->send_header('',$options);
      $formatter->send_title(_("Error: Don't make a clone!"),'',$options);
      $formatter->send_footer('',$options);
    }
    return false;
  }

  $body=$formatter->page->get_raw_body();

  if ($options['id']=='Anonymous')
    $id=$options['name'] ?
      _stripslashes($options['name']):$_SERVER['REMOTE_ADDR'];
  else $id=$options['id'];

  if (!empty($use_meta)) {
    $date=gmdate('Y-m-d H:i:s').' GMT';
    $savetext=rtrim($savetext)."\n";
    $boundary= strtoupper(md5("COMMENT")); # XXX

    $idx=1;
    if (preg_match_all('/-{4}(?:'.$boundary.')?\nComment-Id:\s*(\d+)\n/m',$body,$m)) {
      $idx=$m[1][sizeof($m[1])-1]+1;
    }

    if ($options['id']!='Anonymous') $id='@USERNAME@';
    $meta=<<<META
Comment-Id: $idx
From: $id
Date: $date
META;
    $savetext="----".$boundary."\n$meta\n\n$savetext\n";
  } else {
    if (!empty($options['nosig'])) $savetext="----\n$savetext\n";
    else if($options['id']=='Anonymous')
      $savetext="----\n$savetext -- $id @DATE@\n";
    else
      $savetext="----\n$savetext @SIG@\n";
  }

  while ($options['comment_id']) {
    list($nth,$dum,$v)=explode(',', base64_decode($options['comment_id']),3);

    if ($v) $check='[['.$dum.'('.$v.')]]';
    else $check='[['.$dum.']]';
    if ($v) $check2='<<'.$dum.'('.$v.')>>';
    else $check2='<<'.$dum.'>>';

    if (is_numeric($nth)):

    $raw=str_replace("\n","\1",$body);
    $chunk=preg_split("/({{{.+}}})/U",$raw,-1,PREG_SPLIT_DELIM_CAPTURE); // FIXME

    $nc='';
    $k=1;
    $i=1;
    foreach ($chunk as $c) {
        if ($k%2) {
            $nc.=$c;
        } else {
            $nc.="\7".$i."\7";
            $blocks[$i]=str_replace("\1","\n",$c);
            ++$i;
        }
        $k++;
    }
    $nc=str_replace("\1","\n",$nc);
    if (preg_match_all('/(?!\!)(?:\<\<|\[\[)Comment(?:.*?)(?:\]\]|>>)/', $nc, $m)) {
        if (count($m[0]) == 1) break;
    }
    $chunk=preg_split('/((?!\!)(?:\<\<|\[\[).+(?:\]\]|>>))/U',$nc,-1,PREG_SPLIT_DELIM_CAPTURE);


    $nnc='';
    $ii=1;
    $matched=0;
    for ($j=0,$sz=sizeof($chunk);$j<$sz;++$j) {
        if (($j+1)%2) {
            $nnc.=$chunk[$j];
        } else {
            if ($nth==$ii) {
                $new=$savetext.$chunk[$j];
                if ($check != $chunk[$j] and $check2 != $chunk[$j]) break;
                $nnc.=$new;
                $matched=1;
            }
            else
                $nnc.=$chunk[$j];
            ++$ii;
        }
    }
    if (!empty($blocks)) {
        $formatter->_array_callback($blocks, true);
        $nnc=preg_replace_callback("/\7(\d+)\7/",
            array(&$formatter, '_array_callback'), $nnc);
    }

    endif;

    if (!empty($matched)) $body=$nnc;
    break;
  }
  if (empty($matched)):
  if ($options['comment_id'] and preg_match("/^((?:\[\[|\<\<)Comment\(".$options['comment_id']."\)(?:\]\]|>>))/m",$body, $m)) {
    $str = $m[1];
    $body= preg_replace('/'.preg_quote($str).'/',$savetext.$str,$body,1);
  } else if (preg_match("/\n##Comment\n/i",$body)) {
    $body= preg_replace("/\n##Comment\n/i","\n##Comment\n$savetext",$body,1);
  } else if (preg_match("/^((\[\[|\<\<)Comment(\([^\)]*\))?(\]\]|>>)/m",$body)) {
    $body= preg_replace("/^((\[\[|\<\<)Comment(\([^\)]*\))?(\]\]|>>))/m",$savetext."\\1",$body,1);
  } else
    $body.=$savetext;
  endif;

  $formatter->page->write($body);
  $DBInfo->savePage($formatter->page,"Comment added",$options);
  if ($options['action_mode'] == 'ajax') return true;

  $options['msg']=sprintf(_("%s is commented successfully"),$formatter->link_tag($formatter->page->urlname,"?action=show",$options['page']));
  $title=_("Comment added successfully");

  $myrefresh='';
  if ($DBInfo->use_save_refresh) {
    $sec=$DBInfo->use_save_refresh - 1;
    $lnk=$formatter->link_url($formatter->page->urlname,"?action=show");
    $myrefresh='Refresh: '.$sec.'; url='.qualifiedURL($lnk);
  }
  $formatter->send_header($myrefresh,$options);
  $formatter->send_title($title,'',$options);

  $opt['pagelinks']=1;
  # re-generates pagelinks
  $formatter->send_page('',$opt);
  $formatter->send_footer('',$options);

  return;
}

// vim:et:sts=2:sw=2
?>
