<table class='bbs' cellspacing='1' cellpadding='2'>
<col width='3%' class='num' /><col width='1%' class='check' /><col width='63%' class='title' /><col width='14%' /><col width='13%' /><col width='7%' class='hit' />
<thead><tr><th><?php echo _("no")?></th><th>C</th><th><?php echo _("Title")?></th><th><?php echo _("Name")?></th><th><?php echo _("Date")?></th><th><?php echo _("Hit")?></th></tr><thead>
<tbody>
<?php if(!empty($item)):foreach($item as $i):?>
<tr><td> <?php echo $i["num"]?> </td><td> <?php echo $i["check"]?> </td> <td><?php echo $i["subject"]?> </td><td><?php echo $i["name"]?></td><td><?php echo $i["date"]?></td><td><?php echo $i["hit"]?></td></tr>
<?php endforeach;endif;?>
</tbody>
</table>
