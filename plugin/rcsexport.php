<?php
// Copyright 2006-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a rcsexport plugin for the MoniWiki
//
// Usage: MoniWiki:RcsExportPlugin
//
// $Id: rcsexport.php,v 1.2 2008/12/25 09:14:14 wkpark Exp $

function do_rcsexport($formatter,$options) {
    global $DBInfo;
    if (!$DBInfo->version_class) {
        $msg= _("Version info is not available in this wiki");
        return "<h2>$msg</h2>";
    }

    if ($options['mode'] == 'import') {
        _post_rcsimport($formatter, $options);
        return;
    }

    $version = $DBInfo->lazyLoad('version', $DBInfo);
    header('Content-type:text/plain');
    if (!$formatter->page->exists()) {
        header("HTTP/1.1 404 Not found");
        header("Status: 404 Not found");
        echo "Page not found";
    } else if (method_exists($version,'export')) {
        echo '#title '.$formatter->page->name."\n";
        echo '#charset '.strtoupper($DBInfo->charset)."\n";
        echo '#encrypt base64'."\n";
        echo chunk_split(base64_encode($version->export($options['page'])));
    } else {
        echo 'Not supported';
    }
}

function _post_rcsimport($formatter, $options) {
    global $DBInfo;

    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        $formatter->send_header('', $options);
        $formatter->send_title('', '', $options);
        $COLS_MSIE = 80;
        $COLS_OTHER = 85;

        $cols = preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT']) ? $COLS_MSIE : $COLS_OTHER;

        print <<<FORM
<form method='post' action=''>
<div>
<textarea name='rcsfile' class='' cols='$cols' rows='20'>
</textarea></div>
<input type='hidden' name='action' value='rcsexport' />
<input type='hidden' name='mode' value='import' />
FORM;
        if ($DBInfo->security->is_protected("rcsexport",$options))
            print _("Password"). ": <input type='password' name='passwd' /> ";
        print <<<FORM
<input type='submit' value='Import RCS' />
</form>
FORM;
        $formatter->send_footer('',$options);
        return;
    }

    $version = $DBInfo->lazyLoad('version', $DBInfo);
    header('Content-type:text/plain');
    if (method_exists($version,'import')) {
        $body = $options['rcsfile'];
        $meta = array();
        while(!empty($body)) {
            list($line,$body) = explode("\n",$body,2);
            if (!trim($line)) continue;
            if (preg_match('/^#(.*)$/',$line,$m)) {
                $p = strpos($line,' ');
                if ($p !== false) {
                    $tag = substr($line,0,$p);
                    $val = substr($line,$p+1);
                    if (in_array($tag, array('#title','#charset','#encrypt'))) {
                        $meta[$tag] = $val;
                    }
                }
            } else {
                $body = $line."\n".$body;
                break;
            }
        }
        if (isset($meta['#title']) or isset($meta['#charset'])) {
            $title = isset($meta['#title']) ? $meta['#title']: $options['page'];
            $charset = $meta['#charset'];

            $formatter->send_header('',$options);
            $formatter->send_title('','',$options);
            $COLS_MSIE= 80;
            $COLS_OTHER= 85;

            $cols= preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT']) ? $COLS_MSIE : $COLS_OTHER;

            $tmsg = _("Page name");
            print <<<FORM
<form method='post' action=''>
<div>
<textarea name='rcsfile' class='' cols='$cols' rows='20'>
$body
</textarea></div>
$tmsg: <input type='text' size='40' name='title' value='$title' /><br />
<input type='hidden' name='charset' value='$charset' />
<input type='hidden' name='action' value='rcsexport' />
<input type='hidden' name='mode' value='import' />
FORM;
            if ($DBInfo->security->is_protected("rcsexport",$options))
                print _("Password"). ": <input type='password' name='passwd' /> ";
            print <<<FORM
<input type='submit' value='Import RCS' />
</form>
FORM;
            $formatter->send_footer('',$options);
            return;
        }
        if (!empty($body))
            $body = base64_decode($body);

        $read = '';
        while(!empty($body)) {
            list($line,$body) = explode("\n",$body,2);
            if (preg_match('/^\s+(.*):(\d+\.\d+);\s*(strict;)?$/',$line,$m)) {
                $line = "\t".$DBInfo->rcs_user.':'.$m[2].';';
                $read.=$line."\n";
                break;
            }
            $read.=$line."\n";
        }
        $content= $read.$body;

        if (!empty($options['title'])) $options['page'] = $options['title'];
        if ($options['charset'] and (strcasecmp($options['charset'],$DBInfo->charset) != 0) and function_exists('iconv')) {
            $t = @iconv($options['charset'], $DBInfo->charset, $content);
            if (!empty($t))
                $content = $t;
        }
        if (isset($content[0])) {
            $test = $version->import($options['page'],$content);
        }

        $options['value'] = $options['page'];
        do_goto($formatter, $options);
        return;
    }
}

// vim:et:sts=4:
