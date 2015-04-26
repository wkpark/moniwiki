<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a diff plugin for the MoniWiki
//
// $Id: Diff.php,v 1.27 2010/10/05 22:28:54 wkpark Exp $


function code_diff($diff, $options = array()) {
  global $Config;
  include_once("lib/difflib.php");
  $click = '';
  $numid = '';
  $divs = array();
  $anums = array();
  $fid = 0;

  $header = '';
  $buf = str_replace('<','&lt;', $diff);
  #$buf = str_replace(array('<',"\t"),array('&lt;','        '), $diff);
  $lines = explode("\n",$buf);
  $sz = sizeof($lines);
  $i = 0;
  while($i < $sz) {
    for (; $i < $sz; $i++) {
      if ($lines[$i]{0} == '@') {
        break;
      } else if (preg_match('/^-{3} ([^ \t]+)/',$lines[$i], $m)
        and preg_match('/^\+{3} /',$lines[$i+1])) {
        // get filename
        //$files[] = $m[1];
        break;
      } else if (preg_match('/^={66}/',$lines[$i])) {
        $lines[$i] = "\n";
      }
      $header .= $lines[$i];
    }
    $omarker = 0;
    $orig = array();
    $new = array();

    // for pre block
    $br="\n"; $nl='';
    // for div block
    #$br="<br />"; $nl="\n";
    $next_patch = 0;
    for (;$i < $sz; $i++) {
      $line = $lines[$i];
      $marker = $line{0};
      if (in_array($marker, array('-','+','@',' '))) $line = substr($line, 1);
      else {
        if (empty($new) and empty($orig)) break;
        $next_patch = 1;
      }
      if ($marker=='@' and preg_match('/^@\s\-(\d+)(?:,\d+)?\s\+(\d+)(?:,\d+)?\s@@/',$line,$mat)) {
        $orig = array(); $new=array();
        $omarker = 0;
        $lp = intval($mat[2]); $lm = intval($mat[1]);

        $line = '<div class="diff-sep">@' . "$line</div>";
        $out .= $line . $nl;
        continue;
      }
      else if ($marker == "-") {
        $omarker = 1; $orig[] = $line; continue;
      }
      else if ($marker == "+") {
        $omarker = 1; $new[] = $line; continue;
      }
      else if ($marker == "\\") continue;
      else if ($omarker) {
        $tabidx = ' tabindex="'.($anum + 1).'"';
        $bp = "<a href='#' name='#cr_view".$anum."' id='cr_view".$anum."' class='diffBlock'></a>";

        $anums[] = $fid;
        $anum++;

        $count = sizeof($new);
        $ocount = sizeof($orig);

        $omarker = 0;
        $buf = '';
        $result = new WordLevelDiff($orig, $new, $Config['charset']);
        if (1 or $options['oldstyle']) {
          foreach ($result->orig() as $ll) {
            if (isset($fid)) {
              $key = "f$fid"."_o$lm";
              $anchor = "<a name='but_f$fid"."_o$lm' href='#'></a>";
              $cmtag = '';
              if (isset($review_ar[$key])) {
                $cmtag = "<span class='commentflag selected' onmouseover=\"df_view('overdiv_$key')\" onmouseout=\"hide_div('overdiv_$key')\">".
                  $anchor. $review_ar[$key]['count'] . "</span>";

                $overdivs .= "<div id='overdiv_$key' class='reviewComment'>\n".
                "<strong>".$review_ar[$key]['user']." ".$review_ar[$key]['date']."</strong><br>\n".
                nl2br($review_ar[$key]['body']);
                for ($li=2; $li <= $review_ar[$key]['count']; $li++) {
                  $aikey = 'ai'.$li.$key;
                  $overdivs .= "<br><br>\n<strong>".$review_ar[$aikey]['user']." ".
                    $review_ar[$aikey]['date']."</strong><br>\n".
                    nl2br($review_ar[$aikey]['body']);
                }
                $overdivs .= "</div>\n";
              } else {
                $cmtag = $anchor."<span class='commentflag'></span>";
              }
              $click = " onclick=\"ccmt('o$lm', '$fid');\"";
              $numid = " id='f$fid"."_o$lm'";
            }
            $lm1 = $lm;
            if ($tabidx) {
              $lm1 = "<a href='#but_f$key'$tabidx>".$lm.'</a>';
              $tabidx = '';
            }

            $lll = preg_replace('/^\s*(<div[^>]+>)/',
                "$1<span class='num'>$lm1</span>",$ll,1);
            if ($lll == $ll) $lmm = "<span class='num'>$lm1</span>";
            $buf.= "<div class=\"diff-removed\"$numid$click>$bp$cmtag$lmm$lll</div>".$nl;
            $lmm = '';
            $bp = '';
            $lm++;
          }
          foreach ($result->_final() as $ll) {
            if (isset($fid)) {
              $key = "f$fid"."_n$lp";
              $anchor = "<a name='but_f$fid"."_n$lp'></a>";
              $cmtag = '';
              if (isset($review_ar[$key])) {
                $cmtag = "<span class='commentflag selected' onmouseover=\"df_view('overdiv_$key')\" onmouseout=\"hide_div('overdiv_$key')\">".
                  $anchor. $review_ar[$key]['count'] . "</span>";

                $overdivs .= "<div id='overdiv_$key' class='reviewComment'>\n".
                "<strong>".$review_ar[$key]['user']." ".$review_ar[$key]['date']."</strong><br>\n".
                nl2br($review_ar[$key]['body']);
                for ($li=2; $li <= $review_ar[$key]['count']; $li++) {
                  $aikey = 'ai'.$li.$key;
                  $overdivs .= "<br><br>\n<strong>".$review_ar[$aikey]['user']." ".
                    $review_ar[$aikey]['date']."</strong><br>\n".
                    nl2br($review_ar[$aikey]['body']);
                }
                $overdivs .= "</div>\n";
              } else {
                $cmtag = $anchor."<span class='commentflag'></span>";
              }
              $click = " onclick=\"ccmt('n$lp', '$fid');\"";
              $numid = " id='f$fid"."_n$lp'";
            }
            $lp1 = $lp;
            if ($tabidx) {
              $lp1 = "<a href='#but_f$key'$tabidx>".$lp.'</a>';
              $tabidx = '';
            }

            $lll = preg_replace('/^\s*(<div[^>]+>)/',
                "$1<span class='num'>$lp1</span>",$ll,1);
            if ($lll == $ll) $lpp = "<span class='num'>$lp1</span>";
            $buf.= "<div class=\"diff-added\"$numid$click>$bp$cmtag$lpp$lll</div>".$nl;
            $lpp = '';
            $bp = '';
            $lp++;
          }
        } else {
          foreach ($result->all() as $ll)
            $buf.= "<div class=\"diff\"><span class='num'>$lp</span>$ll</div>".$nl;
        }
        $orig = array(); $new = array();
        $out .= $buf;
        if ($next_patch) {
          $i --;
          break;
        }
        $line .= $br;
        $line = '<span class="num">'.$lm.'</span>'.$line;
        $lp++;
        $lm++;
      }
      else if ($marker==" " and !$omarker) {
        $line .= $br;
        $line = '<span class="num">'.$lm.'</span>'.$line;
        $lp++;
        $lm++;
      } else {
        $line .= $br;
        $line = '<span class="num">'.$lm.'</span>'.$line;
        $lp++;
        $lm++;
      }
      $out .= $line . $nl;
    }
    $click = " onclick=\"var d=$('diffdiv$fid');d.style.display=(d.style.display=='none') ? 'block':'none'; hide_click_comment_div(); \"";
    $divs[] = "<div class='label2'$click><h4 class='h_label'><span>$header</span></h4></div>".'<pre>'.$out.'</pre>';
    $header = '';
    $out = '';
    if (isset($fid)) $fid ++;
  }
  $anumary = "'". implode("','", $anums) ."'";

  $out = '';
  $j = 0;
  $divview = 'block'; # XXX
  foreach ($divs as $d) {
    $out .= "<div class='label_box codeDiff' id='diffdiv$j' style='display:$divview;'>".
      "<a name='difftit$j'></a>" . $d . "</div>\n";
    $j ++;
  }

  return $out.$overdivs;
}

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
    else if ($marker=="\\") continue;
    #else if ($marker=="\\" && $line==" No newline at end of file") continue;
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

  // trash the last empty line;
  $end = end($lines);
  //if (!isset($end[0])) array_pop($lines);
  $out="";
  #unset($lines[0]); unset($lines[1]);

  $omarker=0;
  $orig=array();$new=array();
  foreach ($lines as $line) {
    if (empty($omarker) and empty($line[0])) continue;
    $marker=$line[0];
    if (in_array($marker,array('-','+','@'))) $line=substr($line,1);
    if ($marker=="@") $line='<div class="diff-sep">@'."$line</div>";
    else if ($marker=="-") {
      $omarker=1; $orig[]=$line; continue;
    } else if ($marker=="+") {
      $omarker=1; $new[]=$line; continue;
    } else if ($marker=="\\") continue;
    #} else if ($marker=="\\" && $line==" No newline at end of file") continue;
    else if ($omarker) {
      $omarker=0;
      $buf="";
      $result = new WordLevelDiff($orig, $new, $DBInfo->charset);
      if (empty($options['inline'])) {
        foreach ($result->orig() as $ll)
          $buf.= "<div class=\"diff-removed\">$ll</div>\n";
        foreach ($result->_final() as $ll)
          $buf.= "<div class=\"diff-added\">$ll</div>\n";
      } else {
        foreach ($result->all(null, '', false) as $ll)
          $buf.= "<div class=\"diff\">$ll</div>\n";
      }
      $orig=array();$new=array();
      $line=$buf.$line."<br />";
    }
    else if ($marker==" " and !$omarker)
      $line.="<br />";
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
  #print "<pre>";
  #print_r( $lines);
  #print "</pre>";

  $tags=array("\006","\006","\010","\010");
 
  $news=array(); $dels=array();

  $omarker=0;
  $orig=array();$new=array();
  foreach ($lines as $line) {
    $marker=$line[0];
    $line=substr($line,1);
    if ($marker=='@' and preg_match('/^@\s\-(\d+)(?:,\d+)?\s\+(\d+)(?:,\d+)?\s@@/',$line,$mat)) {
      $lp=$mat[2]; $lm=$mat[1];
    } else if ($marker=='-') {
      $omarker=1; $orig[]=$line; continue;
    } else if ($marker=='+') {
      $omarker=2; $new[]=$line; continue;
    } else if ($marker=="\\") continue;
    #} else if ($marker=="\\" && $line==' No newline at end of file') continue;
    else if ($omarker) {
      $count=sizeof($new);
      $ocount=sizeof($orig);

      $omarker=0;
      $buf='';
      $result = new WordLevelDiff($orig, $new, $DBInfo->charset);

      # rearrange output.
      foreach ($result->all($tags) as $ll)
        $buf.= $ll."\n";

      $buf=substr($buf,0,-1); // drop last added "\n"
      $orig=array();$new=array();

      if ($count != 0) {
        $news[$lp-1]=$buf;
        for ($i=0;$i<$count-1;$i++) $news[$lp+$i]=null;
        #for ($i=$count-1;$i>0;$i--) $news[$lp+$i-1]=null;
      } else if ($ocount != 0) {
        $dels[$lp-1]=$buf;
        for ($i=0;$i<$ocount-1;$i++) $dels[$lp+$i]=null;
      }
      if ($marker==' ') {
        $lp+=$count+1;
        $lm+=$ocount+1;
      }
    }
    else if ($marker==' ' and !$omarker) {
      $lp++;
      $lm++;
    }
  }

  #print "<pre style='color:black;background-color:#93FF93'>";
  #print_r($news);
  #print "</pre>";
  #print "<pre style='color:black;background-color:#FF9797'>";
  #print_r($dels);
  #print "</pre>";
  return array($news,$dels);
}


function macro_diff($formatter,$value,&$options)
{
  global $DBInfo;

  $option='';

  $pi=$formatter->page->get_instructions();
  $formatter->pi=$pi;

  $processor_type=$pi['#format'];
  while ($DBInfo->default_markup != 'wiki') { // XXX
    $processor=$pi['#format'];
    if (!($f=function_exists("processor_".$processor)) and !($c=class_exists('processor_'.$processor))) {
      $pf=getProcessor($processor);
      if (!$pf) break;
      include_once("plugin/processor/$pf.php");
      $processor=$pf;
      $name='processor_'.$pf;
      if (class_exists($name)) {
        $classname='processor_'.$processor;
        $myclass= new $classname($formatter,$options);
        $processor_type=$myclass->_type == 'wikimarkup' ? 'wiki':$pi['#format'];
      }
    } else if ($c=class_exists('processor_'.$processor)) {
      $classname='processor_'.$processor;
      $myclass= new $classname($formatter,$options);
      $processor_type=$myclass->_type == 'wikimarkup' ? 'wiki':$pi['#format'];
    }
    break;
  }

  //if (!in_array($pi['#format'],array('wiki','moni')) and !$options['type']) # is it not wiki format ?
  if ($processor_type != 'wiki' and !$options['type']) # is it not wiki format ?
    $options['type']=$DBInfo->diff_type; # use default diff format

  if (empty($options['type']) and !empty($DBInfo->use_smartdiff))
    $options['type']='smart';

  if (!empty($options['type']) and function_exists($options['type'].'_diff'))
    $type=$options['type'].'_diff';
  else
    $type=$DBInfo->diff_type.'_diff';

  if (!empty($options['text'])) {
    $out= $options['text'];
    if (empty($options['raw']))
      $ret=call_user_func($type,$out, $options);
    else
      $ret="<pre>$out</pre>\n";

    return $ret;
  }

  $rev1=!empty($options['rev']) ? $options['rev'] : ''; // old
  $rev2=!empty($options['rev2']) ? $options['rev2'] : ''; // new

  // check revision number
  if (!empty($rev1) && !preg_match("/^[0-9a-f.]+$/", $rev1)
      || !empty($rev2) && !preg_match("/^[0-9a-f.]+$/", $rev2)) {
    return _("Invalid revision numbers");
  }

  if (!$rev1 and !$rev2) {
    $rev1=$formatter->page->get_rev();
  } else if (0 === strcmp($rev1 , (int)$rev1)) {
    $rev1=$formatter->page->get_rev($rev1); // date
  } else if ($rev1==$rev2) $rev2='';

  #if ($rev1) $option="-r$rev1 ";
  #if ($rev2) $option.="-r$rev2 ";

  if (!$rev1 && !$rev2) {
    $msg= _("No older revisions available");
    if (!empty($options['nomsg'])) return '';
    return "<h2>$msg</h2>";
  }
  if (!$DBInfo->version_class) {
    $msg= _("Version info is not available in this wiki");
    return "<h2>$msg</h2>";
  }
  
  $version = $DBInfo->lazyLoad('version', $DBInfo);
  $out = $version->diff($formatter->page->name,$rev1,$rev2);

  $ret = '';
  if (!$out) {
    $msg= _("No difference found");
  } else {
    #$rev1=substr($rev1,0,5);
    #$rev2=substr($rev2,0,5);
    if ($rev1==$rev2) $ret.= "<h2>"._("Difference between versions")."</h2>";
    else if ($rev1 and $rev2) {
      $msg= sprintf(_("Difference between r%s and r%s"),$rev1,$rev2);
    }
    else if ($rev1 or $rev2) {
      $msg=sprintf(_("Difference between r%s and the current"),$rev1.$rev2);
    }
    if (empty($options['raw'])) {
      $ret= call_user_func($type,$out, $options);
      if (is_array($ret)) { // for smart_diff
        $dels=$ret[1]; $ret=$ret[0];
        $rev=($rev1 and $rev2) ? $rev2:''; // get newest rev.
        if (!empty($rev)) {
          $current=$formatter->page->get_raw_body(array('rev'=>$rev));
        } else {
          $current=$formatter->page->_get_raw_body();
        }
        $lines=explode("\n",$current);
        $nret=$ret;
        foreach ($ret as $k => $v) {
          if ($v=="") continue;
          $tmp=explode("\n",$v);
          $tt=array_pop($tmp);
          if ($tt != '') $tmp[]=$tt;
          for ($kk=0;$kk<sizeof($tmp);$kk++)
          $nret[$k+$kk] = $tmp[$kk];
        }
        foreach ($nret as $k => $v) {
          $lines[$k] = $v;
        }

        # insert deleted lines
        if ($dels) {
          foreach ($dels as $k => $v) {
            $lines[$k]=$v."\n".$lines[$k];
          }
        }
        $diffed=implode("\n",$lines);
        # change for headings
        $diffed=preg_replace("/^(\006|\010)(={1,5})\s(.*)\s\\2\\1$/m",
          "\\2 \\1\\3\\1 \\2",$diffed);
        # change for lists
        $diffed=preg_replace("/(\006|\010)(\s+)(\*|\d+\.\s)(.*)\\1/m",
          "\\2\\3\\1\\4\\1",$diffed);

        # fix <ins>{{{foobar</ins> to {{{<ins>foobar</ins>
        $diffed=preg_replace("/(\006|\010)({{{)(.*)$/m","\\2\\1\\3",$diffed);
        # fix <ins>foobar}}}</ins> to <ins>foobar</ins>}}}
        $diffed=preg_replace("/(\006|\010)(.*)(}}})(\\1)/m","\\1\\2\\4\\3",$diffed);
        # change for hrs
        $diffed=preg_replace("/(\006|\010)(-{4,})\\1/m",
          "\\1\\2\n\\1",$diffed);
        # XXX FIXME
        # merge multiline diffs
        #$diffed=preg_replace("/\006([ ]*)\006$/m","\\1",$diffed);
        #$diffed=preg_replace("/\010([ ]*)\010$/m","\\1",$diffed);
        $diffed=preg_replace("/\006\n\006(?!\n)/m","\n",$diffed);
        $diffed=preg_replace("/\010\n\010(?!\n)/m","\n",$diffed);

        $options['nomsg']=0;
        $options['msg']=$msg;
        $options['smart']=1;

        #if (!in_array($pi['#format'],array('wiki','moni')))
        if ($processor_type != 'wiki')
          print '<pre class="code">'.$diffed.'</pre>';
        else
          $formatter->send_page($diffed,$options);
        #print "<pre>".str_replace(array("\010","\006"),array("+++","---"),$diffed)."</pre>";
        #print "<pre>".$diffed."</pre>";
        return;
      }
    }
    else {
      $out=str_replace('<','&lt;',$out);
      $ret="<pre>$out</pre>\n";
    }
  }
  if (!empty($options['nomsg'])) return $ret;
  return "<h2>$msg</h2>\n$ret";
}

function do_diff($formatter,$options="") {
  global $DBInfo;

  $range=!empty($options['range']) ? $options['range'] : '';
  $date=!empty($options['date']) ? $options['date'] : '';
  $rev=!empty($options['rev']) ? $options['rev'] : '';
  $rev2=!empty($options['rev2']) ? $options['rev2'] : '';

  // check revision number
  if (!empty($rev) && !preg_match("/^[0-9a-f.]+$/", $rev) || !empty($rev2) && !preg_match("/^[0-9a-f.]+$/", $rev2)) {
    $options['title']=_("Invalid revision numbers");
    $options['msg']=_("Please set correct revision numbers");
    do_invalid($formatter, $options);
    return;
  }

  if (!empty($options['rcspurge'])) {
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

  if (!empty($options['type']) and
    !in_array($options['type'],array('smart','fancy','simple')))
    $options['type']=$DBInfo->diff_type;
  else
    $options['type']=$DBInfo->diff_type;

  $formatter->send_header("",$options);

  $title='';
  if (!empty($DBInfo->use_smartdiff)) {
    $rev=substr($rev,0,5);
    $rev2=substr($rev2,0,5);
    if ($rev and $rev2)
      $msg= sprintf(_("Difference between r%s and r%s"),$rev,$rev2);
    else if ($rev)
      $msg= sprintf(_("Difference between r%s and the current"),$rev);
    else
      $msg=_("latest changes");
    $title=$msg;
  }
  $formatter->send_title($title,"",$options);

  $class = 'Diff';
  if ($options['type'] == 'fancy' and !empty($options['inline'])) $class.= 'Inline';
  echo '<div class="'.$options['type'].$class.'">';
  if ($date) {
    $options['rev']=$date;
    print macro_diff($formatter,'',$options);
  }
  else
    print macro_diff($formatter,'',$options);
  echo '</div>';
  if (empty($DBInfo->diffonly) and empty($options['smart'])) {
    print "<br /><hr />\n";
    $formatter->send_page();
  }
  $formatter->send_footer('',$options);
  return;
}

// vim:et:sts=2:sw=2:
?>
