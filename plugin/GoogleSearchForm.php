<?php
// Copyright 2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a simple Google Search plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@gmail.com>
// Date: 2015-11-22
// Name: Google Search plugin
// Description: Google Search Plugin
// URL: MoniWiki:GoogleSearchPlugin
// Version: $Revision: 1.0 $
// License: GPLv2
// SeeAlso: https://cse.google.com/cse/manage/create
//
// Usage: [[GoogleSearchForm]]
//

function macro_GoogleSearchForm($formatter, $value = '', $params = array()) {
    global $Config;

    if (empty($Config['google_search_id']))
        return '';

    // $google_search_id = 'partner-pub-0000000000000000:0000000000';
    $pub = $Config['google_search_id'];

    if (isset($value[0]))
        $params['value'] = $value;
    $form = <<<FORM
<div>
<form action="http://www.google.co.kr" id="cse-search-box">
  <div>
    <input type="hidden" name="cx" value="$pub" />
    <input type="hidden" name="ie" value="UTF-8" />
    <input type="text" name="q" size="55" /> <input type="submit" name="sa" value="본문&#xac80;&#xc0c9;" />
  </div>
</form>
<script type="text/javascript" src="http://www.google.co.kr/coop/cse/brand?form=cse-search-box&amp;lang=ko"></script>
</div>
FORM;
    return $form;
}

// vim:et:sts=4:sw=4:
