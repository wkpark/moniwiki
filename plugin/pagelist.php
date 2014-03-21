<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a PageList plugin for the MoniWiki
//
// Usage: [[PageList(a needle for list,dir,info,date]]
//
// $Id: pagelist.php,v 1.6 2010/04/19 11:26:46 wkpark Exp $

function macro_PageList($formatter,$arg="",$options=array()) {
  global $DBInfo;

  $offset = '';
  if (!is_numeric($options['offset']) or $options['offset'] <= 0) {
    unset($options['offet']);
  } else {
    $offset = $options['offset'];
  }

  preg_match("/([^,]*)(\s*,\s*)?(.*)?$/",$arg,$match);
  if ($match[1]=='date') {
    $options['date']=1;
    $arg='';
  } else if ($match) {
    $arg=$match[1];
    $opts=array();
    if ($match[3]) $opts=explode(",",$match[3]);
    if (in_array('date',$opts)) $options['date']=1;
    if (in_array('dir',$opts)) $options['dir']=1;
    if (in_array('subdir',$opts)) $options['subdir']=1;
    if (in_array('info',$opts)) $options['info']=1;
    else if ($arg and (in_array('metawiki',$opts) or in_array('m',$opts)))
      $options['metawiki']=1;
  }

  $upper = '';
  if (!empty($options['subdir'])) {
    if (($p = strrpos($formatter->page->name,'/')) !== false)
      $upper = substr($formatter->page->name,0,$p);
    $needle=_preg_search_escape($formatter->page->name);
    $needle='^'.$needle.'\/';
  } else if (!empty($options['rawre']))
    $needle = $arg;
  else
    $needle=_preg_search_escape($arg);

  $test=@preg_match("/$needle/","",$match);
  if ($test === false) {
    # show error message
    return "[[PageList(<font color='red'>Invalid \"$arg\"</font>)]]";
  }

  $ret = array();
  $options['ret'] = &$ret;
  $options['offset'] = $offset;
  if (!empty($options['date'])) {
    $tz_offset=&$formatter->tz_offset;
    $all_pages = $DBInfo->getPageLists($options);
  } else {
    if (!empty($options['metawiki']))
      $all_pages = $DBInfo->metadb->getLikePages($needle);
    else
      $all_pages = $DBInfo->getLikePages($needle);
  }

  $hits=array();

  $out = '';
  if (!empty($options['date']) and !is_numeric($k = key($all_pages)) and is_numeric($all_pages[$k])) {
    if ($needle) {
      while (list($pagename,$mtime) = @each ($all_pages)) {
        preg_match("/$needle/",$pagename,$matches);
        if ($matches) $hits[$pagename]=$mtime;
      }
    } else $hits=$all_pages;
    arsort($hits);
    while (list($pagename,$mtime) = @each ($hits)) {
      $out.= '<li>'.$formatter->link_tag(_rawurlencode($pagename),"",
	_html_escape($pagename)).
	". . . . [".gmdate("Y-m-d",$mtime+$tz_offset)."]</li>\n";
    }
    $out="<ol>\n".$out."</ol>\n";
  } else {
    foreach ($all_pages as $page) {
      preg_match("/$needle/",$page,$matches);
      if ($matches) $hits[]=$page;
    }
    sort($hits);
    if (!empty($options['dir']) or !empty($options['subdir'])) {
        $dirs=array();
        $files=array();
        if ($options['subdir']) $plen=strlen($formatter->page->name)+1;
        else $plen=0;
        foreach ($hits as $pagename) {
            if (($rp=strrpos($pagename,'/'))!==false) {
                $p=strpos($pagename,'/');
                $name=substr($pagename,$plen);
                $dum=explode('/',$name);
                if (sizeof($dum) > 1) {
                    $dirname = array_shift($dum);
                    $orgname = substr($pagename, 0, $p).'/'.$dirname;
                    if (empty($dirs[$orgname]))
                      $dirs[$orgname] = array();
                    $dirs[$orgname][] = implode('/', $dum);
                    $files[$orgname] = $dirname;
                } else {
                    $files[$pagename]=$name;
                }
                continue;
            }
            $files[$pagename]=$pagename;
        }
        $iconset='tango';
        $icon_dir=$DBInfo->imgs_dir.'/plugin/UploadedFiles/'.$iconset;
        $dicon="<img src='$icon_dir/folder-16.png' width='16px'/>";
        $uicon="<img src='$icon_dir/up-16.png' width='16px'/>";
        $ficon="<img src='$icon_dir/text-16.png' width='16px'/>";
        $now=time();
        if ($upper)
            $out.= '<tr><td>'.$uicon.'</td><td>'.
                $formatter->link_tag(_rawurlencode($upper),"",'..').'</td>';
            
        foreach ($dirs as $pg=>$name) {
            $out.= '<tr><td>'.$dicon.'</td><td>'.
                $formatter->link_tag(_rawurlencode($pg),"",
	    _html_escape($files[$pg])).'</td>';
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
        foreach ($files as $pg=>$name) {
            $out.= '<tr><td>'.$ficon.'</td><td>'.
                $formatter->link_tag(_rawurlencode($pg),"",
	    _html_escape($name)).'</td>';
            if (!empty($options['info'])) {
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
	_html_escape($pagename))."</li>\n";
    }
    $out="<ol>\n".$out."</ol>\n";
    $count = count($hits);
    $total = $DBInfo->getCounter();

    // hide the link of next page for anonymous user
    if (!empty($options['id']) and $options['id'] == 'Anonymous')
      return $out;

    if ($total > $count or $offset < $total) {
      if (isset($ret['offset']) and $ret['offset'] < $total and $count < $total) {
        $extra = '';
        if ($options['date']) $extra.='&amp;date=1';
        if ($options['info']) $extra.='&amp;info=1';
        if (isset($needle[0])) $extra.='&amp;value='.$needle;
        $qoff = '&amp;offset='.($ret['offset'] + $count);
        $out.= $formatter->link_to("?action=pagelist$extra$qoff", _("Show next page"));
      }
    }

    }
  }

  return $out;
}

function do_pagelist($formatter,$options=array()) {
  if (!is_numeric($options['offset']) or $options['offset'] <= 0)
    unset($options['offet']);

  $arg = '';
  if (isset($options['value'][0])) $arg = $options['value'];
  $formatter->send_header('', $options);
  $formatter->send_title('', '', $options);
  echo macro_PageList($formatter, $arg, $options);
  $formatter->send_footer('', $options);
}

// vim:et:sts=2:sw=2:
