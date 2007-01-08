</div>
</div>
<div id='wikiFooter'>
<?php

$banner= <<<FOOT
 <a href="http://validator.w3.org/check/referer"><img
  src="$this->themeurl/imgs/xhtml.png"
  style='border:0;vertical-align:middle' width="80" height="15"
  alt="Valid XHTML 1.0!" /></a>

 <a href="http://jigsaw.w3.org/css-validator/check/referer"><img
  src="$this->themeurl/imgs/css.png"
  style='border:0;vertical-align:middle' width="80" height="15"
  alt="Valid CSS!" /></a>

 <a href="http://moniwiki.sourceforge.net/"><img
  src="$this->themeurl/imgs/moniwiki-powered-thin.png"
  style='border:0;vertical-align:middle' width="80" height="15"
  alt="powered by MoniWiki" /></a>
FOOT;

  print $menu;
  print '<div style="align:center" id="wikiBanner">'.$banner.'<br />';
  if ($lastedit)
    print "last modified $lastedit $lasttime<br />";
  print 'Processing time '.$timer;
  print '</div>';
?>
</div>
</div></div></div></div></div></div>
</div>
</div>
</div>
