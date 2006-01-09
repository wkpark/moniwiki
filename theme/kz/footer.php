<div style="
	display: block;
	border-top: 1px dashed black;
	margin-top: 1ex;
	margin-right: 11.5em;
	text-align: right;
">
<div align="left">
<?php echo $DBInfo->counter->pageCounter($this->page->name)?>
</div>
<?php
# Processing Instruction의 #action 정의가 있으면,
# $this->actions에 포함되어있는지 확인해보고,
# 있으면 그것을 프린트한다.
    if ($this->pi['#action'] && !in_array($this->pi['#action'],$this->actions)){
      list($act,$txt)=explode(" ",$this->pi['#action'],2);
      print $this->link_to("?action=$act",$txt);
    }
# txt 대신에 적절한 아이콘을 넣던지 합니다.
?>
Best viewed with 
<?php echo $this->link_tag("Mozilla","","Mozilla","")?>
 latest.
Powered by 
<?php echo $this->link_tag("MoniWiki","","MoniWiki","title='MoniWiki'")?>.
</div>
