<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// FootNote plugin
//
// *experimental*
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Since: 2003-04-16
// Name: FootNote macro plugin
// Description: make footnotes
// URL: MoniWiki:FootNoteMacro
// Version: $Revision$
// License: GPL
// Usage: [[FootNote(Hello World)]] or [* Hello World]
//
// $Id$

function macro_FootNote(&$formatter, $value = "", $options= array()) {
  if (!$value) {# emit all footnotes
    if (empty($formatter->foots)) return '';
    $foots=join("\n",$formatter->foots);
    $foots=preg_replace("/(".$formatter->wordrule.")/e","\$formatter->link_repl('\\1')",$foots);
    unset($formatter->foots);
    unset($formatter->rfoots);
    if ($foots)
      return "<div class='foot'><div class='separator'><tt class='wiki'>----</tt></div><ul>\n$foots</ul></div>";
    return '';
  }

  $formatter->foot_idx++;
  $idx=$formatter->foot_idx;

  $text="[$idx&#093;";
  $fnidx="fn".$idx;
  $ididx='';
  if ($value[0] == '*') {
    if (isset($value[1]) and $value[1] == '*') {
      # [** http://foobar.com] -> [*]
      # [*** http://foobar.com] -> [**]
      $p=strrpos($value,'*');
      $len=strlen(substr($value,1,$p));
      $text=str_repeat('*',$len);
      $value=substr($value,$p+1);

      if (empty($value)) {
        $formatter->foot_idx--; # undo ++.
        if (($k = array_search($text, $formatter->rfoots)) !== false) {
          $fnidx = $k;
          $text = $formatter->rfoots[$k];
        } else {
          // search empty slot
          $fnidx = 1;
          while (isset($formatter->rfoots[$myidx])) $fnidx++;
          $formatter->rfoots[$fnidx] = $text;
        }
        return "<tt class='foot'><a href='#fn$fnidx'>$text</a></tt>";
      }
    } else if (isset($value[1]) and $value[1] == '+') {
      $dagger=array('','&#x2020;',
                    '&#x2020;&#x2020;',
                    '&#x2020;&#x2020;&#x2020;',
                    '&#x2021;',
                    '&#x2021;&#x2021;',
                    '&#x2021;&#x2021;&#x2021;');
      $p=strrpos($value,'+');
      $len=strlen(substr($value,0,$p));
      $text=$dagger[$len];
      $value=substr($value,$p+1);
      if (empty($value)) {
        $formatter->foot_idx--; # undo ++.
        if (($k = array_search($text, $formatter->rfoots)) !== false) {
          $fnidx = $k;
          $text = $formatter->rfoots[$k];
        } else {
          // search empty slot
          $fnidx = 1;
          while (isset($formatter->rfoots[$myidx])) $fnidx++;
          $formatter->rfoots[$fnidx] = $text;
        }
        return "<tt class='foot'><a href='#fn$fnidx'>$text</a></tt>";
      }
    } else if (isset($value[1]) and $value[1] == ' ') {
      # [* http://c2.com] -> [1]
      $value=substr($value,2);
    } else {
      # [*ward http://c2.com] -> [ward]
      $text=strtok($value,' ');
      $value=strtok('');
      $fnidx=substr($text,1);
      $text[0]='[';
      $text=$text.'&#093;'; # make a text as [Alex77]

      if ($value) {
        if (is_numeric($fnidx)) {
          $fnidx="fn$fnidx";
        }
      } else {
        $formatter->foot_idx--; # undo ++.
        $attr = '';
        // no text given. [*1] => [1], [*-1] => [?] previous refer
        if (empty($fnidx)) { // [*]
          // search empty slot
          $myidx = 1;
          while (isset($formatter->rfoots[$myidx])) $myidx++;

          $text = '['.$myidx.'&#093';
          $formatter->rfoots[$myidx] = $text;
          $fnidx = 'fn'.$myidx;
          // no title attribute given now
          // $attr = " id='r$fnidx'";
        } else {
          if (is_numeric($fnidx)) {
            if ($fnidx < 0) { // relative reference
              $fnidx = $formatter->foot_idx + $fnidx + 1;
            }
          } else {
            // search empty slot
            $myidx = 1;
            while (isset($formatter->rfoots[$myidx])) $myidx++;
            $formatter->rfoots[$myidx] = $text;
          }
          if (!empty($formatter->rfoots[$fnidx])) {
            $text = $formatter->rfoots[$fnidx];
            if (preg_match('/\[([^\d\+\*]+)&/', $text, $m)) {
              $fnidx = $m[1];
            } else if (is_numeric($fnidx)) {
              $fnidx="fn$fnidx";
            }
          } else {
            $text = '['.$fnidx.'&#093';
            $formatter->rfoots[$fnidx] = $text;
            if (is_numeric($fnidx)) {
              $fnidx="fn$fnidx";
            }
          }
        }
        return "<tt class='foot'><a$attr href='#$fnidx'>$text</a></tt>";
      }
    }
  } else if ($value[0] == "[") {
    $dum=explode("]",$value,2);
    if (trim($dum[1])) {
       $text=$dum[0]."&#093;"; # make a text as [Alex77]
       $fnidx=substr($dum[0],1);
       $formatter->foot_idx--; # undo ++.
       if (is_numeric($fnidx)) $fnidx="fn$fnidx";
       $value=$dum[1]; 
    } else if ($dum[0]) {
       $text=$dum[0]."]";
       $fnidx=substr($dum[0],1);
       $formatter->foot_idx--; # undo ++.
       if (is_numeric($fnidx)) $fnidx="fn$fnidx";
       return "<tt class='foot'><a href='#$fnidx'>$text</a></tt>";
    }
  }

  if (empty($formatter->rfoots) or ($myidx = array_search($text, $formatter->rfoots)) === false) {
    // search empty slot
    $myidx = 1;
    while (isset($formatter->foots[$myidx])) $myidx++;
    $ididx=' id="fn'.$myidx.'"';
  }

  $formatter->foots[$myidx]="<li id='$fnidx'><tt class='foot'>".
                      "<a$ididx href='#r$fnidx'>$text</a></tt> ".
                      "$value</li>";
  #if (!empty($formatter->rfoots[$formatter->foot_idx])) return;
  $formatter->rfoots[$myidx] = $text;
  $tval=strip_tags(str_replace("'","&#39;",$value));
  return "<tt class='foot'>".
    "<a id='r$fnidx' href='#$fnidx' title='$tval'>$text</a></tt>";
}

// vim:et:sts=4:sw=4:
