<?
# $title, $logo
# $menu, $icon, $upper_icon, $rss_icon, $user_link
# $msg
include_once("plugin/login.php");
include_once("plugin/RandomBanner.php");
include_once("plugin/Calendar.php");
$login=macro_login($this);
?>
<div id='wikiHeader'>
<table border='0' width='100%' cellpadding='0' cellspacing='0'><tr>
<td rowspan='2' width='10%'><img src='<?=$this->url_prefix?>/imgs/moniwiki-logo.gif' alt='MoniWiki' /></td><td><?=$title?>
</td><td width='10%' rowspan='2'><?=$login?></td></tr>
<tr><td><?=$goto_form?></td></tr>
<tr><td><font size='-2'>&nbsp;</font></td></tr>
</table>
<table border='0' width='100%'><tr>
<td><?=$menu?></td><td align='right'><?=$icons?><?=$upper_icon?><?=$rss_icon?><?=$home?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td></tr>
</table>
<?=$msg?>
</div>
<table border='0' width='100%'><tr valign='top'>
<td width='100%'>
