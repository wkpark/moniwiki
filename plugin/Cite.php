<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a CITE macro plugin for the MoniWiki
//
// $Id: Cite.php,v 1.4 2010/04/19 11:26:46 wkpark Exp $

function macro_Cite($formatter="",$value="") {
  $CITE_MAP="CiteMap";
  $DEFAULT=<<<EOS
JCP,J.Chem.Phys. http://jcp.aip.org/jcp/top.jsp?vol=\$VOL&amp;pg=\$PAGE
JACS,J.Am.Chem.Soc. http://pubs.acs.org/journals/query/subscriberResults.jsp?Research=true&amp;yearrange1=ASAP&amp;yearrange3=current&amp;cit_qjrn=jacsat&styear=YYYY&endyear=YYYY&vol=\$VOL&spn=\$PAGE
JPC,J.Phys.Chem. http://pubs.acs.org/journals/query/subscriberResults.jsp?Research=true&amp;yearrange1=ASAP&amp;yearrange3=current&amp;cit_qjrn=jpchax&styear=YYYY&endyear=YYYY&vol=\$VOL&spn=\$PAGE
JPCA,J.Phys.Chem.A http://pubs.acs.org/journals/query/subscriberResults.jsp?Research=true&amp;yearrange1=ASAP&amp;yearrange3=current&amp;cit_qjrn=jpcafh&styear=YYYY&endyear=YYYY&vol=\$VOL&spn=\$PAGE
ChemRev,Chem.Rev. http://pubs.acs.org/journals/query/subscriberResults.jsp?Research=true&amp;yearrange1=ASAP&amp;yearrange3=current&amp;cit_qjrn=chreay&styear=YYYY&endyear=YYYY&vol=\$VOL&spn=\$PAGE
RMP,Rev.Mod.Phys. http://link.aps.org/volpage?journal=RMP&volume=\$VOL&id=\$PAGE
PR,Phys.Rev. http://link.aps.org/volpage?journal=PR&volume=\$VOL&id=\$PAGE
PRL,Phys.Rev.Lett. http://link.aps.org/doi/10.1103/PhysRevLett.\$VOL.\$PAGE
CPL,Chem.Phys.Lett. http://www.sciencedirect.com/science/journal/00092614

EOS;
  $CITE_list=array('JCP'=>array('http://jcp.aip.org/jcp/top.jsp?vol=$VOL&amp;pg=$PAGE','','J.Chem.Phys.'));

  $DEFAULT_CITE="JCP";
  $re_cite="/([A-Z][A-Za-z]*)?\s*([0-9\-]+\s*,\s*[0-9]+)/x";

  $test=preg_match($re_cite,$value,$match);
  if ($test === false)
     return "<p><strong class=\"error\">Invalid CITE \"%value\"</strong></p>";

  list($vol,$page)=explode(',',preg_replace('/ /','',$match[2]));

  if (!empty($match[1])) {
    if (strtolower($match[1][0])=="k") $cite="JKCS";
    else $cite=$match[1];
  } else $cite=$DEFAULT_CITE;

  $attr='';
  if (!empty($match[3])) {
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
  foreach ($lists as $line) {
     if (empty($line) or !preg_match("/^[A-Z]/",$line[0])) continue;
     $dum=explode(" ",rtrim($line));
     if (sizeof($dum) == 2)
        $dum[]=$CITE_list[$DEFAULT_CITE][1];
     else if (sizeof($dum) !=3) continue;

     list($dum[0],$name)=explode(',',$dum[0]);
     $CITE_list[$dum[0]]=array($dum[1],$dum[2],$name);
  }

  if (!empty($CITE_list[$cite])) {
     $citelink=$CITE_list[$cite][0];
     $imglink=$CITE_list[$cite][1];
     $citename=$CITE_list[$cite][2];
     if ($citename) $cite=str_replace('.','. ',$citename);
  } else {
     $citelink=$CITE_list[$DEFAULT_CITE][0];
     $imglink=$CITE_list[$DEFAULT_CITE][1];
  }

  $citelink=str_replace('$VOL',$vol,$citelink);
  $citelink=str_replace('$PAGE',$page,$citelink);

  return $formatter->icon['www'].'<a href='."'$citelink'>".
     $cite.' <strong>'.$vol.'</strong>, '.$page.'</a> ';
}

?>
