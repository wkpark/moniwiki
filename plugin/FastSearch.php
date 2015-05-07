<?php
// from http://www.heddley.com/edd/php/search.html
// code itself http://www.heddley.com/edd/php/indexer.tar.gz
//
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
//
// indexer.tar.gz is modified to adopt under MoniWiki
// the indexer engine is a perl program, slightly modified by wkpark
// the lookup script also imported and modified.
//
// a FasetSearch plugin for the MoniWiki
//
// Usage: [[FastSearch(string)]]
//
// $Id: FastSearch.php,v 1.22 2010/08/22 08:40:23 wkpark Exp $

include_once('lib/indexer.DBA.php');

function macro_FastSearch($formatter,$value="",&$opts) {
  global $DBInfo;

  $default_limit = isset($DBInfo->fastsearch_limit) ? $DBInfo->fastsearch_limit : 30;

  if ($value === true) {
    $needle = $value = $formatter->page->name;
  } else {
    # for MoinMoin compatibility with [[FullSearch("blah blah")]]
    $needle = $value = preg_replace("/^('|\")([^\\1]*)\\1/","\\2",$value);
  }

  $needle=_preg_search_escape($needle);
  $pattern = '/'.$needle.'/i';

  $fneedle = _html_escape($needle);
  $url=$formatter->link_url($formatter->page->urlname);

  $arena = 'fullsearch';
  $check1 = 'checked="checked"';
  $check2 = $check3 = '';
  if (in_array($opts['arena'], array('titlesearch', 'fullsearch', 'pagelinks'))) {
    $check1 = '';
    $arena = $opts['arena'];
    if ($arena == 'fullsearch') $check1 = 'checked="checked"';
    else if ($arena == 'titlesearch') $check2 = 'checked="checked"';
    else $check3 = 'checked="checked"';
  }
  if (!empty($opts['backlinks'])) {
    $arena = 'pagelinks';
    $check1 = '';
    $check3 = 'checked="checked"';
  }

  $msg = _("Fast search");
  $msg2 = _("Display context of search results");
  $msg3 = _("Full text search");
  $msg4 = _("Title search");
  $msg5 = _("Link search");

  $form= <<<EOF
<form method='get' action='$url'>
   <input type='hidden' name='action' value='fastsearch' />
   <input name='value' size='30' value="$fneedle" />
   <span class='button'><input type='submit' class='button' value='$msg' /></span><br />
   <input type='checkbox' name='context' value='20' />$msg2<br />
   <input type='radio' name='arena' value='fullsearch' $check1 />$msg3
   <input type='radio' name='arena' value='titlesearch' $check2 />$msg4
   <input type='radio' name='arena' value='pagelinks' $check3 />$msg5<br />
   </form>
EOF;

  if (!isset($needle[0]) or !empty($opts['form'])) { # or blah blah
     $opts['msg'] = _("No search text");
     return $form;
  } else if (validate_needle($needle) === false) {
     $opts['msg'] = sprintf(_("Invalid search expression \"%s\""), $needle);
     return $form;
  }

  $DB=new Indexer_dba($arena,"r",$DBInfo->dba_type);
  if ($DB->db==null) {
    $opts['msg']=_("Couldn't open search database, sorry.");
    $opts['hits']= array();
    $opts['hit']= 0;
    $opts['all']= 0;
    return '';
  }
  $opts['form'] = $form;

  $sc = new Cache_text("searchkey");

  if ($arena == "pagelinks")
    $words = array($value);
  else
    $words = getTokens($value);
  // $words=explode(' ', strtolower($value));

  $idx = array();
  $new_words = array();
  foreach ($words as $word) {
    if ($sc->exists($word)) {
      $searchkeys = $sc->fetch($word);
    } else {
      $searchkeys = $DB->_search($word);
      $sc->update($word, $searchkeys);
    }
    $new_words = array_merge($new_words, $searchkeys);
    $new_words = array_merge($idx,$DB->_search($word));
  }
  $words = array_merge($words, $new_words);

  //
  $word = array_shift($words);
  $idx = $DB->_fetchValues($word);
  foreach ($words as $word) {
    $ids = $DB->_fetchValues($word); // FIXME
    foreach ($ids as $id) $idx[] = $id;
  }

  $init_hits = array_count_values($idx); // initial hits
  $idx = array_keys($init_hits);

  //arsort($idx);
  $all_count = $DBInfo->getCounter();

  $pages = array();
  $hits = array();
  foreach ($idx as $id) {
    $key= $DB->_fetch($id);
    $pages[$id]=$key;
    $hits['_'.$key] = $init_hits[$id]; // HACK. prefix '_' to numerical named pages
  }
  $DB->close();
  if (!empty($_GET['q']) and isset($_GET['q'][0])) return $pages;

  $context = !empty($opts['context']) ? $opts['context'] : 0;
  $limit = isset($opts['limit'][0]) ? $opts['limit'] : $default_limit;
  $contexts = array();

  if ($arena == 'fullsearch' || $arena == 'pagelinks') {
    $idx = 1;
    foreach ($pages as $page_name) {
      if (!empty($limit) and $idx > $limit) break;

      $p = new WikiPage($page_name);
      if (!$p->exists()) continue;
      $body = $p->_get_raw_body();
      $count = preg_match_all($pattern, $body,$matches); // more precisely count matches

      if ($context) {
        # search matching contexts
        $contexts[$page_name] = find_needle($body,$needle,'',$context);
      }
      $hits['_'.$page_name] = $count; // XXX hack for numerical named pages
      $idx++;
    }
  }

  //uasort($hits, 'strcasecmp');
  //$order = 0;
  //uasort($hits, create_function('$a, $b', 'return ' . ($order ? '' : '-') . '(strcasecmp($a, $b));'));
  $name = array_keys($hits);
  array_multisort($hits, SORT_DESC, $name, SORT_ASC);

  $opts['hits']= $hits;
  $opts['hit']= count($hits);
  $opts['all']= $all_count;
  if (!empty($opts['call'])) return $hits;

  $out = "<!-- RESULT LIST START -->"; // for search plugin
  $out.= "<ul>";
  $idx=1;
  while (list($page_name, $count) = each($hits)) {
    $page_name = substr($page_name, 1);
    $out.= '<!-- RESULT ITEM START -->'; // for search plugin
    $out.= '<li>'.$formatter->link_tag(_rawurlencode($page_name),
          "?action=highlight&amp;value="._urlencode($needle),
          $page_name,"tabindex='$idx'");
    if ($count > 1) {
      $out.= ' . . . . ' . sprintf((($count == 1) ? _("%d match") : _("%d matches")), $count );
      if (!empty($contexts[$page_name]))
        $out.= $contexts[$page_name];
    }
    $out.= "</li>\n";
    $out.= '<!-- RESULT ITEM END -->'; // for search plugin
    $idx++;

    if (!empty($limit) and $idx > $limit)
      break;
  }
  $out.= "</ul>\n";
  $out.= "<!-- RESULT LIST END -->"; // for search plugin

  return $out;
}

function do_fastsearch($formatter,$options) {
  global $DBInfo;

  $default_limit = isset($DBInfo->fastsearch_limit) ? $DBInfo->fastsearch_limit : 30;

  $rule = '';
  if ($options['action'] == 'titleindex' || isset($_GET['q'][0])) {
    $options['value'] = $_GET['q'];
    $options['arena'] = 'titlesearch';

    while (!empty($DBInfo->use_hangul_search)) {
      include_once("lib/unicode.php");
      $val= $_GET['q'];
      if (strtoupper($DBInfo->charset) != 'UTF-8' and function_exists('iconv')) {
        $val=iconv($DBInfo->charset,'UTF-8',$val);
      }
      if (!$val) break;

      $rule=utf8_hangul_getSearchRule($val);

      $test=@preg_match("/^$rule/",'');
      if ($test === false) $rule = $options['value'];
      break;
    }
    if (!$rule) $rule = trim($options['value']);
  }

  $ret=$options;

  $extra = '';
  if (!empty($options['backlinks']) || $options['arena'] == 'pagelinks') {
    $title= sprintf(_("BackLinks search for \"%s\""), $options['value']);
    $extra = '&amp;arena=pagelinks';
  } else if (!empty($options['titlesearch']) || $options['arena'] == 'titlesearch') {
    $title= sprintf(_("Title search for \"%s\""), $options['value']);
    if (!empty($options['titlesearch'])) $ret['arena'] = 'titlesearch';
    $extra = '&amp;arena=titlesearch';
  } else
    $title= sprintf(_("Full text search for \"%s\""), $options['value']);
  if ($rule)
    $out= macro_FastSearch($formatter,$rule,$ret);
  else
    $out= macro_FastSearch($formatter,$options['value'],$ret);

  if (isset($_GET['q'][0])) {
    header("Content-Type: text/plain");
    print join("\n",$out);
    return;
  }
  $options['msg']=!empty($ret['msg']) ? $ret['msg'] : '';
  $formatter->send_header("",$options);
  $formatter->send_title($title,$formatter->link_url("FindPage"),$options);

  if (!empty($ret['form']))
    print $ret['form'];
  print $out;

  $context = !empty($options['context']) ? $options['context'] : 0;
  $limit = isset($options['limit'][0]) ? $options['limit'] : $default_limit;

  if ($context)
    $extra = '&amp;context='.$context;

  if ($options['value']) {
    printf(_("Found %s matching %s out of %s total pages")."<br />\n",
         $ret['hit'],
        ($ret['hit'] == 1) ? _("page") : _("pages"),
         $ret['all']);

    if (!empty($limit) and $ret['hit'] > $limit) {
      $page = _urlencode($options['value']);
      echo $formatter->link_to("?action=fastsearch&amp;value=$page&amp;limit=0".$extra,
        sprintf(_("Show all %d results"), $ret['hit']))."<br />\n";
    }
  }
  $args['noaction']=1;
  $formatter->send_footer($args,$options);
}

?>
