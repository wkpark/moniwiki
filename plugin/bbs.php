<?php
// Copyright 2008 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a BBS plugin for the MoniWiki
//
// Usage: [[BBS(pagename,count,mode)]]
//
// $Id$

function _get_pagelist($formatter,$pages,$action,$curpage=1,$listcount=10,$bra="[",$cat="]",$sep="|",$prev="&#171;",$next="&#187;",$first="",$last="",$ellip="...") {

  if ($curpage >=0)
    if ($curpage > $pages)
      $curpage=$pages;
  if ($curpage <= 0)
    $curpage=1;

  $startpage=intval(($curpage-1) / $listcount)*$listcount +1;

  $pnut="";
  if ($startpage > 1) {
    $prevref=$startpage-1;
    if (!$first) {
      $prev_l=$formatter->link_tag('',$action.$prevref,$prev);
      $prev_1=$formatter->link_tag('',$action."1","1");
      $pnut="$prev_l".$bra.$prev_1.$cat.$ellip.$bar;
    }
  } else {
    $pnut=$prev.$bra."";
  }

  for ($i=$startpage;$i < ($startpage + $listcount) && $i <=$pages; $i++) {
    if ($i != $startpage)
      $pnut.=$sep;
    if ($i != $curpage) {
      $link=$formatter->link_tag('',$action.$i,$i);
      $pnut.=$link;
    } else
      $pnut.="<strong>$i</strong>";
  }

  if ($i <= $pages) {
    if (!$last) {
      $next_l=$formatter->link_tag('',$action.$pages,$pages);
      $next_i=$formatter->link_tag('',$action.$i,$next);

      $pnut.=$cat.$ellip.$bra.$next_l.$cat.$next_i;
    }
  } else {
    $pnut.="".$cat.$next;
  }
  return $pnut;
}

class BBS_text {
    function BBS_text($name,$conf) {
        # $conf['data_dir'] from DBInfo.
        $this->bbsname=$name;

        # XXX
        $this->text_dir=$conf['data_dir'].'/text/'.$name.'.d';

        # XXX
        $this->data_dir=$conf['data_dir'].'/bbs/'.$name;
        $this->cache_dir=$this->data_dir.'/cache';
        $this->dba_type=$conf['dba_type'];
        $this->use_attach=$conf['use_attach'];

        # XXX
        $this->index=$this->data_dir.'/.index';
        $this->current=$this->data_dir.'/.current';
        $this->count=$this->data_dir.'/.count';

        # XXX
        if ($conf['use_counter'])
            $this->counter=new Counter_dba($this);
        if (!$this->counter->counter)
            $this->counter=new Counter();

        if (!file_exists($this->index)) {
            umask(000);
            _mkdir_p($this->data_dir,0777); // XXX
            @mkdir($this->text_dir,0777); // XXX
            @mkdir($this->text_dir,0777); // XXX
            umask(022);
            touch($this->index);
            touch($this->count);
            # XXX global lock.
            touch($this->text_dir.'/.lock');
            $fp=fopen($this->current,'w');
            if ($fp) {
                fwrite($fp,'1');
                fclose($fp);
            }
        }
    }

    function getPageKey($id) {
        return $this->text_dir.'/'.$id;
    }

    function hasPage($id) {
        if (!$id) return 0;
        return @file_exists($this->getPageKey($id));
    }

    function exists($id) {
        if (!$id) return 0;
        return @file_exists($this->getPageKey($id));
    }

    function getPage($id) {
        $k=$this->getPageKey($id);
        $fp=fopen($k,'r');
        if ($fp) {
            $fsize=filesize($k);
            if ($fsize > 0)
                $body=fread($fp,$fsize);
            fclose($fp);
        } else
            return null;
        return $body;
    }

    function incCurrent() {
        list($cur,$dum)=file($this->current);
        $fp=fopen($this->current,'w');
        if ($fp) {
            $id=$cur+1;
            flock($fp,LOCK_EX);
            fwrite($fp,"$id");
            flock($fp,LOCK_UN);
            fclose($fp);
        }
        return $cur;
    }

    function incCount() {
        list($cur,$dum)=file($this->count);
        $fp=fopen($this->count,'w');
        if ($fp) {
            $id=$cur+1;
            flock($fp,LOCK_EX);
            fwrite($fp,"$id");
            flock($fp,LOCK_UN);
            fclose($fp);
        }
        return $cur;
    }

    function getCount() {
        list($cur,$dum)=file($this->count);
        return $cur;
    }

    function setCount($num) {
        $fp=fopen($this->count,'w');
        if ($fp) {
            flock($fp,LOCK_EX);
            fwrite($fp,"$num");
            flock($fp,LOCK_UN);
            fclose($fp);
        }
        return $cur;
    }

    function decCount() {
        list($cur,$dum)=file($this->count);
        $fp=fopen($this->count,'w');
        if ($fp) {
            $id=$cur-1;
            flock($fp,LOCK_EX);
            fwrite($fp,"$id");
            flock($fp,LOCK_UN);
            fclose($fp);
            print $id;
        }
        return $id;
    }

    function savePage($data,$options=array()) {
        global $DBInfo;

        $time=time();
        $date=gmdate('Y-m-d H:i:s',$time);
        $ip=$_SERVER['REMOTE_ADDR'];

        $info="$ip,$time,$data[name],$data[pass],$data[email],$data[home],$data[subject],";
        $info.="\"$data[categories]\",\"$data[files]\",\"$data[summary]\"";

        if ($data['no']) {
            # check password
            $id=&$data['no'];
            $body=$this->getPage($id);
            $comments='';
            if ($body != null) {
                include_once('lib/metadata.php');
                #list($meta,$body)=explode("\n\n",$body,2);
                #$metas=getMetadata($meta,1);
                list($metas,$nbody)=_get_metadata($body);
                if ($nbody) $body=$nbody;
                $data['name']=$metas['Name'];
                $updated="\nUpdated: ".gmdate('Y-m-d H:i:s',time());
                $boundary= strtoupper(md5("COMMENT")); # XXX
                list($body,$comments)=explode('----'.$boundary."\n",$body,2); # XXX
            } else {
                return false;
            }
        } else {
            $id=$this->incCurrent();
            $this->incCount();
            $this->counter->incCounter($id,$options);
            $this->updateIndex($id,$info);
        }

        $message=<<<EOF
Name: $data[name]
Subject: $data[subject]
Date: $date$updated
Email: $data[email]
HomePage: $data[home]
IP: $ip

$data[text]
EOF;

        if ($comments) $message.='----'.$boundary."\n".$comments;

        $log=$_SERVER['REMOTE_ADDR'].';;'.$data['name'].';;'.$comment;
        $options['log']=$log;
        $options['pagename']=$this->bbsname.':'.$data['no'];
        $ret=$DBInfo->_savePage($this->getPageKey($id),$message,$options);

        if (!empty($data['attach'])) {
            $cache=new Cache_Text('attachments');
            $cache->update($options['pagename'],serialize($data['attach']));
        }

        return true;
    }

    function deletePage($id) {
        $filename =$this->getPageKey($id);
        unlink($filename);
        $this->deleteIndex($id);
        $this->setCount($this->getCount()-1);
    }

    function deleteIndex($id) {
        $check=0;
        $id=trim($id);
        $fp= fopen($this->index, 'r+');
        while (isset($id) and is_resource($fp) and ($fz=filesize($this->index))>0){
            fseek($fp,0,SEEK_END);
            if ($fz <= 1024) {
                fseek($fp,0);
                $ll=rtrim(fread($fp,1024));
                $lines=explode("\n",$ll);
                for ($i=0,$sz=sizeof($lines);$i<$sz;$i++) {
                    if (preg_match('/^'.$id.',/',$lines[$i])) {
                        unset($lines[$i]);break;
                    }
                }
                $all='';
                if (sizeof($lines)) $all=implode("\n",$lines)."\n";
                fseek($fp,0);
                flock($fp,LOCK_EX);
                fwrite($fp,$all);
                ftruncate($fp,strlen($all));
                flock($fp,LOCK_UN);
                fclose($fp);
                break;
            }
            $a=-1; // hack, don't read last \n char.
            $last='';
            fseek($fp,0,SEEK_END);
            #while($check_from < $check and !feof($fp)){
            while($check != -1 and !feof($fp)){
                $rlen=$fz + $a;
                if ($rlen > 1024) { $rlen=1024;}
                else if ($rlen <= 0) break;
                $a-=$rlen;
                fseek($fp,$a,SEEK_END);
                $l=fread($fp,$rlen);
                if ($rlen != 1024) $l="\n".$l; // hack, for the first log entry.
                while(($p=strrpos($l,"\n"))!==false) {
                    $line=substr($l,$p+1).$last;
                    $last='';
                    $nline++;
                    $l=substr($l,0,$p);
                    $dumm=explode(",",$line,4);
                    $check=$dumm[0];
                    if ($id<$check) continue;
                    else if ($id==$check) {
                        # XXX
                        #print 'WOW'.$a."/".$p."<br />";

                        fseek($fp,$a+$p+1,SEEK_END);
                        $ll=fread($fp,strlen($line));
                        #print '<pre>'.$line."</pre>";
                        #print '<pre>'.$last."</pre>";
                        #print '<pre>'.$ll."</pre>";

                        $pp=$a+$p+1+strlen($line)+1;
                        fseek($fp,$pp,SEEK_END);

                        flock($fp,LOCK_EX);
                        if ($pp < 0) {
                            $lastall=fread($fp,-$pp);
                            fseek($fp,$a+$p+1,SEEK_END);
                            $r=fwrite($fp,$lastall);
                            #print $r.'OK';
                        }
                        $nfz=$fz-strlen($line)-1;
                        #print $fz."/".$nfz."<br />";
                        #print '<pre>'.$lastall."</pre>";
                        ftruncate($fp,$nfz);
                        flock($fp,LOCK_UN);
                        
                        $check=-1; break;
                        $lines[]=$line;
                        if (sizeof($lines) >= $itemnum) { $check=-1; break; }
                    }
                    $last='';
                }
                $last=$l.$last;
            }
            fclose($fp);
            break;
        }
    }

    function updateIndex($cur,$info) {
        $fp=fopen($this->index,'a');

        if ($fp) {
            flock($fp,LOCK_EX);
            fwrite($fp,$cur.','.$info."\n");
            flock($fp,LOCK_UN);
            fclose($fp);
            return true;
        }
        return false;
    }

    function _get_raw_list($items,$opts=array()) {
        $lines=array();
 
        if ($opts['no']) { /* id option */
            $check = 0;
            $check_from=$opts['no'];
            $check_to=$opts['no'];
            $check_field=0;
        } else if (1 or $opts['p']) { /* page option XXX */
            $p=$opts['p'] > 0 ? $opts['p']:1;

            $perpage=$opts['perpage'] ? $opts['perpage']:20;
            
            $check_from=($p-1)*$perpage+1;
            $check_to=$check_from+$perpage;
            $check = 0;

            $check_field=-1;
        } else {
            $time_current= time();
            $secs_per_day= 24*60*60;

            $days= $opts['days'] > 0 ? $opts['days']:30;
            $items= $opts['items'] > 0 ? $opts['items']:$items;
  
            if ($opts['ago']) {
                $check_from= $time_current - ($opts['ago'] * $secs_per_day);
                $check_to= $check_from + ($days * $secs_per_day);
            } else {
                if ($items) {
                    $check_from= $time_current - (365 * $secs_per_day);
                } else {
                    $check_from= $time_current - ($days * $secs_per_day);
                }
                $check_to= $time_current;
            }

            $check=$check_to;
            $check_field=2;
        }

        $itemnum=$items ? $items:200;

        $fp= fopen($this->index, 'r');
        $nline=0;
        while (is_resource($fp) and ($fz=filesize($this->index))>0){
            fseek($fp,0,SEEK_END);
            if ($fz <= 1024) {
                fseek($fp,0);
                $ll=rtrim(fread($fp,1024));
                if (trim($ll))
                    $lines=array_reverse(explode("\n",$ll));
                else
                    $lines=array();
                break;
            }
            $a=-1; // hack, don't read last \n char.
            $last='';
            fseek($fp,0,SEEK_END);
            while($check != -1 and !feof($fp)){
            #while($check_from < $check and !feof($fp)){
                $rlen=$fz + $a;
                if ($rlen > 1024) { $rlen=1024;}
                else if ($rlen <= 0) break;
                $a-=$rlen;
                fseek($fp,$a,SEEK_END);
                $l=fread($fp,$rlen);
                if ($rlen != 1024) $l="\n".$l; // hack, for the first log entry.
                # print '=>'.$check_from.', '.$check_to.', '.$check.'<br />';
                while(($p=strrpos($l,"\n"))!==false) {
                    $line=substr($l,$p+1).$last;
                    $last='';
                    $nline++;
                    $l=substr($l,0,$p);
                    $dumm=explode(",",$line,4);
                    $check=$check_field >= 0 ? $dumm[$check_field]:$nline;
                    if ($check_from>$check) continue;
                    else if ($check_to>=$check) {
                        $lines[]=$line;
                        if (sizeof($lines) >= $itemnum) { $check=-1; break; }
                    }
                    $last='';
                }
                $last=$l.$last;
            }
            #print $a;
            #print sizeof($lines);
            #print_r($lines);
            fclose($fp);
            break;
        }

        return $lines;
    }

    function getList($count,$opts=array()) {
        $list=array();
        $lines=$this->_get_raw_list($count,$opts);
        $expr='/,(?=(?:[^"]*"[^"]*")*(?![^"]*"))/';
        foreach ($lines as $line) {
            $results=preg_split($expr,trim($line));
            $results=preg_replace("/^\"(.*)\"$/","$1",$results);
            $list[]=$results;
        }
        return $list;
    }
}

function macro_BBS($formatter,$value,$options=array()) {
    global $DBInfo;

    # set defaults
    $ncount=20; # default
    $bname=$formatter->page->name;

    $nid='';
    # check options
    $args=preg_split('/\s*,\s*/',$value);
    foreach ($args as $arg) {
        $arg=trim($arg);
        if ($arg == '') continue;
        if (($p=strpos($arg,'='))!==false) {
            $k=substr($arg,0,$p);
            $v=substr($arg,$p+1);
            if ($k=='no') $nid=$v;
            else if ($k=='mode') $options['mode']=$v;
        } else if ($arg == 'mode') { }
        else if ($arg == ((int) $arg)."") { $ncount=$arg; }
        else {
            $bname=$arg;
        }
    }

    $bpage=_rawurlencode($bname);
    $nid= $nid ? $nid:$_GET['no'];

    $nids=preg_split('/\s+/',$nid);
    rsort($nids);

    $options['p']= ($_GET['p'] > 0) ? $_GET['p']:1;
    $options['c']= ($ncount != 20) ? $ncount:'';

    # is it exists ?
    if (!$DBInfo->hasPage($bname)) {
        return _("This bbs does not exists yet. Please save this page first");
    }

    # load a config file
    $conf0=array();
    if (file_exists('config/bbs.'.$bname.'.php')) {
        $confname='bbs.'.$bname.'.php';
        $conf0=_load_php_vars('config/bbs.default.php');
    } else {
        $confname='bbs.default.php';
    }
    $conf=_load_php_vars('config/'.$confname);

    $conf=array_merge($conf0,$conf);

    $conf['data_dir']=$DBInfo->data_dir;
    $conf['dba_type']=$DBInfo->dba_type;

    if (!$DBInfo->use_bbs) return '[[BBS]]';
    #if ($DBInfo->use_bbs == 1);
    #if ($DBInfo->use_bbs == 2);
    $MyBBS=new BBS_text($bname,$conf); // XXX
    if ($options['new'] and $MyBBS) return $MyBBS;

    if (!$MyBBS) return '[[BBS]]';

    $msg='';
    $btn=array();
    # read messages
            #
    $formatter->baserule[]="/^((-=)+-?$)/";
    $formatter->baserule[]="/ comment #(\d+)\b/";
    $formatter->baserule[]="/\[reply (\d+)\]/";

    $formatter->baserepl[]="<hr />\n";
    $formatter->baserepl[]=" comment [#c\\1 #\\1]";
    $formatter->baserepl[]="<script type='text/javascript'><!--\n".
        " addReplyLink(\\1); //--></script>";
    $msg='';
    $narticle=sizeof($nids);
    $js='';

    if ($narticle == 1 and $options['mode'] == 'simple') {
        $nid=$nids[0];
        if (!$nid or !$MyBBS->hasPage($nid)) return '[[BBS(error)]]';
        include_once('lib/metadata.php');
        $body=$MyBBS->getPage($nid);
        list($metas,$body)=_get_metadata($body);
        $img='';
        if ($MyBBS->use_attach) {
            $cache=new Cache_text('attachments');
            $attachs=unserialize($cache->fetch($MyBBS->bbsname.':'.$nid));
            if (preg_match('/^attachment:([^\?]+)(\?.*)?$/',$attachs[0],$m)) {
                $img=$formatter->macro_repl('Attachment',$m[1].'?thumbwidth=100');
            }
            $subject=$formatter->link_tag($bpage,"?no=$nid",$metas['Subject']);
        }

        $out="<div class='simpleView'><table>\n".
            "<tr><td class='img'>".$img."</td><td class='subject'>".$subject.'</td></tr>'.
            "<tr><td colspan='2'></td>\n</tr></table></div>";

        return $out;
    }

    foreach($nids as $nid) {
        if (!$nid or !$MyBBS->hasPage($nid)) continue;
        $fields=array('Name','Subject','Date','Email','HomePage','IP','Keywords');
        include_once('lib/metadata.php');
#Name: wkpark
#Subject: Oh well
#Date: 2006-04-29 42:04:39
#Email: wkpark@gmail.com
#HomePage: 
#IP: 2xx.xxx.xxx.x
        $body=$MyBBS->getPage($nid);
        if ($body != null) {
            $options['nosisters']=1;

            $MyBBS->counter->incCounter($nid,$options);
            list($metas,$body)=_get_metadata($body);

            $boundary= strtoupper(md5("COMMENT")); # XXX

            $copy=$body;
            list($comment,$copy)=explode("----".$boundary."\n",$copy,2);
            while(!empty($comment)) {
                list($comment,$copy)=explode("----".$boundary."\n",$copy,2);
                if (preg_match('/^Comment-Id:\s*(\d+)/i',$comment,$m)) {
                    list($myhead,$my)=explode("\n\n",$comment,2);
                    $hidden.='<pre style="display:none;" id="comment_text_'.$m[1].'">'.htmlspecialchars($my).'</pre>';
                }
            }
            ob_start();

            # add some basic rule/repl for bts
            $rule="/-{4}(?:".$boundary .")?\nComment-Id:\s*(\d+)\n".
                "From:\s*([^\n]+)\nDate:\s*([^\n]+)\n\n/im";
            $repl="----\n'''Comment-Id:''' [#c\\1][#c\\1 #\\1] by \\2 on [[DateTime(\\3)]] [reply \\1]\n\n";
            $body=preg_replace($rule,$repl,$body);

            $formatter->quote_style='bbs-comment';
            $options['usemeta']=1;


            #
            $q_save=$formatter->self_query;
            $query='?no='.$nid.'&amp;p='.$options['p'];
            $formatter->self_query=$query;

            $save=$formatter->preview;
            $formatter->preview=1;
            $save_markup=$formatter->format;
            ob_start();
            if ($conf['default_markup']) {
                $formatter->pi['#format']=$conf['default_markup'];
            }
            $formatter->send_page($body,$options);
            $body= ob_get_contents();
            ob_end_clean();
            $formatter->pi['#format']= $save_markup;
            $formatter->self_query=$q_save;

            $msg.="<div class='bbsArticle'>".
            '<div class="head"><h2>'._("No").' '.$nid.': '.$metas['Subject'].'</h2></div>'.
            '<div class="body">'.
            '<div class="extra"> @ '.$metas['Date'].' ('._mask_hostname($metas['IP'],3).')</div>'.
            '<div class="user"><h3>'.$metas['Name'].'</h3></div>'.
            '<div class="article">'.$body.
            "</div>\n</div>\n".
            '<div class="foot"><div></div></div>'."</div>\n";
            $snid=$nid;
            $btn['edit']=$formatter->link_tag($bpage,"?action=bbs&amp;mode=edit&amp;no=".$nid,
                '<span>'._("Edit").'</span>','class="button"');
            $btn['delete']=$formatter->link_tag($bpage,"?action=bbs&amp;mode=delete&amp;no=".$nid,
                '<span>'._("Delete").'</span>','class="button"');
            if ($narticle == 1 and $conf['use_comment']) {
                $opts['action']='bbs';
                $opts['no']=$nid;
                $opts['p']=$options['p'];
                $opts['mode']='comment';
                $opts['nopreview']=1;

                $p=new WikiPage($bname.':'.$opts['no'],$options);
                $opts['datestamp']=$p->mtime();
                $comment=$formatter->macro_repl('Comment','usemeta',$opts);
                unset($opts['no']); # XXX
            }
            $msg.='<div class="bbsComment">'.$comment.'</div><div class="bbsArticleBtn">'.implode(" ",$btn).'</div>';
            unset($btn['delete']);
            unset($btn['edit']);
            $title=str_replace('"','\"',$metas['Subject']);
            $js.=<<<JS
<script type="text/javascript">
/*<![CDATA[*/
document.title+=" [" + $snid + "] - " + "$title";
/*]]>*/
</script>
JS;
        }
    }
    if (!empty($msg) and ! $_GET['p']) return $msg;

    if (1) { # XXX
        $nochk=_("Please check article numbers.");
        $js.=<<<JS
<script type='text/javascript'>
/*<![CDATA[*/
  function send_list(obj,mode) {
    var tmp="";
    var i, chk=false;

    form=obj.parentNode.parentNode;

    for(i=0;i< form.length;i++) {
       if(form[i].type!="checkbox") continue;
       if(form[i].checked) {
          tmp+=form[i].value+" ";
          chk=true;
       }
    }
    if(chk==true) {
       form.no.value = tmp.substr(0,tmp.length-1);
       if (mode!=undefined) {
         form.elements.action.value = 'bbs';
         form.elements.mode.value = mode;
       } else {
         form.removeChild(form.elements.mode);
         form.removeChild(form.elements.action);
       }
       form.submit();
       return false;
    }
    alert ("$nochk");
    return false;
  }
/*]]>*/
</script>

JS;

    }

    # get list
    $options['perpage']=$ncount;
    $list=$MyBBS->getList($ncount,$options);
    # get total number of articles
    $tot=$MyBBS->getCount();

    $pages= intval($tot / $ncount);
    if ($tot % $ncount) $pages++;

    if ($options['mode'] == 'rss') {
        $rss='<'.'?xml version="1.0" encoding="utf-8"?>'."\n".'<rss version="2.0">'."\n";
        $rss.="<channel>\n<title>".$DBInfo->sitename.": </title>\n";
        $rss.="<link>".qualifiedUrl($formatter->link_url($bpage))."</link>\n";
        $rss.="<description></description>\n";
        $rss.="<pubDate>".gmdate('D, j M Y H:i:s',time())." +0000</pubDate>\n";
        foreach ($list as $l) {
            $item="<item>\n";
            $item.="<title><![CDATA[".$l[7]."]]></title>\n";
            $item.="<link>".qualifiedUrl($formatter->link_url($bpage,"?no=$l[0]"))."</link>\n";
            $item.="<author><![CDATA[".$l[3]."]]></author>\n";
            $item.="<description><![CDATA[".$l[3]."]]></description>\n";
            $item.="<pubDate>".gmdate('D, j M Y H:i:s',$l[2])." +0000</pubDate>\n</item>\n";
            $rss.=$item;
        }
        $rss.="</channel>\n</rss>\n";

        return $rss;
    } else 
    if ($options['mode'] == 'simple') {
        $simple="<div class='bbsSimple'><table class='bbsSimple'>\n";
        foreach ($list as $l) {
            $date=date("Y-m-d",$l[2]);
            $my=$l[7];
            $title='';
            if (function_exists('mb_strimwidth') and strlen($l[7]) > 60) {
                $title='title="'.$l[7].'"';
                $my=mb_strimwidth($l[7],0,40,'...',$DBInfo->charset);
            }
            $simple.="<tr><td class='date'>[".$date."]</td><td>".
                $formatter->link_tag($bpage,"?no=$l[0]".$extra,$my,$title).'</td></tr>';
        }
        $simple.="<tr><td colspan='2' class='more'>".$formatter->link_tag($bpage, "",_("More").'&#187;')."</td>\n</tr>\n";
        $simple.="</table>";
        
        return $simple;
    }

    if ($pages > 1)
      $pnut=_get_pagelist($formatter,$pages,
        '?'.$extra.
        ($extra ?'&amp;p=':'p='),$options['p'],$ncount);
    else
      $pnut="<div class='clear'></div>";

    $extra=$options['p'] ? '&amp;p='.$options['p']:'';

    #$head=array(_("no"),'C',_("Title"),_("Name"),_("Date"),_("Hit"));
    #$out.="<col width='3%' class='num' /><col width='1%' class='check' /><col width='63%' class='title' /><col width='14%' /><col width='13%' /><col width='7%' class='hit' />\n";
    #$out.='<thead><tr><th>'.implode("</th><th>",$head)."</th></tr><thead>\n";
    #$out.="<tbody>\n";
    $item=array();
    foreach ($list as $l) {
        $nid=&$l[0];
        $ip=&$l[1];
        $date=date("Y-m-d",$l[2]);
        $user=$l[3];
        $subject=$formatter->link_tag($bpage,"?no=$nid".$extra,$l[7]);
        $hit=$MyBBS->counter->pageCounter($nid);
        $chk='<input type="checkbox" value="'.$nid.'">';
        #$item=array(in_array($nid,$nids) ? '<strong>&raquo;</strong>':$nid,$chk,$subject,$user,$date,$hit);
        $item[]=array('num'=>in_array($nid,$nids) ? '<strong>&raquo;</strong>':$nid,'check'=>$chk,'subject'=>$subject,
           'name'=>$user,'date'=>$date,'hit'=>$hit);
        ##$tmp='<tr><td>'.implode("</td><td>",$item)."</td></tr>\n";
        #$tmp="<tr><td class='no'>$item[0]</td><td class='check'>$item[1]</td>".
        #    "<td class='title'>$item[2]</td><td class='name'>$item[3]</td>".
        #    "<td class='date'>$item[4]</td><td class='hit'>$item[5]</td>".
        #    "</tr>\n";
        #$out.=$tmp; 
    }

    $formatter->_vars['item']=&$item;
    $out.= $formatter->include_theme('plugin/BBS/default','list',array());
    #$out.= $formatter->include_theme('plugin/BBS/blue_tpl','list',array());
    #$out.= $formatter->processor_repl('tpl_','',array('path'=>'theme/plugin/BBS/blue_tpl/list.tpl'));
    #$out.="</tbody>\n";

    $btn['new']=$formatter->link_tag($bpage,"?action=bbs&amp;mode=edit",'<span>'._("New").'</span>','class="button"');
    unset($btn['edit']);
    $bn['view']=$formatter->link_tag($bpage,"",'<span>'._("Read").'</span>',
        'onclick="return send_list(this)" onfocus="blur()" class="button"');
    $bn['delete']=$formatter->link_tag($bpage,"",'<span>'._("Delete").'</span>',
        'onclick="return send_list(this,\'delete\')" onfocus="blur()" class="button"');
    $del="<div class='bbsAdminBtn'>".implode(" ",$bn)."</div>\n";
    $btns="<div class='bbsBtn'>".implode(" ",$btn)."</div>\n";

    $lnk=$formatter->link_url($bpage,'?action=bbs');
    $form0="<form method='get' action='$lnk'>\n";
    $form1='<input type="hidden" name="no" />';
    if ($options['p'])
        $form1.='<input type="hidden" name="p" value="'.$options['p']."\" />\n";
    $form1.='<input type="hidden" name="mode" />'.
           '<input type="hidden" name="action" />';
    $form1.="</form>\n";
    $pnut= "<div class='pnut'>$pnut</div>";
    $info= '<div class="bbsRSS">'.sprintf(_("Total %s articles."),'<strong>'.$tot.'</strong>').' '.
    #    $formatter->link_tag($bpage,'?action=bbs&amp;mode=rss','<span>RSS</span>').'</div>';
        $formatter->link_tag($bpage,'?action=bbs&amp;mode=rss',$formatter->icon['rss']).'</div>';
    return $info.$pnut.$msg.$js.$form0.$out.$del.$form1.$pnut.$btns;
}

function do_bbs($formatter,$options=array()) {
    global $DBInfo;

    $err='';
    $args=array();

    if ($options['mode']=='rss') {
        #$formatter->send_header("Content-Type: text/xml",$options);
        header("Content-Type: application/xml");
        print macro_BBS($formatter,'',$options);
        return;
    }
    # load a config file
    $bname=$formatter->page->name;
    $conf0=array();
    if (file_exists('config/bbs.'.$bname.'.php')) {
        $confname='bbs.'.$bname.'.php';
        $conf0=_load_php_vars('config/bbs.default.php');
    } else {
        $confname='bbs.default.php';
    }
    $conf=_load_php_vars('config/'.$confname);

    $conf=array_merge($conf0,$conf);

    # check valid IP
    $check_ip=true;
    if ($conf['allowed_ip'] and in_array($options['mode'],array('edit','delete','new'))) {
        include_once 'lib/checkip.php';
        if (!check_ip($conf['allowed_ip'], $_SERVER['REMOTE_ADDR'])) {
            $options['title']=sprintf(_("Your IP address is not allowed to %s at this BBS"),$options["mode"]);
            $check_ip=false;
        }
    }

    $check_pass=false;
    $MyBBS=macro_BBS($formatter,'',array('new'=>1));
    if ($options['id'] != 'Anonymous' and $options['mode']=='edit' and $options['no']) {
        $body=$MyBBS->getPage($options['no']);
        if ($body != null) {
            include_once('lib/metadata.php');
            list($metas,$dummy)=_get_metadata($body);
            if ($metas['Name'] == $options['id']) # XXX
                $check_pass=true;
        }
    }
    # password check
    while ($options['no'] and
        ($options['mode']=='delete' or $options['mode']=='edit') and $_SERVER['REQUEST_METHOD']=="POST") {
        # check admin(WikiMaster) password
        if (!$check_pass) {
            if ($DBInfo->admin_passwd) {
                $check_pass=$DBInfo->admin_passwd==crypt($options['pass'],$DBInfo->admin_passwd);
            } else
                $check_pass=false;
        }

        # check admin(BBSMaster) password
        if (!$check_pass and $conf['admin_passwd'])
            $check_pass=$conf['admin_passwd']==crypt($options['pass'],$conf['admin_passwd']);


        while ($check_ip and $check_pass and $options['mode']== 'delete') {
        
            if (($p=strpos($options['no'],' '))!==false)
                $nids=explode(" ",$options['no']);
            else
                $nids=array($options['no']);

            for ($i=0,$sz=sizeof($nids);$i<$sz;$i++) {
                if ($MyBBS->hasPage($nids[$i])) {
                    $MyBBS->deletePage($nids[$i]);
                } else {
                    $MyBBS->deleteIndex($nids[$i]);
                }
            }

            $query=$options['p'] ? '&p='.$options['p']:'';
            $myrefresh='';
            if ($DBInfo->use_save_refresh) {
                $sec=$DBInfo->use_save_refresh - 1;
                $lnk=$formatter->link_url($formatter->page->urlname,'?'.($query ? $query:'action=show'));
                $myrefresh='Refresh: '.$sec.'; url='.qualifiedURL($lnk);
            }
            $options['msg']=_("Successfully deleted.");
            $header=array("Expires: " . gmdate("D, d M Y H:i:s", 0) . " GMT"); 
            if ($myrefresh) $header[]=$myrefresh;
            $formatter->send_header($header,$options);
            $formatter->send_title("","",$options);
            $formatter->send_footer("",$options);
            return;
        }
        break;
    }
    while ($options['mode']=='comment' and $options['savetext'] and $_SERVER['REQUEST_METHOD']=="POST") {

        $query='no='.$options['no'].($options['p'] ? '&p='.$options['p']:'');
        $myrefresh='';
        if ($DBInfo->use_save_refresh) {
            $sec=$DBInfo->use_save_refresh - 1;
            $lnk=$formatter->link_url($formatter->page->urlname,'?'.$query);
            $myrefresh='Refresh: '.$sec.'; url='.qualifiedURL($lnk);
        }
        $header=array("Expires: " . gmdate("D, d M Y H:i:s", 0) . " GMT"); 
        if ($myrefresh) $header[]=$myrefresh;

        $p=new WikiPage($options['page'].':'.$options['no'],$options);
        $formatter->page=$p;
        $options['page']=$options['page'].':'.$options['no'];
        $options['saveonly']=1;
        $options['minor']=1; # do not log

        $formatter->send_header($header,$options);
        $options['action_mode']='ajax';
        $options['call']=1;
        $ret=$formatter->ajax_repl('comment',$options);
        if ($ret == false)
            $options['msg']=_("Fail to post comment.");
        unset($options['action_mode']);
        $formatter->send_title("","",$options);

        $formatter->send_footer("",$options);
        return;
        break;
    }
    if ($options['mode'] == 'delete') {

        $msg=sprintf(_("The article %s will be deleted."),$options['no']);
        $url=$formatter->link_url($formatter->page->urlname,'');
        $header=array("Expires: " . gmdate("D, d M Y H:i:s", 0) . " GMT"); 
        $formatter->send_header($header,$options);
        $formatter->send_title("","",$options);
        print <<<EOF
<div class='deleteDialog'>
<form method='post' action='$url' >
<strong>$msg</strong>
<table border='0' width='20%'>
<tbody>
<tr><th>Password:</th><td><input type='password' style="width:200px" name='pass' /></td></tr>
</tbody>
</table>
<input type='hidden' name='no' value='$options[no]' />
<input type='hidden' name='p' value='$options[p]' />
<input type='hidden' name='action' value='bbs' />
<input type='hidden' name='mode' value='delete' />
</form>
</div>
EOF;
        $formatter->send_footer("",$options);
        return;
    } else if ($options['mode'] == 'edit') {
        $button_preview=$options['button_preview'];
        while ($_SERVER['REQUEST_METHOD']=="POST") {
            $savetext=$options['savetext'];
            $datestamp=$options['datestamp'];
            $subject=$options['subject'];
            # strip some tags from the subject
            $subject=
                preg_replace("%</?(marquee|embed|object|script|form|frame|iframe|img|a|)[^>]*>%",
                    '',$subject);
            $args['subject']=_stripslashes($subject);
            if ($options['id']=='Anonymous') {
                $name=$options['name'];
                $name=strip_tags($name);
                $pass=$options['pass'];
                $home=$options['homepage'];
                # check a homepage address
                if (!empty($home)) {
                    if (!preg_match('/^((ftp|http|news):\/\/)[a-z0-9][a-z0-9_\-]+\.[a-z0-9\-\.]+.*/',$home)) {
                        $options['msg']=_("Invalid HomePage address.");
                        break;   
                    } else if (!eregi("^(ftp|http|news):\/\/",$home)) {
                        $home="http://".$home;
                    }
                }
                # check email address
                $email=$options['email'];

                $args['name']=_stripslashes($name);
                $args['pass']=_stripslashes($pass);
                $args['home']=_stripslashes($home);
                $args['email']=_stripslashes($email);
                if (!$name) { $options['msg']=_("No Name error."); break; }
            } else {
                $args['name']=$options['id'];
            }

            $args['no']=$options['no'] ? $options['no']:0;

            if ($options['no'] and !$check_pass) break; # edit mode
            if (!$check_ip) break; # not allowed IPs

            if (!$args['subject'] or !$savetext) { $options['msg']=_("No Subject error."); break; }
            if ($button_preview) break;

            $savetext=preg_replace("/\r\n|\r/", "\n", $savetext);

            if ($savetext and $DBInfo->spam_filter) {
                $text=$savetext;
                $fts=preg_split('/(\||,)/',$DBInfo->spam_filter);
                foreach ($fts as $ft) {
                    $text=$formatter->filter_repl($ft,$text,$options);
                }
                if ($text != $savetext) {
                    $options['msg'] = _("Sorry, can not save page because some messages are blocked in this wiki.");
                    break;
                }
            }

            $savetext=rtrim($savetext)."\n";
            $args['text']=_stripslashes($savetext);

            $MyBBS=macro_BBS($formatter,'',array('new'=>1));
            $myrefresh='';
            if ($DBInfo->use_save_refresh) {
                $sec=$DBInfo->use_save_refresh - 1;
                $lnk=$formatter->link_url($formatter->page->urlname,"?action=show");
                $myrefresh='Refresh: '.$sec.'; url='.qualifiedURL($lnk);
            }
            $header=array("Expires: " . gmdate("D, d M Y H:i:s", 0) . " GMT");
            $options['msg']=_("New post added successfully");

            if ($myrefresh) $header[]=$myrefresh;
            $formatter->send_header($header,$options);
            $formatter->send_title("","",$options);

            if ($MyBBS->use_attach) { # XXX
                $args['call']=1;
                $lists=array();
                $lists=$formatter->macro_repl('Attachments','',$args);

                unset($args['call']);
                if (!empty($lists)) $args['attach']=$lists;
            }
            $MyBBS->savePage($args);

            $formatter->send_footer("",$options);
            return;
        }
        #print _bbs_edit_form();
        #print macro_BBSForm($formatter);
        $formatter->send_header("",$options);
        $formatter->send_title("","",$options);
        if ($options['savetext']) {
            $formatter->_raw_body=$options['savetext'];
            if ($options['no'])
                $hidden="<input type='hidden' name='no' value='$options[no]' />\n".
                        "<input type='hidden' name='p' value='$options[p]' />";
            
        } else if ($options['no']) {
            $MyBBS=macro_BBS($formatter,'',array('new'=>1));
            $nid=$options['no'];
            if ($nid and $MyBBS->hasPage($nid)) {
                $fields=array('Name','Subject','Date','Email','HomePage','IP','Keywords');
                include_once('lib/metadata.php');
                $body=$MyBBS->getPage($nid);

                $boundary= strtoupper(md5("COMMENT")); # XXX
                list($body,$comments)=explode('----'.$boundary."\n",$body,2); # XXX
                if ($body != null) {
                    list($metas,$nbody)=_get_metadata($body);
                    if ($nbody) $body=$nbody;
                    $args['name']=$metas['Name'];
                    $args['subject']=$metas['Subject'];
                    $args['home']=$metas['HomePage'];
                    $args['email']=$metas['Email'];
                    $args['text']=$body;
                    $formatter->_raw_body=$body;
                    $hidden="<input type='hidden' name='no' value='$nid' />\n".
                            "<input type='hidden' name='p' value='$options[p]' />";
                }
            }
        } else
            $formatter->_raw_body="";

        if ($options['id']=='Anonymous')
            $formatter->_extra_form=<<<EOF
<div>
<table border='0' width='100%'>
<col width='10%' /><col width='10%' /><col width='10%' /><col width='70%' />
<tbody>
<tr><th>Subject:</th><td colspan='3'><input type='text' style="width:80%" name='subject' value='$args[subject]' /></td></tr>
<tr><th>Name:</th><td><input type='text' name='name' value='$args[name]' /></td>
    <th>Password:</th><td><input type='password' name='pass' /></td></tr>
<tr><th>Email:</th><td colspan='3'><input type='text' style="width:50%" name='email' value='$args[email]' /></td></tr>
<tr><th>HomePage:</th><td colspan='3'><input type='text'style="width:50%" name='homepage' value='$args[home]' /></td></tr>
</tbody>
</table>
$hidden
</div>
EOF;
        else {
            if (!$check_pass and $options['mode']=='edit')
                $pass_form=
    "<tr><th>Password:</th><td><input type='password' name='pass' /></td></tr>";
            $formatter->_extra_form=<<<EOF
<div>
<table border='0' width='100%'>
<col width='20%' /><col width='80%' />
<tbody>
<tr><th>Subject:</th><td><input type='text' style="width:80%" name='subject' value='$args[subject]' /></td></tr>
$pass_form
</tbody>
</table>
$hidden
</div>
EOF;
        }
        $formatter->_mtime=0;
        $options['simple']=2;
        $options['nocategories']=1;
        $options['minor']=1; # do not show a minor checkbox
        print macro_EditText($formatter,$value,$options);
        $formatter->_raw_body=null;
        $formatter->_extra_form=null;
    } else {
        $formatter->send_header("",$options);
        $formatter->send_title("","",$options);
        print macro_BBS($formatter,'no='.$options['no']);
    }

    $formatter->send_footer("",$options);
    return;
}

// vim:et:sts=4:sw=4:
?>
