<div id="wikiMenu">
<div id="gotoForm"><?php echo $goto_form?></div>
<?php echo
$self->link_tag($self->frontpage,"",$self->sitename,"\n").
$self->link_tag("TitleIndex","",$self->icon['list']._("TitleIndex"),"\n").
$self->link_tag("FindPage","",$self->icon['find']._("FindPage"),"\n").
$self->link_tag("RecentChanges","",$self->icon['diff']._("RecentChanges"),"\n");
?>
<div align="right" style="border-top: 1px solid cornflowerblue">
<?php echo $self->link_tag("PageHits","","Total ".macro_PageCount()." pages");?>
</div>
<div id="currentPage">
<h4>Current Page</h4>
<?php echo $title?>
<div id="wikiIcons"><?php echo $icons?></div>
</div>
<div id="wikiMap">
<h4>Central Pages</h4>
<?php echo
$self->link_tag("%B9%E6%B8%ED%B7%CF","",_("GuestBook"),"").
$self->link_tag("UploadedFiles","",_("UploadedFiles"),"");
/*
$self->link_tag("CategoryGybe","","gybe","title='GNOME Yare Browser Engine project'").
$self->link_tag("CategorySpeech","","TTS","title='Text To Speech'").
$self->link_tag("CategoryMusic","","노래","title='Music'").
$self->link_tag("CategoryMovie","","영화","title='Movie'").
$self->link_tag("CategoryBook","","책","title='Book 冊'").
$self->link_tag("CategoryPoetry","","시","title='Poetry'").
"";
*/
?>
</div>
<div id="loginForm"><?php
include ("plugin/login.php");
echo macro_login($self);
?></div>
</div>
<?php if ($msg) echo "<div id='wikiMsg'>".$msg."</div>"; ?>
