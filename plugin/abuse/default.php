<?php
/**
 * default abuse filter
 *
 * @author   Won-Kyu Park <wkpark@gmail.com>
 * @action  string  action name
 * @params  array   parameters
 */
function abusefilter_default($action, $params = array()) {
    global $Config, $DBInfo;

    $members = $DBInfo->members;
    $user = $DBInfo->user;

    // ID or IP
    $id = $params['id'];

    // do not use abuse filter for members
    if (!empty($members) and in_array($id, $members)) return true;

    // default abusing check paramters
    // users can edit 10 times within 5-minutes etc.
    $edit = array('ttl'=>60*5, 'create'=>2, 'delete'=>1, 'revert'=>2, 'save'=>10, 'edit'=>10);

    if (is_array($Config['abusefilter_settings'])) {
        $edit = array_merge($edit, $Config['abusefilter_settings']);
    } if (is_integer($Config['abusefilter_settings'])) {
        // TTL minutes
        $edit['ttl'] = $Config['abusefilter_settings'] * 60;
    }

    $act = strtolower($action);
    $ec = new Cache_text('abusefilter');

    $info = array('create'=>0, 'delete'=>0, 'revert'=>0, 'save'=>0, 'edit'=>0);
    if ($ec->exists($id) and ($info = $ec->fetch($id)) !== false) {
        // check edit count
        if ($info['edit'] > $edit['edit'] || $info[$act] > $edit[$act]) {
            if ($info[$act] > $edit[$act])
                $myact = $act;
            else
                $myact = 'edit';

            if ($user->id == 'Anonymous')
                $user->info['remote'] = $id;

            // save abusing information
            if (empty($user->info['strike']))
                $user->info['strike'] = 0;
            if (empty($user->info['strike_total']))
                $user->info['strike_total'] = 0;
            if (empty($user->info['strikeout']))
                $user->info['strikeout'] = 0;
            if (empty($user->info['strikeout_total']))
                $user->info['strikeout_total'] = 0;

            $user->info['strike_total']++;
            $user->info['strike']++;

            if ($user->info['strike'] % 3 == 0) {
                $user->info['strikeout_total']++;
                $user->info['strikeout']++;
                $user->info['strike'] = 0;
            }

            // default maxage is 30 mimute
            $default_maxage = isset($Config['abusefilter_maxage']) ? $Config['abusefilter_maxage'] : 60*30;
            $maxage = $user->info['strike_total'] * $default_maxage;

            if ($user->info['strikeout'] >= 1)
                // 3, 4, 5, 12, 14, 16 days
                $maxage = $user->info['strike_total'] * $user->info['strikeout'] * $default_maxage * 48; // 1days
            if ($user->info['strikeout'] >= 3)
                $maxage = $user->info['strike_total'] * $user->info['strikeout'] * $default_maxage * 48 * 2; // 2days

            $user->info['addr'] = $id;

            $DBInfo->udb->saveUser($user);
            $ec->update($id, $info, $maxage);

            $y = intval($maxage / (365 * 24 * 60 * 60));
            $d = intval(($maxage % 3153600) / (24 * 60 * 60));
            $h = intval(($maxage % 86400) / (60 * 60));
            $m = intval(($maxage % 3600) / 60);

            $str = array();
            if (!empty($y))
                $str[] = sprintf(_("%s years"), $y);
            if (!empty($d))
                $str[] = sprintf(_("%s days"), $d);
            if (!empty($h))
                $str[] = sprintf(_("%s houers"), $h);
            if (!empty($m))
                $str[] = sprintf(_("%s minutes"), $m);

            $params['retval']['msg'] = sprintf(_("Abusing detected! You are blocked to edit pages until %s."), implode(' ', $str));
            return false;
        }

        $info[$act]++;
        $info['edit']++;
    } else {
        $info[$act]++;
        $info['edit']++;
    }
    $ec->update($id, $info, $edit['ttl']);

    return true;
}

// vim:et:sts=4:sw=4:
