<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
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
  $option='-interaction=batchmode ';

  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);

  if (!$value) return;

  if (!file_exists($cache_dir)) {
    umask(000);
    mkdir($cache_dir,0777);
  }

  $tex=$value;

  $uniq=md5($tex);

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

  $RM='rm';
  $NULL='/dev/null';
  if(getenv("OS")=="Windows_NT") {
    $RM='del';
    $NULL='NUL';
  }
  
  if ($formatter->preview || $formatter->refresh || !file_exists("$cache_dir/$uniq.png")) {
     $fp= fopen($vartmp_dir."/$uniq.tex", "w");
     fwrite($fp, $src);
     fclose($fp);

     $outpath="$cache_dir/$uniq.png";

     # Unix specific FIXME
     $cwd= getcwd();
     chdir($vartmp_dir);
     $cmd= "$latex $option $uniq.tex >$NULL";
     $fp=popen($cmd,'w');
     pclose($fp);

     if (!file_exists($uniq.".dvi")) {
       print "<font color='red'>ERROR:</font> LaTeX does not work properly.";
       chdir($cwd);
       return;
     }
     $cmd= "$dvips -D 600 $uniq.dvi -o $uniq.ps";
     $fp=popen($cmd,'w');
     pclose($fp);
     chdir($cwd);

     $cmd= "$convert -transparent white -crop 0x0 -density 120x120 $vartmp_dir/$uniq.ps $outpath";
     $fp=popen($cmd,'w');
     pclose($fp);
     unlink($vartmp_dir."/$uniq.log");
     unlink($vartmp_dir."/$uniq.aux");
     @unlink($vartmp_dir."/$uniq.bib");
     @unlink($vartmp_dir."/$uniq.ps");
  }
  return "<img class='tex' src='$DBInfo->url_prefix/$cache_dir/$uniq.png' alt='$tex' ".
         "title=\"$tex\" />";
}

?>
