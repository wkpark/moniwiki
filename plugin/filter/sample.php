<?php
// Copyright 2005 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a sample filter plugin for the MoniWiki
//
// $Id: sample.php,v 1.1 2005/04/12 13:31:07 wkpark Exp $

function filter_sample($formatter,$value,$options) {
  return preg_replace($value);
}
// vim:et:sts=4:
?>
