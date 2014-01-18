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
// Version: $Revision: 1.3 $
// License: GPL
// Usage: [[FootNote(Hello World)]] or [* Hello World]
//
// $Id: FootNote.php,v 1.3 2010/08/28 17:02:47 wkpark Exp $

function macro_FootNote(&$formatter, $value = "", $options= array()) {
    if (empty($formatter->foot_offset))
        $formatter->foot_offset = 0;
    if (empty($value)) {# emit all footnotes
        if (empty($formatter->foots)) return '';
        $foots = array_slice($formatter->foots, $formatter->foot_offset);
        //asort($foots);
        $foots = join("\n", $foots);
        $formatter->foot_offset = max(array_keys($formatter->foots));
        $foots = preg_replace_callback("/(".$formatter->wordrule.")/",
            array(&$formatter, 'link_repl'), $foots);
        //unset($formatter->foots);
        if ($foots)
            return "<div class='foot'><div class='separator'><tt class='wiki'>----</tt></div><ul>\n$foots</ul></div>";
        return '';
    }

    $text = $tag = '';
    if ($value[0] == '*') {
        if (!isset($value[1])) {
            // [*] - auto-numbering
            $value = '';
        } else if ($value[1] == ' ') { // FIXME
            // [* http://c2.com] -> [1] - auto-numbering
            $value = substr($value, 2);
        } else if ($value[1] == '*') {
            // star symbols
            // [** http://foobar.com] -> [*] // auto-numbering star
            // [*** http://foobar.com] -> [**] // manual-numbering star
            $p = strrpos($value,'*');
            $len = strlen(substr($value, 1, $p));

            $tag = str_repeat('*', $len);
            $value = substr($value, $p + 1);

            $fnref = '';
        } else if ($value[1] == '+') {
            // dagger symbols
            // [*+ http://foobar.com] -> [+] // auto-numbering dagger
            // [*++ http://foobar.com] -> [++] // manual-numbering dagger
            $dagger = array('&#x2020;', '&#x2021;');
            $p = strrpos($value, '+');
            $len = strlen(substr($value, 0, $p));

            $dag = $len % 2;
            if ($len < 4 and $dag != 0) {
                $tag = str_repeat($dagger[0], $len);
            } else {
                $ddag = intval($len / 2);
                $tag = str_repeat($dagger[1], $ddag);
                $tag.= str_repeat($dagger[0], $dag);
            }

            $value = substr($value, $p + 1);

            $fnref = '';
        } else {
            // [*ward http://c2.com] -> [ward] - labeled
            // [*3 http://c2.com] -> [3] - manually numbered
            $text = strtok($value,' ');
            $tag = substr($text, 1);
            $value = strtok('');
            if (!is_numeric($tag)) $fnref = $tag;
        }
    } else if ($value[0] == '[' and preg_match('/^\[([^\]]+)\](.*)$/', $value, $m)) {
        $tag = $m[1];
        $value = trim($m[2]);
    }

    if (empty($value)) {
        // no text given
        if (empty($tag)) {
            // [*] - auto-numbering
            // search empty slot
            $tagidx = $formatter->tag_offset + 1;
            while (isset($formatter->rfoots[$tagidx])) $tagidx++;

            $tag = $tagidx;
            $fnref = 'fn'.$tagidx;
            // no title attribute given now
            // $attr = " id='r$fnidx'";
        } else {
            // manual number/labeling
            // [*1] => [1], [*label] [*-1] => [?] previous refer
            if (is_numeric($tag)) {
                if ($tag < 0) {
                    // XXX relative to max reference number
                    if (!empty($formatter->rfoots)) {
                        $cur = max(array_keys($formatter->rfoots));
                        $tag = $cur + $tag + 1; // -1 => max ref num
                    } else { // XXX Error XXX just ignore it
                        $tag = abs($tag);
                    }
                }
                $tagidx = $tag;
            } else {
                // [*label]
                // is it already defined footnote ?
                if (!empty($formatter->rfoots) and
                        ($myidx = array_search($tag, $formatter->rfoots)) !== false)
                {
                    $tagidx = $myidx;
                } else {
                    // search empty slot
                    $tagidx = $formatter->tag_offset + 1;
                    while (isset($formatter->rfoots[$tagidx])) $tagidx++;
                    //$tagidx = $tag;
                }
            }
        }

        // already defined ?
        if (!empty($formatter->rfoots[$tagidx])) {
            $tag = $formatter->rfoots[$tagidx];
            if (is_numeric($tagidx))
                $fnref = "fn$tagidx";
            if (preg_match('/^[a-zA-Z][a-zA-Z0-9-_]+$/', $tag))
                $fnref = $tag;
        } else {
            $formatter->rfoots[$tagidx] = $tag;
        }

        if (!isset($fnref)) {
            if (is_numeric($tag)) { // FIXME
                $fnref = "fn$tagidx";
            } else {
                $fnref = $tag;
            }
        }

        if (empty($fnref) and !is_numeric($tag))
            $fnref = "fn$tagidx";

        $text = '['.$tag.'&#093;';
        return "<tt class='foot'><a href='#$fnref'>$text</a></tt>";
    }

    $ididx = '';
    if (!empty($tag) and is_numeric($tag) and isset($formatter->foots[$tag])) {
        // oops!! it is already defined tag
        $tag = ''; // reset
    }
    if (empty($tag) or empty($formatter->rfoots) or
            ($myidx = array_search($tag, $formatter->rfoots)) === false)
    {
        // search empty slot
        $myidx = $formatter->foot_offset + 1;
        while (isset($formatter->foots[$myidx])) $myidx++;
        $ididx = ' id="fn'.$myidx.'"';
    }

    if (empty($tag)) $tag = $myidx;
    $text = '['.$tag.'&#093;';
    if (empty($fnref)) $fnref = "fn$myidx";

    $formatter->foots[$myidx] = "<li id='$fnref'><tt class='foot'>".
                      "<a$ididx href='#r$fnref'>$text</a></tt> ".
                      "$value</li>";

    #if (!empty($formatter->rfoots[$myidx])) return '';
    $formatter->rfoots[$myidx] = $tag;
    $title = strip_tags(str_replace("'", "&#39;", $value));
    return "<tt class='foot'>".
        "<a id='r$fnref' href='#$fnref' title='$title'>$text</a></tt>";
}

// vim:et:sts=4:sw=4:
