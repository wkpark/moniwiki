<?php
$imgdir=$themeurl."/imgs";
$icon[upper]="<img src='$imgdir/upper.gif' alt='U' />";
$icon[edit]="<img src='$imgdir/stock-tool-button-pencil.png' alt='E' align='absmiddle' />";
$icon[diff]="<img src='$imgdir/stock_convert.png' alt='D' align='absmiddle' />";
$icon[del]="<img src='$imgdir/stock_trash.png' align='absmiddle' alt='(del)' title='Delete' />";
$icon[info]="<img src='$imgdir/status-dock-24.png' alt='I' align='absmiddle' />";
$icon[rss]="<img src='$imgdir/rss.gif' alt='RSS' />";
$icon[show]="<img src='$imgdir/stock_dnd.png' alt='R' align='absmiddle' />";
$icon[find]="<img src='$imgdir/stock_search.png' alt='S' align='absmiddle' />";
$icon[help]="<img src='$imgdir/help.gif' alt='H' />";
$icon[www]="<img src='$imgdir/www.gif' alt='www' />";
$icon[mailto]="<img src='$imgdir/email.gif' alt='M' />";
$icon[create]="<img src='$imgdir/compose-message.png' alt='N' align='absmiddle' />";
$icon['new']="<img src='$imgdir/stock_new.png' alt='U' align='absmiddle' />";
$icon[updated]="<img src='$imgdir/stock_insert_object.png' alt='U' align='absmiddle' />";
$icon[home]="<img src='$imgdir/home.gif' alt='M' />";
$icon['list']="<img src='$imgdir/stock_index_24.png' alt='M' align='absmiddle' />";
$icon[user]="UserPreferences";

if (!$_GET['action'] or "show" == $_GET['action']
 or "highlight" == $_GET['action'])
	$edit_or_show = array("","?action=edit",$icon['edit']._("EditText"));
else
	$edit_or_show = array("","?action=show",$icon['show']._("ShowPage"));
$icons=array(
	$edit_or_show,
      array("","?action=diff",$icon['diff']),
      array("","?action=info",$icon['info']),
      /*array("","?action=subscribe",$icon['mailto']),*/
      /*array("","?action=LikePages",$icon['find']),*/
      array("","?action=DeletePage",$icon['del']),
   );
?>
