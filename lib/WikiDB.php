<?php
/**
 * Copyright 2003-2015 Won-Kyu Park <wkpark@gmail.com> all rights reserved.
 * distributable under GPLv2 see COPYING
 *
 * the WikiDB class
 *
 * @since  2003/03/31
 * @author Won-Kyu Park <wkpark@gmail.com>
 * @license GPLv2
 *
 */

class WikiDB
{
    function WikiDB($config)
    {
        // set configurations
        if (is_object($config)) {
            $conf = get_object_vars($config); // merge default settings to $config
        } else {
            $conf = &$config;
        }
        foreach ($conf as $key => $val) {
            if ($key[0] == '_') continue; // internal variables
            $this->$key = $val;
        }

        $this->initEnv();
        $this->initModules();
        register_shutdown_function(array(&$this, 'Close'));
    }

    function initEnv()
    {
        if (!empty($this->path))
            putenv('PATH='.$this->path);

        if (!empty($this->rcs_user))
            putenv('LOGNAME='.$this->rcs_user);
        if (!empty($this->timezone))
            putenv('TZ='.$this->timezone);
        if (function_exists('date_default_timezone_set')) {
            // suppress date() warnings for PHP5.x
            date_default_timezone_set(@date_default_timezone_get());
        }
    }

    function initModules()
    {
        // pagekey class
        if (!empty($this->pagekey_class)) {
            require_once(dirname(__FILE__).'/pagekey.'.$this->pagekey_class.'.php');
            $pagekey_class = 'PageKey_'.$this->pagekey_class;
        } else {
            require_once(dirname(__FILE__).'/pagekey.compat.php');
            $pagekey_class = 'PageKey_compat';
        }

        $this->pagekey = new $pagekey_class($this);

        if (!empty($this->security_class)) {
            require_once(dirname(__FILE__)."/../plugin/security/$this->security_class.php");
            $class='Security_'.$this->security_class;
            $this->security=new $class ($this);
        } else
            $this->security=new Security_base($this);
    }

    function initAlias()
    {
        // parse the aliaspage
        if (!empty($this->use_alias) and file_exists($this->aliaspage)) {
            $ap = new Cache_text('settings');
            $aliases = $ap->fetch('alias');
            if (empty($aliases) or $ap->mtime() < filemtime($this->aliaspage)) {
                $aliases = get_aliases($this->aliaspage);
                $ap->update('alias', $aliases);
            }
        }

        if (!empty($aliases)) {
            require_once(dirname(__FILE__).'/metadb.text.php');
            $this->alias= new MetaDB_text($aliases);
        } else {
            $this->alias= new MetaDB();
        }
    }

    function initMetaDB()
    {
        if (empty($this->alias)) $this->initAlias();

        if (!empty($this->shared_metadb)) {
            if (!empty($this->shared_metadb_type) &&
                    in_array($this->shared_metadb_type, array('dba', 'compact'))) {
                // new
                $type = $this->shared_metadb_type;
                $dbname = $this->shared_metadb_dbname;
            } else {
                // old
                $type = 'dba';
                $dbname = $this->shared_metadb;
            }
            $class = 'MetaDB_'.$type;
            require_once(dirname(__FILE__).'/metadb.'.$type.'.php');
            $this->metadb = new $class($dbname, $this->dba_type);
        }
        if (empty($this->metadb->metadb)) {
            if (is_object($this->alias)) $this->metadb=$this->alias;
            else $this->metadb= new MetaDB();
        } else {
            $this->metadb->attachDB($this->alias);
        }
    }

    function Close()
    {
        if (!empty($this->metadb) and is_object($this->metadb))
            $this->metadb->close();
    }

    function _getPageKey($pagename)
    {
        return $this->pagekey->_getPageKey($pagename);
    }

    function getPageKey($pagename)
    {
        return $this->pagekey->getPageKey($pagename);
    }

    function pageToKeyname($pagename)
    {
        return $this->pagekey->_getPageKey($pagename);
    }

    function keyToPagename($key)
    {
        return $this->pagekey->keyToPagename($key);
    }

    function hasPage($pagename)
    {
        if (!isset($pagename[0])) return false;
        $name=$this->getPageKey($pagename);
        return @file_exists($name);
    }

    function getPage($pagename, $options = "")
    {
        return new WikiPage($pagename, $options);
    }

    function mtime()
    {
        // workaround to check the dir mtime of the text_dir
        if ($this->use_fakemtime)
            return @filemtime($this->editlog_name);

        return @filemtime($this->text_dir);
    }

    function checkUpdated($time, $delay = 1800)
    {
        return $this->mtime() <= $time + $delay;
    }

    /**
     * support lazy loading
     *
     */

    function &lazyLoad($name)
    {
        if (empty($this->$name)) {
            // get extra args
            $tmp = func_get_args();
            array_shift($tmp);
            $params = array();
            for ($i = 0, $num = count($tmp); $i < $num; $i++) {
                if (is_array($tmp[$i]))
                    $params = array_merge($params, $tmp[$i]);
                else
                    $params[] = $tmp[$i];
            }
            if (count($params) == 1) $params = $params[0];

            $classname = $name.'_class';
            // get $this->foobar_class
            if (!empty($this->$classname)) {
                // classname provided like as 'type' and the real classname is 'foobar_type'
                $file = $name.'.'.$this->$classname; // foobar.type.php
                // full classname provided like as Foobar_Type
                $file1 = strtr($this->$classname, '_', '.');
                $class0 = $name.'_'.$this->$classname; // foobar_type class
                if (class_exists($class0)) {
                    $class = $class0;
                } else if ((@include_once('lib/'.$file.'.php')) || (@include_once('lib/'.strtolower($file).'.php'))) {
                    $class = $name.'_'.$this->$classname; // foobar_type class
                } else if ((@include_once('lib/'.$file1.'.php')) || (@include_once('lib/'.strtolower($file1).'.php'))) {
                    $class = $this->$classname;
                } else if (class_exists($this->$classname)) {
                    $class = $this->$classname;
                } else {
                    trigger_error(sprintf(_("File '%s' or '%s' does not exist."), $file, $file1), E_USER_ERROR);
                    exit;
                }
                // create
                if (!empty($params))
                    $this->$name = new $class($params);
                else
                    $this->$name = new $class();

                // init module
                if (method_exists($this->$name, 'init_module')) {
                    call_user_func(array($this->$name, 'init_module'));
                }
            }
        }
        return $this->$name;
    }

    function getPageLists($options = array())
    {
        $indexer = $this->lazyLoad('titleindexer');
        return $indexer->getPages($options);
    }

    function getLikePages($needle, $count = 100, $opts = '')
    {
        $pages= array();

        if (!$needle) return false;

        $m = @preg_match("/$needle/".$opts, 'dummy');
        if ($m === false) return array();
        $indexer = $this->lazyLoad('titleindexer');
        return $indexer->getLikePages($needle, $count);
    }

    function getCounter()
    {
        $indexer = $this->lazyLoad('titleindexer');
        return $indexer->pageCount();
    }

    function addLogEntry($page_name, $remote_name, $comment, $action = "SAVE")
    {
        $user = &$this->user;

        $key_name = $this->_getPageKey($page_name);

        $myid=$user->id;
        if ($myid == 'Anonymous' and !empty($user->verified_email))
            $myid.= '-'.$user->verified_email;

        $comment = trim($comment);
        $comment = strtr(strip_tags($comment),
                array("\r\n"=>' ', "\r"=>' ',"\n"=>' ', "\t"=>' '));
        $fp_editlog = fopen($this->editlog_name, 'a+');
        $time = time();
        if ($this->use_hostname) $host = gethostbyaddr($remote_name);
        else $host = $remote_name;
        $key_name = trim($key_name);
        $msg = "$key_name\t$remote_name\t$time\t$host\t$myid\t$comment\t$action\n";
        fwrite($fp_editlog, $msg);
        fclose($fp_editlog);

        $params = array('pagename'=>$page_name,
                'remote_addr'=>$remote_name,
                'timestamp'=>$time,
                'hostname'=>$host,
                'id'=>$user->id,
                'comment'=>$comment,
                );

        if (function_exists('local_logger')) local_logger($action, $params);
    }

    function editlog_raw_lines($days,$opts=array())
    {
        global $Config;

        $ruleset = array();

        if (!empty($this->members) && !in_array($this->user->id, $this->members))
            $ruleset = $Config['ruleset']['hidelog'];

        $lines = array();

        $time_current = time();
        $secs_per_day = 24*60*60;

        if (!empty($opts['ago'])) {
            $date_from = $time_current - ($opts['ago'] * $secs_per_day);
            $date_to = $date_from + ($days * $secs_per_day);
        } else if (!empty($opts['from'])) {
            $from = strtotime($opts['from']);
            if ($time_current > $from)
                $date_from = $from;
            else
                $date_from = $time_current - ($from - $time_current);

            $date_to = $date_from + ($days * $secs_per_day);
        } else {
            if (!empty($opts['items'])) {
                $date_from = $time_current - (365 * $secs_per_day);
            } else {
                $date_from = $time_current - ($days * $secs_per_day);
            }
            $date_to = $time_current;
        }
        $check = $date_to;

        $itemnum = !empty($opts['items']) ? $opts['items'] : 200;

        $fp = fopen($this->editlog_name, 'r');
        while (is_resource($fp) and ($fz = filesize($this->editlog_name)) > 0){
            fseek($fp, 0, SEEK_END);
            if ($fz <= 1024) {
                fseek($fp,0);
                $ll = rtrim(fread($fp,1024));
                $lines = array_reverse(explode("\n", $ll));
                break;
            }
            $a=-1; // hack, don't read last \n char.
            $last='';
            fseek($fp,0,SEEK_END);
            while($date_from < $check and !feof($fp)){
                $rlen = $fz + $a;
                if ($rlen > 1024) { $rlen = 1024;}
                else if ($rlen <= 0) break;
                $a -= $rlen;
                fseek($fp, $a, SEEK_END);
                $l = fread($fp, $rlen);
                if ($rlen != 1024) $l = "\n".$l; // hack, for the first log entry.
                while(($p = strrpos($l, "\n"))!==false) {
                    $line = substr($l, $p+1).$last;
                    $last = '';
                    $l = substr($l, 0, $p);
                    $dumm = explode("\t", $line, 4);
                    $check = $dumm[2];
                    if ($date_from>$check) break;
                    if ($date_to>$check) {
                        if (!empty($ruleset)) {
                            $page_name = $this->keyToPagename($dumm[0]);
                            if (in_array($page_name, $ruleset))
                                continue;
                        }
                        $lines[]=$line;
                        $pages[$dumm[0]]=1;
                        if (sizeof($pages) >= $itemnum) { $check=0; break; }
                    }
                    $last='';
                }
                $last=$l.$last;
            }
            #echo $a;
            #echo sizeof($lines);
            #print_r($lines);
            fclose($fp);
            break;
        }

        if (!empty($opts['quick'])) {
            $out = array();
            foreach($lines as $line) {
                $dum=explode("\t",$line,2);
                if (!empty($dum[0]) and !empty($keys[$dum[0]])) continue;
                $keys[$dum[0]] = 1;
                $out[] = $line;
            }
            $lines = $out;
        }

        return $lines;
    }

    function _replace_variables($body, $options)
    {
        if ($this->template_regex
                && preg_match("/$this->template_regex/", $options['page']))
            return $body;

        $time = gmdate("Y-m-d\TH:i:s");

        if ($options['id'] == 'Anonymous') {
            $id = !empty($options['name']) ?
                _stripslashes($options['name']) : $_SERVER['REMOTE_ADDR'];
        } else {
            $id = !empty($options['nick']) ? $options['nick'] : $options['id'];
            if (!preg_match('/([A-Z][a-z0-9]+){2,}/',$id)) $id = '['.$id.']';
        }

        $body = preg_replace("/@DATE@/","[[Date($time)]]", $body);
        $body = preg_replace("/@USERNAME@/", "$id", $body);
        $body = preg_replace("/@TIME@/","[[DateTime($time)]]", $body);
        $body = preg_replace("/@SIG@/","-- $id [[DateTime($time)]]", $body);
        $body = preg_replace("/@PAGE@/", $options['page'], $body);
        $body = preg_replace("/@date@/", "$time", $body);

        return $body;
    }

    function _savePage($pagename, $body, $options = array())
    {
        $keyname = $this->_getPageKey($pagename);
        $filename = $this->text_dir.'/'.$keyname;

        $dir = dirname($filename);
        if (!is_dir($dir)) {
            $om = umask(~$this->umask);
            _mkdir_p($dir, 0777);
            umask($om);
        }

        $is_new = false;
        if (!file_exists($filename))
            $is_new = true;

        $fp = @fopen($filename, "a+b");
        if (!is_resource($fp))
            return -1;

        flock($fp, LOCK_EX); // XXX
        ftruncate($fp, 0);
        fwrite($fp, $body);
        flock($fp, LOCK_UN);
        fclose($fp);

        $ret = 0;
        if (!empty($this->version_class)) {
            $om = umask(~$this->umask);
            $ver = $this->lazyLoad('version', $this);

            // get diff
            if (!$is_new) {
                $diff = $ver->diff($pagename);
                // count diff lines, chars
                $changes = diffcount_lines($diff, $this->charset);
                // set return values
                $retval = &$options['retval'];
                $retval['add'] = $changes[0];
                $retval['del'] = $changes[1];
                $retval['add_chars'] = $changes[2];
                $retval['del_chars'] = $changes[3];
            } else {
                // new file.
                // set return values
                $retval = &$options['retval'];
                $retval['add'] = get_file_lines($filename);
                $retval['del'] = 0;
                $retval['add_chars'] = mb_strlen($body, $this->charset);
                $retval['del_chars'] = 0;
            }

            $force = $is_new || $options['.force'];
            // FIXME fix for revert+create cases for clear
            if ($is_new && preg_match('@;;{REVERT}@', $options['log'])) {
                $tmp = preg_replace('@;;{REVERT}:@', ';;{CREATE}{REVERT}:', $options['log']);
                if ($tmp !== null)
                    $options['log'] = $tmp;
            }
            $ret = $ver->_ci($filename, $options['log'], $force);
            if ($ret == -1)
                $options['retval']['msg'] = _("Fail to save version information");
            chmod($filename, 0666 & $this->umask);
            umask($om);
        }
        return $ret;
    }

    function savePage(&$page, $comment = '', $options = array())
    {
        if (empty($options['.force']) && !$this->_isWritable($page->name)) {
            return -1;
        }

        $user = &$this->user;
        if ($user->id == 'Anonymous' and !empty($this->anonymous_log_maxlen))
            if (strlen($comment) > $this->anonymous_log_maxlen) $comment = ''; // restrict comment length for anon.

        if (!empty($this->use_x_forwarded_for))
            $REMOTE_ADDR = get_log_addr();
        else
            $REMOTE_ADDR = $_SERVER['REMOTE_ADDR'];

        $myid=$user->id;
        if (!empty($user->info['nick'])) {
            $myid .= ' '.$user->info['nick'];
            $options['nick'] = $user->info['nick'];
        } else if ($myid == 'Anonymous' and !empty($user->verified_email)) {
            $myid .= '-'.$user->verified_email;
        }
        $options['myid'] = $myid;

        $keyname = $this->_getPageKey($page->name);
        $key = $this->text_dir."/$keyname";

        $body = $this->_replace_variables($page->body,$options);

        if (file_exists($key)) {
            $action = 'SAVE';
        } else {
            $action = 'CREATE';
        }
        if (!empty($options['.reverted']))
            $action = 'REVERT';

        if ($user->id == 'Anonymous' && $action == 'CREATE' &&
                empty($this->anomymous_allow_create_without_backlink)) {
            $bc = new Cache_Text('backlinks');
            if (!$bc->exists($page->name)) {
                $options['retval']['msg'] = _("Anonymous users can not create new pages.");
                return -1;
            }
        }

        // check abusing FIXME
        if (!empty($this->use_abusefilter)) {
            $params = array();
            $params['retval'] = &$options['retval'];
            $params['id'] = ($user->id == 'Anonymous') ? $REMOTE_ADDR : $user->id;
            $params['ip'] = $REMOTE_ADDR;
            $params['action'] = $options['action'];
            $params['editinfo'] = !empty($options['editinfo']) ? $options['editinfo'] : false;

            if (is_string($this->use_abusefilter))
                $filtername = $this->use_abusefilter;
            else
                $filtername = 'default';

            $ret = call_abusefilter($filtername, $action, $params);
            if ($ret === false) return -1;
        }

        if ($action == 'SAVE' && !empty($options['.minorfix'])) {
            $action = 'MINOR';
        }

        $comment = trim($comment);
        $comment = strtr(strip_tags($options['comment']),
                array("\r\n"=>' ', "\r"=>' ',"\n"=>' ', "\t"=>' '));
        // strip out all action flags FIXME
        $comment = preg_replace('@^{(SAVE|CREATE|DELETE|RENAME|REVERT|UPLOAD|ATTDRW|FORK|REVOKE|MINOR|BOTFIX)}:?@', '', $comment);

        if ($action != 'SAVE') {
            $tag = '{'.$action.'}';
            if (!empty($comment))
                $comment = $tag.': '.$comment;
            else
                $comment = $tag;
        }

        $log = $REMOTE_ADDR.';;'.$myid.';;'.$comment;
        $options['log'] = $log;
        $options['pagename'] = $page->name;

        $is_new = false;
        if (!file_exists($key)) $is_new = true;

        // get some edit info;
        $retval = array();
        $options['retval'] = &$retval;
        $ret = $this->_savePage($page->name, $body, $options);
        if ($ret == -1) return -1;

        //
        $page->write($body);

        // check minor edits XXX
        $minor=0;
        if (!empty($this->use_minorcheck) or !empty($options['minorcheck'])) {
            $info = $page->get_info();
            if (!empty($info[0][1])) {
                eval('$check='.$info[1].';');
                if (abs($check) < 3) $minor=1;
            }
        }
        if (empty($options['.nolog']) && empty($options['minor']) && !$minor)
            $this->addLogEntry($page->name, $REMOTE_ADDR, $comment, $action);

        if ($user->id != 'Anonymous' || !empty($this->use_anonymous_editcount)) {
            // save editing information
            if (!isset($user->info['edit_count']))
                $user->info['edit_count'] = 0;

            $user->info['edit_count']++;

            // added/deleted lines
            if (!isset($user->info['edit_add_lines']))
                $user->info['edit_add_lines'] = 0;
            if (!isset($user->info['edit_del_lines']))
                $user->info['edit_del_lines'] = 0;

            if (!isset($user->info['edit_add_chars']))
                $user->info['edit_add_chars'] = 0;
            if (!isset($user->info['edit_del_chars']))
                $user->info['edit_del_chars'] = 0;

            // added/deleted lines
            $user->info['edit_add_lines'] += $retval['add'];
            $user->info['edit_del_lines'] += $retval['del'];

            // added/deleted chars
            $user->info['edit_add_chars'] += $retval['add_chars'];
            $user->info['edit_del_chars'] += $retval['del_chars'];
            // save user
            $this->udb->saveUser($user);
        }

        $indexer = $this->lazyLoad('titleindexer');
        if ($is_new) $indexer->addPage($page->name);
        else $indexer->update($page->name); // just update mtime
        return 0;
    }

    function deletePage($page, $options = '')
    {
        if (empty($options['.force']) && !$this->_isWritable($page->name)) {
            return -1;
        }

        if (!empty($this->use_x_forwarded_for))
            $REMOTE_ADDR = get_log_addr();
        else
            $REMOTE_ADDR = $_SERVER['REMOTE_ADDR'];

        $comment = $options['comment'];
        $user = &$this->user;

        $action = 'DELETE';
        if (!empty($options['.revoke']))
            $action = 'REVOKE';

        // check abusing FIXME
        if (!empty($this->use_abusefilter)) {
            $params = array();
            $params['retval'] = &$options['retval'];
            $params['id'] = ($user->id == 'Anonymous') ? $REMOTE_ADDR : $user->id;

            if (is_string($this->use_abusefilter))
                $filtername = $this->use_abusefilter;
            else
                $filtername = 'default';

            $ret = call_abusefilter($filtername, 'delete', $params);
            if ($ret === false) return -1;
        }

        $comment = trim($comment);
        $comment = strtr(strip_tags($options['comment']),
                array("\r\n"=>' ', "\r"=>' ',"\n"=>' ', "\t"=>' '));
        // strip out all action flags FIXME
        $comment = preg_replace('@^{(SAVE|CREATE|DELETE|RENAME|REVERT|UPLOAD|ATTDRW|FORK|REVOKE|MINOR|BOTFIX)}:?@', '', $comment);

        $tag = '{'.$action.'}';
        if (!empty($comment))
            $comment = $tag.': '.$comment;
        else
            $comment = $tag;

        $keyname = $this->_getPageKey($page->name);

        $deleted = false;
        if (file_exists($this->text_dir.'/'.$keyname)) {
            $deleted = @unlink($this->text_dir.'/'.$keyname);

            // fail to delete
            if (!$deleted)
                return -1;
        }

        if (!empty($this->version_class)) {
            $version = $this->lazyLoad('version', $this);

            if ($deleted && !empty($this->log_deletion)) {
                // make a empty file to log deletion
                touch($this->text_dir.'/'.$keyname);

                $log = $REMOTE_ADDR.';;'.$user->id.';;'.$comment;
                $ret = $version->ci($page->name,$log, true); // force
            }

            // delete history
            if (!empty($this->delete_history) && in_array($options['id'], $this->owners)
                    && !empty($options['history']))
                $version->delete($page->name);

            // delete the empty file again
            if ($deleted)
                @unlink($this->text_dir.'/'.$keyname);
        }

        // history deletion case by owners
        if (!$deleted) return 0;

        if (empty($options['.nolog']))
            $this->addLogEntry($page->name, $REMOTE_ADDR, $comment, $action);

        $indexer = $this->lazyLoad('titleindexer');
        $indexer->deletePage($page->name);
        // remove pagelinks and backlinks
        store_pagelinks($page->name, array());

        // remove aliases
        if (!empty($this->use_alias))
            store_aliases($page->name, array());

        // remove redirects
        update_redirects($page->name, null);

        $handle = opendir($this->cache_dir);
        $permanents = array('backlinks', 'keywords', 'aliases', 'wordindex', 'redirect');
        while ($file = readdir($handle)) {
            if ($file[0] != '.' and is_dir("$this->cache_dir/$file") and is_file($this->cache_dir.'/'.$file.'/.info')) {
                // do not delete permanent caches
                if (in_array($file, $permanents)) continue;

                $cache = new Cache_text($file);
                $cache->remove($page->name);

                # blog cache
                if ($file == 'blogchanges') {
                    $files = array();
                    $cache->_caches($files, array('prefix'=>1));
                    foreach ($files as $file) {
                        #echo $keyname.';'.$fcache."\n";
                        if (preg_match("/\d+_2e$keyname$/", $file))
                            unlink($this->cache_dir.'/'.$file);
                    }
                } # for blog cache
            }
        }
        return 0;
    }

    function renamePage($pagename, $new, $options = array())
    {
        if (!$this->_isWritable($pagename)) {
            return -1;
        }

        if (!empty($this->use_x_forwarded_for))
            $REMOTE_ADDR = get_log_addr();
        else
            $REMOTE_ADDR = $_SERVER['REMOTE_ADDR'];

        // setup log
        $user = &$this->user;
        $myid = $user->id;
        if ($myid == 'Anonymous' and !empty($user->verified_email))
            $myid.= '-'.$user->verified_email;

        $renamed = sprintf("Rename [[%s]] to [[%s]]", $pagename, $new);
        $comment = trim($comment);
        $comment = strtr(strip_tags($options['comment']),
                array("\r\n"=>' ', "\r"=>' ',"\n"=>' ', "\t"=>' '));
        // strip out all action flags FIXME
        $comment = preg_replace('@^{(SAVE|CREATE|DELETE|RENAME|REVERT|UPLOAD|ATTDRW|FORK|REVOKE|MINOR|BOTFIX)}:?@', '', $comment);

        if (isset($comment[0]))
            $renamed .= ': '.$comment;
        else
            $renamed .= '.';

        $log = $REMOTE_ADDR.';;'.$myid.';;{RENAME}: '.$renamed;
        $options['log'] = $log;
        $options['pagename'] = $pagename;

        $with_history = false;
        $ret = 0;
        if (!empty($this->rename_with_history) || !empty($options['history']))
            $with_history = true;

        $okey = $this->getPageKey($pagename);
        $nkey = $this->getPageKey($new);
        $ret = rename($okey, $nkey);

        if (!$ret)
            return -1;

        if ($ret && $with_history && $this->version_class) {
            $version = $this->lazyLoad('version', $this);
            $ret = $version->rename($pagename, $new, $options);

            // fail to rename
            if ($ret < 0 || $ret === false)
                return -1;
        }

        // remove pagelinks and backlinks
        store_pagelinks($pagename, array());
        // remove aliases
        if (!empty($this->use_alias))
            store_aliases($pagename, array());

        $okeyname = $this->_getPageKey($pagename);
        $keyname = $this->_getPageKey($new);

        $newdir = $this->upload_dir.'/'.$keyname;
        $olddir = $this->upload_dir.'/'.$this->_getPageKey($pagename);
        if (!file_exists($newdir) and file_exists($olddir))
            rename($olddir, $newdir);

        $renameas = sprintf(_("Renamed as [[%s]]"), $new);
        $renamefrom = sprintf(_("Renamed from [[%s]]"), $pagename);
        $this->addLogEntry($pagename, $REMOTE_ADDR, $renameas, 'RENAME');
        $this->addLogEntry($new, $REMOTE_ADDR, $renamefrom, 'RENAME');

        $indexer = $this->lazyLoad('titleindexer');
        $indexer->renamePage($pagename, $new);
    }

    function _isWritable($pagename)
    {
        $key = $this->getPageKey($pagename);
        $dir = dirname($key);
        # global lock
        if (@file_exists($this->text_dir.'/.lock')) return false;
        if (@file_exists($dir.'/.lock')) return false;
        # True if page can be changed
        return @is_writable($key) or !@file_exists($key);
    }

    function getPerms($pagename)
    {
        $key = $this->getPageKey($pagename);
        if (file_exists($key))
            return fileperms($key);
        return 0666;
    }

    function setPerms($pagename, $perms)
    {
        $om = umask(0700);
        $key = $this->getPageKey($pagename);
        if (file_exists($key)) chmod($key, $perms);
        umask($om);
    }
}

// vim:et:sts=4:sw=4:
