<?php
// Copyright 2003-2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a FullSearch plugin for the MoniWiki
//
// $Id$

function do_fullsearch($formatter,$options) {

  $ret=$options;

  $options['value']=_stripslashes($options['value']);
  if ($options['backlinks'])
    $title= sprintf(_("BackLinks search for \"%s\""), $options['value']);
  else
    $title= sprintf(_("Full text search for \"%s\""), $options['value']);
  $out= macro_FullSearch($formatter,$options['value'],$ret);
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

function macro_FullSearch($formatter="",$value="", &$opts) {
  global $DBInfo;
  $needle=$value;
  if ($value === true) {
    $needle = $value = $formatter->page->name;
    $options['noexpr']=1;
  } else {
    # for MoinMoin compatibility with [[FullSearch("blah blah")]]
    #$needle = preg_replace("/^('|\")([^\\1]*)\\1/","\\2",$value);
    $needle = $value;
  }

  $url=$formatter->link_url($formatter->page->urlname);
  $fneedle=str_replace('"',"&#34;",$needle); # XXX

  $form= <<<EOF
<form method='get' action='$url'>
   <input type='hidden' name='action' value='fullsearch' />
   <input name='value' size='30' value='$fneedle' />
   <input type='submit' value='Go' /><br />
   <input type='checkbox' name='context' value='20' checked='checked' />Display context of search results<br />
   <input type='checkbox' name='backlinks' value='1' />Search BackLinks only<br />
   <input type='checkbox' name='case' value='1' />Case-sensitive searching<br />
   </form>
EOF;

  if (!$needle) { # or blah blah
     $opts['msg'] = 'No search text';
     return $form;
  }
  # XXX
  if ($opts['noexpr']) {
    $tmp=preg_split("/\s+/",$needle);
    $needle=$value=join('|',$tmp);
    $raw_needle=implode(' ',$tmp);
    $needle=_preg_search_escape($needle);
  } else {
    $needle=_preg_search_escape($needle);
    $terms = preg_split('/((?<!\S)[-+]?"[^"]+?"(?!\S)|\S+)/s',$needle,-1,
      PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);

    $common_words=array('the','that','where','what','who','how','too','are');
    $excl = array();
    $incl = array();
    $common = array();
    foreach($terms as $term) {
      if (trim($term)=='') continue;
      if (preg_match('/^([-+]?)("?)([^\\2]+?)\\2$/',$term,$match)) {
        $word=str_replace(array('/','-','\\','.','*'),'',$match[3]);
        $len=strlen($word);

        if (!$match[1] and $match[2] != '"') {
          if ($len <= 2 or in_array($word,$common_words)) {
            $common[]=$word;
            continue;
          }
        }

        if ($match[1]=='-') $excl[] = $word;
        else $incl[] = $word;
      }
    }
    $needle=implode('|',$incl);
    $raw_needle=implode(' ',$incl);
    $excl_needle=implode('|',$excl);
  }

  $test=@preg_match("/$needle/","",$match);
  $test2=@preg_match("/$excl_needle/","",$match);
  if ($test === false or $test2 === false) {
     $opts['msg'] = sprintf(_("Invalid search expression \"%s\""), $needle);
     return $form;
  }

  $hits = array();
  $pages = $DBInfo->getPageLists();
  $pattern = '/'.$needle.'/';
  if ($excl_needle)
    $excl_pattern = '/'.$excl_needle.'/';
  if ($opts['case']) {
    $pattern.="i";
    $excl_pattern.="i";
  }

  if ($opts['backlinks']) {
     $opts['context']=0; # turn off context-matching
     $cache=new Cache_text("pagelinks");
     foreach ($pages as $page_name) {
       $links==-1;
       $links=$cache->fetch($page_name);
       if ($links==-1) {
          $p= new WikiPage($page_name);
          $f= new Formatter($p);
          $links=$f->get_pagelinks();
       }
       $count= preg_match_all($pattern, $links, $matches);
       if ($count) {
         $hits[$page_name] = $count;
       }
     }
  } else {
     while (list($_, $page_name) = each($pages)) {
       $p = new WikiPage($page_name);
       if (!$p->exists()) continue;
       $body= $p->_get_raw_body();
       #$count = count(preg_split($pattern, $body))-1;
       $count = preg_match_all($pattern, $body,$matches);
       if ($count) {
         # search matching contexts
         $context= find_needle($body,$needle,$excl_needle,$opts['context']);
         if ($opts['context'] and $context) {
           $contexts[$page_name] = $context;
           $hits[$page_name] = $count;
         } else
           $hits[$page_name] = $count;
       }
     }
  }
  arsort($hits);

  $out.= "<ul>";
  reset($hits);
  $idx=1;
  while (list($page_name, $count) = each($hits)) {
    if ($opts['checkbox']) $checkbox="<input type='checkbox' name='pagenames[]' value='$page_name' />";
    $out.= '<li>'.$checkbox.$formatter->link_tag(_rawurlencode($page_name),
          "?action=highlight&amp;value="._urlencode($needle),
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

// vim:et:sts=2:
?>
