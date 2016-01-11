<?php
# a ACL security plugin for the MoniWiki
#
# Please see http://moniwiki.kldp.net/wiki/wiki.php/MoniWikiACL
#
#        see also http://www.dokuwiki.org/acl
#
# ACL file example:
#
## page|*  @group/user allow|deny|protect   action list|*
#
# *     @ALL    allow   *                   # allow all actions
# *     @ALL    allow   ticket              # allow ticket action to show ticket img
# *     Anonymous   deny    edit,diff,info  # deny some actions for Anonymous
# MoniWiki  @ALL    deny    uploadfile,uploadedfiles,edit
# ACL   @ALL        deny    edit,diff,info
## protect all protectable actions
# *	@ALL        protect deletefile,deletepage,rename,rcspurge,rcs,chmod,backup,restore
#
## set group and priority of group
## the priority of @ALL is 1
## User's priority is 4
## @groupname   userlist    [priority (default=2)]
# @Kiwirian     foobar,kiwi 100
# *     @Kiwirian   deny    *
# *     @Kiwirian   allow   read

require_once(dirname(__FILE__).'/../../lib/checkip.php');
require_once(dirname(__FILE__).'/../../lib/cache.text.php');

class Security_ACL extends Security_base {
    var $DB;

    var $_acl_ok=0;
    var $_protected=array();
    function Security_ACL($DB="") {
        $this->DB=$DB;
        # load ACL
        if (!empty($DB->config_dir))
            $config_dir = $DB->config_dir;
        else
            $config_dir = dirname(__FILE__).'/../../config';

        if (!empty($DB->acl_type) and file_exists($config_dir.'/acl.'.$DB->acl_type.'.php'))
            $acl_file=$config_dir.'/acl.'.$DB->acl_type.'.php';
        else
            $acl_file=$config_dir.'/acl.default.php';

        if (is_readable($acl_file)) {
            $this->cache = new Cache_text('acl');
            $this->aux_cache = new Cache_text('aux_acl');
            // merge all acl files
            $cache = new Cache_text('settings', array('depth'=>0));
            $acl_lines = $cache->fetch('acl');
            if ($acl_lines === false) {
                $params = array();
                // save dependencies
                $deps = array($acl_file);
                $params['deps'] = &$deps;
                // read ACL files
                $acl_lines = $this->read_acl($acl_file, $params);
                $cache->update('acl', $acl_lines, 0, $params);

                // parse ACL file
                list($pages, $rules, $group) = $this->parse_acl($acl_lines);
                // save group definitions
                $cache->update('acl_group', $group);

                // save individual acl of all pages
                foreach ($pages as $pagename=>$acl) {
                    $this->cache->update($pagename, $acl, 0, $params);
                }
                // save default ACL
                $cache->update('acl_default', $rules['*']);
                unset($rules['*']);
                // make all in one regex for all patthern
                $tmp = array_keys($rules);
                $rule = '('.implode(')|(', $tmp).')';

                $vals = array_values($rules);
                $vals['*'] = $rule;
                $cache->update('acl_rules', $vals);
            }

            $this->AUTH_ACL = $acl_lines;
            $this->default = $cache->fetch('acl_default');
            $this->rules = $cache->fetch('acl_rules');
            $this->rule = $this->rules['*'];
            $this->group = $cache->fetch('acl_group');
        } else{
            $this->AUTH_ACL= array('*   @ALL    allow   *');
            $this->default = array('@ALL'=>array('allow'=>array('*')));
            $this->rules = null;
            $this->rule = null;
            $this->group = null;
        }

        $wikimasters=isset($DB->wikimasters) ? $DB->wikimasters:array();
        $owners=isset($DB->owners) ? $DB->owners:array();
        $this->allowed_users=array_merge($wikimasters,$owners);
    }

    function acl_ip_info(&$info) {
        // get group info.
        $matches = preg_grep('/^(@[^\s]+)\s+.*$/', $this->AUTH_ACL);
        foreach ($matches as $line) {
            $tmp = preg_split('/\s+/', rtrim($line), 2);
            $group = $tmp[0]; // group name

            $users = get_csv($tmp[1]);
            foreach ($users as $u) {
                if (preg_match('/^[0-9]{1,3}(\.(?:[0-9]{1,3})){0,3}
                    (\/([0-9]{1,3}(?:\.[0-9]{1,3}){3}|[0-9]{2}))?$/x', $u))
                {
                    if (!isset($info[$u])) $info[$u] = array(); // init
                    $info[$u][] = $group;
                }
            }
        }
    }

    function get_acl_group($user, $group = '') {
        if (empty($group))
            $group = '@[^\s]+';

        $groups = array();
        $gpriority = array(); // group priorities

        $ip_info = array(); // ip address based info
        if ($user != 'Anonymous') {
            $groups[]='@User';
        } else {
            $this->acl_ip_info($ip_info);
        }

        // has acl ip address info ?
        if (!empty($ip_info)) {
            $myip = ip2long($_SERVER['REMOTE_ADDR']);
            $mygrp = array();

            $rules = array_keys($ip_info);
   
            foreach ($rules as $rule) {
	        $ret = normalize_network($rule, true);
                if (!$ret) continue; // ignore

                $network = $ret[0];
                $netmask = $ret[1];

                if(($myip & $netmask) == ($network & $netmask)) {
                    $mygrp = array_merge($mygrp, $ip_info[$rule]);
                } else if ($myip == $network) {
                    $mygrp = array_merge($mygrp, $ip_info[$rule]);
                }
            }
            // group found ?
            if (!empty($mygrp))
                $groups = array_merge($groups, $mygrp);
        }

        $matches = preg_grep('/^('.$group.')\s+/', $this->AUTH_ACL);
        foreach ($matches as $line) {
            list($grp, $tmp) = preg_split('/\s+/', $line, 2);
            $tmp = preg_replace("/\s*,\s*/", ",", $tmp); // trim spaces: ' , ' => ','
            $tmp = rtrim($tmp);
            list($users, $priority) = preg_split("/\s+/", $tmp, 2);
            if (preg_match("/(^|.*,)$user(,.*|$)/", $users))
                $groups[] = $grp;

            if (!empty($priority) and is_numeric($priority)) $gpriority[$grp] = $priority; # set group priorities
            else $gpriority[$grp] = 2; # default group priority
        }

        $this->gpriority = $gpriority;
        return $groups;
    }

    // prepare and read all ACL files
    function read_acl($filename, $params = array()) {
        $fp = fopen($filename, 'r');
        if (!is_resource($fp)) return false;

        $rules = array();
        $acls = array();

        $group = '@ALL'; // default group
        while (($line = fgets($fp, 8192)) != false) {
            $line = rtrim($line);
            if (!isset($line[0])) continue;
            if ($line[0] == '#') continue;
            $line = preg_replace('@\s*#.*$@', '', $line); // trim out # comment

            if ($line[0] == '@') {
                // group
                $acls[] = $line;
                continue;
            } else if (preg_match('/^((?:Access|Include)File)\s+(.*)$/', $line, $m)) {
                // include file
                if (!file_exists($m[2]))
                    $m[2] = dirname($filename).'/'.$m[2];
                if (!file_exists($m[2])) {
                    trigger_error(sprintf(_("File not found %s"), $m[2]));
                    continue;
                }
                $readed = $this->read_acl($m[2], $params);
                if ($readed !== false) {
                    $deps = array($m[2]);
                    $params['deps'] = array_merge($params['deps'], $deps);
                    $acls[] = '# '.$m[2].' readed';
                    array_splice($acls, count($acls), 0, $readed);
                }
                continue;
            } else if (preg_match('/^(?:Access)(Rule|Group|List|ListFile)\s+(.*)$/', $line, $m)) {
                $type = strtolower($m[1]);

                $lists = array();
                if ($type == 'rule' && preg_match('@^(allow|deny|protect)\s+(.*)$@', $m[2], $mm)) {
                    $mm[2] = preg_replace('@\s*,\s*@', ',', $mm[2]);
                    if (empty($mm[2]))
                        $mm[2] = '*';

                    if (empty($rules[$mm[1]]))
                        $rules[$mm[1]] = array();

                    $tmp = explode(',' , $mm[2]);
                    $tmp = array_flip($tmp);
                    if (count($tmp) > 1)
                        unset($tmp['*']);
                    $acts = array_keys($tmp);
                    $merged = array_merge($rules[$mm[1]], $acts);
                    $merged = array_unique($merged);
                    sort($merged);
                    $rules[$mm[1]] = $merged;
                    continue;
                } else if ($type == 'group') {
                    $m[2] = preg_replace('@\s*,\s*@', ',', $m[2]);
                    if ($m[2] == '*' || empty($m[2]))
                        $m[2] = '@ALL';
                    $group = $m[2];
                    continue;
                } else if ($type == 'list') {
                    $m[2] = preg_replace('@\s*,\s*@', ',', $m[2]);
                    if (empty($m[2]))
                        $m[2] = '*';
                    $lists = array($m[2]);
                } else if ($type == 'listfile') {
                    // include file
                    if (!file_exists($m[2]))
                        $m[2] = dirname($filename).'/'.$m[2];
                    if (!file_exists($m[2])) {
                        trigger_error(sprintf(_("File not found %s"), $m[2]));
                        continue;
                    }

                    $lst = fopen($m[2], 'r');
                    if (!is_resource($lst))
                        continue;
                    $lists = array();
                    while (($l = fgets($lst, 1024)) !== false) {
                        $l = rtrim($l);
                        if (!isset($l[0])) continue;
                        if ($l[0] == '#') continue;
                        $l = preg_replace('@\s*#.*$@', '', $l); // trim out # comment
                        $lists[] = $l;
                    }
                    fclose($lst);

                    $deps = array($m[2]);
                    $params['deps'] = array_merge($params['deps'], $deps);
                }
                if (empty($lists))
                    continue;

                if (!empty($rules)) {
                    // save and reset $rules
                    $denied = $rules['deny'];
                    $allowed = $rules['allow'];
                    $protected = $rules['protect'];
                    $rules = array();
                }

                foreach ($lists as $item) {
                    $prefix = $item."\t".$group."\t";
                    if ($denied[0] == '*') {
                        $acls[] = $prefix.'deny'."\t*";
                        if (isset($allowed))
                            $acls[] = $prefix.'allow'."\t".implode(',', $allowed);

                        if (count($denied) > 1) {
                            // already allowed but denied again
                            $tmp = $denied;
                            array_shift($tmp);
                            $acls[] = $prefix.'deny'."\t".implode(',', $tmp);
                        }
                    } else if ($allowed[0] == '*') {
                        $acls[] = $prefix.'allow'."\t*";
                        if (isset($denied))
                            $acls[] = $prefix.'deny'."\t".implode(',', $denied);

                        if (count($allowed) > 1) {
                            // already denied but allowed again
                            $tmp = $allowed;
                            array_shift($tmp);
                            $acls[] = $prefix.'allow'."\t".implode(',', $tmp);
                        }
                    } else {
                        if (isset($allowed))
                            $acls[] = $prefix.'allow'."\t".implode(',', $allowed);
                    }
                    if (isset($protected))
                        $acls[] = $prefix.'protect'."\t".implode(',', $protected);
                }
            } else {
                $acls[] = $line;
            }
        }
        fclose($fp);
        return $acls;
    }


    function parse_acl($acl_lines) {
        $pages = array();
        $rules = array();
        $group = array();
        foreach ($acl_lines as $line) {
            $line = preg_replace('/\s*#.*$/', '', $line); # delete comments
            $line = rtrim($line);
            if (!isset($line[0]))
                continue;
            if (in_array($line[0], array('@', '#'))) {
                if ($line[0] == '@') {
                    $group[] = $line;
                }
                continue;
            }
            $line = preg_replace('@\s+(allow|protect|deny)\s+@', "\t$1\t", $line);
            if (!preg_match('/^(.+)\s+(.+)\s+(allow|protect|deny)\s+(.*)?$/', $line, $m))
                continue;
            // $m[1] : page name or page rule
            // $m[2] : groups
            // $m[3] : access type
            // $m[4] : actions

            if (empty($m[4]))
                $m[4] = '*';
            // make ACL info
            // array('@ALL'=>array('deny'=>array('deletepage',...)));
            $acl = array();
            $acl[$m[2]] = array();
            $acl[$m[2]][$m[3]] = explode(',', $m[4]);

            $prules = get_csv(trim($m[1]));
            // a regex or a simplified pattern like as
            // HelpOn* -> HelpOn.*
            // MoniWiki/* -> MoniWiki\/.*

            foreach ($prules as $prule) {
                $pre = '^';
                $post = '$';
                if ($prule[0] == '^' || substr($prule, -1) == '$' ||
                        strpos($prule, '*') !== false) {
                    // is it a regex or a simplified pattern ?
                    if ($prule != '*') {
                        // convert simple pattern to regex
                        // Hello* => ^Hell.*$
                        // *Hello* => ^.*Hell.*$
                        // *Hello$ => ^.*Hello$
                        $pre = '^';
                        $post = '$';
                        if ($prule[0] == '^') $pre = '';
                        if (substr($prule, -1) == '$') $post = '';
                        $tmp =
                            preg_replace(array('@(?:\.)?\*@', "@(?<!\\\\)/@"), array('.*', '\/'), $prule);
                        $prule = $pre.$tmp.$post;
                    }
                    // page rule
                    $entry = &$rules[$prule];
                    $this->merge_acl($entry, $acl);
                } else {
                    // normal page names
                    $entry = &$pages[$prule];
                    $this->merge_acl($entry, $acl);
                }
            }
        }
        return array($pages, $rules, $group);
    }

    function merge_acl(&$acls, $acl) {
        if (empty($acls)) {
            $acls = $acl;
            return;
        }

        // merge ACL array
        foreach ($acl as $g=>$entry) {
            // check ttl
            if (!empty($entry['ttl'])) {
                $ttl = $entry['ttl'];
                $mtime = $entry['mtime'];
                $ttl = $ttl - (time() - $mtime);
                // expired entry. ignore
                if ($ttl <= 0)
                    continue;
            }
            foreach ($entry as $k=>$v) {
                if (!is_array($v)) {
                    // ttl, mtime etc.
                    $acls[$g][$k] = $v;
                    continue;
                }

                // check '*' available
                $a = $acls[$g][$k][0];
                if ($a == '*') {
                    // fix for the following cases
                    // deny *
                    // allow a,b,c
                    // deny a
                    if ($k == 'deny') {
                        $acls[$g]['allow'] = array_diff($acls[$g]['allow'], $v);
                    } else if ($k == 'allow') {
                        $acls[$g]['deny'] = array_diff($acls[$g]['deny'], $v);
                    } else {
                        trigger_error(_("Parse error"));
                    }
                } else {
                    $tmp = array_merge((array)$acls[$g][$k], $v);
                    $acls[$g][$k] = array_unique($tmp);
                }
            }
        }
    }

    function get_page_acl($pagename, $params = array()) {
        $acl = $this->cache->fetch($pagename);
        $aux_acl = $this->aux_cache->fetch($pagename, 0, $params);
        if ($aux_acl === false) {
            return $acl;
        }
        $this->merge_acl($acl, $aux_acl);

        return $acl;
    }

    function add_page_acl($pagename, $acl, $params = array()) {
        // set TTL
        $ttl = 0;
        if (!empty($params['ttl'])) {
            $ttl = $params['ttl'];
        }
        if ($acl == null) {
            // delete all aux acls
            $this->aux_cache->remove($pagename);
        } else {
            $retval = array();
            $ret = array('retval'=>&$retval);
            // get current acl
            $acls = $this->aux_cache->fetch($pagename, 0, $ret);
            if ($acls !== false && $ttl == 0 && !empty($retval['ttl'])) {
                // update TTL
                $ttl = $retval['ttl'] - (time() - $retval['mtime']);
            }

            if (is_array($acl)) {
                // remove ACL entry selectively
                foreach ($acl as $g=>$v) {
                    if (isset($acls[$g]) && $v == null) {
                        unset($acls[$g]);
                        unset($acl[$g]);
                    } else if ($acl[$g]['allow'] && $acls[$g]['deny']) {
                        // acl entry found conflict each other.
                        // just reset aux ACL
                        unset($acls[$g]['deny']);
                    } else if ($acl[$g]['deny'] && $acls[$g]['allow']) {
                        // reset aux ACL
                        unset($acls[$g]['allow']);
                    }
                }
            }
            // merge acl
            if (!empty($acl))
                $this->merge_acl($acls, $acl);
            if (!empty($acls))
                $this->aux_cache->update($pagename, $acls, $ttl);
            else
                $this->aux_cache->remove($pagename);
        }
    }

    function get_acl($action='read',&$options) {
        if (in_array($options['id'],$this->allowed_users)) return true;
        global $DBInfo;

        $pagename = $options['page'];
        $user= $options['id'];

        $groups = $this->get_acl_group($user);
        // check groups in the user information.
        $u = &$DBInfo->user;
        if ($u->id == $options['id'] && !empty($u->groups)) {
            $groups = array_merge($groups, $u->groups);
        }
        $groups[] = '@ALL';
        $groups[] = '@Editor'; // current editor
        $groups[] = $user;
        $allow = array();
        $deny = array();
        $protect = array();

        $gpriority = $this->gpriority;

        // init acl
        $acls = array();
        // get default acl
        $acls['*'] = $this->default;

        // get page acl
        $acl = $this->get_page_acl($pagename);

        // check special pages
        // $special_pages = array('User:%ID%', 'SandBox:%ID%', ...);
        if ($acl === false && !empty($DBInfo->acl_specialpages)) {
            $trans = array('%ID%'=>$user); // translation table: %ID% - user ID
            foreach ($DBInfo->acl_specialpages as $special) {
                $specialtr = strtr($special, $trans);
                if ($pagename == $specialtr) {
                    $acl = $this->cache->fetch($special);
                    break;
                }
            }
        }

        // get page acl
        if ($acl !== false) {
            $acls[$pagename] = $acl;
        } else if (preg_match('/'.$this->rule.'/', $pagename, $m)) {
            for ($i = 1; $i < sizeof($m); $i++) {
                if (!empty($this->rules[$i])) {
                    $acls[''] = $this->rules[$i]; // regex pattern
                    break;
                }
            }
        }

        foreach ($acls as $key=>$acl) {
            $found = 0; // default ACL
            if ($key == '') // regex pattern
                $found = 5;
            else if ($key != '*') // exact matched page
                $found = 10;

            foreach ($groups as $group) {
                if (!isset($acl[$group]))
                    continue;

                $pri = 0;
                if ($group == '@ALL') $pri = 1;
                else if (!empty($gpriority[$group])) $pri = $gpriority[$group];
                else if ($group == $user) $pri = 4;
                else $pri = 2;
                $pri+= $found;

                $entry = $acl[$group];
                $types = array_keys($entry);

                foreach ($types as $type) {
                    $acts = $entry[$type];
                    $tmp = array_flip($acts);
                    foreach ($acts as $act) {
                        if ($type == 'allow') {
                            if (isset($allow[$act]) and $allow[$act] > $pri)
                                unset($tmp[$act]);
                            else
                                $tmp[$act] = $pri;
                            if (isset($deny[$act]) and $deny[$act] <= $pri)
                                unset($deny[$act]);
                        } else if ($type == 'deny') {
                            if (isset($deny[$act]) and $deny[$act] > $pri)
                                unset($tmp[$act]);
                            else
                                $tmp[$act] = $pri;
                            if (isset($allow[$act]) and $allow[$act] <= $pri)
                                unset($allow[$act]);
                        }
                    }
                    ${$type} = array_merge(${$type}, $tmp);
                }
            }
        }
        $protect = array_keys($protect);

        if (!empty($this->DB->acl_debug)) {
            ob_start();
            print "<h4>"._("ACL groups")."</h4>\n";
            print implode(',',$groups);
            print "\n";
            print "<h4>"._("Allowed ACL actions")."</h4>\n";
            foreach ($allow as $k=>$v)
                print $k." ($v),";
            print "\n";
            print "<h4>"._("Denied ACL actions")."</h4>\n";
            foreach ($deny as $k=>$v)
                print $k." ($v),";
            print "\n";
            print "<h4>"._("Protected ACL actions")."</h4>\n";
            print implode(',',$protect);
            $options['msg'].=ob_get_contents();
            ob_end_clean();
        }

        $this->_acl_ok = 1;
        $this->_allowed = $allow;
        $this->_denied = $deny;
        $this->_protected = $protect;
        return array($allow, $deny, $protect);
    }

    function get_acl_raw($action='read',&$options) {
        if (in_array($options['id'],$this->allowed_users)) return true;
        global $DBInfo;

        $pg=$options['page'];
        $user=$options['id'];

        $groups = $this->get_acl_group($user);
        // check groups in the user information.
        $u = &$DBInfo->user;
        if ($u->id == $options['id'] && !empty($u->groups)) {
            $groups = array_merge($groups, $u->groups);
        }
        $groups[]='@ALL';
        $groups[]=$user;
        $allowed=array();
        $denied=array();
        $protected=array();

        $gpriority = $this->gpriority;
        $gregex=implode('|',$groups);

        #get ACL info.
        #$matches= preg_grep('/^('.$pg.'|\*)\s+('.$gregex.')\s+/', $this->AUTH_ACL);
        $matches= preg_grep('/^[^#@].*\s+('.$gregex.')\s+/', $this->AUTH_ACL);
        if (count($matches)) {
            foreach ($matches as $rule) {
                #if (in_array($rule[0],array('@','#'))) continue;
                $rule= preg_replace('/#.*$/','',$rule); # delete comments
                $rule= rtrim($rule);

                $tmp = preg_match('/^(.*)\s+('.$gregex.')\s+(allow|protect|deny)\s*(.*)?$/i', $rule, $acl);
                if (!$tmp) continue;

                $found = 0;
                if (!$acl[4]) $acl[4]='*';
                if ($acl[1] != '*' and $acl[1] != $pg) {
                    $prules = get_csv($acl[1]);
                    // a regex or a simplified pattern like as
                    // HelpOn* -> HelpOn.*
                    // MoniWiki/* -> MoniWiki\/.*

                    foreach ($prules as $prule) {
                        if ($prule == $pg) {
                            $found = 10;
                            break;
                        } else {
                            $pre = '^';
                            $post = '$';
                            if ($prule[0] == '^') $pre = '';
                            if (substr($prule, -1) == '$') $post = '';

                            // is it a regex or a simplified pattern
                            $prule=
                                preg_replace(array('/(?:\.)?\*/',"/(?<!\\\\)\//"),array('.*','\/'),$prule);

                            if (@preg_match("/$pre$prule$post/", $pg)) {
                                $found = 5;
                                break;
                            }
                        }
                    }
                    if (!$found) continue;
                } else if ($acl[1] == $pg) {
                    $found = 10;
                }

                if ($acl[3] == 'allow') {
                    $tmp=explode(',',$acl[4]);
                    $tmp=array_flip($tmp);
                    if ($acl[2] == $user) $pri=4;
                    else if ($acl[2] == '@ALL') $pri=1;
                    else $pri= !empty($gpriority[$acl[2]]) ? $gpriority[$acl[2]]:2; # get group prio
                    $pri+= $found; // set explicitly
                    $keys=array_keys($tmp);
                    foreach ($keys as $t) {
                        if (isset($allowed[$t]) and $allowed[$t] > $pri)
                            unset($tmp[$t]);
                        else
                            $tmp[$t]=$pri;
                        if (isset($denied[$t]) and $denied[$t] <= $pri)
                            unset($denied[$t]);
                    }
                    $allowed=array_merge($allowed,$tmp);
                } else if ($acl[3] == 'deny') {
                    $tmp=explode(',',$acl[4]);
                    $tmp=array_flip($tmp);
                    if ($acl[2] == $user) $pri=4;
                    else if ($acl[2] == '@ALL') $pri=1;
                    else $pri= $gpriority[$acl[2]] ? $gpriority[$acl[2]]:2; # set group prio
                    $pri+= $found; // set explicitly
                    $keys=array_keys($tmp);
                    foreach ($keys as $t) {
                        if (isset($denied[$t]) and $denied[$t] > $pri)
                            unset($tmp[$t]);
                        else
                            $tmp[$t]=$pri;
                        if (isset($allowed[$t]) and $allowed[$t] <= $pri)
                            unset($allowed[$t]);
                    }
                    $denied=array_merge($denied,$tmp);
                } else if ($acl[3] == 'protect') {
                    $tmp=explode(',',$acl[4]);
                    $tmp=array_flip($tmp);
                    $protected=array_merge($protected,$tmp);
                }
            }
        }
        $protected=array_keys($protected);

        if (!empty($this->DB->acl_debug)) {
            ob_start();
            print "<h4>"._("ACL groups")."</h4>\n";
            print implode(',',$groups);
            print "\n";
            print "<h4>"._("Allowed ACL actions")."</h4>\n";
            foreach ($allowed as $k=>$v)
                print $k." ($v),";
            #print_r($allowed);
            print "\n";
            print "<h4>"._("Denied ACL actions")."</h4>\n";
            foreach ($denied as $k=>$v)
                print $k." ($v),";
            #print_r($denied);
            print "\n";
            print "<h4>"._("Protected ACL actions")."</h4>\n";
            print implode(',',$protected);
            $options['msg'].=ob_get_contents();
            ob_end_clean();
        }
        $this->_acl_ok=1;
        $this->_allowed=$allowed;
        $this->_denied=$denied;
        $this->_protected=$protected;
        return array($allowed,$denied,$protected);
    }

    function acl_check($action='read',&$options) {
        # check allow first
        $allowed=&$this->_allowed;
        $denied=&$this->_denied;

        if (!empty($options['explicit'])) {
            if (isset($allowed[$action])) return 1;
            else if (isset($denied[$action])) return 0;
            return false;
        }

        if (isset($denied['*'])) {
            if (isset($allowed[$action])) {
                if ($allowed[$action] >= $denied['*']) return 1;
                return 0;
            }
            return 0;
        }
        if (isset($allowed['*'])) {
            if (isset($denied[$action])) {
                if ($denied[$action] >= $allowed['*']) return 0;
                return 1;
            }
            return 1;
        }
        if ($allowed[$action] >= $denied[$action]) return 1;
        return 0;
        #if (isset($allowed[$action]) and isset($denied[$action])) {
        #    if ($allowed[$action] >= $denied[$action]) return 1;
        #    return 0;
        #}
        #if (isset($allowed[$action])) return 1;
        #if (isset($denied[$action])) return 0;
        #return 1; # default is allow
    }

    function is_allowed($action="read",&$options) {
        # basic allowed actions
        $action=strtolower($action);
        $action=strtr($action,'-','/'); # for myaction/macro or myaction/ajax
        if (!$this->_acl_ok) $this->get_acl($action,$options); # get acl info

        $ret=$this->acl_check($action,$options);
        if ($ret == 0) {
            if ($action == 'download' and
                    preg_match('/\.(gif|png|jpg|jpeg)$/i',$options['value'])) {
                if ($this->DB->default_download_image)
                    $options['value']=$this->DB->default_download_image;
                return 1;
            }
            $options['err']=sprintf(_("You are not allowed to '%s' on this page"),$action);

            if ($options['id'] == 'Anonymous') {
                $args = array('id'=>'@User', 'page'=>$options['page']);
                $this->get_acl($action, $args);
                $ret2 = $this->acl_check($action, $args);
                if ($ret2 != 0) {
                    $options['err'].="\n"._("Please Login to this Wiki.");
                } else {
                    $options['err'].="\n"._("Please contact WikiMasters");
                }
            } else {
              $options['err'].="\n"._("Please contact WikiMasters");
            }
        }
        return $ret;
    }

    function is_protected($action="read",$options) {
        # password protected POST actions
        $action=strtolower($action);
        $action=strtr($action,'-','/'); # for myaction/macro or myaction/ajax
        if (!$this->_acl_ok) $this->get_acl($action,$options); # get acl info

        if (in_array($action,$this->_protected)) return 1;

        $orig = parent::is_protected($action, $options);
        if ($orig) { // check explicitly
            $options['explicit'] = 1;
            $ret=$this->acl_check($action,$options);
            if ($ret === false) {
                // no explicitly protected action found. check again
                $options['explicit'] = 0;
                $ret = $this->acl_check($action,$options);
            }
            // allow => not protected, deny => protected
            return !$ret;
        }
        return 0;
    }
}

// vim:et:sts=4:sw=4:
?>
