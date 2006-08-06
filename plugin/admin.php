<?php
// Copyright 2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a admin plugin for the MoniWiki
//
// Plugin/Processor on/off plugin <!> experimental
//
// Usage: ?action=admin
//
// $Id$

function info_admin() {
    return array(
        'author'  => 'Won-Kyu Park <wkpark@kldp.org>',
        'date'    => '2006-07-30',
        'name'    => 'Admin',
        'desc'    => 'Admin Plugin',
        'url'     => 'MoniWiki:AdminPlugin',
        'version' => substr('$Revision$',1,-1),
        'depend'  => '1.1.3',
        'license' => 'GPL',
     );
}

function macro_admin($formatter,$value='',$options=array()) {
    global $DBInfo;

    if ($DBInfo->include_path)
        $dirs=explode(':',$DBInfo->include_path);
    else
        $dirs=array('.');

    // make plugins list
    foreach ($dirs as $dir) {
        $handle= @opendir($dir.'/plugin');
        if (!$handle) continue;
        while ($file= readdir($handle)) {
            if (is_dir($dir."/plugin/$file")) continue;
            if ($file{0}=='.') continue;
            $name= substr($file,0,-4);
            $plugins[strtolower($name)]= $name;
        }
    }
    // make processors list
    foreach ($dirs as $dir) {
        $handle= @opendir($dir.'/plugin/processor');
        if (!$handle) continue;
        while ($file= readdir($handle)) {
            if (is_dir($dir."/plugin/processor/$file")) continue;
            if ($file{0}=='.') continue;
            $name= substr($file,0,-4);
            $processors[strtolower($name)]= $name;
        }
    }

    ksort($plugins);
    ksort($processors);

    // get settings
    $sc=new Cache_text('settings');
    $pls=unserialize($sc->fetch('plugins'));
    $prs=unserialize($sc->fetch('processors'));

    $pl="<tr><th colspan='2'>Plugins</th></tr>\n";
    $i=0;
    foreach ($plugins as $p=>$v) {
        $ck= isset($pls[$p]) ? 'checked="checked"':'';
        $disabled=empty($ck) ? ' disabled':'';
        $pl.="<tr><th class='info$disabled'>".
            $p.'</th><td>'."$v</td><td><input type='checkbox' name='pl[$p]' value='$v' $ck/></td></tr>\n";
        ++$i;
    }
    $pl.="<tr><td colspan='2'>Total <b>$i</b></td></tr>\n";
    $pr="<tr><th colspan='2'>Processors</th></tr>\n";
    $j=0;
    foreach ($processors as $p=>$v) {
        $ck= isset($prs[$p]) ? 'checked="checked"':'';
        $disabled=empty($ck) ? ' disabled':'';
        $pr.="<tr><th class='info$disabled'>".
            $p.'</th><td>'."$v</td><td><input type='checkbox' name='pr[$p]' value='$v' $ck/></td></tr>\n";
        ++$j;
    }
    $pr.="<tr><td colspan='2'>Total <b>$j</b></td></tr>\n";

    $out="<form method='post' action=''><table><tr valign='top'><td><table>".$pl."</table></td>";
    $out.="<td><table>".$pr."</table></td></tr>";
    $out.='</table>';
    if (in_array($options['id'],$DBInfo->owners)) {
        $out.='<input type="hidden" name="action" value="admin" />';
        $out.='<input type="submit" value="Update" />';
    }
    $out.='</form>';
    return $out;
}

function do_admin($formatter,$options) {
    global $DBInfo;
    if (in_array($options['id'],$DBInfo->owners) and
            (is_array($options['pl']) or is_array($options['pr']))) {
        $formatter->send_header('',$options);
        $cp=new Cache_text('settings');
        $cpl=unserialize($cp->fetch('plugins'));
        $cpr=unserialize($cp->fetch('processors'));

        $out='';
        if (is_array($options['pl'])) {
            $ad=array_diff($options['pl'],$cpl);
            $de=array_diff($cpl,$options['pl']);

            $out.=!empty($ad) ?
                '<h2>'._("Enabled plugins").'</h2><ul><li>'.implode("</li>\n<li>",$ad).'</li></ul>':'';
            $out.=!empty($de) ?
                '<h2>'._("Disabled plugins").'</h2><ul><li>'.implode("</li>\n<li>",$de).'</li></ul>':'';
        }

        if (is_array($options['pr'])) {
            $ad=array_diff($options['pr'],$cpr);
            $de=array_diff($cpr,$options['pr']);
            $out.=!empty($ad) ?
                '<h2>'._("Enabled processors").'</h2><ul><li>'.implode("</li>\n<li>",$ad).'</li></ul>':'';
            $out.=!empty($de) ?
                '<h2>'._("Disabled processors").'</h2><ul><li>'.implode("</li>\n<li>",$de).'</li></ul>':'';
        }

        $cp->update('plugins',serialize($options['pl']));
        $cp->update('processors',serialize($options['pr']));
        $options['title']=_("Plugin/Processor settings are updated");
        $formatter->send_title('','',$options);
        print $out;
        $formatter->send_footer('',$options);
        return;
    }
    $options['title']=_("Enable/disable plugins and processors");
    $formatter->send_header('',$options);
    $formatter->send_title('','',$options);
    echo macro_admin($formatter,$options['value'],$options);
    $formatter->send_footer('',$options);
    return;
}

// vim:et:sts=4:
?>
