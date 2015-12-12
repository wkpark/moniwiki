<?php
/**
 * Copyright 2003-2015 Won-Kyu Park <wkpark@gmail.com> all rights reserved.
 * distributable under GPLv2 see COPYING
 *
 * Abstract base class for MetaDB.
 *
 * @since  2003/04/15
 * @author Won-Kyu Park <wkpark@gmail.com>
 * @license GPLv2
 *
 */

class MetaDB
{
    function MetaDB()
    {
        return;
    }

    function getSisterSites($pagename, $mode = 1)
    {
        if ($mode)
            return '';
        return false;
    }

    function getTwinPages($pagename, $mode = 1)
    {
        if ($mode)
            return array();
        return false;
    }

    function hasPage($pgname)
    {
        return false;
    }

    function getAllPages()
    {
        return array();
    }

    function getLikePages($needle, $count = 1)
    {
        return array();
    }

    function close()
    {
    }
}

// vim:et:sts=4:sw=4:
