<?php
// Copyright 2003-2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a sample plugin for the MoniWiki
//
// Usage: [[Chat]]
//
// $Id$
// http://cvs.drupal.org/viewcvs/drupal/contributions/modules/chat/

function macro_Chat($formatter,$value) {
    global $DBInfo;
    $url=$formatter->link_url($formatter->page->name,
        '?action=chat/ajax');
    ob_start();
    $formatter->ajax_repl('chat',array('value'=>'','room'=>$value));
    //ob_end_flush();
    $msg=ob_get_contents();
    ob_end_clean();
    return <<<EOF
<script type='text/javascript' src='$DBInfo->url_prefix/local/ajax.js'></script>
<script type='text/javascript' src='$DBInfo->url_prefix/local/chat.js'></script>
<div id="chat0">$msg</div>
<input type='text' size='40' class='wikiChat' onChange='sendMsg(this,"$url","chat0")' />
EOF;
}

function do_chat($formatter,$options) {
    $formatter->send_header();
    $formatter->send_title();
    print macro_Chat($formatter,$options[value]);
    $formatter->send_footer("",$options);
    return;
}

function ajax_chat($formatter,$options) {
    global $DBInfo;
    $user=new User(); # get cookie
    if ($user->id != 'Anonymous') {
        $udb=new UserDB($DBInfo);
        $udb->checkUser($user);
    }
    $date=gmdate("[Y m:i]",time());
    $value=_stripslashes($options['value']);

    $itemnum=20; // XXX

    if (!file_exists($DBInfo->upload_dir.'/Chat')) {
        umask(000);
        mkdir($DBInfo->upload_dir.'/Chat',0777);
        umask(022);
    }
        
    if ($options['room']) {
        $md5=md5($options['room']);
        $log=$DBInfo->upload_dir.'/Chat/'.$md5.'.log';
    } else {
        $log=$DBInfo->upload_dir.'/Chat/default.log';
    }
    $lines=array();
    $fp=fopen($log,'a+');
    if (is_resource($fp)) {
        fseek($fp,0,SEEK_END);
        if ($value) {
            fwrite($fp,time()."\t".$user->id."\t".rtrim($value)."\n");
        }
        fseek($fp,0,SEEK_END);
        $fz=filesize($log);
        $a=-1;
        $end=0;
        $last='';
        $check=time();
        $date_from=$check-24*60*60; // one day
        while($date_from < $check and !feof($fp)){
            $a-=1024;
            if (-$a > $fz) { $a=-$fz;}
            fseek($fp,$a,SEEK_END);
            $l=fread($fp,1024);
            while(($p=strrpos($l,"\n"))!==false) {
                $line=substr($l,$p+1).$last;
                $dumm=explode("\t",$line,2);
                $check=$dumm[0] ? $dumm:$check;
                if ($date_from>$check) break;
                $lines[]=$line;
                if (sizeof($lines) >= $itemnum) { $check=0; break; }
                $last='';
                $l=substr($l,0,$p);
            }
            $last=$l.$last;
        }
        fclose($fp);
    }

    $out='';
    if (!$lines[0]) unset($lines[0]); // hack
    $lines=array_reverse($lines);
    foreach ($lines as $line) {
        $dumm=explode("\t",$line,3);
        $line=gmdate("[Y H:i]",$dumm[0]+$options['tz_offset']).
            $dumm[1].': '.$dumm[2];
        $out.=preg_replace("/(".$formatter->wordrule.")/e",
            "\$formatter->link_repl('\\1')",$line).'<br />';
    }
    print $out;
}

// vim:et:sts=4:
?>
