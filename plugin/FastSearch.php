<?php
// from http://www.heddley.com/edd/php/search.html
// code itself http://www.heddley.com/edd/php/indexer.tar.gz
//
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
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

function macro_FastSearch($formatter,$value="",$opts=array()) {
  global $DBInfo;
  $theDB=$DBInfo->data_dir."/index.db";

  if ($value === true) {
    $needle = $value = $formatter->page->name;
  } else {
    # for MoinMoin compatibility with [[FullSearch("blah blah")]]
    $needle = preg_replace("/^(\'|\")(.*)(\'|\")/","\\2",$value);
  }

  $needle=_preg_search_escape($value);
  $pattern = '/'.$needle.'/i';

  $form= <<<EOF
<form method='get' action='$url'>
   <input type='hidden' name='action' value='fastsearch' />
   <input name='value' size='30' value='$fneedle' />
   <input type='submit' value='Fast search' /><br />
   <input type='checkbox' name='context' value='20' checked='checked' />Display context of search results<br />
   </form>
EOF;

  if (!$needle) { # or blah blah
     $opts['msg'] = 'No search text';
     return $form;
  }

  if (($dbindex=@dba_open("$theDB", "r",$DBInfo->dba_type)) === false) {
    $opts['msg']="Couldn't open search database, sorry.";
    $opts['hits']= 0;
    $opts['all']= 0;
    return;
  }

  $words=split(' ', strtolower($value));
  $res=array();
  for(reset($words); $word=current($words); next($words)) {
    $t=strlen($keys=dba_fetch($word,$dbindex));

#   print "'$word' (" . $t/2 . ") ";
    for($i=0; $i<$t;
    // unpack a big-endian short
    $res[ord(substr($keys, $i, 1))*256+ord(substr($keys, $i+1, 1))]++, $i+=2);
  }
  arsort($res);

  $pages=array();
  for(reset($res); $k=key($res); next($res)) {
    $key= dba_fetch("!?" . chr($k/256) . chr($k % 256),$dbindex);
    $pages[]=$key;
  }
  dba_close($dbindex);
#  print_r($pages);

#  if ($opts['case']) $pattern.="i";

  $hits=array();

  foreach ($pages as $key) {
    $page_name= $DBInfo->keyToPagename($key);
    $p = new WikiPage($page_name);
    if (!$p->exists()) continue;

    $body= $p->_get_raw_body();
    $count = preg_match_all($pattern, $body,$matches);
    if ($count) {
      $hits[$page_name] = $count;
      # search matching contexts
      $contexts[$page_name] = find_needle($body,$needle,$opts['context']);
    }
  }

  arsort($hits);

  $out.= "<ul>";
  reset($hits);
  $idx=1;
  while (list($page_name, $count) = each($hits)) {
    $out.= '<li>'.$formatter->link_tag(_rawurlencode($page_name),
          "?action=highlight&amp;value="._urlencode($value),
          $page_name,"tabindex='$idx'");
    $out.= ' . . . . ' . $count . (($count == 1) ? ' match' : ' matches');
    $out.= $contexts[$page_name];
    $out.= "</li>\n";
    $idx++;
  }
  $out.= "</ul>\n";

  $opts['hits']= count($hits);
  $opts['all']= count($pages);
  return $out;
}

function do_fastsearch($formatter,$options) {

  $ret=$options;

  $title= sprintf(_("Full text search for \"%s\""), $options['value']);
  $out= macro_FastSearch($formatter,$options['value'],&$ret);
  $options['msg']=$ret['msg'];
  $formatter->send_header("",$options);
  $formatter->send_title($title,$formatter->link_url("FindPage"),$options);

  print $out;

  if ($options['value'])
    printf(_("Found %s matching %s out of %s total pages")."<br />",
         $ret['hits'],
        ($ret['hits'] == 1) ? 'page' : 'pages',
         $ret['all']);
  $args['noaction']=1;
  $formatter->send_footer($args,$options);
}

?>
