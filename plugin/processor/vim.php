<?
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a vim colorizer plugin for the MoniWiki
//
// Usage: {{{#!vim sh|c|sh|.. [number]
// some codes
// }}}
// $Id$

function processor_vim($formatter,$value) {
  $syntax=array("php","c","python","sh","cpp","diff",
                "tex","java","fortran","vim","perl",
                "haskell","lisp","html","st","objc");
  $options=array("number");

  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);
  # get parameters
  if ($line)
    list($tag,$type,$extra)=explode(" ",$line,3);
  $src=$value;

  if (!in_array($type,$syntax)) 
    return "<pre class='code'>\n#!vim\n$src\n</pre>\n";
  if ($extra == "number") 
    $option='+"set number" ';
  
  $tohtml='\$VIMRUNTIME/syntax/2html.vim';
  $vim="vim";

# simple sample
#$type='c';
#$src='
#void main() {
#printf("Hello World!");
#
#}
#';

  $tmpf=tempnam("/tmp","FOO");
  $fp= fopen($tmpf, "w");
  fwrite($fp, $src);
  fclose($fp);

  $cmd= "$vim -T xterm -e -s $tmpf ".
        ' +"syntax on " +"set syntax='.$type.'" '.$option.
        ' +"so '.$tohtml.'" +"wq! /dev/stdout" +q';

  $fp=popen($cmd,"r");
  #fwrite($fp,$src);

  while($s = fgets($fp, 1024)) {
    $out.= $s;
  };

  pclose($fp);
  unlink($tmpf);

  $out=preg_replace("/<title>.*title>|<\/?head>|<\/?html>|<meta.*>|<\/?body.*>/","", $out);
  $out=preg_replace("/<pre>/","<pre style='font-family:fixedsys,fixed;color:#c0c0c0;background-color:black'>", $out);
#  $out=preg_replace("/<\/pre>/","</span></pre>", $out);

  return $out;
}

// vim:et:ts=2:
?>
