<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// A simple Timer class
//
// @since  2003/04/12
// @author wkpark@kldp.org
//
// $Id$
//

class Timer {
    var $timers = array();
    var $total = 0.0;

    function Timer() {
        $mt = explode(' ',microtime());
        $this->save = $mt[0] + $mt[1];
    }

    function Check($name="default") {
        $mt = explode(' ',microtime());
        $now = $mt[0] + $mt[1];
        $diff = $now - $this->save;
        $this->save = $now;
        if (isset($this->timers[$name]))
            $this->timers[$name]+= $diff;
        else
            $this->timers[$name] = $diff;
        $this->total+= $diff;
    }

    function Write() {
        $out = '';
        while (list($name,$d) = each($this->timers)) {
            $out.= sprintf("%10s :%3.4f sec (%3.2f %%)\n", $name, $d, $d/$this->total*100);
        }
        return $out;
    }

    function Total() {
        return sprintf("%4.4f sec\n", $this->total);
    }

    function Clean() {
        $this->timers = array();
    }
}

// vim:et:sts=4:sw=4:
