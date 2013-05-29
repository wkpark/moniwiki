<?php
// Copyright 2003-2013 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a BabelFish plugin for the MoniWiki
//
// Usage: [[BabelFish]]
//

function macro_BabelFish($formatter,$value,$ret=array()) {
  global $Config;

  $langs=array('ko','ja','en','de','fr','it','es','pt','zh');
  $supported=array('ko_en','en_ko','en_de','en_fr','en_pt','en_it','en_es',
                   'en_ja','en_zh','fr_en','es_en','pt_en','it_en','zh_en',
                   'fr_de','de_en','de_fr','ja_en','ru_en');
  $msg=_("BabelFish Translation");

  if (empty($value))
    $value = !empty($Config['default_babelfish_translation']) ?
        $Config['default_babelfish_translation'] : 'ko,en';

  list($from,$to)=preg_split('/,\s*/',preg_replace("/\s+/",' ',strtolower($value)),2);
  if (!in_array($from,$langs))
    $from='en';
  if (!in_array($to,$langs))
    $to='en';
  if ($from and $to and $from != $to)
    $msg=sprintf(_("Translate %s to %s"),$from,$to);

  $lp=$from."_".$to;
  $URL=qualifiedUrl($formatter->link_url($formatter->page->urlname));
  $TR="http://translate.google.com/translate?";
  if (in_array($lp,$supported)) {
    $URL=urlencode($URL);
    $TR.="sl=$from&amp;tl=$to";
  } else {
    // not supported translation case
    // from => en => to
    $URL=urlencode($TR."&amp;sl=$from&amp;tl=en&amp;url=$URL");
    $TR.="&amp;sl=en&amp;tl=$to";
  }
  $goto=$TR.'&amp;u='.$URL;
  return <<<EOF
<img src='$formatter->imgs_url_interwiki$from-16.png' alt="$from"/> <a href="$goto"><img border='0' src='$formatter->imgs_dir/plugin/babelfish.png' title='$msg' alt='Translation@Google' /></a> <img src='$formatter->imgs_url_interwiki$to-16.png' alt="$to"/>
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
