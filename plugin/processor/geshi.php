<?php
// Copyright 2005 Dongsu Jang <iolo at hellocity.net>
// All rights reserved. Distributable under GPL see COPYING
// a geshi colorizer plugin for the MoniWiki
//
// Usage: {{{#!geshi ada|apache|asm|c|css... [number|fancy]
// some codes
// }}}
//
// or you can replace the vim processor by following option in config.php
//  $myprocessors=array('vim'=>'geshi');
// 
// to use this processor:
// download GeSHi-x.x.x.tar.gz from http://qbnz.com/highlighter/
// and extract it under .../moniwiki/lib directory and rename into 'geshi'
// it looks like after intsall:
// .../moniwiki/
//   |
//   |-- lib/geshi/
//   |  |-- geshi.php (mandatory)
//   |  |-- geshi/... (syntax files here.. not used syntics may be removed)
//   |  |-- contrib/ .. (examples and so on.. could be removed)
//   |  |-- docs/ .. (documents.. could be removed)
//   |
//   |-- plugin/processor/geshi.php (this file)
//   |
//
// this version was tested with geshi 1.0.6.
//
// $Id$

@include_once(dirname(__FILE__)."/../../lib/geshi/geshi.php");

if (defined('GESHI_VERSION')):
function processor_geshi($formatter,$value,$options) {
  global $DBInfo;

  $syntax=array(
    'actionscript', 'ada', 'apache', 'asm', 'asp', 'bash', 'c', 'c_mac',
    'caddcl', 'cadlisp', 'cpp', 'csharp', 'css-gen', 'css', 'delphi',
    'html4strict', 'java', 'javascript', 'lisp', 'lua', 'nsis', 'objc',
    'oobas', 'oracle8', 'pascal', 'perl', 'php-brief', 'php', 'python',
    'qbasic', 'smarty', 'sql', 'vb', 'vbnet', 'visualfoxpro', 'xml'
  );

  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);
  # get parameters
  if ($line)
    list($tag,$type,$extra)=explode(" ",$line,3);
  $src=$value;
  if (!$type) $type='nosyntax';

  $uniq=md5($option.$src);
  if ($DBInfo->cache_public_dir) {
    $fc=new Cache_text('geshi',2,'html',$DBInfo->cache_public_dir);
    $htmlname=$fc->_getKey($uniq,0);
    $html= $DBInfo->cache_public_dir.'/'.$htmlname;
  } else {
    $cache_dir=$DBInfo->upload_dir."/GeshiProcessor";
    $html=$cache_dir.'/'.$uniq.'.html';
  }

  if (!is_dir(dirname($html))) {
    $om=umask(000);
    _mkdir_p(dirname($html),0777);
    umask($om);
  }

  if (file_exists($html) && !$formatter->refresh) {
    $out = "";
    $fp=fopen($html,"r");
    while (!feof($fp)) $out .= fread($fp, 1024);
    return $out;
  }

  # comment out the following two lines to freely use any syntaxes.
  if (!in_array($type,$syntax)) 
    return "<pre class='code'>\n$line\n".htmlspecialchars($src)."\n</pre>\n";

  $geshi = new GeSHi($src, $type, dirname(__FILE__)."/../../lib/geshi/geshi");
  if ($extra == "number") {
    $geshi->enable_line_numbers(GESHI_NORMAL_LINE_NUMBERS);
  } if ($extra == "fancy") {
    $geshi->enable_line_numbers(GESHI_FANCY_LINE_NUMBERS);
  } else {
    $geshi->enable_line_numbers(GESHI_NO_LINE_NUMBERS);
  }
  $out='';
  $geshi->set_comments_style(1, 'font-style: normal;');
  $geshi->set_header_type(GESHI_HEADER_DIV);
  #$geshi->set_header_type(GESHI_HEADER_PRE);
  #$out = '<style type="text/css"><!--'.$geshi->get_stylesheet().'--></style>';
  #$geshi->enable_classes();
  $out.= $geshi->parse_code();

  $fp=fopen($html,"w");
  fwrite($fp,$out);
  fclose($fp);

  return $out;
}

endif;

// vim:et:sts=2:
?>
