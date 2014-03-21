<?php
// Copyright 2006-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a bug track system plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2006-01-09
// Name: BugTrackingSystem
// Description: a Simpl Bug Track System for MoniWiki
// URL: MoniWiki:BugTrackingSystem
// Version: $Revision: 1.5 $
// License: GPL
//
// $Id: bts.php,v 1.5 2010/04/19 11:26:47 wkpark Exp $

include_once("lib/metadata.php");

function _get_btsConfig($raw) {
    $meta='';
    $body=&$raw;
    while(true) {
        #list($line,$body)=explode("\n",$body,2);
        $tmp=explode("\n",$body,2);
        $line = $tmp[0];
        $body = isset($tmp[1]) ? $tmp[1] : '';
        if (isset($line[0]) and $line[0]=='#') continue;
        if (strpos($line,':')===false or trim($line)=='') break;
        $meta.=$line."\n";
    }

    return getMetadata($meta,1);
}

function processor_bts($formatter,$value='',$options='') {
    global $DBInfo;

    $rating_script=&$GLOBALS['rating_script'];

    $script=<<<SCRIPT
<script type="text/javascript">
/*<![CDATA[*/
/* from bugzilla script with small fix */
  /* Outputs a link to call replyToComment(); used to reduce HTML output */
  function addReplyLink(id) {
    /* XXX this should really be updated to use the DOM Core's
     * createElement, but finding a container isn't trivial */
    document.write('[<a href="#add_comment" onclick="replyToComment(' + 
        id + ');">reply<' + '/a>]');
  }

  /* Adds the reply text to the `comment' textarea */
  function replyToComment(id) {
    /* pre id="comment_name_N" */
    var text_elem = document.getElementById('comment_text_'+id);
    var text = getText(text_elem);

    /* make sure we split on all newlines -- IE or Moz use \\r and \\n
     * respectively */
    text = text.split(/\\r|\\n/);

    var replytext = "";
    for (var i=0; i < text.length; i++) {
        replytext += "> " + text[i] + "\\n"; 
    }

    replytext = "(In reply to comment #" + id + ")\\n" + replytext + "\\n";

    /* <textarea name="savetext"> */
    var textarea = document.getElementsByTagName('textarea');
    textarea[0].value += replytext;

    textarea[0].focus();
  }

  if (!Node) {
    /* MSIE doesn't define Node, so provide a compatibility array */
    var Node = {
        TEXT_NODE: 3,
        ENTITY_REFERENCE_NODE: 5
    };
  }

  /* Concatenates all text from element's childNodes. This is used
   * instead of innerHTML because we want the actual text (and
   * innerText is non-standard) */
  function getText(element) {
    var child, text = "";
    for (var i=0; i < element.childNodes.length; i++) {
        child = element.childNodes[i];
        var type = child.nodeType;
        if (type == Node.TEXT_NODE || type == Node.ENTITY_REFERENCE_NODE) {
            text += child.nodeValue;
        } else {
            /* recurse into nodes of other types */
            text += getText(child);
        }
    }
    return text;
  }
/*]]>*/
</script>
SCRIPT;

    if ($value[0]=='#' and $value[1]=='!')
        list($arg,$value)=explode("\n",$value,2);
    if (!empty($arg)) {
        # get parameters
        list($tag, $user, $date, $title)=explode(" ",$line, 4);

        if (preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/',$user))
        $user="Anonymous[$user]";

        if ($date && $date[10] == 'T') {
            $date[10]=' ';
            $time=strtotime($date.' GMT');
            $date= '@ '.date('Y-m-d [h:i a]',$time);
        }
    }

    $bts_conf='BugTrack/Config';
    if ($DBInfo->hasPage($bts_conf)) {
        $p=new WikiPage($bts_conf);
        $config_raw=$p->get_raw_body();
        $confs=_get_btsConfig($config_raw);
        #print_r($confs);
    }
    
    $body=$value;
    # parse metadata
    $meta='';
    while(true) {
        list($line,$body)=explode("\n",$body,2);
        if (isset($line[0]) and $line[0]=='#') continue;
        if (strpos($line,':')===false or !trim($line)) break;
        $meta.=$line."\n";
    }

    $metas=getMetadata($meta);
    $head="##[[InputForm(form:get:bts)]]\n##[[HTML(<table width='100%'><tr><td @@ valign='top'>)]]\n";
    $extra='';
    $attr='<tablewidth="100%">';
    $sep=1;
    foreach ($metas as $k=>$v) {
        $kk=$k;
        if (in_array($k,array('Version','Component'))) {
            $kk=str_replace(' ','-',ucwords($metas['Product'])).'-'.$k;
        }
        if ($k[0]=='X' and $k[1]=='-') {
            if (isset($confs[$kk]))
                $v='[[InputForm(:'._($kk).':'.str_replace($v,$v.' 1',$confs[$kk]).')]]';
            $k=substr($k,2);
            if (substr($k,0,9) =='Separator') {
                $sep++;
                $head.="\n##\n##[[HTML(</td><td @@ valign='top'>)]]\n";
                $attr='<tablewidth="100%">';
            } else {
                if (substr($k,0,4)=='Date') $v='[[DateTime('.$v.')]]';
                $head.="||".$attr." ''".$k."'' || ".$v." ||\n";
                $attr='';
            }
        } else {
            if ($k=='Summary' or $k=='Keywords') {
                $v=str_replace(':','&#58;',$v);
                $v='[[InputForm(input:'._($k).':'.$confs[$k].':'.$v.')]]';
                $extra.="|| '''"._($k)."'''''':'''||$v||\n";
            } else {
                if (isset($confs[$kk]))
                    $v='[[InputForm(:'._($kk).':'.str_replace($v,$v.' 1',$confs[$kk]).')]]';
                $head.="||".$attr."<width='30%'> '''"._($k)."'''''':'''||".$v." ||\n";
                $attr='';
            }
        }
    }
    $attr='width="100%"';
    if ($sep > 1) $attr='width="'.(100/$sep).'%"';
    $head=preg_replace('/@@/',$attr,$head);
    
    $head.=
        "\n##\n##[[HTML(</td></tr></table>)]]\n".
        $extra."\n".
        "[[InputForm(submit:"._("Save Changes").")]]\n##[[InputForm]]";
    #print '<pre>'.$head.'</pre>';
    print <<<HEAD
<fieldset id="bts-properties"><legend>Change Properties</legend>
HEAD;
    $formatter->send_page($head,$options);
    print <<<TAIL
</fieldset>
TAIL;

    if ($body) {
        $options['nosisters']=1;

        $copy=$body;
        $hidden='';
        #list($comment,$copy)=explode("----\n",$copy,2);
        $tmp=explode("----\n",$copy,2);
        $comment = $tmp[0];
        $copy = isset($tmp[1]) ? $tmp[1] : '';
        while(!empty($comment)) {
            #list($comment,$copy)=explode("----\n",$copy,2);
            $tmp=explode("----\n",$copy,2);
            $comment = $tmp[0];
            $copy = isset($tmp[1]) ? $tmp[1] : '';
            if (preg_match('/^Comment-Id:\s*(\d+)/i',$comment,$m)) {
                list($myhead,$my)=explode("\n\n",$comment,2);
                $hidden.='<pre style="display:none;" id="comment_text_'.$m[1].'">'._html_escape($my).'</pre>';
            }
        }

        ob_start();

        # add some basic rule/repl for bts
        $rule="/----\nComment-Id:\s*(\d+)\n".
            "From:\s*([^\n]+)\nDate:\s*([^\n]+)\n\n/im";
        $repl="----\n'''Comment-Id:''' [#c\\1][#c\\1 #\\1] by \\2 on [[DateTime(\\3)]] [reply \\1]\n\n";
        $body=preg_replace($rule,$repl,$body);

        $formatter->quote_style='bts-comment';
        $options['usemeta']=1;

        #
        $formatter->baserule[]="/^((-=)+-?$)/";
        $formatter->baserule[]="/ comment #(\d+)\b/";
        $formatter->baserule[]="/Bug #?(\d+)\b/";
        $formatter->baserule[]="/\[reply (\d+)\]/";

        $formatter->baserepl[]="<hr />\n";
        $formatter->baserepl[]=" comment [#c\\1 #\\1]";
        $formatter->baserepl[]="wiki:BugTrack:\\1";
        $formatter->baserepl[]="<script type='text/javascript'><!--
            addReplyLink(\\1); //--></script>";

        #
        $formatter->send_page($body,$options);
        $msg= ob_get_contents();
        ob_end_clean();
    }
    $msg.= $formatter->macro_repl('Comment(meta)','',$options);
    if (!empty($bts_script)) return $msg.$hidden;
    $bts_script=1;
    return $script.$msg.$hidden;
}

// vim:et:sts=4::
?>
