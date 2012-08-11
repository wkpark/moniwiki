<?php
// Copyright 2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a sample plugin for the MoniWiki
//
// Usage: [[ABBR(HTTP Hyper Text Transper Protocol)]]
//
// $Id: ABBR.php,v 1.1 2006/01/26 15:55:03 wkpark Exp $

function macro_ABBR($formatter,$value) {
    $sym=strtok($value,' '); $val=strtok('');
    return '<abbr title="'.$val.'">'.$sym.'</abbr>';
}
// vim:et:sts=4:
?>
