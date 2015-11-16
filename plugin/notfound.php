<?php
// Copyright 2003-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a notfound plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@gmail.com>
// Since: 2003-03-13
// Name: notfound plugin
// Description: a default 404 handler plugin
// URL: MoniWiki:NotFoundPlugin
// Version: $Revision: 1.1 $
// License: GPLv2
//
// Usage: ?action=notfound
//

function do_notfound($formatter, $options = array()) {
    global $DBInfo, $Config;

    if ($formatter->page->exists()) {
        echo '<html><head></head><body><h1>'._("Page found").'</h1></body></html>';
        return;
    }

    $msg_404 = 'Status: 404 Not found';
    if (!empty($Config['no_404'])) $msg_404 = ''; // for IE
    if (!empty($options['is_robot']) or !empty($Config['nofancy_404'])) {
      if (!empty($msg_404))
        $formatter->header($msg_404);
      echo '<html><head></head><body><h1>'._("Page not found").'</h1></body></html>';
      return true;
    }

    $formatter->send_header($msg_404, $options);

    if (empty($DBInfo->metadb)) $DBInfo->initMetaDB();
    $twins = $DBInfo->metadb->getTwinPages($formatter->page->name, 2);
    if ($twins) {
        $formatter->send_title('', '', $options);
        $twins="\n".implode("\n", $twins);
        $formatter->send_page(_("See TwinPages : ").$twins);
        echo "<br />".
            $formatter->link_to("?action=edit", $formatter->icon['create']._("Create this page"));
    } else {
        $oldver = '';
        if ($DBInfo->version_class) {
            $version = $DBInfo->lazyLoad('version', $DBInfo);
            $oldver = $version->rlog($formatter->page->name, '', '', '-z');
        }
        $button = $formatter->link_to("?action=edit", $formatter->icon['create']._("Create this page"));
        if ($oldver) {
            $formatter->send_title(sprintf(_("%s has saved revisions"), $formatter->page->name), '', $options);
            $searchval=_html_escape($options['page']);
            echo '<h2>'.sprintf(_("%s or click %s to fulltext search.\n"),
                $button, $formatter->link_to("?action=fullsearch&amp;value=$searchval", _("here"))).'</h2>';
            $options['info_actions'] = array('recall'=>'view', 'revert'=>'revert');
            $options['title'] = '<h3>'.sprintf(_("Old Revisions of the %s"),
                _html_escape($formatter->page->name)).'</h3>';
            // if (empty($formatter->wordrule)) $formatter->set_wordrule();
            echo $formatter->macro_repl('Info', '', $options);
        } else {
            $formatter->send_title(sprintf(_("%s is not found in this Wiki"), $formatter->page->name), '', $options);
            $searchval = _html_escape($options['page']);
            if (!empty($DBInfo->default_fullsearch)) {
                $fullsearch = $DBInfo->default_fullsearch;
                if (strpos($fullsearch, '%s') !== false)
                    $fullsearch = sprintf($fullsearch, $searchval);
                else
                    $fullsearch.= $searchval;
                $fullsearch = '<a href="'.$fullsearch.'">'._("here").'</a>';
            } else {
                $fullsearch = $formatter->link_to("?action=fullsearch&amp;value=".$searchval, _("here"));
            }
            echo '<h2>'.sprintf(_("%s or click %s to fulltext search.\n"), $button, $fullsearch).'</h2>';
            $err = array();
            echo $formatter->macro_repl('LikePages', $formatter->page->name, $err);
            if (!empty($err['extra']))
                echo $err['extra'];

            echo '<h2>'._("Please try to search with another word").'</h2>';
            $ret = array('call'=>1);
            $ret = $formatter->macro_repl('TitleSearch', '', $ret);

            //if ($ret['hits'] == 0)
            echo "<div class='searchResult'>".$ret['form']."</div>";
        }

        echo "<hr />\n";
        $options['linkto'] = "?action=edit&amp;template=";
        $options['limit'] = -1;
        $tmpls = $formatter->macro_repl('TitleSearch', $DBInfo->template_regex, $options);
        if ($tmpls) {
            echo sprintf(_("%s or alternativly, use one of these templates:\n"), $button);
            echo $tmpls;
        } else {
            echo "<h3>"._("You have no templates")."</h3>";
        }
        echo sprintf(_("To create your own templates, add a page with '%s' pattern.\n"), $DBInfo->template_regex);
    }

    $args = array('editable'=>1);
    $formatter->send_footer($args, $options);

    return;
}

// vim:et:sts=4:sw=4:
