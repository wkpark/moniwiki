<?php
# $title, $logo
# $menu, $icon, $upper_icon, $rss_icon, $user_link
# $themeurl
# $msg

include("plugin/login.php");
$login=macro_login($this);
?>
<div id='wikiHeader'>
<table border='0' width='100%' cellpadding='0' cellspacing='0'><tr>
<td rowspan='2' width='10%'><img alt='MoniWiki' src='<?php echo $this->url_prefix?>/imgs/moniwiki-logo.gif' /></td><td><?php echo $title?>
</td><td width='10%' rowspan='2'><?php echo $login?></td></tr>
<tr><td><?php echo $goto_form?></td></tr>
<tr><td><font size='-2'>&nbsp;</font></td></tr>
</table>
<table border='0' width='100%'><tr>
<td>&nbsp;&nbsp;&nbsp;&nbsp;<?php echo $menu?></td><td align='right'><?php echo $icons?><?php echo $upper_icon?><?php echo $rss_icon?><?php echo $home?>&nbsp;&nbsp;&nbsp;&nbsp;</td></tr>
</table>
</div>
<?php echo $msg?>
