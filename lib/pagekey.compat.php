<?php
//
// MoniWiki PageKey class
//
// @since  2015/05/06
// @author wkpark@kldp.org
//
// extracted from WikiDB class
//

require_once(dirname(__FILE__).'/pagekey.base.php');

class PageKey_compat extends PageKey_base {
    var $text_dir;
    var $use_namespace = false;

    function PageKey_compat($conf) {
        if (is_object($conf)) {
            $this->text_dir = $conf->text_dir;
            $this->use_namespace = $conf->use_namespace;
        } else {
            $this->text_dir = $conf['text_dir'];
            $this->use_namespace = $conf['use_namespace'];
        }
    }

    // moinmoin 1.0.x style internal encoding
    function _pgencode($m) {
        return '_'.sprintf("%02s", strtolower(dechex(ord(substr($m[1],-1)))));
    }

    function getPageKey($pagename) {
        #$name=preg_replace("/([^a-z0-9]{1})/ie","'_'.strtolower(dechex(ord('\\1')))",$pagename);
        $name = $this->_getPageKey($pagename);
        #$name=preg_replace("/([^a-z0-9]{1})/ie","'_'.strtolower(dechex(ord(substr('\\1',-1))))",$pagename);
        return $this->text_dir . '/' . $name;
    }

    function pageToKeyname($pagename) {
        return $this->_getPageKey($pagename);
        #return preg_replace("/([^a-z0-9]{1})/ie","'_'.strtolower(dechex(ord(substr('\\1',-1))))",$pagename);
        #return preg_replace("/([^a-z0-9]{1})/ie","'_'.strtolower(dechex(ord('\\1')))",$pagename);
    }

    function keyToPagename($key) {
        #  return preg_replace("/_([a-f0-9]{2})/e","chr(hexdec('\\1'))",$key);
        #  $pagename=preg_replace("/_([a-f0-9]{2})/","%\\1",$key);
        #  $pagename=str_replace("_","%",$key);

        $pagename = $key;

        // for namespace
        if (!empty($this->use_namespace))
            $pagename = preg_replace('%\.d/%', ':', $key);

        $pagename = strtr($pagename, '_', '%');
        $pagename = strtr($pagename, array('%0a' => '%1a')); // HACK "%0a" char bug
        return rawurldecode($pagename);
    }

    // normalize a pagename to uniq key
    function _getPageKey($pagename) {
        // moinmoin style internal encoding
        #$name=rawurlencode($pagename);
        #$name=strtr($name,"%","_");
        #$name=preg_replace("/%([a-f0-9]{2})/ie","'_'.strtolower('\\1')",$name);
        #$name=preg_replace(".","_2e",$name);

        $pagename = strtr($pagename, array("\x1a" => "\x0a")); # HACK "%0a" char bug
            // clean up ':' like as the dokuwiki
            if (!empty($this->use_namespace)) {
                $pn= preg_replace('#:+#',':',$pagename);
                $pn= trim($pn,':');
                $pn= preg_replace('#:+#',':',$pn);
            } else {
                $pn = $pagename;
            }

        // namespace spearator ':' like as 'Foobar:Hello'
        $separator = ':';
        if (empty($this->use_namespace)) $separator = '';

        $tr = array(
                '#'=>'_23', ';'=>'_3b', '/'=>'_2f', '?'=>'_3f',
                '='=>'_3d', '&'=>'_26', '-'=>'_2d', '.'=>'_2e',
                '~'=>'_7e', '_'=>'_5f', ':'=>'_3a', '%'=>'_',
                );

        // split into chunks
        $chunks = preg_split('@([a-zA-Z0-9'.$separator.']+)@',
                $pn, -1, PREG_SPLIT_DELIM_CAPTURE);
        for ($i = 0, $sz = count($chunks); $i < $sz; $i+= 2) {
            $chunks[$i] = strtr(strtolower(rawurlencode($chunks[$i])), $tr);
        }
        $pn = implode('', $chunks);
        //$pn = preg_replace_callback("/([^a-z0-9".$separator."]{1})/i",
        //        array($this, '_pgencode'), $pn);

        if (!empty($this->use_namespace))
            $name = preg_replace('#:#','.d/',$pn); // Foobar:Hello page will be stored as text/Foobar.d/Hello
        else
            $name = $pn;
        return $name;
    }
}

// vim:et:sts=4:sw=4:
