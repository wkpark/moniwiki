<?php
// Copyright 1999-2002 by Fred C. Yankowski <fcy@acm.org>, all rights
// reserved.
//
// $Id$
// wkpark@kldp.org 2003

function do_DeletePage($options) {
  global $DBInfo;
  
  $page = $DBInfo->getPage($options[page]);
  $html = new Formatter($page);

  if ($options[passwd]) {
    $check=$DBInfo[admin_passwd]==crypt($options[passwd],$DBInfo[admin_passwd]);
    if ($check) {
      $title = sprintf('"%s" is deleted !', $page->name);
      $html->send_header("",$title);
      $html->send_title($title);
      return;
    } else {
      $title = sprintf('Fail to delete "%s" !', $page->name);
      $html->send_header("",$title);
      $html->send_title($title);
      return;
    }
  }
  $title = sprintf('Delete "%s" ?', $page->name);
  $html->send_header("",$title);
  $html->send_title($title);
  print "<form method=POST>
Comment: <input name=comment size=80 value=''><br>
Password: <input type=password name=passwd size=20 value=''>
Only WikiMaster can delete this page<br>
    <input type=hidden name=action value='DeletePage'>
    <input type=submit value='Delete'>
    </form><hr>";
  $html->send_page($title);
  $html->send_footer();
}

function do_fullsearch($needle) {
  global $DBInfo;
  $page = new WikiPage("FullSearch");
  $html = new Formatter($page);
  $title = sprintf('Full text search for "%s"', $needle);
  $html->send_header("",$title);
  $html->send_title($title);

  $hits = array();
  $all_pages = $DBInfo->getPageLists();
  $pattern = '/'.$needle.'/i';

  while (list($_, $page_name) = each($all_pages)) {
    $p = new WikiPage($page_name);
    $body = $p->get_raw_body();
    $count = preg_match_all($pattern, $body, $matches);
    if ($count)
      $hits[$page_name] = $count;
  }
  arsort($hits);

  print "<UL>";
  reset($hits);
  while (list($page_name, $count) = each($hits)) {
    $p = new WikiPage($page_name);
    $h = new Formatter($p);
    print '<LI>' . $h->link_to();
    print ' . . . . ' . $count . (($count == 1) ? ' match' : ' matches');
  }
  print "</UL>";

  printf("Found %s matching %s out of %s total pages<br>",
	 count($hits),
	 (count($hits) == 1) ? 'page' : 'pages',
	 count($all_pages));
  $html->send_footer();
}

function do_titlesearch($title) {
  // Get this working???
  $msg = '<b>Sorry, Title Search is not working yet.</b>';
  $page = new WikiPage('TitleSearch');
  $page->send_page($msg);
}

function macro_InterWiki($formatter="") {
  global $Globals;

  $out="<table border=0 cellspacing=2 cellpadding=0>";
  foreach (array_keys($Globals[interwiki]) as $wiki) {
    $href=$Globals[interwiki][$wiki];
    $out.="<tr><td><tt><a href='$href"."RecentChanges'>$wiki</a></tt><td><tt>";
    $out.="<a href='$href'>$href</a></tt></tr>\n";
  }
  $out.="</table>\n";
  return $out;
}

if (function_exists ("iconv")) {
  function get_key($name) {
     return '?';
  }
} else {
  function get_key($name) {
    if (preg_match('/[a-z0-9]/i',$name[0])) {
       return strtoupper($name[0]);
    }
    # else EUC-KR
    $korean=array('가','나','다','라','마','바','사','아',
                  '자','차','카','타','파','하',"\xca");
    $lastPosition='~';

    $letter=substr($name,0,2);
    foreach ($korean as $position) {
       if ($position > $letter)
           return $lastPosition;
       $lastPosition=$position;
    }
    return '~';
  }
}

function macro_PageCount($formatter="") {
  global $DBInfo;

  return $DBInfo->getCounter();
}


function macro_PageList($formatter="",$arg="") {
  global $DBInfo;


  $all_pages = $DBInfo->getPageLists();
  $hits=array();
  foreach ($all_pages as $page) {
     preg_match("/$arg/",$page,$matches);
     if ($matches)
        $hits[]=$page;
  }

  sort($hits);

  $out="<UL>";
  foreach ($hits as $pagename) {
    $p = new WikiPage($pagename);
    $h = new Formatter($p);
    $out.= '<LI>' . $h->link_to()."\n";
  }

  return $out."</UL>\n";
}

function macro_TitleIndex($formatter="") {
  global $DBInfo;

  $all_pages = $DBInfo->getPageLists();
  sort($all_pages);

  $key="";
  $out="";
  $keys=array();
  foreach ($all_pages as $page) {
    if ($key != $page[0]) {
       if ($key)
          $out.="</UL>";
       $key=get_key($page);
#       $key=strtoupper($page[0]);
       $keys[]=$key;
       $out.= "<a name='$key'><h3><a href='#top'>$key</a></h3>\n";
       $out.= "<UL>";
    }
    
    $p = new WikiPage($page);
    $h = new Formatter($p);
    $out.= '<LI>' . $h->link_to();
  }
  $out.= "</UL>";

  $index="";
  foreach ($keys as $key)
    $index.= "|<a href='#$key'>$key</a>";
  $index[0]="";
  
  return "<center><a name='top'>$index</center>\n$out";
}

function macro_Icon($formatter="",$value="") {
  global $DBInfo;

  $out=$DBInfo->url_prefix."/imgs/$value";
  $out="<img src='$out' border=0>";
  return $out;
}

function macro_RecentChanges($formatter="") {
  global $DBInfo;

  $lines = $DBInfo->editlog_raw_lines();
  $lines = reverse($lines);
    
  $time_current = time();
  $secs_per_day = 60*60*24;
  $days_to_show = 30;
  $time_cutoff = $time_current - ($days_to_show * $secs_per_day);

  $out="";
  $ratchet_day = FALSE;
  $done_words = array();
  while (list($_, $line) = each($lines)) {
    $parts = explode("\t", $line);
    $page_name = $parts[0];
    $addr = $parts[1];
    $ed_time = $parts[2];

    if ($ed_time < $time_cutoff)
      break;

    if (! empty($done_words[$page_name]))
      continue;			// reported this page already
    $done_words[$page_name] = TRUE;

    $day = date('Y/m/d', $ed_time);
    if ($day != $ratchet_day) {
#      flush();
      $out.=sprintf('<h3>%s</h3>', date($DBInfo->date_fmt, $ed_time));
      $ratchet_day = $day;
    }

    $p = new WikiPage($page_name);
    $h = new Formatter($p);
    $out.= $h->link_to("?action=diff",$DBInfo->icon[diff])." ";
    $out.= $h->link_to();

    if ($DBInfo->show_hosts) {
      $out.= ' . . . . ';
      if (! isset($ip_to_host[$addr])) {
	$ip_to_host[$addr] = gethostbyaddr($addr);
      }
      $out.= $ip_to_host[$addr];
    }
    if (! empty($DBInfo->changed_time_fmt))
      $out.= date($DBInfo->changed_time_fmt, $ed_time);
    $out.= '<br>';
  }
  return $out;
}

function reverse($arrayX) {
  $out = array();
  $size = count($arrayX);
  for ($i = $size - 1; $i >= 0; $i--)
    $out[] = $arrayX[$i];
  return $out;
}

function macro_HTML($formatter="",$value="") {
  return $value;
}

function macro_TableOfContents($formatter="") {
  if ($formatter->TOC) {
     $close="";
     $depth=$formatter->head_dep;
     while ($depth>0) { $depth--;$close.="</dl></dd>\n"; };
     return $formatter->TOC.$close;
  }
  else return "";
}

function macro_FullSearch($formatter="",$value="") {
  return "<form method=GET>
    <input type=hidden name=action value='fullsearch'>
    <input name=value size=30 value='$value'>
    <input type=submit value='Go'><br />
    <input type='checkbox' name='context' value='20' checked>Display context of search results<br />
    <input type='checkbox' name='case' value='1'>Case-sensitive searching<br />

    </form>";
}

function macro_TitleSearch($formatter="",$value="") {
  return "<form method=GET>
    <input type=hidden name=action value='titlesearch'>
    <input name=value size=30 value='$value'>
    <input type=submit value='Go'>
    </form>";
}

function macro_GoTo($formatter="",$value="") {
  return "<form method=GET>
    <input type=hidden name=action value='goto'>
    <input name=value size=30 value='$value'>
    <input type=submit value='Go'>
    </form>";
}
?>
