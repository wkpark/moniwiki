<?php
// Copyright 2008 Your name <foobar at foo.bar>
// All rights reserved. Distributable under GPL see COPYING
// a sample function plugin for the MoniWiki
//
// Author: Your name <foobar@foo.bar>
// Date: 2008-01-01
// Name: Hello world
// Description: Hello world function plugin
// URL: to_plugin url/interwiki name etc.
// Version: $Revision$
// License: GPL
//
// Usage: can be used in template files {=returnHello("foo")}
//
// $Id$

function function_returnHello($formatter,$user='world')
{
    return 'hello '.$user;
}

// vim:et:sts=4:sw=4:
?>
