<?php
# a auth basic security plugin for the MoniWiki
# $Id: authbasic.php,v 1.2 2010/08/26 02:56:53 wkpark Exp $

class Security_authbasic extends Security_base {
    var $DB;

    function Security_authbasic($DB="") {
        $this->DB=$DB;
    }

    function writable($options="") {
        return $this->DB->_isWritable($options['page']);
    }

    function is_allowed($action="read",&$options) {
        # basic allowed actions
        $this->custom=0;
        $allowed_actions=array("savepage","read","raw","info",
            "likepages","uploadedfiles",
            "css","theme","userform","fixmoin");
        $action=strtolower($action);
        if (!$action) return 1;
        if (in_array($action,$allowed_actions)) return 1;

        if ($this->checkAuth($action,$options)==1) return 1;
        $options['custom']='basicAuth';
        $this->custom=1;

        return 0;
    }

    function checkAuth($action,&$options) {
        if ($action=='login' or $action=='logout') {
            $options['custom']='basicAuth';
            unset($_SERVER['PHP_AUTH_USER']);
            unset($_SERVER['PHP_AUTH_PW']);
            return 0;
        }
        if (isset($_SERVER['PHP_AUTH_USER']) and $_SERVER['PHP_AUTH_PW']) {
            $id=$_SERVER['PHP_AUTH_USER'];
            $userdb=new UserDB($this->DB);
            $user=new WikiUser(); # get from COOKIE VARS
            if ($user->id == $id) return 1;

            if ($userdb->_exists($id)) {
                $user=$userdb->getUser($id);
                # check password
                if ($user->checkPasswd($_SERVER['PHP_AUTH_PW'])=== true) {
                    $dummy=$user->setCookie();
	            $dummy=$userdb->saveUser($user);
                    return 1;
                }
            }
        }
        unset($_SERVER['PHP_AUTH_USER']);
        unset($_SERVER['PHP_AUTH_PW']);
        return 0;
    }

    function basicAuth($formatter,$options) {
        global $DBInfo;

        $realm=$DBInfo->realm ? $DBInfo->realm:$DBInfo->sitename;
        header('WWW-Authenticate: Basic realm="'.$realm.'"',false);
        header("Status: 401 Unauthorized");
        header("HTTP-Status: 401 Unauthorized");
        $options['title']=sprintf(_("You have no permission to '%s'."),$options['action']);
        $formatter->send_header('',$options);
        $formatter->send_title('','',$options);
        $formatter->send_page(_("You must enter a valid login ID and password to access this resource.\n"));
        $formatter->send_footer('',$options);
        flush();
        return;
    }
}

// vim:et:sts=4:
?>
