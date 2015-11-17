<?php
// Copyright 2013-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a xe user plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Since: 2013-07-04
// Modified: 2015-11-04
// Name: XE 1.7,1.8 User plugin
// Description: XE 1.7 user plugin
// URL: MoniWiki:XeUserPlugin
// Version: $Revision: 1.5 $
// License: GPL
//
// Param: xe_root_dir='/home/path_to_the_root_of_installed_xe/'; # default xe root path
// Param: xe_allowed_groups=array(1=>'@staff',...); # allowed xe groups
// Usage: set $user_class = 'xe17'; in the config.php
//

class User_xe17 extends WikiUser {

    function xe_context_init($xe) {
        //
        // simplified XE context init method to speed up
        //

        // set context variables in $GLOBALS (to use in display handler)
        $xe->context = &$GLOBALS['__Context__'];
        $xe->context->_COOKIE = $_COOKIE;

        $xe->loadDBInfo();

        // set session handler
        if (Context::isInstalled() && $this->db_info->use_db_session == 'Y') {
            $oSessionModel = getModel('session');
            $oSessionController = getController('session');
            session_set_save_handler(
                    array(&$oSessionController, 'open'),
                    array(&$oSessionController, 'close'),
                    array(&$oSessionModel, 'read'),
                    array(&$oSessionController, 'write'),
                    array(&$oSessionController, 'destroy'),
                    array(&$oSessionController, 'gc')
           );
        }
    }

    function User_xe17($id = '') {
        global $Config;

        parent::WikiUser($id);

        $cookie_id = $this->id != 'Anonymous' ? $this->id : '';

        // set xe_root_dir config option
        $xe_root_dir = !empty($Config['xe_root_dir']) ?
                $Config['xe_root_dir'] : dirname(__FILE__).'/../../../xe';
        // default xe_root_dir is 'xe' subdirectory of the parent dir of the moniwiki

        $sessid = session_name(); // PHPSESSID
        // set the session_id() using saved cookie
        if (isset($_COOKIE[$sessid])) {
            session_id($_COOKIE[$sessid]);
        }

        // do not use cookies for varnish cache server
        ini_set("session.use_cookies", 0);
        session_cache_limiter(''); // Cache-Control manually for varnish cache
        session_start();

        // is it a valid user ?
        $udb = new UserDB($Config);
        $user = $udb->getUser($cookie_id);

        $update = false;
        if (!empty($cookie_id)) {
            // not found
            if ($user->id == 'Anonymous') {
                $this->setID('Anonymous');
                $update = true;
                $cookie_id = '';
            } else {
                // check ticket
                $ticket = getTicket($user->id, $_SERVER['REMOTE_ADDR']);
                if ($this->ticket != $ticket) {
                    // not a valid user
                    $this->ticket = '';
                    $this->setID('Anonymous');
                    $update = true;
                    //$cookie_id = '';
                } else {
                    // OK good user
                    $this->setID($cookie_id);
                    $id = $cookie_id;
                    $this->nick = $user->info['nick'];
                    $this->tz_offset = $user->info['tz_offset'];
                    $this->info = $user->info;
                    $this->ticket = $ticket;
                }
            }
        } else {
            // empty cookie
            $update = true;
        }

        if ($update && !empty($_SESSION['is_logged'])) {
            // init XE17, XE18
            define('__XE__', true);

            require_once($xe_root_dir."/config/config.inc.php");

            $context = &Context::getInstance();
            $this->xe_context_init($context); // simplified init context method
            // $context->init(); // slow slow

            $oMemberModel = &getModel('member');
            $oMemberController = &getController('member');

            $oMemberController->setSessionInfo();
            $member = new memberModel();
            $xeinfo = $member->getLoggedInfo();

            $id = $xeinfo->user_id;
            $user = $udb->getUser($id); // get user info again

            // not a registered user ?
            if ($user->id == 'Anonymous' || $update || empty($user->info['nick'])) {
                // check groups
                $groups = array_keys($xeinfo->group_list);
                $wikigroups = array();
                $group_ok = $xeinfo->is_admin == 'Y' ? true : false;
                if (!empty($Config['xe_allowed_groups'])) {
                    $allowed_groups = $Config['xe_allowed_groups'];
                    for ($i = 0; $i < sizeof($groups); $i++) {
                        if (isset($allowed_groups[$groups[$i]])) {
                            $group_ok = true;
                            $groupname = $allowed_groups[$groups[$i]];
                            if (!empty($groupname)) {
                                $wikigroups[] = $groupname;
                            }
                        }
                    }
                } else {
                    $group_ok = true;
                }

                if ($group_ok) {
                    if (!empty($wikigroups)) {
                        $this->groups = $wikigroups;
                        $user->info['groups'] = implode(',', $wikigroups);
                        $this->info['groups'] = $user->info['groups'];
                    } else if (!empty($this->info['groups'])) {
                        $user->info['groups'] = '@User';
                        $this->info['groups'] = $user->info['groups'];
                    }

                    $this->setID($id); // not found case
                    $this->info = $user->info; // already registered case
                    $ticket = getTicket($id, $_SERVER['REMOTE_ADDR']); // get ticket

                    if ($this->nick != $xeinfo->nick_name) {
                        $this->nick = $xeinfo->nick_name;
                        $this->info['nick'] = $xeinfo->nick_name;
                    }
                    if ($this->info['email'] == '')
                        $this->info['email'] = $xeinfo->email_address;
                    $this->info['tz_offset'] = $this->tz_offset;
                    $this->ticket = $ticket;
                } else {
                    if (!empty($cookie_id))
                        header($this->unsetCookie());
                    $this->setID('Anonymous');
                    $id = 'Anonymous';
                }
            }
        } else {
            // not logged in
            if (empty($_SESSION['is_logged'])) {
                if (!empty($cookie_id))
                    header($this->unsetCookie());
                $this->setID('Anonymous');
                $id = 'Anonymous';
            }
        }

        if ($update || !empty($id) and $id != 'Anonymous') {
            if ($cookie_id != $id)
                header($this->setCookie());
        }

        if ($update || !$udb->_exists($id)) {
            if (!$udb->_exists($id)) {
                if (!empty($Config['use_agreement']) && empty($this->info['join_agreement'])) {
                    $this->info['join_agreement'] = 'disagree';
                }
            }
            // automatically save/register user
            $dummy = $udb->saveUser($this);
        }
    }

    function login($formatter, $params) {
        global $Config;

        @session_start(); // confirm session start

        // set xe_root_dir config option
        $xe_root_dir = !empty($Config['xe_root_dir']) ?
                $Config['xe_root_dir'] : dirname(__FILE__).'/../../../xe';
        $xe_root_url = !empty($Config['xe_root_url']) ?
                $Config['xe_root_url'] : '/xe';
        // default xe_root_dir is 'xe' subdirectory of the parent dir of the moniwiki

        // init XE17, XE18
        define('__XE__', true);

        require_once($xe_root_dir."/config/config.inc.php");

        // setup post params
        $post_params = array();
        $login_path = $xe_root_url.'/index.php';
        $post_params['user_id'] = $params['login_id'];
        $post_params['password'] = $params['password'];
        $post_params['act'] = 'procMemberLogin';

        // setup post url
        $port = $_SERVER['SERVER_PORT'] != 80 ? ':'.$_SERVER['SERVER_PORT'] : '';
        $http = 'http' . ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']=='on') ? 's' : '') . '://';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];
        if(isset($_SERVER['HTTP_HOST']) && preg_match('/:[0-9]+$/', $host))
            $host = preg_replace('/:[0-9]+$/', '', $host);
        $login_path = $http.$host.$port.$login_path;

        $_SERVER['SCRIPT_NAME'] = $xe_root_url.'/index.php';
        require_once dirname(__FILE__)."/../../lib/HTTPClient.php";
        $http = new HTTPClient();
        $http->cookie = $_COOKIE; // set current cookies

        $http->max_redirect = 0; // do not redirect
        $http->post($login_path, $post_params);
        if(isset($http->resp_headers['set-cookie'])){
            foreach ((array) $http->resp_headers['set-cookie'] as $c){
                header('Set-Cookie: '.$c, false);
            }
        }
    }
}

// vim:et:sts=4:sw=4:
