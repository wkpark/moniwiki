<?php
// Copyright 2003-2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a sample plugin for the MoniWiki
//
// Author: Your name <foobar@foo.bar>
// Date: 2006-01-01
// Name: a simple HTML macro plugin
// Description: a simple macro plugin
// URL: to_plugin url/interwiki name etc.
// Version: $Revision: 1.1 $
// License: GPL
//
// Usage: [[HTML(<font size="+6">Hello World!</font>)]]
//
// $Id: HTML.php,v 1.1 2009/04/18 06:28:29 wkpark Exp $

function macro_HTML($formatter,$value) {
  return str_replace("&lt;","<",$value);
}

// vim:et:sts=4:sw=4:
?>
