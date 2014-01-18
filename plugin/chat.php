<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a sample plugin for the MoniWiki
//
// Usage: [[Chat]]
//
// $Id: chat.php,v 1.12 2010/04/19 11:26:46 wkpark Exp $
// http://cvs.drupal.org/viewcvs/drupal/contributions/modules/chat/

function macro_Chat($formatter,$value,$options=array()) {
    global $DBInfo;
    $chat_script=&$GLOBALS['chat_script'];
    $ajax_script=&$GLOBALS['ajax_script'];

    $args=explode(',',$value);
    $itemnum=20; // default

    $tag = '';
    foreach ($args as $arg) {
        if (is_int($arg)) {
            $itemnum=$arg;
        } else if (preg_match('/[a-z\s]+/i',$arg)){
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
    if ($msg=='false') $msg=_("No messages");
    $script = '';
    if (empty($chat_script))
        $script=<<<EOF
<script type='text/javascript' src='$DBInfo->url_prefix/local/ajax.js'></script>
<script type='text/javascript' src='$DBInfo->url_prefix/local/chat.js'></script>
EOF;
    $ajax_script=1;
    $chat_script=1;
    return <<<EOF
$script
<span id="effect"></span>
<div class="wikiChat">
<script language='javascript'>
/*<![CDATA[*/
setInterval('sendMsg("poll",null,"$url","chat$tag",$itemnum)',10000);
setSound('pass','$DBInfo->url_prefix/local/pass.au');
/*]]>*/
</script>
<div id="chat$tag">$msg</div>
<form onSubmit='return false'>
<div class="chatWindow">
<input type='text' size='10' class='chatUser' /> <input type='text' size='40' class='chatMsg' onkeypress='sendMsg(event,this,"$url","chat$tag",$itemnum);' />
<input type='button' id='{$tag}soundon' class='soundOff' onclick="Sound('pass');OnOff(this)" />
</div>
</form>
</div>
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
    $user=&$DBInfo->user; # get cookie
    $id=$user->id;
    $nic='';
    $udb=&$DBInfo->udb;
    if (!empty($options['nic'])) {
        if (!$udb->_exists($options['nic'])) {
            $nic=' '.$options['nic'];
        } else if ($user->id=='Anonymous') {
            $nic=' '.$options['nic'].'_'.substr(md5($_SERVER['REMOTE_ADDR']),0,4);
        }
    }
    // %uD55C%uD558
    $value=_stripslashes($options['value']);
    $value=preg_replace('/%u([a-f0-9]{4})/i','&#x\\1;',$value);
    $nic=preg_replace('/%u([a-f0-9]{4})/i','&#x\\1;',$nic);
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
    if (!$value) {
        if (!file_exists($log)) {
            print 'false';
            return;
        }
        $mtime=filemtime($log);
        if (empty($options['laststamp']) or $mtime <= $options['laststamp']) {
            print 'false';
            return;
        }
    }
    
    $lines=array();
    $fp=fopen($log,'a+');
    while (is_resource($fp)) {
        fseek($fp,0,SEEK_END);
        if ($value)
            fwrite($fp,time()."\t".$user->id.$nic."\t".rtrim($value)."\n");

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

    $debug = '';
    #ob_start();
    #print_r($_GET);
    #$debug=ob_get_contents();
    #ob_end_clean();

    $out='';
    $formatter->set_wordrule();

    if (!empty($formatter->use_smileys) and empty($formatter->smiley_rule))
        $formatter->initSmileys();

    $save=$formatter->sister_on;
    $formatter->sister_on=0;
    $save2=$formatter->nonexists;
    $formatter->nonexists='always';
    foreach ($lines as $line) {
        list($time,$user,$msg)=explode("\t",$line,3);
        if (($p=strpos($user,' '))===false) {
            if ($user!='Anonymous') $user='['.$user.']';
        } else {
            $user='[wiki:'.$user.']';
        }
        $line='<span class="date">'.
            gmdate("H:i:s",$time+$options['tz_offset']).'</span>'.
            '<span class="user">&lt;'.$user.'></span>'.$msg;
        if (!empty($formatter->smiley_rule))
            $line=preg_replace_callback($formatter->smiley_rule,
                array(&$formatter, 'smiley_repl'), $line);
        $out = '<li>'.preg_replace_callback("/(".$formatter->wordrule.")/",
            array(&$formatter, 'link_repl'), $line).'</li>';
        #$out.='<li>'.$line.'</li>';
    }
    $formatter->sister_on=$save;
    $formatter->nonexists=$save2;
    if (!empty($options['action_mode']) and $options['action_mode']=='ajax') {
        $formatter->header('Expires','0');
        $formatter->header('Cache-Control','no-cache');
        $formatter->header('Pragma','no-cache');
    }
    $stamp='<span id="laststamp" style="display:none">'.time().'</span>';
    print '<ul>'.$debug.$out.'</ul>'.$stamp;
}

// vim:et:sts=4:
?>
