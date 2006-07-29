<?php
// Copyright 2003-2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a latex processor plugin for the MoniWiki
//
// Usage: {{{#!latex
// $ \alpha $
// }}}
// $Id$

function processor_latex($formatter="",$value="") {
  global $DBInfo;
  # site spesific variables
  $latex="latex";
  $dvips="dvips";
  $convert="convert";
  $vartmp_dir=&$DBInfo->vartmp_dir;
  $cache_dir=$DBInfo->upload_dir."/LaTeX";
  $cache_url=$DBInfo->upload_url ? $DBInfo->upload_url.'/LaTeX':
    $DBInfo->url_prefix.'/'.$cache_dir;
  $option='-interaction=batchmode ';

  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);

  if (!$value) return '';

  $tex=&$value;

  if ($DBInfo->latex_template and file_exists($DBInfo->data_dir.'/'.$DBInfo->latex_template)) {
    $src=implode('',file($DBInfo->data_dir.'/'.$DBInfo->latex_template));
    $src=str_replace('@TEX@',$tex,$src);
  } else {
    $src="\\documentclass[10pt,notitlepage]{article}
\\usepackage{amsmath}
\\usepackage{amssymb}
\\usepackage{amsfonts}$DBInfo->latex_header
%%\usepackage[all]{xy}
\\pagestyle{empty}
\\begin{document}
$tex
\\end{document}
";
  }

  $uniq=md5($src);
  if ($DBInfo->cache_public_dir) {
    $fc=new Cache_text('latex',2,'png',$DBInfo->cache_public_dir);
    $pngname=$fc->_getKey($uniq,0);
    $png= $DBInfo->cache_public_dir.'/'.$pngname;
    $png_url=
      $DBInfo->cache_public_url ? $DBInfo->cache_public_url.'/'.$pngname:
      $DBInfo->url_prefix.'/'.$png;
  } else {
    $png=$cache_dir.'/'.$uniq.'.png';
    $png_url=$cache_url.'/'.$uniq.'.png';
  }

  if (!is_dir(dirname($png))) {
    $om=umask(000);
    _mkdir_p(dirname($png),0777);
    umask($om);
  }

  $NULL='/dev/null';
  if(getenv("OS")=="Windows_NT") {
    $NULL='NUL';
  }
  
  if ($formatter->preview || $formatter->refresh || !file_exists($png)) {
     $fp= fopen($vartmp_dir."/$uniq.tex", "w");
     fwrite($fp, $src);
     fclose($fp);

     $outpath=&$png;

     # Unix specific FIXME
     $cwd= getcwd();
     chdir($vartmp_dir);
     $formatter->errlog('Dum',$uniq.'.log');
     $cmd= "$latex $option $uniq.tex >$NULL";
     $fp=popen($cmd.$formatter->NULL,'r');
     pclose($fp);
     $log=$formatter->get_errlog(1,1);
     if ($log) {
       list($dum,$log,$dum2)=preg_split('/\n!/',$log,3);
       if ($log)
         $log="<pre class='errlog'>".$log."</pre>\n";
     }

     if (!file_exists($uniq.".dvi")) {
       $log.="<pre class='errlog'><font color='red'>ERROR:</font> LaTeX does not work properly.</pre>";
       chdir($cwd);
       return $log;
     }
     #$formatter->errlog('DVIPS');
     $cmd= "$dvips -D 600 $uniq.dvi -o $uniq.ps";
     $fp=popen($cmd.$formatter->NULL,'r');
     pclose($fp);
     #$log2=$formatter->get_errlog();
     chdir($cwd);

     $cmd= "$convert -transparent white -trim -crop 0x0 -density 120x120 $vartmp_dir/$uniq.ps $outpath";
     $fp=popen($cmd.$formatter->NULL,'r');
     pclose($fp);
     #unlink($vartmp_dir."/$uniq.log");
     unlink($vartmp_dir."/$uniq.aux");
     @unlink($vartmp_dir."/$uniq.bib");
     @unlink($vartmp_dir."/$uniq.ps");
  }
  $alt=str_replace("'","&#39;",$tex);
  return $log."<img class='tex' src='$png_url' alt='$alt' ".
         "title='$alt' />";
}

// vim:et:sts=2:
?>
