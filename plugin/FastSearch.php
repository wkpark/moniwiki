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
// a FasetSearch plugin using a index.db for the MoniWiki
//
// Usage: [[FastSearch(string)]]
//
// $Id$

include_once('lib/indexer.DBA.php');

function macro_FastSearch($formatter,$value="",&$opts) {
  global $DBInfo;

  if ($value === true) {
    $needle = $value = $formatter->page->name;
  } else {
    # for MoinMoin compatibility with [[FullSearch("blah blah")]]
    $needle = $value = preg_replace("/^('|\")([^\\1]*)\\1/","\\2",$value);
  }

  $needle=_preg_search_escape($needle);
  $pattern = '/'.$needle.'/i';
  $fneedle=str_replace('"',"&#34;",$needle); # XXX
  $url=$formatter->link_url($formatter->page->urlname);

  $msg = _("Fast search");
  $msg2 = _("Display context of search results");
  $form= <<<EOF
<form method='get' action='$url'>
   <input type='hidden' name='action' value='fastsearch' />
   <input name='value' size='30' value='$fneedle' />
   <span class='button'><input type='submit' class='button' value='$msg' /></span><br />
   <input type='checkbox' name='context' value='20' />$msg2<br />
   </form>
EOF;

  if (!$needle) { # or blah blah
     $opts['msg'] = _("No search text");
     return $form;
  }

  $DB=new Indexer_dba('fullsearch',"r",$DBInfo->dba_type);
  if ($DB->db==null) {
    $opts['msg']=_("Couldn't open search database, sorry.");
    $opts['hits']= 0;
    $opts['all']= 0;
    return '';
  }
  $opts['form'] = $form;

  $words = getTokens($value);
  // $words=explode(' ', strtolower($value));
  $keys='';
  $idx=array();
  foreach ($words as $word) {
    $idx=array_merge($idx,$DB->_fetchValues($word));
  }

  //arsort($idx);
  $all_pages = $DBInfo->getPageLists();
  $all_count = count($all_pages);
  unset($all_pages);

  $pages=array();
  foreach ($idx as $id) {
    $key= $DB->_fetch($id);
    $pages[]=$key;
    #print $key.'<br />';
  }
  $DB->close();

  $pages = array_unique($pages);
  usort($pages, 'strcasecmp');

  $hits=array();

  $context = !empty($opts['context']) ? $opts['context'] : 0;
  $contexts = array();

  foreach ($pages as $key) {
    $page_name = $key;
    $p = new WikiPage($page_name);
    if (!$p->exists()) continue;

    $body= $p->_get_raw_body();
    $count = preg_match_all($pattern, $body,$matches);
    if ($count) {
      $hits[$page_name] = $count;
      # search matching contexts
      $contexts[$page_name] = find_needle($body,$needle,'',$context);
    }
  }

  //uasort($hits, 'strcasecmp');
  //$order = 0;
  //uasort($hits, create_function('$a, $b', 'return ' . ($order ? '' : '-') . '(strcasecmp($a, $b));'));
  $name = array_keys($hits);
  array_multisort($hits, SORT_DESC, $name, SORT_ASC);

  $out = "<!-- RESULT LIST START -->"; // for search plugin
  $out.= "<ul>";
  reset($hits);
  $idx=1;
  while (list($page_name, $count) = each($hits)) {
    $out.= '<!-- RESULT ITEM START -->'; // for search plugin
    $out.= '<li>'.$formatter->link_tag(_rawurlencode($page_name),
          "?action=highlight&amp;value="._urlencode($needle),
          $page_name,"tabindex='$idx'");
    $out.= ' . . . . ' . $count . (($count == 1) ? _(" match") : _(" matches"));
    $out.= $contexts[$page_name];
    $out.= "</li>\n";
    $out.= '<!-- RESULT ITEM END -->'; // for search plugin
    $idx++;
  }
  $out.= "</ul>\n";
  $out.= "<!-- RESULT LIST END -->"; // for search plugin

  $opts['hits']= count($hits);
  $opts['all']= $all_count;
  return $out;
}

function do_fastsearch($formatter,$options) {

  $ret=$options;

  $title= sprintf(_("Full text search for \"%s\""), $options['value']);
  $out= macro_FastSearch($formatter,$options['value'],$ret);
  $options['msg']=!empty($ret['msg']) ? $ret['msg'] : '';
  $formatter->send_header("",$options);
  $formatter->send_title($title,$formatter->link_url("FindPage"),$options);

  if (!empty($ret['form']))
    print $ret['form'];
  print $out;

  if ($options['value'])
    printf(_("Found %s matching %s out of %s total pages")."<br />",
         $ret['hits'],
        ($ret['hits'] == 1) ? _("page") : _("pages"),
         $ret['all']);
  $args['noaction']=1;
  $formatter->send_footer($args,$options);
}

?>
