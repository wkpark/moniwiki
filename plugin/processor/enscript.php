<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a syntax colorizer plugin using the enscript for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Since: 2003-08-10
// Date: 2008-12-17
// Name: a Enscript syntax colorizer
// Description: a syntax colorizing processor using the Enscript
// URL: MoniWiki:VimProcessor
// Version: $Revision: 1.5 $
// Usage: {{{#!enscript sh|c|sh|..
// some codes
// }}}
//
// $Id: enscript.php,v 1.5 2010/04/19 11:26:47 wkpark Exp $

function processor_enscript($formatter,$value) {
  global $DBInfo;

  $enscript='enscript ';
##enscript --help-pretty-print |grep "^Name" |cut -d" " -f 2
  $syntax=array(
"ada", "asm", "awk", "c", "changelog", "cpp", "diff", "diffu", "delphi",
"elisp", "fortran", "haskell", "html", "idl", "java", "javascript", "mail",
"makefile", "nroff", "objc", "pascal", "perl", "postscript", "python", "scheme",
"sh", "sql", "states", "synopsys", "tcl", "verilog", "vhdl", "vba","php");

  $options=array("number");

  $vartmp_dir=&$DBInfo->vartmp_dir;

  $line='';
  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);
  # get parameters
  if ($line) {
    $line=substr($line,2);
    $tag = strtok($line,' ');
    $type = strtok(' ');
    $extra = strtok('');
    if ($tag != 'enscript') {
      $extra = $type;
      $type = $tag;
    }
  }

  $option = '';
  if ($extra == "number") $option='-C ';

  $src=$value;

  if (!in_array($type,$syntax)) 
    return "<pre class='code'>\n$line\n$src\n</pre>\n";

  if ($type == 'php') {
    ob_start();
    highlight_string($src);
    $html= ob_get_contents();
    ob_end_clean();
  } else {
    $tmpf=tempnam($vartmp_dir,"FOO");
    $fp= fopen($tmpf, "w");
    fwrite($fp, $src);
    fclose($fp);

#-E%s -W html -J "" -B --color --word-wrap 

    #$cmd="ENSCRIPT_LIBRARY=/home/httpd/wiki/lib $enscript -q -o - -E$type -W html --color --word-wrap ".$tmpf;
    if (!empty($DBInfo->enscript_style))
        $cmd="$enscript -q -o - $option -E$type --language=html $DBInfo->enscript_style --color --word-wrap ".$tmpf;
    else
        $cmd="$enscript -q -o - $option -E$type --language=html --style=ifh --color --word-wrap ".$tmpf;

    $fp=popen($cmd.$formatter->NULL, 'r');
    $html='';
    while($s = fgets($fp, 1024)) $html.= $s;
    pclose($fp);

    $html= eregi_replace('^.*<pre>', '<div class="wikiPre"><pre class="wiki">', $html);
    $html= eregi_replace('<\/PRE>.*$', '</pre></div>', $html);
    unlink($tmpf);
  }

  return $html;
}

// vim:et:sts=2:
?>
