<?php
//
// MoniWiki utf8fs PageKey class
//
// @since  2015/05/15
// @author wkpark@kldp.org
// @desc   UTF-8 encoded filename + urlencoded punct for filesystem
//

require_once(dirname(__FILE__).'/pagekey.base.php');

class PageKey_utf8fs extends PageKey_base {
    var $text_dir;

    function PageKey_utf8fs($conf) {
        if (is_object($conf)) {
            $this->text_dir = $conf->text_dir;
        } else {
            $this->text_dir = $conf['text_dir'];
        }
    }

    function _pgencode($m) {
        return '%'.sprintf("%02s", strtolower(dechex(ord(substr($m[1],-1)))));
    }

    function getPageKey($pagename) {
        $name = $this->_getPageKey($pagename);
        return $this->text_dir . '/' . $name;
    }

    function pageToKeyname($pagename) {
        return $this->_getPageKey($pagename);
    }

    function keyToPagename($key) {
        $pagename = strtr($key, array('%0a' => '%1a')); // HACK "%0a" char bug
        return urldecode($pagename);
    }

    // normalize a pagename to uniq key
    function _getPageKey($pagename) {
        // fixup tab,CR,LF
        $pagename = preg_replace("@([\t\r\n]+)@", ' ', $pagename);
        // strip control chars
        $pagename = preg_replace('@[\x01-\x1f]@', '', $pagename);
        return preg_replace_callback("@([ [:punct:]])@",
                array($this, '_pgencode'), $pagename);
    }
}

// vim:et:sts=4:sw=4:
