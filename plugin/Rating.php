<?php
// Copyright 2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Rating plugin for the MoniWiki
//
// Date: 2006-08-16
// Name: Rating
// Description: Rating Plugin
// URL: MoniWiki:RatingPlugin
// Version: $Revision: 1.10 $
// License: GPL
//
// Usage: [[Rating(totalscore,count)]] or [[Rating(initial score)]]
//
// $Id: Rating.php,v 1.10 2010/10/05 22:28:54 wkpark Exp $

function macro_Rating($formatter,$value='',$options=array()) {
    global $Config;
    $rating_script=&$GLOBALS['rating_script'];

    if ($options['mid'])
        $mid='&amp;mid='.base64_encode($options['mid'].',Rating,'.$value);
    else
        $mid='&amp;mid='.base64_encode($formatter->mid.',Rating,'.$value);

    $val=explode(',',$value);
    if (sizeof($val)>=2) {
        $total=$val[0];
        $count=$val[1];
    } else {
        $total=$val[0];
        $count= 0;
    }
    $count=max(1,$count);
    $value=$total/$count; // averaged value
    $value=(!empty($value) and 0 < $value and 6 > $value) ? $value:0;

    $iconset='star';
    $imgs_dir=$Config['imgs_dir'].'/plugin/Rating/'.$iconset;
    $script=<<<EOF
<script type="text/javascript">
/*<![CDATA[*/
function showstars(obj, n) {
    var my = obj.parentNode.parentNode;
    var c = my.getElementsByTagName('img');
    for( i=0; i < c.length; i++ ) {
        if (i < n)
            c[i].src = "$imgs_dir/star1.png"
        else
            c[i].src = "$imgs_dir/star0.png"
    }
}

/*]]>*/
</script>
EOF;

    $star='<span class="rating">';
    $msg=array(
        1=>_("Awful!"),
        2=>_("Not the worst ever."),
        3=>_("Not bad!"),
        4=>_("Useful!"),
        5=>_("Very Gooood!"));

    for ($i=1;$i<=5;++$i) {
        $t=($i <= $value) ? '1':'0';
        $alt=$t ? '{*}':'{o}';
        $star.='<a href="?action=rating'.$mid.'&amp;rating='.$i.'" rel="nofollow">'.
            '<img alt="'.$alt.'" src="'.$imgs_dir.'/star'.$t.'.png" '.
            'onmouseover="showstars(this,'.$i.')" title="'.$msg[$i].'" '.
            'onmouseout="showstars(this,'.intval($value).')" '.
            'style="border:0" class="star" /></a>';
    }
    $star.=<<<EOF
</span>
EOF;
    if ($rating_script) return $star;
    $rating_script=1;
    return $script.$star;
}

function do_rating($formatter,$options) {
    global $DBInfo;
    if ($options['id'] == 'Anonymous') {
        $options['msg'].="\n"._("Please Login or make your ID on this Wiki ;)");
        do_invalid($formatter,$options);
        return;
    }
    $formatter->send_header('',$options);

    $oraw=$formatter->page->get_raw_body();

    list($nth,$dum,$v)=explode(',', base64_decode($options['mid']),3);

    $val=explode(',',$v);
    if (sizeof($val)>=2) {
        $total=$val[0];
        $count=$val[1];
    } else
        $total=$val[0];
    if (isset($count)) {
        $count=max(1,$count);
    } else {
        $count = 1;
    }
    $value=$total/$count; // averaged value
    if ($total==0 and $count==1) $count=0;
    $value=(!empty($value) and 0 < $value and 6 > $value) ? $value:0;
    ++$count;

    $check='[['.$dum.'('.$v.')]]';
    $rating=$options['rating'] ? (int)$options['rating']:1;
    $rating=min(5,max(0,$rating));

    $total+=$rating; // increase total rating

    if (is_numeric($nth)):

    $raw=str_replace("\n","\1",$oraw);
    $chunk=preg_split("/({{{.+}}})/U",$raw,-1,PREG_SPLIT_DELIM_CAPTURE);
    #print '<pre>';
    #print_r($chunk);
    #print '</pre>';
    $nc='';
    $k=1;
    $i=1;
    foreach ($chunk as $c) {
        if ($k%2) {
            $nc.=$c;
        } else {
            $nc.="\7".$i."\7";
            $blocks[$i]=str_replace("\1","\n",$c);
            ++$i;
        }
        $k++;
    }
    $nc=str_replace("\1","\n",$nc);
    $chunk=preg_split('/((?!\!)\[\[.+\]\])/U',$nc,-1,PREG_SPLIT_DELIM_CAPTURE);
    $nnc='';
    $ii=1;
    $matched=0;
    for ($j=0,$sz=sizeof($chunk);$j<$sz;++$j) {
        if (($j+1)%2) {
            $nnc.=$chunk[$j];
        } else {
            if ($nth==$ii) {
                $new='[[Rating('.$total.','.$count.')]]';
                if ($check != $chunk[$j]) break;
                $nnc.=$new;
                $matched=1;
            }
            else
                $nnc.=$chunk[$j];
            ++$ii;
        }
    }
    if (!empty($blocks)) {
        $formatter->_array_callback($blocks, true);
        $nnc=preg_replace_callback("/\7(\d+)\7/",
            array(&$formatter, '_array_callback'), $nnc);
    }

    endif;

    if (empty($matched)) {
        if (!empty($DBInfo->use_rating)) {
            $dum='';
            $pi=$formatter->page->get_instructions($dum);
            $old = !empty($pi['#rating']) ? $pi['#rating'] : '';
            $new='#rating '.$total.','.$count;
            if ($old) {
                list($ts,$cnt)=explode(',',$old);
                $raw=preg_replace('/^#rating\s+.*$/m',$new,$oraw,1);
            } else {
                if (!$formatter->pi)
                    $raw=$new."\n".$oraw;
                else {
                    $body=$oraw;
                    $head='';
                    while (true) {
                        list($line,$body)=explode("\n",$body,2);
                        if ($line{0}=='#') $head.=$line."\n";
                        else {
                            $body=$line."\n".$body;
                            break;
                        }
                    }
                    $raw=$head.$new."\n".$body;
                }
            }
            #print "<pre>".$raw."</pre>";
            $nnc=&$raw;
        } else {
            $options['title']=_("Invalid rating request !");
            $formatter->send_title('','',$options);
            $formatter->send_footer('',$options);
            return;
        }
    }

    $formatter->page->write($nnc);
    $DBInfo->savePage($formatter->page,"Rating",$options);

    #print "<pre>";
    #print_r($options);
    #print "</pre>";
    #print $check;   

    $options['title']=_("Rating successfully !");
    $formatter->send_title('','',$options);
    $formatter->send_page('',$options);
    $formatter->send_footer('',$options);
    return;
}

// vim:et:sts=4:
?>
