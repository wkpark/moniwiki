<?php
/**
 * Copyright 2003-2015 Won-Kyu Park <wkpark@gmail.com> all rights reserved.
 * distributable under GPLv2 see COPYING
 *
 * session utils extracted from wiki.php
 *
 * @since 2015/12/22
 * @since 1.3.0
 * @author Won-Kyu Park <wkpark@gmail.com>
 * @license GPLv2
 *
 */

function _session_start($session_id = null, $id = null) {
    global $DBInfo, $Config;

    // FIXME
    if ($id == null || $id == 'Anonymous')
        return;

    // chceck some action and set expire
    session_cache_limiter('');

    // cookie parameters
    if (!empty($Config['cookie_path']))
        $path = $Config['cookie_path'];
    else
        $path = dirname(get_scriptname());

    if (!empty($Config['cookie_domain']))
        $domain = $Config['cookie_domain'];
    else
        $domain = $_SERVER['HTTP_HOST'];

    $expire = isset($Config['session_lifetime']) ? $Config['session_lifetime'] : 3600;

    if ($session_id == null) {
        // New session
        session_set_cookie_params($expire, $path, $domain);

        session_start();
        $sess_id = session_id();
    } else {
        $sess_id = $session_id;
    }

    // setup the session cookie
    $site_seed = !empty($Config['session_seed']) ? $Config['session_seed'] : 'MONIWIKI';
    $site_hash = md5($site_seed.$sess_id);
    $addr_hash = md5($_SERVER['REMOTE_ADDR'].$sess_id);
    $session_cookie = $site_hash . '-*-' . $addr_hash . '-*-' . time();

    if ($session_id == null) {
        // set session cookie.
        setCookie('MONIWIKI', $session_cookie, time() + $expire, $path, $domain);
    } else {
        $cleanup_session_cookie = false;
        if (empty($_COOKIE['MONIWIKI'])) {
            $cleanup_session_cookie = true;
        } else {
            // check session cookie
            list($site, $addr, $dummy) = explode('-*-', $_COOKIE['MONIWIKI']);
            if ($site != $site_hash || $addr != $addr_hash) {
                $cleanup_session_cookie = true;
            }
        }

        if ($cleanup_session_cookie) {
            // invalid session cookie.
            // remove MONI_ID, MONIWIKI and session cookie
            if (isset($_COOKIE['MONI_ID']))
                setCookie('MONI_ID', null, -1, $path, $domain);
            if (isset($_COOKIE['MONIWIKI']))
                setCookie('MONIWIKI', null, -1, $path, $domain);
            if (isset($_COOKIE[session_name()]))
                setCookie(session_name(), null, -1, $path, $domain);

            // reset some variables
            // FIXME
            $DBInfo->user->id = 'Anonymous';
            $options['id'] = 'Anonymous';
        } else {
            session_set_cookie_params($expire, $path, $domain);

            session_start();
        }
    }
}

// vim:et:sts=4:sw=4:
