<?php
# $title, $logo
# $menu, $icon, $upper_icon, $rss_icon, $user_link
# $themeurl
# $msg
?>
<div id='wikiHeader'>
    <table border='0' width='100%' cellpadding='0' cellspacing='0'>
      <tr>
	<td>
	  <?php echo $menu?><a href="../">Home</a>|<?php echo $rss_icon?>
	</td>
      </tr>
      <tr>
	<td>
	  <font size='-2'>&nbsp;</font>
	</td>
      </tr>
    </table>
    <table border='0' width='100%'>
      <tr>
	<td>
	  <?php echo $title?>
	</td>
      </tr>
    </table>
</div>
<?php echo $msg?>
