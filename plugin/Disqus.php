<?php
// Copyright 2014 Won-Kyu Park <wkpark at gmail.com>
// All rights reserved. Distributable under GPL see COPYING
// a DISQUS plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@gmail.com>
// Date: 2014-01-04
// Name: DISQUS plugin
// Description: DISQUS macro plugin
// URL: MoniWiki:DisqusPlugin
// Version: $Revision: 0.1 $
// License: GPLv2
//
// Param: disqus_devel=0; //
// Param: disqus_shortname='foobar'; // your forum shortname
//
// Usage: [[Disqus]]
//

function macro_Disqus($formatter, $value = '') {
    global $Config;

    if (!empty($formatter->pi['#nocomment'])) return '';

    if (empty($Config['disqus_shortname'])) {
        echo "<a href='https://disqus.com/admin/signup/'>"._("You need to register your forum at DISQUS.").'</a>';
        return;
    }

    $js = <<<EOF
<script charset="utf-8" type="text/javascript">
/*<![CDATA[*/

EOF;

    // set disqus_identifier
    $id = $Config['disqus_shortname'].':';
    $shortname = $Config['disqus_shortname'];
    if (isset($Config['disqus_id'][0]))
        $id.= $Config['disqus_id'].':';
    $id.= addslashes($formatter->page->name);

    if (isset($Config['disqus_devel']))
        $js.= 'var disqus_developer = '.$Config['disqus_devel'].";\n";
    $js.= "var disqus_url     = '".qualifiedUrl($formatter->link_url($formatter->page->urlname))."';\n";
    $js.= "var disqus_title   = '".addslashes($formatter->page->name)."';\n";
    $js.= "var disqus_identifier = '".$id."';\n";
    $js.= 'var disqus_container_id = \'disqus_thread\';'."\n";
    $js.= "var disqus_shortname = '".$Config['disqus_shortname']."';\n";
    $js.=<<<EOF
(function() {
function init() {
    var dsq = document.createElement('script'); dsq.type = 'text/javascript'; dsq.async = true;
    dsq.src = '//' + disqus_shortname + '.disqus.com/embed.js';
    (document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(dsq);
}
if (window.addEventListener) window.addEventListener("load", init, false);
else if (window.attachEvent) window.attachEvent("onload", init);
})();
/*]]>*/
</script>
EOF;

    $formatter->register_javascripts($js);

    $out = '';
    if (empty($value) or $value != 'notitle')
        $out = '<h3><span class="i18n" title="Comments">'._("Comments").'</span>';
    $out.='</h3><div id="disqus_thread"></div>';
    $out.= '<noscript><a href="http://'.$shortname.'.disqus.com/?url=ref">'.
        _("View the discussion thread.").'</a></noscript>';

    return $out;
}

// vim:et:sts=4:sw=4:
