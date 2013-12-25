<?php
// Copyright 2008 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a TPL plugin using the Templete_ processor
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2008-12-17
// Name: a TPL plugin
// Description: a TPL plugin wapper for the Templete_ processor
// URL: MoniWiki:TPLProcessor
// Version: $Revision: 1.1 $
// License: GPL
//
// Usage: [[TPL({=md5(time())})]]
//
// $Id: TPL.php,v 1.1 2008/12/17 06:20:57 wkpark Exp $

function macro_TPL($formatter, $value = '', $params = array()) {
    if ($formatter->_macrocache and empty($params['call']))
        return $formatter->macro_cache_repl('TPL', $value);
    $formatter->_dynamic_macros['@TPL'] = 1;
    return $formatter->processor_repl('tpl_', $value, $params);
}

// vim:et:sts=4:sw=4:
