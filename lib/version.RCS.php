<?php
// Copyright 2008 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a RCS versioning plugin for the MoniWiki
//
// @since  2003/08/22
// @author wkpark@kldp.org
//

class Version_RCS
{
    var $NULL = '';
    var $text_dir;
    var $vartmp_dir = '/var/tmp/';
    var $savepage_timeout = 5;
    var $rcs_always_unlock = false;
    var $rcs_error_log = false;
    var $pagekey;

    function Version_RCS($conf)
    {
        if (is_object($conf)) {
            $this->text_dir = $conf->text_dir;
            $this->vartmp_dir = $conf->vartmp_dir;
            $this->savepage_timeout = $conf->savepage_timeout;
            $this->rcs_always_unlock = $conf->rcs_always_unlock;

            $this->rcs_error_log = !empty($conf->rcs_error_log) ? $conf->rcs_error_log : false;
        } else {
            $this->text_dir = $conf['text_dir'];
            $this->vartmp_dir = $conf['vartmp_dir'];
            $this->savepage_timeout = $conf['savepage_timeout'];
            $this->rcs_always_unlock = $conf['rcs_always_unlock'];

            $this->rcs_error_log = !empty($conf['rcs_error_log']) ? $conf['rcs_error_log'] : false;
        }

        if (getenv('OS') != 'Windows_NT')
            $this->NULL = ' 2>/dev/null';
        if (!empty($this->rcs_error_log))
            $this->NULL = '';
    }

    function _filename($pagename)
    {
        global $DBInfo;

        # have to be factored out XXX
        # Return filename where this word/page should be stored.
        return $DBInfo->getPageKey($pagename);
    }

    function co($pagename, $rev, $opt=array())
    {
        $filename = $this->_filename($pagename);

        $suffix = ',v';

        // support archive
        if (!empty($opt['archive'])) {
            $archive = intval($opt['archive']);
            if ($archive >= 0)
                $suffix = ','.$archive;
        }

        $rev = (is_numeric($rev) and $rev>0) ? "\"".$rev."\" ":'';
        $ropt = '-p';
        if (!empty($opt['stdout'])) $ropt = '-r';
        $fp = @popen('co -x'.$suffix."/ -q $ropt$rev ".$filename.$this->NULL,"r");
        if (!empty($opt['stdout'])) {
            if (is_resource($fp)) {
                pclose($fp);
                return '';
            }
        }

        $out = '';
        if (is_resource($fp)) {
            while (!feof($fp)) {
                $line = fgets($fp, 2048);
                $out .= $line;
            }
            pclose($fp);
        }
        return $out;
    }

    function ci($pagename, $log, $force = false)
    {
        $key = $this->_filename($pagename);
        return $this->_ci($key, $log, $force);
    }

    function _ci($key, $log, $force = false)
    {
        $dir = dirname($key);
        if (!is_dir($dir.'/RCS')) {
            $om = umask(000);
            _mkdir_p($dir.'/RCS', 2777);
            umask($om);
        }
        $mlog = '';
        $plog = '';
        if (getenv('OS') == 'Windows_NT' and isset($log[0])) {
            // win32 cmd.exe arguments do not accept UTF-8 charset correctly.
            // just use the stdin commit msg method instead of using -m"log" argument.
            $logfile = tempnam($this->vartmp_dir, 'COMMIT_LOG');
            $fp = fopen($logfile, 'w');
            if (is_resource($fp)) {
                fwrite($fp, $log);
                fclose($fp);
                $plog = ' < '.$logfile;
            }
        }
        if (empty($plog)) {
            $log = escapeshellarg($log);
            $mlog = ' -m'.$log;
        }

        // setup lockfile
        $lockfile = $key.'.##';
        touch($lockfile);
        $fl = fopen($key.'.##', 'w');
        $counter = 0;
        $locked = true;

        // lock timeout
        $timeout = isset($this->savepage_timeout) && $this->savepage_timeout > 5 ?
            $this->savepage_timeout : 5;

        while(!flock($fl, LOCK_EX | LOCK_NB)) {
            if ($counter ++ < $timeout) {
                sleep(1);
            } else {
                $locked = false;
                break;
            }
        }

        if ($locked):
            if (!empty($this->rcs_always_unlock)) {
                $fp = popen("rcs -l -M $key", 'r');
                if (is_resource($fp)) pclose($fp);
            }

        // force option
        $f = '';
        if ($force)
            $f = '-f ';

        $fp = @popen("ci ".$f."-l -x,v/ -q -t-\"".$key."\" ".$mlog." ".$key.$plog.$this->NULL,"r");
        if (is_resource($fp)) pclose($fp);
        if (isset($plog[0])) unlink($logfile);

        flock($fl, LOCK_UN);
        fclose($fl);

        // remove lockfile
        unlink($lockfile);

        return 0;
        endif;

        // fail to get flock
        return -1;
    }

    function rlog($pagename, $rev = '', $opt = '', $params = array())
    {
        $dmark = '';
        if (isset($rev[0]) and in_array($rev[0], array('>', '<'))) {
            $dmark = $rev[0];
            $rev = substr($rev, 1);
        }
        if (is_numeric($rev) and preg_match('@^[0-9]{10}$@', trim($rev))) {
            // this is mtime
            $date = gmdate('Y/m/d H:i:s', $rev);
            $rev = '';
            if ($date)
                $rev = "-d\\$dmark'$date'";
        } else if (is_numeric($rev) and $rev > 0) {
            // normal revision
            $rev = "-r$rev";
        } else {
            $rev = '';
        }

        $suffix = ',v';

        $args = '';
        if (is_array($params)) {
            // support archive
            if (isset($params['archive'])) {
                $archive = intval($params['archive']);
                if ($archive >= 0)
                    $suffix = ','.$archive;
                unset($params['archive']);
            }
            $args = implode(' ', $params);
        } else if (is_string($params)) {
            $args = $params;
        }

        // absolute path ?
        if ($pagename[0] == '/' && strlen($pagename) > 1 && file_exists($pagename)) {
            // Is it a valid foobar,v RCS file?
            $fp = fopen($pagename, 'r');
            if (is_resource($fp)) {
                $header = fread($fp, 5);
                fclose($fp);
                if ($header != "head\t")
                    // not a valid RCS file.
                    $filename = $this->_filename($pagename);
                else
                    // OK. a RCS file.
                    $filename = $pagename;
            } else {
                $filename = $this->_filename($pagename);
            }
        } else
            $filename = $this->_filename($pagename);

        $fp = popen("rlog $opt $args -x".$suffix."/ $rev ".$filename.$this->NULL, 'r');
        $out = '';
        if (is_resource($fp)) {
            while (!feof($fp)) {
                $line = fgets($fp, 1024);
                $out .= $line;
            }
            pclose($fp);
        }
        return $out;
    }

    function diff($pagename, $rev = "", $rev2 = "", $params = array())
    {
        $option = '';
        $rev = escapeshellcmd($rev);
        $rev2 = escapeshellcmd($rev2);
        if ($rev) $option = "-r$rev ";
        if ($rev2) $option .= "-r$rev2 ";

        $suffix = ',v';
        if (isset($params['archive'])) {
            // support archive
            $archive = intval($params['archive']);
            if ($archive >= 0)
                $suffix = ','.$archive;
        }

        $filename = $this->_filename($pagename);
        $fp = popen("rcsdiff -x".$suffix."/ --minimal -u $option ".$filename.$this->NULL,'r');
        if (!is_resource($fp)) return '';
        while (!feof($fp)) {
            # trashing first two lines
            $line = fgets($fp,1024);
            if (preg_match('/^--- /', $line)) {
                $line = fgets($fp, 1024);
                break;
            }
        }
        $out = '';
        while (!feof($fp)) {
            $line = fgets($fp, 1024);
            $out .= $line;
        }
        pclose($fp);
        return $out;
    }

    function purge($pagename, $rev)
    {
    }

    function delete($pagename)
    {
        global $DBInfo;

        $keyname = $DBInfo->_getPageKey($pagename);
        // do not delete history at all.
        // just rename it.
        $this->_atticpage($pagename);
    }

    // store pagename
    function _atticpage($pagename)
    {
        global $DBInfo;

        $keyname = $DBInfo->_getPageKey($pagename);
        $oname = $this->text_dir."/RCS/$keyname".',v';

        $ext = ',v';
        $i = 0;
        while (file_exists($this->text_dir."/RCS/$keyname".$ext)) {
            $i++;
            $ext = ','.$i;
        }

        $atticname = $this->text_dir."/RCS/$keyname".$ext;
        if ($i != 0)
            return rename($oname, $atticname);
        return false;
    }

    // get all archived pages
    function attics($pagename)
    {
        global $DBInfo;

        $keyname = $DBInfo->_getPageKey($pagename);

        $ext = ',1';
        $i = 1;
        $archive = array();
        while (file_exists($this->text_dir."/RCS/$keyname".$ext)) {
            $archive[] = $i;
            $i++;
            $ext = ','.$i;
        }
        if (empty($archive))
            return false;
        return $archive;
    }

    function rename($pagename, $new, $params = array())
    {
        global $DBInfo;

        $keyname = $DBInfo->_getPageKey($new);
        $oname = $DBInfo->_getPageKey($pagename);

        $ret = $this->_atticpage($new);

        // check again and rename
        if (file_exists($this->text_dir."/RCS/$oname,v") and
                !file_exists($this->text_dir."/RCS/$keyname,v")) {
            $ret = rename($this->text_dir."/RCS/$oname,v",
                    $this->text_dir."/RCS/$keyname,v");

            if ($ret === false)
                return -1;

            $ret = $this->ci($new, $params['log'], true);
            if ($ret == 0)
                return 0;
        }
        return -1;
    }

    function get_rev($pagename, $mtime='', $last = 0)
    {
        $opt = '';
        $tag = 'revision';
        $end = '$';
        if ($last == 1) {
            $tag = 'revision';
            $opt = '-r';
            $end = '\s*';
        }
        if ($mtime) {
            $date = gmdate('Y/m/d H:i:s', $mtime);
            $opt = "-d'$date'";
            $tag = 'revision';
            $end = '\s*';
        }

        $rev = '';
        $total = 0;
        $selected = 0;

        if (empty($opt)) { $opt = '-r'; $end = '\s*'; }

        $out = $this->rlog($pagename, '', $opt);

        $total = 0;

        // get the number of the total revisons and the selected revisions
        if (isset($out[0])) {
            for ($line = strtok($out, "\n"); $line !== false; $line = strtok("\n")) {
                if (empty($total)) {
                    if (preg_match("/^total revisions:\s+(\d+)(?:;\s+selected revisions:\s+(\d+))?\s*$/", $line, $match)) {
                        $total = $match[1];
                        $selected = $match[2];
                        if ($selected == 0) return '';
                    }
                } else if (preg_match("/^$tag\s+(\d\.\d+)$end/", $line, $match)) {
                    $rev = $match[1];
                    $line = strtok("\n");
                    preg_match("/^date: ([^;]+);/", $line, $match);
                    $date = $match[1];
                    break;
                }
            }
        }

        if ($mtime or $last) return $rev;
        if (empty($date)) return '';

        // get the previous version number
        $date = gmdate('"Y/m/d H:i:s"', strtotime($date.' GMT') - 1); // HACK 1-second before
        $opt = '-d'.$date;
        $out = $this->rlog($pagename, '', $opt);
        if (isset($out[0])) {
            for ($line = strtok($out, "\n"); $line !== false; $line = strtok("\n")) {
                if (preg_match("/^$tag\s+(\d\.\d+)$end/", $line, $match)) {
                    $rev = $match[1];
                    break;
                }
            }
        }

        return $rev;
    }

    function is_broken($pagename)
    {
        global $DBInfo;

        $keyname = $DBInfo->_getPageKey($pagename);
        $fname = $this->text_dir."/RCS/$keyname,v";

        $fp = @fopen($fname, 'r');
        if (!is_resource($fp)) return 0; // ignore

        fseek($fp, -4, SEEK_END);
        $end = fread($fp, 4);
        fclose($fp);
        //if ($end != "@\n") return 1; // broken
        if (!preg_match("/@\n\s*$/", $end)) return 1; // broken
        return 0;
    }

    function export($pagename, $limit = 0)
    {
        global $DBInfo;

        $keyname = $DBInfo->_getPageKey($pagename);
        $fname = $this->text_dir."/RCS/$keyname,v";
        if ($limit > 0) {
            require_once('rcslite.php');
            $a = new RcsLite('RCS', $this->rcs_user);
            $a->_process($fname, $limit);
            end($a->_log);
            $rev = key($a->_log);
            // confirm next version is empty
            $a->_next[$rev] = '';
            return $a->_make_rcs();
        }

        $fp = fopen($fname, 'r');
        $out = '';
        if (is_resource($fp)) {
            $sz = filesize($fname);
            if ($sz > 0)
                $out = fread($fp, $sz);
            fclose($fp);
        }
        return $out;
    }

    function import($pagename, $rcsfile)
    {
        global $DBInfo;

        if (empty($rcsfile))
            return false;

        $keyname = $DBInfo->_getPageKey($pagename);
        $fname = $this->text_dir."/RCS/$keyname,v";
        $om = umask(0770);
        chmod($fname,0664);
        umask($om);
        $fp = fopen($fname,'w');
        if (is_resource($fp)) {
            fwrite($fp, $rcsfile);
            fclose($fp);
            return true;
        }
        return false;
    }

    // merge $old and $add RCS files
    function merge($old, $add, $params = array())
    {
        global $DBInfo;

        $rcs_user = $this->rcs_user;

        // old RCS file
        require_once(dirname(__FILE__).'/rcslite.php');
        $a = new RcsLite('RCS', $rcs_user);
        $key = $DBInfo->_getPageKey($old);
        $oldfile = $this->text_dir."/RCS/$key,v";
        $a->_process($oldfile);

        // RCS file to append
        $b = new RcsLite('RCS', $rcs_user);
        $key = $DBInfo->_getPageKey($add);
        $addfile = $this->text_dir."/RCS/$key,v";
        $b->_process($addfile);

        // get all revision numbers
        $revs = array_keys($b->_next);

        $rev = !empty($params['rev']) ? $params['rev'] : null;
        if (in_array($rev, $revs))
            // $rev is found ?
            $start = $rev;
        else
            // merge all
            $start = end($revs);

        // upto the last revision
        $end = $b->_head;

        // from
        $from = $a->_head;
        $tmp = explode('.', $from);
        $from = $tmp[0].'.'.($tmp[1] + 1);

        // merge RCS files
        for ($r = $start; !empty($b->_log[$r]);) {
            $text = $b->getRevision($r);
            $log = $b->_log[$r];
            $a->addRevisionText($text, $log, $b->_date[$r], false);
            $tmp = explode('.', $r);
            $r = $tmp[0].'.'.($tmp[1] + 1);
        }
        $log = $params['log'];
        if ($start == $end)
            $comment = sprintf("Merged [[%s]] r%s as r%s", $add, $start, $a->_head);
        else
            $comment = sprintf("Merged [[%s]] r%s ~ r%s as r%s ~ r%s", $add, $start, $end, $from, $a->_head);

        // return comment
        if (isset($params['retval'])) {
            $params['retval']['comment'] = $comment;
            $params['retval']['text'] = $text;
        }

        $log .= $comment;
        $a->addRevisionText($text, $log, time(), false);

        $merged = $a->_make_rcs();
        if (!empty($params['force'])) {
            $this->import($old, $merged);
            $keyname = $DBInfo->_getPageKey($old);
            $oldfile = $this->text_dir.'/'.$keyname;
            $fp = fopen($oldfile, 'w');
            if (is_resource($fp)) {
                fwrite($fp, $text);
                fclose($fp);
                // change mode
                $om = umask(0770);
                chmod($oldfile, 0664);
                umask($om);
            }
        }
        return $merged;
    }
}

// vim:et:sts=4:sw=4
