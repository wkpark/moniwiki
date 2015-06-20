<?php
// Copyright 2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a GoogleAds plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark at kldp.org>
// Since: 2015-06-20
// Name: GoogleAds Macro
// Description: GoogleAds Macro Plugin
// URL: MoniWiki:GoogleAdsPlugin
// Version: $Revision: 1.0 $
// License: GPLv2
//
// Usage: [[GoogleAds(client, slot)]]
//

function macro_GoogleAds($formatter, $value) {
    global $DBInfo;

    if (empty($DBInfo->google_ads))
        return '';

    $random = false;
    if (!empty($DBInfo->google_ads_random))
        $random = true;
    $args = array();
    $value = trim($value);
    if (!empty($value)) {
        $args = explode(',', str_replace(' ', '', $value));

        for ($i = 0; $i < count($args); $i++) {
            if ($args[$i] == 'random') {
                $random = true;
                unset($args[$i]);
            } else if ($args[$i] == 'norandom') {
                $random = false;
            }
        }
        $args = array_values($args);
    }

    if (sizeof($args) > 2) {
        $client = $args[0];
        $slot = $args[1];

        foreach ($google_ads as $k=>$ad) {
            if ($ad['ad_client'] == $client) {
                $found_slot = $ad['ad_slot'];
                $width = isset($ad['width']) ? $ad['width'] : 0;
                $height = isset($ad['height']) ? $ad['height'] : 0;
                if ($ad['ad_slot'] == $slot)
                    break;
            }
        }

        // no ads found
        if (empty($found_slot))
            return '';

        // given slot not found
        if ($found_slot != $slot)
            $slot = $found_slot;
    } else {
        if ($random)
            $id = mt_rand(0, count($DBInfo->google_ads));
        else
            $id = 0;

        $ad = $DBInfo->google_ads[$id];

        $client = $ad['ad_client'];
        $slot = $ad['ad_slot'];
        $width = isset($ad['width']) ? $ad['width'] : 0;
        $height = isset($ad['height']) ? $ad['height'] : 0;
    }

    $format = 'none';
    if ($width == 0 || $height == 0)
        $format = 'auto';

    if ($format == 'auto')
        return <<<EOF
<div class='googleads'>
<script async src="//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
<!-- Auto -->
<ins class="adsbygoogle"
     style="display:block"
     data-ad-client="$client"
     data-ad-slot="$slot"
     data-ad-format="auto"></ins>
<script>
(adsbygoogle = window.adsbygoogle || []).push({});
</script>
</div>
EOF;

    return <<<EOF
<div class='googleads'>
<!-- Mobile Banner -->
<script type="text/javascript">
google_ad_client = "$client";
google_ad_slot = "$slot";
google_ad_width = $width;
google_ad_height = $height;
</script>
<script type="text/javascript" src="//pagead2.googlesyndication.com/pagead/show_ads.js"></script>
</div>
EOF;
}

// vim:et:sts=4:sw=4:
