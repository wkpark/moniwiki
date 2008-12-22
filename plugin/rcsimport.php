<?php
// Copyright 2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a rcsimport plugin for the MoniWiki
//
// Usage: [[Test]]
//
// $Id$

function do_post_rcsimport($formatter,$options) {
    global $DBInfo;
    if (!$DBInfo->version_class) {
        $msg= _("Version info is not available in this wiki");
        return "<h2>$msg</h2>";
    }
    if (!trim($options['rcsfile'])) {
        $formatter->send_header('',$options);
        $formatter->send_title('','',$options);
        $COLS_MSIE= 80;
        $COLS_OTHER= 85;

        $cols= preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT']) ? $COLS_MSIE : $COLS_OTHER;

        print <<<FORM
<form method='post' action=''>
<div>
<textarea name='rcsfile' class='' cols='$cols' rows='20'>
</textarea></div>
<input type='hidden' name='action' value='rcsimport' />
FORM;
        if ($DBInfo->security->is_protected("rcsimport",$options))
            print _("Password"). ": <input type='password' name='passwd' /> ";
        print <<<FORM
<input type='submit' value='Import RCS' />
</form>
FORM;
        $formatter->send_footer('',$options);
        return;
    }

    getModule('Version',$DBInfo->version_class);
    $class='Version_'.$DBInfo->version_class;
    $version=new $class ($DBInfo);
    header('Content-type:text/plain');
    if (method_exists($version,'import')) {
        $content=base64_decode($options['rcsfile']);

        $body = $content;
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
        $version->import($options['page'],$content);
    }
    print 'OK';
}

// vim:et:sts=4:
?>
