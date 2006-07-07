<?php
# a simple ACL security plugin for the MoniWiki
# $Id$
#
# Please see also http://www.dokuwiki.org/wiki:discussion:acl2
#
# ACL file example:
# <?php // just for hide contents
# *     @ALL    allow   *                   # allow all actions
# *     Anonymous   deny    edit,diff,info  # deny some actions for Anonymous
# MoniWiki  @ALL    deny    uploadfile,uploadedfiles,edit
# ACL   @ALL        deny    edit,diff,info

class Security_ACL extends Security {
    var $DB;

    function Security_ACL($DB="") {
        $this->DB=$DB;
        //load ACL into a global array
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

    function acl_check($action='read',&$options,$groups=array()) {
        if (in_array($options['id'],$this->allowed_users)) return 1;
        $pg=$this->DB->_getPageKey($options['page']);
        $user=$options['id'];

        $groups[]='@ALL';
        $groups[]=$user;
        $allowed=array();
        $denied=array();

        $matches= preg_grep('/^(@[^\s]+)\s+(.*,?'.$user.'|,?.*)/', $this->AUTH_ACL);
        foreach ($matches as $line) {
            $groups[]=strtok($line," \t");
        }

        $gregex=implode('|',$groups);

        $matches= preg_grep('/^('.$pg.'|\*)\s+('.$gregex.')\s+/', $this->AUTH_ACL);
        if (count($matches)) {
            foreach ($matches as $rule) {
                if ($rule[0] == '@') continue; # group definition XXX
                $rule = rtrim($rule);
                $rule = preg_replace('/#.*$/','',$rule); # delete comments
                $acl = preg_split('/\s+/',$rule,4);

                if ($acl[2] == 'allow') {
                    $allowed=array_merge($allowed,split(',',$acl[3]));
                    if ($acl[1] == $user and $acl[3] == '*') {
                        $s=array_search('*',$denied);
                        unset($denied[$s]);
                    }
                } else if ($acl[2] == 'deny') {
                    $denied=array_merge($denied,split(',',$acl[3]));
                    if ($acl[1] == $user and $acl[3] == '*') {
                        $s=array_search('*',$allowed);
                        unset($allowed[$s]);
                    }
                }
            }
        }
        if ($this->DB->acl_debug) {
            ob_start();
            print '<pre>';
            print "*** groups\n";
            print_r($groups);
            print "*** matches\n";
            print_r($matches);
            print "*** allowed\n";
            print_r($allowed);
            print "*** denied\n";
            print_r($denied);
            print '</pre>';
            $options['msg']=ob_get_contents();
            ob_end_clean();
        }

        if ($options['aclinfo']) return array($allowed,$denied);

        if ((($p=in_array('*',$allowed)) or in_array($action,$allowed)) and
            !(in_array($action,$denied) and ($p and !in_array('*',$denied))))
            return 1;
        return 0;
    }

    function is_allowed($action="read",&$options) {
        # basic allowed actions
        $action=strtolower($action);
        $ret=$this->acl_check($action,$options);
        if ($ret == 0) {
            $options['err']=sprintf(_("You are not allowed to '%s' on this page"),$action);
            $options['err'].="\n"._("Please contact WikiMasters :b");
        }
        return $ret;
    }
}

// vim:et:sts=4:
?>
