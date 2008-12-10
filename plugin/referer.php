<?php

function do_referer($formatter, $options)
{
  $out= macro_referer($formatter,$options['value'],&$options);

  return $out;
}

/* snippet from http://au2.php.net/manual/en/function.fseek.php */
function tail_file($file, $lines)
{
        $handle = fopen($file, "r");
        $linecounter = $lines;
        $pos = -2;
        $beginning = false;
        $text = array();
        while ($linecounter > 0) {
                $t = " ";
                while ($t != "\n") {
                        if(fseek($handle, $pos, SEEK_END) == -1) {
                                $beginning = true; break; }
                        $t = fgetc($handle);
                        $pos --;
                }
                $linecounter --;
                if($beginning) rewind($handle);
                $text[$lines-$linecounter-1] = fgets($handle);
                if($beginning) break;
        }
        fclose ($handle);
        return $text;
}

function macro_referer($formatter="",$value, &$options) {
  global $DBInfo;
  if (!$DBInfo->use_referer)
    return "[[referer macro: $use_referer is off.]]";
  $referer_log_filename = $DBInfo->cache_dir."/referer/referer.log";

  if ($value !== true) {
    // [[referer]] or ?action=referer
    $needle = $formatter->page->name;
  } else {
    // [[referer()]]
    unset($needle);
  }

  if ($needle) {
    $handle = fopen($referer_log_filename, 'r');
    $logs = array();
    while(!feof($handle)) {
      $line = fgets($handle);
      list(, $pagename,) = explode("\t", $line);
      if ($pagename == $needle)
        $logs[] = $line;
    }
    fclose($handle);
    $logs = array_reverse($logs);
  } else {
    $number_of_lines = 10;
    $logs = tail_file ($referer_log_filename, $number_of_lines);
  }

  $user=$DBInfo->user; # retrive user info
  if ($user->id != 'Anonymous') {
    $tz_offset= $user->info['tz_offset'];
  }
  if ($tz_offset == '') {
    $tz_offset=date("Z");
  }
  
  $length = sizeof($logs);
  for ($c = 0; $c < $length; $c++) {
    $fields = explode("\t", $logs[$c]);
    $fields[0] = date("Y-m-d H:i:s", strtotime($fields[0])+$tz_offset);
    $fields[1] = $formatter->link_tag(urlencode($fields[1]), "", $fields[1]);
    if (ereg("[?&][pqQ](uery)?=([^&]+)&?", $fields[2], $regs)) {
      $found = urldecode($regs[2]);
      if (@iconv("utf-8", "cp949", $found)) {
        $found = iconv("utf-8", "cp949", $found);
      }
    } else {
      unset($found);
    }
    $fields[2] = ($found ? "[ $found ] " : "") ."<a href='$fields[2]'>".$fields[2]."</a>";

    if (isset($needle)) unset($fields[1]);
    $logs[$c] = "<td>". implode("</td><td>", $fields) ."<td>";
  }
  if ($length > 0) {
    $ret = "\n<table>";
    $ret.= "<caption>Referer history</caption>";
    $ret.= "<tr>";
    $ret.= implode("</tr>\n<tr>", $logs);
    $ret.= "</tr></table>\n";
  } else {
    $ret = "";
  }

  return $ret;
}

// vim:et:ts=2:
?>
