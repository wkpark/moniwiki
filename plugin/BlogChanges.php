<?php
// Copyright 2003-2013 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a BlogChanges action plugin for the MoniWiki
//
// $Id: BlogChanges.php,v 1.40 2010/08/23 09:19:15 wkpark Exp $

class Blog_cache {
  function get_all_blogs() {
    global $DBInfo;

    $blogs=array();

    $cache = new Cache_Text('blog', array('hash'=>''));
    $cache->_caches($blogs);
    return $blogs;
  }

  function get_daterule() {
    $date=date('Y-m');
    list($year,$month)=explode('-',$date);
    $mon=intval($month);
    $y=$year;
    $daterule = '(?='.$y.$month;
    for ($i=1;$i<3;$i++) {
      if (--$mon <= 0) {
        $mon=12;
        $y--;
      }
      $daterule.='|'.$y.sprintf("%02d",$mon);
    }
    $daterule.=')';
    #print $daterule;
    # (200402|200401|200312)
    return $daterule;
  }

  function get_categories() {
    global $DBInfo;

    if (!$DBInfo->hasPage($DBInfo->blog_category)) return array();
    $categories=array();

    $page=$DBInfo->getPage($DBInfo->blog_category);

    $raw=$page->get_raw_body();
    $raw=preg_replace("/(\{\{\{$)(.*)(\}\}\})/ms",'',$raw);
    $temp= explode("\n",$raw);

    foreach ($temp as $line) {
      if (preg_match('/^ \* ([^:]+)(?=\s|:|$)/',$line,$match)) {
        $category=rtrim($match[1]);
        if (!isset($categories[$category]))
          // include category page itself.
          $categories[$category]=array($category);
      } else if (!empty($category)
        and preg_match('/^\s{2,}\* ([^:]+)(?=\s|:|$)/',$line,$match)) {
        // sub category (or blog pages list)
        $subcategory=rtrim($match[1]);
        $categories[$category][]=$subcategory;
        // all items are regarded as a category
        $categories[$subcategory]=array($subcategory);
      }
    }
    return $categories;
  }

  function get_simple($blogs,$options) {
    global $DBInfo;

    $cache = new Cache_text('blog', array('hash'=>''));
    $logs=array();

    foreach ($blogs as $blog) {
      $pagename=$DBInfo->keyToPagename($blog);
      $pageurl=_urlencode($pagename);
      $key = $DBInfo->pageToKeyname($blog);
      $tmp = $cache->fetch($key);

      $items = explode("\n", $tmp);
      array_pop($items); // trash last empty line
      foreach ($items as $line) {
        list($dummy, $dummy2, $tmp) = explode("\t", $line, 3);
        $logs[]=explode("\t", $pageurl."\t".rtrim($tmp), 4);
      }
    }
    return $logs;
  }

  function get_rc_blogs($date,$pages=array()) {
    global $DBInfo;
    $blogs=array();
    $changecache = new Cache_text('blogchanges', array('hash'=>''));
    $files = array();
    $changecache->_caches($files);

    $bc = new Blog_cache;
    if (!$date)
      $date = $bc->get_daterule();

    if (!$pages) {
      $pagerule='.*';
    } else {
      $pages=array_map('_preg_search_escape',$pages);
      $pagerule=implode('|',$pages);
    }
    $rule="@^($date\d*)\.($pagerule)$@";

    foreach ($files as $file) {
      $pagename=$DBInfo->keyToPagename($file);
      if (preg_match($rule,$pagename,$match))
        $blogs[]=$match[2];
    }
    return array_unique($blogs);
  }

  function get_summary($blogs,$options) {
    global $DBInfo;

    if (!$blogs) return array();
    $date = !empty($options['date']) ? $options['date'] : '';

    if ($date) {
      // make a date pattern to grep blog entries
      $check=strlen($date);
      if (($check < 4) or !preg_match('/^\d+/',$date)) $date=date('Y\-m');
      else {
        if ($check==6) $date=substr($date,0,4).'\-'.substr($date,4);
        else if ($check==8) $date=substr($date,0,4).'\-'.substr($date,4,2).'\-'.substr($date,6);
        else if ($check!=4) $date=date('Y\-m');
      }
      #print $date;
    } else {
      $date = '\d{4}-\d{2}-\d{2}T';
    }

    $entries=array();
    $logs=array();

    foreach ($blogs as $blog) {
      $pagename=$DBInfo->keyToPagename($blog);
      $pageurl=_urlencode($pagename);
      $page=$DBInfo->getPage($pagename);

      $raw=$page->get_raw_body();
      $temp= explode("\n",$raw);

      $summary = '';
      foreach ($temp as $line) {
        if (empty($state)) {
          if (preg_match("/^({{{)?#!blog\s(.*)\s($date"."[^ ]+)\s?(.*)?$/", $line, $match)) {
            $entry = array($pageurl, $match[2], $match[3], $match[4]);
            if ($match[1]) $endtag='}}}';
            $state=1;
            $commentcount=0;
          }
          continue;
        }
        if (preg_match("/^$endtag$/",$line)) {
          $state=0;
          $comments = '';
          if (preg_match("/----\n/", $summary))
            list($content,$comments)=explode("----\n",$summary,2);
          else
            $content = $summary;
          $entry[]=$content;
          $commentcount = 0;
          if ($comments and empty($options['noaction']))
            $commentcount=sizeof(explode("----\n",$comments));
          $entry[]=$commentcount;
          $entries[]=$entry;
          $summary='';
          continue;
        }
        $summary.=$line."\n";
      }
    }
    return $entries;
  }
}

function BlogCompare($a,$b) {
  if ($a[2] == $b[2]) return 0;
  # date:2nd field
  # title:3rd field
  # return strcmp($a[3],$b[3]);
  return ($a[2] > $b[2]) ? -1:1;
}

function do_BlogChanges($formatter,$options='') {
#  if (!$options['date']) $options['date']=date('Ym');
  $options['action']=1;
  $options['summary']=1;
  $options['simple']=1;
  $options['all']=1;
# $options['mode'] // XXX
  if (!empty($options['mode']))
    $arg = 'all,'.$options['mode'];
  else
    $arg = 'all';

  $changes=macro_BlogChanges($formatter,$arg,$options);
  $formatter->send_header('',$options);
  if (!empty($options['category']))
    $formatter->send_title(_("Category: ").$options['category'],'',$options);
  else
    $formatter->send_title(_("BlogChanges"),'',$options);
  print '<div id="wikiContent">';
  print $changes;
  print '</div>';
  #$args['editable']=-1;
  // XXX
  $formatter->pi['#action']='BlogCategories';
  $args['noaction']=1;

  $formatter->send_footer($args,$options);
  return;
}

function macro_BlogChanges($formatter,$value,$options=array()) {
  global $DBInfo;

  $tz_off=&$formatter->tz_offset;

  if (empty($options)) $options=array();
  if (!empty($_GET['date']))
    $options['date']=$date=$_GET['date'];
  else
    $date = !empty($options['date']) ? $options['date'] : '';

  // parse args
  preg_match("/^(('|\")([^\\2]+)\\2)?,?(\s*,?\s*.*)?$/",
    $value,$match);

  $opts=explode(',',$match[4]);
  $opts=array_merge($opts,array_keys($options));
  #print_r($match);print_r($opts);
  if (in_array('noaction',$opts))
    $options['noaction']=1;

  $category_pages=array();

  $options['category']=!empty($options['category']) ? $options['category']:$match[3];

  $bc = new Blog_cache;

  if (!empty($options['category'])) {
    $options['category']=
      preg_replace('/(?<!\.|\)|\])\*/','.*',$options['category']);
    
    $test=@preg_match("/".str_replace('/','\/',$options['category'])."/",'');
    if ($test === false) {
      return '[[BlogChanges('.
        sprintf(_("Invalid category expr \"%s\""),$options['category']).')]]';
    }
    if ($DBInfo->blog_category) {
      $categories = $bc->get_categories();
      if (isset($categories[$options['category']]))
        $category_pages=$categories[$options['category']];
    }
    if (!$category_pages) {
      if ($DBInfo->hasPage($options['category'])) {
        // category is not found
        // regard it as a single blog page
        $blog_page=$options['category'];
      } else {
        // or category pattern like as 'Blog/Misc/.*'
        $category_pages=array($options['category']);
      }
    }
  } else
    $opts['all']=1;

  foreach ($opts as $opt)
    if (($temp= intval($opt)) > 1) break;
  $limit = ($temp > 1) ? $temp:0;
 
  if (!$limit) {
    if ($date) $limit=30;
    else $limit=10;
  }

  #print_r($category_pages);
  if (in_array('all',$opts) or $category_pages) {
    $blogs = $bc->get_rc_blogs($date,$category_pages);
  } else if ($blog_page)
    //$blogs=array($DBInfo->pageToKeyname($blog_page));
    $blogs=array($blog_page);

#  if (empty($blogs)) {
#    // no blog entries found
#    return _("No entries found");
#  }
#  print_r($blogs);

  if (in_array('summary',$opts))
    $logs = $bc->get_summary($blogs,$options);
  else
    $logs = $bc->get_simple($blogs,$options);
  usort($logs,'BlogCompare');

  // get the number of trackbacks
  $trackback_list=array();
  if ($DBInfo->use_trackback) {
    #read trackbacks and set entry counter
    $cache= new Cache_text('trackback');
    foreach ($blogs as $blog) {
      if ($cache->exists($blog)) {
        $trackback_raw=$cache->fetch($blog);

        $trackbacks=explode("\n",$trackback_raw);
        foreach ($trackbacks as $trackback) {
          if (($p = strpos($trackback, "\t")) !== false) {
          list($dummy,$entry,$extra)=explode("\t",$trackback);
          if ($entry) {
            if(isset($trackback_list[$blog][$entry]))
              $trackback_list[$blog][$entry]++;
            else
            $trackback_list[$blog]=array($entry=>1);
          }
          }
        }
      }
    }
  }

  if (empty($options['date']) or !preg_match('/^\d{4}-?\d{2}$/',$options['date']))
    $date=date('Ym');

  $year=substr($date,0,4);
  $month=substr($date,4,2);
  $day=substr($date,6,2);

  if (strlen($date)==8) {
    $prev_date= gmdate('Ymd',mktime(0,0,0,$month,intval($day) - 1,$year)+$tz_off);
    $next_date= gmdate('Ymd',mktime(0,0,0,$month,intval($day) + 1,$year)+$tz_off);
  } else if (strlen($date)==6) {
    $cdate=date('Ym');
    $prev_date= date('Ym',mktime(0,0,0,intval($month) - 1,1,$year)+$tz_off);
    if ($cdate > $date)
      $next_date= gmdate('Ym',mktime(0,0,0,intval($month) + 1,1,$year)+$tz_off);
  }

  // set output style
  if (in_array('simple',$opts) or in_array('summary',$opts)) {
    $bra="";
    $sep="<br />";
    $bullet="";
    $cat="";
  } else {
    $bra="<ul class='blog-list'>";
    $bullet="<li class='blog-list'>";
    $sep="</li>\n";
    $cat="</ul>";
  }
  $template='$out="$bullet<a href=\"$url#$tag\">$title</a> '.
    '<span class=\"blog-user\">';
  if (in_array('summary',$opts))
    $template='$out="$bullet<div class=\"blog-summary\"><div class=\"blog-title\"><a name=\"$tag\"></a>'.
      '<a href=\"$url#$tag\">$title</a> <a class=\"perma\" href=\"#$tag\">'.
      addslashes($formatter->perma_icon).
      '</a></div><span class=\"blog-user\">';
  if (!in_array('nouser',$opts))
    $template.='by $user ';
  if (!in_array('nodate',$opts))
    $template.='@ $date ';

  if (in_array('summary',$opts))
    $template.='</span><div class=\"blog-content\">$summary</div>$btn</div>\n";';
  else
    $template.='</span>$sep\n";';
    
  $time_current= time();
  $items='';
  $date_anchor = '';

  $sendopt['nosisters']=1;

  $save_page = $formatter->page;
  foreach ($logs as $log) {
    #list($page, $user,$date,$title,$summary,$commentcount)= $log;
    $page = $log[0];
    $user = $log[1];
    $date = $log[2];
    $title = $log[3];
    $summary = !empty($log[4]) ? $log[4] : '';
    $commentcount = !empty($log[5]) ? $log[5] : '';

    $tag=md5($user.' '.$date.' '.$title);
    $datetag='';

    $url=qualifiedUrl($formatter->link_url(_urlencode($page)));
    if (empty($opts['nouser'])) {
      if (preg_match('/^[\d\.]+$/',$user)) {
        if (!$DBInfo->mask_hostname and $DBInfo->interwiki['Whois'])
          $user='<a href="'.$DBInfo->interwiki['Whois'].$user.'">'.
            _("Anonymous").'</a>';
        else
          $user=_("Anonymous");#"[$user]";
      } else if ($DBInfo->hasPage($user)) {
        $user=$formatter->link_tag(_rawurlencode($user),'',$user);
      }
    }

    if (!$title) continue;

    $date[10]=' ';
    $time=strtotime($date.' GMT');

    $date= gmdate('m-d [h:i a]',$time+$tz_off);
    if (!empty($summary)) {
      $anchor= date('Ymd',$time);
      if ($date_anchor != $anchor) {
        $date_anchor_fmt=$DBInfo->date_fmt_blog;
        $datetag= '<div class="blog-date">'.date($date_anchor_fmt,$time).
          ' <a name="'.$anchor.'"></a><a class="perma" href="#'.$anchor.'">'.
          $formatter->perma_icon.'</a></div>';
        $date_anchor= $anchor;
      }
      $p=new WikiPage($page);
      $formatter->page = $p;
      $summary=str_replace('\}}}','}}}',$summary); # XXX
      ob_start();
      $formatter->send_page($summary,$sendopt);
      $summary=ob_get_contents();
      ob_end_clean();

      if (empty($options['noaction'])) {
        if ($commentcount) {
          $add_button=($commentcount == 1) ? _("%s comment"):_("%s comments");
        } else
          $add_button=_("Add comment");
        $count_tag = '<span class="count">'.$commentcount.'</span>';
        $add_button=sprintf($add_button, $count_tag);
        $btn= $formatter->link_tag(_urlencode($page),"?action=blog&amp;value=$tag#BlogComment",$add_button);

        if ($DBInfo->use_trackback) {
          if (isset($trackback_list[$page][$tag]))
            $counter=' ('.$trackback_list[$page][$tag].')';
          else
            $counter='';

          $btn.= ' | '.$formatter->link_tag(_urlencode($page),"?action=trackback&amp;value=$tag",_("track back").$counter);
        }
        $btn="<div class='blog-action'><span class='bullet'>&raquo;</span> ".$btn."</div>\n";

      } else
        $btn='';
    }

    eval($template);
    $items.=$datetag.$out;
    if (--$limit <= 0) break;
  }

  $formatter->page = $save_page;
  $url=qualifiedUrl($formatter->link_url($DBInfo->frontpage));

  # make pnut
  $action = '';
  if (!empty($options['action'])) $action='action=blogchanges&amp;';
  if (!empty($options['category'])) $action.='category='.$options['category'].'&amp;';
  if (!empty($options['mode'])) $action.='mode='.$options['mode'].'&amp;';

  $prev=$formatter->link_to('?'.$action.'date='.$prev_date,"<span class='bullet'>&laquo;</span> ".
    _("Previous"));
  $next = '';
  if (!empty($next_date))
    $next=" | ".$formatter->link_to('?'.$action.'date='.$next_date,
      _("Next")." <span class='bullet'>&raquo;</span>");
  return $bra.$items.$cat.'<div class="blog-action">'.$prev.$next.'</div>';
}
// vim:et:sts=2:
?>
