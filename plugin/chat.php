<?php
// Copyright 2003-2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a sample plugin for the MoniWiki
//
// Usage: [[Chat]]
//
// $Id$
// http://cvs.drupal.org/viewcvs/drupal/contributions/modules/chat/

function macro_Chat($formatter,$value,$options=array()) {
    global $DBInfo;
    $chat_script=&$GLOBALS['chat_script'];
    $ajax_script=&$GLOBALS['ajax_script'];

    $args=explode(',',$value);
    $itemnum=20; // default

    foreach ($args as $arg) {
        if (is_int($arg)) {
            $itemnum=$arg;
        } else {
            $tag=str_replace(' ','',ucfirst($arg));
        }
    }

    $url=$formatter->link_url($formatter->page->name,
        '?action=chat/ajax');
    ob_start();
    $formatter->ajax_repl('chat',
        array('value'=>'','room'=>'chat'.$tag,'item'=>$itemnum,
        'tz_offset'=>$formatter->tz_offset));
    //ob_end_flush();
    $msg=ob_get_contents();
    ob_end_clean();
    if (!$chat_script)
        $script=<<<EOF
<script type='text/javascript' src='$DBInfo->url_prefix/local/ajax.js'></script>
<script type='text/javascript' src='$DBInfo->url_prefix/local/chat.js'></script>
EOF;
    $ajax_script=1;
    $chat_script=1;
    return <<<EOF
$script
<div id="chat$tag">$msg</div>
<form onSubmit='return false'>
<input type='text' size='40' class='wikiChat' onkeypress='sendMsg(event,this,"$url","chat$tag",$itemnum);' />
</form>
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
    // %uD55C%uD558
    $value=_stripslashes($options['value']);
    $value=preg_replace('/%u([a-f0-9]{4})/i','&#x\\1;',$value);
    $itemnum=_stripslashes($options['item']);
    if ($itemnum > 50 or $itemnum <= 0) $itemnum=20;
    $room=escapeshellcmd(_stripslashes($options['room']));

    if (!file_exists($DBInfo->upload_dir.'/Chat')) {
        umask(000);
        mkdir($DBInfo->upload_dir.'/Chat',0777);
        umask(022);
    }
        
    if ($room== 'chat') {
        $log=$DBInfo->upload_dir.'/Chat/default.log';
    } else {
        $room=substr($room,4);
        $log=$DBInfo->upload_dir.'/Chat/'.$room.'.log';
    }
    $lines=array();
    $fp=fopen($log,'a+');
    while (is_resource($fp)) {
        fseek($fp,0,SEEK_END);
        if ($value) {
            fwrite($fp,time()."\t".$user->id."\t".rtrim($value)."\n");
        }
        if (($fz=filesize($log))==0) break;
        fseek($fp,0,SEEK_END);
        if ($fz < 512) {
            fseek($fp,0);
            $ll=rtrim(fread($fp,512));
            $lines=explode("\n",$ll);
            break;   
        }
        $a=-1;
        $end=0;
        $last='';
        $check=time();
        $date_from=$check-24*60*60; // one day
        while($date_from < $check and !feof($fp)){
            $a-=512;
            // if (-$a > $fz) { $a=-$fz; print 'wwwww';}
            fseek($fp,$a,SEEK_END);
            $l=fread($fp,512);
            while(($p=strrpos($l,"\n"))!==false) {
                $line=substr($l,$p+1).$last;
                $l=substr($l,0,$p);
                $dumm=explode("\t",$line,2);
                $check=$dumm[0];
                if ($date_from>$check) break;
                $lines[]=$line;
                if (sizeof($lines) >= $itemnum) { $check=0; break; }
                $last='';
            }
            $last=$l.$last;
        }
        fclose($fp);
        $lines=array_reverse($lines);
        break;   
    }

    #ob_start();
    #print_r($_GET);
    #$debug=ob_get_contents();
    #ob_end_clean();

    $out='';
    $smiley_rule='/(?<=\s|^|>)('.$DBInfo->smiley_rule.')(?=\s|$)/e';
    $smiley_repl="\$formatter->smiley_repl('\\1')";
    foreach ($lines as $line) {
        $dumm=explode("\t",$line,3);
        $line=gmdate("H:i:s",$dumm[0]+$options['tz_offset']).
            '&lt;['.$dumm[1].']> '.$dumm[2];
        $line=preg_replace($smiley_rule,$smiley_repl,$line);
        $out.='<li>'.preg_replace("/(".$formatter->wordrule.")/e",
            "\$formatter->link_repl('\\1')",$line).'</li>';
    }
    print '<ul>'.$debug.$out.'</ul>';
}

// vim:et:sts=4:
?>
