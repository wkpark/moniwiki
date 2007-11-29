<?php
//
// Metadata module for MoniWiki
//
// $Id$

function getMetadata($raw,$mode=1,$opts=array()) {

    $metas=explode("\n",$raw);
    $meta=array();
    foreach ($metas as $line) {
        if (!trim($line)) break;
        if (($p=strpos($line,':'))!== false) {
            list($mykey,$val)=explode(':',$line,2);
            $val=trim($val);
            # strip leading bullet' * '
            $mykey=preg_replace('/^\s*(\*|\d.)?\s*/','',$mykey);
            if (strpos($mykey,' ')!== false)
                $mykey=str_replace(' ','-',ucwords($mykey));

            $mymeta=array();
            if (trim($val)) {
                $vals=explode(',',$val);
                foreach ($vals as $v) {
                    $v=trim($v);
                    if ($v) $mymeta[]=trim($v);
                }
                $meta[$mykey]=$mymeta;
            }

#            if (strpos($val,' ')!== false) {
#                #$val=str_replace('"','\"',$val);
#                $val=str_replace('"','',$val);
#                $val='"'.trim($val).'"';
#            }
#            $meta[$mykey][]=$val;
        }
    }
    if ($mode==1) {
        foreach ($meta as $k=>$v) {
            array_unique($v);
            $val=implode(", ",$v);
            $metadata[$k]=$val;
        }
        return $metadata;
    }
    return ($meta);
}

// vim:et:sts=4:sw=4:
?>
