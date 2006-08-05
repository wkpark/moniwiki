<?php
// Copyright 2003-2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a vim colorizer plugin for the MoniWiki
//
// Usage: {{{#!vim sh|c|sh|.. [number]
// some codes
// }}}
//
// Win32 note:
//  add $path="/bin;/Program Files/Vim/VimXX"; in the config.php
//
// $Id$

function processor_vim($formatter,$value,$options) {
  global $DBInfo;

  $vim_default='-T xterm';
  static $jsloaded=0;
  $vartmp_dir=&$DBInfo->vartmp_dir;

  $syntax=array("php","c","python","jsp","sh","cpp",
          "java","ruby","forth","fortran","perl",
          "haskell","lisp","st","objc","tcl","lua",
          "asm","masm","tasm","make","mysql",
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

  if ($DBInfo->cache_public_dir) {
    $fc=new Cache_text('vim',2,'html',$DBInfo->cache_public_dir);
    $htmlname=$fc->_getKey($uniq,0);
    $html= $DBInfo->cache_public_dir.'/'.$htmlname;
  } else {
    $cache_dir=$DBInfo->upload_dir."/VimProcessor";
    $html=$cache_dir.'/'.$uniq.'.html';
  }

  $script='';
  if ($DBInfo->use_numbering and empty($formatter->no_js)) {
    $button=_("Toggle line numbers");
    if (!$jsloaded) 
      $script='<script type="text/javascript" src="'.$DBInfo->url_prefix.'/local/numbering.js"></script>';
    $script.="<script type=\"text/javascript\">
/*<![CDATA[*/
document.write('<a href=\"#\" onclick=\"return togglenumber(\'PRE-$uniq\', 1, 1);\" class=\"codenumbers\">$button</a>');
/*]]>*/
</script>";
  }

  $stag="<pre class='wikiSyntax' id='PRE-$uniq' style='font-family:FixedSys,monospace;color:#c0c0c0;background-color:black'>\n";
  $etag="</pre>\n";

  if (!is_dir(dirname($html))) {
    $om=umask(000);
    _mkdir_p(dirname($html),0777);
    umask($om);
  }

  if (file_exists($html) && !$formatter->refresh && !$formatter->preview) {
    $out = "";
    $fp=fopen($html,"r");
    while (!feof($fp)) $out .= fread($fp, 1024);
    @fclose($fp);
    return '<div>'.$script.$out.'</div>';
  }

  if (!empty($DBInfo->vim_nocheck) and !in_array($type,$syntax)) {
    $lines=explode("\n",$line."\n".str_replace('<','&lt;',$src));
    if ($lines[sizeof($lines)-1]=="") array_pop($lines);
    $src="<span class=\"line\">".
      implode("</span>\n<span class=\"line\">",$lines)."</span>";
    return '<div>'.$script."<pre class='wiki' id='PRE-$uniq'>\n$src</pre></div>\n";
  }

  if(getenv("OS")=="Windows_NT") {
    $tohtml='$VIMRUNTIME/syntax/2html.vim';
    $vim="gvim"; # Win32
    $fout=tempnam($vartmp_dir,"OUT");
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

  $tmpf=tempnam($vartmp_dir,"FOO");
  $fp= fopen($tmpf, "w");
  fwrite($fp, $src);
  fclose($fp);

  $cmd= "$vim $vim_default -e -s $tmpf ".
        ' +"syntax on " +"set syntax='.$type.'" '.$option.
        ' +"so '.$tohtml.'" +"wq! '.$fout.'" +q';

  $log='';
  if(getenv("OS")=="Windows_NT") {
    system($cmd);
    $out=join(file($fout),"");
    unlink($fout);
  } else {
    $formatter->errlog();
    $fp=popen($cmd.$formatter->LOG,"r");
    if (is_resource($fp)) {
      while($s = fgets($fp, 1024)) $out.= $s;
      pclose($fp);
    }
    $log=$formatter->get_errlog();
    if ($log) $log='<pre class="errlog">'.$log.'</pre>';
  }
  unlink($tmpf);

  #$out=preg_replace("/<title.*title>|<\/?head>|<\/?html>|<meta.*>|<\/?body.*>/","", $out);
  $out=str_replace("\r\n","\n",$out); # for Win32
  #$out=preg_replace("/(^(\s|\S)*<pre>\n|\n<\/pre>(\s|\S)*$)/","",$out); # XXX segfault sometime
  $fpos=strpos($out,'<pre>');
  $tpos=strpos($out,'</pre>');
  $out=substr($out,$fpos+6,$tpos-$fpos-7);

  $lines=explode("\n",$out);
  $out="<span class=\"line\">".
    implode("</span>\n<span class=\"line\">",$lines)."</span>\n";
  $fp=fopen($html,"w");
  fwrite($fp,$stag.$out.$etag);
  fclose($fp);
  return $log.'<div>'.$script.$stag.$out.$etag.'</div>';
}

// vim:et:sts=2:
?>
