</td><td width='200'>
<?php
print '<div style="font-size:10px">';
if ($options['id']=='Anonymous')
  print macro_calendar($this,"blog,noweek",'Blog');
else
  print macro_calendar($this,"blog,noweek",$options['id']);
print '</div>';
print "<br />\n";
print '<font style="font-size:12px;"><b>';
print '</b></font>';
print "<br /><br />\n";
print '<font style="font-size:11px">';
print macro_RandomPage($this,"6,simple");
print '</font>';

?>
</td>
</tr></table>
<div id='wikiFooter'>
<?php
  if ($lastedit)
    print "last modified $lastedit $lasttime<br />";
  if ($options['id']!='Anonymous') print '<font size="-1">'.$menu.'</font>';
#  print $banner."<br />".$timer;
?>

<!-- Theme imported from http://gnome.org -->
<center>
Copyright ¨Ï 2003, by Nobody.
</center>
</div>
