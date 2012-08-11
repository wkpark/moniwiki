<?php
// Copyright 2008 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a cacheadmin plugin for the MoniWiki
//
// Usage: ?action=cacheadmin
//
// $Id: cacheadmin.php,v 1.3 2008/11/27 10:07:06 wkpark Exp $
//
/**
 * @author  Won-Kyu Park <wkpark@kldp.org>
 * @date    2006-08-05
 * @name    CacheAdmin
 * @desc    CacheAdmin Plugin
 * @url     MoniWiki:CacheAdminPlugin
 * @version $Revision: 1.3 $
 * @depend  1.1.1
 * @license GPL
 */

function macro_CacheAdmin($formatter,$value='',$options=array()) {
    global $Config;
    $protected=
        array('backlinks','blog','settings','blogchanges',
            'fullsearch','index','keylinks','keywords','keyword',
            'metawiki','pagelinks','pagelist','referer',
            'settings','title','trackback','.','..');
    $protected=array_flip($protected);

    $dir=&$Config['cache_dir'];
    $caches=array();
    $handle= @opendir($dir);
    while(1) {
        if (!$handle) break;
        while ($file= readdir($handle)) {
            if (is_dir($dir."/$file")) {
                if (isset($protected[$file])) continue;
                $caches[]= $file;
            }
        }
        break;
    }
    @closedir($handle);

    if (!empty($Config['cache_public_dir'])) {
        $dir=&$Config['cache_public_dir'];
        $pubcaches=array();
        $handle= @opendir($dir);
        while(1) {
            if (!$handle) break;
            while ($file= readdir($handle)) {
                if (is_dir($dir."/$file")) {
                    if ($file[0]=='.') continue;
                    $pubcaches[]= $file;
                }
            }
            break;
        }
        @closedir($handle);
    }

    if (!empty($caches)) {
        $j=0;
        $out="<table border='0'><tr>";
        foreach ($caches as $c) {
            ++$j;
            $out.="<td><input type='checkbox' name='val[]' value='$c'/></td><th class='info'>$c</th><td></td>";
            if ($j%3==0) $out.='</tr><tr>';
        }
        $out.='</tr></table>';
        $out= "<form method='post' action=''>$out";
    
        $out.="<input type='submit' value='purge caches' />";
        $out.="<input type='hidden' name='action' value='cacheadmin' />";

        $out.="</form>";
        $form1=$out;
        $out='';
    }

    if (!empty($pubcaches)) {
        $j=0;
        $out="<br /><table border='0'><tr>";
        foreach ($pubcaches as $c) {
            ++$j;
            $out.="<td><input type='checkbox' name='val[]' value='$c'/></td><th class='info'>$c</th><td></td>";
            if ($j%3==0) $out.='</tr><tr>';
        }
        $out.='</tr></table>';
        $out= "<form method='post' action=''>$out";
    
        $out.="<input type='submit' value='purge public caches' />";
        $out.="<input type='hidden' name='action' value='cacheadmin' />";
        $out.="<input type='hidden' name='type' value='public' />";

        $out.="</form>";
    }
    return $form1.$out;
}

function do_cacheadmin($formatter,$options) {
    global $Config;
    while ($_SERVER['REQUEST_METHOD']=='POST') {
        if (!in_array($options['id'],$Config['owners']) or !is_array($options['val'])) {
            $options['title']=_("You are not WikiMaster!!"); 
            break;
        }

        if ($options['type']!='public') $dir=$Config['cache_dir'];
        else $dir=$Config['cache_public_dir'];
        foreach ($options['val'] as $d) {
            $b=basename($d);
            if (is_dir($dir.'/'.$b)) {
                print "rmdir_r $b<br />";
                _rmdir_r($dir.'/'.$b,true);
            }
        }
        return;
    }
    $options['title']=$options['title'] ? $options['title']:_("Clear cache dirs"); 
    $formatter->send_header('',$options);
    $formatter->send_title('','',$options);
    $ret= macro_CacheAdmin($formatter,$options['value'],$options);
    print $ret;
    $formatter->send_footer('',$options);
    return;
}

// removes a directory and everything within it
// from http://kr.php.net/manual/en/function.rmdir.php#55075
function _rmdir_r($target,$verbose=false,$dry=false) {
    $verbose=$verbose or $dry;
    $exceptions=array('.','..');
    if (!$sourcedir=@opendir($target)) {
        if ($verbose)
            echo '<strong>Couldn&#146;t open '.$target."</strong><br />\n";
        return false;
    }
    while(false!==($sibling=readdir($sourcedir))) {
        if (!in_array($sibling,$exceptions)) {
            $obj=str_replace('//','/',$target.'/'.$sibling);
            if ($verbose)
                echo '<strong>Processing:</strong> '.$obj."<br />\n";
            if (is_dir($obj))
                _rmdir_r($obj,$verbose,$dry);
            if (is_file($obj)) {
                if ($dry)
                    echo "&nbsp;<strong>file:</strong> $obj ...<br />\n";
                else {
                    $result=@unlink($obj);
                    if ($verbose) {
                        if ($result)
                            echo "$obj has been removed<br />\n";
                        else
                            echo "<strong>Couldn&#146;t remove $obj</strong>";
                    }
                }
            }
        }
    }
    closedir($sourcedir);
    if ($dry) {
        echo "&nbsp;<strong>dir:</strong> $target ...<br />\n";
    } else {
        if ($result=@rmdir($target)) {
            if ($verbose)
                echo "Target directory has been removed<br />\n";
            return true;
        }
        if ($verbose)
            echo "<strong>Couldn&#146;t remove target directory</strong>";
    }
    return false;
}

// vim:et:sts=4:
?>
