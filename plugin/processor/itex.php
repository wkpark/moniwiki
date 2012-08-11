<?php
// Copyright 2005-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a latex processor plugin for the MoniWiki
//
// Usage: {{{#!itex
// $ \alpha $
// }}}
//
// you can also replace inline latex processor with following config:
//  $inline_latex='itex';
//
// and replace the latex processor:
//  $processors=array('latex'=>'itex',...);
//
// $Id: itex.php,v 1.6 2010/04/19 11:26:47 wkpark Exp $

/*
  itex2MML.l patch to enable $$\alpha$$ syntax
*/

// fix MathML under HTML
// http://www.orcca.on.ca/MathML/texmml/mathml.dtd

function processor_itex($formatter="",$value="",$options='') {
    global $DBInfo;
    $patched=1;
    $use_javascript=1;
    $fix_mathml=1;

    if ($fix_mathml) {
        # fix output for the traditional XHTML XXX
        $fix_itex=array(
        '&Sum;'=>'&sum;',
        '&rightarrow;'=>'&rarr;',
        '&partial;'=>'&part;',
        '&PartialD;'=>'&part;',
        '&Integral;'=>'&int;',
        '&Hat;'=>'&#770;',
        '&geq;'=>'&#8805;',
        '&epsi;'=>'&#8714;',
        '&hbar;'=>'&#x210F;',
        '&RightVector;'=>'&#x021C0;',
        );
    }

    if ($use_javascript) {
        $flag = 0;
        $id=&$GLOBALS['_transient']['mathml'];
        if ( !$id) { $flag = 1; $id = 1; }
        if ( $flag ) {
            $script= "<script type=\"text/javascript\" src=\"" .
            $DBInfo->url_prefix ."/local/fixmathml.js\"></script>";
            if (preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT']))
                $script.='<object id="mathplayer"'.
                    ' classid="clsid:32F66A20-7614-11D4-BD11-00104BD3F987">'.
                    '</object>'.
                    '<?import namespace="mml" implementation="#mathplayer"?>';
        }
    }

    # site spesific variables
    $itex="itex2MML";
    $vartmp_dir=&$DBInfo->vartmp_dir;
    $cache_dir=$DBInfo->upload_dir."/itex";

    $type='block';
    if (!empty($options['type'])) $type=$options['type'];
  
    if ($value[0]=='#' and $value[1]=='!')
        list($line,$value)=explode("\n",$value,2);
  
    if (!$value) return;
  
    if (!file_exists($cache_dir)) {
        umask(000);
        mkdir($cache_dir,0777);
    }
    if ($patched) $src=$value; # with the patched itex2MML
    else  
        $src=preg_replace(array('/^\$\$/','/\$\$/'),array('\[','\]'),$value); # XXX
    $uniq=md5($src);
  
    $RM='rm';
    $NULL='/dev/null';
    if(getenv('OS')=='Windows_NT') {
        $RM='del';
        $NULL='NUL';
    }
    
    if (!empty($formatter->preview) || !empty($formatter->refresh) || !file_exists("$cache_dir/$uniq.xml")) {
        $srcpath="$vartmp_dir/$uniq.itex";
        $outpath="$cache_dir/$uniq.xml";

        $fp=fopen($srcpath,'w');
        fwrite($fp,$src);
        fclose($fp);
  
        $cmd= "$itex < $srcpath";
  
        $out='';
        $fp=popen($cmd.$formatter->NULL,'r');
        while($s = fgets($fp, 1024)) $out.= $s;
        pclose($fp);
        unlink($srcpath);

        #$out=preg_replace('/^<math /',"<math display='$type' ",$out);
        $fp=fopen($cache_dir."/$uniq".'.xml','w');
        fwrite($fp,$out);
        fclose($fp);
    }
    $out = '';
    $fp=fopen($cache_dir."/$uniq".'.xml','r');
    if (!$fp) return $src;
    while (!feof($fp)) $out .= fread($fp, 1024);
    @fclose($fp);
    #$out=preg_replace('/^<math [^>]+>/',"<math><mstyle fontsize='140%' displaystyle='true'>",$out);
    #$out=preg_replace('/<\/math>/',"</mstyle></math>",$out);

    if ($fix_mathml)
        $out = strtr($out,$fix_itex); # fix output for the traditional XHTML
    $out = "<div class='itex' id=\"mathml" . $id. "\">$out" .'</div>';
    if ($use_javascript)
        $out.= "<script type=\"text/javascript\">fixMmlById('mathml" .$id.
               "');</script>";
    $id++;
    return $script.$out;
}

// vim:et:sts=4:
?>
