<?php
// Copyright 2004 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Jmol plugin for the MoniWiki
//
// http://jmol.sf.net
//
// $Id$

function processor_jmol($formatter,$value="") {
    $verbs=array('#sticks'=>'wireframe 0.25',
                '#ball&stick'=>'wireframe 0.18; spacefill 25%',
                '#wireframe'=>'wireframe 0.1',
                '#cpk'=>'cpk 80%',
                '#spacefill'=>'cpk %80',
                '#black'=>'background [0,0,0]',
                '#white'=>'background [255,255,255]',
                );
    $default_size="width='200' height='200' ";

    if ($value[0]=='#' and $value[1]=='!')
      list($line,$value)=explode("\n",$value,2);
    list($dum,$szarg)=explode(' ',$line);
    if ($szarg) {
      $args= explode('x',$szarg,2);
      $xsize=intval($args[0]);$ysize=intval($args[1]);
    }

    $body = $value;
    $args='<param name="emulate" value="chime" />';
    $args.='<param name="progressbar" value="true" />';

    $script='set frank off; wireframe 0.18; spacefill 25%;';
    if ($DBInfo->jmol_script) $script.=$DBInfo->jmol_script;

    while ($body and $body[0] == '#') {
        # extract first line
        list($line, $body) = explode("\n",$body, 2);

        # skip comments (lines with two hash marks)
        if ($line[1] == '#') continue;

        # parse the PI
        list($verb, $arg) = explode(' ',$line,2);
        $verb = strtolower($verb);
        $arg = rtrim($arg);

        if (array_key_exists($verb,$verbs)) {
            $script.=$verbs[$verb].';';
        }
    }

    if (!$args)
        $args.='<param name="style" value="sticks" />';
    if ($script)
        $args.='<param name="script" value="'.$script.'" />';

    if ($xsize) {
      if ($xsize > 640 or $xsize < 100) $xscale=0.5;
      if ($xscale and ($ysize > 480 or $ysize < 100)) $yscale=0.6;
      $xscale=$xsize/640.0;
    
      if (empty($yscale)) $yscale=$xscale/0.5*0.6;

      $size="width='$xsize' height='$ysize' ";
    } else $size=$default_size;

    $buff=str_replace("\n","|\n",$body)."\n";
    $molstring= trim($buff);

    $pubpath = $formatter->url_prefix.'/applets/JmolPlugin';

    return <<<APP
<applet code='JmolApplet.class' $size archive='$pubpath/JmolApplet.jar' codebase='$pubpath'>
        $args
        <param name='inline' value='$molstring' />
    Loading a JmolApplet object.
</applet>
APP;
}

// vim:et:sts=4:sw=4:
?>
