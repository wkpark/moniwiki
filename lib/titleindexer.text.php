<?php
/**
 * A Simple text based TitleIndexer
 *
 * @since 2013/05/16
 * @author Won-Kyu Park <wkpark@kldp.org>
 * @license GPLv2
 */

class TitleIndexer_Text {
    var $text_dir = '';
    var $_match_flags = 'uim';

    function TitleIndexer_Text($name = 'titleindexer')
    {
        global $Config;

        if (strtolower($Config['charset']) != 'utf-8')
            $this->_match_flags = 'im';

        $this->text_dir = $Config['text_dir'];
        $this->cache_dir = $Config['cache_dir'] . '/'.$name;
        if (!is_dir($this->cache_dir)) {
            $om = umask(000);
            _mkdir_p($this->cache_dir, 0777);
            umask($om);
        }
        $this->pagelst = $this->cache_dir . '/'.$name.'.lst';
        $this->pagecnt = $this->cache_dir . '/'.$name.'.cnt';
        $this->pagelck = $this->cache_dir . '/'.$name.'.lock';
    }

    function mtime()
    {
        return @filemtime($this->pagelst);
    }

    /**
     * update the lifetime of the pagelist
     *
     * @access public
     */
    function update()
    {
        return touch($this->pagelst);
    }

    function init()
    {
        global $DBInfo;

        // check if a tmp file already exists and outdated or not.
        if (file_exists($this->pagelst.'.tmp')) {
            if (time() - filemtime($this->pagelst.'.tmp') < 60*30)
                return false;
        }

        $dh = opendir($this->text_dir);
        if (!is_resource($dh)) return false;

        $flst = fopen($this->pagelst.'.tmp', 'a+b');
        if (!is_resource($flst)) {
            closedir($dh);
            return false;
        }

        $fcnt = fopen($this->pagecnt.'.tmp', 'a+b');
        if (!is_resource($flst)) {
            closedir($dh);
            fclose($flst);
            return false;
        }

        ftruncate($flst, 0);
        ftruncate($fcnt, 0);

        $lst_data = '';
        $total = 0;
        $counter = 0;
        $fseek = 0;
        $pages = array();
        while(($f = readdir($dh)) !== false) {
            if ((($p = strpos($f, '.')) !== false or $f == 'RCS' or $f == 'CVS') and is_dir($this->text_dir .'/'. $f)) continue;
            $counter++;
            $total++;

            $lst_data.= $DBInfo->keyToPagename($f)."\n";

            if ($counter > 1000) {
                fwrite($flst, $lst_data);
                $lst_data = '';
                $counter = 0;
            }
        }
        if (!empty($lst_data)) {
            fwrite($flst, $lst_data);
        }
        fclose($flst);
        closedir($dh);

        fwrite($fcnt, $total); // total page counter
        fclose($fcnt);

        if (getenv('OS') == 'Windows_NT') {
            @unlink($this->pagelst);
            @unlink($this->pagecnt);
        }
        rename($this->pagelst.'.tmp', $this->pagelst);
        rename($this->pagecnt.'.tmp', $this->pagecnt);
    }

    function init_module()
    {
        global $DBInfo;

        // check init() is needed
        if ($this->PageCount() !== false and $DBInfo->checkUpdated($this->mtime(), 60))
            return;

        // exclusive lock to prevent multiple init() calls
        $lock = @fopen($this->pagelck, 'x');
        if (is_resource($lock)) {
            if (flock($lock, LOCK_EX)) {
                $this->init();
                flock($lock, LOCK_UN);
            }
            fclose($lock);
            unlink($this->pagelck);
        }
    }

    function getPagesByIds($ids)
    {
        $lst = file_get_contents($this->pagelst);
        if ($lst === false)
            return false;

        $pages = explode("\n", $lst);
        array_pop($pages); // trash the last empty name
        $count = count($pages);

        $selected = array();
        foreach((array)$ids as $id) {
            if ($id >= $count) continue;
            $selected[] = $pages[$id];
        }

        return $selected;
    }

    function pageCount()
    {
        $count = @file_get_contents($this->pagecnt);
        return $count;
    }

    function sort()
    {
        // check if a tmp file already exists and outdated or not.
        if (file_exists($this->pagelst.'.tmp')) {
            if (time() - filemtime($this->pagelst.'.tmp') < 60*30)
                return false;
        }

        $lock = fopen($this->pagelck, 'w');
        if (!is_resource($lock)) return false;
        flock($lock, LOCK_EX);

        $flst = fopen($this->pagelst.'.tmp', 'a+b');
        if (!is_resource($flst)) {
            return false;
        }

        $tmp = file_get_contents($this->pagelst);
        if ($tmp === false) {
            fclose($flst);
            flock($lock, LOCK_UN);
            fclose($lock);
            return false;
        }

        ftruncate($flst, 0);

        $lst = explode("\n", $tmp);
        $tmp = '';
        array_pop($lst); // trash last empty line
        sort($lst); // sort list

        fwrite($flst, implode("\n", $lst)."\n");
        fclose($flst);

        if (getenv('OS') == 'Windows_NT') {
            @unlink($this->pagelst);
        }
        rename($this->pagelst.'.tmp', $this->pagelst);

        flock($lock, LOCK_UN);
        fclose($lock);
    }

    function addPage($pagename)
    {
        // protect \n char
        $pagename = str_replace("\x0a", "\x1a", $pagename);

        $lock = fopen($this->pagelck, 'w');
        if (!is_resource($lock)) return false;
        flock($lock, LOCK_EX);

        $flst = fopen($this->pagelst, 'a+');
        if (!is_resource($flst)) {
            flock($lock, LOCK_UN);
            fclose($lock);
            return false;
        }

        $fcnt = fopen($this->pagecnt, 'a+b');
        if (!is_resource($fcnt)) {
            fclose($flst);
            flock($lock, LOCK_UN);
            fclose($lock);
            return false;
        }
        $total = fgets($fcnt, 1024);

        fseek($flst, 0, SEEK_END);
        $seek = ftell($flst); // get last position of the page list

        fwrite($flst, $pagename."\n"); // add a new page name.
        fclose($flst);

        ftruncate($fcnt, 0);
        fwrite($fcnt, ++$total); // increase the page counter
        fclose($fcnt);

        flock($lock, LOCK_UN);
        fclose($lock);

        return $total;
    }

    function deletePage($pagename)
    {
        // protect \n char
        $pagename = str_replace("\x0a", "\x1a", $pagename);

        $lock = fopen($this->pagelck, 'w');
        if (!is_resource($lock)) return false;
        flock($lock, LOCK_EX);

        // get the list of pages
        $lst = @file_get_contents($this->pagelst);
        if ($lst === false) {
            flock($lock, LOCK_UN);
            fclose($lock);
            return false;
        }

        $pages = explode("\n", $lst);
        array_pop($pages); // trash the last empty name

        $key = array_search($pagename, $pages);
        if ($key === null) {
            flock($lock, LOCK_UN);
            fclose($lock);
            return false;
        }
        unset($pages[$key]);
        $flst = fopen($this->pagelst, 'a+b');
        if (!is_resource($flst)) {
            flock($lock, LOCK_UN);
            fclose($lock);
            return false;
        }

        $fcnt = fopen($this->pagecnt, 'a+b');
        if (!is_resource($fcnt)) {
            fclose($flst);
            flock($lock, LOCK_UN);
            fclose($lock);
            return false;
        }

        ftruncate($flst, 0);
        fwrite($flst, implode("\n", $pages)."\n");
        fclose($flst);

        ftruncate($fcnt, 0);
        fwrite($fcnt, count($pages)); // total page counter
        fclose($fcnt);

        flock($lock, LOCK_UN);
        fclose($lock);
        return $total;
    }

    function renamePage($oldname, $newname)
    {
        $this->deletePage($oldname);
        $this->addPage($newname);
    }

    function getLikePages($needle, $limit = 100, $params = array())
    {
        if (!isset($needle[0])) return false; // null needle

        // escape \n char
        $needle = str_replace("\x0a", "\x1a", $needle);

        $total = file_get_contents($this->pagecnt);
        if ($total === false) return false;

        $flst = fopen($this->pagelst, 'r');
        if (!is_resource($flst)) {
            return false;
        }

        $pages = array();

        $pre = '.*';
        $suf = '.*';
        if ($needle[0] == '^') {
            $pre = '';
            $needle = substr($needle, 1);
        }
        if (substr($needle, -1) == '$') {
            $suf = '';
            $needle = substr($needle, 0, -1);
        }

        fseek($flst, 0, SEEK_END);
        $size = ftell($flst);
        fseek($flst, 0, SEEK_SET);
        $chunk = min(10240, intval($size / 10));
        $chunk = max($chunk, 8192);
        while (!feof($flst)) {
            $data = fread($flst, $chunk);
            $data .= fgets($flst, 2048);

            if (preg_match_all('/^'.$pre.'(?:'.$needle.')'.$suf.'$/'.$this->_match_flags, $data, $match)) {
                $pages = array_merge($pages, $match[0]);
                if (!empty($limit) and count($pages) > $limit) break;
            }
        }

        fclose($flst);

        return $pages;
    }

    function getPages($params) {
        global $DBInfo;

        //$count = @file_get_contents($this->pagecnt);
        $lst = file_get_contents($this->pagelst);
        if ($lst === false)
            return false;

        $info = array();

        $pages = explode("\n", $lst);
        array_pop($pages); // trash the last empty name

        if (!empty($params['all'])) return $pages;

        $offset = 0;
        if (!empty($params['offset']) and
                is_numeric($params['offset']) and
                $params['offset'] > 0)
            $offset = $params['offset'];

        // set page_limit
        $pages_limit = isset($DBInfo->pages_limit) ?
                $DBInfo->pages_limit : 5000; // 5000 pages

        $info['count'] = count($pages);
        if ($pages_limit > 0) {
            $pages = array_slice($pages, $offset, $pages_limit);
            $info['offset'] = $offset;
            $info['count'] = $pages_limit;
        } else if ($offset > 0) {
            $pages = array_slice($pages, $offset);
            $info['offset'] = $offset;
            $info['count'] = count($pages);
        }
        if (isset($params['ret'])) $params['ret'] = $info;

        return $pages;
    }
}

// vim:et:sts=4:sw=4:
