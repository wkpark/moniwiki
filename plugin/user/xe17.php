<?php
// Copyright 2013 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a sample plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Since: 2013-07-04
// Name: XE 1.7 User plugin
// Description: XE 1.7 user plugin
// URL: MoniWiki:XeUserPlugin
// Version: $Revision: 1.0 $
// License: GPL
//
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
        session_start();

        if ($sess = $_POST[session_name()]) {
            session_id($sess);
        }
    }

    function User_xe17($id = '') {
        global $DBInfo;

        define('__XE__', true);

        $zbxe_root_dir = !empty($DBInfo->zbxe_root_dir) ?
                $DBInfo->zbxe_root_dir : __DIR__.'/../../../xe'; //"/home/httpd/xe/"; // XE root dir

        require_once($zbxe_root_dir."/config/config.inc.php");

        $context = &Context::getInstance();
        $this->xe_context_init($context); // simplified init context method
        //$context->init(); // very slow

        $oMemberModel = &getModel('member');
        $oMemberController = &getController('member');

        if ($oMemberModel->isLogged()) {
            $oMemberController->setSessionInfo();
            $member = new memberModel();
            $info = $member->getLoggedInfo();

            $id = $info->user_id;

            $this->setID($id);
            $udb = new UserDB($DBInfo);
            $tmp = $udb->getUser($id);

            // get timezone and make timezone offset
            $tz_offset = date('Z');
            $update = 0;
            if ($tz_offset != $tmp->info['tz_offset'])
                $update = 1;

            // not registered user ?
            if ($update or empty($tmp->info['nick'])) {
                // automatically save/register user
                $tmp->info['tz_offset'] = $tz_offset;
                $tmp->info['nick'] = $info->nick_name;
                $udb->saveUser($tmp);
            }
        } else {
            $this->setID('Anonymous');
        }

        $this->css = isset($_COOKIE['MONI_CSS']) ? $_COOKIE['MONI_CSS']:'';
        $this->theme = isset($_COOKIE['MONI_THEME']) ? $_COOKIE['MONI_THEME']:'';
        $this->bookmark = isset($_COOKIE['MONI_BOOKMARK']) ? $_COOKIE['MONI_BOOKMARK']:'';
        $this->trail = isset($_COOKIE['MONI_TRAIL']) ? _stripslashes($_COOKIE['MONI_TRAIL']):'';
        $this->tz_offset = isset($_COOKIE['MONI_TZ']) ?_stripslashes($_COOKIE['MONI_TZ']):'';
        $this->nick = isset($_COOKIE['MONI_NICK']) ?_stripslashes($_COOKIE['MONI_NICK']):'';
        if ($this->tz_offset == '') $this->tz_offset = date('Z');

        if (!empty($id) and $id != 'Anonymous') {
            $udb = new UserDB($DBInfo);

            if (!$udb->_exists($id)) {
	        $dummy = $udb->saveUser($this);
            }
        }
    }
}

// vim:et:sts=4:sw=4:
