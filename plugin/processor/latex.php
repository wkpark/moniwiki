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
  $vartmp_dir=$DBInfo->vartmp_dir;
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

  $src="\documentclass[10pt,notitlepage]{article}
\usepackage{amsmath}
\usepackage{amsfonts}
%%\usepackage[all]{xy}
\\begin{document}
\pagestyle{empty}
$tex
\end{document}
";

  if ($formatter->refresh || !file_exists("$cache_dir/$uniq.png")) {
     $fp= fopen($vartmp_dir."/$uniq.tex", "w");
     fwrite($fp, $src);
     fclose($fp);

     $outpath="$cache_dir/$uniq.png";

     # Unix specific FIXME
     $cmd= "cd $vartmp_dir; $latex $option $uniq.tex >/dev/null";
     system($cmd);

     $cmd= "cd $vartmp_dir; $dvips -D 600 $uniq.dvi -o $uniq.ps";
     system($cmd);

     $cmd= "$convert -transparent white -crop 0x0 -density 120x120 $vartmp_dir/$uniq.ps $outpath";
     system($cmd);

     system("rm $vartmp_dir/$uniq.*");
  }
  return "<img class='tex' src='$DBInfo->url_prefix/$cache_dir/$uniq.png' alt='tex'".
         "title=\"$tex\" />";
}

?>
