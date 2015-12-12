<?php
/**
 * Copyright 2003-2015 Won-Kyu Park <wkpark@gmail.com> all rights reserved.
 * distributable under GPLv2 see COPYING
 *
 * the UserDB class
 *
 * @since  2003/04/12
 * @author Won-Kyu Park <wkpark@gmail.com>
 * @license GPLv2
 *
 */

class UserDB
{
    var $users = array();

    function UserDB($conf)
    {
        if (is_array($conf)) {
            $this->user_dir = $conf['user_dir'];
            $this->strict = $conf['login_strict'];
            if (!empty($conf['user_class']))
                $this->user_class = 'User_'.$conf['user_class'];
            else
                $this->user_class = 'WikiUser';
        } else {
            $this->user_dir=$conf->user_dir;
            $this->strict = $conf->login_strict;
            if (!empty($conf->user_class))
                $this->user_class = 'User_'.$conf->user_class;
            else
                $this->user_class = 'WikiUser';
        }
    }

    function _pgencode($m)
    {
        // moinmoin 1.0.x style internal encoding
        return '_'.sprintf("%02s", strtolower(dechex(ord(substr($m[1],-1)))));
    }

    function _id_to_key($id)
    {
        return preg_replace_callback("/([^a-z0-9]{1})/i",
                array($this, '_pgencode'), $id);
    }

    function _key_to_id($key)
    {
        return rawurldecode(strtr($key,'_','%'));
    }

    function getUserList($options = array())
    {
        if ($this->users) return $this->users;

        $type='';
        if ($options['type'] == 'del') $type = 'del-';
        elseif ($options['type'] == 'wait') $type = 'wait-';

        // count users
        $handle = opendir($this->user_dir);
        $j = 0;
        while ($file = readdir($handle)) {
            if (is_dir($this->user_dir."/".$file)) continue;
            if (preg_match('/^'.$type.'wu\-([^\.]+)$/', $file,$match)) {
                $j++;
            }
        }
        closedir($handle);

        if (is_array($options['retval']))
            $options['retval']['count'] = $j;

        $offset = !empty($options['offset']) ? intval($options['offset']) : 0;
        $limit = !empty($options['limit']) ? intval($options['limit']) : 1000;
        $q = !empty($options['q']) ? trim($options['q']) : '[^\.]+';

        // Anonymous user with editing information
        $rawid = false;
        if (preg_match('/^(\d{1,3}\.){3}\d{1,3}$/', $q))
            $rawid = true;

        $users = array();
        if (!empty($options['q'])) {
            // search exact matched user
            if (($mtime = $this->_exists($q, $type != '')) !== false) {
                $users[$q] = $mtime;
                return $users;
            }
        }

        $handle = opendir($this->user_dir);
        $j = 0;
        while ($file = readdir($handle)) {
            if (is_dir($this->user_dir."/".$file)) continue;
            if (preg_match('/^'.$type.'wu\-(.*)$/', $file, $match)) {
                if ($offset > 0) {
                    $offset--;
                    continue;
                }

                if (!$rawid)
                    $id = $this->_key_to_id($match[1]);
                else
                    $id = $match[1];
                if (!empty($q) and !preg_match('/'.$q.'/i', $id)) continue;
                $users[$id] = filemtime($this->user_dir.'/'.$file);
                $j++;
                if ($j >= $limit)
                    break;
            }
        }
        closedir($handle);
        $this->users=$users;
        return $users;
    }

    function getPageSubscribers($pagename)
    {
        $users=$this->getUserList();
        $subs=array();
        foreach ($users as $id) {
            $usr=$this->getUser($id);
            if ($usr->hasSubscribePage($pagename)) $subs[]=$usr->info['email'];
        }
        return $subs;
    }

    function addUser($user, $options = array())
    {
        if ($this->_exists($user->id) || $this->_exists($user->id, true))
            return false;
        $this->saveUser($user, $options);
        return true;
    }

    function isNotUser($user)
    {
        if ($this->_exists($user->id) || $this->_exists($user->id, true))
            return false;
        return true;
    }

    function saveUser($user,$options=array())
    {
        $config = array("regdate",
                "email",
                "name",
                "nick",
                "home",
                "password",
                "last_login",
                "last_updated",
                "login_fail",
                "remote",
                "login_success",
                "ticket",
                "eticket",
                "idtype",
                "npassword",
                "nticket",
                "edit_count",
                "edit_add_lines",
                "edit_del_lines",
                "edit_add_chars",
                "edit_del_chars",
                "groups", // user groups
                "strike",
                "strike_total",
                "strikeout",
                "strikeout_total",
                "join_agreement",
                "join_agreement_version",
                "tz_offset",
                "avatar",
                "theme",
                "css_url",
                "bookmark",
                "scrapped_pages",
                "subscribed_pages",
                "quicklinks",
                "language", // not used
                "datetime_fmt", // not used
                "wikiname_add_spaces", // not used
                "status", // user status for IP user
                );

        $date=gmdate('Y/m/d H:i:s', time());
        $data="# Data saved $date\n";

        if ($user->id == 'Anonymous') {
            if (!empty($user->info['remote']))
                $wu = 'wu-'.$user->info['remote'];
            else
                $wu = 'wu-'.$_SERVER['REMOTE_ADDR'];
        } else {
            $wu = 'wu-'.$this->_id_to_key($user->id);
            if (!empty($options['suspended'])) $wu = 'wait-'.$wu;
        }

        // new user ?
        if (!file_exists("$this->user_dir/$wu") && empty($user->info['regdate'])) {
            $user->info['regdate'] = $date;
        }
        $user->info['last_updated'] = $date;

        if (!empty($user->ticket))
            $user->info['ticket']=$user->ticket;

        ksort($user->info);

        foreach ($user->info as $k=>$v) {
            if (in_array($k, $config)) {
                $data.= $k.'='.$v."\n";
            } else {
                // undefined local config
                if ($k[0] != '_')
                    $k = '_'.$k;
                $data.= $k.'='.$v."\n";
            }
        }

        $fp=fopen("$this->user_dir/$wu","w+");
        if (!is_resource($fp))
            return;
        fwrite($fp,$data);
        fclose($fp);
    }

    function _exists($id, $suspended = false)
    {
        if (empty($id) || $id == 'Anonymous') {
            if ($suspended) return false;
            $wu = 'wu-'.$_SERVER['REMOTE_ADDR'];
        } else if (preg_match('/^(\d{1,3}\.){3}\d{1,3}$/', $id)) {
            if ($suspended) return false;
            $wu = 'wu-'.$id;
        } else {
            $prefix = $suspended ? 'wait-wu-' : 'wu-';
            $wu = $prefix . $this->_id_to_key($id);
        }
        if (file_exists($this->user_dir.'/'.$wu))
            return filemtime($this->user_dir.'/'.$wu);

        if ($suspended) {
            // deletede user ?
            $prefix = 'del-wu-';
            $wu = $prefix . $this->_id_to_key($id);
            if (file_exists($this->user_dir.'/'.$wu))
                return filemtime($this->user_dir.'/'.$wu);
        }
        return false;
    }

    function checkUser(&$user)
    {
        if (!empty($user->info['ticket']) and $user->info['ticket'] != $user->ticket) {
            if ($this->strict > 0)
                $user->id='Anonymous';
            return 1;
        }
        return 0;
    }

    function getInfo($id, $suspended = false)
    {
        if (empty($id) || $id == 'Anonymous') {
            $wu = 'wu-'.$_SERVER['REMOTE_ADDR'];
        } else if (preg_match('/^(\d{1,3}\.){3}\d{1,3}$/', $id)) {
            $wu = 'wu-'.$id;
        } else {
            $prefix = $suspended ? 'wait-wu-' : 'wu-';
            $wu = $prefix . $this->_id_to_key($id);
        }
        if (file_exists($this->user_dir.'/'.$wu)) {
            $data = file($this->user_dir.'/'.$wu);
        } else {
            return array();
        }
        $info=array();
        foreach ($data as $line) {
            #print "$line<br/>";
            if ($line[0]=="#" and $line[0]==" ") continue;
            $p=strpos($line,"=");
            if ($p === false) continue;
            $key=substr($line,0,$p);
            $val=substr($line,$p+1,-1);
            $info[$key]=$val;
        }

        return $info;
    }

    function getUser($id, $suspended = false)
    {
        $user = new WikiUser($id);
        $info = $this->getInfo($id, $suspended);
        $user->info = $info;

        // read group infomation
        if (!empty($info['groups'])) {
            $groups = explode(',', $info['groups']);
            // already has group information ?
            if (!empty($user->groups))
                $user->groups = array_merge($user->groups, $groups);
            else
                $user->groups = $groups;
        }

        // set default timezone
        if (isset($info['tz_offset']))
            $user->tz_offset = $info['tz_offset'];
        else
            $user->info['tz_offset'] = date('Z');

        $user->ticket = !empty($info['ticket']) ? $info['ticket'] : null;

        return $user;
    }

    function delUser($id)
    {
        $id = trim($id);
        if (empty($id) || $id == 'Anonymous')
            return false;

        if (preg_match('/^(\d{1,3}\.){3}\d{1,3}$/', $id)) {
            // change user status
            $info = $this->getInfo($id);
            $user = new WikiUser($id);
            $info['status'] = 'deleted';
            $info['remote'] = $id;
            $user->info = $info;
            $this->saveUser($user);
            return true;
        } else {
            $key = $this->_id_to_key($id);
            $u = 'wu-'. $key;
        }

        $du = 'del-'.$u;
        if ($this->_exists($id)) {
            return rename($this->user_dir.'/'.$u,$this->user_dir.'/'.$du);
        } else if ($this->_exists($id, true)) {
            // delete suspended user
            $u = 'wait-'. $u;
            return rename($this->user_dir.'/'.$u, $this->user_dir.'/'.$du);
        } if (file_exists($this->user_dir.'/'.$du)) {
            // already deleted
            return true;
        }
        return false;
    }

    function activateUser($id, $suspended = false)
    {
        $id = trim($id);
        if (empty($id) || $id == 'Anonymous')
            return false;

        if (preg_match('/^(\d{1,3}\.){3}\d{1,3}$/', $id)) {
            // activate or suspend IP user
            $info = $this->getInfo($id);
            $user = new WikiUser($id);
            if ($suspended)
                $info['status'] = 'suspended';
            else
                unset($info['status']);
            $info['remote'] = $id;
            $user->info = $info;
            $this->saveUser($user);
            return true;
        } else {
            $u = $wu = 'wu-'. $this->_id_to_key($id);
        }
        $states = array('wait', 'del');
        if ($suspended) {
            $wu = 'wait-'.$u;
            $states = array('del', '');
        }

        if (file_exists($this->user_dir.'/'.$wu)) return true;

        foreach ($states as $state) {
            if (!empty($state))
                $uu = $state.'-'.$u;
            else
                $uu = $u;
            if (file_exists($this->user_dir.'/'.$uu))
                return rename($this->user_dir.'/'.$uu, $this->user_dir.'/'.$wu);
        }

        return false;
    }
}

// vim:et:sts=4:sw=4:
