<?php
// Copyright 2006-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a JME plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Since: 2006-07-20
// Name: a JME molecular editor plugin
// Description: a JME molecular editor plugin.
// URL: MoniWiki:JMEProcessor
// Version: $Revision: 1.2 $
// License: GPL
// Usage: [[JME(molname)]]
//
// $Id: JME.php,v 1.2 2010/04/19 11:26:46 wkpark Exp $

function _mol2gau($mol) {
    $line=explode("\n",$mol);
    if (preg_match('/radical/',$line[0]))
        $chmu='0 2';
    else if (preg_match('/cation/',$line[0]))
        $chmu='1 1';
    else if (preg_match('/anion/',$line[0]))
        $chmu='-1 1';
    else
        $chmu='0 1';
    $gau=<<<HEADER
%chk=
# ub3lyp/6-311g(d,p) OPT FREQ POP=full

from NIST: $line[0]
$chmu

HEADER;
    foreach ($line as $l) {
#    0.0000    0.0000    0.0000 H   0  0  0  0  0  1           0  0  0
        preg_match('/\s+([^\s]+)\s+([^\s]+)\s+([^\s]+)\s([A-Z]{1,2})\s+.*$/',$l,$m);
        if ($m)
            $gau.= $m[4].'  '.$m[1].'  '.$m[2].'  '.$m[3]."\n";
    }
    $gau.="\n";
    return $gau;
}

function macro_JME($formatter,$value) {
    global $DBInfo;

    $draw_dir=str_replace('./','',$DBInfo->upload_dir.'/JME');
    if (!file_exists($draw_dir)) {
        umask(000);
        mkdir($draw_dir, 0777);
    }
    $name=$value;
    $urlname=_rawurlencode($value);
    $dummy = explode('/', $name);
    $name = $dummy[count($dummy) - 1];
    $molname=$name.".mol";
    $now=time();

    $url=$formatter->link_url($formatter->page->name,"?action=jme&amp;value=$urlname&amp;now=$now");

    if (!file_exists($draw_dir."/$molname")) {
        if ($name)
            return "<a href='$url'>".sprintf(_("Draw a new molecule '%s'"),$name)."</a>";
        else
            return "<a href='$url'>"._("Draw a new molecule")."</a>";
    }

    $fp=fopen($draw_dir.'/'.$molname,'r');
    if ($fp) {
        $mol = '';
        while(!feof($fp)) $mol.=fgets($fp,2048);
        fclose($fp);
        $mol=str_replace("\r\n","|",$mol);
    }
    $pubpath=$DBInfo->url_prefix."/applets/JMEPlugin";
    $mol = _html_escape($mol);

    $cid = &$GLOBALS['_transient']['jsme'];
    $id = $cid + 0;

    return <<<APPLET
<script defer type="text/javascript" language="javascript" src="$pubpath/jsme/jsme.nocache.js"></script>
<script type='text/javascript'>
/*<![CDATA[*/
var jsmeApplet$id = null;
function jsmeOnLoad() {
            jsmeApplet$id = new JSApplet.JSME("jsme_applet$id", "600px", "440px", {
                // optional parameters
                "options": "depict,marker,markermenu,",
                //"options": "query,hydrogens,fullScreenIcon",
                //"smiles" : glutathione_smiles
                //"jme" : glutathione_JME
                "mol" : "$mol",
                //"chem" : glutathione_mol3 // use generic input file format with automatic chemical format detection
            });
}
/*>*/
</script>
        <div id="jsme_applet$id"></div>
APPLET;
}

function do_post_jme($formatter,$options) {
    global $DBInfo;
    if ($_SERVER['REQUEST_METHOD'] == 'POST' &&
            !$DBInfo->security->writable($options)) {
        $options['title'] = _("Page is not writable");
        return do_invalid($formatter,$options);
    }

    $draw_dir=str_replace("./",'',$DBInfo->upload_dir.'/JME');
    $pagename=$options['page'];

    $name = !empty($options['value']) ? $options['value']: (!empty($options['name']) ? $options['name'] : null);
    if (empty($name)) $name=time();
    else {
        $dummy = explode('/', $name);
        $name = $dummy[count($dummy) - 1];
    }

    if ($_SERVER['REQUEST_METHOD']=='POST' and $options['mol']) {
        $molname=$name.'.mol';
        $fp=fopen($draw_dir.'/'.$molname,'w');
        if ($fp) {
            fwrite($fp,$options['mol']);
            fclose($fp);
        }
        $formatter->send_header('',$options);
        $formatter->send_title(_("Molecule successfully added"),'',$options);
        $formatter->send_footer('',$options);
        return;
    }

    $mol = '';
    $fp = fopen($draw_dir.'/'.$name.".mol", 'r');
    if ($fp) {
        $mol = '';
        while(!feof($fp)) $mol.= fgets($fp, 2048);
        fclose($fp);
        $mol = str_replace("\r\n","|",$mol);
        $mol = _html_escape($mol);
    }

    $pubpath=$DBInfo->url_prefix."/applets/JMEPlugin";
    $formatter->register_javascripts('<script type="text/javascript" src="'.$pubpath.'/jsme/jsme.nocache.js"></script>');

    $formatter->send_header('',$options);
    $formatter->send_title(_("Edit Molecule"),'',$options);
    $script=<<<SCRIPT
<script type="text/javascript">
/*<![CDATA[*/
function setMolFile(obj) {
    var mol = jsmeApplet.molFile();
    obj.mol.value = mol;
}

function getGauFile(obj) {
    var mol = jsmeApplet.molFile();
    var lines = mol.split("\\n");
    var i=0;
    var gau="%chk=\\n# ub3lyp/6-311g(d,p) OPT FREQ POP=full\\n\\n";
    gau += obj.name.value + "\\n\\n0 1\\n";

    while (i < lines.length) {
        var mat = lines[i].match(/^\\s+([^\\s]+)\\s+([^\\s]+)\\s+([^\\s]+)\\s+([A-Z]{1,2})\\s+.*/);
        if (mat != null) {
            gau += mat[4] + ' ' + mat[1] + ' ' + mat[2] + ' ' + mat[3] + "\\n";
        }
        i++;
    }

    obj.mol.value = gau + "\\n";
}
/*]]>*/
</script>
SCRIPT;
    $pubpath=$DBInfo->url_prefix."/applets/JMEPlugin";
    print "<h2>"._("Edit new molecule")."</h2>\n";
    $name = _html_escape($name);
    print <<<FORM
$script
<script type='text/javascript'>
/*<![CDATA[*/
var jsmeApplet = null;
function jsmeOnLoad() {
    jsmeApplet = new JSApplet.JSME("jsme_applet", "600px", "440px", {
        // optional parameters
        "options": "multipart; autoez",
        //"options": "query,hydrogens,fullScreenIcon",
        //"smiles" : glutathione_smiles
        //"jme" : glutathione_JME
        "mol" : "$mol",
        //"chem" : glutathione_mol3 // use generic input file format with automatic chemical format detection
    });
}
/*>*/
</script>
<div id="jsme_applet"></div>
</div>
<form method="POST" action="">
<input type="hidden" name="action" value="jme" />
<input type="hidden" name="name" value="$name" />
<input type="submit" name="submit_button" value="Submit" onclick="setMolFile(this.form)" />
<input type="button" name="gau_button" value="Get Gaussian input" onclick="getGauFile(this.form)" />
<input type="button" value="Get Mol" onclick="setMolFile(this.form)" />
<input type="reset" value="reset" />
<div class="molecule">
<textarea cols="50" rows="20" name="mol" /></textarea></div>
</form>
FORM;

    $formatter->send_footer("",$options);
    return;
}

// vim:et:sts=4:sw=4:
