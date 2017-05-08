<?php
/**
 * Copyright 2003-2015 Won-Kyu Park <wkpark@gmail.com>
 * All rights reserved. Distributable under GPLv2 see COPYING
 *
 * the MoniWiki formatting processor extracted from the Formatter class
 *
 * @since       2015-12-21
 * @since       1.3.0
 * @name        Moniwiki Processor
 * @description Moniwiki default processor
 * @version     $Revision: 1.0 $
 * @depend      1.1.3
 * @license     GPLv2
 */

class processor_monicompat
{
    var $_type = 'wikimarkup';

    function processor_monicompat(&$formatter, $params = array())
    {
        $this->formatter = &$formatter;
    }

    function _list($on, $list_type, $numtype = '', $closetype = '',
            $divtype = ' class="indent"')
    {
        $close = ''; $open = '';
        $dtype = array('dd'=>'div', 'dq'=>'blockquote');
        if ($list_type == 'dd' or $list_type == 'dq') {
            if ($on)
                $list_type = $dtype[$list_type]."$divtype";
            else
                $list_type = $dtype[$list_type];
            $numtype = '';
        } else if ($list_type == 'dl') {
            if ($on)
                $list_type = 'dl';
            else
                $list_type = 'dd></dl';
            $numtype = '';
        } if (!$on and $closetype and !in_array($closetype, array('dd', 'dq')))
        $list_type = $list_type.'>'.$this->_purple().'</li';

        if ($on) {
            if ($numtype) {
                $lists = array(
                        'c'=>'circle',
                        's'=>'square',
                        'i'=>'lower-roman',
                        'I'=>'upper-roman',
                        'a'=>'lower-latin',
                        'A'=>'upper-latin',
                        'n'=>'none'
                        );
                $start = substr($numtype,1);
                $litype = '';
                if (array_key_exists($numtype{0}, $lists))
                    $litype=' style="list-style-type:'.$lists[$numtype{0}].'"';
                if (!empty($start)) {
                    #$litype[]='list-type-style:'.$lists[$numtype{0}];
                    return "<$list_type$litype start='$start'>";
                }
                return "<$list_type$litype>";
            }
            return "$close$open<$list_type>"; // FIX Wikiwyg
        } else {
            return "</$list_type>\n$close$open";
        }
    }

    function _check_p($in_p)
    {
        if ($in_p) {
            $in_p = 'li';
            return "</div>\n<div>"; #close
        }
        return '';
    }

    function _td_span($str, $align = '')
    {
        $len = strlen($str)/2;
        if ($len == 1) return '';
        $attr[] = "colspan='$len'"; #$attr[]="align='center' colspan='$len'";
        return ' '.implode(' ', $attr);
    }

    function _attr($attr, &$sty, $myclass = array(), $align = '')
    {
        $aligns = array('center'=>1,'left'=>1,'right'=>1);
        $attrs = preg_split('@(\w+\=(?:"[^"]*"|\'[^\']*\')\s*|\w+\=[^"\'=\s]+\s*)@',
            $attr, -1, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);

        $myattr = array();
        foreach ($attrs as $at) {
            $at = str_replace(array("'", '"'), '', rtrim($at));
            $k = strtok($at,'=');
            $v = strtok('');
            $k = strtolower($k);
            if ($k == 'style') {
                $stys = preg_split('@;\s*@', $v, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($stys as $my) {
                    $nk = strtok($my, ':');
                    $nv = strtok('');
                    $sty[$nk] = $nv;
                }
            } else {
                switch($k) {
                    case 'class':
                        if (isset($aligns[$v]))
                            $align = $v;
                        else $myclass[] = $v;
                        break;
                    case 'align':
                        $align=$v;
                        break;
                    case 'bgcolor':
                        $sty['background-color'] = strtolower($v);
                        break;
                    case 'border':
                        if (intval($v) == $v and isset($v)) {
                            $myattr[$k] = $v;
                            break;
                        }
                    case 'width':
                    case 'height':
                    case 'color':
                        $sty[$k]=strtolower($v);
                        break;
                    case 'bordercolor':
                        $sty['border'] = 'solid '.strtolower($v);
                        break;
                    default:
                        if ($v) $myattr[$k] = $v;
                        break;
                }
            }
        }

        if ($align) $myclass[] = $align;
        if ($myclass) $myattr['class'] = implode(' ', array_unique($myclass));
        if ($sty) {
            $mysty='';
            foreach ($sty as $k=>$v) $mysty .= "$k:$v;";
            $myattr['style'] = $mysty;
        }
        return $myattr;
    }

    function _td($line, &$tr_attr, $wordrule = '')
    {
        $cells = preg_split('/((?:\|\|)+)/', $line, -1,
                PREG_SPLIT_DELIM_CAPTURE);
        $row = '';
        for ($i = 1, $s = sizeof($cells); $i < $s; $i += 2) {
            $align = '';
            $m = array();
            preg_match('/^((&lt;[^>]+>)*)([ ]*)(.*?)([ ]*)?(\s*)$/s',
                    $cells[$i + 1], $m);
            $cell = $m[3].$m[4].$m[5];

            // count left, right spaces to align
            $l = strlen($m[3]);
            $r = strlen($m[5]);

            // strip last "\n"
            if (substr($cell, -1) == '\n')
                $cell = substr($cell, 0, -1);
            if (strpos($cell,"\n") !== false) {
                // strip first space.
                if ($cell[0] == ' ' and !preg_match('/^[ ](?:(\d+|i|a|A)\.|[*])[ ]/', $cell))
                    $cell = substr($cell, 1);
                $cell = str_replace("\002\003", '||', $cell); // revert table separator ||
                $params = array('notoc'=>1);
                $cell = str_replace('&lt;', '<', $cell); // revert from baserule
                $cell = strtr($cell, array('\\}}}'=>'}}}', '\\{{{'=>'{{{')); // FIXME
                $cell = $this->process($cell, $params);
                $cell = str_replace('&lt;', '<', $cell); // revert from baserule
                // do not align multiline cells
                $l = '';
                $r = '';
            } else if (isset($wordrule[0])) {
                $cell = preg_replace_callback("/(".$wordrule.")/",
                    array(&$this->formatter, 'link_repl'), $cell);
            }

            // set table alignment
            if ($l and $r) {
                if ($l > 0 and $r > 0) {
                    if ($l == $r) {
                        if ($l == 1 and $this->formatter->markdown_style)
                            $align = '';
                        else
                            $align = 'center';
                    } else if ($this->formatter->markdown_style) {
                        if ($l > 1 and $r > 1)
                            $align = 'center';
                        else if ($l > 1)
                            $align = 'right';
                    }
                }
                else if ($l > 1)
                    $align = 'right';
            }
            else if (!$l) $align='';
            else if (!$r) $align='right';

            $tag = 'td';
            $attrs = $this->_td_attr($m[1], $align);
            if (!$tr_attr) $tr_attr=$m[1]; // XXX

            // check TD is header or not
            if (isset($attrs['heading'])) {
                $tag = 'th';
                unset($attrs['heading']);
            }
            $attr = '';
            foreach ($attrs as $k=>$v) $attr.= $k.'="'.trim($v, "'\"").'" ';
            $attr.= $this->_td_span($cells[$i]);
            $row.= "<$tag $attr>".$cell.'</'.$tag.'>';
        }
        return $row;
    }

    function _td_attr(&$val, $align = '')
    {
        if (!$val) {
            if ($align) return array('class'=>$align);
            return array();
        }
        $para=str_replace(array('&lt;', '&gt'), array('<', '>'), $val);
        // split attributes <:><|3> => ':', '|3'
        $tmp = explode('><', substr($para, 1, -1));
        $paras = array();
        foreach ($tmp as $p) {
            // split attributes <(-2> => '(', '-2'
            if (preg_match_all('/([\^_v\(:\)\!=]|[-\|]\d+|\d+%|#[0-9a-fA-F]{6}|(?:colspan|rowspan|[a-z]+)\s*=\s*.+)/i', $p, $m))
                $paras = array_merge($paras, $m[1]);
            else
                $paras[] = $p;
        }
        # rowspan
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
                        $align = 'left';
                        break;
                    case ')':
                        $align = 'right';
                        break;
                    case ':':
                        $align = 'center';
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

        $val = '';
        foreach ($rattr as $k=>$v) $val .= $k.'="'.trim($v, "'\"").'" ';

        return $attr;
    }

    function _table($on, &$attr)
    {
        if (!$on) return "</table>\n";

        $sty = array();
        $myattr = array();
        $mattr = array();
        $attrs = str_replace(array('&lt;', '&gt'), array('<', '>'), $attr);
        $attrs = explode('><', substr($attrs, 1, -1));
        $myclass = array();
        $rattr = array();
        $attr = '';
        foreach ($attrs as $tattr) {
            $tattr = trim($tattr);
            if (empty($tattr)) continue;
            if (substr($tattr, 0, 5) == 'table') {
                $tattr = substr($tattr, 5);
                $mattr = $this->_attr($tattr, $sty, $myclass);
                $myattr = array_merge($myattr, $mattr);
            } else { // not table attribute
                $rattr[] = $tattr;
                #else $myattr=$this->_attr($tattr,$sty,$myclass);
            }
        }
        if (!empty($rattr)) $attr = '&lt;'.implode('>&lt;', $rattr).'>';
        if (!empty($myattr['class']))
            $myattr['class'] = 'wiki '.$myattr['class'];
        else
            $myattr['class'] = 'wiki';
        $my = '';
        foreach ($myattr as $k=>$v) $my .= $k.'="'.$v.'" ';
        return "<table cellspacing='0' $my>\n";
    }

    function _purple()
    {
        if (!$this->formatter->use_purple) return '';
        $id = sprintf('%03d', $this->formatter->purple_number++);
        $nid = 'p'.$id;
        return "<span class='purple'><a name='$nid' id='$nid'></a><a href='#$nid'>(".$id.")</a></span>";
    }

    function _div($on, &$in_div, &$enclose, $attr = '')
    {
        $close = $open = '';
        $tag = array("</div>\n","<div$attr>");
        if ($on) { $in_div++; $open = $enclose;}
        else {
            if (!$in_div) return '';
            $close = $enclose;
            $in_div--;
        }
        $enclose = '';
        $purple = '';
        if (!$on) $purple = $this->_purple();
        #return "(".$in_div.")".$tag[$on];
        return $purple.$open.$tag[$on].$close;
    }

    function _li($on, $empty = '')
    {
        $tag = array("</li>\n",'<li>');
        $purple = '';
        if (!$on and !$empty) $purple = $this->_purple();
        return $purple.$tag[$on];
    }

    /**
     * temporary callback hack example to support extra params with callback
     */
    function _array_callback($match, $init = false)
    {
        static $array;

        if ($init) {
            // XXX hack to store extra params with callback
            $array = $match;
            return;
        }
        return $array[$match[1]];
    }

    function process($body, $params = array())
    {
        global $Config;

        $args = null;
        $start_offset = 0;
        if (is_string($body)) {
            if ($body[0] == '#' and $body[1] == '!') {
                list($line, $body) = explode("\n", $body, 2);
                $dum = preg_split('/\s+/', $line, 2);
                if (!empty($dum[1])) $args = $dum[1];
                $start_offset = 1;
            }

            $lines = explode("\n", $body);
            $el = end($lines);
            // delete last empty line
            if (!isset($el[0]))
                array_pop($lines);
        } else {
            $lines = &$body;
        }

        $is_writable = !empty($params['is_writable']) ? $params['is_writable'] : 0;

        $my_divopen = '';
        $my_divclose = '';
        if (!empty($args)) {
            if (preg_match_all('@((?:[#.])?\w+)(?:\s*=\s*(["\'])?(.+?)(?(2)\2|\b))?@', $args, $matches, PREG_SET_ORDER)) {
                // parse attributes class="foo" id=bar style="border:1px sold red;"
                $attrs = array();
                foreach ($matches as $match) {
                    $tag = $match[1];
                    if (isset($match[3])) {
                        $val = trim($match[3], '; ');
                        $val = strtr($val, array('"'=>'&quot;'));
                        $val = strip_tags($val);
                        switch ($tag) {
                            case 'style':
                            case 'class':
                            case 'id':
                                $attrs[$tag] = $val;
                                break;
                            default:
                                // ignore
                                default;

                        }
                    } else {
                        if ($tag[0] == '.')
                            $attrs['class'] = substr($tag, 1);
                        else if ($tag[0] == '#')
                            $attrs['id'] = substr($tag, 1);
                        else
                            $attrs['class'] = substr($tag, 1);
                    }
                }
                $attr = '';
                foreach ($attrs as $k=>$v) {
                    $attr .= ' '.$k.'="'.$v.'"';
                }

                $my_divopen = '<div '.$attr.'>';
                $my_divclose = '</div>';
            }
        }

        # for headings
        if (isset($params['notoc'])) {
            $headinfo = null;
        } else {
            $headinfo = array();
            $headinfo['top'] = 0;
            $headinfo['num'] = 1;
            $headinfo['dep'] = 0;
        }

        $text = '';
        $in_p = '';
        $in_div = 0;
        $in_bq = 0;
        $in_li = 0;
        $in_pre = 0;
        $in_table = 0;
        $li_open = 0;
        $li_empty = 0;
        $div_enclose = '';
        $indent_list[0] = 0;
        $indent_type[0] = '';
        $_myindlen = array(0);
        $oline = '';
        $pre_line = '';

        $formatter = &$this->formatter;

        $wordrule = "\[\[(?:[A-Za-z0-9]+(?:\((?:(?<!\]\]).)*\))?)\]\]|". # macro
            "<<(?:[^<>]+(?:\((?:(?<!\>\>).)*\))?)>>|"; # macro
        if ($formatter->inline_latex) # single line latex syntax
            $wordrule .= "(?<=\s|^|>)\\$(?!(?:Id|Revision))(?:[^\\$]+)\\$(?=\s|\.|,|<|$)|".
                "(?<=\s|^|>)\\$\\$(?:[^\\$]+)\\$\\$(?=\s|<|$)|";
        #if ($Config['builtin_footnote']) # builtin footnote support
        $wordrule .= $formatter->wordrule;
        $wordrule .= '|'.$formatter->footrule;

        // override start_offset
        if (isset($params['.start_offset']))
            $start_offset = $params['.start_offset'];
        $ii = isset($formatter->pi['start_line']) ? $formatter->pi['start_line'] : 0;
        $ii = isset($params['.start_line']) ? $params['.start_line'] : $ii;
        if (isset($formatter->pi['#linenum']) and empty($formatter->pi['#linenum']))
            $this->linenum = -99999;
        else
            $this->linenum = $ii;

        $lcount = count($lines);
        for (; $ii < $lcount; $ii++) {
            $line = $lines[$ii];
            $this->linenum++;
            $lid = $this->linenum + $start_offset;
            # empty line
            if (!strlen($line) and empty($oline)) {
                if ($in_pre) { $pre_line .= "\n"; continue;}
                if ($in_li) {
                    if ($in_table) {
                        $text .= $this->_table(0, $dumm); $in_table = 0; $li_empty = 1;
                    }
                    if ($indent_type[$in_li] == 'dq') {
                        // close all tags for quote blocks '> '
                        while($in_li >= 0 && $indent_list[$in_li] > 0) {
                            if (!in_array($indent_type[$in_li], array('dd', 'dq')) && $li_open == $in_li)
                                $text .= $this->_li(0,$li_empty);
                            $text .= $this->_list(0,$indent_type[$in_li], '',
                                    $indent_type[$in_li-1]);
                            unset($indent_list[$in_li]);
                            unset($indent_type[$in_li]);
                            unset($_myindlen[$in_li]);
                            $in_li--;
                        }
                    }

                    $text .= $this->_purple()."<br />\n";
                    if ($li_empty==0 && !$formatter->auto_linebreak ) $text .= "<br />\n";
                    $li_empty=1;
                    continue;
                }
                if ($in_table) {
                    $text .= $this->_table(0, $dumm)."<br />\n"; $in_table = 0; continue;
                } else {
                    #if ($in_p) { $text.="</div><br />\n"; $in_p='';}
                    if ($in_bq) { $text .= str_repeat("</blockquote>\n", $in_bq); $in_bq = 0; }
                    if ($in_p) { $text .= $this->_div(0, $in_div, $div_enclose)."<br />\n"; $in_p = '';}
                    else if ($in_p == '') { $text .= "<br />\n";}
                    continue;
                }
            }

            // comments
            if (!$in_pre and isset($line[1]) and $line[0]=='#' and $line[1]=='#') {
                if ($formatter->wikimarkup) {
                    $out = $line.'<br />';
                    $nline = str_replace(array('=', '-', '&', '<'), array('==', '-=', '&amp;', '&lt;'), $line);
                    $text = $text."<span class='wikiMarkup'><!-- wiki:\n$nline\n\n-->$out</span>";
                }
                continue;
            }

            if ($in_pre) {
                $pre_line .= "\n".$line;
                if (preg_match("/^({{{(?:(?:[^{}]+|{[^{}]+}(?!})|(?<!{){{1,2}(?!{)|(?<!})}{1,2}(?!})|(?<=\\\\)[{}]{3}(?!}))|(?1))*+}}})/x",
                        $pre_line, $match))
                {
                    $p = strlen($match[1]);
                    $line = substr($pre_line, $p);
                    $pre_line = $match[1];

                    if ($in_table || (!empty($oline) and preg_match('/^\s*\|\|/', $oline))) {
                        $pre_line = str_replace('||', "\002\003", $pre_line); // escape || chars
                        $line = $pre_line.$line;
                        $in_pre = 0;
                    } else {
                        $pre_line = substr($pre_line, 3, -3); // strip {{{, }}}

                        // strip the blockquote markers '> ' from the pre block
                        if ($in_bq > 0 and preg_match("/\n((?:\>\s)*\>\s?)/s", $pre_line, $match))
                            $pre_line = str_replace("\n".$match[1], "\n", $pre_line);
                        $in_pre = -1;
                    }
                } else {
                    continue;
                }
            } else {
                $chunk = preg_replace_callback(
                        "/(({{{(?:(?:[^{}]+|{[^{}]+}(?!})|(?<!{){{1,2}(?!{)|(?<!})}{1,2}(?!})|(?<=\\\\)[{}]{3}(?!}))|(?2))*+}}})|".
                        // unclosed inline pre tags
                        "(?:(?!<{{{){{{}}}(?!}}})|{{{(?:{{{|}}})}}}))/x",
                        create_function('$m', 'return str_repeat("_", strlen($m[1]));'), $line);
                if (($p = strpos($chunk, '{{{')) !== false) {
                    $processor = '';
                    $in_pre = 1;

                    $pre_line = substr($line, $p);

                    if (!isset($line[0]) and !empty($formatter->auto_linebreak))
                        $formatter->nobr = 1;

                    // check processor
                    $t = isset($line[$p+3]);
                    if ($t and $line[$p+3] == '#' and $line[$p+4] == '!') {
                        $dummy = explode(' ', substr($line, $p+5), 2);
                        $tag = $dummy[0];

                        if (!empty($tag)) $processor = $tag;
                    }
                    $line = substr($line, 0, $p);
                }
            }

            $ll=strlen($line);
            if ($ll and $line[$ll-1]=='&') {
                $oline.=substr($line,0,-1);
                continue;
            } else if (preg_match('/^\s*\|\|/',$line) and $in_pre) {
                // "||{{{foobar..." case
                $oline .= isset($oline[0]) ? "\n".$line : $line;
                continue;
            } else if (!isset($oline[0]) and preg_match('/^\s*\|\|/',$line) and !preg_match('/\|(\||-+)\s*$/',$line)) {
                $oline .= $line;
                continue;
            } else if (!empty($oline)
                    and ($in_table or preg_match('/^\s*\|\|/',$oline))
                    and !preg_match('/\|(\||-+)\s*$/',$line) and isset($lines[$ii + 1])) {
                // not closed table and not reached at the end line
                $oline .= "\n".$line;
                continue;
            } else {
                $line = isset($oline[0]) ? $oline."\n".$line : $line;
                $oline = '';
            }

            $p_closeopen = '';
            if (preg_match('/^[ ]*(-{4,})$/', $line, $m)) {
                if ($formatter->use_folding && strlen($m[1]) > 10) {
                    if (empty($formatter->section_style)) {
                        $line = '[[Section(close)]]';
                    } else {
                        $line = '[[Section(off)]]';
                    }
                } else {
                    $func = $Config['hr_type'].'_hr';
                    $line = $formatter->$func($m[1]);
                }
                if ($formatter->auto_linebreak) $formatter->nobr = 1; // XXX
                if ($in_bq) { $p_closeopen .= str_repeat("</blockquote>\n", $in_bq); $in_bq = 0; }
                if ($in_p) { $p_closeopen .= $this->_div(0, $in_div, $div_enclose); $in_p='';}
            } else {
                if ($in_p == '' and $line!=='') {
                    $p_closeopen = $this->_div(1, $in_div, $div_enclose, $lid > 0 ? ' id="aline-'.$lid.'"' : '');
                    $in_p = $line;
                }

                // split into chunks. nested {{{}}} and [ ] inline elems
                $chunk = preg_split("/({{{
                    (?:(?:[^{}]+|
                        {[^{}]+}(?!})|
                        (?<!{){{1,2}(?!{)|
                        (?<!})}{1,2}(?!})|
                        (?<=\\\\)[{}]{3}(?!}))|(?1)
                    )++}}}|
                    \[ (?: (?>[^\[\]]+) | (?R) )* \])/x", $line, -1, PREG_SPLIT_DELIM_CAPTURE);
                $inline = array(); // save inline nowikis

                if (count($chunk) > 1) {
                    // protect inline nowikis
                    $nc = '';
                    $k = 1;
                    $idx = 1;
                    foreach ($chunk as $c) {
                        if ($k % 2) {
                            $nc .= $c;
                        } else if (in_array($c[3], array('#', '-', '+'))) { # {{{#color text}}}
                            $nc .= $c;
                        } else {
                            $inline[$idx] = $c;
                            $nc .= "\017".$idx."\017";
                            $idx++;
                        }
                        $k++;
                    }
                    $line = $nc;
                }

                if (($len = strlen($line)) > 10000) {
                    // XXX too long string will crash at preg_replace() with PHP 5.3.8
                    $new = '';
                    $start = 0;
                    while (($start + 10000) < $len && ($pos = strpos($line, "\n", $start + 10000)) > 0) {
                        $chunk = substr($line, $start, $pos - $start + 1);#.'<font color="#ff0000">xxxxxx</font>';
                        $new .= preg_replace($formatter->baserule, $formatter->baserepl, $chunk);
                        $start = $pos + 2;
                    }
                    $new .= preg_replace($formatter->baserule, $formatter->baserepl, substr($line, $start));
                    $line = $new;
                    //$line = preg_replace($formatter->baserule, $formatter->baserepl, $line);
                } else {
                    $line = preg_replace($formatter->baserule, $formatter->baserepl, $line);
                }

                // restore inline nowikis
                if (!empty($inline)) {
                    $this->_array_callback($inline, true);
                    $line = preg_replace_callback("/\017(\d+)\017/",
                            array(&$this, '_array_callback'), $line);
                }
            }

            // blockquote
            if ($in_pre != -1 and (!$in_table or !isset($oline[0])) and $line[0] == '>' and
                    preg_match('/^((?:>\s?)*>\s?(?!>))/', $line, $match))
            {
                $line = substr($line, strlen($match[1])); // strip markers
                $depth = substr_count($match[1], '>'); // count '>'
                if ($depth == $in_bq) {
                    // continue
                } if ($depth > $in_bq) {
                    $p_closeopen.= str_repeat("<blockquote class='quote'>", $depth - $in_bq);
                    $in_bq = $depth;
                } else {
                    $p_closeopen.= str_repeat("</blockquote>\n", $in_bq - $depth);
                    $in_bq = $depth;
                }
            } else if (!$in_pre and $in_bq > 0) {
                $p_closeopen.= str_repeat("</blockquote>\n", $in_bq);
                $in_bq = 0;
            }
            #if ($in_p and ($in_pre==1 or $in_li)) $line=$this->_check_p().$line;

            # bullet and indentation
            # and quote begin with ">"
            if ($in_pre != -1 &&
                    preg_match("/^(((>\s?)*>\s?(?!>))|(\s*>*))/",$line,$match))
            {
                #if (preg_match("/^(\s*)/",$line,$match)) {
                #echo "{".$match[1].'}';
                $open='';
                $close='';
                $indtype="dd";
                $indlen=strlen($match[0]);
                $line=substr($line,$indlen);
                $liopen='';
                if ($indlen > 0) {
                    $myindlen = $indlen;
                    # check div type.
                    $mydiv = array('indent');
                    if ($match[0][$indlen-1] == '>') {
                        $indtype = 'dq';
                        # get user defined style
                        if (($line[0] == '.' or $line[0] == '#') and ($p = strpos($line, ' '))) {
                            $divtype = '';
                            $mytag = substr($line, 1, $p-1);
                            if ($line[0] == '.') $mydiv[] = $mytag;
                            else $divtype = ' id="'.$mytag.'"';
                            $divtype .= ' class="quote '.implode(' ', $mydiv).'"';
                            $line = substr($line, $p + 1);
                        } else {
                            if ($line[0] == ' ') {
                                $line = substr($line,1); // with space
                                $myindlen = $indlen + 1;
                            }
                            $divtype = ' class="quote indent '.$formatter->quote_style.'"';
                        }
                    } else {
                        $divtype = ' class="indent"';
                    }

                    $numtype = '';
                    if ($line[0] == '*') {
                        $limatch[1] = '*';
                        $myindlen = (isset($line[1]) and $line[1]==' ') ? $indlen + 2 : $indlen + 1;
                        preg_match("/^(\*\s?)/", $line, $m);
                        $liopen = '<li>'; // XXX
                        $line = substr($line, strlen($m[1]));
                        if ($indent_list[$in_li] == $indlen && !in_array($indent_type[$in_li], array('dd', 'dq'))){
                            $close .= $this->_li(0);
                            $_myindlen[$in_li] = $myindlen;
                        }
                        $numtype = '';
                        $indtype = "ul";
                    } elseif (preg_match("/^(([1-9]\d*|[aAiI])\.)(#\d+)?\s/", $line, $limatch)) {
                        $myindlen = $indlen + strlen($limatch[1]) + 1;
                        $line = substr($line, strlen($limatch[0]));
                        if ($indent_list[$in_li] == $indlen && !in_array($indent_type[$in_li], array('dd', 'dq'))) {
                            $close .= $this->_li(0);
                            $_myindlen[$in_li] = $myindlen;
                        }
                        $numtype = $limatch[2][0];
                        if (isset($limatch[3]))
                            $numtype .= substr($limatch[3],1);
                        $indtype = "ol";
                        $lival = '';
                        if ($in_li and isset($limatch[3]))
                            $lival = ' value="'.substr($limatch[3], 1).'"';
                        $liopen = "<li$lival>"; // XXX
                    } elseif (preg_match("/^([^:]+)::\s/", $line, $limatch)) {
                        $myindlen = $indlen;
                        $line = preg_replace("/^[^:]+::\s/",
                                "<dt class='wiki'>".$limatch[1]."</dt><dd>", $line);
                        if ($indent_list[$in_li] == $indlen) $line = "</dd>\n".$line;
                        $numtype = '';
                        $indtype = "dl";
                    } else if ($_myindlen[$in_li] == $indlen) {
                        $indlen = $indent_list[$in_li]; // XXX
                    }
                }
                if ($indent_list[$in_li] > $indlen ||
                        $indtype != 'dd' && $indent_type[$in_li][1] != $indtype[1]) {
                    $fixlen = $indlen;
                    if ($indent_list[$in_li] == $indlen and
                            $indlen > 0 and $in_li > 0 and $indent_type[$in_li] != $indtype)
                        $fixlen = $indent_type[$in_li - 1]; // close prev tags

                    while($in_li >= 0 && $indent_list[$in_li] > $fixlen) {
                        if (!in_array($indent_type[$in_li], array('dd', 'dq')) && $li_open == $in_li)
                            $close.=$this->_li(0, $li_empty);
                        $close .= $this->_list(0, $indent_type[$in_li], '',
                                $indent_type[$in_li - 1]);
                        unset($indent_list[$in_li]);
                        unset($indent_type[$in_li]);
                        unset($_myindlen[$in_li]);
                        $in_li--;
                    }
                    #$li_empty=0;
                }
                if ($indent_list[$in_li] < $indlen) {
                    $in_li++;
                    $indent_list[$in_li] = $indlen; # add list depth
                    $_myindlen[$in_li] = $myindlen; # add list depth
                    $indent_type[$in_li] = $indtype; # add list type
                    $open .= $this->_list(1, $indtype, $numtype, '', $divtype);
                }
                if ($liopen) $open .= $liopen;
                $li_empty = 0;
                if ($indent_list[$in_li] <= $indlen || $limatch) $li_open = $in_li;
                else $li_open = 0;
            }

            #if (!$in_pre && !$in_table && preg_match("/^\|\|.*\|\|$/",$line)) {
            if (!$in_pre && $line[0]=='|' && !$in_table &&
                    preg_match("/^(\|([^\|]+)?\|((\|\|)*))((?:&lt;[^>\|]*>)*)(.*)$/s", $line, $match))
            {
                $open .= $this->_table(1, $match[5]);
                if (!empty($match[2])) $open .= '<caption>'.$match[2].'</caption>';
                $line = '||'.$match[3].$match[5].$match[6];
                $in_table = 1;
            } elseif ($in_table && ($line[0]!='|' or
                !preg_match("/^\|{2}.*(?:\|(\||-+))$/s", rtrim($line)))) {
                $close = $this->_table(0, $dumm).$close;
                $in_table = 0;
            }
            $skip_link = false;
            while ($in_table) {
                $line=preg_replace('/(\|\||\|-+)$/', '', rtrim($line));
                {
                    $skip_link = strpos($line, "\n") !== false;
                    $tr_attr = '';
                    $row = $this->_td($line, $tr_attr, $skip_link ? $wordrule : '');
                    if ($lid > 0) $tr_attr .= ' id="line-'.$lid.'"';
                    $line = "<tr $tr_attr>".$row.'</tr>';
                    $tr_attr = '';
                    $lid = '';
                }

                $line = str_replace('\"','"',$line); # revert \\" to \"
                break;
            }

            # InterWiki, WikiName, {{{ }}}, !WikiName, ?single, ["extended wiki name"]
            # urls, [single bracket name], [urls text], [[macro]]

            if (!$skip_link)
                $line = preg_replace_callback("/(".$wordrule.")/",
                    array(&$formatter, 'link_repl'), $line);
                #$line=preg_replace("/(".$wordrule.")/e","\$formatter->link_repl('\\1')",$line);

            # Headings
            while (!$in_table && preg_match("/(?<!=)(={1,})\s+(.*)\s+\\1\s?$/sm", $line, $m)) {
                if ($in_bq) {
                    $dummy = null;
                    $line = $formatter->head_repl(strlen($m[1]), $m[2], $dummy);
                    break;
                }
                $this->sect_num++;
                #if ($p_closeopen) { // ignore last open
                #  #$p_closeopen='';
                #  $p_closeopen.= '}}'.$this->_div(0,$in_div,$div_enclose);
                #}

                while($in_div > 0)
                    $p_closeopen .= $this->_div(0, $in_div, $div_enclose);

                // check section styling
                $cls = '';
                if (!empty($formatter->section_style))
                    $cls = ' styling';
                $p_closeopen .= $this->_div(1, $in_div, $div_enclose, ' class="section'.$cls.'"');
                $in_p = '';
                $edit = ''; $anchor = '';
                if ($is_writable && $formatter->section_edit && empty($formatter->preview)) {
                    $act = 'edit';

                    $wikiwyg_mode = '';
                    if ($Config['use_wikiwyg'] == 1) {
                        $wikiwyg_mode = ',true';
                    }
                    if ($Config['sectionedit_attr']) {
                        if (!is_string($Config['sectionedit_attr']))
                            $sect_attr = ' onclick="javascript:sectionEdit(null,this,'.
                                $this->sect_num.$wikiwyg_mode.');return false;"';
                        else
                            $sect_attr = $Config['sectionedit_attr'];
                    }
                    $url = $formatter->link_url($formatter->page->urlname,
                            '?action='.$act.'&amp;section='.$this->sect_num);
                    if ($formatter->source_site) {
                        $url = $formatter->source_site.$url;
                        $sect_attr = ' class="externalLink source"';
                    }
                    $lab = _("edit");
                    $edit = "<div class='sectionEdit' style='float:right;'><span class='sep'>[</span><span><a href='$url'$sect_attr><span>$lab</span></a></span><span class='sep'>]</span></div>\n";
                    $anchor_id = 'sect-'.$this->sect_num;
                    $anchor = "<a id='$anchor_id'></a>";
                }
                $attr = $lid > 0 ? ' id="line-'.$lid.'"' : '';
                $lid = '';

                // section heading style etc.
                $hcls = '';
                $cls = '';
                if (!empty($formatter->section_style)) {
                    $hcls = ' class="heading '.$formatter->section_style['heading'].'"';
                    $cls = ' class="'.$formatter->section_style['section'].'"';
                }

                $line = $anchor.$edit.$formatter->head_repl(strlen($m[1]), $m[2], $headinfo, $attr.$hcls);
                $dummy = '';
                $line .= $this->_div(1, $in_div, $dummy, $cls.' id="sc-'.$this->sect_num.'"'); // for folding
                $edit = ''; $anchor = '';
                break;
            }

            # Smiley
            if (!empty($formatter->use_smileys) and empty($formatter->smiley_rule))
                $formatter->initSmileys();

            if (!empty($formatter->smiley_rule)) {
                $chunk = preg_split("@(<tt[^>]*>.*</tt>)@", $line, -1, PREG_SPLIT_DELIM_CAPTURE);
                if (count($chunk) > 1) {
                    $nline = '';
                    $k = 1;
                    foreach ($chunk as $c) {
                        if ($k % 2) {
                            if (isset($c[0]))
                                $nline .= preg_replace_callback($formatter->smiley_rule,
                                        array(&$formatter, 'smiley_repl'), $c);
                        } else {
                            $nline .= $c;
                        }
                        $k++;
                    }
                    $line = $nline;
                } else {
                    $line = preg_replace_callback($formatter->smiley_rule,
                            array(&$formatter, 'smiley_repl'), $line);
                }
            }

            if (!empty($formatter->extrarule))
                $line = preg_replace($formatter->extrarule, $formatter->extrarepl, $line);
            #if ($formatter->auto_linebreak and preg_match('/<div>$/',$line))
            #  $formatter->nobr=1;

            $line = $close.$p_closeopen.$open.$line;
            $open = ''; $close = '';

            if ($in_pre == -1) {
                $in_pre = 0;

                # for smart diff
                $show_raw=0;
                if ($formatter->use_smartdiff and
                    preg_match("/\006|\010/", $pre_line)) $show_raw=1;

                // revert escaped {{{, }}}
                $pre_line = strtr($pre_line, array('\\}}}'=>'}}}', '\\{{{'=>'{{{')); // FIXME

                if ($processor and !$show_raw) {
                    $value = &$pre_line;
                    if ($processor == 'wiki') {
                        $processor = 'monimarkup';
                        if (isset($params['notoc']))
                            $save_toc = $params['notoc'];
                        $params['notoc'] = 1;
                    }
                    $out = $formatter->processor_repl($processor, $value, $params);
                    if (isset($save_toc)) {
                        // do not shoe edit section link in the processor mode
                        $params['notoc'] = $save_toc;
                        unset($save_toc);
                    }
                    #if ($formatter->wikimarkup)
                    #  $line='<div class="wikiMarkup">'."<!-- wiki:\n{{{".
                    #    $value."}}}\n-->$out</div>";
                    #else
                    #  $line=$out.$line;
                    $line = $out.$line;
                    unset($out);
                } else {
                    # htmlfy '<', '&'
                    if (!empty($Config['default_pre'])) {
                        $out=$formatter->processor_repl($Config['default_pre'], $pre_line, $params);
                    } else {
                        $pre=str_replace(array('&', '<'),
                               array("&amp;", "&lt;"),
                               $pre_line);
                        $pre = preg_replace("/&lt;(\/?)(ins|del)/", "<\\1\\2", $pre);
                        # FIXME Check open/close tags in $pre
                        #$out="<pre class='wiki'>\n".$pre."</pre>";
                        $out = "<pre class='wiki'>".$pre."</pre>";
                        if ($formatter->wikimarkup) {
                            $nline = str_replace(array('=', '-', '&', '<'), array('==', '-=', '&amp;', '&lt;'), $pre_line);
                            $out = '<span class="wikiMarkup">'."<!-- wiki:\n{{{\n".
                                str_replace('}}}', '\}}}', $nline).
                                "}}}\n-->".$out."</span>";
                        }
                    }
                    $line = $out.$line;
                    unset($out);
                }
                $formatter->nobr = 1;
            }

            $lidx = '';
            if ($lid > 0) $lidx = "<span class='line-anchor' id='line-".$lid."'></span>";

            if (isset($line[0]) and $formatter->auto_linebreak && !$in_table && !$formatter->nobr)
                $text .= $line.$lidx."<br />\n";
            else
                $text .= $line ? $line.$lidx."\n":'';
            $formatter->nobr = 0;
            # empty line for quoted div
            if (!$formatter->auto_linebreak and !$in_pre and trim($line) == '')
                $text .= "<br />\n";

        } # end rendering loop
        # for smart_diff (div)
        if ($formatter->use_smartdiff)
            $text = preg_replace_callback(array("/(\006|\010)(.*)\\1/sU"),
                array(&$formatter, '_diff_repl'), $text);

        $fts = array();
        if (!empty($formatter->pi['#postfilter'])) $fts = preg_split('/(\||,)/', $formatter->pi['#postfilter']);
        if (!empty($formatter->postfilters)) $fts=array_merge($fts, $formatter->postfilters);
        if ($fts) {
            foreach ($fts as $ft)
            $text = $formatter->postfilter_repl($ft, $text, $params);
        }

        # close all tags
        $close = '';
        # close pre,table
        if ($in_pre) {
            // fail to close pre tag
            $text .= $formatter->processor_repl($processor, $pre_line, $params);
        }
        if ($in_table) $close .= "</table>\n";
        # close indent
        while($in_li >= 0 && $indent_list[$in_li] > 0) {
            if (!in_array($indent_type[$in_li], array('dd', 'dq')) && $li_open == $in_li) // XXX
                $close .= $this->_li(0);
            #$close.=$this->_list(0,$indent_type[$in_li]);
            $close .= $this->_list(0, $indent_type[$in_li], '', $indent_type[$in_li-1]);
            unset($indent_list[$in_li]);
            unset($indent_type[$in_li]);
            $in_li--;
        }
        # close div
        #if ($in_p) $close.="</div>\n"; # </para>
        if ($in_bq) { $close .= str_repeat("</blockquote>\n", $in_bq); $in_bq = 0; }
        if ($in_p) $close .= $this->_div(0, $in_div, $div_enclose); # </para>
        #if ($div_enclose) $close.=$this->_div(0,$in_div,$div_enclose);
        while($in_div > 0)
            $close .= $this->_div(0, $in_div, $div_enclose);

        # activate <del></del> tag
        #$text=preg_replace("/(&lt;)(\/?del>)/i","<\\2",$text);
        $text .= $close;

        return $my_divopen.$text.$my_divclose;
    }
}

// vim:et:sts=4:sw=4:
