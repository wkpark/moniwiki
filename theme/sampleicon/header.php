<?
# $title, $logo
# $menu, $icon, $upper_icon, $rss_icon, $user_link
# $themeurl
# $msg

include("plugin/login.php");
$login=macro_login($this);
?>
<div id='wikiHeader'>
<table border='0' width='100%' cellpadding='0' cellspacing='0'><tr>
<td rowspan='2' width='10%'><img alt='MoniWiki' src='<?=$this->url_prefix?>/imgs/moniwiki-logo.gif' /></td><td><?=$title?>
</td><td width='10%' rowspan='2'><?=$login?></td></tr>
<tr><td><?=$goto_form?></td></tr>
<tr><td><font size='-2'>&nbsp;</font></td></tr>
</table>
<table border='0' width='100%'><tr>
<td>&nbsp;&nbsp;&nbsp;&nbsp;<?=$menu?></td><td align='right'><?=$icons?><?=$upper_icon?><?=$rss_icon?><?=$home?>&nbsp;&nbsp;&nbsp;&nbsp;</td></tr>
</table>
</div>
<?=$msg?>
