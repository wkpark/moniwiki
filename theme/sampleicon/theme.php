<?php
$imgdir=$themeurl."/imgs";
$icon[upper]="<img src='$imgdir/upper.png' alt='U' align='middle' border='0' />";
$icon[edit]="<img src='$imgdir/edit.png' alt='E' align='middle' border='0' />";
$icon[diff]="<img src='$imgdir/diff.png' alt='D' align='middle' border='0' />";
$icon[del]="<img src='$imgdir/deleted.gif' alt='(del)' align='middle' border='0' />";
$icon[info]="<img src='$imgdir/info.png' alt='I' align='middle' border='0' />";
$icon[rss]="<img src='$imgdir/rss.gif' alt='RSS' align='middle' border='0' />";
$icon[show]="<img src='$imgdir/show.png' alt='R' align='middle' border='0' />";
$icon[find]="<img src='$imgdir/search.png' alt='S' align='middle' border='0' />";
$icon[help]="<img src='$imgdir/help.png' alt='H' align='middle' border='0' />";
$icon[www]="<img src='$imgdir/www.gif' alt='www' align='middle' border='0' />";
$icon[mailto]="<img src='$imgdir/email.png' alt='M' align='middle' border='0' />";
$icon[create]="<img src='$imgdir/create.gif' alt='N' align='middle' border='0' />";
$icon['new']="<img src='$imgdir/new.gif' alt='U' align='middle' border='0' />";
$icon[updated]="<img src='$imgdir/updated.gif' alt='U' align='middle' border='0' />";
$icon[user]="UserPreferences";
$icon[home]="<img src='$imgdir/home.gif' alt='M' align='middle' border='0' />";

      $icons=array(
              array("","?action=edit",$icon['edit'],"accesskey='e'"),
              array("","?action=diff",$icon['diff'],"accesskey='c'"),
              array("","",$icon['show']),
              array("FindPage","",$icon['find']),
              array("","?action=info",$icon['info']),
              array("","?action=subscribe",$icon['mailto']),
              array("HelpContents","",$icon['help']),
           );

?>
