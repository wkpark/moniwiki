<?
# $title, $logo
# $menu, $icon, $upper_icon, $rss_icon, $user_link
# $msg
include_once("plugin/RandomBanner.php");
include_once("plugin/Calendar.php");
?>
<style type="text/css">
<!--
body {
  background-image:url("<?=$themeurl?>/imgs/bg.gif");
}
-->
</style>
<div id='wikiHeader'>
<table border='0' width='100%' cellpadding='0' cellspacing='0'><tr>
<td>&nbsp;<font style='font-family:palatino linotype;font-size:22px'><b>Moni Wiki</b></font></td></tr>
<tr><td align='right'><?=$goto_form?></td></tr>
<?php if ($options['id']!='Anonymous') print "<tr><td><font size='-2'>$menu</font></td></tr>"; ?>
</table>
<div style='text-align:right;font-size:8px;'><a href='?action=edit'>edit</a></div>
</div>
<?=$msg?>
<table border='0' width='100%'><tr valign='top'>
<td width='100%'>
