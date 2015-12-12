<?php
/**
 * Copyright 2003-2015 Won-Kyu Park <wkpark@gmail.com> all rights reserved.
 * distributable under GPLv2 see COPYING
 *
 * Abstract base class for the Counter
 *
 * @since  2003/05/02
 * @author Won-Kyu Park <wkpark@gmail.com>
 * @license GPLv2
 *
 */

class Counter_base
{
    function Counter_base($conf)
    {
    }

    function incCounter($page, $params = '')
    {
    }

    function pageCounter($page)
    {
        return 1;
    }

    function getPageHits($perpage = 200, $page = 0, $cutoff = 0)
    {
        return array();
    }

    function close()
    {
    }
}

// vim:et:sts=4:sw=4:
