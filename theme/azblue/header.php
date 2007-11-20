<?php
# MoniWiki Theme by wkpark at kldp.org
# $Id$
#
if ($this->_sidebar) {
  include_once("plugin/login.php");
  include_once("plugin/RandomBanner.php");
  include_once("plugin/Calendar.php");
  $login=macro_login($this);
}
if ($DBInfo->use_tagging) {
  include_once("plugin/Keywords.php");
}
# theme options
#$_theme['sidebar']=1;

if ($this->_width) {
  print <<<EOF
<style type='text/css'>
#mainBody { width:$this->_width;}
</style>
EOF;
}

?>
<div id='topHeader'>
<!--
&middot; <a href='http://kldp.org'>KLDP.org</a> &middot;
<a href='http://kldp.net'>KLDP.net</a> &middot;
<a href='http://wiki.kldp.org'>KLDP Wiki</a> &middot;
<a href='http://bbs.kldp.org'>KLDP BBS</a> &middot;
-->
</div>
<div id='mainBody'>
<!--
<div id='topBanner'>
<img src="<?php echo $this->themeurl?>/imgs/kldpwikilogo.png"/>
</div>
-->
<?php if ($this->_topicon): ?>
<div id='topIcon'>
<a href='?action=edit'><img src='<?php echo $this->themeurl?>/imgs/record.png' alt='*' style='border:0' /></a>
<a href='?action=new'><img src='<?php echo $this->themeurl?>/imgs/add.png' alt='+' style='border:0' /></a>
<a href='?action=subscribe'><img src='<?php echo $this->themeurl?>/imgs/favorite.png' alt='#' style='border:0' /></a>
<a href='?action=rss_rc'><img src='<?php echo $this->themeurl?>/imgs/rss.png' alt='.)' style='border:0' /></a>
-->
</div>
<?php endif;?>
<div class='pBodyRight'><div class='pBodyLeft'>
<div id='pTopRight'><div id='pTopLeft'>
<div id='pBanSpace'></div>
<div id='wikiHeadPage'>
<?php
if ($this->popup!=1) :
?>
<?php if ($this->_topbanner): ?>
<div id='pBanRight'><div id='pBanLeft'>
 <div id='pBanner'>
<img src='<?php echo $DBInfo->logo_img?>' /><?php
  echo $DBInfo->sitename;
  if ($DBInfo->site_description) echo '<p class="siteDescription">'.$DBInfo->site_description.'</p>';
?>
 </div>
<?php endif; /* topbanner */?>
<div id='goForm'>
<form id='go' action='' method='get' onsubmit="return moin_submit(this);">
<div>
<input type='text' name='value' size='20' accesskey='s' class='goto' style='width:120px' />
<input type='hidden' name='action' value='goto' />
<input type='submit' name='status' class='submitBtn' value='Go' style='width:35px;' />
</div>
</form>
</div>
<div id='pTitle'>
<?php if (!$this->_topbanner and $this->_logo): ?>
<img src='<?php echo $DBInfo->logo_img?>' style='text-align:left;' alt='moniwiki' />
<?php endif; /* topbanner */?>
<?php echo $title?></div>
<?php if ($this->_topbanner): ?>
 </div>
</div>
<?php endif; /* topbanner */?>
<?php endif; /* popup */?>
</div>
</div></div>
</div></div>
<?php if ($this->_splash):?>
<div class='pBodyRight'><div class='pBodyLeft'>
 <div id='wikiSplash'>
 </div>
</div></div>
<?php endif; /* _splash */?>
<span class='clear' ><!-- for IE --></span>
<div class='pBodyRight'><div class='pBodyLeft'>
<div id='pBottomRight'><div id='pBottomLeft'>
<div id='wikiPage'>
<span class='clear'></span>
<?php if ($this->popup) :?>
&nbsp;<!-- oops!! firefox bug workaround :( -->
<?php else:?>
<div id='wikiHeader'>
 <div id='pMenuRight'><div id='pMenuLeft'>
  <div id='wikiMenuBar'>
   <div id='wikiIcon'><?php echo $upper_icon.$icons.$rss_icon?></div>
<?php echo $menu?>
  </div>
 </div></div>
</div>
<span class='clear'></span>
<?php endif; /* popup */?>
<?php echo $msg?>
<div id='container'>
<?php
# enable/disable sidebar
if ($this->_sidebar==1) :
?>
<div id='wikiSideMenu'>
<?php
if ($this->_login) print macro_login($this);
print '<div class="calendar">';
if ($options['id']=='Anonymous')
  print macro_calendar($this,"'Blog',blog,noweek,archive,center",'Blog');
else
  print macro_calendar($this,"'$options[id]',blog,noweek,archive,center",$options['id']);
print '</div>';
print '<div class="randomQuote">';
print macro_RandomQuote($this);
print '</div>';
print '<div class="randomPage">';
print macro_RandomPage($this,"4,simple");
print '</div>';
if ($DBInfo->use_tagging) {
  print "<div>";
  print macro_Keywords($this,"all,tour,limit=15");
  print "</div>";
}
?>
</div>
<?php
endif;

?>
<div id='mycontent'>
