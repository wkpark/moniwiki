<?php
// Copyright 2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a po processor for the MoniWiki
//
// $Id: po.php,v 1.3 2008/05/01 09:27:26 wkpark Exp $

function processor_po($formatter,$value='') {
    if ($value[0]=='#' and $value[1]=='!')
        list($line,$value)=explode("\n",$value,2);
    if ($line) {
    }

    $lines= explode("\n",$value);
    unset($value);

    $out='';
    $msgstr=array();
    $msgid=array();

    $js=<<<JS
<script type="text/javascript">
/*<![CDATA[*/
function checkmsg(obj) {
    var tok=obj.name.split("-");
    var id=tok[1];

    var msgid=document.getElementsByName('msgid' + '-' + id)[0];
    var msgstr=document.getElementsByName('msgstr' + '-' + id)[0];

    var postdata = 'action=msgfmt/ajax&msgid=' +
        encodeURIComponent(msgid.value) + '&msgstr=' + encodeURIComponent(msgstr.value);

    var msg= HTTPPost(self.location, postdata);
    if (msg.substr(0,4) == 'true') {
        alert('OK\\n' + msg);
    } else {
        alert ('*** Error: AJAX msgfmt checker ***\\n' + msg);
    }
}
/*]]>*/
</script>
JS;

    foreach ($lines as $l) {
        if ($l[0]!='m' and !preg_match('/^\s*"/',$l)) {
            if ($msgstr) {
                $mid=implode("\n",$msgid);
                $msg=implode("\n",$msgstr);
                $sid=md5(preg_replace("/(\r\n|\r)/","\n",rtrim($msg)));
                $id=md5($mid);
                $msg = _html_escape($msg);
                $vmid = _html_escape($mid);
                $test= strpos($msg,"\n");
                $row=max(sizeof($msgstr),sizeof($msgid));
                $out.="<input type='hidden' name='msgid-$id' value=\"$vmid\" />";
                $out.="<input type='hidden' name='md5sum-$id' value=\"$sid\" />";
                $btn=
                "<input type='button' onclick='javascript:checkmsg(this)' name='check-$id' value='check' />";
                if ($row > 1) {
                    $out.="msgid $mid\n";
                    $out.="msgstr\n<textarea cols='80' rows='$row' name='msgstr-$id'>"
                        .$msg."</textarea>$btn\n\n";
                } else {
                    $sz=min(50,strlen($mid));
                    $out.="msgid $mid\n";
                    $out.="msgstr <input name='msgstr-$id' size='$sz' value=\"$msg\" /> $btn\n";
                }
                # reset
                $msgid=array(); $msgstr=array();
            }
            if ($l[0] == '#') {
                if ($l[1]==':')
                    $out.="<span class='posrc'>$l</span>\n";
                else if ($l[1]==',')
                    $out.="<span class='potype'>$l</span>\n";
                else if ($l[1]=='~')
                    $out.="<span class='poold'>$l</span>\n";
                else if ($l[1]==' ')
                    $out.="<span class='pocomment'>$l</span>\n";
                else $out.=$l."\n";
            } else {
                $out.=$l."\n";
            }
        } else if (preg_match('/^(msgid|msgstr)\s+(\".*\")$/',$l,$m)) {
            if ($m[1]=='msgid') {
                $msgid[]=$m[2];
            } else {
                $msgstr[]=$m[2];
            }
        } else if (preg_match("/^\s*(\".*\")\s*$/",$l,$m)) {
            if ($msgstr) $msgstr[]=$m[1];
            else $msgid[]=$m[1];
        }
    }
    #$text=str_replace(array('msgid','msgstr'),
    #        array('<span class="msgid">msgid</span>',
    #        '<span class="msgstr">msgstr</span>',
    #        '<span class="fuzzy">fuzzy</span>',)
    #        ,$value);
    #$out= preg_replace(array('/(%.)/','/(\\\\n)/'),
    #    array("<span class='posym'>\\1</span>",
    #    "<span class='potok'>\\1</span>",),
    #    $out);
    $link=$formatter->link_url($formatter->page->urlname,'?action=msgfmt');
    $formhead="<form method='post' action='$link'>"
         ."<input type='hidden' name='action' value='msgfmt'>";
    $formtail=
        "<input type='submit' value='Check all' />\n".
        "<input type='checkbox' name='patch' checked='checked' />: Show patch\n".
        "</form>\n";
    return "$js$formhead<pre>$out</pre>$formtail";
}

// vim:et:sts=4:
?>
