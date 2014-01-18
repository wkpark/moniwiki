<?php
// Copyright 2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a admin plugin for the MoniWiki
//
// Plugin/Processor on/off plugin <!> experimental
//
// Date: 2006-07-30
// Name: Admin
// Description: Admin Plugin
// URL: MoniWiki:AdminPlugin
// Version: $Revision: 1.6 $
// Depend: 1.1.3
// License: GPL
//
// Usage: ?action=admin
//
// $Id: admin.php,v 1.6 2010/09/14 19:50:11 wkpark Exp $

function get_plugin_info($plugin_file) {
    // wordpress style management
    $info=array();
    $fp=fopen($plugin_file,'r');
    if (is_resource($fp)) {
        while(1) {
            $l=fgets($fp,2048);
            if (!rtrim($l)) break; // XXX
            if ($l[0] == ' ' and $l[1] == '*') {
                if (preg_match('@^ \* ([A-Z][A-Za-z0-9]+(?:\s?[A-Z][A-Za-z0-9]+)?)\s?\:\s?(.*)@', $l, $m)) {
                    $k = strtolower($m[1]);
                    $v = $m[2];
                    $info[$k] = !empty($info[$k]) ? $info[$k] . ',' . $v : $v;
                } else if ($l[2] == ' ' and $l[3] != '@') {
                    $desc.=substr($l,3);
                } else if (substr($l,1,3)=='* @') {
                    $l=substr($l,4);
                    list($k,$v)=preg_split("/\s+/",rtrim($l),2);
                    $nk=strtolower($k);
                    $info[$nk]=!empty($info[$nk]) ? $info[$nk].','.$v:$v;
                }
            } else if ($l[1]=='/' and $l[2]==' ') {
                $l=substr($l,3);
                if (($p=strpos($l,':'))!== false) {
                    $k=substr($l,0,$p);
                    $v=trim(substr($l,$p+2));
                    $nk=strtolower($k);
                    $info[$nk]=!empty($info[$nk]) ? $info[$nk].','.$v:$v;
                }
            }
        }
        fclose($fp);
    }
    if (!$info) return array();

    $plugin_info = array();
    $fields = array('name', 'description', 'author', 'license', 'url', 'depend', 'author url');
    $alias = array('desc'=>'description', 'dependency'=>'depend', 'uri'=>'url', 'author uri'=>'author url', 'plugin name'=>'name');

    foreach ($info as $k=>$v) {
        $kk = $k;
        if (array_key_exists($k, $alias)) $kk = $alias[$k];
        if (in_array($kk, $fields)) {
            $plugin_info[ucfirst($k)] = $v;
        }
    }

    if (!empty($info['version']) and $info['version'][0]=='$' and substr($info['version'], 1, 9)=='Revision:') {
        $plugin_info['Version'] = substr($info['version'], 10, -1);
    }

    return $plugin_info;
}

function macro_admin($formatter,$value='',$options=array()) {
    global $DBInfo;

    if ($DBInfo->include_path)
        $dirs=explode(':',$DBInfo->include_path);
    else
        $dirs=array('.');

    $arena='plugin';
    $plcur=' class="current"';
    $prcur='';
    if ($options['arena']=='processor') {
        $arena='processor';
        $prcur=' class="current"';
        $plcur='';
    }
    $pdir=$arena == 'plugin' ? 'plugin':'plugin/processor';
    $tag=$arena == 'plugin' ? 'pl':'pr';
    
    // make plugins list
    foreach ($dirs as $dir) {
        $handle= @opendir($dir.'/'.$pdir);
        if (!$handle) continue;
        while ($file= readdir($handle)) {
            if (is_dir($dir.'/'.$pdir.'/'.$file)) continue;
            if ($file{0}=='.') continue;
            if (substr($file,-4)!='.php') continue;
            $name= substr($file,0,-4);
            $plugins[strtolower($name)]= $name;
            $pl_infos[strtolower($name)]=
                get_plugin_info($dir.'/'.$pdir.'/'.$file);
        }
    }

    ksort($plugins);

    //
    $formatter->set_wordrule(array('#camelcase'=>0));

    // get settings
    $sc=new Cache_text('settings');
    $pls = $sc->fetch($arena.'s');

    #$pl="<tr><th colspan='3'>"._($arena)."</th></tr>\n";
    $pl='';
    $i=0;
    foreach ($plugins as $p=>$v) {
        ++$i;
        $ck= isset($pls[$p]) ? 'checked="checked"':'';
        $disabled=empty($ck) ? ' disabled':'';
        $name=$pl_infos[$p]['Name'] ? $pl_infos[$p]['Name']:$p;
        $version=$pl_infos[$p]['Version'];
        $author=$pl_infos[$p]['Author'];
        $version=$pl_infos[$p]['Version'];
        $license=$pl_infos[$p]['License'];
        $depend=$pl_infos[$p]['Depend'];
        $url=$pl_infos[$p]['URL'];
        $desc=$pl_infos[$p]['Description'] ? $pl_infos[$p]['Description']:'';
        $pl.="<tr><th class='info$disabled' width='10%'>".
            $name.' '.$version.'</th><td>'."$v</td><td width='2%'><input type='checkbox' name='{$tag}[$p]' value='$v' $ck/></td></tr>\n";
        if ($author or $desc or $license) {
            $msg=_("Description");
            $pl.="<tr><td colspan='3'><fieldset class='collapsible collapsed'><legend>$msg: </legend>";
            if ($author)
                $pl.='<strong>'._("Author").': '.$author."</strong><br />\n";
            if ($license)
                $pl.='<strong>'._("License").': '.$license."</strong><br />\n";
            if ($depend)
                $pl.='<strong>'._("Depend").': '.$depend."</strong><br />\n";
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
            $pl.="</fieldset></td></tr>\n";
        }
    }
    $pl.="<tr><td colspan='3'>Total <b>$i</b></td></tr>\n";

    $out=<<<MENU
<ul id="admin-submenu">
    <li><a href="?action=admin&amp;arena=plugin"$plcur>Plugins</a></li>
    <li><a href="?action=admin&amp;arena=processor"$prcur>Processors</a></li>
</ul>
MENU;
    $out.="<form method='post' action=''><table algin='center'><tr valign='top'>".$pl.
        "</table>";
    if (is_array($DBInfo->owners) and in_array($options['id'],$DBInfo->owners)) {
        $out.='<input type="hidden" name="action" value="admin" />';
        $out.='<input type="submit" value="Update" />';
    }
    $out.='</form>';
    return $out;
}

function do_admin($formatter,$options) {
    global $DBInfo;
    if (is_array($DBInfo->owners) and in_array($options['id'],$DBInfo->owners) and
            (is_array($options['pl']) or is_array($options['pr']))) {
        $formatter->send_header('',$options);
        $cp=new Cache_text('settings');
        $cpl = $cp->fetch('plugins');
        $cpr = $cp->fetch('processors');

        $out='';
        if (is_array($options['pl']) and is_array($cpl)) {
            $ad=array_diff($options['pl'],$cpl);
            $de=array_diff($cpl,$options['pl']);
            $cp->update('plugins', $options['pl']);

            $out.=!empty($ad) ?
                '<h2>'._("Enabled plugins").'</h2><ul><li>'.implode("</li>\n<li>",$ad).'</li></ul>':'';
            $out.=!empty($de) ?
                '<h2>'._("Disabled plugins").'</h2><ul><li>'.implode("</li>\n<li>",$de).'</li></ul>':'';
        }

        if (is_array($options['pr']) and is_array($cpr)) {
            $ad=array_diff($options['pr'],$cpr);
            $de=array_diff($cpr,$options['pr']);
            $cp->update('processors', $options['pr']);
            $out.=!empty($ad) ?
                '<h2>'._("Enabled processors").'</h2><ul><li>'.implode("</li>\n<li>",$ad).'</li></ul>':'';
            $out.=!empty($de) ?
                '<h2>'._("Disabled processors").'</h2><ul><li>'.implode("</li>\n<li>",$de).'</li></ul>':'';
        }

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
