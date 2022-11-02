<?php
// Copyright 2022 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPLv2 see COPYING
// a JSmol plugin for the MoniWiki
//
// https://jmol.sourceforge.net/
//

function processor_jsmol($formatter, $value = "") {
    global $DBInfo;

    $verbs = array('#sticks' => 'wireframe 0.25',
                 '#ball&stick' => 'wireframe 0.18; spacefill 25%',
                 '#wireframe' => 'wireframe 0.1',
                 '#cpk' => 'spacefill 80%',
                 '#spacefill' => 'spacefill 80%',
                 '#black' => 'background [0,0,0]',
                 '#white' => 'background [255,255,255]',
             );
    $default_size = "width='300' height='300'";

    $sep = '';
    //$use_sep = 1;
    # old jmol behavior
    if (!empty($use_sep)) {
        $sep = '|';
    }

    $use_inline = 1; // MOPAC format does not recognized with a param "loadInline"

    if ($value[0]=='#' and $value[1]=='!')
        list($line, $value) = explode("\n", $value, 2);
    $dum = explode(' ', $line);
    $szarg = !empty($dum[1]) ? $dum[1] : '';
    if (!empty($szarg)) {
        $args = explode('x', $szarg, 2);
        $xsize = intval($args[0]);
        $ysize = intval($args[1]);
    }

    $body = $value;

    $script = 'set defaultColors Rasmol;set frank off;wireframe 0.18;spacefill 25%;';
    $usesearch = 'true';
    //$script='set frank off;wireframe 0.18;spacefill 25%;';
    if (!empty($DBInfo->jmol_script)) $script .= $DBInfo->jmol_script;


    while ($body and $body[0] == '#') {
        # extract first line
        list($line, $body) = explode("\n", $body, 2);

        # skip comments (lines with two hash marks)
        if ($line[1] == '#') continue;

        # parse the PI
        list($verb, $arg) = explode(' ', $line, 2);
        $verb = strtolower($verb);
        $arg = rtrim($arg);

        if (array_key_exists($verb,$verbs)) {
            $script .= $verbs[$verb].';';
        } else if ($verb == '#nosearch') {
            $usesearch = 'false';
        }
    }

    if (!empty($xsize)) {
        if ($xsize > 640 or $xsize < 100) $xscale = 0.5;
        if ($xscale and ($ysize > 480 or $ysize < 100)) $yscale = 0.6;
        $xscale = $xsize/640.0;

        if (empty($yscale)) $yscale = $xscale/0.5*0.6;

        $size = "width='$xsize' height='$ysize'";
    } else {
        $size = $default_size;
        $xsize = 300;
        $ysize = 300;
    }

    $molstring = '';
    $molscript = '';
    if ($use_inline == 1) {
        $buff = str_replace("\n", "\\n\"\n+\"", $body)."\n";
        $molstring = rtrim($buff);
        $molscript = &$script;
    }

    $cid = &$GLOBALS['_transient']['jsmol'];
    $id = $cid + 0;

    $js = '';
    if ($id == 0) {
        $jsize = str_replace(array(" ", "'"), array(",", ""), $size);
        $jsIE = '';
        $base64js = '';
        $base64url = '';
        if (preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT'])) {
            $jsurl = qualifiedUrl($formatter->url_prefix.'/local/base64.js');
            $base64js = "<script type='text/javascript' src='$jsurl'></script>";
            $base64url = $formatter->link_tag('/local/base64.js');
            $jsIE = <<<IEJS
<script type='text/javascript'>
/*<![CDATA[*/
function hello() {
    function fixBase64(img) {
        var myimg= img.cloneNode(true);
        alert(myimg.src.substr(0,10));
        var m=null;
        if (m=myimg.src.match(/^(data:.*;base64,)$/i)) {
            //img.src=decode64(m[1]);
            alert('w');
        }
    }
    for (var i = 0; i < document.images.length; i++)
       fixBase64(document.images[i]);
}
/*>*/
</script>\n
IEJS;
            $jsIE=preg_replace("/(\r\n|\n|\r)+/","\\\\n\"\n+\"",$jsIE);
        }
        $js=<<<JS
$base64js
<script type='text/javascript'>
/*<![CDATA[*/
        function open_image(base64) {
            base64=base64.replace(/\ \|\ /g, "").replace(/\s/g, "");
            var img = eval("("+base64+")").image;
            var imgsrc = "data:image/jpeg;base64,"+img;

            var open=window.open('','_blank',"$jsize,menubar=1,toolbar=1,scrollbars=1,status=1,resizable=1");
            //var open=window.open('','_blank',"$jsize,menubar=0,toolbar=0,scrollbars=0,status=0");
            //var open=window.open(imgsrc,'_blank',"$jsize,menubar=0,toolbar=0,scrollbars=0,status=0");
            open.document.writeln(
                '<html><head><title>Image/JPEG</title><style>body {margin:0}</style>'
                +"$jsIE"
                +'</head><body>'
                +"<img src='" + imgsrc +"'" + " />"
                +'</body></html>');
            open.document.close();
            open.focus();
        }

        function addJmolBtns(applet,btns) {
            var s = Jmol.getPropertyAsJSON(applet, 'atomInfo');
            var A = eval("(" + s + ")");
            if (A && A.atomInfo[0] != undefined && A.atomInfo[0].partialCharge) {
                var btn = document.createElement('button');
                var text = document.createTextNode('MEP');
                btn.appendChild(text);
                btn.onclick = function() {
                    Jmol.script(applet, 'isosurface delete resolution 0 molecular map MEP translucent');
                };
                btns.appendChild(btn);
            }

            s = Jmol.getPropertyAsJSON(applet, 'auxiliaryInfo');
            if (s == '') return;

            A = eval("(" + s + ")");
            if (A != undefined && A.auxiliaryInfo.models && A.auxiliaryInfo.models[0].moData) {
                Jmol.script(applet, "mo fill nomesh;mo TITLEFORMAT \"Model %M, MO %I/%N |E = %E %U |?Symm = %S |?Occ = %O\"");
                var mos = A.auxiliaryInfo.models[0].moData.mos
                var len = mos.length;
                var sel = document.createElement('select');
                var opt = document.createElement('option');
                var text = document.createTextNode('-- MO --');
                sel.onchange = function() { Jmol.script(applet, this.value); };
                sel.appendChild(opt);
                opt.appendChild(text);
                for (var i = len; i > 0; i--) {
                    opt = document.createElement('option');
                    text = document.createTextNode('#' + i + ' E:' + mos[i-1].energy);
                    opt.appendChild(text);
                    sel.appendChild(opt);
                    opt.value= 'mo ' + i;
                }
                btns.appendChild(sel);
            }
        }

/*>*/
</script>\n
JS;
    }

    $pubpath = $formatter->url_prefix.'/applets/JmolPlugin';

    $cid++;
    $js.=<<<JS
<script type='text/javascript'>
/*<![CDATA[*/
(function() {

function initJmol() {
    Jmol._isAsync = false;

    // last update 2/18/2014 2:10:06 PM
    var jmolApplet$id; // set up in HTML table, below
    // logic is set by indicating order of USE -- default is HTML5 for this test page, though
    var s = document.location.search;

    // Developers: The _debugCode flag is checked in j2s/core/core.z.js,
    // and, if TRUE, skips loading the core methods, forcing those
    // to be read from their individual directories. Set this
    // true if you want to do some code debugging by inserting
    // System.out.println, document.title, or alert commands
    // anywhere in the Java or Jmol code.

    Jmol._debugCode = (s.indexOf("debugcode") >= 0);
}

jmol_isReady = function(applet) {
    Jmol._getElement(applet, "appletdiv").style.border="1px solid black"

    var btns = document.getElementById('jmolButton$id');

    Jmol.script(jmolApplet$id, 'load inline "' + "$molstring" + '";');

    addJmolBtns(jmolApplet$id, btns);
}

var Info = {
    width: $xsize,
    height: $ysize,
    debug: false,
    color: "0x000000",
    addSelectionOptions: $usesearch,
    use: "HTML5",   // JAVA HTML5 WEBGL are all options
    j2sPath: "$pubpath/jsmol/j2s", // this needs to point to where the j2s directory is.
    jarPath: "$pubpath",// this needs to point to where the java directory is.
    jarFile: "JmolApplet.jar",
    isSigned: false,
    script: "$molscript",
    serverURL: "$pubpath/jsmol/php/jsmol.php",
    readyFunction: jmol_isReady,
    disableJ2SLoadMonitor: true,
    disableInitialConsole: true,
    allowJavaScript: true
    //defaultModel: "\$dopamine",
    //console: "none", // default will be jmolApplet0_infodiv, but you can designate another div here or "none"
}

$(function() {
    initJmol();
    $("#appdiv").html(Jmol.getAppletHtml("jmolApplet$id", Info))
})

})();
var lastPrompt=0;
/*>*/
</script>\n
JS;
    $formatter->jqReady = true;
    $formatter->register_javascripts('<script defer type="text/javascript" src="'.$pubpath.'/jsmol/JSmol.min.nojq.js"></script>');

    return <<<APP
<div class="jmolControl">
<div id='appdiv'>
</div>
<button onclick="javascript:open_image(Jmol.getPropertyAsJSON(jmolApplet$id, 'image'))">JPEG</button>
<button onclick="javascript:Jmol.script(jmolApplet$id, 'set minimizationSteps 10;set minimizationRefresh true;set minimizationCriterion 0.001; set loglevel 6;select *;minimize;')">MM</button>
<span id='jmolButton$id'>
</span>
</div>
$js
APP;
}

// vim:et:sts=4:sw=4:
