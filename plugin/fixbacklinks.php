<?php
// Copyright 2003-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a fixbacklinks action plugin for the MoniWiki
//
// Since: 2003-06-30
// Author: Won-Kyu Park <wkpark at kldp.org>
// Date: 2015-05-12
// Name: FixBacklinks
// License: GPLv2
//
// Usage: ?action=fixbacklinks
//

function do_post_fixbacklinks($formatter, $options = array()) {
    global $DBInfo;
    if ($_SERVER['REQUEST_METHOD'] == 'POST' &&
            !$DBInfo->security->writable($options)) {
        $options['title'] = _("Page is not writable");
        return do_invalid($formatter,$options);
    }

    $options['name'] = trim($options['name']);
    $new = $options['name'];
    if (!empty($DBInfo->use_namespace) and $new[0] == '~' and ($p = strpos($new, '/')) !== false) {
        // Namespace renaming ~foo/bar -> foo~bar
        $dummy = substr($new, 1, $p - 1);
        $dummy2 = substr($new, $p + 1);
        $options['name'] = $dummy.'~'.$dummy2;
    } 
    if (isset($options['name'][0]) and $options['name']) {
        if ($DBInfo->hasPage($options['name'])) {
            $formatter->send_header('', $options);
            $new_encodedname = _rawurlencode($options['name']);
            $fixed = 0;
            $msg = '';
            $title = sprintf(_("backlinks of \"%s\" page are fixed !"), $options['page']);
            $comment = sprintf(_("Fixed \"%s\" to \"%s\""), $options['page'], $options['name']);
            if ($options['pagenames'] and is_array($options['pagenames'])) {
                $regex = preg_quote($options['page']);
                //$options['minor'] = 1; # disable log
                foreach ($options['pagenames'] as $page) {
                    $p = new WikiPage($page);
                    if (!$p->exists()) continue;
                    $f = new Formatter($p);
                    $body = $p->_get_raw_body();
                    $nbody = preg_replace("/$regex/m", $options['name'], $body); // FIXME
                    if ($nbody !== false && $body != $nbody) {
                        $f->page->write($nbody);
                        if (!$options['show_only'])
                            $DBInfo->savePage($f->page, $comment, $options);
                        $msg.= sprintf(_("'%s' is changed"),
                                $f->link_tag(_rawurlencode($page),
                                "?action=highlight&amp;value=".$new_encodedname, _html_escape($page)))."<br />";
                        $fixed++;
                    }
                }
            }

            if ($fixed == 0)
                $title = _("No pages are fixed!");
            $formatter->send_title($title, '', $options);

            if ($fixed > 0) {
                print $msg;
                print sprintf(_("'%s' links are successfully fixed as '%s'."),
                    _html_escape($options['page']),
                    $formatter->link_tag($new_encodedname,
                        "?action=highlight&amp;value=".$new_encodedname, _html_escape($options['name'])));
            }

            $formatter->send_footer('', $options);
            return;
        } else {
            $title = sprintf(_("Fail to fix backlinks of \"%s\" !"), $options['page']);
            $options['msg'] = sprintf(_("New pagename \"%s\" is not exists!"), $options['name']);
            $formatter->send_header('', $options);
            $formatter->send_title($title, '', $options);
            $formatter->send_footer('', $options);
            return;
        }
    }
    $title = sprintf(_("Fix backlinks of \"%s\" ?"), $options['page']);
    $formatter->send_header('', $options);
    $formatter->send_title($title, '', $options);

    $obtn = _("Old name:");
    $nbtn = _("New name:");
    $pgname = _html_escape($options['page']);
    print "<form method='post'>
        <table border='0'>
        <tr><td align='right'>$obtn </td><td><b>$pgname</b></td></tr>
        <tr><td align='right'>$nbtn </td><td><input name='name' /></td></tr>\n";

    if (!empty($options['value']) and $options['value'] == 'check_backlinks') {
        $button = _("Fix backlinks");
        print "<tr><td colspan='2'>\n";
        print check_backlinks($formatter, $options);   
        print "</td></tr>\n";
    } else {
        $button = _("Check backlinks");
    }

    if ($DBInfo->security->is_protected("fixbacklinks", $options))
        print "<tr><td align='right'>"._("Password").": </td><td><input type='password' name='passwd' /> ".
            _("Only WikiMaster can fix backlinks of this page")."</td></tr>\n";
    if (!empty($options['value']) and $options['value'] == 'check_backlinks')
        print "<tr><td colspan='2'><input type='checkbox' name='show_only' checked='checked' />"._("show only")."</td></tr>\n";
    print "<tr><td></td><td><input type='submit' name='button_fixbacklinks' value='$button' />";
    print "<input type='hidden' name='value' value='check_backlinks' />";
    print "</td></tr>\n";
    print "
        </table>
        <input type='hidden' name='action' value='fixbacklinks' />
        </form>";
    $formatter->send_footer('', $options);
}

function check_backlinks($formatter, $options) {
    $options['checkbox'] = 1;
    $options['backlinks'] = 1;

    return $formatter->macro_repl('FullSearch', $options['page'], $options);
}

// vim:et:sts=4:sw=4:
