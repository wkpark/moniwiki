<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a docbook processor plugin for the MoniWiki
//
// Usage: {{{#!jade
// docbook code
// }}}
// $Id: jade.php,v 1.9 2006/07/07 12:57:32 wkpark Exp $

function processor_jade($formatter,$value,$options=array()) {
  global $DBInfo;
  $methods=array('html');

#  'jade ' +
#  "-V %%root-filename%%='%s-x0' " % tmpfile +
#  "-V %%html-prefix%%='%s-' " % tmpfile +
#  "-V '(define %use-id-as-filename% #f)' " +
#  '-t sgml -i html -d %s#html ' % (DEFAULT_DSL) +
#  ' ' + tmpfile + '.sgml')

  $pagename=$formatter->page->name;
  $vartmp_dir=&$DBInfo->vartmp_dir;
  $cache= new Cache_text("jade");

  if (!$formatter->refresh and !$formatter->preview and $cache->exists($pagename) and $cache->mtime($pagename) > $formatter->page->mtime())
    return $cache->fetch($pagename);

  $method="#html";
  if ($options and in_array($options['method'],$methods))
    $method="#".$options['method'];

  $jade= "jade";
#  $args= "-V %%root-filename%%='$tmpfile-x0' ".
#         "-V %%html-prefix%%='$tmpfile-' ".
  $args= "-V '(define %use-id-as-filename% #f)' ".
         "-t sgml -i html ".
         "-V nochunks -o /dev/stdout ";
# jade -V nochunks -t sgml -i html vim.sgml -o /dev/stdout

  if ($value[0]=='#' and $value[1]=='!') {
    list($line,$value)=explode("\n",$value,2);
    # get parameters
    list($tag,$dummy)=explode(" ",$line,2);
  }

  list($line,$body)=explode("\n",$value,2);
  $buff="";
  $dsssl_flag=false;
  while(($line[0]=='<') or !$line) {
    preg_match("/^<\?stylesheet\s+href=\"([^\"]+)\"/",$line,$match);
    if ($match) {
      if ($DBInfo->hasPage($match[1]))
        $line='<?stylesheet href="'.getcwd().'/'.$DBInfo->text_dir.'/'.$match[1].$method.'" type="text/dsssl"?>';
      $dsssl_flag=true;
      break;
    }
    $buff.=$line."\n";
    list($line,$body)=explode("\n",$body,2);
  }
  $src=$buff.$line."\n".$body;
  if (!$dsssl_flag and $DBInfo->default_dsssl)
    $args.=" -d $DBInfo->default_dsssl";

  if (strtolower($DBInfo->charset)=='utf-8') {
    if ($DBInfo->docbook_xmldcl)
      $args.=' '.$DBInfo->docbook_xmldcl;
    else
      $args.=' xml.dcl';
    $sp_encoding='SP_ENCODING=utf-8 ';
    #putenv('SP_ENCODING=utf-8');
  }

  $tmpf=tempnam($vartmp_dir,"JADE");
  $fp= fopen($tmpf, "w");
  fwrite($fp, $src);
  fclose($fp);

  $cmd=$sp_encoding."$jade $args $tmpf";

  $formatter->errlog();
  $fp=popen($cmd.$formatter->LOG,"r");
  if (is_resource($fp)) {
    $html='';
    while($s = fgets($fp, 1024)) $html.= $s;

    pclose($fp);
  }
  unlink($tmpf);
  $err=$formatter->get_errlog();

  if ($err) $err='<pre class="errlog">'.$err.'</pre>';

  if (!$html) {
    $src=str_replace("<","&lt;",$value);
    return "<pre class='code'>$src\n</pre>\n";
  }

  if (!$formatter->preview) $cache->update($pagename,$html);
  return $err.$html;
}

// vim:et:sts=2:
?>
