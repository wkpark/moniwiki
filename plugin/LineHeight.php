<?php
// Copyright 2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a sample plugin for the MoniWiki
//
// Usage: [[LineHeight]]
//   or set $extra_macros=array('LineHeight'); in the config.php
//
// $Id: LineHeight.php,v 1.1 2006/01/10 11:54:55 wkpark Exp $

function macro_LineHeight($formatter,$value) {
    global $DBInfo;

    if ($value != 'nocss')
        print <<<CSS
<style type="text/css">
<!--
#lineheightForm {
  display: block;
  text-align:right;
  position: fixed;
  filter:alpha(opacity=25);-moz-opacity:.25;opacity:.25;
  top: 0px;
  left: 1px;
  right: 1px;
  height: 1em;
  z-index: 1;
  overflow: hidden;
  background-color: #e0e0e0;

  padding: 2px;
  padding-right:20px;
}
//-->
</style>
CSS;
    return "<script type='text/javascript' src='".$DBInfo->url_prefix.'/local/lineheight.js'."'></script>\n";
}

// vim:et:sts=4:
?>
