<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a vim colorizer plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Since: 2003-06-04
// Date: 2008-12-17
// Name: a VIM syntax colorizer
// Description: a Syntax colorizing processor using the VIM
// URL: MoniWiki:VimProcessor
// Version: $Revision: 1.48 $
// License: GPL
// Usage: {{{#!vim sh|c|sh|.. [number]
// some codes
// }}}
//
// Win32 note:
//  add $path="/bin;/Program Files/Vim/VimXX"; in the config.php
//
// $Id: vim.php,v 1.48 2010/09/07 12:11:49 wkpark Exp $

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

  $line='';
  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);
  # get parameters
  if ($line) {
    $line=substr($line,2);
    $tag = strtok($line,' ');
    $type = strtok(' ');
    $extra = strtok('');
    if ($tag != 'vim') {
      $extra = $type;
      $type = $tag;
    }
  }
  $src=$value;
  if (!preg_match('/^\w+$/',$type)) $type='nosyntax';

  $option = '';
  if ($extra == "number") 
    $option='+"set number" ';

  if ($DBInfo->vim_options)
    $option.=$DBInfo->vim_options.' ';

  $uniq=md5($option.$extra.$type.$src);

  if ($DBInfo->cache_public_dir) {
    $fc = new Cache_text('vim', array('ext'=>'html', 'dir'=>$DBInfo->cache_public_dir));
    $htmlname = $fc->getKey($uniq, false);
    $html= $DBInfo->cache_public_dir.'/'.$htmlname;
  } else {
    $cache_dir=$DBInfo->upload_dir."/VimProcessor";
    $html=$cache_dir.'/'.$uniq.'.html';
  }

  $script='';
  if (!empty($DBInfo->use_numbering) and empty($formatter->no_js)) {
    $formatter->register_javascripts('numbering.js');

    $script="<script type=\"text/javascript\">
/*<![CDATA[*/
addtogglebutton('PRE-$uniq');
/*]]>*/
</script>";
  }

  if (!is_dir(dirname($html))) {
    $om=umask(000);
    _mkdir_p(dirname($html),0777);
    umask($om);
  }

  if (file_exists($html) && empty($formatter->refresh) && empty($formatter->preview)) {
    $out = "";
    $fp=fopen($html,"r");
    while (!feof($fp)) $out .= fread($fp, 1024);
    @fclose($fp);
    return '<div>'.$out.$script.'</div>';
  }

  if (!empty($DBInfo->vim_nocheck) and !in_array($type,$syntax)) {
    $lines=explode("\n",$line."\n".str_replace('<','&lt;',$src));
    if ($lines[sizeof($lines)-1]=="") array_pop($lines);
    $src="<span class=\"line\">".
      implode("</span>\n<span class=\"line\">",$lines)."</span>";
    return '<div>'.$script."<pre class='wiki' id='PRE-$uniq'>\n$src</pre></div>\n";
  }

  $tohtml= !empty($DBInfo->vim_2html) ? $DBInfo->vim_2html:
    $tohtml='$VIMRUNTIME/syntax/2html.vim';
  #$tohtml= realpath($DBInfo->data_dir).'/2html.vim';
  if(getenv("OS")=="Windows_NT") {
    $vim="gvim"; # Win32
    $fout=tempnam($vartmp_dir,"OUT");
  } else {
    $tohtml='\\'.$tohtml;
    $vim="vim";
    $fout="/dev/stdout";
  }  

  $tmpf=tempnam($vartmp_dir,"FOO");
  $fp= fopen($tmpf, "w");
  fwrite($fp, $src);
  fclose($fp);

  $cmd= "$vim $vim_default -e -s $tmpf ".
        ' +"syntax on " +"set syntax='.$type.'" '.$option.
        ' +"so '.$tohtml.'" +"wq! '.$fout.'" +qall';

  $log='';
  $out = '';
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

  preg_match("/<body\s+bgcolor=(\"|')([^\\1]+)\\1\s+text=\\1([^\\1]+)\\1/U",
    $out,$match);
  $bgcolor='#000000';
  $fgcolor='#c0c0c0';
  if ($match) {
    $bgcolor=$match[2];
    $fgcolor=$match[3];
  }
  $myspan='pre';
  $fpos=strpos($out,'<pre>');
  if ($fpos === false) {
    $myspan='div';
    $fpos=strpos($out,'<body');
    $tpos=strpos($out,'</body>');
    $out=substr($out,$fpos+7,$tpos-$fpos-7);
    $out=preg_replace('/^[^>]+>/','',$out);
    $out = preg_replace(array("@^<font face[^>]*>\n@","@\n?</font>\n?$@"),array('',''),$out); // vim7.x
  } else {
    $tpos=strpos($out,'</pre>');
    $out=substr($out,$fpos+6,$tpos-$fpos-7);
  }
  $stag="<$myspan class='wikiSyntax' id='PRE-$uniq' style='font-family:FixedSys,monospace;color:$fgcolor;background-color:$bgcolor'>\n";
  $etag="</$myspan>\n";


  $lines=explode("\n",$out);
  $out = '';
  if (0) { // list style
    $col = array(' alt','');
    $sz = count($lines);
    for ($i=0;$i<$sz;$i++) {
      $cls = $col[$i % 2];
      $out.= "<li class=\"line$cls\">".$lines[$i]."</li>\n";
    }
    $out = '<ul style="margin:0;padding:0;list-style:none;">'.$out.'</ul>';
  } else {
    $out="<span class=\"line\">".
      implode("</span>\n<span class=\"line\">",$lines)."</span>\n";
  }
  $fp=fopen($html,"w");
  fwrite($fp,$stag.$out.$etag);
  fclose($fp);
  return $log.'<div>'.$stag.$out.$etag.$script.'</div>';
}

// vim:et:sts=2:
?>
