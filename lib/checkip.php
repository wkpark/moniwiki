<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
//
// See also http://kr.php.net/ip2long/
// $Id$
// 

function normalize_network($network, $ip2long = false, $range = false)
{
    if (($p = strpos($network, '/')) !== false) {
        $tmp = substr($network, 0, $p);
        $netmask = substr($network, $p + 1);
        $network = trim($tmp);
        if (is_numeric($netmask)) {
            // validate netmask
            if ($netmask < 0 or $netmask > 32)
                return false;
        } else if (preg_match('/^(((128|192|224|240|248|252|254)\.0\.0\.0)|
                (255\.(0|128|192|224|240|248|252|254)\.0\.0)|
                (255\.255\.(0|128|192|224|240|248|252|254)\.0)|
                (255\.255\.255\.(0|128|192|224|240|248|252|254)))$/x', $netmask)) {
            $netmask = ip2long($netmask);
            $netmask = strlen(rtrim(decbin($netmask), '0'));
        } else {
            return false;
        }
    }
    $network = rtrim($network, '.'); // trim last dot. eg. 1.2.3. => 1.2.3

    $n = 3 - substr_count($network, '.');
    if ($n > 0) // 123.123 -> 123.123.0.0
        $network.= str_repeat('.0', $n);

    // validate network
    if (($net = ip2long($network)) === false)
        return false;

    if (!isset($netmask))
        $netmask = 8 * (4 - $n);
    $imask = (1 << (32 - $netmask)) - 1;
    $mask = ~$imask & 0xffffffff;

    $net = $net & $mask;
    $end = $net | $imask;
    // fixup for 32bit machine
    if ($mask < 0) $mask = sprintf("%u", $mask);
    if ($net < 0) $net = sprintf("%u", $net);
    if ($end < 0) $end = sprintf("%u", $end);

    if ($ip2long) {
        if ($range)
            return array($net, $end);
        return array($net, $mask);
    }

    $network = long2ip($net);
    if ($range)
        return array($network, long2ip($end));
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
        $ret = normalize_network($rule, true);
        if (!$ret) continue; // ignore

        $network = $ret[0];
        $netmask = $ret[1];

        if(($ip & $netmask) == ($network & $netmask)) {
            return true;
        } else if (empty($netmask) and $ip == $network) {
            return true;
        }
    }
    return false;
}

/**
 * make searchable network ranges for search_network()
 *
 * @author  Won-Kyu Park <wkpark at gmail.com>
 * @since   2015/10/05
 */

function make_ip_ranges($rules) {
    $ips = array();
    foreach ($rules as $ip) {
        $ip = trim($ip);
        $l = false;
        if (strpos($ip, '/') === false)
            $l = ip2long($ip);
        if ($l === false) {
            $tmp = normalize_network($ip, true, true);
            if ($tmp === false)
                // ignore
                continue;
            $ips[$tmp[0]] = $tmp[1];
            continue;
        }
        $l = sprintf("%u", $l);
        $ips[$l] = 0;
    }
    ksort($ips);
    $from = array_keys($ips);
    $to = array_values($ips);

    return array($from, $to);
}

/**
 * binary search IP ranges
 *
 * @author  Won-Kyu Park <wkpark at gmail.com>
 * @since   2015/10/05
 */

function search_network($ranges, $ip, $params = array()) {
    $val = ip2long($ip);
    if ($val === false)
        return false;

    $low = 0;
    $high = count($ranges[0]);
    while(true) {
        $mid = ($high + $low) >> 1;
        $from = $ranges[0][$mid];
        $to = $ranges[1][$mid];
        if ($to == 0)
            $to = $from;
        //echo long2ip($from),"\n";

        if ($from > $val) {
            $high = $mid - 1;
        } else if ($from == $val) {
            // exact match
            $ret = true;
            // return the found range.
            if (isset($params['retval'])) {
                $params['retval'] = simple_range_to_network($from, $to);
            }
            break;
        } else if ($from < $val) {
            if ($val <= $to) {
                $ret = true;
                // return the found range.
                if (isset($params['retval'])) {
                    $params['retval'] = simple_range_to_network($from, $to);
                }
                break;
            }
            $low = $mid + 1;
        }

        if ($high < $low) {
            $ret = false;
            // return the last found range.
            if (isset($params['retval'])) {
                $params['retval'] = simple_range_to_network($from, $to);
            }
            break;
        }
    }
    return $ret;
}

function simple_range_to_network($from, $to) {
    $count = $to - $from;
    $netmask = 32 - substr_count(decbin($count), '1');
    $ip = long2ip($from);
    return $ip.'/'.$netmask;
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
