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

  $out= strip_tags($html_data, '<a><b><i><u><h1><h2><h3><h4><h5><li><img>');
  $out= preg_replace("/<img\s*[^>]*src=['\"]((http|ftp)[^'\"]+)['\"][^>]*>/",
    "\\1",$out);
  $out = preg_replace("/<img\s*[^>]*src=['\"]([^'\"]+)['\"][^>]*>/e",
    "fix_url('$value','\\1')",$out);
  $out= preg_replace("/<b>([^<]+)<\/b>/i","'''\\1'''",$out);
  $out= preg_replace("/<i>([^<]+)<\/i>/i","''\\1''",$out);
  $out= preg_replace("/<u>([^<]+)<\/u>/i","__\\1__",$out);
  $out= preg_replace("/<li>/i"," * \\1",$out);
  $out= preg_replace("/<\/li>/i","",$out);
  $out= preg_replace("/<h(\d)>([^<]+)<\/h\d>/ie",
    "str_repeat('=', \\1).' \\2 '.str_repeat('=', \\1)",$out);
  $out= preg_replace("/<a\s*[^>]*href=['\"]([^'\"]+)['\"][^>]*>([^<]+)<\/a>/ie",
    "'['.fix_url('$value','\\1').' \\2]'",$out);
  $out= preg_replace("/\r/","",$out);
  $out= preg_replace("/\n\s+/","\n",$out);
  $formatter->send_header("content-type: text/plain",$options);
  print $out;
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

  if ($url[0] == '/') {
    return $path[0].$url;
  } else if (preg_match('@^(\./)+@',$url)) {
    // base_url: http://foo.bar.com/hello/world/
    // img url: ./imgs/hello.gif
    $url = preg_replace('@^(\./)+@','',$url);
    return end($path).'/'.$url;
  } else if (preg_match('@^(\.\./)+@',$url,$match)) {
    // base_url: http://foo.bar.com/hello/world/
    // img url: ../../imgs/hello.gif
    $url = preg_replace('@^(\.\./)+@','',$url);
    $sz=sizeof(explode('/',$match[1]));
    if ($sz > sizeof($path)) return $path[0].'/'.$url;
    else {
      end($path);
      for ($j=1; $j<$sz;$j++) prev($path);
      return current($path).'/'.$url;
    }
  }
  return end($path).'/'.$url;
}

// vim:et:sts=2:
?>
