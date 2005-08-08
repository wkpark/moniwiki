<?php
// Copyright 2003-2004 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
//
// See also http://kr.php.net/ip2long/
// $Id$
// 

function is_valid_network($network)
{
    $nums = explode('.', $network);
    if (sizeof($nums) != 4)
        return false;
    if (!preg_match('/^(\d{1,3}\.){3}\d{1,3}$/', $network))
        return false;
    foreach ($nums as $num)
        if ($num > 255)
            return false;
    return true;
}

function check_ip($rules,$ip) {
    if (!$rules or !$ip) return false; // do not ckeck
    $ip = ip2long($ip);

    $rules = explode(':',$rules);
    foreach ($rules as $rule)
    {
        list($network,$netmask)=explode('/',$rule);
        if (!is_valid_network($network)) continue; // ignore error

        $network = ip2long($network);

        if ($netmask) {
            // echo "$ip ;$netmask; $network\n";

            if(is_valid_network($netmask))
                $netmask = ip2long($netmask);
            else if ($netmask >= 0 && $netmask <= 32)
                $netmask = 0xffffffff << (32 - $netmask);
            else
                continue; // ignore error

            // echo "$ip ;$netmask; $network\n";
            // print long2ip($netmask);
            // echo sprintf("%u", ip2long($ip));
            if(($ip & $netmask) == ($network & $netmask))
                return true;
        } else if ($ip == $network) {
            return true;
        }
    }
    return false;
}

/*
# simple tests
if ( check_ip("203.252.48.0/24:203.252.57.2","203.252.48.99") )
    print "OK\n";
else
    print "Oh no !\n";
if ( check_ip("203.252.48.0/24:203.252.57.2/24","203.252.57.2") )
    print "OK\n";
else
    print "Oh no !\n";
*/
?>
