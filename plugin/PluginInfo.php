<?php
// Copyright 2008 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a PluginInfo macro plugin for the MoniWiki
//
// Date: 2008-12-12
// Name: PluginInfo
// Description: Show Plugin/Processor Info
// URL: MoniWiki:PluginInfoMacro
// Version: $Revision: 1.3 $
// Depend: 1.1.3
// License: GPL
//
// Usage: [[PluginInfo(name)]]
//
// $Id: PluginInfo.php,v 1.3 2010/08/23 15:14:10 wkpark Exp $

include_once(dirname(__FILE__).'/admin.php');

function macro_PluginInfo($formatter='',$value='') {
    global $_revision,$_release;

    $version=phpversion();
    $uname=php_uname();
    list($aversion,$dummy)=explode(" ",$_SERVER['SERVER_SOFTWARE'],2);

    if (!$value) {
        $num = getPlugin(true);
        return sprintf(_("Total %s plugin activated."),$num);
    }
    $file = getPlugin(strtolower($value));
    if (empty($file)) {
        if (($m = function_exists('macro_'.$value)) or ($m = function_exists('do_'.$value))) {
            return sprintf(_("%s is internal plugin."),$value);
        } else {
            return sprintf(_("%s plugin is not found."),$value);
        }
    }
    $info = get_plugin_info(dirname(__FILE__)."/$file.php");

    $name = !empty($info['Name']) ? $info['Name'].' ('.$value.')':$value;
    $version = !empty($info['Version']) ? $info['Version'] : '';
    $author = !empty($info['Author']) ? $info['Author'] : '';
    $license = !empty($info['License']) ? $info['License'] : '';
    $depend = !empty($info['Depend']) ? $info['Depend'] : '';
    $url = !empty($info['URL']) ? $info['URL'] : '';
    $desc = !empty($info['Description']) ? $info['Description'] : '';

    $msg=_("Description");
    $pl="<tr><td colspan='3'><fieldset class='collapsible collapsed'><legend>$msg: </legend><div>";
        $pl.='<strong>'._("Name").': '.$name."</strong><br />\n";
    if ($version)
        $pl.='<strong>'._("Version").': '.$version."</strong><br />\n";
    if ($author)
        $pl.='<strong>'._("Author").': '.$author."</strong><br />\n";
    if ($license)
        $pl.='<strong>'._("License").': '.$license."</strong><br />\n";
    if ($depend)
        $pl.='<strong>'._("Depend").': '.$depend."</strong><br />\n";

    if (empty($formatter->wordrule)) $formatter->set_wordrule();

    if ($url) {
        $url = preg_replace_callback("/(".$formatter->wordrule.")/",
            array(&$formatter, 'link_repl'), $url);
        $pl.='<strong>'._("URL").': '.$url."</strong><br />\n";
    }
    if ($desc) {
        $desc = preg_replace_callback("/(".$formatter->wordrule.")/",
            array(&$formatter, 'link_repl'), $desc);
        $pl.="<p><pre>$desc</pre></p>\n";
    }
    $pl.="</div></fieldset></td></tr>\n";
  return <<<EOF
<div class='pluginInfo'>
<table border='0' cellpadding='5'>
$pl
</table>
</div>
EOF;

// vim:et:sts=4:sw=4:
}
?>
