<?php
// Copyright 2004 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a importurl action plugin for the MoniWiki
//
// Usage: ?action=importurl&url=http://foo.bar.com/
//
// $Id$

function do_ImportUrl($formatter,$options) {
  $value=$options['url'];
  $fp = fopen("$value","r");
  if (!$fp) {
    do_invalid($formatter,$options);
    return;
  }

  while ($data = fread($fp, 4096)) $html_data.=$data;
  fclose($fp);

#  fix_url($value,$dummy);
#  fix_url('http://hello.com/',$dummy);
#  fix_url('http://hello.com',$dummy);

  $out= strip_tags($html_data,'<pre><hr><td><tr><a><b><i><u><h1><h2><h3><h4><h5><li><img>');

  $splits=preg_split('/(<pre\s*[^>]*>|<\/pre>)/', $out,
    -1, PREG_SPLIT_DELIM_CAPTURE);

  $wiki='';
  foreach ($splits as $split) {
    if (preg_match('/^<pre\s/i',$split)) {
      $state='p';
      if (preg_match("/<pre\s*class=.wikiSyntax.[^>]*>/i",$split))
        $pre='{{{#!vim';
      else if (preg_match("/<pre\s*class=.wiki.>/i",$split))
        $pre='{{{';
      else
        $pre='{{{#!';
      continue;
    } else if (preg_match('/^<\/pre>/i',$split)) {
      $state='';
      $pre.="}}}\n";
      $pre= str_replace(array("&quot;",'&lt;','&gt;','&amp;'),
                        array('"','<','>','&'),$pre);
      $wiki.=$pre;
      $pre='';
      continue;
    }
    if ($pre) {
      $pre.=$split;
      continue;
    }
    # remove leading spaces
    $out= preg_replace("/\n[ ]+/","\n",$split);
    $out= preg_replace("/\r/","",$out);
    $out= preg_replace("/<img\s*[^>]*src=['\"]((http|ftp)[^'\"]+)['\"][^>]*>/i",
      "\\1",$out);
    $out = preg_replace("/<img\s*[^>]*src=['\"]([^'\"]+)['\"][^>]*>/ie",
      "fix_url('$value','\\1')",$out);
    $out= preg_replace("/<b>([^<]+)<\/b>/i","'''\\1'''",$out);
    $out= preg_replace("/<i>([^<]+)<\/i>/i","''\\1''",$out);
    $out= preg_replace("/<u>([^<]+)<\/u>/i","__\\1__",$out);
    $out= preg_replace("/<li>/i"," * ",$out);
    $out= preg_replace("/<\/li>\n*/i","",$out);
    $out= preg_replace("/<td\s*[^>]*>/i","||",$out);
    $out= preg_replace("/<\/td>\n*/i","",$out);
    $out= preg_replace("/<tr\s*[^>]*>/i","",$out);
    $out= preg_replace("/<\/tr>\n*/i","||\n",$out);
    $out= preg_replace("/<hr\s*[^>]*>/i","----\n",$out);
    #
    $out= str_replace(array("&quot;",'&lt;','&gt;','&amp;'),
                      array('"','<','>','&'),$out);
    # for rendered wiki page
    #$out= preg_replace("/<pre\s*class=.wiki.>/i","{{{",$out);
    #$out= preg_replace("/<pre\s*[^>]*>/i","{{{#!vim config",$out);
    #$out= preg_replace("/<\/pre>/i","}}}\n",$out);
    # remove id tag and perma links
    $out= preg_replace("/<a\s*id=[^>]+>[^<]*<\/a>/i","",$out);
    $out= preg_replace("/<a\s*[^>]*href=['\"]#[^>]+>[^<]*<\/a>/i","",$out);
    # remove ?WikiName links
    $out= preg_replace("/<a\s*[^>]*href=['\"][^>]+>\?<\/a>/i","",$out);
    # url
    $out= preg_replace("/<a\s*[^>]*href=['\"]([^'\"]+)['\"][^>]*>([^<]+)<\/a>/ie",
      "'['.fix_url('$value','\\1').'\\2]'",$out);
    # heading
    $out= preg_replace("/<h(\d)[^>]*>(?:\d+\.?\d*)*([^<]+)<\/h\d>/ie",
      "str_repeat('=', \\1).' \\2 '.str_repeat('=', \\1)",$out);
    # paragraph
    $out= preg_replace("/\n{3,}/","\n\n",$out);

    $wiki.=$out;
  }

  $formatter->send_header("content-type: text/plain",$options);
  print $wiki;
  return;

  $options['savetext']=$out;
  $options['button_preview']=1;
  $formatter->send_header("",$options);
  $formatter->send_title(_("Import URL"),"",$options);
  #$ret= macro_Test($formatter,$options[value]);
  #$formatter->send_page($ret);
  print macro_EditText($formatter,$value,$options);
  $formatter->send_footer("",$options);
  return;
}

function prep_url($base_url) {
  $proto=strtok($base_url,'/').'//';
  $base_url=strtok('');
  $base_url=preg_replace('/(\/[^\/]+)$/','',$base_url);
  $root_url=strtok($base_url,'/');

  $path=array();
  $path[]=$proto.$root_url;
  
  while ($str=strtok('/')) {
    $path[]=current($path).'/'.$str;
    next($path);
  }
  return $path;
}

function fix_url($base_url,$url) {
  static $path=array();

  if (!$base_url) $path=array(); // reset
  else if (!count($path)) $path=prep_url($base_url);

  if (substr($url,0,7)=='mailto:') return $url;

  $p=strpos($url,'://');
  if ($p !== false) {
    $type=substr($url,0,$p);
    if ($type == 'http' or $type == 'ftp') return $url.' ';
  }

  if ($url[0] == '/') {
    if (substr($url,1,5)=='imgs/') return '';
    else if (substr($url,1,8)=='wiki.php') return '';
    return $path[0].$url.' ';
  } else if (preg_match('@^(\./)+@',$url)) {
    // base_url: http://foo.bar.com/hello/world/
    // img url: ./imgs/hello.gif
    $url = preg_replace('@^(\./)+@','',$url);
    return end($path).'/'.$url.' ';
  } else if (preg_match('@^(\.\./)+@',$url,$match)) {
    // base_url: http://foo.bar.com/hello/world/
    // img url: ../../imgs/hello.gif
    $url = preg_replace('@^(\.\./)+@','',$url);
    $sz=sizeof(explode('/',$match[1]));
    if ($sz > sizeof($path)) return $path[0].'/'.$url.' ';
    else {
      end($path);
      for ($j=1; $j<$sz;$j++) prev($path);
      return current($path).'/'.$url.' ';
    }
  }
  return end($path).'/'.$url.' ';
}

// vim:et:sts=2:
?>
