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

    $new = $options['name'];
    if ($new[0] == '~' and ($p=strpos($new,'/')) !== false) {
        // Namespace renaming
        $dummy = substr($new, 1, $p - 1);
        $dummy2 = substr($new, $p + 1);
        $options['name'] = $dummy.'~'.$dummy2;
    } 
    if (isset($options['name']) and trim($options['name'])) {
        if ($DBInfo->hasPage($options['page']) && !$DBInfo->hasPage($options['name'])) {
            $title = sprintf(_("backlinks of \"%s\" page are fixed !"), $options['page']);
            $formatter->send_header('', $options);
            $formatter->send_title($title, '', $options);
            $new_encodedname = _rawurlencode($options['name']);
            if ($options['pagenames'] and is_array($options['pagenames'])) {
                $regex = preg_quote($options['page']);
                $options['minor']=1;
                foreach ($options['pagenames'] as $page) {
                    $p = new WikiPage($page);
                    if (!$p->exists()) continue;
                    $f = new Formatter($p);
                    $body = $p->_get_raw_body();
                    $body = preg_replace("/$regex/m", $options['name'], $body);
                    $f->page->write($body);
                    if (!$options['show_only'])
                        $DBInfo->savePage($f->page, '', $options);
                    $msg.=sprintf(_("'%s' is changed"),
                            $f->link_tag(_rawurlencode($page),
                                "?action=highlight&amp;value=".$new_encodedname))."<br />";
                }
            }
            print $msg;
            print sprintf(_("'%s' links are successfully fixed as '%s'."),
                    $options['page'],
                    $formatter->link_tag($options['name'],
                        "?action=highlight&amp;value=".$new_encodedname));

            $formatter->send_footer('', $options);
            return;
        } else {
            $title = sprintf(_("Fail to fix backlinks of \"%s\" !"), $options['page']);
            $formatter->send_header('', $options);
            $formatter->send_title($title, '', $options);
            $formatter->send_footer('', $options);
            return;
        }
    }
    $title = sprintf(_("fix backlinks of \"%s\" ?"), $options['page']);
    $formatter->send_header('', $options);
    $formatter->send_title($title, '', $options);

    $obtn = _("Old name:");
    $nbtn = _("New name:");
    print "<form method='post'>
        <table border='0'>
        <tr><td align='right'>$obtn </td><td><b>$options[page]</b></td></tr>
        <tr><td align='right'>$nbtn </td><td><input name='name' /></td></tr>\n";
    $button = _("Fix backlinks");
    if ($options['value'] == 'check_backlinks') {
        print "<tr><td colspan='2'>\n";
        print check_backlinks($formatter, $options);   
        print "</td></tr>\n";
    }
    if ($DBInfo->security->is_protected("fixbacklinks", $options))
        print "<tr><td align='right'>"._("Password").": </td><td><input type='password' name='passwd' /> ".
            _("Only WikiMaster can fix backlinks of this page")."</td></tr>\n";
    print "<tr><td colspan='2'><input type='checkbox' name='show_only' checked='checked' />"._("show only")."</td></tr>\n";
    print "<tr><td></td><td><input type='submit' name='button_fixbacklinks' value='$button' />";
    print " <a href='?action=fixbacklinks&value=check_backlinks'>"._("Check backlinks").
        "</a>";
    print "</td></tr>\n";
    print "
        </table>
        <input type='hidden' name='action' value='fixbacklinks' />
        </form>";
    $formatter->send_footer('', $options);
}

function check_backlinks($formatter, $options) {
    $options['checkbox'] = 1;

    return $formatter->macro_repl('FullSearch', $options['page'], $options);
}

// vim:et:sts=4:sw=4:
