<?php
// Copyright 2004-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Jmol plugin for the MoniWiki
//
// http://jmol.sf.net
//
// $Id: jmol.php,v 1.7 2010/04/19 11:26:47 wkpark Exp $

function processor_jmol($formatter,$value="") {
    global $DBInfo;

    $verbs=array('#sticks'=>'wireframe 0.25',
                '#ball&stick'=>'wireframe 0.18; spacefill 25%',
                '#wireframe'=>'wireframe 0.1',
                '#cpk'=>'spacefill 80%',
                '#spacefill'=>'spacefill 80%',
                '#black'=>'background [0,0,0]',
                '#white'=>'background [255,255,255]',
                );
    $default_size="width='200' height='200'";
    $sep='';
    # old java behavior
    if (!empty($use_sep)) { $sep='|'; }

    $use_inline=1; // MOPAC format does not recognized with a param "loadInline"

    if ($value[0]=='#' and $value[1]=='!')
      list($line,$value)=explode("\n",$value,2);
    $dum=explode(' ',$line);
    $szarg = !empty($dum[1]) ? $dum[1] : '';
    if (!empty($szarg)) {
      $args= explode('x',$szarg,2);
      $xsize=intval($args[0]);$ysize=intval($args[1]);
    }

    $body = $value;
    //$args='<param name="emulate" value="chime" />';
    $args = '';
    $args.='<param name="progressbar" value="true" />';

    $script='set defaultColors Rasmol;set frank off;wireframe 0.18;spacefill 25%;';
    //$script='set frank off;wireframe 0.18;spacefill 25%;';
    if (!empty($DBInfo->jmol_script)) $script.=$DBInfo->jmol_script;


    while ($body and $body[0] == '#') {
        # extract first line
        list($line, $body) = explode("\n",$body, 2);

        # skip comments (lines with two hash marks)
        if ($line[1] == '#') continue;

        # parse the PI
        list($verb, $arg) = explode(' ',$line,2);
        $verb = strtolower($verb);
        $arg = rtrim($arg);

        if (array_key_exists($verb,$verbs)) {
            $script.=$verbs[$verb].';';
        }
    }

    if (!$args)
        $args.='<param name="style" value="sticks" />'."\n";
    if ($script)
        $args.='<param name="script" value="'.$script.'" />'."\n";
    $args.='<param name="mayscript" value="true" />'."\n";

    if (!empty($xsize)) {
      if ($xsize > 640 or $xsize < 100) $xscale=0.5;
      if ($xscale and ($ysize > 480 or $ysize < 100)) $yscale=0.6;
      $xscale=$xsize/640.0;
    
      if (empty($yscale)) $yscale=$xscale/0.5*0.6;

      $size="width='$xsize' height='$ysize'";
    } else $size=$default_size;

    $molstring='';
    $molscript='';
    if ($use_inline == 0) {
        $buff=str_replace("\n","\\n\"\n+\"",$body)."\n";
        $molstring= rtrim($buff);
        $molscript=&$script;
    }

    $cid=&$GLOBALS['_transient']['jmol'];
    $id=$cid+0;

    $js='';
    if ($id==0) {
        $jsize=str_replace(array(" ","'"),array(",",""),$size);
        $jsIE='';
        $base64js = '';
        $base64url = '';
        if (preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT'])) {
            $jsurl=qualifiedUrl($formatter->url_prefix.'/local/base64.js');
            $base64js="<script type='text/javascript' src='$jsurl'></script>";
            $base64url=$formatter->link_tag('/local/base64.js');
            $jsIE=<<<IEJS
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
<\/script>
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
            this.applet=applet;
            var self=this;
            var s=this.applet.getPropertyAsJSON('atomInfo') + "";
            var A = eval("("+s+")");
            if (A && A.atomInfo[0] != undefined && A.atomInfo[0].partialCharge) {
                var btn = document.createElement('button');
                var text = document.createTextNode('MEP');
                btn.appendChild(text);
                btn.onclick=function() {
                    self.applet.script('isosurface delete resolution 0 molecular map MEP translucent');
                };
                btns.appendChild(btn);
            }

            s=this.applet.getPropertyAsJSON('auxiliaryInfo') + "";
            A = eval("("+s+")");
            if (A != undefined && A.auxiliaryInfo.models && A.auxiliaryInfo.models[0].moData) {
                this.applet.script("mo fill nomesh;mo TITLEFORMAT \"Model %M, MO %I/%N |E = %E %U |?Symm = %S |?Occ = %O\"");
                var mos=A.auxiliaryInfo.models[0].moData.mos
                var len=mos.length;
                var sel = document.createElement('select');
                var opt = document.createElement('option');
                var text = document.createTextNode('-- MO --');
                sel.onchange=function() { self.applet.script(this.value); };
                sel.appendChild(opt);
                opt.appendChild(text);
                for (var i=len;i>0;i--) {
                    opt = document.createElement('option');
                    text = document.createTextNode('#' + i + ' E:' + mos[i-1].energy);
                    opt.appendChild(text);
                    sel.appendChild(opt);
                    opt.value= 'mo ' + i;
                }
                btns.appendChild(sel);
            }
        }

var _jmolTimer=new Array();
/*>*/
</script>\n
JS;
    }

    $cid++;
    $js.=<<<JS
<script type='text/javascript'>
/*<![CDATA[*/
addLoadEvent(function() {
    _jmolTimer[$id]=setInterval(function() {
        var model="$molstring";
        var script="$molscript";
        var applet=document.getElementById("jmolApplet$id");
        if (applet && applet.isActive != undefined) {
            if (model.length > 0 ) applet.loadInline(model);
            if (script.length > 0 ) applet.script(script);

            var btns=document.getElementById('jmolButton$id');

            addJmolBtns(applet,btns);
            clearInterval(_jmolTimer[$id]);
        }
    } ,500);
});
/*>*/
</script>\n
JS;

    $pubpath = $formatter->url_prefix.'/applets/JmolPlugin';

    if (!empty($use_inline)) {
        $molstring=$body;
        if (!empty($use_sep)) {
            $molstring=str_replace("\n",$sep."\n",$molstring);
        }
        if ($molstring[0] == ' ') $molstring=$sep."\n".$molstring;
        $args.="<param name='loadinline' value='$molstring' />";
    }

    return <<<APP
<div>
<applet name='jmolApplet$id' id='jmolApplet$id' code='JmolApplet.class' $size archive='$pubpath/JmolApplet.jar' codebase='$pubpath' mayscript='mayscript'>
        $args
    Loading a JmolApplet object.
</applet>
$js
<div>
<button onclick="javascript:open_image(document.jmolApplet$id.getPropertyAsJSON('image'))">JPEG</button>
<button onclick="javascript:document.jmolApplet$id.script('set minimizationSteps 10;set minimizationRefresh true;set minimizationCriterion 0.001; set loglevel 6;select *;minimize')">MM</button>
<span id='jmolButton$id'>
</span>
</div>
</div>
APP;
}

// vim:et:sts=4:sw=4:
?>
