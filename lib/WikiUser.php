<?php
/**
 * Copyright 2003-2015 Won-Kyu Park <wkpark@gmail.com> all rights reserved.
 * distributable under GPLv2 see COPYING
 *
 * Base WikiUser class
 *
 * @since  2003/04/12
 * @author Won-Kyu Park <wkpark@gmail.com>
 * @license GPLv2
 *
 */

class WikiUser
{
    var $cookie_expires = 2592000; // 60 * 60 * 24 * 30; // default 30 days

    function WikiUser($id = "")
    {
        global $Config;

        if (!empty($Config['cookie_expires']))
            $this->cookie_expires = $Config['cookie_expires'];

        if ($id && $id != 'Anonymous') {
            $this->setID($id);
            return;
        }
        $id = '';
        if (isset($_COOKIE['MONI_ID'])) {
            $this->ticket=substr($_COOKIE['MONI_ID'],0,32);
            $id=urldecode(substr($_COOKIE['MONI_ID'],33));
        }
        $ret = $this->setID($id);
        if ($ret) $this->getGroup();

        $this->css=isset($_COOKIE['MONI_CSS']) ? $_COOKIE['MONI_CSS']:'';
        $this->theme=isset($_COOKIE['MONI_THEME']) ? $_COOKIE['MONI_THEME']:'';
        $this->bookmark=isset($_COOKIE['MONI_BOOKMARK']) ? $_COOKIE['MONI_BOOKMARK']:'';
        $this->trail=isset($_COOKIE['MONI_TRAIL']) ? _stripslashes($_COOKIE['MONI_TRAIL']):'';
        $this->tz_offset=isset($_COOKIE['MONI_TZ']) ?_stripslashes($_COOKIE['MONI_TZ']):'';
        $this->nick=isset($_COOKIE['MONI_NICK']) ?_stripslashes($_COOKIE['MONI_NICK']):'';
        $this->verified_email = isset($_COOKIE['MONI_VERIFIED_EMAIL']) ? _stripslashes($_COOKIE['MONI_VERIFIED_EMAIL']) : '';
        if ($this->tz_offset =='') $this->tz_offset=date('Z');
    }

    // get ACL group
    function getGroup()
    {
        global $DBInfo;

        if ($this->id == 'Anonymous') return;

        // get groups
        if (isset($DBInfo->security) && method_exists($DBInfo->security, 'get_acl_group'))
            $this->groups = $DBInfo->security->get_acl_group($this->id);
    }

    // check group Information
    function checkGroup()
    {
        global $DBInfo;

        if ($this->id == 'Anonymous') return;

        // a user of members
        $this->is_member = in_array($this->id, $DBInfo->members);

        // check ACL admin groups
        if (!empty($DBInfo->acl_admin_groups)) {
            foreach ($this->groups as $g) {
                if (in_array($g, $DBInfo->acl_admin_groups)) {
                    $this->is_member = true;
                    break;
                }
            }
        }
    }

    function setID($id)
    {
        if ($id and $this->checkID($id)) {
            $this->id=$id;
            return true;
        }
        $this->id='Anonymous';
        $this->ticket='';
        return false;
    }

    function getID($name)
    {
        $name = trim($name);
        if (strpos($name, ' ') !== false) {
            $dum=explode(" ",$name);
            $new=array_map("ucfirst",$dum);
            return implode('', $new);
        }
        return $name;
    }

    function setCookie()
    {
        global $Config;

        if ($this->id == "Anonymous") return false;

        if (($sessid = session_id()) == '') {
            // no session used. IP dependent.
            $ticket = getTicket($this->id, $_SERVER['REMOTE_ADDR']);
        } else {
            // session enabled case. use session.
            $ticket = md5($this->id.$sessid);
        }
        $this->ticket=$ticket;
        # set the fake cookie
        $_COOKIE['MONI_ID']=$ticket.'.'.urlencode($this->id);
        if (!empty($this->info['nick'])) $_COOKIE['MONI_NICK']=$this->info['nick'];

        $domain = '';
        if (!empty($Config['cookie_domain'])) {
            $domain = '; Domain='.$Config['cookie_domain'];
        } else {
            $dummy = '; Domain='.$_SERVER['SERVER_NAME'];
        }

        if (!empty($Config['cookie_path']))
            $path = '; Path='.$Config['cookie_path'];
        else
            $path = '; Path='.dirname(get_scriptname());
        return "Set-Cookie: MONI_ID=".$ticket.'.'.urlencode($this->id).
            '; expires='.gmdate('l, d-M-Y H:i:s', time() + $this->cookie_expires).' GMT '.$path.$domain;
    }

    function unsetCookie()
    {
        global $Config;

        # set the fake cookie
        $_COOKIE['MONI_ID']="Anonymous";

        $domain = '';
        if (!empty($Config['cookie_domain'])) {
            $domain = '; Domain='.$Config['cookie_domain'];
        } else {
            $dummy = '; Domain='.$_SERVER['SERVER_NAME'];
        }
        if (!empty($Config['cookie_path']))
            $path = '; Path='.$Config['cookie_path'];
        else
            $path = '; Path='.dirname(get_scriptname());
        return "Set-Cookie: MONI_ID=".$this->id."; expires=Tuesday, 01-Jan-1999 12:00:00 GMT; Path=".$path.$domain;
    }

    function setPasswd($passwd,$passwd2="",$rawmode=0)
    {
        if (!$passwd2) $passwd2=$passwd;
        $ret=$this->validPasswd($passwd,$passwd2);
        if ($ret > 0) {
            if ($rawmode)
                $this->info['password']=$passwd;
            else
                $this->info['password']=crypt($passwd);
        }
        #   else
        #       $this->info[password]="";
        return $ret;
    }

    function checkID($id)
    {
        $SPECIAL='\\,;\$\|~`#\+\*\?!"\'\?%&\(\)\[\]\{\}\=';
        preg_match("/[$SPECIAL]/",$id,$match);
        if (!$id || $match)
            return false;
        if (preg_match('/^\d/', $id)) return false;
        return true;
    }

    function checkPasswd($passwd,$chall=0)
    {
        if (strlen($passwd) < 3)
            return false;
        if ($chall) {
            if (hmac($chall,$this->info['password']) == $passwd)
                return true;
        } else {
            if (crypt($passwd,$this->info['password']) == $this->info['password'])
                return true;
        }
        return false;
    }

    function validPasswd($passwd,$passwd2)
    {
        if (strlen($passwd)<4)
            return 0;
        if ($passwd2!="" and $passwd!=$passwd2)
            return -1;

        $LOWER='abcdefghijklmnopqrstuvwxyz';
        $UPPER='ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $DIGIT='0123456789';
        $SPECIAL=',.;:-_#+*?!"\'?%&/()[]{}\=~^|$@`';

        $VALID=$LOWER.$UPPER.$DIGIT.$SPECIAL;

        $ok=0;

        for ($i=0;$i<strlen($passwd);$i++) {
            if (strpos($VALID,$passwd[$i]) === false)
                return -2;
            if (strpos($LOWER,$passwd[$i]))
                $ok|=1;
            if (strpos($UPPER,$passwd[$i]))
                $ok|=2;
            if (strpos($DIGIT,$passwd[$i]))
                $ok|=4;

            if ($ok==7 and strlen($passwd)>10) return $ok+1;
            // sufficiently safe password

            if (strpos($SPECIAL,$passwd[$i]))
                $ok|=8;
        }
        return $ok;
    }

    function hasSubscribePage($pagename)
    {
        if (!$this->info['email'] or !$this->info['subscribed_pages']) return false;
        $page_list=_preg_search_escape($this->info['subscribed_pages']);
        if (!trim($page_list)) return false;
        $page_lists=explode("\t",$page_list);
        $page_rule='^'.join("$|^",$page_lists).'$';
        if (preg_match('/('.$page_rule.')/',$pagename))
            return true;
        return false;
    }
}

// vim:et:sts=4:sw=4:
