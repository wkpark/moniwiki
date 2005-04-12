<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a CITE macro plugin for the MoniWiki
//
// $Id$

function macro_Cite($formatter="",$value="") {
  $CITE_MAP="CiteMap";
  $DEFAULT=<<<EOS
JCP http://jcp.aip.org/jcp/top.jsp?vol=\$VOL&amp;pg=\$PAGE
JPC http://pubs.acs.org/journals/query/subscriberResults.jsp?Research=true&amp;yearrange1=ASAP&amp;yearrange3=current&amp;cit_qjrn=jpchax&styear=YYYY&endyear=YYYY&vol=\$VOL&spn=\$PAGE
ChemRev http://pubs.acs.org/journals/query/subscriberResults.jsp?Research=true&amp;yearrange1=ASAP&amp;yearrange3=current&amp;cit_qjrn=chreay&styear=YYYY&endyear=YYYY&vol=\$VOL&spn=\$PAGE
EOS;

  $DEFAULT_CITE="JCP";
  $re_cite="/([A-Z][A-Za-z]*)?\s*([0-9\-]+\s*,\s*[0-9]+)/x";

  $test=preg_match($re_cite,$value,$match);
  if ($test === false)
     return "<p><strong class=\"error\">Invalid CITE \"%value\"</strong></p>";

  list($vol,$page)=explode(',',preg_replace('/ /','',$match[2]));

  if ($match[1]) {
    if (strtolower($match[1][0])=="k") $lang="JKCS";
    else $lang=$match[1];
  } else $lang=$DEFAULT_CITE;

  $attr='';
  if ($match[3]) {
    $args=explode(",",$match[3]);
    foreach ($args as $arg) {
      if ($arg == "noimg") $noimg=1;
      else {
        $name=strtok($arg,'=');
        $val=strtok(' ');
        $attr.=$name.'="'.$val.'" ';
        if ($name == 'align') $attr.='class="img'.ucfirst($val).'" ';
      }
    }
  }

  $list= $DEFAULT;
  $map= new WikiPage($CITE_MAP);
  if ($map->exists()) $list.=$map->get_raw_body();

  $lists=explode("\n",$list);
  $CITE_list=array();
  foreach ($lists as $line) {
     if (!$line or !preg_match("/^[A-Z]/",$line[0])) continue;
     $dum=explode(" ",rtrim($line));
     if (sizeof($dum) == 2)
        $dum[]=$CITE_list[$DEFAULT_CITE][1];
     else if (sizeof($dum) !=3) continue;

     $CITE_list[$dum[0]]=array($dum[1],$dum[2]);
  }

  if ($CITE_list[$lang]) {
     $citelink=$CITE_list[$lang][0];
     $imglink=$CITE_list[$lang][1];
  } else {
     $citelink=$CITE_list[$DEFAULT_CITE][0];
     $imglink=$CITE_list[$DEFAULT_CITE][1];
  }

  $citelink=str_replace('$VOL',$vol,$citelink);
  $citelink=str_replace('$PAGE',$page,$citelink);

  return $formatter->icon['www'].'<a href='."'$citelink'>".
     $lang.' <strong>'.$vol.'</strong>, '.$page.'</a>';
}

?>
