<?php
/**
 * A Simple PageIndex for RandomPage macro
 *
 * @since 2010/08/16
 * @author Won-Kyu Park <wkpark@kldp.org>
 * @license GPL
 */

class PageIndex {
    var $text_dir = '';

    function PageIndex($DB)
    {
        $this->text_dir = $DB->text_dir;
        $this->cache_dir = $DB->cache_dir . '/pageindex';
        if (!is_dir($this->cache_dir)) {
            $om = umask(000);
            _mkdir_p($this->cache_dir, 0777);
            umask($om);
        }
        $this->pagelst = $this->cache_dir . '/pageindex.lst';
        $this->pageidx = $this->cache_dir . '/pageindex.idx';
    }

    function mtime()
    {
        return @filemtime($this->pageidx);
    }

    function init()
    {
        global $DBInfo;

        $dh = opendir($this->text_dir);
        if (!is_resource($dh)) return false;

        $fidx = fopen($this->pageidx.'.tmp', 'a+b');
        if (!is_resource($fidx)) {
            closedir($dh);
            return false;
        }
        $flst = fopen($this->pagelst.'.tmp', 'a+b');
        if (!is_resource($flst)) {
            closedir($dh);
            fclose($fidx);
            return false;
        }

        ftruncate($flst, 0);
        ftruncate($fidx, 0);

        $idx_data = '';
        $lst_data = '';
        $counter = 0;
        $fseek = 0;
        $pages = array();
        while(($f = readdir($dh)) !== false) {
            if ((($p = strpos($f, '.')) !== false or $f == 'RCS' or $f == 'CVS') and is_dir($this->text_dir .'/'. $f)) continue;
            $counter++;

            $idx_data.= pack('N', $fseek);
            $pagename = $DBInfo->keyToPagename($f);
            $len = strlen($pagename) + 1;
            $fseek += $len;
            $lst_data.= $DBInfo->keyToPagename($f)."\n";

            if ($counter > 1000) {
                fwrite($fidx, $idx_data);
                fwrite($flst, $lst_data);
                $idx_data = '';
                $lst_data = '';
                $counter = 0;
            }
        }
        if (!empty($lst_data)) {
            fwrite($fidx, $idx_data);
            fwrite($flst, $lst_data);
        }
        fclose($fidx);
        fclose($flst);
        closedir($dh);
        rename($this->pagelst.'.tmp', $this->pagelst);
        rename($this->pageidx.'.tmp', $this->pageidx);
    }

    function getPagesByIds($ids)
    {
        $fidx = fopen($this->pageidx, 'r');
        if (!is_resource($fidx)) return array();
        $flst = fopen($this->pagelst, 'r');
        if (!is_resource($flst)) {
            fclose($fidx);
            return array();
        }
        $pages = array();
        foreach($ids as $id) {
            fseek($fidx, $id * 4);
            $seek = unpack('N', fread($fidx, 4));
            fseek($flst, $seek[1]);
            $pg = fgets($flst, 2048);
            $pages[] = substr($pg, 0, -1);
        }
        fclose($fidx);
        fclose($flst);

        return $pages;
    }
}


// vim:et:sts=4:sw=4:
