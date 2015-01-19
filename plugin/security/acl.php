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

require_once('lib/checkip.php');

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

        if(is_readable($acl_file)) {
            $this->AUTH_ACL= file($acl_file);
        } else{
            $this->AUTH_ACL= array('*   @ALL    allow   *');
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

    function get_acl($action='read',&$options) {
        if (in_array($options['id'],$this->allowed_users)) return 1;
        $pg=$options['page'];
        $user=$options['id'];

        $groups=array();
        $groups[]='@ALL';

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
	        $ret = normalize_network($rule);
                if (!$ret) continue; // ignore

                $network = $ret[0];
                $netmask = $ret[1];
                #print $network . '/' . $netmask . "\n";
                if (is_int($netmask)) {
                    $netmask = 0xffffffff << (32 - $netmask);
                } else {
                    $netmask = ip2long($netmask);
                }
                $network = ip2long($network);

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
        $groups[]=$user;
        $allowed=array();
        $denied=array();
        $protected=array();

        $gpriority=array(); # group priorities

        #get group info.
        $matches= preg_grep('/^(@[^\s]+)\s+(.*,?'.$user.',?.*)/', $this->AUTH_ACL);
        foreach ($matches as $line) {
            list($grp, $tmp) = preg_split('/\s+/', $line, 2);
            $tmp = preg_replace("/\s*,\s*/", ",", $tmp); // trim spaces: ' , ' => ','
            list($users, $priority) = preg_split("/\s+/", $tmp, 2);
            if (!preg_match("/(^|.*,)$user(,.*|$)/", $users))
                continue;

            $groups[] = $grp;
            if (!empty($priority) and is_numeric($priority)) $gpriority[$grp] = $priority; # set group priorities
            else $gpriority[$grp] = 2; # default group priority
        }

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

                if (!$acl[4]) $acl[4]='*';
                if ($acl[1] != '*' and $acl[1] != $pg) {
                    $prules = get_csv($acl[1]);
                    // a regex or a simplified pattern like as
                    // HelpOn* -> HelpOn.*
                    // MoniWiki/* -> MoniWiki\/.*

                    $found = false;
                    foreach ($prules as $prule) {
                        if ($prule == $pg) {
                            $found = true;
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
                                $found = true;
                                break;
                            }
                        }
                    }
                    if (!$found) continue;
                }

                if ($acl[3] == 'allow') {
                    $tmp=explode(',',$acl[4]);
                    $tmp=array_flip($tmp);
                    if ($acl[2] == $user) $pri=4;
                    else if ($acl[2] == '@ALL') $pri=1;
                    else $pri= !empty($gpriority[$acl[2]]) ? $gpriority[$acl[2]]:2; # get group prio
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
            if ($ret === false) return 1;
            // allow => not protected, deny => protected
            return !$ret;
        }
        return 0;
    }
}

// vim:et:sts=4:sw=4:
?>
