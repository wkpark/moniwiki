<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a new action plugin for the MoniWiki
// vim:et:ts=2:
//
// $Id$

function do_new($formatter,$options) {
  if (!$options['value']) {
    $title=_("Create a new page");
    $formatter->send_header("",$options);
    $formatter->send_title($title,"",$options);
    $url=$formatter->link_url($formatter->page->urlname);

    $msg=_("Enter a page name");
    $fixname=_("Normalize this page name");
    $btn=_("Create a new page");
    print <<<FORM
<form method='get' action='$url'>
    $msg: <input type='hidden' name='action' value='new' />
    <input name='value' size='30' />
    <input type='checkbox' name='fixname' checked='checked' />$fixname<br />
    <input type='submit' value='$btn' />
    </form>
FORM;
    $formatter->send_footer();
  } else {
    $pgname=$options['value'];
    if ($options['fixname']) $pgname=normalize($pgname);
    $options['page']=$pgname;
    $page=new WikiPage($pgname);
    $f=new Formatter($page,$options);
    do_edit($f,$options);
    return true;
  }
}

?>
