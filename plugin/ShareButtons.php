<?php
// Copyright 2013 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a share buttons macro for the MoniWiki
//
// Author: Won-Kyu Park <wkpark @ kldp.org>
// Date: 2013-09-28
// Name: ShareButtons macro
// Description: a Share Button macro plugin
// URL: MoniWiki:ShareButtonMacro
// Version: $Revision: 1.0 $
// License: GPLv2
//
// Usage: [[ShareButtons]]
//

function macro_ShareButtons($formatter, $value = '', $params) {
    global $DBInfo;

    $lang = $DBInfo->lang;
    $btn = _("Tweet");
    $link = $formatter->link_url($formatter->page->name);
    $href = qualifiedURL($link);
    $encoded_href = $href;

    $twitter_attr = '';
    $facebook_attr = 'data-layout="button_count"';
    $gplus_attr =' data-size="medium"';
    if ($value == 'vertical' or $value == 'vert') {
        $twitter_attr = ' data-count="vertical"';
        $gplus_attr =' data-size="tall"';
        $facebook_attr = 'data-layout="box_count"';
    }

    $twitter = <<<EOF
<a href="https://twitter.com/share" class="twitter-share-button" data-url="$href" data-lang="$lang" data-dnt="true"$twitter_attr>$btn</a>
EOF;
    $js = <<<EOF
<script type="text/javascript">!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0],p=/^http:/.test(d.location)?'http':'https';if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src=p+'://platform.twitter.com/widgets.js';fjs.parentNode.insertBefore(js,fjs);}}(document, 'script', 'twitter-wjs');</script>
EOF;
    $formatter->register_javascripts($js);
    $gplus = <<<EOF
<div class="g-plusone" data-href="$href"$gplus_attr></div>
EOF;

    $js= <<<EOF
<script type="text/javascript">
  (function() {
    var po = document.createElement('script'); po.type = 'text/javascript'; po.async = true;
    po.src = 'https://apis.google.com/js/plusone.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(po, s);
  })();
</script>
EOF;
    $formatter->register_javascripts($js);

    $js = <<<EOF
<script type="text/javascript">(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/ko_KR/all.js#xfbml=1";
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));</script>

EOF;
    $formatter->register_javascripts($js);

    $fb = <<<EOF
<div class="fb-like"
data-href="$href"
data-width="450"
data-action="recommend"
data-show-faces="false"
$facebook_attr
data-send="false"></div>
EOF;

    return '<div class="share-buttons">'.$fb.' '.$twitter.' '.$gplus.'</div>';
}

// vim:et:sts=4:sw=4:
