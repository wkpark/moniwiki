<?
# $title, $logo
# $menu, $icon, $upper_icon, $rss_icon, $user_link
# $msg
include_once("plugin/RandomBanner.php");
include_once("plugin/Calendar.php");
?>
<style type="text/css">
<!--
#wikiHeader {
 top: 0px; left: 0px; right: 0px;
 width: 100%;
 height: 60px;
 background-color: #e3ffc3;
// border-bottom: 1px solid #666666;
 background: url("<?=$themeurl?>/imgs/bg.png") no-repeat;
}
-->
</style>
<div id='wikiHeader'>
<table border='0' width='100%' cellpadding='0' cellspacing='0'><tr>
<td width='64'><img src='<?=$themeurl?>/imgs/logo.png'></td>
<td valign='bottom'><font size='-1'><?=$menu?></font></td></tr>
<tr><td colspan='2' align='right'><?=$goto_form?>&nbsp;&nbsp;</td></tr>
</table>
</div>
<div><?=$title?></div>
<?=$msg?>
<table border='0' width='100%'><tr valign='top'>
<td width='100%'>
