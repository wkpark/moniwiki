<?php
/**
 * Copyright 2003-2015 Won-Kyu Park <wkpark@gmail.com> all rights reserved.
 * distributable under GPLv2 see COPYING
 *
 * WikiPage class in the WikiDB.
 *
 * @since  2003/03/30
 * @author Won-Kyu Park <wkpark@gmail.com>
 * @license GPLv2
 *
 */

class WikiPage
{
    var $rev;
    var $title;
    var $filename;
    var $urlname;
    var $name;

    var $pi = null;
    var $body;

    function WikiPage($name, $params = array())
    {
        if (!empty($params['rev']))
            $this->rev = $params['rev'];
        else
            $this->rev = 0; # current rev.
                $this->name = $name;
        $this->filename = $this->_filename($name);

        $this->urlname = _rawurlencode($name);
        $this->body = '';
        $this->title = get_title($name);
        #$this->title=preg_replace("/((?<=[A-Za-z0-9])[A-Z][a-z0-9])/"," \\1",$name);
    }

    function _filename($pagename)
    {
        # have to be factored out XXX
        # Return filename where this word/page should be stored.
        global $DBInfo;
        return $DBInfo->getPageKey($pagename);
    }

    function exists()
    {
        # Does a page for the given word already exist?
        return @file_exists($this->filename);
    }

#   function writable() {
#       # True if page can be changed
#       return is_writable($this->filename) or !$this->exists();
#   }

    function mtime()
    {
        return @filemtime($this->filename);
    }

    function etag($params = array())
    {
        global $DBInfo;

        $dep = '';
        $tag = '';
        if (!empty($DBInfo->etag_seed))
            $tag.= $DBInfo->etag_seed;

        // check some parameters
        foreach (array('action', 'lang', 'theme') as $k)
            if (isset($params[$k])) $tag.= $params[$k];

        if (!empty($params['deps'])) {
            foreach ($params['deps'] as $d) {
                !empty($params[$d]) ? $tag.= $params[$d] : true;
            }
        }
        if ($params['action'] != 'raw' || empty($params['nodep']))
            $dep.= $DBInfo->mtime();
        return md5($this->mtime().$dep.$tag.$this->name);
    }

    function size()
    {
        if ($this->fsize) return $this->fsize;
        $this->fsize = @filesize($this->filename);
        return $this->fsize;
    }

    function lines()
    {
        return get_file_lines($this->filename);
    }

    function get_raw_body($options = '')
    {
        global $DBInfo;

        if ($this->body && empty($options['rev']))
            return $this->body;

        $rev = !empty($options['rev']) ? $options['rev']:(!empty($this->rev) ? $this->rev:'');
        if (!empty($rev)) {
            if (!empty($DBInfo->version_class)) {
                $version = $DBInfo->lazyLoad('version', $DBInfo);
                $out = $version->co($this->name, $rev, $options);
                return $out;
            } else {
                return _("Version info does not supported in this wiki");
            }
        }
        $fp = @fopen($this->filename, 'r');
        if (!is_resource($fp)) {
            if (file_exists($this->filename)) {
                $out="You have no permission to see this page.\n\n";
                $out.="See MoniWiki/AccessControl\n";
                return $out;
            }
            $out = _("File does not exist");
            return $out;
        }
        $this->fsize = filesize($this->filename);
        if ($this->fsize > 0)
            $body = fread($fp, $this->fsize);
        fclose($fp);
        $this->body = $body;

        return $body;
    }

    function _get_raw_body()
    {
        $fp = @fopen($this->filename,"r");
        if (is_resource($fp)) {
            $size = filesize($this->filename);
            if ($size >0)
                $this->body = fread($fp, $size);
            fclose($fp);
        } else
            return '';

        return $this->body;
    }

    function set_raw_body($body)
    {
        $this->body = $body;
    }

    function update()
    {
        if ($this->body)
            $this->write($this->body);
    }

    function write($body)
    {
        $this->body = $body;
    }

    function get_rev($mtime = '', $last = 0)
    {
        global $DBInfo;

        if (!empty($DBInfo->version_class)) {
            $version = $DBInfo->lazyLoad('version', $DBInfo);
            $rev= $version->get_rev($this->name,$mtime,$last);

            if (!empty($rev)) return $rev;
        }
        return '';
    }

    function get_info($rev = '')
    {
        global $DBInfo;

        $infos = array();
        if (empty($rev))
            $rev = $this->get_rev('', 1);
        if (empty($rev)) return false;

        if (!empty($DBInfo->version_class)) {
            $opt = '';

            $version = $DBInfo->lazyLoad('version', $DBInfo);
            $out = $version->rlog($this->name,$rev,$opt);
        } else {
            return false;
        }

        $state=0;
        if (isset($out)) {
            for ($line = strtok($out,"\n"); $line !== false;$line = strtok("\n")) {
                if ($state == 0 and preg_match("/^date:\s.*$/", $line)) {
                    $info = array();
                    $tmp = preg_replace("/date:\s(.*);\s+author:.*;\s+state:.*;/", "\\1", rtrim($line));
                    $tmp = explode('lines:', $tmp);
                    $info[0] = $tmp[0];
                    $info[1] = isset($tmp[1]) ? $tmp[1] : '';
                    $state = 1;
                } else if ($state) {
                    list($info[2], $info[3], $info[4]) = explode(';;', $line, 3);
                    $infos[] = $info;
                    $state = 0;
                }
            }
        }
        return $infos;
    }

    function get_redirect()
    {
        $body = $this->get_raw_body();
        if ($body[0] == '#' and ($p = strpos($body, "\n")) !== false) {
            $line = substr($body, 0, $p);
            if (preg_match('/#redirect\s/i', $line)) {
                list($tag, $val) = explode(' ', $line, 2);
                if (isset($val[0])) return $val;
            }
        }
    }

    function get_instructions($body = '', $params = array())
    {
        global $Config;

        $pikeys = array('#redirect', '#action', '#title', '#notitle', '#keywords', '#noindex',
                '#format', '#filter', '#postfilter', '#twinpages', '#notwins', '#nocomment', '#comment',
                '#language', '#camelcase', '#nocamelcase', '#cache', '#nocache', '#alias', '#linenum', '#nolinenum',
                '#description', '#image',
                '#noads', // hide google ads
                '#singlebracket', '#nosinglebracket', '#rating', '#norating', '#nodtd');
        $pi = array();

        $format = '';

        // get page format from $pagetype
        if (empty($this->pi['#format']) and !empty($Config['pagetype'])) {
            preg_match('%(:|/)%',$this->name,$sep);
            $key = strtok($this->name,':/');
            if (isset($Config['pagetype'][$key]) and $f = $Config['pagetype'][$key]) {
                $p = preg_split('%(:|/)%', $f);
                $p2 = strlen($p[0].$p[1])+1;
                $p[1] = $p[1] ? $f{strlen($p[0])}.$p[1] : '';
                $p[2] = $p[2] ? $f{$p2}.$p[2] : '';
                $format = $p[0];
                if ($sep[1]) { # have : or /
                    $format = ($sep[1] == $p[1]{0}) ? substr($p[1], 1):
                        (($sep[1] == $p[2]{0}) ? substr($p[2], 1) : 'plain');
                }
            } else if (isset($Config['pagetype']['*']))
                $format = $Config['pagetype']['*']; // default page type
        } else {
            if (empty($body) and !empty($this->pi['#format']))
                $format = $this->pi['#format'];
        }

        $update_pi = false;
        if (empty($body)) {
            if (!$this->exists()) return array();
            if (isset($this->pi)) return $this->pi;

            $pi_cache = new Cache_text('PI');
            if (empty($params['refresh']) and $this->mtime() < $pi_cache->mtime($this->name)) {
                $pi = $pi_cache->fetch($this->name);

                if (!isset($pi['#format']))
                    $pi['#format'] = $Config['default_markup'];

                return $pi;
            }

            $body = $this->get_raw_body();
            $update_pi = true;
        }

        if (!empty($Config['use_metadata'])) {
            // FIXME experimental
            include_once(dirname(__FILE__).'/metadata.php');
            list($this->metas, $nbody) = _get_metadata($body);
            if ($nbody != null) $body = $nbody;
        }

        if (!$format and $body[0] == '<') {
            list($line, $dummy) = explode("\n", $body,2);
            if (substr($line, 0, 6) == '<?xml ')
                #$format = 'xslt';
                $format = 'xsltproc';
            elseif (preg_match('/^<\?php(\s|\b)/', $line))
                $format = 'php'; # builtin php detect
        } else {
            if ($body[0] == '#' and $body[1] == '!') {
                list($format, $body) = explode("\n", $body, 2);
                $format = rtrim(substr($format, 2));
            }

            // not parsed lines are comments
            $notparsed = array();
            $pilines = array();
            $body_start = 0;
            while ($body and $body[0] == '#') {
                $body_start++;
                # extract first line
                list($line, $body) = explode("\n", $body, 2);
                if ($line == '#') break;
                else if ($line[1] == '#') {
                    $notparsed[] = $line;
                    continue;
                }
                $pilines[] = $line;

                $val = '';
                if (($pos = strpos($line, ' ')) !== false)
                    list($key, $val) = explode(' ', $line, 2);
                else
                    $key = trim($line);
                $key = strtolower($key);
                $val = trim($val);
                if (in_array($key, $pikeys)) {
                    $pi[$key] = $val ? $val : 1;
                } else {
                    $notparsed[] = $line;
                    array_pop($pilines);
                }
            }
            $piline = implode("\n", $pilines);
            $piline = $piline ? $piline."\n" : '';

            if (isset($pi['#notwins'])) $pi['#twinpages'] = 0;
            if (isset($pi['#nocamelcase'])) $pi['#camelcase'] = 0;
            if (isset($pi['#nocache'])) $pi['#cache'] = 0;
            if (isset($pi['#nofilter'])) unset($pi['#filter']);
            if (isset($pi['#nosinglebracket'])) $pi['#singlebracket'] = 0;
            if (isset($pi['#nolinenum'])) $pi['#linenum'] = 0;
        }

        if (empty($pi['#format']) and !empty($format))
            $pi['#format'] = $format; // override default

        if (!empty($pi['#format']) and ($p = strpos($pi['#format'], ' ')) !== false) {
            $pi['args'] = substr($pi['#format'], $p + 1);
            $pi['#format']= substr($pi['#format'], 0, $p);
        }

        if (!empty($piline)) $pi['raw'] = $piline;
        if (!empty($body_start)) $pi['start_line'] = $body_start;

        if ($update_pi) {
            $pi_cache->update($this->name, $pi);
            $this->cache_instructions($pi, $params);
        }

        if (!isset($pi['#format']))
            $pi['#format'] = $Config['default_markup'];

        return $pi;
    }

    function cache_instructions($pi, $params = array())
    {
        global $Config;

        $pagename = $this->name;

        // update aliases
        if (!empty($Config['use_alias'])) {
            $ac = new Cache_text('alias');
            // is it removed ?
            if ($ac->exists($pagename) and
                    empty($pi['#alias']) and empty($pi['#title'])) {
                // remove aliases
                store_aliases($pagename, array());
            } else if (!$ac->exists($pagename) or
                    $ac->mtime($pagename) < $this->mtime() or !empty($_GET['update_alias'])) {
                $as = array();
                // parse #alias
                if (!empty($pi['#alias']))
                    $as = get_csv($pi['#alias']);
                // add #title as a alias
                if (!empty($pi['#title']))
                    $as[] = $pi['#title'];

                // update aliases
                store_aliases($pagename, $as);
            }
        }

        // update redirects cache
        $redirect = isset($pi['#redirect'][0]) ? $pi['#redirect'] : null;
        update_redirects($pagename, $redirect, $params['refresh']);

        if (!empty($Config['use_keywords']) or !empty($Config['use_tagging']) or !empty($_GET['update_keywords'])) {
            $tcache = new Cache_text('keyword');
            $cache = new Cache_text('keywords');

            $cur = $tcache->fetch($pagename);
            if (empty($cur)) $cur = array();
            $keys = array();
            if (empty($pi['#keywords'])) {
                $tcache->remove($pagename);
            } else {
                $keys = explode(',', $pi['#keywords']);
                $keys = array_map('trim', $keys);
                if (!$tcache->exists($pagename) or
                        $tcache->mtime($pagename) < $this->mtime() or
                        !empty($_GET['update_keywords'])) {
                    $tcache->update($pagename, $keys);
                }
            }

            $adds = array_diff($keys, $cur);
            $dels = array_diff($cur, $keys);

            // merge new keywords
            foreach ($adds as $a) {
                if (!isset($a[0])) continue;
                $l = $cache->fetch($a);
                if (!is_array($l)) $l = array();
                $l = array_merge($l, array($pagename));
                $cache->update($a, $l);
            }

            // remove deleted keywords
            foreach ($dels as $d) {
                if (!isset($d[0])) continue;
                $l = $cache->fetch($d);
                if (!is_array($l)) $l = array();
                $l = array_diff($l, array($pagename));
                $cache->update($d, $l);
            }
        }

        if (!empty($pi['#title']) and !empty($Config['use_titlecache'])) {
            $tc = new Cache_text('title');
            $old = $tc->fetch($pagename);
            if (!isset($pi['#title']))
                $tc->remove($pagename);
            else if ($old != $pi['#title'] or !$tcache->exists($pagename) or !empty($_GET['update_title']))
                $tc->update($pagename, $pi['#title']);
        }

        return;
    }
}

// vim:et:sts=4:sw=4:
