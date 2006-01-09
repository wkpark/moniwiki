<div id="wikiMenu">
<div id="gotoForm"><?php echo $goto_form?></div>
<?php echo
$this->link_tag($this->frontpage,"",$this->sitename,"\n").
$this->link_tag("TitleIndex","",$this->icon['list']._("TitleIndex"),"\n").
$this->link_tag("FindPage","",$this->icon['find']._("FindPage"),"\n").
$this->link_tag("RecentChanges","",$this->icon['diff']._("RecentChanges"),"\n");
?>
<div align="right" style="border-top: 1px solid cornflowerblue">
<?php echo $this->link_tag("PageHits","","Total ".macro_PageCount()." pages");?>
</div>
<div id="currentPage">
<h4>Current Page</h4>
<?php echo $title?>
<div id="wikiIcons"><?php echo $icons?></div>
</div>
<div id="wikiMap">
<h4>Central Pages</h4>
<?php echo
$this->link_tag("%B9%E6%B8%ED%B7%CF","",_("GuestBook"),"").
$this->link_tag("UploadedFiles","",_("UploadedFiles"),"");
/*
$this->link_tag("CategoryGybe","","gybe","title='GNOME Yare Browser Engine project'").
$this->link_tag("CategorySpeech","","TTS","title='Text To Speech'").
$this->link_tag("CategoryMusic","","노래","title='Music'").
$this->link_tag("CategoryMovie","","영화","title='Movie'").
$this->link_tag("CategoryBook","","책","title='Book 冊'").
$this->link_tag("CategoryPoetry","","시","title='Poetry'").
"";
*/
?>
</div>
<div id="loginForm"><?php
include ("plugin/login.php");
echo macro_login($this);
?></div>
</div>
<?php if ($msg) echo "<div id='wikiMsg'>".$msg."</div>"; ?>
