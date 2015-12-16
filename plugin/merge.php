<?php
// Copyright 2003-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a revert plugin for the MoniWiki
//
// Author: wkpark <wkpark@kldp.org>
// Since: 2007-01-06
// Modified: 2015-11-24
// Name: Merge Plugin
// Description: Merge page's history
// PluginType: action
// URL: MoniWiki:MergePlugin
// Version: $Revision: 1.5 $
// Depend: 1.1.3
// License: GPLv2
//
// Usage: ?action=merge&name=foobar
//

function macro_Merge($formatter, $value = '', $params=array()) {
    $pagename = isset($value[0]) ? $value : $formatter->page->name;
    $acts = array('recall'=>'view', 'raw'=>'source');

    if (isset($pagename[0]))
        $acts['merge&amp;name='.$pagename] = 'merge';
    $params['info_actions'] = $acts;

    $params['title'] = '<h3>'.sprintf(_("Old Revisions of the %s"),
        _html_escape($formatter->page->name)).'</h3>';
    $out = $formatter->macro_repl('Info', $formatter->page->name, $params);
    return $out;
}

function do_merge($formatter, $params = array()) {
    global $DBInfo;

    if ($_SERVER['REQUEST_METHOD'] == 'POST' &&
            !$DBInfo->security->writable($params)) {
        $params['title'] = _("Page is not writable");
        return do_invalid($formatter, $params);
    }

    // check full permission to edit
    $full_permission = true;
    if (!empty($DBInfo->no_full_edit_permission) or
            ($params['id'] == 'Anonymous' && !empty($DBInfo->anonymous_no_full_edit_permission)))
        $full_permission = false;

    // members always have full permission to edit
    if (in_array($params['id'], $DBInfo->members))
        $full_permission = true;

    $is_new = !$formatter->page->exists();
    if (!$is_new and !$full_permission) {
        $formatter->send_header('', $params);
        $title = _("You do not have full permission to merge this page.");
        $formatter->send_title($title, '', $params);
        $formatter->send_footer('', $params);
        return;
    }

    $pagename = isset($params['name'][0]) ? $params['name'] : '';

    $formatter->send_header('', $params);
    $force = 1;
    if (isset($_POST['name'][0]) and $DBInfo->hasPage($_POST['name'])) {
        $force = 0;
        if ($_POST['force']) $force = 1;
    }

    // validate rev
    if (!empty($params['rev'])) {
        $info = $formatter->page->get_info($params['rev']);
        if (empty($info[0])) {
            unset($params['rev']);
            if (!empty($_POST['rev']))
                unset($_POST['rev']);
        }
    }

    if (!empty($_POST['rev']) and isset($_POST['name'][0]) and $pagename !== $formatter->page->name and
            $DBInfo->hasPage($pagename)) {
        if (!empty($DBInfo->version_class)) {
            $REMOTE_ADDR = $_SERVER['REMOTE_ADDR'];

            $user = &$DBInfo->user;

            $comment = _stripslashes($params['comment']);
            $tag = '{MERGE}';
            if (!empty($comment))
                $comment = $tag.': '.$comment.': ';
            else
                $comment = $tag.': ';

            $log = $REMOTE_ADDR.';;'.$user->id.';;'.$comment;

            $version = $DBInfo->lazyLoad('version', $DBInfo);
            if (!method_exists($version, 'merge')) {
                // check merge method
                $formatter->send_title(_("No merge method available."), '', $params);
                $formatter->send_footer('', $params);
                return;
            }

            $params['log'] = $log;
            $ret = array();
            $params['retval'] = &$ret;
            // merge RCS revisions
            $merged = $version->merge($pagename, $formatter->page->name, $params);

            if (!$force) {
                $fname = tempnam($DBInfo->vartmp_dir, 'MERGED');
                $fp = fopen($fname.',v', 'w');
                if (is_resource($fp)) {
                    fwrite($fp, $merged);
                    fclose($fp);
                }

                // parse rlog
                require_once(dirname(__FILE__).'/Info.php');
                $out = $version->rlog($fname.',v', '');
                $params['simple'] = true;
                $info = _parse_rlog($formatter, $out, $params);

                @unlink($fname);
                @unlink($fname.',v');
            } else if ($merged !== false) {
                // $params['retval']['comment'] has merged versions information
                $log = $comment.$params['retval']['comment'];

                $DBInfo->addLogEntry($pagename,
                        $REMOTE_ADDR, $log, 'MERGE');
                $indexer = $DBInfo->lazyLoad('titleindexer');
                if ($is_new) $indexer->addPage($pagename);
                else $indexer->update($pagename);

                $info = '';
            }
        } else {
            $formatter->send_title(_("No version control available."), '', $params);
            $formatter->send_footer('', $params);
            return;
        }
        $params['.title'] = _("Merge result.");
        $formatter->send_title(sprintf(_("%s is successfully merged."),
                $formatter->page->name), '', $params);
        if (!$force)
            echo '<h3>'._("This is a testing merge. Please confirm force option to merge it.").'</h3>';

        echo $info;
        $formatter->send_footer('', $params);
        return;
    } else {
        if (!isset($params['name'][0]) || !$DBInfo->hasPage($params['name']))
            $title = _("Please select the original page to merge.");
        else if (empty($params['rev']))
            $title = _("Please select the revision to merge from.");
        else {
            if ($DBInfo->hasPage($formatter->page->name)) {
                $title = _("Are you really want to merge this page ?");
            }
        }
        $params['.title'] = _("Merge Page history.");
        $formatter->send_title($title, '', $params);
    }

    $pname = _html_escape($pagename);
    $lab = _("Summary");
    $rev = !empty($params['rev']) ? _html_escape($params['rev']) : '';
    if (!empty($rev) && isset($pagename[0]) && $DBInfo->hasPage($pagename)) {
        $extra = '<input type="checkbox" name="force" />'._("Force overwrite").'<br />';
        $placeholder = sprintf(_("Merge [[%s]] with [[%s]] from r%s: "), $pname, _html_escape($formatter->page->name), $rev);
        echo "<form method='post'>
$lab: <input name='comment' size='80' value='$comment' placeholder='$placeholder' /><br />\n";
        $btn = sprintf(_("Merge [[%s]] to [[%s]]:"), _html_escape($formatter->page->name), $pname);
        $msg = sprintf(_("Only WikiMaster can %s this page"),_("merge"));
        if ($DBInfo->security->is_protected("merge", $params))
            echo _("Password").": <input type='password' name='passwd' size='20' value='' />
$msg<br />\n";
        echo <<<FORM
    <input type='hidden' name='name' value='$pname' />
    <input type='hidden' name='action' value='merge' />
    <input type='hidden' name='rev' value='$rev' />
    <input type='submit' value='$btn' />$extra
    </form>
FORM;
    } else {
        $btn = _("Select Page to Merge");
        echo <<<FORM
    <form method='get'>
    <input name='name' value='$pname' />
    <input type='hidden' name='action' value='merge' />
    <input type='submit' value='$btn' />$extra
    </form>
FORM;
    }

    if (isset($pagename[0]) && $pagename !== $formatter->page->name && $DBInfo->hasPage($pagename))
        echo macro_Merge($formatter, $pagename, $params);
    $formatter->send_footer('', $params);
    return;
}

// vim:et:sts=4:sw=4:
