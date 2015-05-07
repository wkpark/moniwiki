<?php
/**
 * simplified common.php by wkpark @ gmail.com
 * since 2015/04/29
 */

function g4_get_member() {
    global $g4, $g5, $g4_root_dir;

    // init
    $member = array();

    if (!defined('G5_VERSION')) {
        include_once("$g4_root_dir/lib/constant.php");  // constants
    }
    include_once("$g4_root_dir/lib/common.lib.php"); // common library
    if (defined('G5_VERSION')) {
        include_once("$g4_root_dir/data/dbconfig.php"); // db configs
    } else {
        include_once("$g4_root_dir/dbconfig.php"); // db configs
    }

    if (defined('G5_VERSION')) {
        $connect_db = sql_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD);
        $select_db  = sql_select_db(G5_MYSQL_DB, $connect_db);
        $g5['connect_db'] = $connect_db;
        sql_query(" set names utf8 ");
    } else {
        $connect_db = sql_connect($mysql_host, $mysql_user, $mysql_password);
        $select_db = sql_select_db($mysql_db, $connect_db);
    }

    // is it a valid PHPSESSID ?
    if ($_REQUEST['PHPSESSID'] && $_REQUEST['PHPSESSID'] != session_id()) {
        $member['mb_id'] = '';
        return $member;
    }

    // already logged in
    if (!empty($_SESSION['ss_mb_id']))
        $member = get_member($_SESSION['ss_mb_id']);

    return $member;
}

// vim:et:sts=4:sw=4:
