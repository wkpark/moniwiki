<?php
/**
 * @author              Won-Kyu Park
 * @package             Mixdown
 * @date                2014/01/24
 * @version             $Revision: 1.0 $
 * @description         Hybrid Markdown+Moniwiki parser
 */

// Original source code:
// Parsedown (c) Emanuil Rusev erusev.com
// Disributable under MIT-License

define('FZW_CHAR', "\032");

class processor_mixdown
{
    //
    // Setters
    //

    var $breaks_enabled = false;
    var $table_parser = null;

    function processor_mixdown(&$formatter, $params = array())
    {
        $this->formatter = &$formatter;

        $this->wordrule = "\[\[(?:[A-Za-z0-9]+(?:\((?:(?<!\]\]).)*\))?)\]\]|". # macro
              "<<(?:[A-Za-z0-9]+(?:\((?:(?<!>>).)*\))?)>>|"; # macro

        if (!empty($formatter->wordrule)) {
            $this->wordrule .= $formatter->wordrule;
            $this->wordrule .= '|'.$formatter->footrule;
        }
    }

    function set_breaks_enabled($breaks_enabled)
    {
        $this->breaks_enabled = $breaks_enabled;

        return $this;
    }

    function expandtab($text, $sizeoftab = 4)
    {
        $lines = explode("\n", $text);

        foreach ($lines as $i => $line) {
            // find tab pos
            $expanded = '';
            while (($pos = strpos($line, "\t")) !== false) {
                // find tab pos and expand it.
                $expanded .= substr($line, 0, $pos);
                $expanded .= str_repeat(' ',
                        $sizeoftab - $pos % $sizeoftab);
                $line = substr($line, $pos + 1);
            }

            $lines[$i] = $expanded.$line;
        }

        return implode("\n", $lines);
    }

    //
    // Public Methods
    //

    function process($text, $params = array())
    {
        if ($text[0] == '#' and $text[1] == '!')
            list($line, $text) = explode("\n", $text, 2);

        // removes \r characters
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);

        // replaces tabs with spaces
        $text = $this->expandtab($text);

        // rtrim or trash last "\n"
        if (!empty($params['rtrim'])) {
            $text = rtrim($text, "\n");
        } else if (substr($text, -1) == "\n") {
            $text = substr($text, 0, -1);
        }

        $out = $this->parse_block_elements($text);

        return $out;
    }

    function _append_line(&$block, $line)
    {
        if (isset($block['lines'])) {
            $end = end($block['lines']);
            $line = ltrim($line, FZW_CHAR);
            $tmp = explode("\n", $line);
            if (substr($end, -1) == FZW_CHAR) {
                $end = substr($end, 0, -1). $tmp[0];
                array_pop($block['lines']);
                $block['lines'][] = $end;
                array_shift($tmp);
            }
            foreach ($tmp as $l)
                $block['lines'][] = $l;

            return true;
        } else if (isset($block['text'])) {
            $block['text'] = rtrim($block['text'], FZW_CHAR);
            $block['text'] .= ltrim($line, FZW_CHAR);

            return true;
        }
        return false;
    }

    //
    // block parser
    //
    function parse_block_elements($text, $params = array())
    {
        if (is_array($text))
            $text = implode("\n", $text);

        $blocks = array();
        $context = isset($params['context']) ? $params['context'] : '';
        $start = isset($params['start']) ? $params['start'] - 1 : 0;

        // set line ID
        if ($start < 0)
            $lid = -99999;
        else
            $lid = $start;

        $block = array(
            'type' => '',
        );

        $line = '';
        $offset = 0;
        $save_offset = 0;
        while ($offset !== false) {
            $inc = 1;
            if ($offset > 0) {
                if ($save_offset != $offset) {
                    if (!isset($text[$offset]))
                        $offset--; // fix for notice warning
                    $inc = substr_count($text, "\n", 0, $offset);
                }
                $text = substr($text, $offset);
            }
            if (($offset = strpos($text, "\n")) !== false) {
                $line = substr($text, 0, $offset);
                $offset++; // skip "\n"
            } else {
                $line = $text;
            }
            $save_offset = $offset; // save offset to set line ID correctly
            $lid += $inc; // increase line ID

            // fenced elements
            switch ($block['type']) {
                case 'fenced block':
                    if (!isset($block['closed'])) {
                        if (preg_match('/^[ ]*'.$block['fence'][0].'{3,}[ ]*$/', $line)) {
                            $block['closed'] = true;
                        } else {
                            $block['text'] !== '' and $block['text'] .= "\n";

                            $block['text'] .= $line;
                        }

                        continue 2;
                    }
                    break;

                case 'block-level markup':
                    if (!isset($block['closed'])) {
                        if (strpos($line, $block['start']) !== false) # opening tag
                        {
                            $block['depth']++;
                        }

                        if (strpos($line, $block['end']) !== false) # closing tag
                        {
                            $block['depth'] > 0
                                ? $block['depth']--
                                : $block['closed'] = true;
                        }

                        $block['text'] .= "\n".$line;
                        continue 2;
                    }
                    break;
            }

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
                        if (($lp = strpos($text, "\n", $np)) !== false) {
                            $line = substr($text, 0, $lp);
                            $offset = $lp + 1;
                            $pos = $np; // next remaining line offset

                            continue;
                        } else {
                            $line = $text;
                            $offset = strlen($text) + 1;
                        }
                    }
                }
                // fail to find pre block
                break;
            }

            // *
            $outdented_line = ltrim($line);

            if ($outdented_line === '') {
                // render multiple empty lines as BRs
                if (!empty($block['interrupted'])) {
                    if ($block['type'] !== 'li') {
                        $blocks[] = $block;

                        $block = array(
                            'type' => 'br',
                            'interrupted' => true,
                        );
                        if ($lid > 0) $block['lid'] = $lid;
                    } else {
                        $this->_append_line($block, '');
                    }
                } else {
                    $block['interrupted'] = true;
                }

                continue;
            }

            // composite elements
            switch ($block['type']) {
                case 'blockquote':
                    if (!isset($block['interrupted'])) {
                        $line = preg_replace('/^[ ]*>[ ]?/', '', $line);

                        $block['lines'][] = $line;

                        continue 2;
                    }
                    break;

                case 'li':
                    preg_match('/^([ ]*)((?:[*+-]|(\()?(\d+|#|[a-z]|[ivxlcdm]+)((?(3)\)|(?:\.|\)))))[ ]+)?(.*)/si', $line, $matches);
                    $indent = strlen($matches[1]);

                    if (isset($block['interrupted']) and
                        empty($matches[2]) and
                        $indent >= $block['indent']) {
                        // check intented paragraph
                        // to prevent being indented list
                        $len = strlen($line);
                        if (isset($text[$len + 1]) and
                            $text[$len + 1] != "\n" and
                            $text[$len + 1] != " ") {
                            // check next line
                            $next = substr($text, $len + 1);
                            preg_match('/^([ ]*)((?:[*+-]|(\()?(\d+|#|[a-z]|[ivxlcdm]+)((?(3)\)|(?:\.|\)))))[ ]+)?(.*)/is', $next, $tmp_matches);
                            if (empty($tmp_matches[2])) {
                                // next line is not a list
                                // this is a indented paragraph
                                // reset indent
                                $indent = strlen($tmp_matches[1]);
                            }
                        }
                    }

                    if (!empty($matches[2]) || $indent > 0 and $indent >= $block['indent']) {
                        #echo '<pre>';
                        #var_dump($block);
                        #var_dump($matches);
                        #echo '</pre>';

                        // 1. aaa | 1. aaa  | 1. aaa bbb |
                        //    bbb | bbb bbb |      cccc  |

                        if (($indent > $block['indent']) or (empty($matches[2]) and $indent == $block['indent'])) {
                            if (isset($block['interrupted'])) $block['lines'][] = '';
                            unset($block['interrupted']);

                            if ($indent < $block['undent'])
                                $block['undent'] = $block['indent']; // reset undent

                            // XXX recursive indent check.
                            if ($indent >= $block['undent'])
                                $block['lines'][] = substr($line, $block['undent']);
                            else
                                $block['lines'][] = substr($line, $block['indent']);
                        } else if (!empty($matches[2]) and $indent == $block['indent']) {
                            $info = '';
                            // same level indentation
                            if (preg_match('/[*+-]/', $matches[2])) {
                                $type = $matches[2];
                            } else {
                                // list info (1): paren, 1) rparen, 1. dot 
                                $info = $matches[3] == '(' ? 'p' : ($matches[5] == '.' ? 'd' : 'r');
                                if (is_numeric($matches[4])) {
                                    $type = 1;
                                } else if ($block['list-type'] === 'i' and preg_match('/^[ivxlcdm]+$/', $matches[4])) {
                                    $type = $block['list-type'];
                                } else if ($block['list-type'] === 'I' and preg_match('/^[IVXLCDM]+$/', $matches[4])) {
                                    $type = $block['list-type'];
                                } else if ($block['list-type'] === 'a' and preg_match('/^[a-z]$/', $matches[4])) {
                                    $type = $block['list-type'];
                                } else if ($block['list-type'] === 'A' and preg_match('/^[A-Z]$/', $matches[4])) {
                                    $type = $block['list-type'];
                                } else {
                                    if (preg_match('/^[IVXLCDM]+$/', $matches[4]))
                                        $type = 'I';
                                    else if (preg_match('/^[ivxlcdm]+$/', $matches[4]))
                                        $type = 'i';
                                    else if (preg_match('/^[A-Z]$/', $matches[4]))
                                        $type = 'A';
                                    else if (preg_match('/^[a-z]$/', $matches[4]))
                                        $type = 'a';
                                }
                            }

                            if ($type == $block['list-type'] && $info == $block['list-info'])
                                // same type
                                unset($block['last']);

                            $blocks[] = $block;

                            if ($type == $block['list-type'] && $info == $block['list-info']) {
                                unset($block['first']);
                            } else {
                                unset($block['start']);
                                $block['first'] = true;
                                $block['list-type'] = $type;
                                $block['list-info'] = $info;
                                $block['indent'] = $indent;
                            }

                            // fixup undent like as 123. foobar cases
                            if (!empty($matches[2])) {
                                $undent = $indent;
                                $undent+= strlen($matches[2]);
                                $block['undent'] = $undent;
                            }

                            $block['last'] = true;
                            $block['lines'] = array(
                                preg_replace('/^[ ]{0,4}/', '', $matches[6]),
                            );
                            if ($lid > 0) $block['lid'] = $lid;

                            // reset previous interrupted info.
                            unset($block['interrupted']);
                        }

                        continue 2;
                    } else if ($block['indent'] > 0 and $indent == 0) {
                        if (empty($block['list-type']) and !isset($block['interrupted'])) {
                            $block['lines'][] = $line;

                            continue 2;
                        } else {
                            // end of block
                            $blocks[] = $block;

                            $block = array(
                                'type' => 'empty',
                            );
                        }
                    } else if (isset($block['interrupted'])) {
                        if ($line[0] == ' ') {
                            $block['lines'][] = '';

                            $line = preg_replace('/^[ ]{0,4}/', '', $line);
                            $block['lines'][] = $line;

                            unset($block['interrupted']);

                            continue 2;
                        }
                    } else {
                        $this->_append_line($block, $line);

                        continue 2;
                    }

                    break;
            }

            // indentation sensitive types
            switch ($line[0]) {
                case ' ':
                    // code block

                    if (isset($line[3]) and $line[3] === ' ' and $line[2] === ' ' and $line[1] === ' ') {
                        $code_line = substr($line, 4);

                        if ($block['type'] === 'code block') {
                            if (isset($block['interrupted'])) {
                                $block['text'] .= "\n";

                                unset ($block['interrupted']);
                            }

                            $block['text'] .= "\n".$code_line;
                        } else {
                            $blocks[] = $block;

                            $block = array(
                                'type' => 'code block',
                                'text' => $code_line,
                            );
                            if ($lid > 0) $block['lid'] = $lid;
                        }

                        continue 2;
                    }
                    break;

                case '-':
                case '=':
                    // setext heading
                    if ($block['type'] === 'paragraph' and isset($block['interrupted']) === false) {
                        preg_match('/^('.$line[0].'+)[ ]*$/', $line, $matches);
                        if (function_exists('mb_strwidth')) {
                            $len = mb_strwidth($block['text'], 'UTF-8');
                        } else {
                            $len = strlen($block['text']);
                        }
                        if (isset($matches[1]) and strlen($matches[1]) == $len) {
                            $block['type'] = 'heading';
                            $block['level'] = $line[0] === '-' ? 2 : 1;

                            continue 2;
                        }
                    }
                    if ($line[0] == '-') {
                        break;
                    }

                case '#':
                    $marker = $line[0];
                    if (!isset($line[1])) break;

                    $check_comment = false;
                    if ($marker == '#') {
                        if ($line[1] == '#') {
                            // moniwiki comments
                            $check_comment = true;
                        }

                        // `## foobar` => moniwiki comments
                        // `## foobar ##` => markdown heading
                        $bra = '[ ]*';
                        $ket = '[ ]*\1';
                    } else {
                        // moniwiki style heading `== foobar ==`
                        $bra = '[ ]+';
                        $ket = '[ ]+\1';
                    }
                    if (preg_match('/^('.$marker.'{1,6})'.$bra.'(.+?)'.$ket.'[ ]*$/', $line, $matches)) {
                        // atx like heading
                        $blocks[] = $block;

                        $level = strlen($matches[1]);
                        $block = array(
                            'type' => 'heading',
                            'text' => $matches[2],
                            'level' => $level,
                        );
                        if ($lid > 0) $block['lid'] = $lid;

                        continue 2;
                    } else if ($check_comment) {
                        // moniwiki comments
                        $blocks[] = $block;

                        $block = array(
                            'type' => 'comment',
                            'text' => substr($line, 2),
                        );
                        if ($lid > 0) $block['lid'] = $lid;

                        continue 2;
                    }

                    break;
            }

            // indentation insensitive types
            switch ($outdented_line[0]) {
                case '<':
                    $position = strpos($outdented_line, '>');

                    if ($position > 1) { // tag
                        $name = substr($outdented_line, 1, $position - 1);
                        $name = rtrim($name);

                        if (substr($name, -1) === '/') {
                            $self_closing = true;

                            $name = substr($name, 0, -1);
                        }

                        $position = strpos($name, ' ');

                        if ($position) {
                            $name = substr($name, 0, $position);
                        }

                        if (!ctype_alpha($name)) {
                            break;
                        }

                        if (in_array($name, $this->text_level_elements)) {
                            break;
                        }

                        $blocks[] = $block;

                        if (isset($self_closing)) {
                            $block = array(
                                'type' => 'self-closing tag',
                                'text' => $outdented_line,
                            );
                            if ($lid > 0) $block['lid'] = $lid;

                            unset($self_closing);

                            continue 2;
                        }

                        $block = array(
                            'type' => 'block-level markup',
                            'text' => $outdented_line,
                            'start' => '<'.$name.'>',
                            'end' => '</'.$name.'>',
                            'depth' => 0,
                        );
                        if ($lid > 0) $block['lid'] = $lid;

                        if (strpos($outdented_line, $block['end'])) {
                            $block['closed'] = true;
                        }

                        continue 2;
                    }
                    break;

                case '>':
                    // quote
                    if (preg_match('/^>[ ]?(.*)/', $outdented_line, $matches)) {
                        $blocks[] = $block;

                        $block = array(
                            'type' => 'blockquote',
                            'lines' => array(
                                $matches[1],
                            ),
                        );
                        if ($lid > 0) $block['lid'] = $lid;

                        continue 2;
                    }
                    break;

                case '[':
                    // reference

                    if (preg_match('/^\[(.+?)\]:[ ]*(.+?)(?:[ ]+[\'"](.+?)[\'"])?[ ]*$/', $outdented_line, $matches))
                    {
                        $label = strtolower($matches[1]);

                        $this->reference_map[$label] = array(
                            '»' => trim($matches[2], '<>'),
                        );

                        if (isset($matches[3]))
                        {
                            $this->reference_map[$label]['#'] = $matches[3];
                        }

                        continue 2;
                    }

                    break;

                case '`':
                case '~':
                    // fenced code block
                    if (preg_match('/^([`]{3,}|[~]{3,})[ ]*(\S+)?[ ]*$/', $outdented_line, $matches)) {
                        $blocks[] = $block;

                        $block = array(
                            'type' => 'fenced block',
                            'text' => '',
                            'fence' => $matches[1],
                        );
                        if ($lid > 0) $block['lid'] = $lid;

                        isset($matches[2]) and $block['language'] = $matches[2];

                        continue 2;
                    }

                    break;

                case '*':
                case '+':
                case '-':
                case '_':
                    // rule
                    if (preg_match('/^([-*_])(?:\1{3,}|(?:[ ]{1,2}\1){2,})[ ]*$/', $outdented_line)) {
                        $blocks[] = $block;

                        $block = array(
                            // FIXME size of hr
                            'type' => 'rule',
                        );
                        if ($lid > 0) $block['lid'] = $lid;

                        continue 2;
                    }

                    break;

                case '|':
                    if ($outdented_line[1] == '|') {
                        if (!isset($this->table_parser)) {
                            include_once('table.php');
                            $this->table_parser = new TableParser;
                        }
                        $newoffset = 0;
                        $table = $this->table_parser->parse($text, $newoffset);
                        if ($newoffset > 0) {
                            $offset = $newoffset;
                            $blocks[] = $block;

                            $table['lid'] = $lid;
                            $block = $table;

                            continue 2;
                        }
                    }
            }

            // li
            preg_match('/^([ ]*)((?:[*+-]|(\()?(\d+|#|[a-z]|[ivxlcdm]+)((?(3)\)|(?:\.|\)))))[ ]+)?(.*)/is', $line, $matches);

            if (empty($matches[2]) and strlen($matches[1]) > 0) {
                //
                // check indented paragraph
                //
                //    This is a indented
                // paragraph. blah blah
                //
                // do not count indentation
                // in this case.
                //
                $len = strlen($line);
                if (isset($text[$len + 1]) and
                    $text[$len + 1] != "\n" and
                    $text[$len + 1] != " ") {
                    // check next line

                    $next = substr($text, $len + 1);
                    preg_match('/^([ ]*)((?:[*+-]|(\()?(\d+|#|[a-z]|[ivxlcdm]+)((?(3)\)|(?:\.|\)))))[ ]+)?(.*)/is', $next, $tmp_matches);
                    if (empty($tmp_matches[2])) {
                        $matches[1] = '';
                        $line = '&#8203;'.$line;
                        // prepend ZWSP to preserve indentation spaces
                    }
                }
            }
            if (!empty($matches[2]) || strlen($matches[1]) > 0) {
                $blocks[] = $block;
                #echo '<pre>';
                #echo "list=====>\n";
                #var_dump($matches);
                #echo '</pre>';

                $start = null;
                $style = array();
                $info = '';
                if ($matches[2] == '')
                    $type = '';
                else if (preg_match('/^[*+-]/i', $matches[2]))
                    $type = $matches[2];
                else {
                    // list info (1): paren, 1) rparen, 1. dot 
                    $info = $matches[3] == '(' ? 'p' : ($matches[5] == '.' ? 'd' : 'r');
                    $type = 1;
                    if ($matches[4] == '#') {
                    } else if (is_numeric($matches[4])) {
                        if ($matches[4] != 1)
                            $start = $matches[4];
                    } else {
                        if ($matches[4] == 'i' or $matches[4] == 'I') {
                            $type = $matches[4];
                        } else if (preg_match('/^[a-z]$/i', $matches[4])) {
                            $type = 'A';
                            if ($matches[4] !== 'a' and $matches[4] !== 'A') {
                                $start = ord($matches[4]) - ord('A') + 1; // alpha to int
                                if ($start > 32)
                                    $start-= 32;
                            }
                            if (preg_match('/^[a-z]+$/', $matches[4])) {
                                $type = 'a';
                            }
                        } else if (preg_match('/^[ivxlcdm]+$/i', $matches[4])) {
                            $type = 'I';
                            if ($matches[4] !== 'i' and $matches[4] !== 'I')
                                $start = $this->roman2int($matches[4]); // convert roman to int
                            if (preg_match('/^[ivxlcdm]+$/', $matches[4])) {
                                $type = 'i';
                            }
                        }
                    }
                }

                $indent = strlen($matches[1]);
                $undent = $indent;
                if ($matches[2] !== '') {
                    $undent+= strlen($matches[2]);
                }

                $block = array(
                    'type' => 'li',
                    'list-type' => $type,
                    'list-info' => $info,
                    'indent' => $indent,
                    'undent' => $undent,
                    'first' => true,
                    'last' => true,
                    'lines' => array(
                        preg_replace('/^[ ]{0,4}/', '', $matches[6]),
                    ),
                );
                if (!empty($style))
                    $block['style'] = implode(';', $style);
                if ($start !== null)
                    $block['start'] = $start;
                //echo "<pre>";
                //var_dump($block);
                //echo "</pre>";
                if ($lid > 0) $block['lid'] = $lid;
                continue;
            }

            // GFM table
            if ($outdented_line[0] == '|' or ($pos = strpos($line, '|') !== false)) {
                $tmp = substr($text, $offset);
                while (preg_match("/^[ ]*\|?[ ]*([:]?[-]+[:]?[ ]*\|)+[ ]*([:]?[-]+[:]?[ ]*)\|?[ ]*\n?/", $tmp, $matches)) {
                    $text = $tmp;
                    $offset = 0;

                    // Remove trailing and leading |
                    $tmp_line = preg_replace("/(^[ ]*\||\|\s*$)/", '', $line);
                    // Break up the columns and remove any whitespace
                    $tmp_rows = preg_split("/\|/", $tmp_line);
                    $count = count($tmp_rows);
                    $tmp_rows = array_map('trim', $tmp_rows);
                    $alignments = array();

                    $tmp_line = preg_replace("/(^[ ]*\||\|\s*$)/", '', $matches[0]);
                    $rows = preg_split("/\|/", $tmp_line);
                    $rows = array_map('trim', $rows);
                    if ($count != count($rows)) {
                        break;
                    }

                    $text = substr($text, strlen($matches[0]));
                    $offset = 0;

                    if (($p = strpos($matches[0], ':')) !== false) {
                        foreach ($rows as $row) {
                            $left = ($row[0] == ':');
                            $right = (substr($row, -1, 1) == ':');
                            $align = '';
                            if ($left && $right) {
                                $align = 'center';
                            } else if ($right) {
                                $align = 'right';
                            }
                            $alignments[] = $align;
                        }
                    }

                    $rows = array();
                    foreach ($tmp_rows as $id => $txt) {
                        $row = array(
                            'tag'=>'th',
                            'text'=>$txt,
                        );
                        if (isset($alignments[$id][0])) {
                            $row['attribute'] = 'style="text-align:'.$alignments[$id].'"';
                        }
                        $rows[] = $row;
                    }

                    $cols = array();
                    $col = array(
                        'tag'=>'tr',
                        'elements'=>$rows,
                    );
                    $cols[] = $col;


                    while (true) {
                        if (preg_match("/^[ ]*\|?[ ]*([^|\n]*\|)+[ ]*([^|\n]*)\|?[ ]*\n?/", $text, $matches)) {
                            // Remove trailing and leading |
                            $tmp_line = preg_replace("/(^[ ]*\||\|\s*$)/", '', $matches[0]);
                            // Break up the columns and remove any whitespace
                            $tmp_rows = preg_split("/\|/", $tmp_line);
                            $tmp_rows = array_map('trim', $tmp_rows);

                            if ($count != count($tmp_rows)) {
                                break;
                            }

                            $rows = array();
                            foreach ($tmp_rows as $id => $txt) {
                                $row = array(
                                    'tag'=>'td',
                                    'text'=>$txt,
                                );
                                if (isset($alignments[$id][0])) {
                                    $row['attribute'] = 'style="text-align:'.$alignments[$id].'"';
                                }
                                $rows[] = $row;
                            }

                            $text = substr($text, strlen($matches[0]));
                            $offset = 0;

                            $col = array(
                                'tag'=>'tr',
                                'elements'=>$rows,
                            );
                            $cols[] = $col;
                        } else {
                            break;
                        }
                    }
                    $blocks[] = $block;

                    $block = array(
                        'type' => 'table',
                        'attribute' => 'class="wiki"',
                        'elements' => $cols,
                    );

                    break;
                }
                if ($block['type'] == 'table') {
                    continue;
                }
            }

            // paragraph
            if ($block['type'] === 'paragraph') {
                if (isset($block['interrupted'])) {
                    $blocks[] = $block;

                    $block['text'] = $line;
                    if ($lid > 0) {
                        $block['lid'] = $lid;
                    }

                    unset($block['interrupted']);
                } else {
                    // check fake zero-width chars
                    if (substr($block['text'], -1) == FZW_CHAR || $line[0] == FZW_CHAR) {
                        $this->_append_line($block, $line);
                    } else {
                        $this->breaks_enabled and $block['text'] .= '  ';

                        $block['text'] .= "\n".$line;
                    }
                }
            } else {
                $blocks[] = $block;

                $block = array(
                    'type' => 'paragraph',
                    'text' => $line,
                );
                if ($lid > 0) {
                    $block['lid'] = $lid;
                }
            }
        } // foreach

        $blocks[] = $block;
        unset($blocks[0]); // trash empty the first element

        //echo '------------------<pre>';
        //var_dump($blocks);
        //echo '-----------</pre>';

        //
        // ~
        //
        return $this->render($blocks, $params);
    }

    function _link_repl($text)
    {
        if ($this->formatter !== null) {
            $text = preg_replace_callback("/(".$this->wordrule.")/",
                    array(&$this->formatter, 'link_repl'), $text);
        }
        return $text;
    }

    function render($blocks, $params = array())
    {

        $context = isset($params['context']) ? $params['context'] : '';
        $start = isset($params['start']) ? $params['start'] - 1 : 0;

        $markup = '';
        $buffer = '';

        foreach ($blocks as $block) {
            if (!is_array($block)) {
                $buffer .= $block;

                continue;
            } else if (isset($buffer[0])) {
                $buffer = $this->_link_repl($buffer);

                $markup .= $buffer;
                $buffer = '';
            }
            if (isset($block['tag'])) {
                switch ($block['tag']) {
                case 'a':
                    $markup .= '<a href="'.$block['href'].'" />';
                    $markup .= $block['text'].'</a>';
                    break;
                case 'img':
                    $markup .= '<img src="'.$block['src'].'" />';
                    break;
                case 'tt':
                    $markup .= '<'.$block['tag'].' class="wiki">'.$block['text'].'</'.$block['tag'].'>';
                    break;
                case 'em':
                case 'sup':
                case 'sub':
                case 'del':
                case 'ins':
                case 'strong':
                    $markup .= '<'.$block['tag'].'>'.$block['text'].'</'.$block['tag'].'>';
                    break;
                case 'u':
                    $markup .= '<'.$block['tag'].'>'.$block['text'].'</'.$block['tag'].'>';
                    break;
                case 'code':
                    $text = strtr($block['text'], array('<'=>'&lt;', ));
                    $markup .= '<'.$block['tag'].'>'.$this->_link_repl($text).'</'.$block['tag'].'>';
                    break;
                }
            } else
            switch ($block['type']) {
                case 'paragraph':
                    $text = $this->parse_span_elements($block['text']);

                    if (strpos($text, "\n") !== false) {
                        $markup .= '<p>'.$text.'</p>'."\n";
                        break;
                    }

                    if ($context === 'li' and $markup === '') {
                        if (isset($block['interrupted'])) {
                            $markup .= "\n".'<p>'.$text.'</p>'."\n";
                        } else {
                            $markup .= $text;

                            if (isset($blocks[2])) {
                                $markup .= "\n";
                            }
                        }
                    } else {
                        $markup .= '<p>'.$text.'</p>'."\n";
                    }

                    break;

                case 'blockquote':
                    $params = array();
                    if (isset($block['lid']))
                        $params['start'] = $block['lid'];

                    $text = $this->parse_block_elements($block['lines'], $params);

                    $markup .= '<blockquote'.
                        ' class="quote"'.
                        '>'.$text.'</blockquote>'."\n";

                    break;

                case 'code block':
                    if ($block['text'][0] == '#' and $block['text'][1] == '!') {
                        if (($p = strpos($block['text'], "\n")) !== false) {
                            list($language, $dummy) = explode(' ', substr($block['text'], 2, $p - 2));
                        }
                    }
                    $text = _html_escape($block['text']);

                    $markup .= '<pre class="wiki"><code>'.$text.'</code></pre>';

                    break;

                case 'processor':

                    if ($this->formatter != null) {
                        $markup .= $this->formatter->processor_repl($block['processor'], $block['text']);
                    } else {
                        $text = _html_escape($block['text']);

                        $markup .= '<pre class="wiki"><code>'.$text.'</code></pre>';
                    }

                    break;

                case 'fenced block':
                    if (!isset($block['language']) and $block['text'][0] == '#' and $block['text'][1] == '!') {
                        if (($p = strpos($block['text'], "\n")) !== false) {
                            list($language, $dummy) = explode(' ', substr($block['text'], 2, $p - 2));
                        }
                    }
                    $text = _html_escape($block['text']);

                    $markup .= '<pre><code';

                    isset($block['language']) and $markup .= ' class="language-'.$block['language'].'"';

                    $markup .= '>'.$text.'</code></pre>'."\n";

                    break;

                case 'heading':
                    $text = $this->parse_span_elements($block['text']);

                    $markup .= '<h'.$block['level'].'>'.$text.'</h'.$block['level'].'>'."\n";

                    break;

                case 'comment':
                    $text = _html_escape($block['text']);

                    $markup .= '<!-- '.$text.' -->'."\n";

                    break;

                case 'rule':
                    $markup .= '<hr />'."\n";

                    break;

                case 'li':
                    if (isset($block['first'])) {
                        $start = '';
                        $type = 'ol';
                        $style = array();
                        if (preg_match('/[*+-]/', $block['list-type'])) {
                            $type = 'ul';
                        } else if ($block['list-type'] == '') {
                            $type = 'div class="indent"';
                        }
                        $markup .= '<'.$type;
                        if (isset($block['start']))
                            $markup .= ' start="'.$block['start'].'"';
                        if (!empty($block['style']))
                            $markup .= ' style="'.$block['style'].'"';
                        if ($type == 'ol' and preg_match('/[ia]/i', $block['list-type'])) {
                            $markup .= ' type="'.$block['list-type'].'"';
                        }
                        $markup .= '>'."\n";
                    }

                    if (isset($block['interrupted']) and ! isset($block['last'])) {
                        $block['lines'][] = '';
                    }

                    $params = array();
                    if ($block['list-type'] != '')
                        $params['context'] = 'li';
                    if (isset($block['lid']))
                        $params['start'] = $block['lid'];
                    $text = $this->parse_block_elements($block['lines'], $params);

                    if ($block['list-type'] != '')
                        $markup .= '<li>'.$text.'</li>'."\n";
                    else
                        $markup .= $text;

                    if (isset($block['last'])) {
                        $type = 'ol';
                        if (preg_match('/[*+-]/', $block['list-type'])) {
                            $type = 'ul';
                        } else if ($block['list-type'] == '') {
                            $type = 'div';
                        }
                        $markup .= '</'.$type.'>'."\n";
                    }

                    break;

                case 'block-level markup':
                    $markup .= $block['text']."\n";

                    break;

                case 'empty':
                    continue;

                case 'br':
                    $markup .= '<br />'."\n";
                    continue;

                case 'table':
                    $markup .= '<table';
                    if (isset($block['attribute']))
                        $markup .= ' '.$block['attribute'];
                    $markup .= '>'."\n";
                    foreach ($block['elements'] as $col) {
                        $markup .= '<tr';
                        if (isset($col['attribute']))
                            $markup .= ' '.$col['attribute'];
                        $markup .= '>'."\n";
                        foreach ($col['elements'] as $row) {
                            $tag = $row['tag'];
                            $markup .= '<'.$tag;

                            if (isset($row['attribute']))
                                $markup .= ' '.$row['attribute'];
                            $markup .= '>';
                            if (strpos($row['text'], "\n") !== false)
                                $markup .= $this->parse_block_elements($row['text']);
                            else
                                $markup .= $this->parse_span_elements($row['text']);
                            $markup .= '</'.$tag.'>';
                        }
                        $markup .= '</tr>'."\n";
                    }

                    $markup .= '</table>'."\n";
                    continue;

                default:
                    $markup .= $block['text']."\n";
            }
        }
        if (isset($buffer[0])) {
            $buffer = $this->_link_repl($buffer);

            $markup .= $buffer;

        }

        return $markup;
    }


    function parse_span_elements($text, $markers = array("  \n", '{{{', '![', '&', '*', "''", '<', '[', '\\', '__', ',,', '^', '`', '~~'))
    {
        if (isset($text[1]) === false or $markers === array()) {
            return $text;
        }

        // ~
        $markups = array();

        while ($markers) {
            $closest_marker = null;
            $closest_marker_index = 0;
            $closest_marker_position = null;

            foreach ($markers as $index => $marker) {
                $marker_position = strpos($text, $marker);

                if ($marker_position === false) {
                    unset($markers[$index]);

                    continue;
                }

                if ($closest_marker === null or $marker_position < $closest_marker_position) {
                    $closest_marker = $marker;
                    $closest_marker_index = $index;
                    $closest_marker_position = $marker_position;
                }
            }

            // ~
            if ($closest_marker === null or isset($text[$closest_marker_position + 1]) === false) {
                $markups[] = $text;

                break;
            } else {
                $markups[] = substr($text, 0, $closest_marker_position);
            }

            $text = substr($text, $closest_marker_position);

            // ~
            unset($markers[$closest_marker_index]);

            $markup = '';

            // ~
            switch ($closest_marker) {
                case "  \n":
                    // markdown style <br>
                    $markup = array('tag' => 'br');

                    $offset = 3;

                    break;

                case '{{{':
                    if (preg_match("/^(({{{(?:(?:[^{}]+|{[^{}]+}(?!})|(?<!{){{1,2}(?!{)|(?<!})}{1,2}(?!}))|(?2))++}}})|".
                        # {{{{{{}}}, {{{}}}}}}, {{{}}}
                        "(?:(?!<{{{){{{}}}(?!}}})|{{{(?:{{{|}}})}}}))/", $text, $matches)) {

                        // nowiki or pre-blocks
                        $nowiki = substr($matches[0], 3, -3);
                        if (strpos($nowiki, "\n") !== false) {
                            if ($nowiki[0] == "\n") {
                                $nowiki = substr($nowiki, 1); // trash first "\n" char
                            }

                            $processor = '';
                            if ($nowiki[0] == '#' and $nowiki[1] == '!') {
                                if (($p = strpos($nowiki, "\n")) !== false) {
                                    list($processor, $dummy) = explode(' ', substr($nowiki, 2, $p - 2));
                                    if ($processor == 'wiki') {
                                        $nowiki = substr($nowiki, $p);
                                    }
                                }
                            }
                            // processor block
                            if (isset($processor[0])) {
                                if ($processor == 'wiki') {
                                    $markup = $this->parse_block_elements($nowiki);
                                } else {
                                    $markup = array('type' => 'processor',
                                                'processor' => $processor,
                                                'text' => $nowiki,
                                              );
                                }
                            } else {
                                $markup = array('type' => 'code block',
                                            'text' => $nowiki,
                                          );
                            }
                        } else {
                            // inline nowiki
                            $nowiki = _html_escape($nowiki);
                            $attributes = array('class' => 'wiki');
                            $markup = array('tag' => 'tt',
                                        'attributes' => $attributes,
                                        'text' => $nowiki,
                                      );
                        }
                        $offset = strlen($matches[0]);
                    } else {
                        $markup .= $closest_marker;
                        $offset = 3;
                    }
                    break;

                case '![':
                case '[':
                    // markdown style links, images
                    if (strpos($text, ']') and preg_match('/\[((?:[^][]|(?R))*)\]/', $text, $matches)) {
                        $element = array(
                            '!' => $text[0] === '!',
                            'a' => $matches[1],
                        );
                        $link = $matches[0]; // save matches
                        $element['!'] and $link = '!'.$link;

                        $offset = strlen($matches[0]);

                        $element['!'] and $offset++;

                        $remaining_text = substr($text, $offset);

                        if ($remaining_text[0] === '(' and preg_match('/\([ ]*(.*?)(?:[ ]+[\'"](.+?)[\'"])?[ ]*\)/', $remaining_text, $matches)) {
                            $element['»'] = $matches[1];

                            if (isset($matches[2])) {
                                $element['#'] = $matches[2];
                            }

                            $offset += strlen($matches[0]);
                        } elseif ($this->reference_map) {
                            $reference = $element['a'];

                            if (preg_match('/^\s*\[(.*?)\]/', $remaining_text, $matches)) {
                                $reference = $matches[1] ? $matches[1] : $element['a'];

                                $offset += strlen($matches[0]);
                            }

                            $reference = strtolower($reference);

                            if (isset($this->reference_map[$reference])) {
                                $element['»'] = $this->reference_map[$reference]['»'];

                                if (isset($this->reference_map[$reference]['#'])) {
                                    $element['#'] = $this->reference_map[$reference]['#'];
                                }
                            } else {
                                unset($element);
                            }
                        } else {
                            unset($element);
                        }
                    }

                    if (isset($element)) {
                        $element['»'] = str_replace('&', '&amp;', $element['»']);
                        $element['»'] = str_replace('<', '&lt;', $element['»']);

                        if ($element['!']) {
                            $markup = array('tag' => 'img',
                                        'src' => $element['»'],
                                        'alt' => $element['a'],
                                      );

                            isset($element['#']) and $markup['title'] = $element['#'];
                        } else {
                            $element['a'] = $this->parse_span_elements($element['a'], $markers);

                            $markup = array('tag' => 'a',
                                        'href' => $element['»'],
                                        'text' => $element['a'],
                                      );

                            isset($element['#']) and $markup['title'] = $element['#'];
                        }

                        unset($element);
                    } /* else if (preg_match('/^!?\[\[(.*)\]\]$/', $link, $matches)) {
                        // Wiki Links
                        $markup = array('tag' => 'a',
                                    'href' => '#',
                                    'text' => $matches[1],
                                  );
                    } */ else {
                        $markup .= $closest_marker;

                        $offset = $closest_marker === '![' ? 2 : 1;
                    }

                    break;

                case '&':
                    // entities
                    if (preg_match('/^&#?\w+;/', $text, $matches)) {
                        $markup = $matches[0];

                        $offset = strlen($matches[0]);
                    } else {
                        $markup .= '&amp;';

                        $offset = 1;
                    }
                    break;


                case '^':
                    // superscript
                    if (preg_match('/^(\^{1,2})([^\^ ](?:.*?)[^\^ ])\1(?!^)/s', $text, $matches)) {
                        $matches[2] = $this->parse_span_elements($matches[2], $markers);

                        $markup = array('tag' => 'sup', 'text' => $matches[2]);
                        $offset = strlen($matches[0]);
                    } else {
                        $markup .= $closest_marker;
                        $offset = 1;
                    }

                    break;

                case "''":
                    // wiki style strong, em
                case '*':
                case '_':
                    $len = strlen($closest_marker);
                    // markdown style strong, em
                    // strong, em
                    if (isset($text[$len]) and $text[$len] === $closest_marker[0] and preg_match($this->strong_regex[$closest_marker], $text, $matches)) {
                        $markers[] = $closest_marker;
                        $matches[1] = $this->parse_span_elements($matches[1], $markers);

                        $markup = array('tag' => 'strong', 'text' => $matches[1]);
                    } elseif (preg_match($this->em_regex[$closest_marker], $text, $matches)) {
                        $markers[] = $closest_marker;
                        $matches[1] = $this->parse_span_elements($matches[1], $markers);
                        $markup = array('tag' => 'em', 'text' => $matches[1]);
                    }

                    if (isset($matches) and $matches) {
                        $offset = strlen($matches[0]);
                    } else {
                        $markup .= $closest_marker;

                        $offset = $len;
                    }
                    break;

                case '<':
                    if (strpos($text, '>') !== false) {
                        if ($text[1] === 'h' and preg_match('/^<(https?:[\/]{2}[^\s]+?)>/i', $text, $matches)) {
                            $element_url = $matches[1];
                            $element_url = str_replace('&', '&amp;', $element_url);
                            $element_url = str_replace('<', '&lt;', $element_url);

                            $markup = '<a href="'.$element_url.'">'.$element_url.'</a>';

                            $offset = strlen($matches[0]);
                        } elseif (strpos($text, '@') > 1 and preg_match('/<(\S+?@\S+?)>/', $text, $matches)) {
                            $markup = '<a href="mailto:'.$matches[1].'">'.$matches[1].'</a>';

                            $offset = strlen($matches[0]);
                        } elseif (preg_match('/^<\/?\w.*?'.'>/', $text, $matches)) {
                            $markup .= $matches[0];

                            $offset = strlen($matches[0]);
                        } else {
                            $markup .= '&lt;';

                            $offset = 1;
                        }
                    } else {
                        $markup .= '&lt;';

                        $offset = 1;
                    }
                    break;

                case '\\':
                    if (in_array($text[1], $this->special_characters)) {
                        $markup .= $text[1];

                        $offset = 2;
                    } else {
                        $markup .= '\\';

                        $offset = 1;
                    }
                    break;

                case '`':
                    if (preg_match('/^(`+)[ ]*(.+?)[ ]*\1(?!`)/', $text, $matches)) {
                        $element_text = $matches[2];

                        $markup = array('tag' => 'code', 'text' => $element_text);

                        $offset = strlen($matches[0]);
                    } else {
                        $markup .= '`';

                        $offset = 1;
                    }

                    break;

                case ',,':
                case '__':
                case '--':
                case '~~':
                    if (preg_match('@^'.$closest_marker.'(?=\S)(.+?)(?<=\S)'.$closest_marker.'@', $text, $matches)) {
                        $matches[1] = $this->parse_span_elements($matches[1], $markers);

                        $markup = $this->simple_tags[$closest_marker][1].$matches[1].$this->simple_tags[$closest_marker][0];

                        $offset = strlen($matches[0]);
                    } else {
                        $markup .= $closest_marker;

                        $offset = 2;
                    }

                    break;
            }

            if (isset($offset)) {
                $text = substr($text, $offset);
            }
            $markups[] = $markup;

            $markers[$closest_marker_index] = $closest_marker;
        }

        return $this->render($markups);
    }

    function roman2int($roman)
    {
        $romans = array(
                'M' => 1000,
                'CM' => 900,
                'D' => 500,
                'CD' => 400,
                'C' => 100,
                'XC' => 90,
                'L' => 50,
                'XL' => 40,
                'X' => 10,
                'IX' => 9,
                'V' => 5,
                'IV' => 4,
                'I' => 1,
                );

        $result = 0;
        $roman = strtoupper($roman);
        foreach ($romans as $key => $value) {
            while (strpos($roman, $key) === 0) {
                $result += $value;
                $roman = substr($roman, strlen($key));
            }
        }
        return $result;
    }

    var $reference_map = array();

    //
    // Read-only
    //

    var $strong_regex = array(
        '*' => '/^[*]{2}((?:[^*]|[*][^*]*[*])+?)[*]{2}(?![*])/s',
        '_' => '/^__((?:[^_]|_[^_]*_)+?)__(?!_)/s',
        "''" => "/^'''((?U)(?<!\s)(?:[^']|[^']'(?!')|''((?:[^']|[^']'(?!'))*?)'')*?)(?!\s)'''(?!')/s",
    );

    var $em_regex = array(
        '*' => '/^[*]((?:[^*]|[*][*][^*]+?[*][*])+?)[*](?![*])/s',
        '_' => '/^_((?:[^_]|__[^_]*__)+?)_(?!_)/s',
        "''" => "/^''((?:[^']|[^']'(?!')|'''((?:[^']|(?<!')'(?!'))+?)''')+?)''(?!')/s",
    );

    var $sup_regex = array(
        '^' => '/^\^([^\^ ](.*?)[^\^ ])\^(?!^)/s',
        '^^' => '/^\^{2}([^\^ ](.*?)[^\^ ])\^{2}(?!^)/s',
    );

    var $simple_tags = array(
        '--' => array('</del>', '<del>'),
        '~~' => array('</del>', '<del>'),
        ',,' => array('</sub>', '<sub>'),
        '__' => array('</u>', '<u class="underline">'),
    );

    var $special_characters = array(
        '\\', '`', '*', '_', '{', '}', '[', ']', '(', ')', '>', '#', '+', '-', '.', '!',
    );

    var $text_level_elements = array(
        'a', 'br', 'bdo', 'abbr', 'blink', 'nextid', 'acronym', 'basefont',
        'b', 'em', 'big', 'cite', 'small', 'spacer', 'listing',
        'i', 'rp', 'sub', 'code',          'strike', 'marquee',
        'q', 'rt', 'sup', 'font',          'strong',
        's', 'tt', 'var', 'mark',
        'u', 'xm', 'wbr', 'nobr',
                          'ruby',
                          'span',
                          'time',
    );
}

// vim:et:sts=4:sw=4:
