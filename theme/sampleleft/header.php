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
<div style='text-align:right;font-size:8px;'><a href='?action=edit'>edit</a></div>
</div>
<?=$msg?>
<table border='0' cellpadding='0' cellspacing='0' width='100%'><tr valign='top'>
<td id='wikiMenu' width='200'>
<table border='0' width='100%' cellpadding='0' cellspacing='0'><tr>
<td>&nbsp;<font style='font-family:Georgia,Lucida;font-size:22px;color:white;'><b>Moni Wiki</b></font></td></tr>
<tr><td align='right'><?=$goto_form?></td></tr>
</table>
<?
print '<div style="font-size:10px">';
if ($options['id']=='Anonymous')
  print macro_calendar($this,"blog,noweek",'Blog');
else
  print macro_calendar($this,"blog,noweek",$options['id']);
print '</div>';
print "<br />\n";
print '<font style="font-size:14px">';
print "$menu<br />";
print $this->link_tag("","?action=randompage","RandomPage");
print "<br />\n";
print "<br />\n";
$args['editable']=1;
print $this->get_actions($args,$options);
print '</font>';

?>
</td>
<td width='100%'>
