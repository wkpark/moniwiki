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
<div id='pBodyRight'><div id='pBodyLeft'>
<div id='pBottomRight'><div id='pBottomLeft'>
<div id='pTopRight'><div id='pTopLeft'>

<div id='wikiPage'>
<div id='pBanSpace'></div>
<div id='pBanRight'><div id='pBanLeft'>
<div id='pBanner'>
<?php echo $DBInfo->sitename?>
</div>
<div id='goForm'>
<form name='go' id='go' action='' method='get' onsubmit="return moin_submit();">
<input type='text' name='value' size='20' accesskey='s' class='goto' style='width:120px' />
<input type='hidden' name='action' value='goto' />
<input type='submit' name='status' class='submitBtn' value='Go' style='width:35px;' />
</form>
</div>
<div id='pTitle'><?php echo $title?></div><div id='wikiHeader'>
<div id='pMenuRight'><div id='pMenuLeft'>
<div id='wikiMenuBar'>
<div id='wikiIcon'><?php echo $upper_icon.$icons.$rss_icon.$home?></div>
<?php echo $menu?>
</div>
</div></div>
</div>
</div>
<div class='clear'></div>
<?php echo $msg?>
<div id='container'>
<?php
# enable/disable sidebar
if ($this->_sidebar==1) :
?>
<div id='wikiSideMenu'>
<?php
print macro_login($this);
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
<div id='content'>
