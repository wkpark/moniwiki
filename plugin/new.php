<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a new action plugin for the MoniWiki
// vim:et:ts=2:
//
// $Id: new.php,v 1.3 2007/10/09 05:17:23 wkpark Exp $

function do_new($formatter,$options) {
  global $DBInfo;

  if (!$options['value']) {
    $title=_("Create a new page");
    $formatter->send_header("",$options);
    $formatter->send_title($title,"",$options);
    $url=$formatter->link_url($formatter->page->urlname);

    if ($DBInfo->hasPage('MyNewPage')) {
        $p = $DBInfo->getPage('MyNewPage');
        $f = new Formatter($p,$options);
        $f->use_rating=0;

        $f->send_page('',$options);
    }

    $msg=_("Page Name");
    $fixname=_("Normalize this page name");
    $btn=_("Create a new page");
    print <<<FORM
<div class='addPage'>
<form method='get' action='$url'>
<table style='border:0'><tr><th class='addLabel'><labe>$msg: </label></th><td><input type='hidden' name='action' value='new' />
    <input name='value' size='30' /></td></tr>
<tr><th class='addLabel'><input type='checkbox' name='fixname' checked='checked' /></th><td>$fixname</td></tr>
<td></td><td><input type='submit' value='$btn' /></td>
</tr></table>
    </form>
</div>
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
