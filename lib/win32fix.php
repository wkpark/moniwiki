<?php

if (function_exists('iconv')) {
    function _win32fs_local($name) {
        global $Config;

        $new=iconv('utf-8','UHC',$name);
        if ($new) return $new;
        return $name; // silently ignore
    }

    function _win32fs_public($name) {
        global $Config;

        $new=iconv('UHC','utf-8',$name);
        if ($new) return $new;
        return $name;
    }
} else {
    function _win32fs_local($name) {
        return $name;
    }

    function _win32fs_public($name) {
        return $name;
    }
}

if (getenv('OS')=='Windows_NT' and strtolower($Config['charset']) == 'utf-8') {
    function _l_filename($name) {
        return _win32fs_local($name);
    }
    function _p_filename($name) {
        return _win32fs_public($name);
    }
} else {
    function _l_filename($name) {
        return $name;
    }
    function _p_filename($name) {
        return $name;
    }
}

// vim:et:sts=4:sw=4:
?>
