<?php
// Copyright 2003-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a wikimedia commons plugin for the MoniWiki
//
// Since: 2015-10-06
// Name: WikimediaCommons
// Author: wkpark at gmail.com
// Description: WikiMedia commons plugin to embed a wikimedia image.
// Credit: some part of script inspired from the Rigveda version of a WikiCommons macro but rewritten.
//
// Params: width, height, algin etc.
//
// Usage: [[WikimediaCommons(URL or filename,params...)]]
//

function do_wikimediacommons($formatter, $params = array()) {
    global $DBInfo;

    $retval = array();
    $params['call'] = 1;
    $params['retval'] = &$retval;
    $ret = macro_WikimediaCommons($formatter, $params['url'], $params);

    if (empty($params['image']) && preg_match('@text/@', $_SERVER['HTTP_ACCEPT'])) {
        echo $ret;
    } elseif (!empty($params['image']) || preg_match('@image/@', $_SERVER['HTTP_ACCEPT'])) {
        header('Pragma: no-cache');
        header('Cache-Control: public, max-age=0, s-maxage=0');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate', false);
        $formatter->send_header(array('Status: 302', 'Location: '.$retval['url']));
    } else {
        // json
        echo json_encode($retval);
    }
}

function macro_WikimediaCommons($formatter, $value, $params = array()) {
    global $DBInfo, $Config;

    $orig_value = $value;
    $lazyload = !empty($Config['external_image_lazyload']) ? $Config['external_image_lazyload'] : false;

    $args = array();
    if (($p = strpos($value, ',')) !== false) {
        $arg = substr($value, $p + 1);
        $value = substr($value, 0, $p);
    } else if (($p = strpos($value, '?')) !== false) {
        $arg = substr($value, $p + 1);
        $value = substr($value, 0, $p);
        $arg = preg_replace('@&(amp;)?@', ',', $arg);
    }
    if (isset($arg[0])) {
        $arg = preg_replace('@\s*,\s*@', ',', $arg);
        $arg = preg_replace('@\s*=\s*@', '=', $arg);
        $args = explode(',', $arg);
    }
    if (!empty($params['attr'])) {
        $args = array_merge($args, (array) $params['attr']);
    }

    $data = array(
        'action' => 'query',
        'prop' => 'imageinfo',
        'iiprop' => 'extmetadata|url',
        'format' => 'json',
        'rawcontinue' => '1',
    );

    // default API url
    $api_url = 'https://commons.wikimedia.org/w/api.php';
    // check full url
    if (preg_match('@^https?://upload\.wikimedia\.org/wikipedia/(?:(en|commons)/)?(thumb/)?./../([^/]+\.(?:gif|jpe?g|png|svg))(?(2)/(\d+px)-\3)@', $value, $m)) {
        // WikiMedia
        $remain = substr($value, strlen($m[0]));
        if (!empty($m[1]) && in_array($m[1], array('en')))
            $api_url = 'https://'.$m[1].'.wikipedia.org/w/api.php';

        $value = urldecode($m[3]);
        if (!empty($m[4]))
            $width = intval($m[4]);
        $data['titles'] = 'Image:'.$value;
        $data['iiprop'] = 'extmetadata|url';
    } else if (preg_match('@^https?://((?:[^.]+)\.(?:wikimedia|wikipedia)\.org)/wiki/(?:Image|File):([^/]+\.(?:gif|jpe?g|png|svg))$@', $value, $m)) {
        // WikiMedia or WikiPedia
        $api_url = 'https://'.$m[1].'/w/api.php';

        $value = urldecode($m[2]);
        $data['titles'] = 'Image:'.$value;
        $data['iiprop'] = 'extmetadata|url';
        $source = _("WikiMedia Commons");
    } else if (preg_match('@^https?://([^.]+)\.wikia\.com/wiki/(?:Image|File):(.*\.(?:gif|jpe?g|png|svg))@', $value, $m)) {
        $src = 'wikia.';
        // Wikia
        $api_url = 'https://'.$m[1].'.wikia.com/api.php';
        $value = urldecode($m[2]);
        $data['titles'] = 'Image:'.$value;
        $data['iiprop'] = 'url|user|size|comment';
        $source = _("Wikia");
    } else if (preg_match('@^https?://.*\.wikia\..*/([^/]+)/images/./../([^/]+\.(?:gif|jpe?g|png|svg))@', $value, $m)) {
        $src = 'wikia.';
        // Wikia
        $api_url = 'https://'.$m[1].'.wikia.com/api.php';
        $value = urldecode($m[2]);
        $data['titles'] = 'Image:'.$value;
        $data['iiprop'] = 'url|user|size|comment';
        $source = _("Wikia");
    } else {
        $value = urldecode($value);
        $data['titles'] = 'Image:'.$value;
        $data['iiprop'] = 'extmetadata|url';

        $source = _("WikiMedia Commons");
    }

    $styles = array();
    foreach ($args as $arg) {
        $k = $v = '';
        if (($p = strpos($arg, '=')) !== false) {
            $k = substr($arg, 0, $p);
            $k = trim($k);
            $v = substr($arg, $p + 1);
            $v = trim($v, '"\'');
        } else {
            continue;
        }
        $k = strtolower($k);
        switch($k) {
            case 'width':
            case 'height':
                if (preg_match('@^(\d+)(px|%)?$@', $v, $m)) {
                    if (isset($m[2]) && $m[2] == '%') {
                        $styles[$k] = $v;
                    } else {
                        $styles[$k] = $v;
                        ${$k} = intval($m[1]);
                    }
                }
                break;
            case 'align':
                $v = strtolower($v);
                if (in_array($v, array('left', 'right', 'center')))
                    $addClass = ' img'.ucfirst($v);
                break;
        }
    }
    if (empty($addClass) && !empty($params['class']))
        $addClass = $params['class'];

    $common = new Cache_Text('wikicommons');
    $key = $value.$src;
    if (isset($width)) $key.= $width;
    if (isset($height)) $key.= '.h'.$height;
    while (!empty($formatter->refresh) || ($images = $common->fetch($key)) === false) {
        // dynamic macro
        if ($formatter->_macrocache and empty($params['call']))
            return $formatter->macro_cache_repl('WikimediaCommons', $orig_value);
        if (empty($params['call']))
            $formatter->_dynamic_macros['@WikimediaCommons'] = 1;

        if (empty($params['call']) && $lazyload) {
            break;
        }

        if (!empty($width)) {
            $data['iiurlwidth'] = min(1280, $width);
        } else if (!empty($height)) {
            $data['iiurlheight'] = min(1280, $height);
        } else {
            // default image width
            $data['iiurlwidth'] = 640;
        }
        require_once(dirname(__FILE__).'/../lib/HTTPClient.php');

        $http = new HTTPClient();
        $save = ini_get('max_execution_time');
        set_time_limit(0);

        // support proxy
        if (!empty($DBInfo->proxy_host)) {
            $http->proxy_host = $DBInfo->proxy_host;
            if (!empty($DBInfo->proxy_port))
                $http->proxy_port = $DBInfo->proxy_port;
        }

        $http->sendRequest($api_url, $data, 'POST');
        set_time_limit($save);

        // FIXME
        if ($http->status != 200) {
            return '';
        }
        $res = json_decode($http->resp_body);
        $images = $res->query->pages;

        // fail to get image information.
        if (empty($images)) {
            // is it right URL?
            if (isset($params['retval']))
                $params['retval']['url'] = $orig_value;
            return '<a href="'.$orig_value.'">'.$orig_value.'</a>';
        }
        $common->update($key, $images);
        break;
    }

    if (!empty($images)) {
        $image = current($images);
        $image_url = $image->imageinfo[0]->thumburl;
        $desc_url = $image->imageinfo[0]->descriptionurl;

        if (empty($styles['width']) && !empty($image->imageinfo[0]->thumbwidth)) {
            $styles['width'] = $image->imageinfo[0]->thumbwidth.'px';
        }
    }

    $attrs = array();
    $keys = array('width', 'height');
    foreach ($keys as $key) {
        if (isset($styles[$key])) {
            $attrs[] = $key.'="'.$styles[$key].'"';
            unset($styles[$key]);
        }
    }
    $attr = '';
    if (count($attrs))
        $attr = ' '.implode(' ', $attrs);

    $style = '';
    foreach ($styles as $k=>$v)
        $style.= $k.':'.$v.';';
    if (!empty($style))
        $style = ' style="'.$style.'"';

    // setup externamImage's class name
    $class = 'externalImage'.' '.$addClass;
    $class = preg_replace('@\s+@', ' ', $class);
    $cls = explode(' ', $class);
    $cls = array_flip(array_flip($cls));
    $class = implode(' ', $cls);

    if (!empty($image)) {
        if (!empty($image->imageinfo[0]->extmetadata)) {
            $copyright = $image->imageinfo[0]->extmetadata->Copyrighted->value;
            $description = $image->imageinfo[0]->extmetadata->ImageDescription->value;
            $author = $image->imageinfo[0]->extmetadata->Artist->value;
            $license = $image->imageinfo[0]->extmetadata->License->value;
            $comment = '';
        } else if (!empty($image->imageinfo[0]->user)) {
            // Wikia case
            $copyright = 'True';
            $author = sprintf(_("Uploaded by %s"), $image->imageinfo[0]->user);
            $license = '';
            $description = $image->imageinfo[0]->comment;
        } else {
            // not found
            return false;
        }

        if (!empty($formatter->fetch_images) && !empty($image_url)) {
            $image_url = $formatter->fetch_action. str_replace(array('&', '?'), array('%26', '%3f'), $image_url);
            // use thumbnails ?
            if (!empty($formatter->use_thumb_by_default))
                $image_url.= '&amp;thumbwidth='.$formatter->thumb_width;
        }

        $copyrighted = $copyright == 'True';
        $info = ($copyrighted ? '&copy; ' : '(&#596;) ').$author;
        if ($copyrighted && isset($license[0])) $info.= " ($license)";

        if (isset($params['retval'])) {
            $retval = &$params['retval'];
            $retval['url'] = $image_url;
            $retval['info'] = $info.$comment.' from '."<a href='$desc_url' target='_blank'>$source</a>";
            if (!empty($description))
                $retval['description'] = $description;
        }

        $out = '<div class="'.$class.'">';
        $out.= "<div><img src='$image_url'$style$attr>";
        $out.= "<div class='info'>".$info.$comment.' from '."<a href='$desc_url' target='_blank'>$source</a></div>";
        $out.= "</div>";
        if (!empty($DBInfo->wikimediacommons_use_description) && !empty($description))
            $out.= '<div class="desc">'.$description.'</div>';
        $out.= "</div>\n";
    } else {
        // not yet

        $image_url = str_replace(array('&', '?'), array('%26', '%3f'), $orig_value);
        if (isset($params['retval'])) {
            $retval = &$params['retval'];
            $url = $formatter->link_url('', '?action=wikimediacommons&url='.$image_url);
            $retval['url'] = qualifiedUrl($url);
        }

        $out = '<div class="'.$class.'">';
        if ($lazyload == 1 || !empty($params['call']) || !empty($formatter->wikimarkup))
            $out.= "<div><img class='loading' src='?action=wikimediacommons&amp;url=$image_url'$style$attr>";
        else
            $out.= "<div><img class='loading' ".
            "src='data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEAAAAALAAAAAABAAEAAAIBRAA7' ".
            "data-src='?action=wikimediacommons&amp;url=$image_url'$style$attr>";

        $out.= "<div class='info'></div>";
        $out.= "</div>";
        if (!empty($DBInfo->wikimediacommons_use_description))
            $out.= '<div class="desc"></div>';
        $out.= "</div>\n";
    }

    return $out;
}

// vim:et:sts=4:sw=4:
