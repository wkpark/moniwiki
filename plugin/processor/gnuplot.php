<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a gnuplot processor plugin for the MoniWiki
//
// Usage: {{{#!gnuplot
// plot sin(x)
// }}}
// $Id: gnuplot.php,v 1.18 2010/09/07 12:11:49 wkpark Exp $

function processor_gnuplot($formatter="",$value="") {
  global $DBInfo;

  $convert="convert";
  if(getenv("OS")=="Windows_NT")
    $gnuplot="wgnuplot"; # Win32
  else
    $gnuplot="gnuplot";

  $vartmp_dir=&$DBInfo->vartmp_dir;

  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);
  if (strpos($line, ' ') !== false) {
    list($dum,$szarg)=explode(' ',$line);
    $args= explode('x',$szarg,2);
    if (count($args) > 2) {
      $xsize=max(intval($args[0]),50);$ysize=max(intval($args[1]),50);
    }
    $value='#'.$line."\n".$value;
  }

  #$term='dumb'; // for w3m,lynx
  $term='png';
  if ($term=='png') $ext='png';
  else if ($term == 'dumb') $ext='txt';

  $default_size="set size 0.5,0.6";

  $body=$plt=$value;
  while ($body and $body[0] == '#') {
    # extract first line
    list($line, $body) = explode("\n",$body, 2);

    # skip comments (lines with two hash marks)
    if ($line[1] == '#') continue;

    # parse the PI
    list($verb, $arg) = explode(' ',$line,2);
    $verb = strtolower($verb);
    $arg = rtrim($arg);

    if (in_array($verb,array('#size'))) {
      $args= explode('x',$arg,2);
      $xsize=intval($args[0]);$ysize=intval($args[1]);
    }
  }
  if (!empty($xsize)) {
    if ($xsize > 640 or $xsize < 100) $xscale=0.5;
    if ($xscale and ($ysize > 480 or $ysize < 100)) $yscale=0.6;
    $xscale=$xsize/640.0;
    
    if (empty($yscale)) $yscale=$xscale/0.5*0.6;

    $size='set size '.$xscale.','.$yscale;
  } else $size=$default_size;

# a sample for testing
#  $plt='
#set term gif
#!  ls
#plot sin(x)
#';
  # normalize plt
  $plt=str_replace("\r\n","\n",$plt); 
  $plt="\n".$plt."\n";
  $plt=preg_replace("/\n\s*![^\n]+\n/","\n",$plt); # strip shell commands
  $plt=preg_replace("/[ ]+/"," ",$plt);
  preg_match("/\nset?\s+(t|te|ter|term)\s(.*)\n/", $plt,$tmatch);
  $plt=preg_replace("/\nset?\s+(t|o|si).*\n/", "\n",$plt);
  $plt=preg_replace("/system/", "", $plt); # strip system() function
  #
  $plt=preg_replace("/('|\")<(\s*)/", "\\1\\2", $plt); # strip all redirection mark
  
  #print "<pre>$plt</pre>";

  if ($tmatch) {
    if (preg_match('/^postscript\s*(enhanced|color)?/',$tmatch[2])) { // XXX
      $term=$tmatch[2];
      $ext='ps';
      $size='#set term '.$term;
    } else if (preg_match('/^svg/',$tmatch[2])) {
      $term=$tmatch[2];
      $ext='svg';
      $size="set size 1.0,1.0\n#set term ".$term;
    }
  }

  if ($term != 'dumb') 
    $plt="\n".$size."\n".$plt;
  $uniq=md5($plt);
  if ($DBInfo->cache_public_dir) {
    $fc = new Cache_text('gnuplot',array('ext'=>$ext, 'dir'=>$DBInfo->cache_public_dir));
    $pngname=$fc->getKey($uniq, false);
    $png= $DBInfo->cache_public_dir.'/'.$pngname;
    $png_url=
      $DBInfo->cache_public_url ? $DBInfo->cache_public_url.'/'.$pngname:
      $DBInfo->url_prefix.'/'.$png;
    $cache_dir=$DBInfo->cache_public_dir;
  } else {
    $cache_dir=$DBInfo->upload_dir."/GnuPlot";
    $cache_url=$DBInfo->upload_url ? $DBInfo->upload_url.'/GnuPlot':
    $DBInfo->url_prefix.'/'.$cache_dir;
    $png=$cache_dir.'/'.$uniq.".$ext";
    $png_url=$cache_url.'/'.$uniq.".$ext";
  }

  $outpath=&$png;

  $src="
set term $term
set out '$outpath'
$plt
";

  if (!is_dir(dirname($png))) {
    $om=umask(000);
    _mkdir_p(dirname($png),0777);
    umask($om);
  }

  $log = '';
  if ($formatter->refresh || !file_exists($outpath)) {

     $flog=tempnam($vartmp_dir,"GNUPLOT");
     #
     # for Win32 wgnuplot.exe
     #
     if(getenv("OS")=="Windows_NT") {
       $finp=tempnam($vartmp_dir,"GNUPLOT");
       $ifp=fopen($finp,"w");
       fwrite($ifp,$src);
       fclose($ifp);

       $cmd= "$gnuplot \"$finp\" > $flog";
       $fp=system($cmd);
       $log=join(file($flog),"");
       if (file_exists($outpath)) {
         unlink($flog);
         unlink($finp);
       } else {
         print "<font color='red'>ERROR:</font> Gnuplot does not work correctly";
       }
     } else {
       #
       # Unix
       #
       $cmd= $gnuplot;
       $formatter->errlog('GnuPlot');
       $fp=popen($cmd.$formatter->LOG,"w");
       if (is_resource($fp)) {
         fwrite($fp,$src);
         pclose($fp);
       }
       $log=$formatter->get_errlog();
       if (filesize($outpath) == 0) {
         $log.="\n<font color='red'>ERROR:</font> Gnuplot does not work correctly";
         unlink($outpath);
       }
     }

     if ($log)
        $log ="<pre class='errlog'>$log</pre>\n";
  }

  $rext=$ext;
  $rpng_url=$png_url;

  if ($ext == 'ps' and file_exists($outpath)) {
     $routpath=preg_replace('/\.'.$ext.'$/','.png',$outpath);
     if ($formatter->refresh || !file_exists($routpath)) {
     	$cmd= "$convert -rotate 90 $outpath $routpath";
     	$fp=popen($cmd.$formatter->NULL,'r');
     	pclose($fp);
     }
     $rpng_url=preg_replace('/\.'.$ext.'$/','.png',$png_url);
     $rext='png';
  } else if ($ext == 'svg') {
     $fp=fopen($outpath,'r');
     if ($fp) {
       $svg=fread($fp,filesize($outpath));
       fclose($fp);

       $svg=preg_replace('/<svg [^>]+>/','<svg xmlns="http://www.w3.org/2000/svg"
     xmlns:xlink="http://www.w3.org/1999/xlink">',$svg);
       $fp=fopen($outpath,'w');
       if ($fp) {
         fwrite($fp,$svg);
         fclose($fp);
       }
     }
  }

  $bra = ''; $ket = '';
  if ($ext == 'ps') {
    $bra='<a href="'.$png_url.'" />';
    $ket='</a>';
  }

  if (!file_exists($outpath)) return $log;
  if ($rext == 'png')
     return $log.$bra."<img src='$rpng_url' alt='gnuplot' style='border:0' />".$ket;
  if ($rext == 'svg')
     return $log.$bra."<embed src='$rpng_url' alt='gnuplot' width='640' height='480' />".$ket;
  if ($rext == 'txt')
    return $log.'<pre class="gnuplot">'.(implode('',file("$cache_dir/$pngname"))).'</pre>';
}

?>
