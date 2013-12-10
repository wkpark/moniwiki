<?php
// Copyright 2008 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a savepage action plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Since: 2008-12-29
// Date: 2013-05-22
// Name: a autosave action plugin
// Description: a autosave action plugin
// URL: MoniWiki:AutoSavePlugin
// Version: $Revision: 1.2 $
// License: GPL
// Usage: add the config variable $use_autosave=1; to config.php
//

function do_autosave($formatter,$options) {
    global $DBInfo;

    if (session_id() == '') { // ip based
        if ($DBInfo->user->id == 'Anonymous') {
            $myid = md5($_SERVER['REMOTE_ADDR'].'.'.'MONIWIKI'); // IP based for Anonymous user XXX
        } else {
            $myid = md5($DBInfo->user->id.$_SERVER['REMOTE_ADDR'].'.'.'MONIWIKI');
        }
    } else {
        if (0) {
            if ($_SESSION['_autosave'])
                $myid = $_SESSION['_autosave'];
            else {
                $myid = session_id();
                $_SESSION['_autosave'] = $myid;
            }
        } else {
            if ($DBInfo->user->id == 'Anonymous') {
                $myid = md5($_SERVER['REMOTE_ADDR'].'.'.'MONIWIKI'); // IP based for Anonymous user XXX
            } else {
                $myid = md5($DBInfo->user->id.$_SERVER['REMOTE_ADDR'].'.'.'MONIWIKI');
            }
        }
    }
    $myid = md5($myid . $formatter->page->name);
    if (isset($options['section']))
        $myid.= '.'.$options['section']; // XXX section support

    $save = new Cache_text('autosave');

    if (!empty($options['retrive'])) {
        $saved = $save->fetch($myid);
        $os = rtrim($saved);

        $stamp = $save->mtime($myid);
        echo $stamp."\n".$os;
        return true;
    } else if (!empty($options['remove'])) {
        $save->remove($myid);
        echo 'true';
        return true;
    }

    $savetext = $options['savetext'];
    $datestamp = substr($options['datestamp'], 0, 10); // only 10-digits used

    $savetext = preg_replace("/\r\n|\r/", "\n", $savetext);
    $savetext = _stripslashes($savetext);

    if ($save->exists($myid) and $save->mtime($myid) > $datestamp) {
        echo 'false';
        return false;
    }
    $save->update($myid, $savetext);
    echo 'true';
    return true;
}

// vim:et:sts=4:sw=4:
?>
