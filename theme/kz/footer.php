<div style="
	display: block;
	border-top: 1px dashed black;
	margin-top: 1ex;
	margin-right: 11.5em;
	text-align: right;
">
<div align="left">
<?php echo $DBInfo->counter->pageCounter($self->page->name)?>
</div>
<?php
# Processing Instruction의 #action 정의가 있으면,
# $self->actions에 포함되어있는지 확인해보고,
# 있으면 그것을 프린트한다.
    if ($self->pi['#action'] && !in_array($self->pi['#action'],$self->actions)){
      list($act,$txt)=explode(" ",$self->pi['#action'],2);
      print $self->link_to("?action=$act",$txt);
    }
# txt 대신에 적절한 아이콘을 넣던지 합니다.
?>
Best viewed with 
<?php echo $self->link_tag("Mozilla","","Mozilla","")?>
 latest.
Powered by 
<?php echo $self->link_tag("MoniWiki","","MoniWiki","title='MoniWiki'")?>.
</div>
