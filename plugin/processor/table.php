<?php
// Copyright 2003-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a table parser plugin for MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2014-02-12
// Name: a Table parser processor
// Description: Moniwiki Table parser
// URL: MoniWiki:TableParser
// Version: $Revision: 1.0 $
// License: GPLv2
//

/**
 * @author              Won-Kyu Park
 * @version             $Revision: 1.0 $
 * @license             GPLv2
 * @description         Moniwiki Table parser
 */

class tableParser {

    function tableParser()
    {
    }

    function _colspan($str, $align = '')
    {
        $len = strlen($str) / 2;
        if ($len == 1)
            return '';
        return ' colspan="'.$len.'"';
    }

    function _attr($attr, &$sty, $myclass = array(), $align = '')
    {
        $aligns = array('center'=>1, 'left'=>1, 'right'=>1);
        $attrs = preg_split('@(\w+[ ]*=[ ]*(?:"[^"]*"|\'[^\']*\'|[^"\']+)[ ]*)@',
            $attr, -1, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);

        $myattr = array();
        foreach ($attrs as $a) {
            $k = strtok($a, '=');
            $v = strtok('');
            $k = trim($k);
            $v = trim($v, " '\"");
            $k = strtolower($k);
            switch($k) {
                case 'style':
                    $chunks = preg_split('@[ ]*;[ ]*@', $v, -1, PREG_SPLIT_NO_EMPTY);
                    foreach ($chunks as $s) {
                        $nk = strtok($s, ':');
                        $nv = strtok('');
                        $nk = trim($nk);
                        $sty[$nk] = $nv;
                    }
                    break;
                case 'class':
                    if (isset($aligns[$v]))
                        $align = $v;
                    else
                        $myclass[] = $v;
                    break;
                case 'align':
                    $align = $v;
                    break;
                case 'bgcolor':
                    $sty['background-color'] = strtolower($v);
                    break;
                case 'border':
                case 'width':
                case 'height':
                case 'color':
                    $sty[$k] = strtolower($v);
                    break;
                default:
                    if ($v) $myattr[$k] = $v;
                    break;
            }
        }

        // set align as a class name
        if (!empty($align))
            $myclass[] = $align;
        if (!empty($myclass))
            $myattr['class'] = implode(' ', array_unique($myclass));

        if (!empty($sty)) {
            $mysty = '';
            foreach ($sty as $k => $v)
                $mysty .= $k.':'.$v.';';
            $myattr['style'] = $mysty;
        }

        return $myattr;
    }

    function _td($line, &$tr_attr, $wordrule = '') {
        $cells = preg_split('/((?:\|\|)+)/', $line, -1,
            PREG_SPLIT_DELIM_CAPTURE);

        $rows = array();
        for ($i = 1, $sz = sizeof($cells); $i < $sz; $i += 2) {
            $align = '';
            $m = array();
            preg_match('/^((<[^>]+>)*)([ ]?)(.*)(?<!\s)([ ]*)?(\s*)$/s',
                $cells[$i + 1], $m);
            $cell = $m[3].$m[4].$m[5];

            $l = $m[3];
            $r = $m[5];
            if (strpos($cell, "\n") !== false) {
                $cell = rtrim($cell, "\n"); // rtrim \n
                $cell = str_replace("\002\003", '||', $cell); // revert table separator ||

                // do not align multiline cells
                $l = '';
                $r = '';
            }
            if ($l and $r) $align = 'center';
            else if (!$l) $align = '';
            else if (!$r) $align = 'right';

            $tag = 'td';
            $attrs = $this->_td_attr($m[1], $align);
            if (!$tr_attr) $tr_attr = $m[1]; // XXX

            // check TD is header or not
            if (isset($attrs['heading'])) {
                $tag = 'th';
                unset($attrs['heading']);
            }
            $attr = '';
            foreach ($attrs as $k => $v) $attr .= $k.'="'.trim($v, "'\"").'" ';
            $attr .= $this->_colspan($cells[$i]);
            $rows[] = array('tag'=>$tag,
                'attributes' => $attr,
                'text' => $cell,
            );
        }
        return $rows;
    }

    function _td_attr(&$val, $align = '') {
        if (!$val) {
            if (!empty($align))
                return array('class' => $align);
            return array();
        }

        // split attributes <:><|3> => ':', '|3'
        $tmp = explode('><', substr($val, 1, -1));
        $paras = array();
        foreach ($tmp as $p) {
            // split attributes <(-2> => '(', '-2'
            if (preg_match_all('/([\^_v\(:\)\!]|[-\|]\d+|\d+%|#[0-9a-f]{6}|(?:colspan|rowspan)\s*=\s*\d+)/', $p, $m))
                $paras = array_merge($paras, $m[1]);
            else
                $paras[] = $p;
        }

        // rowspan
        $sty = array();
        $rsty = array();
        $attr = array();
        $rattr = array();
        $myattr = array();
        $myclass = array();

        foreach ($paras as $para) {
            if (preg_match("/^(\-|\|)(\d+)$/", $para, $match)) {
                if ($match[1] == '-')
                    $attr['colspan'] = $match[2];
                else
                    $attr['rowspan'] = $match[2];
                $para = '';
            }
            else if (strlen($para) == 1) {
                switch ($para) {
                    case '^':
                        $attr['valign'] = 'top';
                        break;
                    case 'v':
                    case '_':
                        $attr['valign'] = 'bottom';
                        break;
                    case '(':
                        $align='left';
                        break;
                    case ')':
                        $align='right';
                        break;
                    case ':':
                        $align='center';
                        break;
                    case '!':
                    case '=':
                        $attr['heading'] = true; // hack to support table header
                        break;
                    default:
                        break;
                }
            } else if ($para[0] == '#') {
                $sty['background-color'] = strtolower($para);
                $para = '';
            } else if (is_numeric($para[0])) {
                $attr['width'] = $para;
                $para = '';
            } else {
                if (substr($para, 0, 7) == 'colspan') {
                    $attr['colspan'] = trim(substr($para, 8), ' =');
                    $para = '';
                } else if (substr($para, 0, 7)=='rowspan') {
                    $attr['rowspan'] = trim(substr($para, 8), ' =');
                    $para = '';
                } else if (substr($para, 0, 3) == 'row') {
                    // row properties
                    $val = substr($para, 3);
                    $myattr = $this->_attr($val, $rsty);
                    $rattr = array_merge($rattr, $myattr);
                    continue;
                }
            }
            $myattr = $this->_attr($para, $sty, $myclass, $align);
            $attr = array_merge($attr, $myattr);
        }
        $myclass = !empty($attr['class']) ? $attr['class'] : '';
        unset($attr['class']);
        if (!empty($myclass))
            $attr['class'] = trim($myclass);

        // not parsed attributes
        $val = '';
        foreach ($rattr as $k => $v) $val .= $k.'="'.trim($v, "'\"").'" ';

        return $attr;
    }

    function _table_attr(&$attr)
    {
        $sty = array();
        $myattr = array();
        $mattr = array();
        $attrs = explode('><', substr($attr, 1, -1));
        $myclass = array();
        $rattr = array();
        $attr = '';
        foreach ($attrs as $tattr) {
            $tattr = trim($tattr);
            if (empty($tattr)) continue;
            if (substr($tattr, 0, 5)=='table') {
                $tattr = substr($tattr, 5);
                $mattr = $this->_attr($tattr, $sty, $myclass);
                $myattr = array_merge($myattr,$mattr);
            } else { // not table attribute
                $rattr[] = $tattr;
                #else $myattr=$this->_attr($tattr,$sty,$myclass);
            }
        }
        if (!empty($rattr)) $attr = '<'.implode('><',$rattr).'>';
        if (!empty($myattr['class']))
            $myattr['class'] = 'wiki '.$myattr['class'];
        else
            $myattr['class'] = 'wiki';
        $my = array();
        foreach ($myattr as $k => $v) $my[] = $k.'="'.$v.'"';

        if (isset($my[0]))
            return implode(' ', $my);
        return '';
    }

    function parse($text, &$offset)
    {
        $in_table = false;
        $indentlen = null;
        $indent = '';
        $offset = 0;

        $table = array(
            'type'=>'table',
        );

        $oline = '';
        $myoffset = 0;
        while ($myoffset !== false) {
            $inc = 1;
            if ($myoffset > 0) {
                if ($save_offset != $myoffset) {
                    if (!isset($text[$myoffset]))
                        $myoffset--; // fix for notice warning
                    $inc = substr_count($text, "\n", 0, $myoffset);
                }
                $text = substr($text, $myoffset);
            }
            if (($myoffset = strpos($text, "\n")) !== false) {
                $line = substr($text, 0, $myoffset);
                $myoffset++; // skip "\n"
            } else {
                $line = $text;
            }
            $save_offset = $myoffset; // save offset to set line ID correctly

            // get next line with pre block
            $pos = 0;
            while (strpos($line, '{{{', $pos) !== false) {
                // trial test
                $chunk = preg_replace_callback(
                    "/(({{{(?:(?:[^{}]+|{[^{}]+}(?!})|(?<!{){{1,2}(?!{)|(?<!})}{1,2}(?!}))|(?2))*+}}})|".
                    // unclosed inline pre tags
                    "(?:(?!<{{{){{{}}}(?!}}})|{{{(?:{{{|}}})}}}))/x",
                    create_function('$m', 'return str_repeat("_", strlen($m[1]));'), $line);

                // real test
                if (($p = strpos($chunk, '{{{', $pos)) !== false) {
                    if (preg_match("/^({{{(?:(?:[^{}]+|{[^{}]+}(?!})|(?<!{){{1,2}(?!{)|(?<!})}{1,2}(?!}))|(?1))*+}}})/x",
                            substr($text, $p), $matches)) {
                        $np = $p + strlen($matches[0]);

                        // protect || being parsed inside pre blocks
                        $matches[0] = str_replace('||', "\002\003", $matches[0]);
                        $tmp = substr($text, 0, $p);
                        $tmp .= $matches[0];
                        $tmp .= substr($text, $np);
                        $text = $tmp;

                        if (($lp = strpos($text, "\n", $np)) !== false) {
                            $line = substr($text, 0, $lp);
                            $myoffset = $lp + 1;
                            $pos = $np; // next remaining line offset

                            continue;
                        } else {
                            $line = $text;
                            $myoffset = strlen($text) + 1;
                        }
                    }
                }
                // fail to find pre block
                break;
            }

            if (!isset($oline[0]) and preg_match('/^[ ]*\|\|/', $line) and !preg_match('/(\|\||\|-+)[ ]*$/', $line)) {
                $oline .= $line;

                continue;
            } else if (isset($oline[0]) and ($in_table or (preg_match('/^[ ]*\|\|/',$oline)))) {
                if (!preg_match('/(\|\||\|-+)[ ]*$/', $line)) {
                    $oline .= "\n".$line;

                    continue;
                } else {
                    $line = $oline."\n".$line;
                    $oline = '';
                }
            }

            if (!trim($line)) {
                if ($in_table) {
                    $table['elements'] = $cols;
                    $in_table = false;
                }
                break;
            }

            if (preg_match('/^[ ]*/', $line, $match)) {
                $tmp = strlen($match[0]);
                if ($indentlen === null) {
                    $indentlen = $tmp;
                    $indent = $match[0];
                    $table['indent'] = $indentlen;
                }

                if ($in_table and $indentlen != $tmp) {
                    $table['elements'] = $cols;
                    $in_table = false;
                    break;
                }
                $line = substr($line, $indentlen);
            }

            if (!$in_table and $line[0] == '|' and
                    preg_match("/^[ ]*(\|([^\|]+)?\|((\|\|)*))((?:<[^>\|]+>)*)(.*)(\|\||\|-+)?[ ]*$/s", $line, $m))
            {
                $offset += strlen($indent.$line) + 1;

                $m[7] = isset($m[7]) ? $m[7] : '';

                $table_attr = $this->_table_attr($m[5]);
                if (isset($table_attr[0]))
                    $table['attribute'] = $table_attr;

                if ($m[2]) $table['caption'] = $m[2];
                $line = '||'.$m[3].$m[5].$m[6].$m[7];

                $in_table = true;
                $cols = array();
            } elseif ($in_table and $line[0] != '|') {
                $table['elements'] = $cols;
                $in_table = false;
                break;
            } else {
                $offset += strlen($indent.$line) + 1;
            }

            if ($in_table) {
                // remove trailing ||, |[-]+
                $line = preg_replace('/(\|\||\|-+)$/', '', $line);

                // split cells
                $cells = preg_split('/((?:\|\|)+)/', $line, -1, 
                    PREG_SPLIT_DELIM_CAPTURE);

                $rows = array();
                $tr_attr = '';
                for ($i = 1, $sz = sizeof($cells); $i < $sz; $i += 2) {
                    $align = '';
                    preg_match('/^((<[^>]+>)*)([ ]?)(.*)(?<!\s)([ ]*)?(\s*)$/s',
                        $cells[$i+1], $m);
                    $cell = $m[3].$m[4].$m[5];
                    if ($m[3] and $m[5]) $align = 'center';
                    else if (!$m[3]) $align = '';
                    else if (!$m[5]) $align = 'right';

                    if (isset($cell[0]) and substr($cell, -1) === "\n")
                        $cell = substr($cell, 0, -1).' '; // XXX
                    if (strpos($cell, "\n")) {
                        $cell = rtrim($cell, "\n"); // rtrim \n
                        $cell = str_replace("\002\003", '||', $cell); // XXX revert table separator ||

                        // multi-line cell
                        $align = '';
                    }

                    $tag = 'td';
                    $attrs = $this->_td_attr($m[1], $align);
                    if (!$tr_attr) $tr_attr = $m[1]; // XXX

                    // check TD is header or not
                    if (isset($attrs['heading'])) {
                        $tag = 'th';
                        unset($attrs['heading']);
                    }
                    $attr = array();
                    foreach ($attrs as $k => $v)
                        $attr[] = $k.'="'.trim($v, "'\"").'"';
                    if (isset($cells[$i][2]))
                        $attr[] = $this->_colspan($cells[$i]);
                    $row = array(
                            'tag'=>$tag,
                            'text'=>$cell,
                        );
                    if (isset($attr[0]))
                        $row['attribute'] = implode(' ', $attr);
                    $rows[] = $row;
                }
                $col = array(
                            'tag'=>'tr',
                            'elements'=>$rows,
                        );
                if (isset($tr_attr[0]))
                    $col['attribute'] = $tr_attr;
                $cols[] = $col;
            }
        }
        if ($in_table)
            $table['elements'] = $cols;

        if (isset($table['elements']))
            return $table;

        return null;
    }
}

if ((isset($_SERVER['argv']) and basename($_SERVER['argv'][0]) == basename(__FILE__)) ||
    (isset($_SERVER['SCRIPT_NAME']) and basename($_SERVER['SCRIPT_NAME']) == basename(__FILE__))):
$t = new tableParser();

$text = <<<EOF
|| bb cc
bbb || dd ee ||
    ||bbb || dd ee ||
||bbb || dd ee ||

aaa bb 

EOF;

$offset = 0;
$table = $t->parse($text, $offset);
print_r($table);

echo 'offset='.$offset;

$tmp = substr($text, $offset);
var_dump($tmp);

endif;

// vim:et:sts=4:sw=4:
