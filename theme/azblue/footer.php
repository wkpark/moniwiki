</div>
<?php

$banner= <<<FOOT
 <a href="http://validator.w3.org/check/referer"><img
  src="$this->themeurl/imgs/xhtml.png"
  border="0" width="80" height="15"
  align="middle"
  alt="Valid XHTML 1.0!" /></a>

 <a href="http://jigsaw.w3.org/css-validator/check/referer"><img
  src="$this->themeurl/imgs/css.png"
  border="0" width="80" height="15"
  align="middle"
  alt="Valid CSS!" /></a>

 <a href="http://moniwiki.sourceforge.net/"><img
  src="$this->themeurl/imgs/moniwiki-powered-thin.png"
  border="0" width="80" height="15"
  align="middle"
  alt="powered by MoniWiki" /></a>
FOOT;
?>
</div>
</div>
<div id='wikiFooter'>
<?php
  print $menu;
  print '<div align="center" id="wikiBanner">'.$banner.'<br />';
  if ($lastedit)
    print "last modified $lastedit $lasttime<br />";
  print 'Processing time '.$timer;
  print '</div>';
?>
</div>
</div></div></div></div></div></div>
</div></div>
