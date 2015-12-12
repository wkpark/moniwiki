<?php
// Copyright 2003-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a DeleteFile plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2003-05-10
// Name: DeleteFile plugin
// Description: show DeleteFile form
// URL: MoniWiki:DeleteFilePlugin
// Version: $Revision: 1.0 $
// License: GPLv2
//
// Usage: ?action=deletefile
//

function do_post_DeleteFile($formatter,$options) {
    global $DBInfo;
    if ($_SERVER['REQUEST_METHOD'] == 'POST' &&
            !$DBInfo->security->writable($options)) {
        $options['title'] = _("Page is not writable");
        return do_invalid($formatter,$options);
    }

    if ($_SERVER['REQUEST_METHOD']=="POST") {
        if (!empty($options['value'])) {
            $key=$DBInfo->pageToKeyname(urldecode(_urlencode($options['value'])));
            $dir=$DBInfo->upload_dir."/$key";
            if (!is_dir($dir) and !empty($DBInfo->use_hashed_upload_dir)) {
                $dir = $DBInfo->upload_dir.'/'.get_hashed_prefix($key).$key;
            }
        } else {
            $dir=$DBInfo->upload_dir;
        }
    } else {
        // GET with 'value=filename' query string
        if ($p=strpos($options['value'],'/')) {
            $key=substr($options['value'],0,$p-1);
            $file=substr($options['value'],$p+1);
        } else
            $file=$options['value'];
    }

    if (isset($options['files']) or isset($options['file'])) {
        if (isset($options['file'])) {
            $options['files']=array();
            $options['files'][]=$options['file'];
        }

        if ($options['files']) {
            foreach ($options['files'] as $file) {
                $key=$DBInfo->pageToKeyname($file);

                if (!is_dir($dir."/".$file) && !is_dir($dir."/".$key)) {
                    $fdir=$options['value'] ? _html_escape($options['value']).':':'';
                    if (@unlink($dir."/".$file))
                        $log.=sprintf(_("File '%s' is deleted")."<br />",$fdir.$file);
                    else
                        $log.=sprintf(_("Fail to delete '%s'")."<br />",$fdir.$file);
                } else {
                    if ($key != $file)
                        $realfile = $key;
                    if (@rmdir($dir."/".$realfile))
                        $log.=sprintf(_("Directory '%s' is deleted")."<br />",$file);
                    else
                        $log.=sprintf(_("Fail to rmdir '%s'")."<br />",$file);
                }
            }
            $title = sprintf(_("Delete selected files"));
            $formatter->send_header("",$options);
            $formatter->send_title($title,"",$options);
            print $log;
            $formatter->send_footer('',$options);
            return;
        } else
            $title = sprintf(_("No files are selected !"));
    } else if ($file) {
        list($page,$file)=explode(':',$file);
        if (!$file) {
            $file=$page;
            $page=$formatter->page->name;
        }
        $page = _html_escape($page);
        $file = _html_escape($file);

        $link=$formatter->link_url($formatter->page->urlname);
        $out="<form method='post' action='$link'>";
        $out.="<input type='hidden' name='action' value='DeleteFile' />\n";
        if ($page)
            $out.="<input type='hidden' name='value' value=\"$page\" />\n";
        $out.="<input type='hidden' name='file' value=\"$file\" />\n<h2>";
        $out.=sprintf(_("Did you really want to delete '%s' ?"),$file).'</h2>';
        if ($DBInfo->security->is_protected("deletefile",$options))
            $out.=_("Password").": <input type='password' name='passwd' size='10' />\n";
        $out.="<input type='submit' value='"._("Delete")."' /></form>\n";
        $title = sprintf(_("Delete selected file"));
        $log=$out;
    } else {
        $title = sprintf(_("No files are selected !"));
    }
    $formatter->send_header("",$options);
    $formatter->send_title($title,"",$options);
    print $log;
    $formatter->send_footer('',$options);
    return;
}

// vim:et:sts=4:sw=4:
