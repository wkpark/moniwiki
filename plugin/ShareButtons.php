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
    $btn = _("tweet");
    $link = $formatter->link_url($formatter->page->urlname);
    $href = qualifiedURL($link);
    $ehref = urlencode($href); // fix for twitter

    if (!$formatter->page->exists()) {
        return '';
    }

    if ($value == 'nojs') {
        $fb = '<li><a class="facebook" href="https://www.facebook.com/sharer/sharer.php?u='.$href.'" target="_blank"><span>'.
            _("fb").'</span></a></li>';
        $gplus = '<li><a class="gplus" href="https://plus.google.com/share?url='.$href.'" target="_blank"><span>'.
            _("g+").'</span></a></li>';
        $twitter = '<li><a class="twitter" href="https://twitter.com/share?url='.$ehref.'" target="_blank"><span>'.$btn.'</span></a></li>';

        $oc = new Cache_text('opengraph');
        $pin = '';
        if (($val = $oc->fetch($formatter->page->name)) !== false) {
            if (!empty($val['image'])) {
                $image = $val['image'];
                $image_href = urlencode(str_replace('&amp;', '&', $image)); // fix
                $pin = '<li><a class="pinterest" href="https://pinterest.com/pin/create/button/?url='.$ehref.'&amp;description='._urlencode($val['description']).'&amp;media='.$image_href.'" target="_blank"><span>'._("pin").'</span></a></li>';
            }
        }
        return '<div class="share-buttons"><ul>'.$pin.' '.$fb.' '.$twitter.' '.$gplus.'</ul></div>';
    }

    $twitter_attr = '';
    $facebook_attr = 'data-layout="button_count"';
    $gplus_attr =' data-size="medium"';
    if ($value == 'vertical' or $value == 'vert') {
        $twitter_attr = ' data-count="vertical"';
        $gplus_attr =' data-size="tall"';
        $facebook_attr = 'data-layout="box_count"';
    } else if ($value == 'icon') {
        $twitter_attr = ' data-count="none"';
        $gplus_attr =' data-annotation="none" data-size="tall"';
        $facebook_attr = 'data-layout="button"';
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
