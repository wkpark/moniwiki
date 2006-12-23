<?php
# $title, $logo
# $menu, $icon, $upper_icon, $rss_icon, $user_link
# $msg
include_once("plugin/login.php");
include_once("plugin/RandomBanner.php");
include_once("plugin/Calendar.php");
include_once("plugin/trackback.php");
$login=macro_login($this);
?>
<div id='wikiHeader'>
<table border='0' width='100%' cellpadding='0' cellspacing='0'><tr>
<td rowspan='2' width='10%'><img src='<?php echo $this->url_prefix?>/imgs/moniwiki-logo.gif' alt='MoniWiki' /></td><td><?php echo $title?>
</td><td width='10%' rowspan='2'><?php echo $login?></td></tr>
<tr><td><?php echo $goto_form?></td></tr>
</table>
</div>
<div id='wikiIcon'><?php echo $upper_icon?><?php echo $icons?><?php echo $rss_icon?></div>
<div id='wikiMenu'><?php echo $menu?></div>
<?php echo $msg?>
<table border='0' width='100%'><tr valign='top'>
<td width='100%'>
