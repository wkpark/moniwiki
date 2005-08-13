<?php
// Copyright 2003-2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a vim colorizer plugin for the MoniWiki
//
// Usage: {{{#!vim sh|c|sh|.. [number]
// some codes
// }}}
//
// Win32 note:
//  add $path="%PATH%;/Program Files/Vim/VimXX"; in the config.php
//
// $Id$

function processor_vim($formatter,$value,$options) {
  global $DBInfo;
  static $jsloaded=0;
  $cache_dir=$DBInfo->upload_dir."/VimProcessor";

  $syntax=array("php","c","python","jsp","sh","cpp",
          "java","ruby","forth","fortran","perl",
          "haskell","lisp","st","objc","tcl","lua",
          "asm","masm","tasm","make",
          "awk","docbk","diff","html","tex","vim",
          "xml","dtd","sql","conf","config","nosyntax","apache");

  #$opts=array("number");

  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);
  # get parameters
  if ($line)
    list($tag,$type,$extra)=preg_split('/\s+/',$line,3);
  $src=$value;
  if (!preg_match('/^\w+$/',$type)) $type='nosyntax';

  if ($extra == "number") 
    $option='+"set number" ';

  if ($DBInfo->vim_options)
    $option.=$DBInfo->vim_options.' ';

  $uniq=md5($option.$src);
  $script='';
  if ($DBInfo->use_numbering) {
    $button=_("Toggle line numbers");
    if (!$jsloaded) 
      $script='<script type="text/javascript" src="'.$DBInfo->url_prefix.'/local/numbering.js"></script>';
    $script.="<script type=\"text/javascript\">
document.write('<a href=\"#\" onClick=\"return togglenumber(\'PRE-$uniq\', 1, 1);\" class=\"codenumbers\">$button</a>');
</script>";
  }

  $stag="<pre class='wikiSyntax' id='PRE-$uniq' style='font-family:FixedSys,monospace;color:#c0c0c0;background-color:black'>\n";
  $etag="</pre>\n";

  if (!file_exists($cache_dir)) {
    umask(000);
    mkdir($cache_dir,0777);
    umask(022);
  }

  if (file_exists($cache_dir."/$uniq".".html") && !$formatter->refresh && !$formatter->preview) {
    $out = "";
    $fp=fopen($cache_dir."/$uniq".".html","r");
    while (!feof($fp)) $out .= fread($fp, 1024);
    @fclose($fp);
    return '<div>'.$script.$out.'</div>';
    #return join('',file($cache_dir."/$uniq".".html"));
  }

  if (!empty($DBInfo->vim_nocheck) and !in_array($type,$syntax)) {
    $lines=explode("\n",$line."\n".$src);
    if ($lines[sizeof($lines)-1]=="") array_pop($lines);
    $src="<span class=\"line\">".
      implode("</span>\n<span class=\"line\">",$lines)."</span>";
    return '<div>'.$script."<pre class='wiki' id='PRE-$uniq'>\n$src</pre></div>\n";
  }

  if(getenv("OS")=="Windows_NT") {
    $tohtml='$VIMRUNTIME/syntax/2html.vim';
    $vim="gvim"; # Win32
    $fout=tempnam("/tmp","OUT");
  } else {
    $tohtml='\$VIMRUNTIME/syntax/2html.vim';
    $vim="vim";
    $fout="/dev/stdout";
  }  

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
        ' +"so '.$tohtml.'" +"wq! '.$fout.'" +q';

  if(getenv("OS")=="Windows_NT") {
    system($cmd);
    $out=join(file($fout),"");
    unlink($fout);
  } else {
    $fp=popen($cmd,"r");
    while($s = fgets($fp, 1024)) $out.= $s;
    pclose($fp);
  }
  unlink($tmpf);

  #$out=preg_replace("/<title.*title>|<\/?head>|<\/?html>|<meta.*>|<\/?body.*>/","", $out);
  $out=preg_replace("/\r?\n/","\n",$out); # for Win32
  $out=preg_replace("/(^(\s|\S)*<pre>\n|\n<\/pre>(\s|\S)*$)/","",$out);
  $lines=explode("\n",$out);
  $out="<span class=\"line\">".
    implode("</span>\n<span class=\"line\">",$lines)."</span>\n";
  $fp=fopen($cache_dir."/$uniq".".html","w");
  fwrite($fp,$stag.$out.$etag);
  fclose($fp);
  return '<div>'.$script.$stag.$out.$etag.'</div>';
}

// vim:et:sts=2:
?>
