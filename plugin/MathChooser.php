<?php
// Copyright 2008-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a sample plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2008-12-01
// Name: MathChooser
// Description: Latex symbol selector
// URL: MoniWiki/MathChooserPlugin
// Version: $Revision: 1.9 $
// License: GPL
//
// Usage: [[MathChooser]]
//
// $Id: MathChooser.php,v 1.9 2010/08/23 15:14:10 wkpark Exp $

define('USER_LATEX_MAP','LatexSymbolTable');
function macro_MathChooser($formatter,$value) {
    global $DBInfo;

    $js=<<<JS
<script language="javascript" type="text/javascript">
/*<![CDATA[*/

var currentMenu=false;

function menuToogle(el)
{
  if (!currentMenu) currentMenu=el.parentNode.childNodes[0];
  currentMenu.className = '';
  document.getElementById('toolbar_'+currentMenu.title).style.display='none';

  currentMenu = el;
  el.className = 'active';
  document.getElementById('toolbar_'+el.title).style.display='block';
}

function showBig(elm, e)
{
    var div=document.getElementById('magnify');
    while (div.firstChild) div.removeChild(div.firstChild);

    var img = new Image();
    //img.src = 'http://www.codecogs.com/gif.latex?\\\\200dpi ' + elm.title;
    var sep = '?';
    var url = decodeURIComponent(String(self.location));
    var i;
    if ((i = url.indexOf(_ap)) != -1) url = url.substr(0,i); // _ap is global variable. ? or &
    img.src = url + _ap + 'action=latex2png&dpi=200&value=$' + encodeURIComponent(elm.title) +'$';
    div.appendChild(img);

    var pos = getPos(elm);
    div.style.left = pos.x + elm.clientWidth + 20 + 'px';
    div.style.top = pos.y + elm.clientHeight + 10 + 'px';

    div.style.display='block';
    div.style.position='absolute';
    elm.onmouseout= function() { document.getElementById('magnify').style.display='none';}
}

/*]]>*/
</script>
JS;

    $latex_map=USER_LATEX_MAP;
    $lines=array();
    if ($DBInfo->hasPage($latex_map)) {
        $p=$DBInfo->getPage($latex_map);
        $lines=explode("\n",($p->get_raw_body()));
    }

    $mytools=array();
    if (!empty($lines)) {
        $o='';
        foreach ($lines as $l) {
            $l=rtrim($l);
            if (empty($l)) continue;
            if ($l{0}=='#') continue;
            if (substr($l,-1)=='&') { $o.=$l; continue; }
            else if (!empty($o)) { $l=$o.$l; $o='';}
            list ($k,$v)=explode(' ',$l,2);
            $mytools[$k]=$v;
        }
    }

    $mtools=array(
        'Greek'=>'\alpha & \beta & \gamma & \delta & \epsilon & \zeta & \eta & \theta & \iota & \kappa &
\lambda & \mu & \nu & \xi & \o & \pi & \rho & \sigma & \tau & \upsilon & \phi & \chi & \psi & \omega &
\Gamma & \Lambda & \Sigma & \Psi & \Delta & \Xi & \Upsilon & \Omega & \Theta & \Pi &\Phi &
\Re & \Im & \aleph & \hbar & \imath & \jmath',
        'Math'=>'\frac{a}{b} & a^{b} & a_{b} & \sqrt{a} & \sqrt[n]{a} & \sum & \sum_{a}^{b} & \prod &
\prod_{a}^{b} & \int & \int_{a}^{b} & \int_{-\infty}^{\infty} & \oint & \mathop{\lim}\limits_{a \to \infty}',
        'Symbol'=>'+ & - & \pm & \mp & * & = & \div & \equiv & \sim & \approx & \ne & \doteq & \cong & \propto &
\forall & \exists & \neg & \vee & \wedge & \in & \ni &
\subset & \supset & \subseteq & \supseteq & \emptyset &
\ldots & \nabla & \partial & \prime & \circ & < & > & \le & \ge & \ll & \gg &
\text{\euro} & \mathdollar & \mathsterling &
\leftarrow & \rightarrow & \leftrightarrow'
    );

    $mtools=array_merge($mtools,$mytools);

    $out='';
    $tab='';
    $attr=' class="active"';
    $sty=' style="display:block"';
    foreach ($mtools as $k=>$tool) {
        $tool= trim($tool);
        $tmp= explode('&',$tool);
        $col= str_repeat('|@{\hspace{0.2pt}}c@{}',sizeof($tmp)); // @{}: no spacing, @{\hspace{0.2pt}
        $tex= '\displaystyle '.implode('& \displaystyle ',$tmp);
        $tex = "$$\n\\begin{array}{ $col }\n$tex\n\\end{array}\n$$\n";

        $retval = false;
        $params = array();
        $params['retval'] = &$retval;
        
        $my = $formatter->macro_repl('latex2png',$tex, $params);
        if (empty($params['retval'])) {
            $toolbar = $my;
            $turl = $my;
        } else {
            $toolbar = $params['retval'];
            $turl = $my;
        }
        $tab.='<li title="'.$k.'"'.$attr.' onclick="menuToogle(this)"><span>'._($k).'</span></li>';
        $attr='';

        $im = imagecreatefrompng($toolbar);
        $col = imagecolorallocate($im, 0, 0, 0);
        list($width, $height, $type, $attr) = getimagesize($toolbar);

	$toolurl=qualifiedUrl($turl); // XXX

        $x= 0;
        $c= imagecolorat($im,0,0);
        $xpos= array();
        while($x < $width) {
          if ($c == imagecolorat($im,$x++,0)) {
            $xpos[]=$x;
          }
        }
        $sz=sizeof($xpos);
        $out.="<div id='toolbar_$k'$sty><ul class='toolbar'>\n";
        //$out.="<div id='toolbar_$k'$sty><ul class='toolbar' style='height:{$height}px'>\n";
        for ($i=1;$i<$sz;$i++) {
          $w=($xpos[$i]-$xpos[$i-1]-1);
          $x=$xpos[$i-1];
          $tex=trim($tmp[$i-1]);
          $mouseover=' onmouseover="showBig(this,event)" ';
          $out.= "<li><a href='#' $mouseover title='$tex' ".
            " onclick=\"return insertTags('$ ',' $','".str_replace('\\','\\\\',$tex)."',2)\">".
            "<div style='background:url($toolurl);width:{$w}px;height:{$height}px;background-position:-{$x}px 0px;'></div></a></li>\n";
        }
        $out.="\n</ul></div>\n";
        $sty=' style="display:none"';
    }
    $formatter->register_javascripts($js);
    return <<<EOF
<div id='mathChooser' style='display:block'>
<ul class='tabs'>$tab</ul>
<div style='clear:both;'></div>
$out
</div>
<div id='magnify' style='display:none'></div>
<div style='clear:both;'></div>
EOF;
}

// vim:et:sts=4:
?>
