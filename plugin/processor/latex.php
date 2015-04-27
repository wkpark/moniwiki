<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
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
// $Id: latex.php,v 1.29 2010/09/09 14:42:06 wkpark Exp $

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

  if (empty($formatter->latex_uniq)) {
    $formatter->latex_all='';
    $formatter->latex_uniq=array();
  }

  $latex_convert_options=
    !empty($DBInfo->latex_convert_options) ? $DBInfo->latex_convert_options:"-trim -crop 0x0 -density 120x120";

  $raw_mode = isset($options['retval']) ? 1:0;

  # site spesific variables
  $latex="latex";
  $dvicmd="dvipng";
  $dviopt='-D 120 -gamma 1.3';
  $convert="convert";
  $mogrify="mogrify";
  $vartmp_dir=&$DBInfo->vartmp_dir;
  $cache_dir=$DBInfo->upload_dir."/LaTeX";
  $cache_url=!empty($DBInfo->upload_url) ? $DBInfo->upload_url.'/LaTeX':
    $DBInfo->url_prefix.'/'.$cache_dir;
  $option='-interaction=batchmode ';
  $mask='';

  $options['dpi'] = intval($options['dpi']);
  if (preg_match('/ps$/',$dvicmd)) {
    $tmpext='ps';
    $dviopt='-D 300';
    if (!empty($options['dpi']))
      $latex_convert_options.= ' -density '.$options['dpi'].'x'.$options['dpi'];
  } else {
    $tmpext='png';
    $mask='-%d';
    if (!empty($options['dpi'])) {
      $dviopt= preg_replace('/-D 120/','',$dviopt);
      $dviopt.=' -D '.$options['dpi'];
    }
  }

  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);

  if (!$value) {
    if (empty($DBInfo->latex_allinone)) return '';
  }

  $tex=$value;

  if (!empty($DBInfo->latex_renumbering)) {
    $GLOBALS['_latex_eq_num']=!empty($formatter->latex_num) ? $formatter->latex_num:0;
    // renumbering
    //  just remove numbers and use \\tag{num}
    $ntex=preg_replace_callback('/\\\\begin\{\s*(equation)\s*\}((.|\n)+)\\\\end\{\s*\1\s*\}/',
      '_latex_renumber',$tex);
    #print '<pre>'.$ntex.'</pre>';
    if ($tex != $ntex) { $tex=$ntex; }
    $formatter->latex_num=$GLOBALS['_latex_eq_num']; // save
  } else if (!$raw_mode and !empty($DBInfo->latex_allinone)) {
    $chunks = preg_split('/(\\\\begin\{\s*(?:equation)\s*\}(?:(?:.|\n)+)\\\\end\{\s*\1\s*\})/',
        $tex, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (($sz = count($chunks)) > 0) {
      $ntex = '';
      for ($i = 1; $i < $sz; $i+= 2) {
        $ntex.= $chunks[$i - 1];
        preg_match('/\\\\begin\{\s*(equation)\s*\}((.|\n)+)\\\\end\{\s*\1\s*\}/', $chunks[$i], $m);
        $ntex.= _latex_renumber(array('', $m[1], $m[2]), "\n%%");
      }
      $tex = $ntex;
    }
    #print '<pre>'.$ntex.'</pre>';
  }

  if (!empty($DBInfo->latex_template) and file_exists($DBInfo->data_dir.'/'.$DBInfo->latex_template)) {
    $templ=implode('',file($DBInfo->data_dir.'/'.$DBInfo->latex_template));
  } else {
    $head = !empty($DBInfo->latex_header) ? $DBInfo->latex_header : '';
    $templ="\\documentclass[10pt,notitlepage]{article}
\\usepackage{amsmath}
\\usepackage{amssymb}
\\usepackage{amsfonts}$head
%%\usepackage[all]{xy}
\\pagestyle{empty}
\\begin{document}
@TEX@
\\end{document}
%%$dviopt
%%$latex_convert_options
";
  }

  $src=str_replace('@TEX@',$tex,$templ);

  $uniq=$tex ? md5($src):$formatter->latex_uniq[sizeof($formatter->latex_uniq)-1];

  // check image file exists
  if (empty($raw_mode) and !empty($DBInfo->latex_allinone) and $tex) {
    $formatter->latex_uniq[]=$uniq;
    $formatter->latex_all.=$tex."\n\\pagebreak\n\n";
    #print '<pre>'.$tex.'</pre>';
  }

  if (!empty($DBInfo->cache_public_dir)) {
    $fc = new Cache_text('latex',array('ext'=>'png','dir'=>$DBInfo->cache_public_dir));
    $pngname=$fc->getKey($uniq, false);
    $png= $DBInfo->cache_public_dir.'/'.$pngname;
    $png_url=
      !empty($DBInfo->cache_public_url) ? $DBInfo->cache_public_url.'/'.$pngname:
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

  $bra = ''; $ket = '';
  if (!empty($formatter->preview) and empty($DBInfo->latex_allinone)) {
    $bra='<span class="previewTex"><input type="checkbox" class="previewTex" name="_tex_'.$uniq.'" />';
    $ket='</span>';
  }

  $img_exists=file_exists($png);
  $log = '';
  while (!empty($formatter->preview) || !empty($formatter->refresh) || !$img_exists) {
  //if ($options['_tex_'.$uniq] || $formatter->refresh || !file_exists($png)) {

     if (empty($raw_mode) and !empty($DBInfo->latex_allinone)) {
       if (empty($value)) {
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
       #list($dum,$log,$dum2)=preg_split('/\n!/',$log,3);
       if (($p = strpos($log, "\n!")) !== FALSE) {
         $log = substr($log,$p);
         $log="<pre class='errlog'>".$log."</pre>\n";
       } else {
         $log = '';
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
     $cmd= "$dvicmd $dviopt $uniq.dvi -o $uniq$mask.$tmpext";
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
       if (!$raw_mode and !empty($DBInfo->latex_allinone)) $outpath="$vartmp_dir/$uniq.$tmpext";
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

     if ($raw_mode or ($tmpext == 'png' and empty($DBInfo->latex_allinone)) ) {
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
            $pngname=$fc->getKey($id, false);
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
    if ($DBInfo->latex_allinone==1 && empty($formatter->wikimarkup))
      $png_url=$DBInfo->imgs_dir.'/loading.gif';
  }
  if (!$raw_mode)
    return $log.$bra."<img class='tex' src='$png_url' rel='$uniq' alt='$alt' ".
         "title='$title' />".$ket;
  $retval = &$options['retval'];
  $retval = $png;
  return $png_url;
}

// vim:et:sts=2:sw=2
?>
