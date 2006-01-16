<?php
// Copyright 2003-2004 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a diff plugin for the MoniWiki
//
// $Id$

function simple_diff($diff) {
  $diff=str_replace("<","&lt;",$diff);
  $out="";
  //unset($lines[0]); unset($lines[1]); // XXX

  for ($line=strtok($diff,"\n"); $line !== false;$line=strtok("\n")) {
    $marker=$line[0];
    $line=substr($line,1);
    if ($marker=="@") $line='<div class="diff-sep">@'."$line</div>";
    else if ($marker=="-") $line='<div class="diff-removed">'."$line</div>";
    else if ($marker=="+") $line='<div class="diff-added">'."$line</div>";
    else if ($marker=="\\" && $line==" No newline at end of file") continue;
    else $line.="<br />";
    $out.=$line."\n";
  }
  return $out;
}

function fancy_diff($diff,$options=array()) {
  global $DBInfo;
  include_once("lib/difflib.php");
  $diff=str_replace("<","&lt;",$diff);
  $lines=explode("\n",$diff);
  $out="";
  #unset($lines[0]); unset($lines[1]);

  $omarker=0;
  $orig=array();$new=array();
  foreach ($lines as $line) {
    $marker=$line[0];
    if (in_array($marker,array('-','+','@'))) $line=substr($line,1);
    if ($marker=="@") $line='<div class="diff-sep">@'."$line</div>";
    else if ($marker=="-") {
      $omarker=1; $orig[]=$line; continue;
    }
    else if ($marker=="+") {
      $omarker=1; $new[]=$line; continue;
    }
    else if ($omarker) {
      $omarker=0;
      $buf="";
      $result = new WordLevelDiff($orig, $new, $DBInfo->charset);
      if ($options['oldstyle']) {
        foreach ($result->orig() as $ll)
          $buf.= "<div class=\"diff-removed\">$ll</div>\n";
        foreach ($result->_final() as $ll)
          $buf.= "<div class=\"diff-added\">$ll</div>\n";
      } else {
        foreach ($result->all() as $ll)
          $buf.= "<div class=\"diff\">$ll</div>\n";
      }
      $orig=array();$new=array();
      $line=$buf.$line."<br />";
    }
    else if ($marker==" " and !$omarker)
      $line.="<br />";
    else if ($marker=="\\" && $line==" No newline at end of file") continue;
    else $line.="<br />";
    $out.=$line."\n";
  }
  return $out;
}

function smart_diff($diff) {
  global $DBInfo;
  include_once("lib/difflib.php");
  $diff=str_replace("<","&lt;",$diff);
  $lines=explode("\n",$diff);
  #unset($lines[0]); unset($lines[1]);

  $tags=array("(%%","%%)","(@@","@@)");
 
  $newlines=array();

  $omarker=0;
  $orig=array();$new=array();
  foreach ($lines as $line) {
    $marker=$line[0];
    $line=substr($line,1);
    if ($marker=="@" and preg_match('/^@\s\-\d+,\d+\s\+(\d+),\d+\s@@/',$line,$mat))
      $lp=$mat[1];
    else if ($marker=="-") {
      $omarker=1; $orig[]=$line; continue;
    }
    else if ($marker=="+") {
      $omarker=1; $new[]=$line; continue;
    }
    else if ($omarker) {
      $count=max(sizeof($new),sizeof($orig));
      $omarker=0;
      $buf='';
      $result = new WordLevelDiff($orig, $new, $DBInfo->charset);

      # rearrange output.
      foreach ($result->all($tags) as $ll)
        $buf.= $ll."\n";
      $orig=array();$new=array();

      $newlines[$lp-1]=$buf;
      for ($i=$count-1;$i>0;$i--) $newlines[$lp+$i-1]=null;
      if ($marker==" ") $lp+=$count+1;
    }
    else if ($marker==" " and !$omarker) {
      $lp++;
    }
    else if ($marker=="\\" && $line==" No newline at end of file") continue;
  }

  #print "<pre style='color:white;background-color:black'>";
  #print_r($newlines);
  #print "</pre>";
  return $newlines;
}


function macro_diff($formatter,$value,&$options)
{
  global $DBInfo;

  $option='';

  if ($options['type'] and function_exists($options['type'].'_diff'))
    $type=$options['type'].'_diff';
  else
    $type=$DBInfo->diff_type.'_diff';

  if ($options['text']) {
    $out= $options['text'];
    if (!$options['raw'])
      $ret=call_user_func($type,$out);
    else
      $ret="<pre>$out</pre>\n";

    return $ret;
  }

  $rev1=$options['rev'];
  $rev2=$options['rev2'];
  if (!$rev1 and !$rev2) {
    $rev1=$formatter->page->get_rev();
  } else if (0 === strcmp($rev1 , (int)$rev1)) {
    $rev1=$formatter->page->get_rev($rev1); // date
  } else if ($rev1==$rev2) $rev2='';
  if ($rev1) $option="-r$rev1 ";
  if ($rev2) $option.="-r$rev2 ";

  if (!$rev1 && !$rev2) {
    $msg= _("No older revisions available");
    if ($options['nomsg']) return '';
    return "<h2>$msg</h2>";
  }
  if (!$DBInfo->version_class) {
    $msg= _("Version info is not available in this wiki");
    return "<h2>$msg</h2>";
  }
  
  getModule('Version',$DBInfo->version_class);
  $class='Version_'.$DBInfo->version_class;
  $version=new $class ($DBInfo);

  $out=$version->diff($formatter->page->name,$rev1,$rev2);

  if (!$out) {
    $msg= _("No difference found");
  } else {
    if ($rev1==$rev2) $ret.= "<h2>"._("Difference between versions")."</h2>";
    else if ($rev1 and $rev2) {
      $msg= sprintf(_("Difference between r%s and r%s"),$rev1,$rev2);
    }
    else if ($rev1 or $rev2) {
      $msg=sprintf(_("Difference between r%s and the current"),$rev1.$rev2);
    }
    if (!$options['raw']) {
      #print "<pre>$out</pre>";
      $ret= call_user_func($type,$out);
      if (is_array($ret)) { // for smart_diff
        $rev=$rev2 ? $rev2:$rev1;
        $current=$formatter->page->get_raw_body(array('rev'=>$rev));
        $lines=explode("\n",$current);
        #print "<pre>";
        #print_r($lines);
        #print_r($ret);
        $nret=$ret;
        foreach ($ret as $k => $v) {
          if ($v=="") continue;
          $tmp=explode("\n",$v);
          array_pop($tmp);
          for ($kk=0;$kk<sizeof($tmp);$kk++)
          $nret[$k+$kk] = $tmp[$kk];
        }
        #print_r($nret);
        #print "</pre>";
        foreach ($nret as $k => $v) {
          $lines[$k] = $v;
        }
        ksort($lines);
        #print_r($lines);
        $diffed=implode("\n",$lines);
        $diffed=preg_replace("/\@@\)\n\(@@/m","\n",$diffed);
        $diffed=preg_replace("/\%%\)\n\(%%/m","\n",$diffed);
        $diffed=preg_replace(array("/\(@@(.*)@@\)/","/\(%%(.*)%%\)/"),
          array("<ins class='diff-added'>\\1</ins>",
                "<del class='diff-removed'>\\1</del>"),
          $diffed);

        $diffed=preg_replace(array(
            "/\n\(@@/m","/@@\)\n/m","/\(@@/","/@@\)/",
            "/\n\(%%/m","/%%\)\n/m","/\(%%/","/%%\)/"),
          array(
            "\n<div class='diff-added'>","\n</div>",
            "<ins class='diff-added'>","\n</ins>",
            "\n<div class='diff-removed'>","\n</div>",
            "<del class='diff-removed'>","</del>")
            ,$diffed);
        $options['nomsg']=1;
        return $formatter->send_page($diffed,$options);
        #return "<pre>$diffed</pre>";
      }
    }
    else
      $ret="<pre>$out</pre>\n";
  }
  if ($options['nomsg']) return $ret;
  return "<h2>$msg</h2>\n$ret";
}

function do_diff($formatter,$options="") {
  global $DBInfo;

  $range=$options['range'];
  $date=$options['date'];
  $rev=$options['rev'];
  $rev2=$options['rev2'];
  if ($options['rcspurge']) {
    if (!$range) $range=array();
    $rr='';
    $dum=array();
    foreach (array_keys($range) as $r) {
      if (!$rr) $rr=$range[$r];
      if ($range[$r+1]) continue;
      else
        $rr.=":".$range[$r];
      $dum[]=$rr;$rr='';
    }
    $options['range']=join(';',$dum);
    include_once("plugin/rcspurge.php");

    do_RcsPurge($formatter,$options);
    return;
  }
  if ($DBInfo->use_smartdiff) $options['type']='smart';
  $formatter->send_header("",$options);
  if ($rev)
    $title=$options['page']. sprintf(_(" (with diff for %s)"),$rev);
  else
    $title=$options['page']. _(" (with diff)");
  $formatter->send_title($title,"",$options);
  if ($date) {
    $options['rev']=$date;
    print macro_diff($formatter,'',$options);
  }
  else
    print macro_diff($formatter,'',$options);
  if (!$DBInfo->diffonly) {
    print "<br /><hr />\n";
    $formatter->send_page();
  }
  $formatter->send_footer($args,$options);
  return;
}

// vim:et:sts=2:
?>
