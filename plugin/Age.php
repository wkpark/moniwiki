<?php
// Copyright 2015 adx0389 <adx0389 at rigvedawiki.net>
// All rights reserved. Distributable under GPLv2 see COPYING
// a age calculator plugin for the MoniWiki
//
// Author: adx0389 <adx0389 at rigvedawiki.net>
// Description: Age Calculator plugin
// Since: 2015/10/09
//
// Param: year
// Param: year/month/date
//
// Usage: [[Age(YYYY)]] or[[Age(YYYY/mm/dd)]]

function macro_Age($formatter, $value = '') {
    if(empty($value))
    {
        $text = '';
    }
    else if(is_numeric($value))
    {
        $n_year = (int)date('Y');
        $text = $n_year - $value;
    }
    else
    {
        $now_date = getdate(time());
        $n_year = (int)date('Y');
        $n_month = (int)date('m');
        $n_day = (int)date('d');

        $values = explode('/', $value);
        $v_year = $values[0];
        $v_month = $values[1];
        $v_day = $values[2];

        $age = 0;
        $age = $n_year - $v_year;
        if(($n_month >= $v_month) &&
                ($n_day >= $v_day))
            $age = $age + 1;
        $text = $age;
    }
    return "$text";
}

// vim:et:sts=4:sw=4:
