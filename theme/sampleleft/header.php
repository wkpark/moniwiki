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
<img alt='ChemWiki' src='<?=$this->url_prefix?>/imgs/wikiwiki.gif' />
<?=$title?>
</div>
<div style='background-color:#a8a8a8;text-align:right;font-size:8px;'><?=$icons?><?=$rss_icon?></div>
<?=$msg?>
<table border='0' cellpadding='0' cellspacing='0' width='100%'><tr valign='top'>
<td id='wikiMenu' width='200'>
<table border='0' width='100%' cellpadding='0' cellspacing='0'><tr>
<tr><td align='right'><?=$goto_form?></td></tr>
</table>
<?
print '<div style="padding-left:10px;font-size:12px">';
print "$menu<br />";
print "</div>";
print "<hr />\n";
print '<div style="padding-left:10px;font-size:12px">';
$args['editable']=1;
print $this->get_actions($args,$options);
print "<br />";
print $this->link_tag("","?action=randompage","RandomPage");
print '</div>';
print "<hr />\n";
print '<div style="font-size:10px">';
if ($options['id']=='Anonymous')
  print macro_calendar($this,"blog,noweek",'Blog');
else
  print macro_calendar($this,"blog,noweek",$options['id']);
print '</div>';

?>
</td>
<td width='100%'>
