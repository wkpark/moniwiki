<?php
// Copyright 2003-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a simple namumarkup filter plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2015-11-19
// Name: a simple namu markup filter plugin
// Description: a simple filter to convert namu markup to moniwiki markup
// URL: MoniWiki:NamuMarkupFilter
// Version: $Revision: 1.0 $
// License: GPLv2

function filter_namumarkup($formatter, $value, $params = array()) {
    $value = preg_replace('@\[(목차|각주)\]@i', '[[$1]]', $value);
    $value = preg_replace('@\[(tableofcontents|br|youtube|include|anchor|date|datetime)(\(.*\))?\]@i', '[[$1$2]]', $value);
    return $value;
}

// vim:et:sts=4:sw=4:
