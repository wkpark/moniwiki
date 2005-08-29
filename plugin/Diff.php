<?php
// Copyright 2003-2004 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a diff plugin for the MoniWiki
//
// $Id$

function macro_diff($formatter,$value,&$options)
{
  global $DBInfo;

  $option='';

  if ($options['text']) {
    if (0) {
      $tmpf=tempnam($DBInfo->vartmp_dir,'DIFF');
      $fp= fopen($tmpf, 'w');
      fwrite($fp, $options['text']);
      fclose($fp);

      $fp=popen('diff -u '.$formatter->page->filename.' '.$tmpf,'r');
      if (!$fp) {
        unlink($tmpf);
        return '';
      }
      fgets($fp,1024); fgets($fp,1024);
      while (!feof($fp)) {
        $line=fgets($fp,1024);
        $out .= $line;
      }
      pclose($fp);
      unlink($tmpf);
    } else {
      $current=$formatter->page->get_raw_body();
      include_once('lib/difflib.php');
      $mydiff=new Diff(explode("\n",$current),explode("\n",$options['text']));

      $fmtdiff = new UnifiedDiffFormatter;
      $out = $fmtdiff->format($mydiff);
    }

    if (!$out) {
       $msg=_("No difference found");
    } else {
       $msg= _("Difference between yours and the current");
       if (!$options['raw'])
         $ret=call_user_func(array(&$formatter,$DBInfo->diff_type.'_diff'),$out);
       else
         $ret="<pre>$out</pre>\n";
    }
    if ($options['nomsg']) return $ret;
    return "<h2>$msg</h2>\n$ret";
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
    if (!$options['raw'])
      $ret= call_user_func(array(&$formatter,$DBInfo->diff_type.'_diff'),$out);
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
  $formatter->send_header("",$options);
  $formatter->send_title("Diff for $rev ".$options['page'],"",$options);
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
