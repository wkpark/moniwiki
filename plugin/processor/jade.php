<?
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a docbook processor plugin for the MoniWiki
//
// Usage: {{{#!jade
// docbook code
// }}}
// $Id$

function processor_jade($formatter,$value) {
  global $DBInfo;

#  'jade ' +
#  "-V %%root-filename%%='%s-x0' " % tmpfile +
#  "-V %%html-prefix%%='%s-' " % tmpfile +
#  "-V '(define %use-id-as-filename% #f)' " +
#  '-t sgml -i html -d %s#html ' % (DEFAULT_DSL) +
#  ' ' + tmpfile + '.sgml')

  $pagename=$formatter->page->name;
  $cache= new Cache_text("jade");

  if (!$formatter->preview and $cache->exists($pagename) and $cache->mtime($pagename) > $formatter->page->mtime())
    return $cache->fetch($pagename);

  $jade= "jade";
#  $args= "-V %%root-filename%%='$tmpfile-x0' ".
#         "-V %%html-prefix%%='$tmpfile-' ".
  $args= "-V '(define %use-id-as-filename% #f)' ".
         "-t sgml -i html ".
         "-V nochunks -o /dev/stdout";
# jade -V nochunks -t sgml -i html vim.sgml -o /dev/stdout

  if ($value[0]=='#' and $value[1]=='!') {
    list($line,$value)=explode("\n",$value,2);
    # get parameters
    list($tag,$args)=explode(" ",$line,2);
  }

  list($line,$body)=explode("\n",$value,2);
  $buff="";
  while(($line[0]=='<') or !$line) {
    preg_match("/^<\?stylesheet\s+href=\"([^\"]+)\"/",$line,$match);
    if ($match) {
      if ($DBInfo->hasPage($match[1]))
        $line='<?stylesheet href="'.getcwd().'/'.$DBInfo->text_dir.'/'.$match[1].'#html" type="text/dsssl"?>';
      $flag=1;
    }
    $buff.=$line."\n";
    list($line,$body)=explode("\n",$body,2);
    if ($flag) break;
  }
  $src=$buff.$line."\n".$body;

  $tmpf=tempnam("/tmp","JADE");
  $fp= fopen($tmpf, "w");
  fwrite($fp, $src);
  fclose($fp);

  $cmd="$jade $args $tmpf";

  $fp=popen($cmd,"r");
  fwrite($fp,$src);

  while($s = fgets($fp, 1024)) $html.= $s;

  pclose($fp);
#  unlink($tmpf);

  if (!$html) {
    $src=str_replace("<","&lt;",$value);
    return "<pre class='code'>$src\n</pre>\n";
  }

  if (!$formatter->preview)
    $cache->update($pagename,$html);
  return $html;
}

// vim:et:ts=2:
?>
