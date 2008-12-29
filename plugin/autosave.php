<?php
// Copyright 2008 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a savepage action plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2008-12-29
// Name: a autosave action plugin
// Description: a autosave action plugin
// URL: MoniWiki:AutoSavePlugin
// Version: $Revision$
// License: GPL
// Usage: add the config variable $use_autosave=1; to config.php
//
// $Id$

function do_autosave($formatter,$options) {
    global $DBInfo;

    if ($DBInfo->nosession) { // ip based
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
    $savetext = $options['savetext'];
    $datestamp = substr($options['datestamp'], 0, 10); // only 10-digits used

    $myid = md5($myid . $formatter->page->name);
    if (isset($options['section']))
        $myid.= '.'.$options['section']; // XXX section support

    $savetext = preg_replace("/\r\n|\r/", "\n", $savetext);
    $savetext = _stripslashes($savetext);

    $save = new Cache_text('autosave');

    if ($options['retrive']) {
        $saved = $save->fetch($myid);
        $os = rtrim($saved);

        $stamp = $save->mtime($myid);
        echo $stamp."\n".$os;
        return true;
    }
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
