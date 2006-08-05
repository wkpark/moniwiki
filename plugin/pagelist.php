<?php
// Copyright 2003-2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a PageList plugin for the MoniWiki
//
// Usage: [[PageList(a needle for list,dir,info,date]]
//
// $Id$

function macro_PageList($formatter,$arg="") {
  global $DBInfo;

  preg_match("/([^,]*)(\s*,\s*)?(.*)?$/",$arg,$match);
  if ($match[1]=='date') {
    $options['date']=1;
    $arg='';
  } else if ($match) {
    $arg=$match[1];
    $options=array();
    if ($match[3]) $options=explode(",",$match[3]);
    if (in_array('date',$options)) $options['date']=1;
    if (in_array('dir',$options)) $options['dir']=1;
    if (in_array('info',$options)) $options['info']=1;
    else if ($arg and (in_array('metawiki',$options) or in_array('m',$options)))
      $options['metawiki']=1;
  }
  $needle=_preg_search_escape($arg);

  $test=@preg_match("/$needle/","",$match);
  if ($test === false) {
    # show error message
    return "[[PageList(<font color='red'>Invalid \"$arg\"</font>)]]";
  }

  if ($options['date']) {
    $tz_offset=&$formatter->tz_offset;
    $all_pages = $DBInfo->getPageLists($options);
  } else {
    if ($options['metawiki'])
      $all_pages = $DBInfo->metadb->getLikePages($needle);
    else
      $all_pages = $DBInfo->getPageLists();
  }

  $hits=array();

  if ($options['date']) {
    if ($needle) {
      while (list($pagename,$mtime) = @each ($all_pages)) {
        preg_match("/$needle/",$pagename,$matches);
        if ($matches) $hits[$pagename]=$mtime;
      }
    } else $hits=$all_pages;
    arsort($hits);
    while (list($pagename,$mtime) = @each ($hits)) {
      $out.= '<li>'.$formatter->link_tag(_rawurlencode($pagename),"",
	htmlspecialchars($pagename)).
	". . . . [".gmdate("Y-m-d",$mtime+$tz_offset)."]</li>\n";
    }
    $out="<ol>\n".$out."</ol>\n";
  } else {
    foreach ($all_pages as $page) {
      preg_match("/$needle/",$page,$matches);
      if ($matches) $hits[]=$page;
    }
    sort($hits);
    if ($options['dir']) {
        $dirs=array();
        $files=array();
        foreach ($hits as $pagename) {
            if (($p=strpos($pagename,'/'))!==false) {
                $name=substr($pagename,0,$p);
                $dirs[$name]=$name;
                continue;
            }
            $files[$pagename]=$pagename;
        }
        $iconset='tango';
        $icon_dir=$DBInfo->imgs_dir.'/plugin/UploadedFiles/'.$iconset;
        $dicon="<img src='$icon_dir/folder-16.png' width='16px'/>";
        $ficon="<img src='$icon_dir/text-16.png' width='16px'/>";
        $now=time();
        foreach ($dirs as $pg) {
            $out.= '<tr><td>'.$dicon.'</td><td>'.
                $formatter->link_tag(_rawurlencode($pg),"",
	    htmlspecialchars($pg)).'</td>';
            if ($options['info']) {
                $p=new WikiPage($pg);
                $mtime=$p->mtime();
                $time_diff=(int)($now - $mtime)/60;
                if ($time_diff < 1440)
                    $date=sprintf(_("[%sh %sm ago]"),(int)($time_diff/60),$time_diff%60);
                else
                    $date=date("Y/m/d H:i",$mtime);        
                $out.='<td>'.$date.'</td>';
            }
            $out.="</tr>\n";
            if (isset($files[$pg])) unset($files[$pg]);
        }
        foreach ($files as $pg) {
            $out.= '<tr><td>'.$ficon.'</td><td>'.
                $formatter->link_tag(_rawurlencode($pg),"",
	    htmlspecialchars($pg)).'</td>';
            if ($options['info']) {
                $p=new WikiPage($pg);
                $mtime=$p->mtime();
                $time_diff=(int)($now - $mtime)/60;
                if ($time_diff < 1440)
                    $date=sprintf(_("[%sh %sm ago]"),(int)($time_diff/60),$time_diff%60);
                else
                    $date=date("Y/m/d H:i",$mtime);        
                $out.='<td>'.$date.'</td>';
            }
            $out.="</tr>\n";
        }
        $out='<table>'.$out.'</table>';
    } else {
    foreach ($hits as $pagename) {
      $out.= '<li>' . $formatter->link_tag(_rawurlencode($pagename),"",
	htmlspecialchars($pagename))."</li>\n";
    }
    $out="<ul>\n".$out."</ul>\n";
    }
  }

  return $out;
}

// vim:et:sts=2:
?>
