<?php
// Copyright 2003-2008 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a latex processor plugin for the MoniWiki
//
// Usage: {{{#!latex
// $ \alpha $
// }}}
//
// under Win32 env. you have to add latex/ImageMagick pathes like as following:
// # in config.php
// $path='./bin;C:/Program Files/MiKTeX 2.5/miktex/bin;C:/Program Files/ImageMagick-6.3.6-Q16';
// # ImagMagick and MikTeX are used in this setting.
//
// $Id$

function _latex_renumber($match,$tag='\\tag') {
  // XXX
  $num= &$GLOBALS['_latex_eq_num'];
  $num++;

  if ($tag == '\\tag') $star='*';
  else $star='';
  $math=rtrim($match[2]);
  
  return '\\begin{'.$match[1].$star.'}'.$math.$tag.'{'.$num.'}'."\n".'\\end{'.$match[1].$star.'}';
}

function processor_latex(&$formatter,$value="",$options=array()) {
  global $DBInfo;

  if (!$formatter->latex_uniq) {
    $formatter->latex_all='';
    $formatter->latex_uniq=array();
  }

  $latex_convert_options=
    $DBInfo->latex_convert_options ? $DBInfo->latex_convert_options:"-trim -crop 0x0 -density 120x120";

  $raw_mode = $options['raw'] ? 1:0;

  # site spesific variables
  $latex="latex";
  $dvicmd="dvipng";
  $dviopt='-D 120 -gamma 1.5';
  $convert="convert";
  $mogrify="mogrify";
  $vartmp_dir=&$DBInfo->vartmp_dir;
  $cache_dir=$DBInfo->upload_dir."/LaTeX";
  $cache_url=$DBInfo->upload_url ? $DBInfo->upload_url.'/LaTeX':
    $DBInfo->url_prefix.'/'.$cache_dir;
  $option='-interaction=batchmode ';
  $mask='';

  if (preg_match('/ps$/',$dvicmd)) {
    $tmpext='ps';
    $dviopt='-D 600';
  } else {
    $tmpext='png';
    $mask='-%d';
  }

  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);

  if (!$value) {
    if (!$DBInfo->latex_allinone) return '';
  }

  $tex=$value;

  if ($DBInfo->latex_renumbering) {
    $GLOBALS['_latex_eq_num']=$formatter->latex_num ? $formatter->latex_num:0;
    // renumbering
    //  just remove numbers and use \\tag{num}
    $ntex=preg_replace_callback('/\\\\begin\{\s*(equation)\s*\}((.|\n)+)\\\\end\{\s*\1\s*\}/',
      '_latex_renumber',$tex);
    #print '<pre>'.$ntex.'</pre>';
    if ($tex != $ntex) { $tex=$ntex; }
    $formatter->latex_num=$GLOBALS['_latex_eq_num']; // save
  } else if (!$raw_mode and $DBInfo->latex_allinone) {
    $ntex=preg_replace('/\\\\begin\{\s*(equation)\s*\}((.|\n)+)\\\\end\{\s*\1\s*\}/e',
      "_latex_renumber(array('','\\1','\\2'),\"\n%%\")",$tex);
    if ($tex != $ntex) { $tex=$ntex; }
    #print '<pre>'.$ntex.'</pre>';
  }

  if ($DBInfo->latex_template and file_exists($DBInfo->data_dir.'/'.$DBInfo->latex_template)) {
    $templ=implode('',file($DBInfo->data_dir.'/'.$DBInfo->latex_template));
  } else {
    $templ="\\documentclass[10pt,notitlepage]{article}
\\usepackage{amsmath}
\\usepackage{amssymb}
\\usepackage{amsfonts}$DBInfo->latex_header
%%\usepackage[all]{xy}
\\pagestyle{empty}
\\begin{document}
@TEX@
\\end{document}
";
  }

  $src=str_replace('@TEX@',$tex,$templ);

  $uniq=$tex ? md5($src):$formatter->latex_uniq[sizeof($formatter->latex_uniq)-1];

  // check image file exists
  if (!$raw_mode and $DBInfo->latex_allinone and $tex) {
    $formatter->latex_uniq[]=$uniq;
    $formatter->latex_all.=$tex."\n\\pagebreak\n\n";
    #print '<pre>'.$tex.'</pre>';
  }

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
    $vartmp_dir=getenv('TEMP');
    #$convert="wconvert";
  }

  if ($formatter->preview and !$DBInfo->latex_allinone) {
    $bra='<span class="previewTex"><input type="checkbox" class="previewTex" name="_tex_'.$uniq.'" />';
    $ket='</span>';
  }

  $img_exists=file_exists($png);
  while ($formatter->preview || $formatter->refresh || !$img_exists) {
  //if ($options['_tex_'.$uniq] || $formatter->refresh || !file_exists($png)) {

     if (!$raw_mode and $DBInfo->latex_allinone) {
       if (!$value) {
         #$js= '<script type="text/javascript" src="'.$DBInfo->url_prefix.'/local/latex.js"></script>';
    	 if ($formatter->register_javascripts('latex.js'));

         $src=str_replace('@TEX@',$formatter->latex_all,$templ);
         #print '<pre>'.$src.'</pre>';
         $uniq=md5($src);
       } else {
         $formatter->postamble['latex']='processor:latex:';
         break;
       }
     }

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
       if ($log) {
         $log="<pre class='errlog'>".$log."</pre>\n";
       }
     }

     if (!file_exists($uniq.".dvi")) {
       if (!$image_mode) {
         $log.="<pre class='errlog'><font color='red'>ERROR:</font> LaTeX does not work properly.</pre>";
         trigger_error ($log, E_USER_WARNING);
       }
       chdir($cwd);
       return '';
     }
     #$formatter->errlog('DVIPS');
     $cmd= "$dvicmd $dvipot $uniq.dvi -o $uniq$mask.$tmpext";
     $formatter->errlog('DVI',$uniq.'.log');
     $fp=popen($cmd.$formatter->NULL,'r');
     pclose($fp);
     $log2=$formatter->get_errlog();
     if ($log2 and !$raw_mode) trigger_error ($log2, E_USER_NOTICE);
     chdir($cwd);

     chdir(dirname($outpath)); # XXX :(
     if ($tmpext == 'ps') {
       $cmd= "$convert -transparent white $latex_convert_options $vartmp_dir/$uniq.$tmpext ".basename($outpath);
     } else {
       if (!$raw_mode and $DBInfo->latex_allinone) $outpath="$vartmp_dir/$uniq.$tmpext";
       $cmd= "$mogrify -transparent white $latex_convert_options $vartmp_dir/$uniq*.$tmpext";
     }

     # ImageMagick of the RedHat AS 4.x do not support -trim option correctly
     # http://kldp.net/forum/message.php?msg_id=12024
     #$cmd= "$convert -transparent white -trim -crop 0x0 -density 120x120 $vartmp_dir/$uniq.ps $outpath";
     $formatter->errlog('CNV',$uniq.'.log');
     $fp=popen($cmd.$formatter->LOG,'r');
     pclose($fp);
     $log2=$formatter->get_errlog(1,1);
     if (!$raw_mode and $log2) trigger_error ($log2, E_USER_WARNING);
     chdir($cwd);

     if ($raw_mode and $tmpext == 'png') {
       rename("$vartmp_dir/$uniq-1.$tmpext",$outpath);
     } else if ($DBInfo->latex_allinone) {
        $sz=sizeof($formatter->latex_uniq);

        if ($tmpext == 'png') {
          $soutpath=preg_replace('/\.png/','',$outpath);
          if (file_exists($outpath.'.0')) # old convert behavior
            $soutpath="$soutpath.png.%d";
          else
            $soutpath="$soutpath-%d.png"; # new behavior :(
        }
        for ($i=0;$i<$sz;$i++) {
          $id=$formatter->latex_uniq[$i];
          if ($DBInfo->cache_public_dir) {
            $pngname=$fc->_getKey($id,0);
            $img= $DBInfo->cache_public_dir.'/'.$pngname;
          } else {
            $img=$cache_dir.'/'.$id.'.png';
          }

          if ($tmpext == 'ps' and $sz==1)
            rename($outpath,$img);
          else {
            $ii=$i;
            if ($tmpext == 'png') $ii++;
            rename(sprintf($soutpath,$ii),$img);
          }
        }
        $formatter->latex_all='';
        $formatter->latex_uniq=array();
        $formatter->postamble['latex']='';
     } 

     @unlink($vartmp_dir."/$uniq.log");
     @unlink($vartmp_dir."/$uniq.aux");
     @unlink($vartmp_dir."/$uniq.tex");
     @unlink($vartmp_dir."/$uniq.dvi");
     @unlink($vartmp_dir."/$uniq.bib");
     @unlink($vartmp_dir."/$uniq.ps");
     $img_exists=true;
     break;
  }
  if (!$raw_mode and !$value) return $js;
  $alt=str_replace("'","&#39;",$value);
  $title=$alt;
  if (!$raw_mode and !$img_exists) {
    $title=$png_url;
    if ($DBInfo->latex_allinone==1 && !$formatter->wikimarkup)
      $png_url=$DBInfo->imgs_dir.'/loading.gif';
  }
  if (!$raw_mode)
    return $log.$bra."<img class='tex' src='$png_url' rel='$uniq' alt='$alt' ".
         "title='$title' />".$ket;
  else return $png;
}

// vim:et:sts=2:sw=2
?>
