<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a moztab macro plugin for the MoniWiki
//
// Usage: [[moztab]]
//
// $Id: moztab.php,v 1.4 2006/01/05 17:33:43 wkpark Exp $
// vim:et:sts=2:

function macro_MozTab($formatter,$value) {
  global $DBInfo;
  $url=qualifiedUrl($formatter->link_url("","?action=recentchanges"));

  $tab=<<<TAB
<script language="JavaScript">
/*<![CDATA[*/
 function selfside() {
    if ((typeof window.sidebar == "object") && (typeof window.sidebar.addPanel == "function")) {
          window.sidebar.addPanel ("$DBInfo->sitename", "$url","");
       }
       else {
          var rv = window.confirm ("This Funkcion should work with Netscape 6.x or Mozilla" + "www.mozilla.org");
          if (rv)
             document.location.href = "http://www.mozilla.org/";
       }
    }
/*]]>*/
</script>
<a href="javascript:selfside();"><img src="$DBInfo->imgs_dir/plugin/moztab.png" border=0 title="add mozilla tab"></a>
TAB;
  return $tab;
}

?>
