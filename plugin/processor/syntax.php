<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a syntax colorizer plugin using the enscript for the MoniWiki
//
// Usage: {{{#!syntax sh|c|sh|..
// some codes
// }}}
// $Id$

function processor_syntax($formatter,$value) {
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

  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);
  # get parameters
  if ($line)
    list($tag,$type,$extra)=explode(" ",$line,3);

  if ($extra == "number") $option='-C ';

  $src=$value;

  if (!in_array($type,$syntax)) 
    return "<pre class='code'>\n$line\n$src\n</pre>\n";

  if ($type=='php') {
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

    #$cmd="ENSCRIPT_LIBRARY=/home/httpd/wiki/lib $enscript -q -o - -E$type -W html --color=ifh --word-wrap ".$tmpf;
    $cmd="$enscript -q -o - $option -E$type -W html --color=ifh --word-wrap ".$tmpf;
    $fp=popen($cmd, 'r');
    $html='';
    while($s = fgets($fp, 1024)) $html.= $s;
    pclose($fp);

    $html= eregi_replace('^.*<pre>', '<div class="wikiPre"><pre class="wiki">', $html);
    $html= eregi_replace('<\/PRE>.*$', '</pre></div>', $html);
    unlink($tmpf);
  }

  return $html;
}

// vim:et:ts=2:
?>
