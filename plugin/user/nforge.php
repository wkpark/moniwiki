<?php
// Copyright 2009 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a sample plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Author: nFORGE Team 2008
// Since: 2009-04-18
// Name: nFORGE Unix based User plugin
// Description: nFORGE Unix user plugin
// URL: MoniWiki:NForgeUserPlugin
// Version: $Revision$
// License: GPL
//
// Usage: set $user_class = 'nforge'; in the config.php
//
// $Id$

class User_nforge extends WikiUser {
    function User_nforge($id = '') {
        if ($id) {
            $this->setID($id);
            $u =& user_get_object_by_name($id);
        } else {
            $u =& user_get_object(user_getid());
            if ($u) {
                global $DBInfo;
                $id = $u->getUnixName();
            }
            $this->setID($id);
            $udb=new UserDB($DBInfo);
            $tmp = $udb->getUser($id);
            if (empty($tmp->info['nick']) or $tmp->info['nick']!=$u->data_array['realname']) {
                // register user
                $tmp->info['nick']=$u->data_array['realname'];
                $udb->saveUser($tmp);
            }
        }

        $this->css=isset($_COOKIE['MONI_CSS']) ? $_COOKIE['MONI_CSS']:'';
        $this->theme=isset($_COOKIE['MONI_THEME']) ? $_COOKIE['MONI_THEME']:'';
        $this->bookmark=isset($_COOKIE['MONI_BOOKMARK']) ? $_COOKIE['MONI_BOOKMARK']:'';
        $this->trail=isset($_COOKIE['MONI_TRAIL']) ? _stripslashes($_COOKIE['MONI_TRAIL']):'';
        $this->tz_offset=isset($_COOKIE['MONI_TZ']) ?_stripslashes($_COOKIE['MONI_TZ']):'';
        $this->nick=isset($_COOKIE['MONI_NICK']) ?_stripslashes($_COOKIE['MONI_NICK']):'';
        if ($this->tz_offset == '') $this->tz_offset = date('Z');

        if (!empty($id)) {
            global $DBInfo;
            $udb = new UserDB($DBInfo);

            if (!$udb->_exists($id)) {
	        $dummy=$udb->saveUser($this);
            }
        }
    }
}

// vim:et:sts=4:sw=4:
