<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a SystemInfo macro plugin for the MoniWiki
//
// $Id: SystemInfo.php,v 1.3 2005/08/26 09:58:52 wkpark Exp $

function macro_SystemInfo($formatter='',$value='') {
  global $_revision,$_release;

  $version = preg_replace('/(\.\d+)$/', '.x', phpversion());
  $tmp = explode(' ', php_uname());
  $uname = ' ('.$tmp[0].' '.$tmp[2].' '.$tmp[4].')';
  list($aversion,$dummy)=explode(" ",$_SERVER['SERVER_SOFTWARE'],2);

  $pages=macro_PageCount($formatter);
  $npage=_("Number of Pages");
  $ver_serv=_("HTTP Server Version");
  $ver_moni=_("MoniWiki Version");
  $ver_php=_("PHP Version");

  return <<<EOF
<table border='0' cellpadding='5'>
<tr><th width='200'>$ver_php</th> <td>$version$uname</td></tr>
<tr><th>$ver_moni</th> <td>Release $_release [$_revision]</td></tr>
<tr><th>$ver_serv</th> <td>$aversion</td></tr>
<tr><th>$npage</th> <td>$pages</td></tr>
</table>
EOF;
}
?>
