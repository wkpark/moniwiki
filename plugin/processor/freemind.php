<?php
// Copyright 2004 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a FreeMind plugin for the MoniWiki
//
// $Id$

function _interwiki_repl($formatter,$url) {
    global $DBInfo;

    if ($url[0]=="w")
      $url=substr($url,5);
    $dum=explode(":",$url,2);
    $wiki=$dum[0]; $page=$dum[1];
    if (!$page) {
      $page=$dum[0];
      return array($formatter->link_url($page));
    }

    $url=$DBInfo->interwiki[$wiki];
    # invalid InterWiki name
    if (!$url)
      return array();

    $urlpage=_urlencode(trim($page));
    #$urlpage=trim($page);
    if (strpos($url,'$PAGE') === false)
      $url.=$urlpage;
    else {
      # GtkRef http://developer.gnome.org/doc/API/2.0/gtk/$PAGE.html
      # GtkRef:GtkTreeView#GtkTreeView
      # is rendered as http://...GtkTreeView.html#GtkTreeView
      $page_only=strtok($urlpage,'#?');
      $query= substr($urlpage,strlen($page_only));
      #if ($query and !$text) $text=strtok($page,'#?');
      $url=str_replace('$PAGE',$page_only,$url).$query;
    }

    $img=$formatter->imgs_dir_interwiki.strtolower($wiki).'-16.png';
    if (preg_match("/\.(png|gif|jpeg|jpg)$/i",$url))
      $img=$url;

    return array($url,$img);
}

function _link_repl($formatter,$url) {
    if (preg_match("/\.(png|gif|jpeg|jpg)$/i",$url))
      $img=$url;
    return array($url,$img);
}

function processor_freemind($formatter,$value) {
    global $DBInfo;

    if ($value[0]=='#' and $value[1]=='!')
        list($line,$value)=explode("\n",$value,2);
    if ($line) {
    }

    $_dir=$DBInfo->upload_dir.'/FreeMind';
    if (!file_exists($_dir)) {
        umask(000); mkdir($_dir,0777);
    }

    $_FONT=array('Verdana','sans-serif');
    $_SIZE=array(12,20,16,14,12);
    $_COLOR=array('#ffcc33','#336699','#336600');

    $map=md5($value).'.mm';
    if (!file_exists($_dir.'/'.$map)) {
        $depth=$odepth=0;
        $dep=$odep=0;
        $out='<map version="0.7.1">'."\n";
        $lines= explode("\n",$value);
    
        foreach ($lines as $line) {
            preg_match('/^(\s+)\*\s?(.*)$/',$line,$m);
            if (!$m) continue;
            $dep=strlen($m[1]);
            if ($dep == $odep)
                $out.="</node>\n";
            else if ($dep > $odep)
                $depth++;
            else {
                while ($odep>=$dep) {
                    $out.="</node>\n";
                    $odep--;
                }
                $odep++;
            }
    
            if ($_FONT[$dep]) $FONT=$_FONT[$dep];
            else $FONT=$_FONT[0];
            if ($_SIZE[$dep]) $SIZE=$_SIZE[$dep];
            else $SIZE=$_SIZE[0];
            if ($_COLOR[$dep]) $COLOR=$_COLOR[$dep];
            else $COLOR=$_COLOR[0];
    
            $text=$m[2];
            $link='';
            $extra='';
            $img='';
            if (preg_match('/^(http|mailto|wiki):/',$text,$match)) {
                list($link,$text)=explode(' ',$text,2);
                if ($match[1]=='wiki') {
                    list($link,$img)=_interwiki_repl($formatter,$link);
                    $link='LINK="'.addslashes($link).'" ';
                    if ($img) $extra='<html><img src="'.$img.'"/></html>';
                } else {
                    list($link,$img)=_link_repl($formatter,$link);
                    $link='LINK="'.addslashes($link).'" ';
                    if ($img) $extra='<html><img src="'.$img.'"/></html>';
                }
            }
            $text=addslashes($text);
    
            $out.='<node '.$link.'COLOR="'.$COLOR.'" TEXT="'.$text.'">'."\n";
            $out.="<font NAME=\"$FONT\" SIZE=\"$SIZE\"/>\n";
            $out.=$extra;
      
            $odep=$dep;
        }
        for (;$odep!=0;$odep--) {
            $out.="</node>\n";
        }
    
        $out.='</map>'."\n";
        $fp=fopen($_dir.'/'.$map,'w');
        fwrite($fp,$out);
        fclose($fp);
    }

    $pubpath = $formatter->url_prefix.'/applets/FreeMind';
    $puburl = qualifiedUrl($formatter->url_prefix.'/'.$_dir);
    return <<<APP
<applet code="freemind.main.FreeMindApplet.class" codebase='$pubpath'
          archive="freemindbrowser.jar" width="100%" height="100%">
  <param name="type" value="application/x-java-applet">
  <param name="scriptable" value="true">
  <param name="modes" value="freemind.modes.browsemode.BrowseMode">
  <param name="browsemode_initial_map"
         value="$puburl/$map">
  <!--          ^ Put the path to your map here  -->
  <param name="initial_mode" value="Browse">
</applet>
APP;
}

// vim:et:sts=4:sw=4:
?>
