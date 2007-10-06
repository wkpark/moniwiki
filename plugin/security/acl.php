<?php
# a ACL security plugin for the MoniWiki (experimental)
# $Id$
#
# Please see also http://www.dokuwiki.org/wiki:discussion:acl2
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

class Security_ACL extends Security {
    var $DB;

    var $_acl_ok=0;
    var $_protected=array();
    function Security_ACL($DB="") {
        $this->DB=$DB;
        # load ACL
        define(_CURRENT,dirname(__FILE__));
        if ($DB->acl_type and file_exists(_CURRENT.'/../../config/acl.'.$DB->acl_type.'.php'))
            $acl_file=_CURRENT.'/../../config/acl.'.$DB->acl_type.'.php';
        else
            $acl_file=_CURRENT.'/../../config/acl.default.php';

        if(is_readable($acl_file)) {
            $this->AUTH_ACL= file($acl_file);
        } else{
            $this->AUTH_ACL= array('*   @ALL    allow   *');
        }

        $this->allowed_users=array_merge($DB->wikimasters,$DB->owners);
    }

    function get_acl($action='read',&$options) {
        if (in_array($options['id'],$this->allowed_users)) return 1;
        $pg=$options['page'];
        $user=$options['id'];

        $groups=array();
        $groups[]='@ALL';
        if ($user != 'Anonymous') $groups[]='@User';
        $groups[]=$user;
        $allowed=array();
        $denied=array();
        $protected=array();

        $gpriority=array(); # group priorities

        #get group info.
        $matches= preg_grep('/^(@[^\s]+)\s+(.*,?'.$user.',?.*)/', $this->AUTH_ACL);
        foreach ($matches as $line) {
            $grp=preg_split('/\s+/',$line);
            $groups[]=$grp[0];
            if ($grp[2]) $gpriority[$grp[0]]=$grp[2]; # set group priorities
            else $gpriority[$grp[0]]=2; # default group priority
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
                $acl= preg_split('/\s+/',$rule,4);
                if (!$acl[3]) $acl[3]='*';
                if ($acl[0] != '*') {
                    $prule=$acl[0];
                    // HelpOn* -> HelpOn.*
                    // MoniWiki/* -> MoniWiki\/.*
                    $prule=
                       preg_replace(array('/(?!<\.)\*/',"/(?<!\\\\)\//"),array('.*','\/'),$prule);
                    if (false === @preg_match("/$prule/",'')) continue;
                    if (!preg_match("/$prule/",$pg)) continue;
                }

                if ($acl[2] == 'allow') {
                    $tmp=split(',',$acl[3]);
                    $tmp=array_flip($tmp);
                    if ($acl[1] == $user) $pri=4;
                    else if ($acl[1] == '@ALL') $pri=1;
                    else $pri= $gpriority[$acl[1]] ? $gpriority[$acl[1]]:2; # get group prio
                    $keys=array_keys($tmp);
                    foreach ($keys as $t) {
                        if (isset($allowed[$t]) and $allowed[$t] > $pri)
                            unset($tmp[$t]);
                        else
                            $tmp[$t]=$pri;
                    }

                    $allowed=array_merge($allowed,$tmp);
                    if ($acl[3]=='*' and isset($denied['*'])) {
                        if ($allowed['*']>=$denied['*']) unset($denied['*']);
                        else $unset($allowed['*']);
                    }
                } else if ($acl[2] == 'deny') {
                    $tmp=split(',',$acl[3]);
                    $tmp=array_flip($tmp);
                    if ($acl[1] == $user) $pri=4;
                    else if ($acl[1] == '@ALL') $pri=1;
                    else $pri= $gpriority[$acl[1]] ? $gpriority[$acl[1]]:2; # set group prio
                    $keys=array_keys($tmp);
                    foreach ($keys as $t) {
                        if (isset($denied[$t]) and $denied[$t] > $pri)
                            unset($tmp[$t]);
                        else
                            $tmp[$t]=$pri;
                    }
                    $denied=array_merge($denied,$tmp);
                    if ($acl[3]=='*' and isset($allowed['*'])) {
                        if ($allowed['*']<=$denied['*']) unset($allowed['*']);
                        else unset($denied['*']);
                    }
                } else if ($acl[2] == 'protect') {
                    $tmp=split(',',$acl[3]);
                    $tmp=array_flip($tmp);
                    $protected=array_merge($protected,$tmp);
                }
            }
        }
        $protected=array_keys($protected);

        if ($this->DB->acl_debug) {
            ob_start();
            print "<h4>groups</h4>\n";
            print implode(',',$groups);
            print "\n";
            print "<h4>Allowed actions</h4>\n";
            foreach ($allowed as $k=>$v)
                print $k." ($v),";
            #print_r($allowed);
            print "\n";
            print "<h4>Denied actions</h4>\n";
            foreach ($denied as $k=>$v)
                print $k." ($v),";
            #print_r($denied);
            print "\n";
            print "<h4>Protected actions</h4>\n";
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

        if ($options['explicit']) {
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
            $options['err']=sprintf(_("You are not allowed to '%s' on this page"),$action);
            $options['err'].="\n"._("Please contact WikiMasters :b");
        }
        return $ret;
    }

    function is_protected($action="read",$options) {
        # password protected POST actions
        $action=strtolower($action);
        $action=strtr($action,'-','/'); # for myaction/macro or myaction/ajax
        if (!$this->_acl_ok) $this->get_acl($action,$options); # get acl info

        if (in_array($action,$this->_protected)) return 1;
        return 0;
    }
}

// vim:et:sts=4:
?>
