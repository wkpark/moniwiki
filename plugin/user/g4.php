<?php
// Copyright 2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a GnuBoard4 user plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Since: 2015-04-29
// Date: 2015-05-07
// Name: GnuBoard4 User plugin
// Description: GnuBoard4 user plugin
// URL: MoniWiki:GnuBoard4UserPlugin
// Version: $Revision: 1.6 $
// License: GPL
//
// Param: g4_root_dir = '/home/path_to_g4_installed_dir/';
// Usage: set $user_class = 'g4'; in the config.php
//

class User_g4 extends WikiUser {

    var $g4_root_dir;

    function g4_init() {
        global $g4, $member, $g4_root_dir;

        require_once('g4.common.php');
        $member = g4_get_member();
    }

    function User_g4($id = '') {
        global $DBInfo;
        global $g4, $member, $g4_root_dir;

        parent::WikiUser($id);
        if ($this->id == 'Anonymous')
            return;

        $cookie_id = $this->id;

        // setup GnuBoard
        $g4_root_dir = !empty($DBInfo->g4_root_dir) ?
                $DBInfo->g4_root_dir : __DIR__.'/../../../gb4';
        $g4_root_url = !empty($DBInfo->g4_root_url) ?
                $DBInfo->g4_root_url : '/gb4';

        $g5_path = array();
        $g5_path['path'] = realpath($g4_root_dir);
        $g5_path['url'] = $g4_root_url;
        include_once("$g4_root_dir/config.php"); // g4 config file

        ini_set("url_rewriter.tags", "");
        // session settings
        session_save_path("$g4_root_dir/data/session");
        ini_set("session.use_trans_sid", 1); // default
        //ini_set("session.cache_expire", 180); //default
        //ini_set("session.gc_probability", 1); // default
        //ini_set("session.gc_divisor", 100); // default

        session_set_cookie_params(0, "/");
        if (defined('G5_VERSION'))
            ini_set("session.cookie_domain", G5_COOKIE_DOMAIN);
        else
            ini_set("session.cookie_domain", $g4['cookie_domain']);
        // do not use cookies for varnish cache server
        ini_set("session.use_cookies", 0);

        // set the session_id() using saved cookie
        if (isset($_COOKIE['PHPSESSID']))
            session_id($_COOKIE['PHPSESSID']);

        session_cache_limiter(''); // Cache-Control manually for varnish cachie
        session_start();


        $udb = new UserDB($DBInfo);
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
                    $cookie_id = '';
                } else {
                    // OK good user
                    $this->setID($cookie_id);
                    $id = $cookie_id;
                    $this->nick = $user->info['nick'];
                    $this->tz_offset = $user->info['tz_offset'];
                    $this->info = $user->info;
                }
            }
        } else {
            $update = true;
        }

        if ($update && !empty($_SESSION['ss_mb_id'])) {
            // init G4
            $this->g4_init();

            if (!empty($member['mb_id'])) {
                $id = $member['mb_id'];
                $user = $udb->getUser($id); // get user info again

                // not a registered user ?
                if ($user->id == 'Anonymous' || $update || empty($user->info['nick'])) {
                    $this->setID($id); // not found case
                    $this->info = $user->info; // already registered case

                    if (isset($member['mb_nick']) and $this->nick != $member['mb_nick']) {
                        // G4
                        $this->info['nick'] = $member['mb_nick'];
                        $this->nick = $member['mb_nick'];
                    } else if (isset($member['nick']) and $this->nick != $member['nick']) {
                        // G5
                        $this->info['nick'] = $member['nick'];
                        $this->nick = $member['nick'];
                    }
                    if ($this->info['email'] == '')
                        $this->info['email'] = $member['mb_email'];
                    $this->info['tz_offset'] = $this->tz_offset;
                }
            }
        } else {
            // not logged in
            if (empty($_SESSION['ss_mb_id'])) {
                if (!empty($cookie_id))
                    header($this->unsetCookie());
                $this->setID('Anonymous');
                $id = 'Anonymous';
            }
        }

        // update timezone
        if ($this->tz_offset != $user->info['tz_offset']) {
            $this->info['tz_offset'] = $this->tz_offset;
            $update = true;
        }

        if ($update || !empty($id) and $id != 'Anonymous') {
            if ($cookie_id != $id)
                header($this->setCookie());
        }

        if ($update || !$udb->_exists($id)) {
            if (!$udb->_exists($id)) {
                if (!empty($DBInfo->use_agreement) && empty($this->info['join_agreement'])) {
                    $this->info['join_agreement'] = 'disagree';
                }
            }
            // automatically save/register user
            $dummy = $udb->saveUser($this);
        }
    }

    function login($formatter, $params) {
        global $DBInfo;
        global $g4, $g4_root_dir;

        $g4_root_dir = !empty($DBInfo->g4_root_dir) ?
                $DBInfo->g4_root_dir : __DIR__.'/../../../gb4';
        $g4_root_url = !empty($DBInfo->g4_root_url) ?
                $DBInfo->g4_root_url : '/gb4';

        include_once("$g4_root_dir/config.php"); // g4 config file

        if (!defined('G5_VERSION')) {
            include_once("$g4_root_dir/lib/constant.php");  // constants
        }
        //include_once("$g4_root_dir/lib/common.lib.php"); // common library

        $post_params = array();
        if (defined('G5_VERSION')) {
            $login_path = G5_BBS_URL.'/login_check.php';
        } else {
            $login_path = $g4_root_url.$g4['bbs_path'].'/login_check.php';
        }

        // set post parameters
        $post_params['mb_id'] = $params['login_id'];
        $post_params['mb_password'] = $params['password'];

        // setup post url
        $port = $_SERVER['SERVER_PORT'] != 80 ? ':'.$_SERVER['SERVER_PORT'] : '';
        $http = 'http' . ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']=='on') ? 's' : '') . '://';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];
        if(isset($_SERVER['HTTP_HOST']) && preg_match('/:[0-9]+$/', $host))
            $host = preg_replace('/:[0-9]+$/', '', $host);
        $login_path = $http.$host.$port.$login_path;

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
