<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a BabelFish plugin for the MoniWiki
//
// Usage: [[BabelFish]]
//
// $Id$

#<script language="JavaScript1.2" src="http://www.altavista.com/r?entr"></script>
function macro_BabelFish($formatter,$value) {
  $langs=array('ko','ja','en','de','fr','it','es','pt','zh');
  $supported=array('ko_en','en_ko','en_de','en_fr','ko_jp','jp_ko');
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
  $URL=qualifiedUrl($formatter->link_url(urlencode($formatter->page->urlname)));
  $TR="http://babelfish.altavista.com/babelfish/urlload?tt=url";
  if (in_array($lp,$supported)) {
    $TR.="&lp=$lp";
  } else {
    $lp=$from.'_en';
    $URL=urlencode($TR."&lp=$lp&url=").$URL;
    $lp='en_'.$to;
    $TR.="&lp=$lp";
  }
  return <<<EOF
<img src='$formatter->imgs_dir/$from-16.png' /> <a href="$TR&url=$URL"><img border='0' src='$formatter->imgs_dir/fishloop.gif' title='$msg' alt='BabelFish@altavista' /></a><img src='$formatter->imgs_dir/$to-16.png' />
EOF;

}

function do_babelfish($formatter,$options) {
  $formatter->send_header();
  $formatter->send_title();
  $ret= macro_BabelFish($formatter,$options['value']);
  $formatter->send_page($ret);
  $formatter->send_footer("",$options);
  return;
}

?>
