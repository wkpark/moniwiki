<?php
/**
 * Return the IP of the client
 *
 * Honours X-Forwarded-For and X-Real-IP Proxy Headers
 *
 * It returns a comma separated list of IPs if the above mentioned
 * headers are set. If the single parameter is set, it tries to return
 * a routable public address, prefering the ones suplied in the X
 * headers
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @param  boolean $single If set only a single IP is returned
 * @return string
 */
function clientIP($single = true) {
    $ip   = array();
    if(!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ip = explode(',', str_replace(' ', '', $_SERVER['HTTP_X_FORWARDED_FOR']));
    if(!empty($_SERVER['HTTP_X_REAL_IP']))
        $ip = explode(',', str_replace(' ', '', $_SERVER['HTTP_X_REAL_IP']));
    if (!sizeof($ip))
        return $_SERVER['REMOTE_ADDR'];

    // mod remoteip case
    if ($ip[0] == $_SERVER['REMOTE_ADDR'])
        return $ip[0];

    // check the remote ip is already exists in the IPs
    if (!in_array($_SERVER['REMOTE_ADDR'], $ip)) {
        $ip[] = $_SERVER['REMOTE_ADDR'];
    }

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
    $cnt   = count($ip);
    $match = array();
    for($i = 0; $i < $cnt; $i++) {
        if(preg_match("/^$IPv4Address$/", $ip[$i], $match) || preg_match("/^$IPv6Address$/", $ip[$i], $match)) {
            $ip[$i] = $match[0];
        } else {
            $ip[$i] = '';
        }
        if(empty($ip[$i])) unset($ip[$i]);
    }
    $ip = array_values(array_unique($ip));
    if(!$ip[0]) $ip[0] = '0.0.0.0'; // for some strange reason we don't have a IP

    if(!$single) return join(',', $ip);

    // decide which IP to use, trying to avoid local addresses
    foreach($ip as $i) {
        if(preg_match('/^(::1|[fF][eE]80:|127\.|10\.|192\.168\.|172\.((1[6-9])|(2[0-9])|(3[0-1]))\.)/', $i)) {
            continue;
        } else {
            return $i;
        }
    }
    // still here? just use the first address
    return $ip[0];
}
