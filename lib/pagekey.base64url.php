<?php
//
// MoniWiki PageKey class
//
// @since  2015/05/09
// @author wkpark@kldp.org
//

require_once(dirname(__FILE__).'/pagekey.base.php');

class PageKey_base64url extends PageKey_base {
    var $text_dir;

    function PageKey_base64url($conf) {
        if (is_object($conf)) {
            $this->text_dir = $conf->text_dir;
        } else {
            $this->text_dir = $conf['text_dir'];
        }
    }

    function getPageKey($pagename) {
        $name = $this->_getPageKey($pagename);
        return $this->text_dir . '/' . $name;
    }

    function pageToKeyname($pagename) {
        return $this->_getPageKey($pagename);
    }

    function keyToPagename($key) {
        $pagename = base64_decode(strtr($key, '-_', '+/').(($m = strlen($key) % 4) > 0 ? substr('====', $m) : ''));
        $pagename = strtr($pagename, array("\x0a" => "\x1a")); // HACK "%0a" char bug
        return $pagename;
    }

    // normalize a pagename to uniq key
    function _getPageKey($pagename) {
        return rtrim(strtr(base64_encode($pagename), '+/', '-_'), '=');
    }
}

// vim:et:sts=4:sw=4:
