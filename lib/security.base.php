<?php
/**
 * Copyright 2003-2015 Won-Kyu Park <wkpark@gmail.com> all rights reserved.
 * distributable under GPLv2 see COPYING
 *
 * A base class for Security
 *
 * @since   2003/05/28
 * @author  Won-Kyu Park <wkpark@gmail.com>
 * @license GPLv2
 *
 */

class Security_base
{
    var $DB;

    function Security_base($DB = '')
    {
        $this->DB = $DB;
    }

    function readable($options = array())
    {
        return 1;
    }

    function writable($options = array())
    {
        if (!isset($options['page'][0]))
            return 0;
        return $this->DB->_isWritable($options['page']);
    }

    function validuser($options = array())
    {
        return 1;
    }

    function is_allowed($action = 'read', &$options)
    {
        return 1;
    }

    function is_protected($action = 'read', $options)
    {
        # password protected POST actions
        $protected_actions = array(
                'deletepage', 'deletefile', 'rename', 'rcspurge', 'rcs', 'chmod', 'backup', 'restore', 'rcsimport', 'revert', 'userinfo', 'merge');
        $action = strtolower($action);

        if (in_array($action, $protected_actions)) {
            return 1;
        }
        return 0;
    }

    function is_valid_password($passwd, $options)
    {
        return
            $this->DB->admin_passwd == crypt($passwd, $this->DB->admin_passwd);
    }
}

// vim:et:sts=4:sw=4:
