<?php
// Copyright 2004-2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a importurl action plugin for the MoniWiki
//
// Usage: ?action=importurl&url=http://foo.bar.com/
//
// $Id: ImportUrl.php,v 1.10 2006/08/15 07:55:25 wkpark Exp $

function macro_ImportUrl($formatter,$value='',$options=array()) {
  $value=$value ? $value:$options['url'];

  if (!$value) {
    return <<<EOF
<div>
<form method='get' action=''>
<input type='hidden' name='action' value='importurl' />
<input name='url' value='http://' size='60' />
<input type='submit' value='html 2 wiki' />
</form>
</div>
EOF;
  }

  if (!preg_match('/^(http|ftp|https):\/\//',$value))
    return false;

  $fp = fopen("$value","r");
  if (!$fp) return false;

  while ($data = fread($fp, 4096)) $html_data.=$data;
  fclose($fp);

  # only use <body> contents
  preg_match("/<\s*body[^>]*>(.*)<\/\s*body\s*>/is",$html_data,$m);
  if ($m) $html_data=$m[1];
#  fix_url($value,$dummy);
#  fix_url('http://hello.com/',$dummy);
#  fix_url('http://hello.com',$dummy);

  # remove some tags
  $out=preg_replace("@<(script|style)[^>]*>.*</\\1>@is","",$html_data);

  # remove empty tags
  $out=preg_replace("@<(h.|).[^>]*></\\1>@i","",$out);

  # strip tags
  $out= strip_tags($out,'<pre><hr><td><tr><a><b><i><u><h1><h2><h3><h4><h5><li><img>');

  # fix some "\n" important sytaxes
  $out=preg_replace(array("/(?!\n)(\s*<h.[^>]*>)/i",
                          "/((<\/h.\s*>)(?:[ ]*)(?!\n))/i"),
                    array("\n\\1","\\2\n"),$out);

  $splits=preg_split('/(<pre\s*[^>]*>|<\/pre>)/', $out,
    -1, PREG_SPLIT_DELIM_CAPTURE);

  $wiki='';

  $base_url = $value;
  if (($p = strrpos($value, '/')) !== false) {
    $base_url = substr($value, 0, $p);
  }

  _fix_url_callback($base_url, true);
  _fix_url_callback2($base_url, true);
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
      $pre= str_replace(array("&quot;",'&lt;','&gt;','&amp;','<b>','</b>'),
                        array('"','<','>','&','',''),$pre);
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
    #$out= preg_replace("/<img\s*[^>]*src=(['\"])?((http|ftp)[^'\"]+)\\1[^>]*>/i",
    #  "\\2",$out);
    $out = preg_replace_callback("/<img\s*[^>]*src=(['\"])?([^'\"]+)\\1[^>]*>/i",
     '_fix_url_callback', $out);
    $out= preg_replace("/<li[^>]*>/i"," * ",$out);
    $out= preg_replace("/<\/li>\n*/i","\n",$out);
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
    $out= preg_replace("/<a\s*[^>]*href=['\"]#[^>]+>[^<]*<\/a>/i","",$out);
    # remove ?WikiName links
    $out= preg_replace("/<a\s*[^>]*href=['\"][^>]+>\?<\/a>/i","",$out);
    # remove hrefs with a blank link
    $out= preg_replace("/<a\s*[^>]*href=['\"][^>]+><\/a>/i","",$out);
    # url
    $out= preg_replace_callback("/<a\s*[^>]*href=['\"]([^'\"]+)['\"][^>]*>([^<]+)<\/a>/i",
      '_fix_url_callback2', $out);
    # heading
    $out= preg_replace_callback("/<h(\d)[^>]*>(?:\d+\.?\d*)*([^<]+)<\/h\d>/i",
      '_heading_callback', $out);
    # paragraph
    $out= preg_replace("/\n{3,}/","\n\n",$out);
    $out= preg_replace("/<b>([^<]+)<\/b>/i","'''\\1'''",$out);
    $out= preg_replace("/<i>([^<]+)<\/i>/i","''\\1''",$out);
    $out= preg_replace("/<u>([^<]+)<\/u>/i","__\\1__",$out);

    $wiki.=$out;
  }

  #$wiki=preg_replace(array("/\007\s/","/\007/"),array(" ",""),$wiki);
  return $wiki;

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
  $base_url=trim($base_url);
  $base_url=substr($base_url,-1,1)!='/' ? $base_url.'/':$base_url;
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

function _heading_callback($m) {
  $tag = str_repeat('=', $m[1]);
  return $tag.' '.$m[2].' '.$tag;
}

function _fix_url_callback($m, $init = false) {
  static $base_url;
  if ($init) {
    $base_url = $m;
    return;
  }
  fix_url($base_url, $m);
}

function _fix_url_callback2($m, $init = false) {
  static $base_url;
  if ($init) {
    $base_url = $m;
    return;
  }
  return '[['.fix_url($base_url, $m).trim($m[2]).']]';
}

function fix_url($base_url,$url,$text='') {
  static $path=array();

  if (is_array($url)) {
    // for callback function
    $m = $url;
    $url = array_pop($m);
    if ($tmp == '"' || $tmp == "'") {
      $url = array_pop($m);
    }
    if (count($m)) {
      $text = array_pop($m);
    }
  }

  if ($url== $text) return '';
  if (!$base_url) $path=array(); // reset
  else if (!count($path)) $path=prep_url($base_url);

  if (substr($url,0,7)=='mailto:') {
    if (substr($url,7) == $text) return '';
    return $url.' ';
  }

  $p=strpos($url,'://');
  if ($p !== false) {
    $type=substr($url,0,$p);
    if ($type == 'http' or $type == 'ftp') return $url.' ';
  }

  if ($url[0] == '/') {
    if (substr($url,1,5)=='imgs/') return '';
    else if (substr($url,1,8)=='wiki.php') {
      preg_match('/value=(.*)\.(gif|png|jpeg|jpg)$/',$url,$m);
      if ($m[2])
        return 'attachment:'.$m[1].'.'.$m[2];

      return '';
    }
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

function do_ImportUrl($formatter,$options=array())
{
  $value=$options['url'];

  if (!preg_match('/^(http|ftp|https):\/\//',$value)) {
    do_invalid($formatter,$options);
    return;
  }

  $ret=macro_ImportUrl($formatter,$value,$options);

  $formatter->send_header("content-type: text/plain",$options);
  print $ret;
}

// vim:et:sts=2:
?>
