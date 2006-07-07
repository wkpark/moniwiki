<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a man_get action plugin for the MoniWiki
//
// $Id$
// vim:et:ts=2:

function do_man_get($formatter,$options) {
  global $DBInfo;

  if (!$options['man']) {
    $options['title']=_("No manpage selected");
    do_invalid($formatter,$options);
    return;
  }

  $cmd="man -w $options[man]";
  $formatter->errlog();
  $fp=popen(escapeshellcmd($cmd).$formatter->LOG,'r');
  if (is_resource($fp)) {
    $fname=rtrim(fgets($fp,1024));
    pclose($fp);
  }
  $err=$formatter->get_errlog();
  if ($err) {
    $err='<pre class="errlog">'.$err.'</pre>';
  }

  if (!$fname) {
    $options['title']=_("No manpage found");
    do_invalid($formatter,$options);
    return;
  }
  $man= preg_replace("/\.gz$/","",basename($fname));
  $options['page']="ManPage/$man";

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

  $options['title']=$options['page'];

  $formatter->send_header("",$options);
  $formatter->send_title("","",$options);
  $options['savetext']=$raw;
  if ($options['edit']) {
    print macro_EditText($formatter,$raw,$options);
  } else {
    print $formatter->processor_repl('man',$raw,$options);
    $formatter->actions[]='?action=man_get&man='.$options['man'].'&edit=1 '._("Edit");
  }
  $formatter->send_footer('',$options);
  return;
}

?>
