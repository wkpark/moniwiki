<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a SystemInfo macro plugin for the MoniWiki
//
// $Id$

function macro_SystemInfo($formatter='',$value='') {
  global $_revision,$_release;

  $version=phpversion();
  $uname=php_uname();
  list($aversion,$dummy)=explode(" ",$_SERVER['SERVER_SOFTWARE'],2);

  $pages=macro_PageCount($formatter);

  return <<<EOF
<table border='0' cellpadding='5'>
<tr><th width='200'>PHP Version</th> <td>$version ($uname)</td></tr>
<tr><th>MoniWiki Version</th> <td>Release $_release [$_revision]</td></tr>
<tr><th>Apache Version</th> <td>$aversion</td></tr>
<tr><th>Number of Pages</th> <td>$pages</td></tr>
</table>
EOF;
}
?>
