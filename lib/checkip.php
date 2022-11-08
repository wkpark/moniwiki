<?php
// Copyright 2003-2022 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
//
// See also http://kr.php.net/ip2long/
//

function validate_ip($ip) {
    // some IPv4/v6 regexps borrowed from Feyd
    // see: http://forums.devnetwork.net/viewtopic.php?f=38&t=53479
    $dec_octet   = '(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|[0-9])';
    $dec_octet0  = '(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|[1-9])';
    $hex_digit   = '[A-Fa-f0-9]';
    $h16         = "{$hex_digit}{1,4}";
    $IPv4Address = "$dec_octet\\.$dec_octet\\.$dec_octet\\.$dec_octet0";
    $ls32        = "(?:$h16:$h16|$IPv4Address)";
    $IPv6Address =
        "(?:(?:{$IPv4Address})|(?:".
            "(?:$h16:){6}$ls32".
            "|::(?:$h16:){5}$ls32".
            "|(?:$h16)?::(?:$h16:){4}$ls32".
            "|(?:(?:$h16:){0,1}$h16)?::(?:$h16:){3}$ls32".
            "|(?:(?:$h16:){0,2}$h16)?::(?:$h16:){2}$ls32".
            "|(?:(?:$h16:){0,3}$h16)?::(?:$h16:){1}$ls32".
            "|(?:(?:$h16:){0,4}$h16)?::$ls32".
            "|(?:(?:$h16:){0,5}$h16)?::$h16".
            "|(?:(?:$h16:){0,6}$h16)?::".
            ")(?:\\/(?:12[0-8]|1[0-1][0-9]|[1-9][0-9]|[0-9]))?)";

    // remove any non-IP stuff
    if (preg_match("/^$IPv4Address$/", $ip, $match) || preg_match("/^$IPv6Address$/", $ip)) {
        return true;
    }
    return false;
}

function _ipv6getbitsmask($bits = 64, $type = 'hex') {
    $hex = str_pad('f', intval($bits / 4), 'f', STR_PAD_LEFT);
    $left = $bits % 4;
    if ($left > 0) {
        $hi = dechex(0xf >> (4-$left));
        $hex = $hi.$hex;
    }

    $mask = str_pad($hex, 32, '0', STR_PAD_LEFT);
    if ($type == 'bin') {
        return pack('H*', $mask);
    }
    return $mask;
}

/**
 * Uncompress an IPv6 address
 *  ie. 2001:db8::1 => 2001:0db8:0000:0000:0000:0000:0000:0001
 *
 * @author Bas Roos <bas@gatlan.nl>
 * @since 2009/05/21
 * @modified 2022/11/04
 * @license MIT
 * @param string $ip IPv6 address
 * @return string Uncompressed IPv6 address, or false in case of an invalid IPv6 address
 */
function ipv6uncompress($ip, $type = 'hex') {
    if (!validate_ip($ip))
        return false;

    // add additional colon's, until 7 (or 6 in case of an IPv4 (mapped) address
    while (substr_count($ip, ":") < (substr_count($ip, ".") == 3 ? 6 : 7))
        $ip = substr_replace($ip, "::", strpos($ip, "::"), 1);

    $ip = explode(":", $ip);

    // replace the IPv4 part address with hexadecimals if needed
    if ((strpos($ip[count($ip)-1], '.')) !== false) {
        $ipv4 = &$ip[count($ip)-1];
        $chunks = explode('.', $ipv4);
        foreach ($chunks as $i=>$chunk) {
            $chunks[$i] = sprintf("%02s", dechex($chunk));
        }
        $ipv4 = $chunks[0].$chunks[1];
        $ip[] = $chunks[2].$chunks[3];
    }

    // Add leading 0's in every part, up until 4 characters
    foreach ($ip as $i=>$chunk)
        $ip[$i] = sprintf("%04s", $chunk);

    if ($type == 'hex')
        return implode('', $ip);
    else if ($type == 'bin') {
        $hex = implode('', $ip);
        // inet_pton()
        return pack('H*', $hex);
    }
    return implode(':', $ip);
}


/**
 * Compress an IPv6 address, according to
 *   http://tools.ietf.org/html/draft-kawamura-ipv6-text-representation-02
 *   ie. 2001:0db8:0000:0000:0000:0000:0000:0001 => 2001:db8::1
 *
 * @author Bas Roos <bas@gatlan.nl>
 * @since 2009/05/21
 * @modified 2022/11/04
 * @license MIT
 * @param string an IPv6 address
 * @return string Compressed IPv6 address, or false in case of an invalid IPv6 address
 */
function ipv6compress($ip) {
    if (!validate_ip($ip))
        return false;

    // uncompress the address, so we are sure the address isn't already compressed
    $ip = ipv6uncompress($ip);

    // remove all leading 0's; 0034 -> 34; 0000 -> 0
    $ip = preg_replace("/(^|:)0+(?=[a-fA-F\d]+(?::|$))/", '$1', $ip);

    // find all :0:0: sequences
    preg_match_all("/((?:^|:)0(?::0)+(?::|$))/", $ip, $matches);

    // Search all :0:0: sequences and determine the longest
    $reg = "";
    foreach ($matches[0] as $match)
        if (strlen($match) > strlen($reg))
            $reg = $match;

    // replace the longst :0 sequence with ::, but do it only once
    if (strlen($reg))
        $ip = preg_replace("/$reg/", '::', $ip, 1);

    return $ip;
}

function ipv6_normalize_network($network, $type = 'hex', $range = false)
{
    if (strpos($network, '/') !== false) {
        $parts = explode('/', $network);
        if (count($parts) != 2)
            return false;
        if ($parts[1] < 0 || $parts[1] > 128)
            return false;
        if (!validate_ip($parts[0]))
            return false;

        $network = ipv6uncompress($parts[0], 'hex');
        $prefix = intval($parts[1]);
        $bits = 128 - $prefix;
    } else {
        $network = ipv6uncompress($network);

        if (preg_match('@(0+)[0-9a-f]*:((?:0000:)*0000)$@', $network, $m)) {
            $a = strlen($m[1]) * 4;
            $b = (1 + strlen($m[2])) / 5 * 4 * 4;
            $bits = $a + $b;
        } else {
            $bits = 0;
        }
        $prefix = 128 - $bits;
        $network = str_replace(':', '', $network);
    }
    $mask = _ipv6getbitsmask($bits, 'bin');

    $startbin = pack('H*', $network) & ~$mask;
    if ($range) {
        $chunks = str_split(bin2hex($startbin), 4);
        //echo bin2hex($mask), ', ~', bin2hex(~$mask),"\n";
        $endbin = pack('H*', $network) | $mask;
        if ($type == 'bin' || $type === true) {
        //echo bin2hex($startbin), '=>', bin2hex($endbin),"\n";
            return array($startbin, $endbin);
        } if ($type == 'hex') {
            $start = implode('', $chunks);

            $chunks = str_split(bin2hex($endbin), 4);
            $end = implode('', $chunks);
            return array($start, $end);
        }

        $start = implode(':', $chunks);

        $chunks = str_split(bin2hex($endbin), 4);
        $end = implode(':', $chunks);
        return array($network, $end);
    }

    $chunks = str_split(bin2hex($startbin), 4);
    if ($type == 'bin' || $type === true) {
        return array($startbin, ~$mask);
    } if ($type == 'hex') {
        $start = implode('', $chunks);

        $chunks = str_split(bin2hex(~$mask), 4);
        $mask = implode('', $chunks);
        return array($start, $mask);
    }
    $start = implode(':', $chunks);

    $chunks = str_split(bin2hex(~$mask), 4);
    $mask = implode(':', $chunks);

    return array($start, $mask);
}

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
    if (!validate_ip($ip))
        return false;
    $is_ipv6 = false;
    $normalize_network = 'normalize_network';
    if (strpos($ip, ':') !== false) {
        // IPv6
        $normalize_network = 'ipv6_normalize_network';
        $ip = ipv6uncompress($ip, 'bin');
        $is_ipv6 = true;
    } else {
        // normal IPv4
        $is_ipv6 = false;
        $ip = ip2long($ip);
    }

    if (empty($ip)) return false;

    if (is_string($rules)) { // : separated rules like as '192.168.0.2:192.167.:...'
        if (strpos($rules, ';') !== false) // check ';' separator
            $rules = explode(';', $rules);
        else if (!$is_ipv6)
            $rules = explode(':', $rules);
        if (is_string($rules))
            $rules = array($rules);
    }

    foreach ($rules as $rule)
    {
        $ret = $normalize_network($rule, true);
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
        $is_ipv6 = false;

        $normalize_network = 'normalize_network';
        if (strpos($ip, ':') !== false) {
            $is_ipv6 = true;
            $normalize_network = 'ipv6_normalize_network';
        }

        if (strpos($ip, '/') === false) {
            if ($is_ipv6) {
                // IPv6
                $l = ipv6uncompress($ip, 'bin');
                $is_ipv6 = true;
            } else {
                // normal IPv4
                $is_ipv6 = false;
                $l = ip2long($ip);
                $l = sprintf("%u", $l);
            }
            if (empty($l))
                continue; // ignore
            $ips[$l] = 0;
            continue;
        } else {
            $tmp = $normalize_network($ip, true, true);
            if ($tmp === false)
                // ignore
                continue;
            $ips[$tmp[0]] = $tmp[1];
        }
    }
    ksort($ips);
    $from = array_keys($ips);
    $to = array_values($ips);

    return array($from, $to);
}

/**
 * make searchable IPv6 network ranges for search_network()
 *
 * @author  Won-Kyu Park <wkpark at gmail.com>
 * @since   2022/11/08
 */

function ipv6_make_ip_ranges($rules) {
    $ips = array();
    foreach ($rules as $ip) {
        $ip = trim($ip);
        $l = false;
        if (strpos($ip, '/') === false)
            $l = ipv6uncompress($ip, 'bin');
        if ($l === false) {
            $tmp = ipv6_normalize_network($ip, true, true);
            if ($tmp === false)
                // ignore
                continue;
            $ips[$tmp[0]] = $tmp[1];
            continue;
        }
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
    if (strpos($ip, ':') === false) {
        $val = ip2long($ip);
        if ($val === false)
            return false;
    } else {
        // IPv6
        $val = ipv6uncompress($ip, 'bin');
        if ($val === false)
            return false;
    }

    if (!isset($ranges[0]) or !isset($ranges[0][0]))
        return false;

    $low = 0;
    $high = count($ranges[0]) - 1;
    while(true) {
        $mid = ($high + $low) >> 1;
        $from = $ranges[0][$mid];
        if (is_array($ranges[1]) and sizeof($ranges[1]) > 0) {
            $to = $ranges[1][$mid];
            if (empty($to))
                $to = $from;
        } else {
            $to = $from;
        }
        //echo long2ip($from),"\n";
        //echo 'mid = ', $mid, ";",bin2hex($from), "=>", bin2hex($to),"\n";

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
    if (!is_integer($from)) {
        // IPv6 case
        $hex = bin2hex($from);
        $end = bin2hex($to);

        $pos = strlen(rtrim($end, 'f'));
        $bits = (32 - $pos)*4;

        // check the last bits
        $l = substr($end, $pos - 1, 1);
        $s = substr($hex, $pos - 1, 1);
        if (!empty($s))
            $l = ((int)$l) & ~((int)$s);
        $lbits = array(1=>1, 3=>2, 7=>3);
        if (isset($lbits[$l]))
            $bits += $lbits[$l];
        $chunks = str_split($hex, 4);
        return implode(':', $chunks).'/'.(128 - $bits);
    }
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
