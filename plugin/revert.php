<?php
// Copyright 2007 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a revert plugin for the MoniWiki
//
// Author: wkpark <wkpark@kldp.org>
// Date: 2007-01-06
// Name: Rollback plugin
// Description: Rollback Plugin
// PluginType: macro,action
// ActionType: protected
// URL: to_plugin url/interwiki name etc.
// Version: $Revision: 1.5 $
// Depend: 1.1.3
// License: GPL
//
// Usage: ?action=revert&rev=1.1
//
// $Id: revert.php,v 1.5 2010/08/20 08:28:53 wkpark Exp $

function macro_Revert($formatter,$value,$options=array()) {
    $options['info_actions']=array('recall'=>'view','revert'=>'revert');
    $options['title']='<h3>'.sprintf(_("Old Revisions of the %s"),_html_escape($formatter->page->name)).'</h3>';
    $out= $formatter->macro_repl('Info','',$options);
    return $out;
}

function do_revert($formatter,$options) {
    global $DBInfo;

    $formatter->send_header('',$options);
    $force=1;
    if (isset($_POST['name'][0]) and $DBInfo->hasPage($_POST['name'])) {
        $force=0;
        if ($_POST['force']) $force=1;
    }

    // validate rev
    if (!empty($options['rev'])) {
        $info = $formatter->page->get_info($options['rev']);
        if (empty($info[0])) {
            unset($options['rev']);
            if (!empty($_POST['rev']))
                unset($_POST['rev']);
        }
    }
    if (!empty($_POST['rev']) and isset($_POST['name'][0]) and $force) {
        $is_new = false;
        if (!$formatter->page->exists()) $is_new = true;

        if (!empty($DBInfo->version_class)) {
            $REMOTE_ADDR=$_SERVER['REMOTE_ADDR'];

            $user=&$DBInfo->user;

            $comment=_stripslashes($options['comment']);

            $key=$DBInfo->getPageKey($formatter->page->name);

            $version = $DBInfo->lazyLoad('version', $DBInfo);
            if ($force) @unlink($key); // try to delete
            $ret=$version->co($formatter->page->name,$_POST['rev'],array('stdout'=>1));
            chmod($key,0666);

            $log=$REMOTE_ADDR.';;'.$user->id.';;'.$comment;
            $keyname=$DBInfo->_getPageKey($formatter->page->name);
            $DBInfo->addLogEntry($keyname, $REMOTE_ADDR,$comment,"SAVE");
            $indexer = $DBInfo->lazyLoad('titleindexer');
            if ($is_new) $indexer->addPage($formatter->page->name);
            else $indexer->update($formatter->page->name);
        } else {
            $formatter->send_title(_("No version control available."),"",$options);
            $formatter->send_footer('',$options);
            return;
        }
        $formatter->send_title(sprintf(_("%s is successfully rollback."),$formatter->page->name),"",$options);
        $formatter->send_footer('',$options);
        return;
    } else {
        if (empty($options['rev']))
            $title = _("Please select old revision to revert.");
        else {
            if ($DBInfo->hasPage($formatter->page->name)) {
                $title = _("Are you really want to overwrite this page ?");
                $extra='<input type="checkbox" name="force" />'._("Force overwrite").'<br />';
            } else {
                $title = _("Are you really want to revert this page ?");
            }
        }
        $formatter->send_title($title,"",$options);
    }

    $pagename=$formatter->page->name;
    $lab=_("Summary");
    $rev=!empty($options['rev']) ? $options['rev'] : '';
    if (!empty($rev)) {
        $comment=sprintf(_("Rollback to revision %s"),$rev);
        print "<form method='post'>
$lab: <input name='comment' size='80' value='$comment' /><br />\n";
        $btn=_("Revert page");
        $msg=sprintf(_("Only WikiMaster can %s this page"),_("revert"));
        if ($DBInfo->security->is_protected("revert",$options))
            print _("Password").": <input type='password' name='passwd' size='20' value='' />
$msg<br />\n";
        print "
    <input type='hidden' name='action' value='revert' />
    <input type='hidden' name='rev' value='$rev' />
    <input type='hidden' name='name' value='$pagename' />
    <input type='submit' value='$btn' />$extra
    </form>";
    }

    print macro_revert($formatter,$options['value'], $options);
    $formatter->send_footer('',$options);
    return;
}

// vim:et:sts=4:
?>
