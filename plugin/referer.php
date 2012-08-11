<?php
// Copyright 2007-2008 Keizie <keizie at gmail.com>
// All rights reserved. Distributable under GPL see COPYING
// a referer plugin for the MoniWiki
//
// Author: Keizie <keizie at gmail.com>
// Date: 2007-04-30
// Name: a referer plugin
// Description: show referer plugin
// URL: MoniWiki:RefererPlugin
// Version: $Revision: 1.6 $
// License: GPL
//
// Usage: [[Referer]]

function do_referer($formatter, $options)
{
    $out= macro_referer($formatter,$options['value'],$options);

    return $out;
}

/* snippet from http://au2.php.net/manual/en/function.fseek.php */
function tail_file($file, $lines)
{
    $handle = fopen($file, "r");
    if (!is_resource($handle)) return '';

    $linecounter = $lines;
    $pos = -2;
    $beginning = false;
    $text = array();
    while ($linecounter > 0) {
        $t = " ";
        while ($t != "\n") {
            if(fseek($handle, $pos, SEEK_END) == -1) {
                $beginning = true; break; }
            $t = fgetc($handle);
            $pos --;
        }
        $linecounter --;
        if($beginning) rewind($handle);
        $text[$lines-$linecounter-1] = fgets($handle);
        if($beginning) break;
    }
    fclose ($handle);
    return $text;
}

function macro_Referer($formatter, $value, &$options) {
    global $DBInfo;

    if (empty($DBInfo->use_referer))
        return "[[Referer macro: $use_referer is off.]]";
    $referer_log_filename = $DBInfo->cache_dir."/referer/referer.log";

    if ($value !== true) {
        // [[referer]] or ?action=referer
        $needle = $formatter->page->urlname;
    } else {
        // [[referer()]]
        unset($needle);
    }

    if ($needle and false) {
        // so slow XXX
        $handle = fopen($referer_log_filename, 'r');
        if (!is_resource($handle)) return '';
        $logs = array();
        while(!feof($handle)) {
            $line = fgets($handle);
            list(, $pagename,) = explode("\t", $line);
            if ($pagename == $needle)
                $logs[] = $line;
            if ($count > 100) break;
            $count++;
        }
        fclose($handle);
        $logs = array_reverse($logs);
    } else {
        $number_of_lines = 200; // XXX
        $logs = tail_file ($referer_log_filename, $number_of_lines);
    }

    $log = array();
    $counter = 10; // XXX
    $count = 0;
    foreach ($logs as $line) {
        list(, $pagename,) = explode("\t", $line);
        if (strcmp($pagename,$needle) == 0)
            $log[] = $line;
        if ($count > $counter) break;
        $count++;
    }
    $logs = $log;

    $tz_offset= $formatter->tz_offset;

    $length = sizeof($logs);
    for ($c = 0; $c < $length; $c++) {
        $fields = explode("\t", $logs[$c]);
        $fields[0] = date("Y-m-d H:i:s", strtotime($fields[0])+$tz_offset);
        $fields[1] = $formatter->link_tag(_rawurlencode($fields[1]), "", urldecode($fields[1]));
        $found = '';
        if (ereg("[?&][pqQ](uery)?=([^&]+)&?", $fields[2], $regs)) {
            $check = strpos($regs[2],'%'); # is it urlecnoded ?
            if ($check !== false) {
                $found = urldecode($regs[2]);
                if (function_exists('iconv')) {
                    $test = false;
                    if (strcasecmp('utf-8',$DBInfo->charset) != 0) {
                        $test = iconv('utf-8', $DBInfo->charset, $found);
                        if ($test !== false)
                            $found = $test;
                    }
                    if ($test === false and !empty($DBInfo->url_encodings)) {
                        $cs = explode(',',$DBInfo->url_encodings);
                        foreach ($cs as $c) {
                            $test = @iconv($c, $DBInfo->charset, $found);
                            if ($test !== false) {
                                $found = $test;
                                break;
                            }
                        }
                    }
                }
            } else {
                $found = $regs[2];
            }
        }
        $fields[2] = (!empty($found) ? "[ $found ] " : '') ."<a href='$fields[2]'>".urldecode($fields[2])."</a>";

        if (isset($needle)) unset($fields[1]);
        $logs[$c] = "<td class='date' style='width:20%'>". implode("</td><td>", $fields) ."<td>";
    }
    $ret = '';
    if ($length > 0) {
        $ret = "\n<table>";
        $ret.= "<caption>"._("Referer history")."</caption>";
        $ret.= "<tr>";
        $ret.= implode("</tr>\n<tr>", $logs);
        $ret.= "</tr></table>\n";
    }

    return '<div class="Referer">'.$ret.'</div>';
}

// vim:et:sts=4:sw=4:
?>
