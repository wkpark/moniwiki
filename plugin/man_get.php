<?php
// Copyright 2003-2008 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a man_get action plugin for the MoniWiki
//
// $Id: man_get.php,v 1.10 2008/12/26 05:43:34 wkpark Exp $
// vim:et:sts=2:sw=2

function do_man_get($formatter,$options) {
  global $DBInfo;
  $supported=array('C',
    'bg','de','en','fr','hu','ja','pt','ru','sr',
    'cs','de_DE', 'es','fr_FR','id','ko','nl','pt_BR','sk','sv',
    'da','el','fi','hr','it','pl','ro','sl');

  if (!$options['man']) {
    $options['title']=_("No manpage selected");
    do_invalid($formatter,$options);
    return;
  }

  $LANG='';
  if ($options['lang'] and in_array($options['lang'],$supported))
    $LANG='LANG='.$options['lang'];
  if ($options['sec']!=intval($options['sec'])) unset($options['sec']);
  $cmd=$LANG." man -a -w $options[sec] $options[man]";
  $formatter->errlog();
  $fp=popen(escapeshellcmd($cmd).$formatter->LOG,'r');
  if (is_resource($fp)) {
    $fnames=array();
    while ($l=fgets($fp,1024)) {
      if (preg_match('/\.gz$/',$l))
        $fnames[]=trim($l);
    }
    pclose($fp);
  }
  $err=$formatter->get_errlog();
  if ($err) {
    $err='<pre class="errlog">'.$err.'</pre>';
  }

  if (!$fnames) {
    $options['title']=_("No manpage found");
    $options['msg']=$err; // XXX
    do_invalid($formatter,$options);
    return;
  }
  $sz=count($fnames);
  $man=array();
  if ($sz >=1) {
    foreach ($fnames as $fname) {
      $man[]= $tmp=preg_replace("/\.gz$/","",basename($fname));
    }
    $options['page']="ManPage/$man[0]";
    $fname=$fnames[0];
  }

  if ($DBInfo->hasPage($options['page'])) {
    $options['value']=$options['page'];
    do_goto($formatter,$options);
    return;
  }

  if (function_exists('gzfile')) {
    $raw=gzfile($fname);
    $raw=join('',$raw);
  } else {
    exec("zcat $fname",$raw);
    $raw=join("\n",$raw);
  }

  if ($sz>1) {
    $lnk=array();
    foreach ($fnames as $f) {
      $tmp=preg_match("@/([^/]+)?/man[0-9nl].?/([^/]+)\.(\d[^.]*)(?:\.gz)?$@",$f,$m);
      $lang='en';
      if ($m) {
        if ($m[1] != 'man') $lang=$m[1];
        $myman=$m[2];
        $mysec=$m[3];
        $tag='';
        if ($lang) {
            $tag = ($lang == 'ko') ? '('.$lang.')':'';
            $lang='&amp;lang='.$lang;
        }
        $lnk[]=$formatter->link_tag('ManPage/'.$myman.'.'.$mysec,
            '?action=man_get&amp;man='.$myman.'&amp;sec='.$mysec.$lang,$myman.'.'.$mysec).$tag;
      }
    }
    if (sizeof($lnk) > 0)
        $options['msgtitle'] = implode(', ',$lnk);
  }
  if ($DBInfo->man_charset and
    $DBInfo->man_charset != $DBInfo->charset) {
    if (function_exists('iconv')) {
      $ignore='//IGNORE'; // XXX
      $raw=iconv($DBInfo->man_charset,$DBInfo->charset.$ignore,$raw);
    }
  }
  if ($DBInfo->man_filter)
    $raw = $formatter->filter_repl('simplere',$raw,array('page'=>$DBInfo->man_filter) );
  $options['savetext']=$raw;

  if ($options['edit']) {
    $formatter->send_header("",$options);
    $formatter->send_title("","",$options);
    $options['action'] = 'savepage';

    print macro_EditText($formatter,$raw,$options);
  } else if ($options['raw']) {
    $formatter->send_header("content-type: text/plain",$options);
    print $raw;
    return;
  } else {
    $formatter->send_header("",$options);
    $formatter->send_title("","",$options);

    print $formatter->processor_repl('man',$raw,$options);
    $extra='';
    if ($options['sec']) $extra='&amp;sec='.$options['sec'];
    if ($options['lang']) $extra='&amp;lang='.$options['lang'];
    $formatter->actions[]='?action=man_get&man='.$options['man'].
        $extra.'&amp;edit=1 '._("Edit man page");
  }
  $formatter->send_footer('',$options);
  return;
// vim:et:sts=4:
}

?>
