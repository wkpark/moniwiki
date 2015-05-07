<?php
// Copyright 2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a msgfmt plugin for the MoniWiki
//
// $Id: msgfmt.php,v 1.3 2008/05/01 09:27:26 wkpark Exp $

function _pocheck($po) {
    global $DBInfo;
    include_once 'lib/Gettext/PO.php';

    $myPO = new TGettext_PO;
    if (true !== ($e = $myPO->load($po,0))) {
        print "Fail to load po file.\n";
        return $e;
    }

    $myMO = $myPO->toMO();

    $vartmp_dir=$DBInfo->vartmp_dir;
    $tmp=tempnam($vartmp_dir,"GETTEXT");
    #$tmp=$vartmp_dir."/GETTEXT.mo";

    if (true !== ($e = $myMO->save($tmp))) {
        print "Fail to compile mo file.\n";
        return $e;
    }
    unset($myPO, $myMO);
    chmod($tmp,0644);
    //unlink($tmp);

    return true;
}

function do_msgfmt($formatter,$options) {
    global $DBInfo;

    $po='';
    $domain='PoHello';
    if (isset($options['msgid']) or isset($options['msgstr'])) {
        # just check a single msgstr
        header("Content-type: text/plain");
        $date=date('Y-m-d h:i+0900');
        $charset=strtoupper($DBInfo->charset);
        if (_stripslashes($options['msgid']) != '""')
            $po=<<<POHEAD
msgid ""
msgstr ""
"Project-Id-Version: $domain 1.1\\n"
"POT-Creation-Date: $date\\n"
"PO-Revision-Date: $date\\n"
"Last-Translator: MoniWiki <nobody@localhost>\\n"
"Language-Team: moniwiki <ko@localhost>\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=$charset\\n"
"Content-Transfer-Encoding: 8bit\\n"\n\n

#: src/test.c\n
POHEAD;
        $po.= 'msgid '._stripslashes($options['msgid'])."\n";
        #$msg=preg_replace('/""(?!")/',"\"\n\"",
        #    _stripslashes($options['msgstr']));
        $msg=_stripslashes($options['msgstr']);
        $po.= 'msgstr '.$msg."\n";
        $po.= "\n\n";
        $ret=_pocheck($po,1);
        if ($ret == true) {
            print "true\n".$po;
        }
        return;
    }

    if ($options['po'] and $options['btn']) {
        $formatter->send_header('',$options);
        $formatter->send_title(sprintf(_("Translation of %s"),$options['page']),'',$options);

        $comment=$options['comment'] ? _stripslashes($options['comment']):
            "Translations are updated";
        $po=preg_replace("/(\r\n|\r)/","\n",
                    _stripslashes($options['po']));
        $formatter->page->write($po);
        $ret=$DBInfo->savePage($formatter->page,$comment,$options);
        if ($ret != -1) {
            print "<h2>"._("Translations are successfully updated.")."</h2>";
        } else {
            print "<h2>"._("Fail to save translations.")."</h2>";
        }
        $formatter->send_footer('',$options);
        return;
    }

    $msgkeys=array_keys($options);
    $msgids=preg_grep('/^msgid-/',$msgkeys);
    $msgstrs=preg_grep('/^msgstr-/',$msgkeys);

    if (sizeof($msgids) != sizeof($msgstrs)) {
        print "Invalid request.";
        return;
    }

    $rawpo= $formatter->page->_get_raw_body();
    $lines= explode("\n",$rawpo);

    $po='';
    $comment='';
    $msgid=array(); $msgstr=array();
    foreach ($lines as $l) {
        if ($l[0]!='m' and !preg_match('/^\s*"/',$l)) {
            if ($msgstr) {
                $mid=implode("\n",$msgid);
                $id=md5($mid);

                $msg=preg_replace("/(\r\n|\r)/","\n",
                    _stripslashes($options['msgstr-'.$id]));

                $sid=md5(rtrim($msg));
                if ($options['md5sum-'.$id] and $options['md5sum-'.$id]!= $sid){
                    $comment=preg_replace('/#, fuzzy\n/m','',$comment);
                    $comment=str_replace(', fuzzy','',$comment);
                }
                # fix msgstr
                #$msg=preg_replace('/(?!<\\\\)"/','\\"',$msg);
                $po.=$comment;
                $po.='msgid '.preg_replace('/(\r\n|\r)/',"\n",
                    _stripslashes($options['msgid-'.$id]))."\n";
                $po.='msgstr '.$msg."\n";

                # init
                $msgid=array();$msgstr=array();$comment='';
            } 
            if ($l[0]=='#' and $l[1]==',') {
                if ($comment) {$po.=$comment;$comment='';}
                $comment.=$l."\n";
            } else {
                if ($comment) {$po.=$comment;$comment='';}
                $po.=$l."\n"; continue;
            }
        } else if (preg_match('/^(msgid|msgstr)\s+(".*")\s*$/',$l,$m)) {
            if ($m[1]=='msgid') {
                $msgid[]=$m[2];
                continue;        
            }
            $msgstr[]=$m[2];
        } else if (preg_match('/^\s*(".*")\s*$/',$l,$m)) {
            if ($msgstr) $msgstr[]=$m[1];
            else $msgid[]=$m[1];
        } else {
            $po.=$l."\n";
        }
    }

    $formatter->send_header('',$options);
    $formatter->send_title(sprintf(_("Translation of %s"),$options['page']),'',$options);

    $e=_pocheck($po);
    #if ($e != true) return;
    #print $po;

    $url=$formatter->link_url($formatter->page->urlname);
    print "<form method='post' action='$url'>\n".
        "<input type='hidden' name='action' value='msgfmt' />\n";
    print "<input type='submit' name='btn' value='Save Translation ?' /> ";
    print "Summary:".
        " <input type='text' size='60' name='comment' value='Translations are updated' />".
        "<br />\n";
    if ($options['patch']) {
        include_once('lib/difflib.php');
        $rawpo=array_map(create_function('$a', 'return $a."\n";'),
            explode("\n",$rawpo));
        $newpo=array_map(create_function('$a', 'return $a."\n";'),
            explode("\n",$po));
        $diff = new Diff($rawpo, $newpo);

        $f = new UnifiedDiffFormatter;
        $f->trailing_cr="";
        $diffs = $f->format($diff);
        $sz=sizeof(explode("\n",$diffs));
        print "<textarea cols='80' rows='$sz' style='width:80%'>";
        print $diffs;
        print "</textarea>\n";
    }
    $po = _html_escape($po);
    print "<input type='hidden' name='po' value=\"$po\" />\n";
    print "</form>";

    $formatter->send_footer('',$options);
    return;
}

// vim:et:sts=4:
?>
