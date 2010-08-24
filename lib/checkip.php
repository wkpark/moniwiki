<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
//
// See also http://kr.php.net/ip2long/
// $Id$
// 

function normalize_network($network)
{
    if (($p = strpos($network, '/')) !== false) {
        $tmp = explode('/', $network);
        if (count($tmp) > 2)
            return false;
        $network = $tmp[0];
        $netmask = $tmp[1];
    }
    $network = rtrim($network, '.'); // trim last dot. eg. 1.2.3. => 1.2.3

    $dot = substr_count($network, '.');
    if ($dot < 3) // 123.123 -> 123.123.0.0
        $network.= str_repeat('.0', 3 - $dot);

    // validate network
    if (ip2long($network) === false) return false;

    if (empty($netmask)) {
        $netmask = 8 * ($dot + 1);
    } else if (is_numeric($netmask)) {
        // validate netmask
        if ($netmask < 0 or $netmask > 32) return false;
    } else {
        if (ip2long($netmask) === false) return false;
    }
    #print $network . '/'. $netmask . "\n";

    return array($network, $netmask);
}

function check_ip($rules, $ip) {
    if (empty($rules) or empty($ip)) return false; // do not ckeck
    $ip = ip2long($ip);

    if (!$ip) return false;

    if (is_string($rules)) // : separated rules like as '192.168.0.2:192.167.:...'
    	$rules = explode(':', $rules);

    if (!is_array($rules)) return false;

    foreach ($rules as $rule)
    {
        $ret = normalize_network($rule);
        if (!$ret) continue; // ignore

        $network = $ret[0];
        $netmask = $ret[1];
        if (is_numeric($netmask)) {
            $netmask = 0xffffffff << (32 - $netmask);
        } else {
            $netmask = ip2long($netmask);
        }
        $network = ip2long($network);

        if(($ip & $netmask) == ($network & $netmask)) {
            return true;
        } else if (empty($netmask) and $ip == $network) {
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

// vim:et:sts=4:sw=4:
?>
