<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a BabelFish plugin for the MoniWiki
//
// Usage: [[BabelFish]]
//
// $Id$

#<script language="JavaScript1.2" src="http://www.altavista.com/r?entr"></script>
function macro_BabelFish($formatter,$value,$ret=array()) {
  $langs=array('ko','ja','en','de','fr','it','es','pt','zh');
  $supported=array('ko_en','en_ko','en_de','en_fr','en_pt','en_it','en_es',
                   'en_ja','en_zh','fr_en','es_en','pt_en','it_en','zh_en',
                   'fr_de','de_en','de_fr','ja_en','ru_en');
  $msg=_("BabelFish Translation");

  if (!$value)
    return <<<EOF
<script language="JavaScript1.2" src="http://www.altavista.com/r?entr"></script>
EOF;

  list($from,$to)=split('[ ,]',preg_replace("/\s+/",' ',strtolower($value)),2);
  if (!in_array($from,$langs))
    $from='en';
  if (!in_array($to,$langs))
    $to='en';
  if ($from and $to and $from != $to)
    $msg=sprintf(_("Translate %s to %s"),$from,$to);

  $lp=$from."_".$to;
  $URL=qualifiedUrl($formatter->link_url($formatter->page->urlname));
  #$TR="http://babelfish.altavista.com/babelfish/urlload?tt=url";
  $TR="http://babelfish.altavista.com/babelfish/tr?doit=done";
  #$TR="http://babelfish.altavista.com/babelfish/tr?doit=done&urltext=http://chemie.skku.ac.kr/wiki/wiki.php/BabelFishMacro&lp=en_ja
  if (in_array($lp,$supported)) {
    $URL=urlencode($URL);
    $TR.="&amp;lp=$lp";
  } else {
    // XXX not supported by http://babelfish.altavista.com/
    $lp=$from.'_en';
    $URL=urlencode($TR."&amp;lp=$lp&amp;url=$URL");
    $lp='en_'.$to;
    $TR.="&amp;lp=$lp";
  }
  $goto=$TR.'&amp;url='.$URL;
  return <<<EOF
<img src='$formatter->imgs_dir_interwiki$from-16.png' alt="$from"/> <a href="$goto"><img border='0' src='$formatter->imgs_dir/plugin/babelfish.gif' title='$msg' alt='BabelFish@altavista' /></a><img src='$formatter->imgs_dir_interwiki$to-16.png' alt="$to"/>
EOF;

}

function do_babelfish($formatter,$options) {
  global $DBInfo;

  $formatter->send_header('',$options);
  $formatter->send_title('','',$options);
  print macro_BabelFish($formatter,$options['value']);
  $formatter->send_page();
  $formatter->send_footer("",$options);
  return;
}

?>
