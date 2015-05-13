<?php
// Copyright 2003-2014 Won-Kyu Park <wkpark@gmail.com>
// All rights reserved. Distributable under GPLv2 see COPYING
// a table import plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@gmail.com>
// Date: 2014-01-21
// Name: Table Import plugin
// Description: a Table Import plugin
// URL: MoniWiki:TableImportPlugin
// Version: $Revision: 1.0 $
// License: GPLv2
//
// Usage: [[ImportTable]] or ?action=importtable

function macro_ImportTable($formatter, $value, $params = array()) {
    global $DBInfo;
    global $HTTP_USER_AGENT;

    $COLS_MSIE = 80;
    $COLS_OTHER = 85;
    $cols = preg_match('/MSIE/', $HTTP_USER_AGENT) ? $COLS_MSIE : $COLS_OTHER;

    $rows = $params['rows'] > 10 ? $params['rows']: 10;
    $cols = $params['cols'] > 60 ? $params['cols']: $cols;

    $tabletext = $params['tablecontent'];

    $url = $formatter->link_url($formatter->page->urlname);

    $formatter->register_javascripts('textarea.js');
    $form = "<form method='post' action='$url'>\n";
    $form.= <<<FORM
    <div class="resizable-textarea">
        <textarea class="wiki resizable" name="tablecontent"
        rows="$rows" cols="$cols">$tabletext</textarea></div>
FORM;
    $preview = _("Convert");
    $form.= <<<FORM
        <input type="hidden" name="action" value="ImportTable" />
        <span class='button'><input type="submit" name="button_preview" class="button" value="$preview" /></span>
FORM;
    $form.= "</form>\n";

    return '<div>'.$form.'</div>';
}

function _a_callback($matches) {
    $attrs = array();
    $attr = trim(str_replace("\n", ' ', $matches[1]));
    $title = trim(str_replace("\n", ' ', $matches[2]));
    $chunks = preg_split('/((?:href|title|class)\s*=\s*)/i', $attr, -1, PREG_SPLIT_DELIM_CAPTURE);
    for ($i = 1, $sz = count($chunks); $i < $sz; $i+= 2) {
        $key = strtolower(trim($chunks[$i], ' ='));
        $val = trim($chunks[$i + 1], ' \'"');
        if ($key == 'href') {
        } else if ($key == 'title') {
        }
    }

    return '[['.$title.']]';
}

function _table_callback($matches) {
    $attr = trim(str_replace("\n", ' ', $matches[1]));
    $chunks = preg_split('/((?:width|cellspacing|cellpadding|border|class|align|style)\s*=\s*)/i', $attr, -1, PREG_SPLIT_DELIM_CAPTURE);
    $attr = '';
    for ($i = 1, $sz = count($chunks); $i < $sz; $i+= 2) {
        $key = strtolower(trim($chunks[$i], ' ='));
        $val = trim($chunks[$i + 1], ' \'"');
        if ($key == 'width') {
            if (is_numeric($val))
                $val.= 'px';
            $attr.= '<tablewidth="'.$val.'">';
        } else if ($key == 'align') {
            $attr.= '<tablealign="'.$val.'">';
        } else if ($key == 'style') {
            $attr.= '<tablestyle="'.$val.'">';
        } else if ($key == 'border') {
            $attr.= '<tableborder="'.$val.'">';
        }
    }
    return '||'.$attr;
}

function _td_callback($matches) {
    $attr = trim(str_replace("\n", ' ', $matches[2]));
    $chunks = preg_split('/((?:colspan|rowspan|class|width|border|align|style)\s*=\s*)/i', $attr, -1, PREG_SPLIT_DELIM_CAPTURE);
    $attr = '';
    for ($i = 1, $sz = count($chunks); $i < $sz; $i+= 2) {
        $key = strtolower(trim($chunks[$i], ' ='));
        $val = trim($chunks[$i + 1], ' \'"');
        if ($key == 'colspan') {
            if ($val != 1)
                $attr.= '<-'. $val.'>';
        } else if ($key == 'rowspan') {
            if ($val != 1)
                $attr.= '<|'. $val.'>';
        } else if ($key == 'align' || $key == 'class') {
            switch ($val) {
            case 'center':
                $attr.= '<:>';
                break;
            case 'left':
                $attr.= '<(>';
                break;
            case 'right':
                $attr.= '<)>';
                break;
            default:
                break;
            } 
        } else if ($key == 'width') {
            if (is_numeric($val))
                $val.= 'px';
            $attr.= '<width="'.$val.'">';
        } else if ($key == 'style') {
            $attr.= '<style="'.$val.'">';
        }
    }

    return '||'.$attr;
}

function do_ImportTable($formatter, $params = array()) {
    global $DBInfo;
    global $HTTP_USER_AGENT;

    $COLS_MSIE = 80;
    $COLS_OTHER = 85;
    $cols = preg_match('/MSIE/', $HTTP_USER_AGENT) ? $COLS_MSIE : $COLS_OTHER;

    $rows = $params['rows'] > 5 ? $params['rows'] : 8;
    $cols = $params['cols'] > 60 ? $params['cols']: $cols;

    $url = $formatter->link_url($formatter->page->urlname);

    if (!empty($params['tablecontent'])) {
        $tabletext = _stripslashes($params['tablecontent']);
        $tabletext = str_replace("\r", '', $tabletext);

        $lines = explode("\n", $tabletext);

        // check tab mode
        $tabmode = false;
        if (strpos($tabletext, "\t") !== false) {
            $tabmode = true;
        } else {
            // preserve table attributes
            $tabletext = preg_replace('/(<)([\:\(\)\|\-_\^v]|width|bgcolor|'.
                            'colspan|rowspan|#|'.
                            'table(?:width|style|border|bgcolor)|style|rowbgcolor)/', "\007\\2", $tabletext);
            // remove some tags
            $tabletext = strip_tags($tabletext, '<table><td><th><tr><br><img><hr><a><b><i><sub><sup><del><tt><u><strong>');
            // convert basic wiki tags
            $tabletext = str_ireplace(
                            array('<b>', '</b>', '<i>', '</i>', '<strong>', '</strong>',
                                  '<sub>', '</sub>', '<sup>', '</sup>', '<del>', '</del>', '<hr>'),
                            array("'''", "'''", "''", "''", "'''", "'''",
                                  ',,', ',,', '^^', '^^', '~~', '~~', "\n----\n"),
                            $tabletext);

            // BR macro
            $tabletext = preg_replace('@<br\s*[^>]*>\n?@is', '[[BR]]', $tabletext);
            // images
            $tabletext = preg_replace('@<img\s[^>]*src=(\'|")?([^\'"]+)(?1)[^>]*>@is', '\\2', $tabletext);
            // href
            $tabletext = preg_replace_callback('@<a\s([^>]*)>([^<]*)</a>@is', '_a_callback', $tabletext);

            // remove some table tags
            $tabletext = preg_replace('@<(?:tr|/td|/th|/table)[^>]*>\s*@is', '', $tabletext);
            $tabletext = preg_replace('@\s*<tr>\s*@is', '', $tabletext);
            // parse td attributes
            $tabletext = preg_replace_callback('@(<t(?:d|h)([^>]*)>)@i', '_td_callback', $tabletext);
            // table attributes
            $tabletext = preg_replace_callback('@<table([^>]*)>\s*\|\|@is', '_table_callback', $tabletext);
            $tabletext = preg_replace('@</tr>\s*@is', "||\n", $tabletext);

            // revert <
            $tabletext = str_replace("\007", '<', $tabletext);
            $lines = explode("\n", $tabletext);
        }

        // trash empty last line
        $end = end($lines);
        if (!isset($end[0])) array_pop($lines);

        // count maximum tabs
        $maxtab = 1;
        for ($i = 0, $sz = count($lines); $i < $sz; $i++) {
            $line = $lines[$i];
            if ($tabmode) {
                // from excel or tab separated table contents
                $tabs[$i] = substr_count($line, "\t");
                $line = preg_replace("/\t(?=\t)/", ' || ', $line);
                $line = str_replace("\t", '||', $line);
                $lines[$i] = '||'.$line.'||';
                if ($tabs[$i] > $maxtab) $maxtab = $tabs[$i];
            } else {

            }
        }
        if ($tabmode) {
            for ($i = 0, $sz = count($tabs); $i < $sz; $i++) {
                if ($tabs[$i] < $maxtab) {
                    $tab = str_repeat('||', $maxtab - $tabs[$i]);
                    $lines[$i] = $tab.$lines[$i];
                }
            }
        }
        $tabletext = implode("\n", $lines);
    }

    if (!empty($tabletext)) {
        $formatter->send_header('', $params);
        $formatter->send_title(_("Preview"), '', $params);
        $formatter->send_page($tabletext."\n----");
        $params['tablecontent'] = $tabletext;
        echo macro_ImportTable($formatter, '', $params);
        $formatter->send_footer('', $params);
    } else if (!$tabletext) {
        $formatter->send_header('', $params);
        $formatter->send_title(_("Import Tables"), '', $params);
        echo macro_ImportTable($formatter, '', $params);
        $formatter->send_footer('', $params);
    }
}

// vim:et:sts=4:sw=4:
