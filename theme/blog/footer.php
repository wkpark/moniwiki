</td><td width='200'>
<div id='wikiSideMenu'>
<?php
print '<div style="font-size:10px">';
if ($options['id']=='Anonymous')
  print macro_calendar($this,"'Blog',blog,noweek,archive",'Blog');
else
  print macro_calendar($this,"'$options[id]',blog,noweek",$options['id']);
print '</div>';
print "<br />\n";
print '<font style="font-size:12px;"><b>';
print macro_RandomQuote($this);
print '</b></font>';
print "<br /><br />\n";
print '<font style="font-size:11px">';
print macro_RandomPage($this,"4,simple");
print '</font>';

?>
</div>
</td>
</tr></table>
<div id='wikiFooter'>
<?php
  if ($lastedit)
    print "last modified $lastedit $lasttime<br />";
  print $menu.$banner."<br />".$timer;
?>
</div>
