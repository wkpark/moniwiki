<?php
// Copyright 2004-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Navigation plugin for the MoniWiki
//
// Usage: [[Navigation(IndexPage)]]
//
// $Id: Navigation.php,v 1.17 2010/08/23 15:14:10 wkpark Exp $

function macro_Navigation($formatter,$value) {
  global $DBInfo;

  preg_match('/([^,]+),?\s*(.*)/',$value,$match);
  if ($match) {
    $opts=explode(',',$match[2]);
    $value=$match[1];
  }
  if (!$value or !$DBInfo->hasPage($value))
    return '[[Navigation('._("No Index page found").')]]';

  $use_action=0;
  if (in_array('action',$opts)) $use_action=1;

  $pg=$DBInfo->getPage($value);
  $lines=explode("\n",$pg->get_raw_body());

  $group='';#$formatter->group;
  $current=$formatter->page->name;
  if ($formatter->group)
    $current=$formatter->page->name;
  if (strpos($value,'~')) {
    $group=strtok($value,'~').'~';
    $page=strtok('');
  } else
    $page=$value;

#  print $current;

  $pagelinks = $formatter->pagelinks; // save
  if (empty($formatter->wordrule)) $formatter->set_wordrule();

  $indices=array();
  $count=0;
  foreach ($lines as $line) {
    if (preg_match("/^\s+(?:\*|\d+\.)\s*($formatter->wordrule)/",$line,$match)) {
      $word = trim($match[1], '[]"');

      list($index,$text,$dummy)= normalize_word($word,$group,$page);
      if ($group) $indices[]=$index;
      else $indices[]=$index;
      $texts[]=$text ? $text:$word;
      $count++;
    }
  }

  #print_r($indices);
  if ($count > 1) {
    $prev='';
    $next=($current == $page) ? 0:-1;
    $index_text=$value;
    if ($group) {
      $index=$value;
      $index_text=substr($index,strlen($group));
    }
    else $index=$value;
  }

  for ($i=0;$i<$count;$i++) {
    #print $indices[$i];
    #print ':'.$formatter->page->name;
    if ($indices[$i]==$current) {
      if ($i > 0) $prev=$i-1;
      if ($i < ($count - 1)) {
	$next=$i+1;
      }
    }
  }
  #print $prev.':'.$next;

  if ($count > 1) {
    if ($use_action) {
      $save=!empty($formatter->query_string) ? $formatter->query_string : '';
      $query='?action=navigation&amp;value='.$value;
      $formatter->query_string=$query;
    }
    $pnut='&laquo; ';
    if ($prev >= 0) {
      $prev_text=!empty($texts[$prev]) ? $texts[$prev] : '';
      $prev=!empty($indices[$prev]) ? $indices[$prev] : '';
      if (($p=strpos($prev,'~'))!==false)
        $prev_text=substr($prev,$p+1);
      if ($prev) {
        if (strpos($prev,':')===false) $prev='"'.$prev.'"';
        #$pnut.=$formatter->link_tag($prev, "", $prev_text," accesskey=\",\" ");
        $pnut.=$formatter->link_repl("[wiki:$prev $prev_text]"," accesskey=\",\" ");
      }
    }
    if ($use_action) $formatter->query_string=$save;
    $pnut.=" | ".$formatter->link_repl("[wiki:$index $index_text]")." | ";
    if ($use_action) $formatter->query_string=$query;
    if ($next >=0) {
      $next_text=$texts[$next];
      $next=$indices[$next];
      if (($p=strpos($next,'~'))!==false)
        $next_text=substr($next,$p+1);
      if (strpos($next,':')===false) $next='"'.$next.'"';
      # to make wiki:"My Page" to fix PR #301055
      #$pnut.=$formatter->link_tag($next, "", $next_text, " accesskey=\".\" ");
      $pnut.=$formatter->link_repl("[wiki:$next $next_text]"," accesskey=\".\" ");
    }
    $pnut.=' &raquo;';
    if ($use_action) $formatter->query_string=$save;
  }
  $formatter->pagelinks = $pagelinks; // restore
  if (!empty($pnut))
    return $pnut;
  return '';
}

function do_navigation($formatter,$options) {
  if (empty($formatter->wordrule)) $formatter->set_wordrule();
  $pnut=macro_Navigation($formatter,$options['value'].',action');
  $formatter->send_header('',$options);
  $formatter->send_title('', $formatter->link_url("FindPage"),$options);
  print "<div class='wikiNavigation'>\n";
  print $pnut;
  print "<hr /></div>\n";
  $formatter->send_page();
  print "<div class='wikiNavigation'>\n<hr />";
  print $pnut;
  print "</div>\n";
  $formatter->send_footer('',$options);
}

// vim:et:sts=2:
?>
