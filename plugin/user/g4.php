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

        $g4_root_dir = !empty($DBInfo->g4_root_dir) ?
                $DBInfo->g4_root_dir : __DIR__.'/../../../gb4';

        $g5_path = array();
        $g5_path['path'] = realpath($g4_root_dir);
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

        // for Anonymous users
        $this->css = isset($_COOKIE['MONI_CSS']) ? $_COOKIE['MONI_CSS'] : '';
        $this->theme = isset($_COOKIE['MONI_THEME']) ? $_COOKIE['MONI_THEME'] : '';
        $this->bookmark = isset($_COOKIE['MONI_BOOKMARK']) ? $_COOKIE['MONI_BOOKMARK'] : '';
        $this->trail = isset($_COOKIE['MONI_TRAIL']) ? _stripslashes($_COOKIE['MONI_TRAIL']) : '';
        $this->tz_offset = isset($_COOKIE['MONI_TZ']) ?_stripslashes($_COOKIE['MONI_TZ']) : '';
        $this->nick = isset($_COOKIE['MONI_NICK']) ?_stripslashes($_COOKIE['MONI_NICK']) : '';
        if ($this->tz_offset == '') $this->tz_offset = date('Z');

        $cookie_id = '';
        // get the current Cookie vals
        if (isset($_COOKIE['MONI_ID'])) {
     	    $this->ticket = substr($_COOKIE['MONI_ID'], 0, 32);
     	    $cookie_id = urldecode(substr($_COOKIE['MONI_ID'], 33));
        }

        $udb = new UserDB($DBInfo);
        $user = $udb->getUser(!empty($cookie_id) ? $cookie_id : 'Anonymous');

        $update = false;
        if (!empty($cookie_id)) {
            // not found
            if ($user->id == 'Anonymous') {
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

                // not a registered user ?
                if ($user->id == 'Anonymous' || $update || empty($user->info['nick'])) {
                    $this->setID($id);

                    if (isset($member['mb_nick']) and $this->nick != $member['mb_nick']) {
                        // G4
                        $this->info['nick'] = $member['mb_nick'];
                        $this->nick = $member['mb_nick'];
                    } else if (isset($member['nick']) and $this->nick != $member['nick']) {
                        // G5
                        $this->info['nick'] = $member['nick'];
                        $this->nick = $member['nick'];
                    }
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
            // automatically save/register user
            $dummy = $udb->saveUser($this);
        }
    }
}

// vim:et:sts=4:sw=4:
