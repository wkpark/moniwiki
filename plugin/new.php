<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a new action plugin for the MoniWiki
// vim:et:ts=2:
//
// $Id$

function do_new($formatter,$options) {
  $title=_("Create a new page");
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  $url=$formatter->link_url($formatter->page->urlname);

  $msg=_("Enter a page name");
  print <<<FORM
<form method='get' action='$url'>
    $msg: <input type='hidden' name='action' value='goto' />
    <input name='value' size='30' />
    <input type='submit' value='Create' />
    </form>
FORM;

  $formatter->send_footer();
}

?>
