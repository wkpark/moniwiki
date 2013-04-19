<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a WordIndex plugin for the MoniWiki
//
// Usage: [[WordIndex]]
//
// $Id: WordIndex.php,v 1.9 2010/09/11 03:58:59 wkpark Exp $

function macro_WordIndex($formatter,$value, $params = array()) {
  global $DBInfo;

  $pagelinks=$formatter->pagelinks; // save
  $save=$formatter->sister_on;
  $formatter->sister_on=0;

  if (!empty($DBInfo->use_titlecache)) {
    $cache=new Cache_text('title');
  }

  $word_limit = 50;

  $start = 0;
  $prev = 0;
  if (!empty($params['start']) and is_numeric($params['start']))
    $start = $params['start'];
  if (!empty($params['prev']) and is_numeric($params['prev']))
    $prev = $params['prev'];

  $value = strval($value);
  if ($value == '' or $value == 'all') $sel = '';
  else $sel = $value;
  if (@preg_match('/'.$sel.'/i','') === false) $sel='';

  $keys = array();
  $dict = array();

  // cache wordindex
  $wc = new Cache_text('wordindex');
  $delay = !empty($DBInfo->default_delaytime) ? $DBInfo->default_delaytime : 0;

  $lock_file = _fake_lock_file($DBInfo->vartmp_dir, 'wordindex');
  $locked = _fake_locked($lock_file, $DBInfo->mtime());
  if ($locked or ($wc->exists('key') and $DBInfo->checkUpdated($wc->mtime('key'), $delay))) {
    if ($formatter->group) {
      $keys = $wc->fetch('key.'.$formatter->group);
      $dict = $wc->fetch('wordindex.'.$formatter->group);
    } else {
      $keys = $wc->fetch('key');
      $dict = $wc->fetch('wordindex');
    }

    if (empty($dict) and $locked) {
      // no cache found
      return _("Please wait...");
    }
  }

  if (empty($keys) or empty($dict)) {
    _fake_lock($lock_file);

    $all_pages = array();
    if ($formatter->group) {
      $group_pages = $DBInfo->getLikePages($formatter->group);
      foreach ($group_pages as $page)
        $all_pages[] = str_replace($formatter->group, '', $page);
    } else {
      $all_pages = $DBInfo->getPageLists();
    }

    foreach ($all_pages as $page) {
      if (!empty($DBInfo->use_titlecache) and $cache->exists($page))
        $title=$cache->fetch($page);
      else
        $title=$page;
      $tmp=preg_replace("/[\?!$%\.\^;&\*()_\+\|\[\]<>\"' \-~\/:]/"," ",$title);
      $tmp=preg_replace("/((?<=[A-Za-z0-9])[A-Z][a-z0-9])/"," \\1",ucwords($tmp));
      $words=preg_split("/\s+/",$tmp);
      foreach ($words as $word) {
        $word=ltrim($word);
        if (!$word) continue;
        $key = get_key($word);
        $keys[$key] = $key;
        if (!empty($dict[$key][$word])) {
          $dict[$key][$word][] = $page;
        } else {
          if (empty($dict[$key]))
            $dict[$key] = array();
          $dict[$key][$word] = array($page);
        }
      }
    }

    sort($keys);
    foreach ($keys as $k) {
      #ksort($dict[$k]);
      #ksort($dict[$k], SORT_STRING);
      #uksort($dict[$k], "strnatcasecmp");
      uksort($dict[$k], "strcasecmp");
    }
    if ($formatter->group) {
      $wc->update('key.'.$formatter->group, $keys);
      $wc->update('wordindex.'.$formatter->group, $dict);
    } else {
      $wc->update('key', $keys);
      $wc->update('wordindex', $dict);
    }

    _fake_lock($lock_file, LOCK_UN);
  }

  if (isset($sel[0]) and isset($dict[$sel])) {
    $selected = array($sel);
  } else {
    $selected = &$keys;
  }

  $out = '';
  $key = -1;
  $count = 0;
  $idx = 0;
  foreach ($selected as $k) {
    $words = array_keys($dict[$k]);
    $sz = count($words);
    for ($idx = $start; $idx < $sz; $idx++) {
      $word = $words[$idx];
      $pages = &$dict[$k][$word];
      $pkey = $k;
      if ($key != $pkey) {
        $key=$pkey;
        if (!empty($sel) and !preg_match('/^'.$sel.'/i', $pkey)) continue;
        if (!empty($out)) $out.="</ul>";
        $ukey=urlencode($key);
        $out.= "<a name='$ukey'></a><h3><a href='#top'>$key</a></h3>\n";
      }
      if (!empty($sel) and !preg_match('/^'.$sel.'/i',$pkey)) continue;

      $out.= "<h4>$word</h4>\n";
      $out.= "<ul>\n";
      foreach ($pages as $page)
        $out.= '<li>' . $formatter->word_repl('"'.$page.'"')."</li>\n";
      $out.= "</ul>\n";
      $count++;
      if ($count >= $word_limit) break;
    }
  }

  if (isset($sel[0])) {
    $last = count($dict[$sel]);
    $offset = $idx + 1;
    $pager = array();

    if ($start > 0) {
      // get previous start offset.
      $count = 0;
      $idx -= $word_limit - 1;
      if ($idx < 0) $idx = 0;

      $link = $formatter->link_url($formatter->page->name,'?action=wordindex&amp;sec='.$sel.'&amp;start='.$idx);
      $pager[] = "<a href='$link'>"._("&#171; Prev").'</a>';
    }
    if ($offset < $last) {
      $link = $formatter->link_url($formatter->page->name,'?action=wordindex&amp;sec='.$sel.'&amp;start='.$offset);
      $pager[] = "<a href='$link'>"._("Next &#187;").'</a>';
    }

    if (!empty($pager))
      $out.= implode(' | ', $pager) . "<br />\n";
  }

  $index = array();
  $tlink = '';
  if (isset($sel[0])) {
    $tlink = $formatter->link_url($formatter->page->name,'?action=wordindex&amp;sec=');
  }

  foreach ($keys as $key) {
    $name = strval($key);
    if ($key == 'Others') $name=_("Others");
    $ukey=urlencode($key);
    $link = !empty($tlink) ? preg_replace('/sec=/','sec='._urlencode($key), $tlink) : '';
    $index[] = "<a href='$link#$ukey'>$name</a>";
  }
  $str = implode(' | ', $index);
  $formatter->pagelinks = $pagelinks; // restore
  $formatter->sister_on= $save;

  return "<center><a name='top'></a>$str</center>\n$out";
}

function do_wordindex($formatter, $options) {
  $formatter->send_header('', $options);
  $formatter->send_title('', '', $options);
  echo macro_WordIndex($formatter, $options['sec'], $options);
  $formatter->send_footer($args, $options);
}

// vim:et:ts=2:
