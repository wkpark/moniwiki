<?php
// Copyright 2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a msgfmt plugin for the MoniWiki
//
// $Id$

function _pocheck($po,$showpo=0) {
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
    unlink($tmp);

    print "OK\n";
    if ($showpo) print $po;
    return true;
}

function do_msgfmt($formatter,$options) {
    global $DBInfo;

    $po='';
    $domain='PoHello';
    if ($options['msgid'] and $options['msgstr']) {
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
        _pocheck($po,1);
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
    $msgid=array(); $msgstr=array();
    foreach ($lines as $l) {
        if ($l[0]!='m' and !preg_match('/^\s*"/',$l)) {
            if ($msgstr) {
                $mid=implode("\n",$msgid);
                $id=md5($mid);
                $po.='msgid '.preg_replace('/(\r\n|\r)/',"\n",
                    _stripslashes($options['msgid-'.$id]))."\n";

                $msg=preg_replace('/(\r\n|\r)/',"\n",
                    _stripslashes($options['msgstr-'.$id]));
                # fix msgstr
                #$msg=preg_replace('/(?!<\\\\)"/','\\"',$msg);
                $po.='msgstr '.$msg."\n";

                # init
                $msgid=array();$msgstr=array();
            }
            $po.=$l."\n"; continue;
        } else if (preg_match('/^(msgid|msgstr)\s+(".*")\s*$/',$l,$m)) {
            if ($m[1]=='msgid') {
                $msgid[]=$m[2];
                continue;        
            }
            $msgstr[]=$m[2];
        } else if (preg_match('/\s*(".*")\s*$/',$l,$m)) {
            if ($msgstr) continue;
            if ($msgid) $msgid[]=$m[1];
            continue;
        } else {
            $po.=$l."\n";
        }
    }

    header("Content-type: text/plain");

    $e=_pocheck($po);
    #if ($e != true) return;
    #print $po;

    include_once('lib/difflib.php');
    $rawpo=array_map(create_function('$a', 'return $a."\n";'),
            explode("\n",$rawpo));
    $po=array_map(create_function('$a', 'return $a."\n";'),
            explode("\n",$po));
    $diff = new Diff($rawpo, $po);

    $f = new UnifiedDiffFormatter;
    $f->trailing_cr="";
    $diffs = $f->format($diff);
    print $diffs;

    return;
}

// vim:et:sts=4:
?>
