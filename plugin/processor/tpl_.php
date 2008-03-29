<?php
// Copyright 2008 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Template_ processor for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2008-03-27
// Name: A Template_ processor
// Description: A Template_ Processor could process Template_ syntaxs
// URL: MoniWiki:TemplateUnderscore
// Version: $Revision$
// License: LGPL
//
// Usage: {{{#!tpl_
// {=date("Y-m-d h:i:s",time()) }
// }}}
//
// $Id$

include_once (dirname(__FILE__).'/../../lib/Template_.compiler.php');

function processor_tpl_(&$formatter,$source,$params=array()) {
    global $Config;
    #if (!$Config['use_tpl']) return $source;
    $cache= new Cache_text("tpl_",'2');

    if (!empty($source)) {
        $id=md5($source); $mtime=$formatter->page->mtime();
    } else if ($params['path']) {
        $id=md5_file($params['path']); $mtime=filemtime($params['path']);
    }
    $params['uniq']=$id;
    $params['formatter']=&$formatter;

    $params['cache_head']='/* Template_ '.__TEMPLATE_UNDERSCORE_VER__.' '.$id.($params['path']? ' '.$params['path']:'').' */';

    $TPL_VAR=&$formatter->_vars;

    if (!$formatter->preview and $cache->exists($id) and $cache->mtime($id) > $mtime) {
        $params['_vars']=&$formatter->_vars;
        $ret = $cache->fetch($id,0,$params);
        if ($ret === true) return '';
        if ($params['print']) return eval('?'.'>'.$ret.'<'.'?php ');
        if ($params['raw']) return $ret;
        ob_start();
        eval('?'.'>'.$ret.'<'.'?php ');
        $fetch = ob_get_contents();
        ob_end_clean();
        return $fetch;
    }

    $formatter->plugin_dir='plugin'; #
    $formatter->safe_mode=1; # XXX

    $compiler=new Template_Compiler_;

    if ($source[0]=='#' and $source[1]=='!')
        list($line,$source)=explode("\n",$source,2);
    if ($line) list($tag,$args)=explode(' ',$line,2);

    $out=$compiler->_compile_template($formatter, $source, $params);
    if (!$formatter->preview)
        $cache->update($id,$out);
    if ($params['print']) return eval('?'.'>'.$out.'<'.'?php ');
    if ($params['raw']) return $out;
    #print '<pre>'.(preg_replace('/</','&lt;',$out)).'</pre>';
    ob_start();
    eval('?'.'>'.$out.'<'.'?php ');
    $fetch = ob_get_contents();
    ob_end_clean();
    return $fetch;
}

// vim:et:sts=4:sw=4:

?>
