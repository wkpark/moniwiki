<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org> all rights reserved.
// distributable under GPL see COPYING
//
// many codes are imported from the MoinMoin
// some codes are reused from the Phiki
//
// * MoinMoin is a python based wiki clone based on the PikiPiki
//    by Ju"rgen Hermann <jhs at web.de>
// * PikiPiki is a python based wiki clone by MartinPool
// * Phiki is a php based wiki clone based on the MoinMoin
//    by Fred C. Yankowski <fcy at acm.org>
//
// $Id$

function _preg_escape($val) {
  return preg_replace('/([\$\^\.\[\]\{\}\|\(\)\+\*\/\\\\!\?]{1})/','\\\\\1',$val);
}

function _preg_search_escape($val) {
  return preg_replace('/([\/]{1})/','\\\\\1',$val);
}

function _mkdir_p($target,$mode=0777) {
  // from php.net/mkdir user contributed notes
  if (file_exists($target)) {
    if (!is_dir($target)) return false;
    else return true;
  }
  // recursivly create dirs.
  return (_mkdir_p(dirname($target),$mode) and mkdir($target,$mode));
}

/**
 * Check double slashes in the REQUEST_URI
 * and try to get the PATH_INFO or parse the PHP_SELF.
 *
 * @author Won-Kyu Park <wkpark@gmail.com>
 * @since  2015/12/14
 * @since  1.2.5
 *
 * @return string
 */
function get_pathinfo() {
    if (!isset($_SERVER['PATH_INFO'])) {
        // the PATH_INFO not available.
        // try to get the PATH_INFO from the PHP_SELF.
        $path_parts = explode('/', $_SERVER['PHP_SELF']);

        // remove all real path parts from PHP_SELF
        $root = $_SERVER['DOCUMENT_ROOT'];
        $path = $root;
        foreach ($path_parts as $k=>$part) {
            if ($part === '')
                continue;
            $path .= '/'.$part;
            if (file_exists($path))
                unset($path_parts[$k]);
            else
                break;
        }
        // combine remaining parts
        $_SERVER['PATH_INFO'] = implode('/', $path_parts);
    }

    // if REQUEST_URI is not available.
    if (!isset($_SERVER['REQUEST_URI']))
        return $_SERVER['PATH_INFO'];

    // check double slashes in the REQUEST_URI if it available
    //
    // from MediaWikiSrc:WebRequest.php source code
    // by Apache 2.x, double slashes are converted to single slashes.
    // and PATH_INFO is mangled due to https://bugs.php.net/bug.php?id=31892
    $uri = $_SERVER['REQUEST_URI'];
    if (($p = strpos($uri, '?')) !== false) {
        // remove the query string part.
        $uri = substr($uri, 0, $p);
    }
    // rawurldecode REQUEST_URI
    $decoded_uri = rawurldecode($uri);
    if (strpos($decoded_uri, '//') === false)
        return $_SERVER['PATH_INFO'];
    return guess_pathinfo($decoded_uri);
}

/**
 * Try to get PATH_INFO from the REQUEST_URI
 *
 * @author Won-Kyu Park <wkpark@gmail.com>
 * @since  2015/12/14
 * @since  1.2.5
 *
 * @return string
 */

function guess_pathinfo($decoded_uri) {
    // try to get PATH_INFO from the REQUEST_URI
    // $uri = rawurldecode($_SERVER['REQUEST_URI']);
    // split all parts of REQUEST_URI.

    $parts = preg_split('@(/)@', $decoded_uri, -1, PREG_SPLIT_DELIM_CAPTURE);
    // /foo//bar/foo => '','/','foo','/','','/','bar','/','foo'

    // try to get the PATH_INFO path parts
    if ($_SERVER['PATH_INFO'] == '/') {
        $pos = count($parts) - 1;
    } else {
        $path = explode('/', $_SERVER['PATH_INFO']);

        // search unmatch REQUEST_URI part
        $pos = count($parts) - 1;
        for (; $pos > 0; $pos--) {
            if ($parts[$pos] == '' || $parts[$pos] == '/')
                continue;
            $part = end($path);
            if ($parts[$pos] != $part)
                break;
            array_pop($path);
        }
    }

    // skip all path components
    for (; $pos > 0; $pos--) {
        if ($parts[$pos] == '' || $parts[$pos] == '/')
            continue;
        else
            break;
    }

    // remove non path components.
    for (; $pos > 0; $pos--)
        unset($parts[$pos]);

    // merge all path components.
    return implode('', $parts);
}

function get_scriptname() {
  // Return full URL of current page.
  // $_SERVER["SCRIPT_NAME"] has bad value under CGI mode
  // set 'cgi.fix_pathinfo=1' in the php.ini under
  // apache 2.0.x + php4.2.x Win32
  // check mod_rewrite
  if (strpos($_SERVER['REQUEST_URI'],$_SERVER['SCRIPT_NAME'])===false) {
    if ($_SERVER['REQUEST_URI'][0] == '/' and ($p = strpos($_SERVER['REQUEST_URI'], '/', 1)) !== false) {
      $prefix = substr($_SERVER['REQUEST_URI'], 0, $p);
      if (($p = strpos($_SERVER['SCRIPT_NAME'], $prefix)) === 0)
        return $prefix;
    }
    return '';
  }
  return $_SERVER['SCRIPT_NAME'];
}

/**
 * get the number of lines in a file
 *
 * @author wkpark@kldp.org
 * @since  2010/09/13
 *
 */

function get_file_lines($filename) {
  $fp = fopen($filename, 'r');
  if (!is_resource($fp)) return 0;

  // test \n or \r or \r\n
  $i = 0;
  while(($test = fgets($fp, 4096)) and !preg_match("/(\r|\r\n|\n)$/", $test, $match)) $i++;

  $i = 1;
  $bsz = 1024 * 8;
  if (isset($match[1])) {
    while ($chunk = fread($fp, $bsz))
      $i += substr_count($chunk, $match[1]);
  }
  fclose($fp);
  return $i;
}

/**
 * counting add/del lines of a given diff
 *
 * @author wkpark@kldp.org
 * @since  2015/06/08
 * @param  string   $diff   - diff -u output
 * @return array            - return added/deleted lines
 */

function diffcount_simple($diff) {
    $retval = &$params['retval'];
    $lines = explode("\n", $diff);
    $lsz = sizeof($lines);
    $add = 0;
    $del = 0;
    for ($i = 0; $i < $lsz; $i++) {
        $marker = $lines[$i][0];
        if (!in_array($marker, array('-','+'))) {
            continue;
        }
        if ($marker == '-')
            $del++;
        else
            $add++;
    }

    return array($add, $del, 0, 0);
}

/**
 * counting add/del lines and chars of a given diff
 *
 * @author wkpark@kldp.org
 * @since  2015/06/08
 * @param  string   $diff   - diff -u output
 * @return array            - return added/deleted chars and lines
 */
function diffcount_lines($diff, $charset) {
    $lines = explode("\n", $diff);
    $lsz = sizeof($lines);
    if ($lines[$lsz - 1] == '') {
        // trash last empty line
        array_pop($lines);
        $lsz--;
    }

    $add = 0;
    $del = 0;
    $add_chars = 0;
    $del_chars = 0;

    $minorfix = true;

    $orig = array();
    $new = array();
    $om = false;

    for ($i = 0; $i < $lsz; $i++) {
        $line = &$lines[$i];
        if (!isset($line[0])) break;

        $mark = $line[0];
        if (!$om && $mark == '-' && isset($line[3]) && substr($line, 0, 4) == '--- ') {
            // trash first --- blah\n+++ blah\n lines
            $i++;
            continue;
        }

        $line = substr($line, 1);
        if ($mark == '@') {
            continue;
        } else if ($mark == '-') {
            $om = true;
            $orig[] = $line;
            $del++;
            continue;
        } else if ($mark == '+') {
            $om = true;
            $new[] = $line;
            $add++;
            continue;
        } else if ($om) {
            $om = false;

            $diffchars = diffcount_chars($orig, $new, $charset);

            if ($diffchars === false) {
                // simply check the difference of strlen
                $nc = mb_strlen(implode("\n", $new), $charset);
                $oc = mb_strlen(implode("\n", $orig), $charset);
                $added = $nc - $oc;

                if ($added > 0)
                    $add_chars+= $added;
                else
                    $del_chars+= -$added;
            } else {
                $add_chars+= $diffchars[0];
                $del_chars+= $diffchars[1];
            }

            // is it minorfix ?
            if (!$diffchars[2]) $minorfix = false;

            $orig = array();
            $new = array();
        }
    }

    if (!empty($orig) or !empty($new)) {
        $diffchars = diffcount_chars($orig, $new, $charset);

        if ($diffchars === false) {
            // simply check the difference of strlen
            $nc = mb_strlen(implode("\n", $new), $charset);
            $oc = mb_strlen(implode("\n", $orig), $charset);
            $added = $nc - $oc;

            if ($added > 0)
                $add_chars+= $added;
            else
                $del_chars+= -$added;
        } else {
            $add_chars+= $diffchars[0];
            $del_chars+= $diffchars[1];
        }

        // is it minorfix ?
        if (!$diffchars[2]) $minorfix = false;
    }

    return array($add, $del, $add_chars, $del_chars, $minorfix);
}

/**
 * counting add/del chars of a given array
 *
 * @author wkpark@kldp.org
 * @since  2015/06/08
 * @param  array    $orig   - original lines
 * @param  array    $new    - modified lines
 * @param  string   $charet - character set
 * @return array    added,deleted chars
 */
function diffcount_chars($orig, $new, $charset) {
    $oc = count($orig);
    $nc = count($new);
    if ($oc > 200 or $nc > 200) {
        // too big to call WordLevelDiff.
        return false;
    }
    include_once('lib/difflib.php');

    $add_chars = 0;
    $del_chars = 0;

    $minorfix = true;

    $result = new WordLevelDiff($orig, $new, $charset);
    foreach ($result->edits as $edit) {
        if (is_a($edit, '_DiffOp_Copy')) {
            continue;
        } elseif (is_a($edit, '_DiffOp_Add')) {
            $chunk = str_replace('&nbsp;', "\n", implode('', $edit->_final));
            $chunk = preg_replace('@(\n|\s)+@m', '', $chunk);
            $add = mb_strlen($chunk, $charset);
            if ($add > 3) $minorfix = false;
            $add_chars+= $add;
        } elseif (is_a($edit, '_DiffOp_Delete')) {
            $del = mb_strlen(implode('', $edit->orig), $charset);
            if ($del > 3) $minorfix = false;
            $del_chars+= $del;
        } elseif (is_a($edit, '_DiffOp_Change')) {
            $del_change = mb_strlen(implode('', $edit->orig), $charset);
            $add_change = mb_strlen(implode('', $edit->_final), $charset);
            if (abs($add_change - $del_change) > 5) $minorfix = false;
            $del_chars+= $del_change;
            $add_chars+= $add_change;
        }
    }
    return array($add_chars, $del_chars, $minorfix);
}

/**
 * Extracted from Gallery Plugin
 *
 * make pagelist to paginate.
 *
 * @author wkpark@kldp.org
 * @since  2003/08/10
 * @param  integer  $pages - the number of pages
 * @param  string   $action - link to page action
 * @param  integer  $curpage - current page
 * @param  integer  $listcount - the number of pages to show
 */

function get_pagelist($formatter,$pages,$action,$curpage=1,$listcount=10,$bra="[",$cat="]",$sep="|",$prev="&#171;",$next="&#187;",$first="",$last="",$ellip="...") {

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
      $pnut.="<b>$i</b>";
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

function _html_escape($string) {
  return preg_replace(array("@<(?=/?\s*\w+[^<>]*)@", '@"@', '@&(?!#?[a-zA-Z0-9]+;)@'), array("&lt;", '&quot;', '&amp;'), $string);
}

function _rawurlencode($url) {
  $name=rawurlencode($url);
  $urlname = str_replace(array('%2F', '%7E', '%3A'), array('/', '~', ':'), $name);
  $urlname= preg_replace('#:+#',':',$urlname);
  return $urlname;
}

/**
 * do not encode already urlencoded chars.
 *
 * @author wkpark at gmail.com
 * @since  2015/07/03
 */
function _urlencode($url) {
    $url = preg_replace('#:+#', ':', $url);

    $chunks = preg_split("@([a-zA-Z0-9/?.~#&:;=%_-]+)@", $url, -1, PREG_SPLIT_DELIM_CAPTURE);
    for ($i = 0, $sz = count($chunks); $i < $sz; $i++) {
        if ($i % 2 == 0) {
            $chunks[$i] = strtr(rawurlencode($chunks[$i]), array(
                    '%23'=>'#',
                    '%26'=>'&',
                    '%2F'=>'/',
                    '%3A'=>':',
                    '%3B'=>';',
                    '%3D'=>'=',
                    '%3F'=>'?',
                )
            );
        }
    }
    return preg_replace("/%(?![a-fA-Z0-9]{2})/", '%25', implode('', $chunks));
}

/**
 * auto detect the encoding of a given URL and fix it
 *
 * @since  2014/03/21
 */

function _autofixencode($str) {
  global $DBInfo;

  if (isset($DBInfo->url_encodings)) {
    $charset = mb_detect_encoding($str, $DBInfo->url_encodings);
    if ($charset !== false) {
      $tmp = iconv($charset, $DBInfo->charset, $str);
      if ($tmp !== false) return $tmp;
    }
  }
  return $str;
}

if (!function_exists('_stripslashes')) {
function _stripslashes($str) {
  return get_magic_quotes_gpc() ? stripslashes($str):$str;
}
}

/**
 * get random string to test regex
 * from http://stackoverflow.com/questions/4356289/php-random-string-generator
 */
function _str_random($len, $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ;:%#@",`abcdefghijklmnopqrstuvwxyz1234567890') {
  $clen = strlen($chars);
  $str = '';
  for ($i = 0; $i < $len; $i++) {
    $str.= $chars[rand(0, $clen - 1)];
  }
  return $str;
}

function qualifiedUrl($url) {
  if (substr($url,0,7)=='http://' or substr($url,0,8) == 'https://')
    return $url;
  $port= ($_SERVER['SERVER_PORT'] != 80) ? ':'.$_SERVER['SERVER_PORT']:'';
  $proto= 'http';
  if (!empty($_SERVER['HTTPS'])) $proto= 'https';
  else $proto= strtolower(strtok($_SERVER['SERVER_PROTOCOL'],'/'));
  if (empty($url[0]) or $url[0] != '/') $url='/'.$url; // XXX
  if (strpos($_SERVER['HTTP_HOST'],':') !== false)
    $port = '';
  return $proto.'://'.$_SERVER['HTTP_HOST'].$port.$url;
}

function find_needle($body,$needle,$exclude='',$count=0) {
  if (!$body) return '';

  $test=@preg_match("/$needle/","",$match);
  if ($test === false) {
    return '';
  }

  $lines=explode("\n",$body);
  $out="";
  $matches=preg_grep("/($needle)/i",$lines);
  if ($exclude)
    if (preg_grep("/($exclude)/i",$matches)) return '';

  if (count($matches) > $count) $matches=array_slice($matches,0,$count);
  foreach ($matches as $line) {
    $line=preg_replace("/($needle)/i","<strong>\\1</strong>",str_replace("<","&lt;",$line));
    $out.="<br />\n&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".$line;
  }
  return $out;
}

function normalize($title) {
  if (strpos($title," "))
    #return preg_replace("/[\?!$%\.\^;&\*()_\+\|\[\] ]/","",ucwords($title));
    return str_replace(" ","",ucwords($title));
  return $title;
}

function normalize_word($word,$group='',$pagename='',$nogroup=0,$islink=1) {
  if ($word[0]=='[') $word=substr($word,1,-1);
  if ($word[0]=='"') $word=substr($word,1,-1);
  $page=$word;
  $text='';
  $main_page='';

  # User namespace extension
  if ($page[0]=='~' and ($p=strpos($page,'/'))) {
    # change ~User/Page to User~Page
    $main_page=$page;
    $page=$text=substr($page,1,$p-1).'~'.substr($page,$p+1);
    return array($page,$text,$main_page);
  }
    
  if ($page[0]=='.' and preg_match('/^(\.{1,2})\//',$page,$match)) {
    if ($match[1] == '..') {
      if (($pos = strrpos($pagename,'/')) > 0) {
        $upper=substr($pagename,0,$pos);
        $page=substr($page,2);
        if ($page == '/') $page=$upper;
        else $page=$upper.$page;
      } else {
        $page=substr($page,3);
        if ($page == '') $page=substr($pagename,strlen($group));
        else if ($group) $page=$group.$page;
      }
    } else {
      $page=substr($page,1);
      if ($page == '/') $page='';
      $page=$pagename.$page;
    }
    return array($page,$text,$main_page);
  }

  #if ($nogroup and $page[0]=='/') { # SubPage without group support. XXX disabled
  if ($page[0]=='/') { # SubPage
    $page=$pagename.$page;
  } else if (!empty($islink) && $tok=strtok($page,'.')) {
#    print $tok;
    if ($tok=='Main') {
      # Main.MoniWiki => MoniWiki
      $page=$text=strtok('');
      return array($page,$text,$main_page);
    } else if (strpos($tok,'~') === false and strpos($tok,'/') === false) {
      # Ko~Hello.World =x=> Ko~Hello~World
      # Ko.Hello => Ko~Hello

      #$page=preg_replace('/\./','~',$page,1);
      $npage=preg_replace('/(?<!\\\\)\./','~',$page,1);
      if ($npage == $page) $page=preg_replace('/(\\\.)/','.',$page,1);
      else $page=$npage;

      $text=$main_page=strtok('');
    }
  }
  if (!$nogroup and $group and !strpos($page,'~')) {
    # UserNameSpace pages: e.g.) Ko~MoniWiki etc.
    if ($page[0]=='/') {
      # /MoniWiki => MoniWiki
      $page=$text=substr($page,1);
    } else {
      $main_page=$text=$page;
      $page=$group.$page;
    }
  }

  if (preg_match("/^wiki:/", $page)) { # wiki:
    $text=$page=substr($page,5);

    if (preg_match("/^\"([^\"]+)\"\s?(.*)$/", $page, $m)) {
      // [[wiki:"Page with space" goto Page]] case
      list($page, $text) = array($m[1], $m[2]);
    } else if (strpos($page,' ')) { # have a space ?
      list($page,$text)= explode(' ',$page,2);
    }
 
    if ($page[0]=='/') $page= $pagename.$page;
  }

  return array($page,$text,$main_page);
}

if (function_exists('str_getcsv')) {
function get_csv($str) {
  return str_getcsv($str);
}

} else {
function get_csv($str) {
  // csv_regex from Mastering regular expressions p480, 481
  $csv_regex = '{
    \G(?:^|\s*,)\s* # spaces are added
    (?:
      # Either a double quoted filed
      " # field opening quote
       ( [^"]*+ (?: "" [^"]*+ )*+ )
      " # closing quote
    | # .. or ...
      # ... some non-quote/non-comma text...
      ( [^",]*+ )
    )
  }x';

  preg_match_all($csv_regex, $str, $all_matches);

  $ret = array();
  for ($i = 0; $i < count($all_matches[0]); $i++) {
    if (strlen($all_matches[2][$i]) > 0)
      $ret[] = $all_matches[2][$i];
    else
      // a quoted value.
      $ret[] = preg_replace('/""/', '"', $all_matches[1][$i]);
  }
  return $ret;
}
}

/**
 * get aliases from alias file
 *
 * @author	wkpark@kldp.org
 * @since	2010/08/12
 *
 */
function get_aliases($file) {
  $lines = array();
  if (file_exists($file)) $lines = file($file);
  if (empty($lines))
    return array();

  $alias = array();
  foreach ($lines as $line) {
    $line=trim($line);
    if (empty($line) or $line[0]=='#') continue;
    # support three types of aliases
    #
    # dest<alias1,alias2,...
    # dest,alias1,alias2,...
    # alias>dest1,dest2,dest3,...
    #
    if (($p=strpos($line,'>')) !== false) {
      list($key, $list) = explode('>',$line,2);
      $vals = get_csv($list);
      $alias[$key] = $vals;
    } else {
      if (($p = strpos($line, '<')) !== false) {
        list($val, $keys) = explode('<', $line, 2);
        $keys = get_csv($keys);
      } else {
        $keys = get_csv($line);
        $val = array_shift($keys);
      }

      foreach ($keys as $k) {
        if (!isset($alias[$k])) $alias[$k] = array();
        $alias[$k][] = $val;
      }
    }
  }
  return $alias;
}

/**
 * Store aliases
 *
 * @author   Won-Kyu Park <wkpark@gmail.com>
 */
function store_aliases($pagename, $aliases) {
    $cache = new Cache_Text('alias');

    $cur = $cache->fetch($pagename);
    if (!is_array($cur)) $cur = array();
    if (empty($cur) and empty($aliases))
        return;

    // inverted index
    $icache = new Cache_Text('aliasname');

    if (key($cur) == $pagename)
        $cur = $cur[$pagename];

    $add = array_diff($aliases, $cur);
    $del = array_diff($cur, $aliases);

    // merge new aliases
    foreach ($add as $a) {
        if (!isset($a[0])) continue;
        $i = $icache->fetch($a);
        if (!is_array($i)) $i = array();
        $i = array_merge($i, array($pagename));
        $i = array_unique($i);
        $icache->update($a, $i);
    }

    // remove deleted aliases
    foreach ($del as $d) {
        if (!isset($d[0])) continue;
        $i = $icache->fetch($d);
        if (!is_array($i)) $i = array();
        $i = array_diff($i, array($pagename));

        if (empty($i))
            $icache->remove($d);
        else
            $icache->update($d, $i);
    }

    // update pagealiases
    if (!empty($aliases))
        $cache->update($pagename, array($pagename => $aliases));
    else
        $cache->remove($pagename);
}

/**
 * Store pagelinks
 *
 * @author   Won-Kyu Park <wkpark@gmail.com>
 */
function store_pagelinks($pagename, $pagelinks) {
  global $DBInfo;

  $bcache = new Cache_Text('backlinks');
  $cache = new Cache_Text('pagelinks');

  unset($pagelinks['TwinPages']);
  $cur = $cache->fetch($pagename);
  if (!is_array($cur)) $cur = array();

  $add = array_diff($pagelinks, $cur);
  $del = array_diff($cur, $pagelinks);

  // merge new backlinks
  foreach ($add as $a) {
    if (!isset($a[0])) continue;
    $bl = $bcache->fetch($a);
    if (!is_array($bl)) $bl = array();
    $bl = array_merge($bl, array($pagename));
    $bl = array_unique($bl);
    sort($bl);
    $bcache->update($a, $bl);
  }

  // remove deleted backlinks
  foreach ($del as $d) {
    if (!isset($d[0])) continue;
    $bl = $bcache->fetch($d);
    if (!is_array($bl)) $bl = array();
    $bl = array_diff($bl, array($pagename));
    sort($bl);
    $bcache->update($d, $bl);
  }

  if (!empty($pagelinks))
    $cache->update($pagename, $pagelinks);
  else
    $cache->remove($pagename);
}

/**
 * Get pagelinks from the wiki text
 *
 * @author   Won-Kyu Park <wkpark@gmail.com>
 */
function get_pagelinks($formatter, $text) {
    // split into chunks
    $chunk = preg_split("/({{{
                        (?:(?:[^{}]+|
                        {[^{}]+}(?!})|
                        (?<!{){{1,2}(?!{)|
                        (?<!})}{1,2}(?!}))|(?1)
                        )++}}})/x",$text,-1,PREG_SPLIT_DELIM_CAPTURE);
    $inline = array(); // save inline nowikis

    if (count($chunk) > 1) {
        // protect inline nowikis
        $nc = '';
        $k = 1;
        $idx = 1;
        foreach ($chunk as $c) {
            if ($k % 2) {
                $nc.= $c.' ';
            }
            $k++;
        }
        $text = $nc;
    }

    // check wordrule
    if (empty($formatter->wordrule)) $formatter->set_wordrule();

    preg_match_all("/(".$formatter->wordrule.")/", $text, $match);

    $words = array();
    foreach ($match[0] as $k=>$v) {
        if (preg_match('/^\!/', $v)) continue;
        if (preg_match('/^\?/', $v)) {
            $words[] = substr($v, 1);
        } else if (preg_match('/^\[?wiki:[^`\'\{\]\^\*\(]/', $v) || !preg_match('/^\[?'.$formatter->urls.':/', $v)) {
            $extended = false;
            $creole = false;
            $word = rtrim($v, '`'); // XXX
            if (preg_match('/^\[\[(.*)\]\]$/', $word, $m)) {
                // MediaWiki/WikiCreole like links
                $creole = true;
                $word = $m[1];
            } else if (preg_match('/^\[(.*)\]$/', $word, $m)) {
                $word = $m[1];
            }

            if (preg_match('/^(wiki:)?/', $word, $m)) {
                if (!empty($m[1])) $word = substr($word, 5);
                $word = ltrim($word); // ltrim wikiwords
                if (preg_match("/^\"([^\"]*)\"\s?/", $word, $m1)) {
                    $extended = true;
                    $word = $m1[1];
                } else if (!empty($m[1]) and ($p = strpos($word, " ")) !== false) {
                    $word = substr($word, 0, $p);
                }
            } else if ($creole and ($p = strpos($word, '|')) !== false) {
                $word = substr($word, 0, $p);
            }

            if (!$extended and empty($formatter->mediawiki_style) and strpos($word, " ") !== false) {
                $word = normalize($word);
            }

            if (preg_match("/^([^\(:]+)(\((.*)\))?$/", $word, $m)) {
                if (isset($m[1])) {
                    $name = $m[1];
                } else {
                    $name = $word;
                }

                // check macro
                $myname = getPlugin($name);
                if (!empty($myname)) {
                    // this is macro
                    continue;
                }
            }
            $word = strtok($word, '#?'); // trim anchor tag
            $words[] = $word;
        }
    }
    return array_values(array_unique($words));
}

/**
 * Update redirect cache and it's index
 *
 * @author   Won-Kyu Park <wkpark at gmail.com>
 * @param    timestamp $timestamp lastmodified time of the cache file
 * @return   void
 */
function update_redirects($pagename, $redirect, $refresh = false) {
    // update #redirect cache
    $rd = new Cache_Text('redirect');
    $old = $rd->fetch($pagename);
    // FIXME for legacy case
    if (is_array($old)) $old = $old[0];

    if ($old === false && !isset($redirect[0]))
        return;

    // update invert redirect index
    $rds = new Cache_Text('redirects');
    if (!$refresh || $old != $redirect) {
        // update direct cache
        $rd->update($pagename, array($redirect));
        $nr = $redirect;
        if (($p = strpos($nr, '#')) > 0) {
            // get pagename only
            //$anchor = substr($nr, $p);
            $nr = substr($nr, 0, $p);
        }
        if (!isset($nr[0])) {
            $rd->remove($pagename);
        } else if (!preg_match('@^https?://@', $nr)) { // not a URL redirect
            // add redirect links
            $redirects = $rds->fetch($nr);
            if (empty($redirects)) $redirects = array();
            $redirects = array_merge($redirects, array($pagename));
            $rds->update($nr, $redirects);
        }

        while ($old != '' and $old != false) {
            // get pagename only
            if (($p = strpos($old, '#')) > 0) {
                //$anchor = substr($old, $p);
                $old = substr($old, 0, $p);
            }
            if ($nr == $old) break; // same redirect check A#s-1 ~ A#s-2 redirects
            // delete redirect links
            $l = $rds->fetch($old);
            if ($l !== false and is_array($l)) {
                $redirects = array_diff($l, array($pagename));
                if (empty($redirects)) $rds->remove($old);
                else $rds->update($old, $redirects);
            }
            break;
        }
    }
}

/**
 * Checks and sets HTTP headers for conditional HTTP requests
 * slightly modified to set $etag separatly by wkpark@kldp.org
 *
 * @author   Simon Willison <swillison@gmail.com>
 * @link     http://simon.incutio.com/archive/2003/04/23/conditionalGet
 * @param    timestamp $timestamp lastmodified time of the cache file
 * @returns  void or exits with previously header() commands executed
 */
function http_need_cond_request($mtime, $last_modified = '', $etag = '') {
    // A PHP implementation of conditional get, see
    //   http://fishbowl.pastiche.org/archives/001132.html
    if (empty($last_modified)) // is it timestamp ?
        $last_modified = substr(gmdate('r', $mtime), 0, -5).'GMT';

    if (empty($etag)) // pseudo etag
        $etag = md5($last_modified);

    if ($etag[0] != '"')
        $etag = '"' . $etag . '"';

    // See if the client has provided the required headers
    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
        // fix broken IEx
        $if_modified_since = preg_replace('/;.*$/', '', _stripslashes($_SERVER['HTTP_IF_MODIFIED_SINCE']));
    }else{
        $if_modified_since = false;
    }

    if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
        $if_none_match = _stripslashes($_SERVER['HTTP_IF_NONE_MATCH']);
    }else{
        $if_none_match = false;
    }

    if (!$if_modified_since && !$if_none_match) {
        return true;
    }

    // At least one of the headers is there - check them
    while ($if_none_match && $if_none_match != $etag) {
        // it is weak ETag ?
        if (preg_match('@^W/(.*)@', $if_none_match, $m)) {
            if ($m[1] == $etag)
                break;
        }
        return true; // etag is there but doesn't match
    }

    if ($if_modified_since) {
        // calculate time
        $mytime = @strtotime( $if_modified_since );
        if ( $mtime > $mytime) {
            header('X-Check: '.$mtime.' '.$mytime);
            return true; // if-modified-since is there but doesn't match
        }
    }

    // Nothing has changed since their last request
    return false;
}

/**
 * find the extension of given mimetype using the mime.types
 *
 * @author   Won-Kyu Park <wkpark@gmail.com>
 */
function get_extension($mime_types = 'mime.types', $mime) {
    if (!file_exists($mime_types)) return 'bin';
    $mimetypes = file_get_contents($mime_types);
    if (preg_match('@(^'.$mime.'\s+.*$)@m', $mimetypes, $match)) {
        $tmp = preg_split('/\s+/', $match[1]);
        return $tmp[1];
    }
    return 'bin';
}

/**
 * get hased prefix for given name
 *
 * @author   Won-Kyu Park <wkpark@gmail.com>
 */
function get_hashed_prefix($key, $level = 2) {
    $hash = md5($key);
    $prefix = '';
    for ($i = 0; $i < $level; $i++) {
        $prefix.= substr($hash, 0, $i + 1) . '/';
    }

    return $prefix;
}

/**
 * abuse filter wrapper
 */
function call_abusefilter($filter, $action, $params = array()) {
    require_once(dirname(__FILE__).'/plugin/abuse/'.$filter.'.php');
    $filtername = 'abusefilter_'.$filter;

    return $filtername($action, $params);
}

/**
 * static content action
 */

function is_static_action($params) {
    if (isset($params['action']) and $params['action'] == 'raw')
        return true;
    // pre defined etag found. force static page
    if (isset($params['etag'][0]))
        return true;
    return false;
}

/**
 * Deprecated
 */
function is_mobile() {
  global $DBInfo;

  if (!empty($DBInfo->mobile_agents)) {
    $re = '/'.$DBInfo->mobile_agents.'/i';
  } else {
    $re = '/android|iphone/i';
  }
  if (preg_match($re, $_SERVER['HTTP_USER_AGENT']))
    return true;
  if (!empty($DBInfo->mobile_referer_re) and preg_match($DBInfo->mobile_referer_re, $_SERVER['HTTP_REFERER']))
    return true;
  return false;
}

/**
 * Get the real IP address for proxy
 *
 * @author   Won-Kyu Park <wkpark@gmail.com>
 */
function realIP() {
    global $Config;

    if (!empty($Config['use_cloudflare']) && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }

    $REMOTE_ADDR = $_SERVER['REMOTE_ADDR'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if (!empty($_SERVER['HTTP_X_REAL_IP']))
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    else
        return $_SERVER['REMOTE_ADDR'];

    if (strpos($ip, ',') === false && $ip == $REMOTE_ADDR)
        return $ip;

    if (!empty($Config['use_x_forwarded_for'])) {
        require_once('lib/clientip.php');
        return clientIP();
    }
    return $_SERVER['REMOTE_ADDR'];
}

/**
 * check X-Forwarded-For and get the pass-by IP addresses to log
 *
 * @author  wkpark at kldp.org
 *
 * @return  X-Forwarded-For address list + Remote Address if it needed
 */
function get_log_addr() {
    $REMOTE_ADDR = $_SERVER['REMOTE_ADDR'];

    if (!empty($Config['use_cloudflare']) && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }

    if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) and $REMOTE_ADDR != $_SERVER['HTTP_X_FORWARDED_FOR']) {
        // XFF contains the REMOTE_ADDR ?
        $xff = str_replace(' ', '', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $tmp = explode(',', $xff);

        // Real IP == REMOTE_ADDR case. (mod_remoteip etc.)
        if ($tmp[0] == $REMOTE_ADDR)
            return $REMOTE_ADDR;

        require_once('lib/clientip.php');

        $filtered = clientIP(false);
        $tmp = explode(',', $filtered);
        $last = array_pop($tmp);
        if ($last == $REMOTE_ADDR)
            $REMOTE_ADDR = $filtered;
        else
            // append REMOTE_ADDR
            $REMOTE_ADDR = $filtered.','.$REMOTE_ADDR;
    }
    return $REMOTE_ADDR;
}

/**
 * get default cols of textarea
 *
 */
function get_textarea_cols($is_mobile = false) {
  $COLS_MSIE = 80;
  $COLS_OTHER = 85;

  if (preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT'])) {
    $cols = $COLS_MSIE;
  } else if ($is_mobile) {
    $cols = 30;
  } else {
    $cols = $COLS_OTHER;
  }
  return $cols;
}

/**
 * get description of content.
 * strip wikitags etc.
 *
 * @author  wkpark at gmail.com
 *
 */

function get_description($raw) {
    $baserule = array(
            "/(?<!')'''((?U)(?:[^']|(?<!')'(?!')|'')*)?'''(?!')/",
            "/(?<!')''((?:[^']|[^']'(?!'))*)''(?!')/",
            "/`(?<!\s)(?!`)([^`']+)(?<!\s)'(?=\s|$)/",
            "/`(?<!\s)(?U)(.*)(?<!\s)`/",
            "/^(={4,})$/",
            "/,,([^,]{1,40}),,/",
            "/\^([^ \^]+)\^(?=\s|$)/",
            "/\^\^(?<!\s)(?!\^)(?U)(.+)(?<!\s)\^\^/",
            "/__(?<!\s)(?!_)(?U)(.+)(?<!\s)__/",
            "/--(?<!\s)(?!-)(?U)(.+)(?<!\s)--/",
            "/~~(?<!\s)(?!~)(?U)(.+)(?<!\s)~~/",
    );

    // check summary
    $chunks = preg_split('@^((?:={1,2})\s+.*\s+(?:={1,2}))\s*$@m', $raw, -1,
            PREG_SPLIT_OFFSET_CAPTURE | PREG_SPLIT_DELIM_CAPTURE);

    if (sizeof($chunks) > 2 && strlen($chunks[2][0]) > 20) {
        // get the first == blah blah == section
        $raw = $chunks[2][0];
    }

    $lines = explode("\n", $raw);

    // trash PIs
    for ($i = 0; $i < sizeof($lines); $i++) {
        if (isset($lines[$i][0]) and $lines[$i][0] == '#')
            continue;
        break;
    }

    $out = '';
    for (;$i < sizeof($lines); $i++) {
        // FIXME
        $line = preg_replace('@^(={1,6})\s+(.*)\s+(?1)\s*$@', '\\2', $lines[$i]);
        $line = preg_replace('@</?[^>]+>@', '', $line); // strip HTML like tags
        $line = preg_replace('@^((?:>\s*)+)@', '', $line); // strip quotes
        $line = preg_replace('@(\|{2})+@', '', $line); // strip table tags
        $line = preg_replace($baserule, '\\1', $line); // strip all base tags
        $out.= trim($line).' ';
    }
    $out = trim($out);
    if (empty($out))
        return false;

    return $out;
}

function get_title($page,$title='') {
  global $DBInfo;
  if (!empty($DBInfo->use_titlecache)) {
    $cache = new Cache_text('title');
    $title = $cache->fetch($page);
    $title = $title ? $title : $page;
  } else
    $title=$title ? $title: $page;

  #return preg_replace("/((?<=[a-z0-9]|[B-Z]{2}|A)([A-Z][a-z]|A))/"," \\1",$title);
  if (empty($DBInfo->use_camelcase)) return $title;

  if ($DBInfo->title_rule)
    return preg_replace('/'.$DBInfo->title_rule.'/'," \\1",$title);
  return preg_replace("/((?<=[a-z0-9]|[B-Z]{2})([A-Z][a-z]))/"," \\1",$title);
}

function _mask_hostname($addr,$opt=1,$mask='&loz;') {
  $tmp=explode('.',$addr);
  switch($sz=sizeof($tmp)) {
  case 4:
    if ($opt >= 1)
      $tmp[$sz-1]=str_repeat($mask,strlen($tmp[$sz-1]));
    if ($opt == 2)
      $tmp[$sz-2]=str_repeat($mask,strlen($tmp[$sz-2]));
    else if ($opt == 3)
      $tmp[$sz-3]=str_repeat($mask,strlen($tmp[$sz-3]));
    break;
  default:
    $tmp[0]=str_repeat($mask,strlen($tmp[0]));
  }
  return implode('.',$tmp);
}

function _load_php_vars($filepath, $vars=array())
{
#   foreach ($vars as $key=>$val) $$key=$val;
#   unset($key,$val,$vars);
    extract($vars);
    unset($vars);

    ob_start();
    include $filepath;
    unset($filepath);
    $vars=get_defined_vars();
    ob_end_clean();

    return $vars;
}

// from php.net
//
// It seems that the best solution would be to use HMAC-MD5.
// An implementation of HMAC-SHA1 was posted by mark on 30-Jan-2004 02:28
// as a user comment to sha1() function
// (-> http://php.net/manual/function.sha1.php#39492).
// Here's how it would look like
// (some other optimizations/modifications are included as well):
// Calculate HMAC according to RFC2104
// http://www.ietf.org/rfc/rfc2104.txt
//
function hmac($key, $data, $hash = 'md5', $blocksize = 64) {
  if (strlen($key)>$blocksize) {
   $key = pack('H*', $hash($key));
  }
  $key  = str_pad($key, $blocksize, chr(0));
  $ipad = str_repeat(chr(0x36), $blocksize);
  $opad = str_repeat(chr(0x5c), $blocksize);
  return $hash(($key^$opad) . pack('H*', $hash(($key^$ipad) . $data)));
}

/**
 * return an obfuscated email address in line from dokuwiki
 *
 * @author Harry Fuecks <hfuecks@gmail.com>
 * @author Christopher Smith <chris@jalakai.co.uk>
 */
function email_guard($email,$mode='hex') {

  switch ($mode) {
    case 'visible' :
      $obfuscate = array('@' => ' [at] ', '.' => ' [dot] ', '-' => ' [dash] ');
      return strtr($email, $obfuscate);

    case 'hex' :
      $encode = '';
      $sz=strlen($email);
      for ($i=0; $i<$sz; $i++)
        $encode .= '&#x' . bin2hex($email{$i}).';';
      return $encode;

    case 'none' :
    default :
      return $email;
  }
}

// Remember to initialize MT (using mt_srand() ) if required
function pw_encode($password) {
  $seed = substr('00' . dechex(mt_rand()), -3) .
   substr('00' . dechex(mt_rand()), -3) .
   substr('0' . dechex(mt_rand()), -2);
  return hmac($seed, $password, 'md5', 64) . $seed;
}

function getTicket($seed,$extra='',$size=0,$flag=0) {
  global $DBInfo, $Config;
  # make the site specific ticket based on the variables in the config.php
  if (empty($Config))
    $configs = getConfig("config.php");
  else
    $configs = $Config;
  $siteticket = '';
  foreach ($configs as $config) {
    if (is_array($config)) $siteticket.=md5(base64_encode(serialize($config)));
    else $siteticket.=md5($config);
  }
  if ($size>3) {
    $ticket= md5($siteticket.$seed.$extra);
    $n=0;$passwd='';
    for ($i=0,$n=0;$n<$size;$i++) {
      $j=ord($ticket[$i])-48;
      if (0<=$j and $j<=9) {
        $passwd.="$j"; $n++;
      }
    }
    return $passwd;
  }
  if ($flag)
    # change user's ticket
    return md5($siteticket.$seed.$extra.time());
  return md5($siteticket.$seed.$extra);
}

function getTokens($string, $params = null) {
    $words = array();

    // strip macros, entities
    $raw = preg_replace("/&[^;\s]+;|\[\[[^\[]+\]\]/", ' ', $string);
    // strip comments
    $raw = preg_replace("/^##.*$/m", ' ', $raw);
    // strip puncts.
    $raw = preg_replace("/([;\"',`\\\\\/\.:@#\!\?\$%\^&\*\(\)\{\}\[\]\~\-_\+=\|<>])/",
        ' ', strip_tags($raw));

    // split wiki words
    $raw = preg_replace("/((?<=[a-z0-9]|[B-Z]{2})([A-Z][a-z]))/", " \\1", $raw);
    $raw = strtolower($raw);
    $raw = preg_replace("/\b/", ' ', $raw);
    //$raw=preg_replace("/\b([0-9a-zA-Z'\"])\\1+\s*/",' ',$raw);

    // split hangul syllable bloundries
    $raw = preg_replace('/([\x{AC00}-\x{D7AF}]+)/u', " \\1 ", $raw);

    // split ASCII punctuation boundries U+00A0 ~ U+00BF
    // split General punctuation boundries U+2000 ~ U+206F
    // split CJK punctuation boundries U+3001 ~ U+303F
    $words = preg_split("/[\s\n\x{A0}-\x{BF}\x{3000}\x{3001}-\x{303F}\x{2000}-\x{206F}]+/u", trim($raw));

    $words = array_unique($words);
    asort($words);
    return $words;
}

function log_referer($referer,$page) {
  global $DBInfo;
  if (!$referer) return;

  $ignore=array("http://".$_SERVER['HTTP_HOST']);

  foreach ($ignore as $str)
    if (($p=strpos($referer,$str)) !== false) return;

  if (!file_exists($DBInfo->cache_dir."/referer")) {
    umask(000);
    mkdir($DBInfo->cache_dir."/referer",0777);
    umask(011);
    touch($DBInfo->cache_dir."/referer/referer.log");
  }

  $fp=fopen($DBInfo->cache_dir."/referer/referer.log",'a');
  $date=gmdate("Y-m-d\TH:i:s",time());
  fwrite($fp,"$date\t$page\t$referer\n");
  fclose($fp);
}

function toutf8($uni) {
  $utf[0]=0xe0 | ($uni >> 12);
  $utf[1]=0x80 | (($uni >> 6) & 0x3f);
  $utf[2]=0x80 | ($uni & 0x3f);
  return chr($utf[0]).chr($utf[1]).chr($utf[2]);
}

function load_ruleset($ruleset_file) {
  require_once 'lib/ruleset.php';

  // cache settings
  $settings = new Cache_text('settings', array('depth'=>0));

  // get cached ruleset
  if (!($ruleset = $settings->fetch('ruleset'))) {
    $deps = array();
    $rdeps = array($ruleset_file);
    $deps['deps'] = &$rdeps;

    $validator = array(
        'blacklist'=>'ip_ruleset',
        'whitelist'=>'ip_ruleset',
        'trustedproxy'=>'ip_ruleset',
        'internalproxy'=>'ip_ruleset',
    );

    $ruleset = parse_ruleset($ruleset_file, $validator, $deps);

    // somewhat bigger blacklist ?
    if (isset($ruleset['blacklist']) && count($ruleset['blacklist']) > 50) {
        require_once (dirname(__FILE__).'/lib/checkip.php');

        $ranges = make_ip_ranges($ruleset['blacklist']);
        // save blacklist separately
        $settings->update('blacklist', $ruleset['blacklist']);
        // unset blacklist array
        unset($ruleset['blacklist']);
        // set blacklist.ranges array
        $ruleset['blacklist.ranges'] = $ranges;
    }

    $settings->update('ruleset', $ruleset, 0, $deps);
  }

  return $ruleset;
}

function is_allowed_robot($rules, $name) {
  global $Config;

  if (in_array($name, $rules))
    return true;

  $rule = implode('|', array_map('preg_quote', $rules));
  if (preg_match('!'.$rule.'!i', $name))
    return true;

  return false;
}

function getSmileys() {
  global $DBInfo;
  static $smileys = null;
  if ($smileys) return $smileys;

  if (!empty($DBInfo->smiley))
  include_once($DBInfo->smiley.'.php');
  # set smileys
  if (!empty($DBInfo->shared_smileymap) and file_exists($DBInfo->shared_smileymap)) {
    $myicons=array();
    $lines=file($DBInfo->shared_smileymap);
    foreach ($lines as $l) {
      if ($l[0] != ' ') continue;
      if (!preg_match('/^ \*\s*([^ ]+)\s(.*)$/',$l,$m)) continue;
      $name=_preg_escape($m[1]);
      if (($pos = strpos($m[2], ' ')) !== false)
        list($img,$extra)=explode(' ',$m[2]);
      else
        $img = trim($m[2]);
      if (preg_match('/^(http|ftp):.*\.(png|jpg|jpeg|gif)/',$img)) {
        $myicons[$name]=array(16,16,0,$img);
      } else {
        continue;
      }
    }
    $smileys=array_merge($smileys,$myicons);
  }
  return $smileys;
}

class UserDB {
  var $users=array();
  function __construct($conf) {
    if (is_array($conf)) {
      $this->user_dir = $conf['user_dir'];
      $this->strict = $conf['login_strict'];
      if (!empty($conf['user_class']))
        $this->user_class = 'User_'.$conf['user_class'];
      else
        $this->user_class = 'WikiUser';
    } else {
      $this->user_dir=$conf->user_dir;
      $this->strict = $conf->login_strict;
      if (!empty($conf->user_class))
        $this->user_class = 'User_'.$conf->user_class;
      else
        $this->user_class = 'WikiUser';
    }
  }

  function _pgencode($m) {
    // moinmoin 1.0.x style internal encoding
    return '_'.sprintf("%02s", strtolower(dechex(ord(substr($m[1],-1)))));
  }

  function _id_to_key($id) {
    return preg_replace_callback("/([^a-z0-9]{1})/i",
      array($this, '_pgencode'), $id);
  }

  function _key_to_id($key) {
    return rawurldecode(strtr($key,'_','%'));
  }

  function getUserList($options = array()) {
    if ($this->users) return $this->users;

    $type='';
    if (!empty($options['type'])) {
      if ($options['type'] == 'del') $type = 'del-';
      elseif ($options['type'] == 'wait') $type = 'wait-';
    }

    // count users
    $handle = opendir($this->user_dir);
    $j = 0;
    while ($file = readdir($handle)) {
      if (is_dir($this->user_dir."/".$file)) continue;
      if (preg_match('/^'.$type.'wu\-([^\.]+)$/', $file,$match)) {
        $j++;
      }
    }
    closedir($handle);

    if (!empty($options['retval']) and is_array($options['retval']))
      $options['retval']['count'] = $j;

    $offset = !empty($options['offset']) ? intval($options['offset']) : 0;
    $limit = !empty($options['limit']) ? intval($options['limit']) : 1000;
    $q = !empty($options['q']) ? trim($options['q']) : '[^\.]+';

    // Anonymous user with editing information
    $rawid = false;
    if (preg_match('/^(\d{1,3}\.){3}\d{1,3}$/', $q))
      $rawid = true;

    $users = array();
    if (!empty($options['q'])) {
      // search exact matched user
      if (($mtime = $this->_exists($q, $type != '')) !== false) {
        $users[$q] = $mtime;
        return $users;
      }
    }

    $handle = opendir($this->user_dir);
    $j = 0;
    while ($file = readdir($handle)) {
      if (is_dir($this->user_dir."/".$file)) continue;
      if (preg_match('/^'.$type.'wu\-(.*)$/', $file, $match)) {
        if ($offset > 0) {
          $offset--;
          continue;
        }

        if (!$rawid)
          $id = $this->_key_to_id($match[1]);
        else
          $id = $match[1];
        if (!empty($q) and !preg_match('/'.$q.'/i', $id)) continue;
        $users[$id] = filemtime($this->user_dir.'/'.$file);
        $j++;
        if ($j >= $limit)
          break;
      }
    }
    closedir($handle);
    $this->users=$users;
    return $users; 
  }

  function getPageSubscribers($pagename) {
    $users=$this->getUserList();
    $subs=array();
    foreach ($users as $id) {
      $usr=$this->getUser($id);
      if ($usr->hasSubscribePage($pagename)) $subs[]=$usr->info['email'];
    }
    return $subs;
  }

  function addUser($user, $options = array()) {
    if ($this->_exists($user->id) || $this->_exists($user->id, true))
      return false;
    $this->saveUser($user, $options);
    return true;
  }

  function isNotUser($user) {
    if ($this->_exists($user->id) || $this->_exists($user->id, true))
      return false;
    return true;
  }

  function saveUser($user,$options=array()) {
    $config = array("regdate",
                  "email",
                  "name",
                  "nick",
                  "home",
                  "password",
                  "last_login",
                  "last_updated",
                  "login_fail",
                  "remote",
                  "login_success",
                  "ticket",
                  "eticket",
                  "idtype",
                  "npassword",
                  "nticket",
                  "edit_count",
                  "edit_add_lines",
                  "edit_del_lines",
                  "edit_add_chars",
                  "edit_del_chars",
                  "groups", // user groups
                  "strike",
                  "strike_total",
                  "strikeout",
                  "strikeout_total",
                  "join_agreement",
                  "join_agreement_version",
                  "tz_offset",
                  "avatar",
                  "theme",
                  "css_url",
                  "bookmark",
                  "scrapped_pages",
                  "subscribed_pages",
                  "quicklinks",
                  "language", // not used
                  "datetime_fmt", // not used
                  "wikiname_add_spaces", // not used
                  "status", // user status for IP user
    );

    $date=gmdate('Y/m/d H:i:s', time());
    $data="# Data saved $date\n";

    if ($user->id == 'Anonymous') {
      if (!empty($user->info['remote']))
        $wu = 'wu-'.$user->info['remote'];
      else
        $wu = 'wu-'.$_SERVER['REMOTE_ADDR'];
    } else {
      $wu = 'wu-'.$this->_id_to_key($user->id);
      if (!empty($options['suspended'])) $wu = 'wait-'.$wu;
    }

    // new user ?
    if (!file_exists("$this->user_dir/$wu") && empty($user->info['regdate'])) {
      $user->info['regdate'] = $date;
    }
    $user->info['last_updated'] = $date;

    if (!empty($user->ticket))
      $user->info['ticket']=$user->ticket;

    ksort($user->info);

    foreach ($user->info as $k=>$v) {
      if (in_array($k, $config)) {
        $data.= $k.'='.$v."\n";
      } else {
        // undefined local config
        if ($k[0] != '_')
          $k = '_'.$k;
        $data.= $k.'='.$v."\n";
      }
    }

    $fp=fopen("$this->user_dir/$wu","w+");
    if (!is_resource($fp))
      return;
    fwrite($fp,$data);
    fclose($fp);
  }

  function _exists($id, $suspended = false) {
    if (empty($id) || $id == 'Anonymous') {
      if ($suspended) return false;
      $wu = 'wu-'.$_SERVER['REMOTE_ADDR'];
    } else if (preg_match('/^(\d{1,3}\.){3}\d{1,3}$/', $id)) {
      if ($suspended) return false;
      $wu = 'wu-'.$id;
    } else {
      $prefix = $suspended ? 'wait-wu-' : 'wu-';
      $wu = $prefix . $this->_id_to_key($id);
    }
    if (file_exists($this->user_dir.'/'.$wu))
      return filemtime($this->user_dir.'/'.$wu);

    if ($suspended) {
      // deletede user ?
      $prefix = 'del-wu-';
      $wu = $prefix . $this->_id_to_key($id);
      if (file_exists($this->user_dir.'/'.$wu))
        return filemtime($this->user_dir.'/'.$wu);
    }
    return false;
  }

  function checkUser(&$user) {
    if (!empty($user->info['ticket']) and $user->info['ticket'] != $user->ticket) {
      if ($this->strict > 0)
        $user->id='Anonymous';
      return 1;
    }
    return 0;
  }

  function getInfo($id, $suspended = false) {
    if (empty($id) || $id == 'Anonymous') {
      $wu = 'wu-'.$_SERVER['REMOTE_ADDR'];
    } else if (preg_match('/^(\d{1,3}\.){3}\d{1,3}$/', $id)) {
      $wu = 'wu-'.$id;
    } else {
      $prefix = $suspended ? 'wait-wu-' : 'wu-';
      $wu = $prefix . $this->_id_to_key($id);
    }
    if (file_exists($this->user_dir.'/'.$wu)) {
       $data = file($this->user_dir.'/'.$wu);
    } else {
       return array();
    }
    $info=array();
    foreach ($data as $line) {
       #print "$line<br/>";
       if ($line[0]=="#" and $line[0]==" ") continue;
       $p=strpos($line,"=");
       if ($p === false) continue;
       $key=substr($line,0,$p);
       $val=substr($line,$p+1,-1);
       $info[$key]=$val;
    }

    return $info;
  }

  function getUser($id, $suspended = false) {
    $user = new WikiUser($id);
    $info = $this->getInfo($id, $suspended);
    $user->info = $info;

    // read group infomation
    if (!empty($info['groups'])) {
        $groups = explode(',', $info['groups']);
        // already has group information ?
        if (!empty($user->groups))
            $user->groups = array_merge($user->groups, $groups);
        else
            $user->groups = $groups;
    }

    // set default timezone
    if (isset($info['tz_offset']))
      $user->tz_offset = $info['tz_offset'];
    else
      $user->info['tz_offset'] = date('Z');

    $user->ticket = !empty($info['ticket']) ? $info['ticket'] : null;

    return $user;
  }

  function delUser($id) {
    $id = trim($id);
    if (empty($id) || $id == 'Anonymous')
      return false;

    if (preg_match('/^(\d{1,3}\.){3}\d{1,3}$/', $id)) {
      // change user status
      $info = $this->getInfo($id);
      $user = new WikiUser($id);
      $info['status'] = 'deleted';
      $info['remote'] = $id;
      $user->info = $info;
      $this->saveUser($user);
      return true;
    } else {
      $key = $this->_id_to_key($id);
      $u = 'wu-'. $key;
    }

    $du = 'del-'.$u;
    if ($this->_exists($id)) {
      return rename($this->user_dir.'/'.$u,$this->user_dir.'/'.$du);
    } else if ($this->_exists($id, true)) {
      // delete suspended user
      $u = 'wait-'. $u;
      return rename($this->user_dir.'/'.$u, $this->user_dir.'/'.$du);
    } if (file_exists($this->user_dir.'/'.$du)) {
      // already deleted
      return true;
    }
    return false;
  }

  function activateUser($id, $suspended = false) {
    $id = trim($id);
    if (empty($id) || $id == 'Anonymous')
      return false;

    if (preg_match('/^(\d{1,3}\.){3}\d{1,3}$/', $id)) {
      // activate or suspend IP user
      $info = $this->getInfo($id);
      $user = new WikiUser($id);
      if ($suspended)
        $info['status'] = 'suspended';
      else
        unset($info['status']);
      $info['remote'] = $id;
      $user->info = $info;
      $this->saveUser($user);
      return true;
    } else {
      $u = $wu = 'wu-'. $this->_id_to_key($id);
    }
    $states = array('wait', 'del');
    if ($suspended) {
      $wu = 'wait-'.$u;
      $states = array('del', '');
    }

    if (file_exists($this->user_dir.'/'.$wu)) return true;

    foreach ($states as $state) {
      if (!empty($state))
        $uu = $state.'-'.$u;
      else
        $uu = $u;
      if (file_exists($this->user_dir.'/'.$uu))
        return rename($this->user_dir.'/'.$uu, $this->user_dir.'/'.$wu);
    }

    return false;
  }
}

class WikiUser {
  var $cookie_expires = 2592000; // 60 * 60 * 24 * 30; // default 30 days

  function __construct($id="") {
     global $Config;

     if (!empty($Config['cookie_expires']))
        $this->cookie_expires = $Config['cookie_expires'];

     if ($id && $id != 'Anonymous') {
        $this->setID($id);
        return;
     }
     $id = '';
     if (isset($_COOKIE['MONI_ID'])) {
     	$this->ticket=substr($_COOKIE['MONI_ID'],0,32);
     	$id=urldecode(substr($_COOKIE['MONI_ID'],33));
     }
     $ret = $this->setID($id);
     if ($ret) $this->getGroup();

     $this->css=isset($_COOKIE['MONI_CSS']) ? $_COOKIE['MONI_CSS']:'';
     $this->theme=isset($_COOKIE['MONI_THEME']) ? $_COOKIE['MONI_THEME']:'';
     $this->bookmark=isset($_COOKIE['MONI_BOOKMARK']) ? $_COOKIE['MONI_BOOKMARK']:'';
     $this->trail=isset($_COOKIE['MONI_TRAIL']) ? _stripslashes($_COOKIE['MONI_TRAIL']):'';
     $this->tz_offset=isset($_COOKIE['MONI_TZ']) ?_stripslashes($_COOKIE['MONI_TZ']):'';
     $this->nick=isset($_COOKIE['MONI_NICK']) ?_stripslashes($_COOKIE['MONI_NICK']):'';
     $this->verified_email = isset($_COOKIE['MONI_VERIFIED_EMAIL']) ? _stripslashes($_COOKIE['MONI_VERIFIED_EMAIL']) : '';
     if ($this->tz_offset =='') $this->tz_offset=date('Z');
  }

  // get ACL group
  function getGroup() {
      global $DBInfo;

      if ($this->id == 'Anonymous') return;

      // get groups
      if (isset($DBInfo->security) && method_exists($DBInfo->security, 'get_acl_group'))
          $this->groups = $DBInfo->security->get_acl_group($this->id);
  }

  // check group Information
  function checkGroup() {
      global $DBInfo;

      if ($this->id == 'Anonymous') return;

      // a user of members
      $this->is_member = in_array($this->id, $DBInfo->members);

      // check ACL admin groups
      if (!empty($DBInfo->acl_admin_groups)) {
          foreach ($this->groups as $g) {
              if (in_array($g, $DBInfo->acl_admin_groups)) {
                  $this->is_member = true;
                  break;
              }
          }
      }
  }

  function setID($id) {
     if ($id and $this->checkID($id)) {
        $this->id=$id;
        return true;
     }
     $this->id='Anonymous';
     $this->ticket='';
     return false;
  }

  function getID($name) {
     $name = trim($name);
     if (strpos($name, ' ') !== false) {
        $dum=explode(" ",$name);
        $new=array_map("ucfirst",$dum);
        return implode('', $new);
     }
     return $name;
  }

  function setCookie() {
     global $Config;

     if ($this->id == "Anonymous") return false;

     if (($sessid = session_id()) == '') {
       // no session used. IP dependent.
       $ticket = getTicket($this->id, $_SERVER['REMOTE_ADDR']);
     } else {
       // session enabled case. use session.
       $ticket = md5($this->id.$sessid);
     }
     $this->ticket=$ticket;
     # set the fake cookie
     $_COOKIE['MONI_ID']=$ticket.'.'.urlencode($this->id);
     if (!empty($this->info['nick'])) $_COOKIE['MONI_NICK']=$this->info['nick'];

     $domain = '';
     if (!empty($Config['cookie_domain'])) {
        $domain = '; Domain='.$Config['cookie_domain'];
     } else if (strpos($_SERVER['SERVER_NAME'], '.') !== false) {
        $tmp = explode('.', $_SERVER['SERVER_NAME']);
        if (count($tmp) >= 3)
          $domain = '; Domain='.$_SERVER['SERVER_NAME'];
     }
     if (empty($domain))
        $domain = '; Domain='.$_SERVER['HTTP_HOST'];

     if (!empty($Config['cookie_path']))
        $path = '; Path='.$Config['cookie_path'];
     else
        $path = '; Path='.dirname(get_scriptname());
     return "Set-Cookie: MONI_ID=".$ticket.'.'.urlencode($this->id).
            '; expires='.gmdate('l, d-M-Y H:i:s', time() + $this->cookie_expires).' GMT '.$path.$domain;
  }

  function unsetCookie() {
     global $Config;

     # set the fake cookie
     $_COOKIE['MONI_ID']="Anonymous";

     $domain = '';
     if (!empty($Config['cookie_domain'])) {
        $domain = '; Domain='.$Config['cookie_domain'];
     } else if (strpos($_SERVER['SERVER_NAME'], '.') !== false) {
        $tmp = explode('.', $_SERVER['SERVER_NAME']);
        if (count($tmp) >= 3)
          $domain = '; Domain='.$_SERVER['SERVER_NAME'];
     }
     if (empty($domain))
        $domain = '; Domain='.$_SERVER['HTTP_HOST'];

     if (!empty($Config['cookie_path']))
        $path = '; Path='.$Config['cookie_path'];
     else
        $path = '; Path='.dirname(get_scriptname());
     return "Set-Cookie: MONI_ID=".$this->id."; expires=Tuesday, 01-Jan-1999 12:00:00 GMT; Path=".$path.$domain;
  }

  function setPasswd($passwd,$passwd2="",$rawmode=0) {
     if (!$passwd2) $passwd2=$passwd;
     $ret=$this->validPasswd($passwd,$passwd2);
     if ($ret > 0) {
        if ($rawmode)
           $this->info['password']=$passwd;
        else
           $this->info['password']=crypt($passwd);
     }
#     else
#        $this->info[password]="";
     return $ret;
  }

  function checkID($id) {
     $SPECIAL='\\,;\$\|~`#\+\*\?!"\'\?%&\(\)\[\]\{\}\=';
     preg_match("/[$SPECIAL]/",$id,$match);
     if (!$id || $match)
        return false;
     if (preg_match('/^\d/', $id)) return false;
     return true;
  }

  function checkPasswd($passwd,$chall=0) {
     if (strlen($passwd) < 3)
        return false;
     if ($chall) {
        if (hmac($chall,$this->info['password']) == $passwd)
        return true;
     } else {
        if (crypt($passwd,$this->info['password']) == $this->info['password'])
        return true;
     }
     return false;
  }

  function validPasswd($passwd,$passwd2) {

    if (strlen($passwd)<4)
       return 0;
    if ($passwd2!="" and $passwd!=$passwd2)
       return -1;

    $LOWER='abcdefghijklmnopqrstuvwxyz';
    $UPPER='ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $DIGIT='0123456789';
    $SPECIAL=',.;:-_#+*?!"\'?%&/()[]{}\=~^|$@`';

    $VALID=$LOWER.$UPPER.$DIGIT.$SPECIAL;

    $ok=0;

    for ($i=0;$i<strlen($passwd);$i++) {
       if (strpos($VALID,$passwd[$i]) === false)
          return -2;
       if (strpos($LOWER,$passwd[$i]))
          $ok|=1;
       if (strpos($UPPER,$passwd[$i]))
          $ok|=2;
       if (strpos($DIGIT,$passwd[$i]))
          $ok|=4;

       if ($ok==7 and strlen($passwd)>10) return $ok+1;
       // sufficiently safe password

       if (strpos($SPECIAL,$passwd[$i]))
          $ok|=8;
    }
    return $ok;
  }

  function hasSubscribePage($pagename) {
    if (empty($this->info['email']) or empty($this->info['subscribed_pages'])) return false;
    $page_list=_preg_search_escape($this->info['subscribed_pages']);
    if (!trim($page_list)) return false;
    $page_lists=explode("\t",$page_list);
    $page_rule='^'.join("$|^",$page_lists).'$';
    if (preg_match('/('.$page_rule.')/',$pagename))
      return true;
    return false;
  }
}

function macro_EditText($formatter,$value,$options) {
  global $DBInfo;

  # simple == 1 : do not use EditTextForm, simple == 2 : do not use GUI/Preview
  $has_form = false;

  $form = '';
  if (empty($options['simple']) or $options['simple']!=1) {
    if (!empty($DBInfo->editform) and file_exists($DBInfo->editform)) {
      $form = file_get_contents($DBInfo->editform);
    } else if ($DBInfo->hasPage('EditTextForm')) {
      $p = $DBInfo->getPage('EditTextForm');
      $form = $p->get_raw_body();
    }
  }

  $tmpls = '';
  if (isset($form[0])) {
    $form=preg_replace('/\[\[EditText\]\]/i','#editform',$form);
    ob_start();
    $opi=$formatter->pi; // save pi
    $formatter->pi = array('#linenum'=>0); // XXX override pi
    $save = $formatter->auto_linebreak;
    $formatter->auto_linebreak = 0;
    $formatter->send_page("#format wiki\n".rtrim($form),$options);
    $formatter->auto_linebreak = $save;
    $formatter->pi=$opi; // restore pi
    $form= ob_get_contents();
    ob_end_clean();
    preg_match('@(</form>)@i', $form, $m);
    if (isset($options['has_form']))
      $has_form = &$options['has_form'];
    if (isset($m[1])) $has_form = true;

    $options['tmpls'] = &$tmpls;
    $editform= macro_Edit($formatter,'nohints,nomenu',$options);
    $new=str_replace("#editform",$editform,$form); // XXX
    if ($form == $new) $form.=$editform;
    else $form=$new;
  } else {
    $form = macro_Edit($formatter,$value,$options);
  }
  $js = '';
  $css = '';
  if (empty($DBInfo->edit_with_sidebar))
    $sidebar_style="#wikiSideMenu { display: none; }\n";
  if ($has_form and !empty($DBInfo->use_jsbuttons)) {
    $css=<<<CSS
<style type='text/css'>
/*<![CDATA[*/
#mycontent input.save-button { display: none; }
#mycontent input.preview-button { display: none; }
input.save-button { display: none; }
$sidebar_style
/*]]>*/
</style>
CSS;
    $js=<<<JS
<script type='text/javascript'>
/*<![CDATA[*/
function submit_all_forms() {
  var form = document.getElementById('editform'); // main edit form
  var all = document.getElementById('all-forms');
  var all_forms = all.getElementsByTagName('form'); // all extra forms
  for (var i=0; i < all_forms.length; i++) {
    if (all_forms[i] == form) continue;
    if (all_forms[i].encoding == "multipart/form-data" || all_forms[i].enctype == "multipart/form-data") {
      form.encoding = "multipart/form-data";
      form.encoding = "multipart/form-data";
    }
    for (var j=0; j < all_forms[i].elements.length; j++) {
      if (all_forms[i].elements[j].type == 'button' || all_forms[i].elements[j].type == 'submit') continue;
      if (all_forms[i].elements[j].name == 'action' || all_forms[i].elements[j].name == '') continue;
      var newopt = all_forms[i].elements[j];
      //newopt.setAttribute('style', 'display:none');
      form.appendChild(newopt);
    }
  }
  form.elements['button_preview'].value = '';
  form.submit();
}

function check_uploadform(obj) {
  var form = document.getElementById('editform'); // main edit form
  var all = document.getElementById('all-forms');
  var all_forms = all.getElementsByTagName('form'); // all extra forms
  for (var i=0; i < all_forms.length; i++) {
    if (all_forms[i].encoding == "multipart/form-data" || all_forms[i].enctype == "multipart/form-data") {
      for (var j=0; j < all_forms[i].elements.length; j++) {
        if (all_forms[i].elements[j].type == 'file' && all_forms[i].elements[j].value != '') {
          alert("Please upload your files first!");
          return;
        }
      }
    }
  }
  if (obj.name != "") {
    var newopt = document.createElement('input');
    newopt.setAttribute('name', obj.name);
    newopt.setAttribute('value','dummy');
    newopt.setAttribute('type','hidden');
    form.appendChild(newopt);
  }
  form.submit();
}
/*]]>*/
</script>\n
JS;
  } else if (!empty($sidebar_style)) {
    $css=<<<CSS
<style type='text/css'>
/*<![CDATA[*/
$sidebar_style
/*]]>*/
</style>\n
CSS;
  }
  if (!empty($DBInfo->use_jsbuttons)) {
    $js.= <<<JS
<script data-cfasync="false" type='text/javascript'>
/*<![CDATA[*/
function init_editor() {
  var form = document.getElementById('editform'); // main edit form
  if (form.elements['button_preview']) {
    var save_onclick = form.elements['button_preview'].onclick;
    form.elements['button_preview'].onclick = function(ev) {
      try { save_onclick(ev); } catch(e) {};
      return submit_preview(ev);
    };

    if (form.elements['button_changes'])
      form.elements['button_changes'].onclick = function(ev) {
        return submit_preview(ev);
      };
  }
}

function submit_preview(e) {
  e = e || window.event;

  var form = document.getElementById('editform'); // main edit form
  var textarea = form.getElementsByTagName('textarea')[0];
  var wikitext = textarea.value;

  var action = 'markup';
  var datestamp = form.elements['datestamp'].value;
  var section = form.elements['section'] ? form.elements['section'].value : null;

  // preview
  var toSend = 'action=markup/ajax&preview=1' +
    '&value=' + encodeURIComponent(wikitext);

  var location = self.location + '';
  var markup = HTTPPost(location, toSend);

  // set preview
  var preview = document.getElementById('wikiPreview');
  preview.style.display = 'block';
  preview.innerHTML = markup;

  // get diffpreview
  var diffview = document.getElementById('wikiDiffPreview');
  var node = e.target || e.srcElement;

  if (node.name == "button_changes") {
    var toSend = 'action=diff/ajax' +
      '&value=' + encodeURIComponent(wikitext) + '&rev=' + datestamp;
    if (section)
      toSend+= '&section=' + section;

    var diff = HTTPPost(location, toSend);
    if (!diffview) {
      diffview = document.createElement('div');
      diffview.setAttribute('id', 'wikiDiffPreview');
      preview.parentNode.insertBefore(diffview, preview);
    }
    if (diffview) {
      diffview.style.display = 'block';
      diffview.innerHTML = diff;
    }
  } else {
    if (diffview) {
      diffview.style.display = 'none';
      diffview.innerHTML = '';
    }
  }

  return false;
}

(function(){
// onload
var oldOnload = window.onload;
window.onload = function() {
  try { oldOnload(); } catch(e) {};
  init_editor();
};
})();
/*]]>*/
</script>\n
JS;
  }
  return $css.$js.'<div id="all-forms">'.$form.'</div>'.$tmpls;
}

function do_edit($formatter,$options) {
  global $DBInfo;
  if (!$DBInfo->security->writable($options)) {
    $formatter->preview=1;
    $options['msg'] = _("You are not allowed to edit this page !");
  }
  $formatter->send_header("",$options);
  $sec = '';
  if (!empty($options['section']))
    $sec=' (Section)';
  $options['msgtype'] = isset($options['msgtype']) ? $options['msgtype'] : 'warn';
  $formatter->send_title(sprintf(_("Edit %s"),$options['page']).$sec,"",$options);
  //print '<div id="editor_area">'.macro_EditText($formatter,$value,$options).'</div>';
  $has_form = false;

  $options['has_form'] = &$has_form;
  $options['comment'] = ''; // do not accept comment from _GET[] ?action=edit&comment=blahblah
  $value = '';
  echo macro_EditText($formatter,$value,$options);
  echo $formatter->get_javascripts();
  if (isset($DBInfo->use_wikiwyg) and $DBInfo->use_wikiwyg>=2) {
    $js=<<<JS
<script type='text/javascript'>
/*<![CDATA[*/
sectionEdit(null,true,null);
/*]]>*/
</script>
JS;
    if (!$DBInfo->hasPage($options['page'])) print $js;
    else {
      $pi=$formatter->page->get_instructions($dum);
      if (in_array($pi['#format'],array('wiki','monimarkup')) )
	print $js;
    }
  }
  if ($has_form and !empty($DBInfo->use_jsbuttons)) {
    $msg = _("Save");
    $onclick=' onclick="submit_all_forms()"';
    $onclick1=' onclick="check_uploadform(this)"';
    echo "<div id='save-buttons'>\n";
    echo "<button type='button'$onclick tabindex='10'><span>$msg</span></button>\n";
    echo "<button type='button'$onclick1 tabindex='11' name='button_preview' value='1'><span>".
      _("Preview").'</span></button>';
    if ($formatter->page->exists())
      echo "\n<button type='button'$onclick1 tabindex='12' name='button_changes' value='1'><span>".
        _("Show changes").'</span></button>';
    if (!empty($formatter->preview))
      echo ' '.$formatter->link_to('#preview',_("Skip to preview"),' class="preview-anchor"');
    echo "</div>\n";
  }

  echo "<div id='wikiDiffPreview' style='display:none;'>\n</div>\n";
  echo "<div id='wikiPreview' style='display:none;'>\n</div>\n";

  $formatter->send_footer('',$options);
}

function ajax_edit($formatter,$options) {
  global $DBInfo;
  if (!$DBInfo->security->writable($options)) {
    $formatter->preview=0;
    return ajax_invalid($formatter,$options);
  }
  if ($options['section'])
    $sec=' (Section)';
  $options['simple']=1;
  $options['nohints']=1;
  $options['nomenu']=1;
  $options['nocategories']=1;
  $options['noresizer']=1;
  $options['rows']=12;
  $formatter->header('Content-type:text/html;charset='.$DBInfo->charset);
  print macro_EditText($formatter,$value,$options);
}

function _get_sections($body,$lim=5) {
  $tmp = preg_split("/({{{
            (?:(?:[^{}]+|
            {[^{}]+}(?!})|
            (?<!{){{1,2}(?!{)|
            (?<!})}{1,2}(?!}))|(?1)
            )++}}})/x", $body, -1, PREG_SPLIT_DELIM_CAPTURE);

  // fix for inline {{{foobar}}} in the headings.
  $chunks = array();
  $i = $j = 0;
  $c = count($tmp);
  while ($i < $c) {
    if ($i % 2) {
      if (strpos($tmp[$i],"\n") === false) {
        $chunks[$j-1].= $tmp[$i].$tmp[$i+1];
        $i+=2;
      } else {
        $chunks[$j++] = $tmp[$i++];
      }
    } else {
      $chunks[$j++] = $tmp[$i++];
    }
  }
  unset($tmp);

  $sects=array();
  $sects[]='';
  if ($lim > 1 and $lim < 5) $lim=','.$lim;
  else if ($lim == 5) $lim = ',';
  else $lim='';
  for ($jj=0,$ii=0,$ss=count($chunks); $ii<$ss; $ii++) {
    if (($ii%2)) {
      $sec=array_pop($sects);
      $sects[]=$sec.$chunks[$ii];
      continue;
    }
    $parts=array();
    $parts=preg_split("/^((?!\n)[ ]*={1$lim}\s#?.*\s+={1$lim}\s?)$/m",$chunks[$ii],
      -1, PREG_SPLIT_DELIM_CAPTURE);
    for ($j=0,$i=0,$s=count($parts); $i<$s; $i++) {
      if (!($i%2)) {
        $sec=array_pop($sects);
        $sects[]=$sec.$parts[$i];
        continue;
      }
      if (preg_match("/^\s*(={1$lim})\s#?.*\s+\\1\s?/",$parts[$i])) {
        $sects[]=$parts[$i];
      } else {
        $sec=array_pop($sects);
        $sects[]=$sec.$parts[$i];
      }
    }
  }
  return $sects;
}

function macro_Edit($formatter,$value,$options='') {
  global $DBInfo;

  $options['mode'] = !empty($options['mode']) ? $options['mode'] : '';
  $edit_rows=$DBInfo->edit_rows ? $DBInfo->edit_rows: 16;
  $cols= get_textarea_cols($options['is_mobile']);

  $use_js= preg_match('/Lynx|w3m|links/',$_SERVER['HTTP_USER_AGENT']) ? 0:1;

  $rows= (!empty($options['rows']) and $options['rows'] > 5) ? $options['rows']: $edit_rows;
  $rows= $rows < 60 ? $rows: $edit_rows;
  $cols= (!empty($options['cols']) and $options['cols'] > 60) ? $options['cols']: $cols;

  $text= !empty($options['savetext']) ? $options['savetext'] : '';
  $editlog= !empty($options['editlog']) ? $options['editlog'] : "";
  if (empty($editlog) and !empty($options['comment']))
      $editlog=_stripslashes($options['comment']);
  $editlog = _html_escape($editlog);

  $args= explode(',',$value);
  if (in_array('nohints',$args)) $options['nohints']=1;
  if (in_array('nomenu',$args)) $options['nomenu']=1;

  $preview= !empty($options['preview']) ? $options['preview'] : 0;

  if ($options['action']=='edit') $saveaction='savepage';
  else $saveaction=$options['action'];

  $extraform=!empty($formatter->_extra_form) ? $formatter->_extra_form:'';

  $options['notmpl']=isset($options['notmpl']) ? $options['notmpl']:0;
  $form = '';

  $tmpls = '';
  if (!$options['notmpl'] and (!empty($options['template']) or !$formatter->page->exists()) and !$preview) {
    $options['linkto']="?action=edit&amp;template=";
    $options['limit'] = -1;
    $tmpls= macro_TitleSearch($formatter,$DBInfo->template_regex,$options);
    if ($tmpls) {
      $tmpls = '<div>'._("Use one of the following templates as an initial release :\n").$tmpls;
      $tmpls.= sprintf(_("To create your own templates, add a page with '%s' pattern."),$DBInfo->template_regex)."\n</div>\n";
    }
    if (isset($options['tmpls'])) {
      $options['tmpls'] = $tmpls;
      $tmpls = '';
    }
  }

  $merge_btn=_("Merge");
  $merge_btn2=_("Merge manually");
  $merge_btn3=_("Ignore conflicts");
  $extra = '';
  if (!empty($options['conflict'])) {
    $extra='<span class="button"><input type="submit" class="button" name="button_merge" value="'.$merge_btn.'" /></span>';
    if ($options['conflict']==2) {
      $extra.=' <span class="button"><input type="submit" class="button" name="manual_merge" value="'.$merge_btn2.'" /></span>';
      if ($DBInfo->use_forcemerge)
        $extra.=' <span class="button"><input type="submit" class="button" name="force_merge" value="'.$merge_btn3.'" /></span>';
    }
  }

  $hidden = '';
  if (!empty($options['section']))
    $hidden='<input type="hidden" name="section" value="'.$options['section'].
            '" />';
  if (!empty($options['mode']))
    $hidden='<input type="hidden" name="mode" value="'.$options['mode'].'" />';

  # make a edit form
  if (empty($options['simple']))
    $form.= "<a id='editor'></a>\n";

  if (isset($DBInfo->use_preview_anchor))
    $preview_anchor = '#preview';
  else
    $preview_anchor = '';

  if (isset($options['page'][0]))
    $previewurl=$formatter->link_url(_rawurlencode($options['page']), $preview_anchor);
  else
    $previewurl=$formatter->link_url($formatter->page->urlname, $preview_anchor);

  $menu= ''; $sep= '';
  if (empty($DBInfo->use_resizer) and (empty($options['noresizer']) or !$use_js)) {
    $sep= ' | ';
    $menu= $formatter->link_to("?action=edit&amp;rows=".($rows-3),_("ReduceEditor"));
    $menu.= $sep.$formatter->link_to("?action=edit&amp;rows=".($rows+3),_("EnlargeEditor"));
  }

  if (empty($options['nomenu'])) {
    $menu.= $sep.$formatter->link_tag('InterWiki',"",_("InterWiki"));
    $sep= ' | ';
    $menu.= $sep.$formatter->link_tag('HelpOnEditing',"",_("HelpOnEditing"));
  }

  $form.=$menu;
  $ajax = '';
  $js = '';
  $guide = '';
  if (!empty($options['action_mode']) and $options['action_mode']=='ajax') {
    $ajax=" onsubmit='savePage(this);return false'";
  }
  $formh= sprintf('<form id="editform" method="post" action="%s"'.$ajax.'>',
    $previewurl);
  if ($text) {
    $raw_body = preg_replace("/\r\n|\r/", "\n", $text);
  } else if (!empty($options['template'])) {
    $p= new WikiPage($options['template']);
    $raw_body = preg_replace("/\r\n|\r/", "\n", $p->get_raw_body());
  } else if (isset($formatter->_raw_body)) {
    # low level XXX
    $raw_body = preg_replace("/\r\n|\r/", "\n", $formatter->_raw_body);
  } else if ($options['mode']!='edit' and $formatter->page->exists()) {
    $raw_body = preg_replace("/\r\n|\r/", "\n", $formatter->page->_get_raw_body());
    if (isset($options['section'])) {
      $sections= _get_sections($raw_body);
      if ($sections[$options['section']])
        $raw_body = $sections[$options['section']];
      #else ignore
    }
  } else {
    $raw_body = '';
    if (!empty($options['orig_pagename'])) {
      $raw_body="#title $options[orig_pagename]\n";
    }
    if (strpos($options['page'],' ') > 0) {
      #$raw_body="#title $options[page]\n";
      $options['page']='["'.$options['page'].'"]';
    }
    $guide = sprintf(_("Describe %s here"), $options['page']);
    if (empty($DBInfo->use_edit_placeholder)) {
      $raw_body.= $guide;
      $guide = '';
    } else {
      $guide = ' placeholder="'._html_escape($guide).'"';
    }
    $js=<<<EOF
<script type="text/javascript">
/*<![CDATA[*/
(function() {
    function selectGuide() {
        var txtarea = document.getElementById('editor-textarea');
        if (!txtarea) return;

        txtarea.focus();
        var txt = txtarea.value;
        var pos = 0;
        if (txt.indexOf('#title ') == 0) {
            pos = txt.indexOf("\\n") + 1;
        }
        var end = txt.length;

        if (txtarea.selectionStart || txtarea.selectionStart == '0') {
            // goto
            txtarea.selectionStart = pos;
            txtarea.selectionEnd = end;
        } else if (document.selection && !is_gecko && !is_opera) {
            // IE
            var r = document.selection.createRange();
            var range = r.duplicate();

            range.moveStart('character', pos);
            range.moveEnd('character', end - pos);
            r.setEndPoint('StartToStart', range);
            range.select();
        }
    }

    var oldOnLoad = window.onLoad;
    window.onload = function() {
        try { oldOnLoad() } catch(e) {};
        selectGuide();
    }
})();
/*]]>*/
</script>\n
EOF;
  }

  $summary_guide = '';
  if (!empty($options['.minorfix']))
    $summary_guide = _("This ia a minor edit.");
  if (empty($DBInfo->use_edit_placeholder)) {
    $summary_guide = '';
  } else {
    $summary_guide = ' placeholder="'._html_escape($summary_guide).'"';
  }

  # for conflict check
  if (!empty($options['datestamp']))
     $datestamp= $options['datestamp'];
  else if (!empty($formatter->_mtime))
     # low level control XXX
     $datestamp= $formatter->_mtime;
  else
     $datestamp= $formatter->page->mtime();

  if (!empty($DBInfo->use_savepage_hash)) {
    // generate hash
    $ticket = getTicket($datestamp.$DBInfo->user->id, $_SERVER['REMOTE_ADDR']);
    $hash = md5($ticket);
    $hidden .=
        "\n<input type=\"hidden\" name=\"hash\" value=\"".$hash."\" />\n";
  }

  $raw_body = str_replace(array("&","<"),array("&amp;","&lt;"),$raw_body);

  # get categories
  $select_category = '';
  if (!empty($DBInfo->use_category) and empty($options['nocategories'])) {
    $categories = $DBInfo->getLikePages($DBInfo->category_regex, -1);
    if ($categories) {
      $select_category="<label for='category-select'>"._("Category")."</label><select id='category-select' name='category' tabindex='4'>\n";
      $mlen = 0;
      $opts = '';
      foreach ($categories as $category) {
        $len = mb_strwidth($category);
        $category = _html_escape($category);
        if ($len > $mlen) $mlen = $len;
        $opts .= "<option value=\"$category\">$category</option>\n";
      }
      $lab = _(" Select ");
      $len = intval(($mlen - mb_strwidth($lab)) / 2);
      $pad = str_repeat('-', $len);
      $select_category.= "<option value=''>".$pad.$lab.$pad."</option>\n".$opts;
      $select_category.="</select>\n";
    }
  }

  $extra_check = '';
  if (empty($options['minor']) and !empty($DBInfo->use_minoredit)) {
    $user=&$DBInfo->user; # get from COOKIE VARS
    if (!empty($DBInfo->owners) and in_array($user->id,$DBInfo->owners)) {
      $extra_check=' '._("Minor edit")."<input type='checkbox' tabindex='3' name='minor' />";
    }
  }

  $captcha='';
  if ($use_js and !empty($DBInfo->use_ticket) and $options['id'] == 'Anonymous') {
     $msg = _("Refresh");
     $seed=md5(base64_encode(time()));
     $ticketimg=$formatter->link_url($formatter->page->urlname,'?action=ticket&amp;__seed='.$seed.'&amp;t=');
     $onclick = " onclick=\"document.getElementById('captcha_img').src ='".$ticketimg."'+ Math.random()\"";
     $captcha=<<<EXTRA
  <div class='captcha' style='float:right'><div><span class='captchaImg'><img id="captcha_img" src="$ticketimg" alt="captcha" /></span></div>
  <button type='button' class='refresh-icon'$onclick><span>$msg</span></button><input type="text" tabindex="2" size="10" name="check" />
<input type="hidden" name="__seed" value="$seed" /></div>
EXTRA;
  }

  $summary_msg=_("Summary");
  $wysiwyg_btn = '';
  $skip_preview = '';
  if (empty($options['simple'])) {
    $preview_btn='<span class="button"><input type="submit" tabindex="6" name="button_preview" class="button preview-button" value="'.
      _("Preview").'" /></span>';
    $changes_btn = '';
    if ($formatter->page->exists())
      $changes_btn=' <span class="button"><input type="submit" tabindex="6" name="button_changes" class="button preview-button" value="'.
        _("Show changes").'" /></span>';
    if ($preview and empty($options['conflict']))
      $skip_preview= ' '.$formatter->link_to('#preview',_("Skip to preview"),' class="preview-anchor"');
    if (!empty($DBInfo->use_wikiwyg)) {
      $confirm = 'false';
      if (!empty($DBInfo->wikiwyg_confirm)) $confirm = 'null';
      $wysiwyg_msg=_("GUI");
      $wysiwyg_btn.='&nbsp;<button type="button" tabindex="7"'.
        ' onclick="javascript:sectionEdit(null,'.$confirm .',null)" ><span>'.
	$wysiwyg_msg.'</span></button>';
    }
    $summary=<<<EOS
<span id='edit-summary'><label for='input-summary'>$summary_msg</label><input name="comment" id='input-summary' value="$editlog" size="60" maxlength="128" tabindex="2" $summary_guide />$extra_check</span>
EOS;
    $emailform = '';
    if (!empty($DBInfo->anonymous_friendly) and $options['id'] == 'Anonymous') {
      $useremail = isset($DBInfo->user->verified_email) ? $DBInfo->user->verified_email : '';
      if ($useremail) {
        $email_msg = _("E-Mail");
        $send_msg = sprintf(_("Send mail to %s"), "<span class='email'>".$useremail."</span>");
        #<label for='input-email'>$email_msg</label>
        #<span id='edit-email'><label for='input-email'>$email_msg</label><input name="email" id='input-email' value="$useremail" size="40" maxlength="60" tabindex="3" /></span>
        $emailform = <<<EOS
        $send_msg <input type='checkbox' tabindex='3' checked='checked' name='cc' />
EOS;
      }
    }

    // show contributor license agreement form
    $ok_agreement = true;
    if (!empty($DBInfo->use_agreement)) {
      if ($options['id'] != 'Anonymous') {
        $ok_agreement = !empty($DBInfo->user->info['join_agreement']) && $DBInfo->user->info['join_agreement'] == 'agree';
        if ($ok_agreement && !empty($DBInfo->agreement_version))
          $ok_agreement = $DBInfo->user->info['join_agreement_version'] == $DBInfo->agreement_version;
      } else {
        $ok_agreement = false;
      }
    }

    if (!$ok_agreement) {
      if ($options['id'] != 'Anonymous') {
        if (!empty($DBInfo->contributor_license_agreement))
          $agree_msg = $DBInfo->contributor_license_agreement;
        else
          $agree_msg = _("Agree to the contributor license agreement on this wiki");
      } else {
        if (!empty($DBInfo->irrevocable_contribution_agreement))
          $agree_msg = $DBInfo->irrevocable_contribution_agreement;
        else
          $agree_msg = _("Agree to the contribution agreement for Anonymous doner");
      }

      $emailform.= <<<EOS
      $agree_msg <input type='checkbox' tabindex='3' checked='checked' name='license_agree' />
EOS;
    }
    if (isset($emailform[0]))
      $emailform = '<div id="contribution_agreement">'.$emailform.'</div>';
  }
  $save_msg=_("Save");
  $resizer = '';
  if ($use_js and !empty($DBInfo->use_resizer)) {
    if ($DBInfo->use_resizer==1) {
      $resizer=<<<EOS
<script type="text/javascript" language='javascript'>
/*<![CDATA[*/
function resize(obj,val) {
  rows= obj.savetext.rows;
  rows+=val;
  if (rows > 60) rows=16;
  else if (rows < 5) rows=16;
  obj.savetext.rows=rows;
}

var resizer=document.createElement('div');
resizer.setAttribute('id','wikiResize');
resizer.innerHTML="<input type='button' class='inc' value='+' onclick='resize(this.form,3)' />\\n<input type='button' class='dec' value='-' onclick='resize(this.form,-3)' />";

var toolbar=document.getElementById('toolbar');
if (toolbar) {
  toolbar.insertBefore(resizer, toolbar.firstChild);
} else {
  var editor=document.getElementById('wikiEditor');
  editor.insertBefore(resizer, editor.firstChild);
}
/*]]>*/
</script>
EOS;
    } else {
      $formatter->register_javascripts('textarea.js');
    }
  }
  $form.=<<<EOS
<div id="editor_area">
$formh
<div class="resizable-textarea" style='position:relative'><!-- IE hack -->
<div id="save_state"></div>
<textarea id="editor-textarea" wrap="virtual" name="savetext" tabindex="1"
 rows="$rows" cols="$cols" class="wiki resizable"$guide>$raw_body</textarea>
$captcha
</div>
$extraform
<div id="editor_info">
<ul>
<li>$summary $emailform</li>
<li>$select_category
<span>
<input type="hidden" name="action" value="$saveaction" />
<input type="hidden" name="datestamp" value="$datestamp" />
$hidden
<span class="button"><input type="submit" class='save-button' tabindex="5" accesskey="x" value="$save_msg" /></span>
<!-- <input type="reset" value="Reset" />&nbsp; -->
$preview_btn$changes_btn$wysiwyg_btn$skip_preview
$extra
</span>
</li></ul>
</div>
</form>
</div>
EOS;
  if (empty($options['nohints']))
    $form.= $formatter->macro_repl('EditHints');
  if (empty($options['simple']))
    $form.= "<a id='preview'></a>";
  return $form.$resizer.$js.$tmpls;
}


function do_invalid($formatter,$options) {
  global $DBInfo;

  if ($options['action_mode'] == 'ajax') {
    return ajax_invalid($formatter,$options);
  }

  if ($options['action'] == 'notfound' && !$formatter->page->exists()) {
    $header = 'Status: 404 Not found';
    $msg = _("404 Not found");
  } else {
    $header = 'Status: 406 Not Acceptable';
    $msg = sprintf(_("You are not allowed to '%s'"), $options['action']);
    if (isset($options['allowed']) && $options['allowed'] === false)
      $msg = sprintf(_("%s action is not found."), $options['action']);
    else
      $msg = sprintf(_("You are not allowed to '%s'"), $options['action']);
  }

  $formatter->send_header($header, $options);
  $formatter->send_title($msg, '', $options);
  if ($options['action'] != 'notfound') {
    if (!empty($options['err'])) {
      $formatter->send_page($options['err']);
    } else {
      if (!empty($options['action']))
        $formatter->send_page("== ".sprintf(_("%s is not valid action"),$options['action'])." ==\n");
      else
        $formatter->send_page("== "._("Is it valid action ?")." ==\n");
    }
  }

  if ($options['help'] and
      method_exists($DBInfo->security,$options['help'])) {
    echo "<div id='wikiHelper'>";
    echo call_user_func(array($DBInfo->security, $options['help']),$formatter,$options);
      echo "</div>\n";
  }

  $formatter->send_footer("",$options);
  return false;
}

function ajax_invalid($formatter,$options) {
  if ($options['action'] == 'notfound' && !$formatter->page->exists()) {
    $header = 'Status: 404 Not found';
  } else {
    $header = 'Status: 406 Not Acceptable';
  }

  if (!empty($options['call'])) return false;
  $formatter->send_header(array("Content-Type: text/plain",
			$header),$options);
  print "false\n";
  return false;
}

function do_post_DeleteFile($formatter,$options) {
  global $DBInfo;
  if ($_SERVER['REQUEST_METHOD'] == 'POST' &&
      !$DBInfo->security->writable($options)) {
    $options['title'] = _("Page is not writable");
    return do_invalid($formatter,$options);
  }

  if ($_SERVER['REQUEST_METHOD']=="POST") {
    if (!empty($options['value'])) {
      $key=$DBInfo->pageToKeyname(urldecode(_urlencode($options['value'])));
      $dir=$DBInfo->upload_dir."/$key";
      if (!is_dir($dir) and !empty($DBInfo->use_hashed_upload_dir)) {
        $dir = $DBInfo->upload_dir.'/'.get_hashed_prefix($key).$key;
      }
    } else {
      $dir=$DBInfo->upload_dir;
    }
  } else {
    // GET with 'value=filename' query string
    if ($p=strpos($options['value'],'/')) {
      $key=substr($options['value'],0,$p-1);
      $file=substr($options['value'],$p+1);
    } else
      $file=$options['value'];
  }

  if (isset($options['files']) or isset($options['file'])) {
    if (isset($options['file'])) {
      $options['files']=array();
      $options['files'][]=$options['file'];
    }
      
    if ($options['files']) {
      foreach ($options['files'] as $file) {
        $key=$DBInfo->pageToKeyname($file);

        if (!is_dir($dir."/".$file) && !is_dir($dir."/".$key)) {
          $fdir=$options['value'] ? _html_escape($options['value']).':':'';
          if (@unlink($dir."/".$file))
            $log.=sprintf(_("File '%s' is deleted")."<br />",$fdir.$file);
          else
            $log.=sprintf(_("Fail to delete '%s'")."<br />",$fdir.$file);
        } else {
          if ($key != $file)
            $realfile = $key;
          if (@rmdir($dir."/".$realfile))
            $log.=sprintf(_("Directory '%s' is deleted")."<br />",$file);
          else
            $log.=sprintf(_("Fail to rmdir '%s'")."<br />",$file);
        }
      }
      $title = sprintf(_("Delete selected files"));
      $formatter->send_header("",$options);
      $formatter->send_title($title,"",$options);
      print $log;
      $formatter->send_footer('',$options);
      return;
    } else
      $title = sprintf(_("No files are selected !"));
  } else if ($file) {
    list($page,$file)=explode(':',$file);
    if (!$file) {
      $file=$page;
      $page=$formatter->page->name;
    }
    $page = _html_escape($page);
    $file = _html_escape($file);

    $link=$formatter->link_url($formatter->page->urlname);
    $out="<form method='post' action='$link'>";
    $out.="<input type='hidden' name='action' value='DeleteFile' />\n";
    if ($page)
      $out.="<input type='hidden' name='value' value=\"$page\" />\n";
    $out.="<input type='hidden' name='file' value=\"$file\" />\n<h2>";
    $out.=sprintf(_("Did you really want to delete '%s' ?"),$file).'</h2>';
    if ($DBInfo->security->is_protected("deletefile",$options))
      $out.=_("Password").": <input type='password' name='passwd' size='10' />\n";
    $out.="<input type='submit' value='"._("Delete")."' /></form>\n";
    $title = sprintf(_("Delete selected file"));
    $log=$out;
  } else {
    $title = sprintf(_("No files are selected !"));
  }
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  print $log;
  $formatter->send_footer('',$options);
  return;
}

function do_post_DeletePage($formatter,$options) {
  global $DBInfo;
  if ($_SERVER['REQUEST_METHOD'] == 'POST' &&
      !$DBInfo->security->writable($options)) {
    $options['title'] = _("Page is not writable");
    return do_invalid($formatter,$options);
  }

  $page = $DBInfo->getPage($options['page']);

  $not_found = !$page->exists();

  if ($not_found && !in_array($options['id'], $DBInfo->owners)) {
    $formatter->send_header('', $options);
    $title = _("Page not found.");
    $formatter->send_title($title, '',$options);
    $formatter->send_footer('', $options);
    return;
  }

  // check full permission to edit
  $full_permission = true;
  if (!empty($DBInfo->no_full_edit_permission) or
      ($options['id'] == 'Anonymous' && !empty($DBInfo->anonymous_no_full_edit_permission)))
    $full_permission = false;

  // members always have full permission to edit
  if (in_array($options['id'], $DBInfo->members))
    $full_permission = true;

  if (!$full_permission) {
    $formatter->send_header('', $options);
    $title = _("You do not have full permission to delete this page on this wiki.");
    $formatter->send_title($title, '',$options);
    $formatter->send_footer('', $options);
    return;
  }

  // get the site specific hash code
  $ticket = $page->mtime().getTicket($DBInfo->user->id, $_SERVER['REMOTE_ADDR']);
  $hash = md5($ticket);

  if (isset($options['name'][0])) $options['name']=urldecode($options['name']);
  $pagename= $formatter->page->urlname;
  if (isset($options['name'][0]) and $options['name'] == $options['page']) {
    $retval = array();
    $options['retval'] = &$retval;

    $ret = -1;
    // check hash
    if (empty($options['hash']))
      $ret = -2;
    else if ($hash == $options['hash'])
      $ret = $DBInfo->deletePage($page, $options);
    else
      $ret = -3;

    if ($ret == -1) {
      if (!empty($options['retval']['msg']))
        $title = $options['retval']['msg'];
      else
        $title = sprintf(_("Fail to delete \"%s\""), _html_escape($page->name));
    } else if ($ret == -2) {
      $title = _("Empty hash code !");
    } else if ($ret == -3) {
      $title = _("Incorrect hash code !");
    } else {
      $title = sprintf(_("\"%s\" is deleted !"), _html_escape($page->name));
    }

    $myrefresh='';
    if (!empty($DBInfo->use_save_refresh)) {
      $sec=$DBInfo->use_save_refresh - 1;
      $lnk=$formatter->link_url($formatter->page->urlname,"?action=show");
      $myrefresh='Refresh: '.$sec.'; url='.qualifiedURL($lnk);
    }
    $formatter->send_header($myrefresh,$options);

    $formatter->send_title($title,"",$options);
    $formatter->send_footer('',$options);
    return;
  } else if (isset($options['name'][0])) {
    #print $options['name'];
    $options['msg'] = _("Please delete this file manually.");
  }
  $title = sprintf(_("Delete \"%s\" ?"), $page->name);
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  $btn = _("Summary");
  echo "<form method='post'>\n";
  if ($not_found)
    echo _("Page already deleted.").'<br />';
  else
    echo "$btn: <input name='comment' size='80' value='' /><br />\n";
  if (!empty($DBInfo->delete_history) && in_array($options['id'], $DBInfo->owners))
    print _("with revision history")." <input type='checkbox' name='history' />\n";
  print "\n<input type=\"hidden\" name=\"hash\" value=\"".$hash."\" />\n";

  $pwd = _("Password");
  $btn = _("Delete Page");
  $msg = _("Only WikiMaster can delete this page");
  if ($DBInfo->security->is_protected("DeletePage",$options))
    print "$pwd: <input type='password' name='passwd' size='20' value='' />
$msg<br />\n";
  print "
    <input type='hidden' name='action' value='DeletePage' />
    <input type='hidden' name='name' value='$pagename' />
    <span class='button'><input type='submit' class='button' value='$btn' /></span>
    </form>";
#  $formatter->send_page();
  $formatter->send_footer('',$options);
}

function form_permission($mode) {
  $read = $write = '';
  if ($mode & 0400)
     $read="checked='checked'";
  if ($mode & 0200)
     $write="checked='checked'";
  $form= "<tr><th>read</th><th>write</th></tr>\n";
  $form.= "<tr><td><input type='checkbox' name='read' $read /></td>\n";
  $form.= "<td><input type='checkbox' name='write' $write /></td></tr>\n";
  return $form;
}

function do_raw($formatter,$options) {
  global $Config;
  $force_charset = '';
  if (!empty($Config['force_charset']))
    $force_charset = '; charset='.$Config['charset'];
  $supported=array('text/plain','text/css','text/javascript');

  $header = array();
  if (!empty($options['mime']) and in_array($options['mime'],$supported))
    $header[] = "Content-Type: $options[mime]";

  if ($formatter->page->exists()) {
    $mtime = $formatter->page->mtime();
    $lastmod = gmdate('D, d M Y H:i:s \G\M\T', $mtime);
    $options['deps'] = array('rev', 'section');
    $options['nodep'] = true;
    $etag = $formatter->page->etag($options);
    if (strstr($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') and function_exists('ob_gzhandler')) {
      $gzip_mode = 1;
      $etag.= '.gzip'; // is it correct?
    }
    $options['etag'] = $etag;

    // set the s-maxage for proxy
    $proxy_maxage = !empty($Config['proxy_maxage']) ? ', s-maxage='.$Config['proxy_maxage'] : '';
    $header[] = 'Content-Type: text/plain'.$force_charset;
    $header[] = 'Cache-Control: public'.$proxy_maxage.', max-age=0, must-revalidate';
    $need = http_need_cond_request($mtime, $lastmod, $etag);
    if (!$need)
      $header[] = 'HTTP/1.0 304 Not Modified';
    $formatter->send_header($header, $options);
    if (!$need) {
      @ob_end_clean();
      return;
    }
  } else {
    if (empty($options['mime']))
      $header[] = 'Content-Type: text/plain'.$force_charset;

    if (empty($options['rev'])) {
      header('HTTP/1.0 404 Not found');
      header("Status: 404 Not found");
      return;
    } else {
      $formatter->send_header($header, $options);
    }
  }

  # disabled
  #if (!empty($gzip_mode)) {
  #  ob_start('ob_gzhandler');
  #}

  $raw_body=$formatter->page->get_raw_body($options);
  if (isset($options['section'])) {
    $sections= _get_sections($raw_body);
    if ($sections[$options['section']])
      $raw_body = $sections[$options['section']];
    else
      $raw_body = "Fill Me\n";
  }
  print $raw_body;
}

function do_recall($formatter,$options) {
  $formatter->send_header("",$options);
  $formatter->send_title(sprintf(_("%s (rev. %s)"),$options['page'],
                                 $options['rev']),"",$options);
  $formatter->send_page("",$options);
  $formatter->send_footer('',$options);
}

function do_goto($formatter,$options) {
  global $DBInfo;

  // #redirect URL controlled by the $redirect_urls option
  if (!empty($DBInfo->redirect_urls) and preg_match("@^(?:https?|ftp)://@", $options['value']) and
      preg_match('@'.$DBInfo->redirect_urls.'@', $options['value'])) {
     $options['url']=$options['value'];
     unset($options['value']);
  } else if (preg_match("/^(".$DBInfo->interwikirule."):(.*)/",$options['value'],$match)) {
    $url=$DBInfo->interwiki[$match[1]];
    if ($url) {
      $page=trim($match[2]);

      if (strpos($url,'$PAGE') === false)
        $url.=$page;
      else
        $url=str_replace('$PAGE',$page,$url);
      $options['url']=$url;
      unset($options['value']);
    }
  }
  if (isset($options['value'][0])) {
     $url=_stripslashes(trim($options['value']));
     $anchor = '';
     if (($p = strpos($url, '#')) > 0) {
       $anchor = substr($url, $p);
       $url = substr($url, 0, $p);
     }
     $url=_rawurlencode($url);
     if (!empty($options['redirect']))
       $url=$formatter->link_url($url,"?action=show&amp;redirect=".
          str_replace('+', '%2B', $formatter->page->urlname).$anchor);
     else
       $url=$formatter->link_url($url,"");
     $url = preg_replace('/[[:cntrl:]]/', ' ', $url); // tr control chars

     # FastCGI/PHP does not accept multiple header infos. XXX
     #$formatter->send_header("Location: ".$url,$options);
     $url = preg_replace('/&amp;/', '&', $url);
     $formatter->header('Cache-Control: private, s-maxage=0, max-age=0');
     $formatter->header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
     $formatter->header('Cache-Control: no-store, no-cache, must-revalidate', false);
     $formatter->header('Pragma: no-cache');

     $formatter->send_header(array("Status: 302","Location: ".$url),$options);
  } else if ($options['url']) {
    $url=$options['url'];

    if ($options['ie']) $from=strtoupper($options['ie']);
    else $from=strtoupper($DBInfo->charset);
    if ($options['oe']) $to=strtoupper($options['oe']);

    if ($to and $to != $from) {
      $url=urldecode($url);

      if (function_exists("iconv")) {
        $new=iconv($from,$to,$url);
        if ($new) $url=_urlencode($new);
      } else {
        $buf=exec(escapeshellcmd("echo ".$url." | ".escapeshellcmd("iconv -f $DBInfo->charset -t $to")));
        $url=_urlencode($buf);
      }
    }
    $url=str_replace("&amp;","&",$url);
    if (!preg_match("/^(http:\/\/|ftp:\/\/)/",$options['url'])) {
       print <<<HEADER
<html>
  <head>
    <meta http-equiv="refresh" content="0;URL=$options[url]">
  </head>
  <body bgcolor="#FFFFFF" text="#000000">
  </body>
</html>
HEADER;
     } else {
       $formatter->send_header(array("Status: 302","Location: ".$url),$options);
     }
  } else {
     $title = _("Use more specific text");
     $formatter->send_header("",$options);
     $formatter->send_title($title,"",$options);
     $args['noaction']=1;
     $formatter->send_footer($args,$options);
  }
}


function do_titleindex($formatter,$options) {
  global $DBInfo, $Config;

  if (isset($options['q'])) {
    if (!$options['q']) { print ''; return; }
    #if (!$options['q']) { print "<ul></ul>"; return; }
    $limit = isset($options['limit']) ? intval($options['limit']) : 100;
    $limit = min(100, $limit);

    $val='';
    $rule='';
    while (!empty($DBInfo->use_hangul_search)) {
      include_once("lib/unicode.php");
      $val=$options['q'];
      if (strtoupper($DBInfo->charset) != 'UTF-8' and function_exists('iconv')) {
        $val=iconv($DBInfo->charset,'UTF-8',$options['q']);
      }
      if (!$val) break;
        
      $rule=utf8_hangul_getSearchRule($val, !empty($DBInfo->use_hangul_lastchar_search));

      $test=@preg_match("/^$rule/",'');
      if ($test === false) $rule=$options['q'];
      break;     
    }
    if (!$rule) $rule=trim($options['q']);

    $test = validate_needle('^'.$rule);
    if (!$test)
      $rule = preg_quote($rule);

    $indexer = $DBInfo->lazyLoad('titleindexer');
    $pages = $indexer->getLikePages($rule, $limit);

    sort($pages);
    //array_unshift($pages, $options['q']);
    $ct = "Content-Type: text/plain";
    $ct.= '; charset='.$DBInfo->charset;

    header($ct);
    $maxage = 60 * 10;
    header('Cache-Control: public, max-age='.$maxage.',s-maxage='.$maxage.', post-check=0, pre-check=0');
    if ($pages) {
    	$ret= implode("\n",$pages);
    	#$ret= "<ul>\n<li>".implode("</li>\n<li>",$pages)."</li>\n</ul>\n";
    } else {
        #$ret= "<ul>\n<li>".$options['q']."</li></ul>";
        $ret= '';
        #$ret= "<ul>\n</ul>";
    }
    if (strtoupper($DBInfo->charset) != 'UTF-8' and function_exists('iconv')) {
      $val=iconv('UTF-8',$DBInfo->charset,$ret);
      if ($val) { print $val; return; }
    }
    #print 'x'.$rule;
    print $ret;
    return;
  } else if ($options['sec'] =='') {
    if (!empty($DBInfo->no_all_titleindex))
      return;

    $tc = new Cache_text('persist', array('depth'=>0));

    // all pages
    $mtime = $DBInfo->mtime();
    $lastmod = gmdate('D, d M Y H:i:s \G\M\T', $mtime);
    $etag = md5($mtime.$DBInfo->etag_seed);
    $options['etag'] = $etag;
    $options['mtime'] = $mtime;

    // set the s-maxage for proxy
    $date = gmdate('Y-m-d-H-i-s', $mtime);
    $proxy_maxage = !empty($Config['proxy_maxage']) ? ', s-maxage='.$Config['proxy_maxage'] : '';
    $header[] = 'Content-Type: text/plain';
    $header[] = 'Cache-Control: public'.$proxy_maxage.', max-age=0, must-revalidate';
    $need = http_need_cond_request($mtime, $lastmod, $etag);
    if (!$need)
      $header[] = 'HTTP/1.0 304 Not Modified';
    else
      $header[] = 'Content-Disposition: attachment; filename="titleindex-'.$date.'.txt"';
    $formatter->send_header($header, $options);
    if (!$need) {
      @ob_end_clean();
      return;
    }

    if (($out = $tc->fetch('titleindex', 0, array('print'=>1))) === false) {
      $args = array('all'=>1);
      $pages = $DBInfo->getPageLists($args);

      sort($pages);

      $out = join("\n", $pages);
      $ttl = !empty($DBInfo->titleindex_ttl) ? $DBInfo->titleindex_ttl : 60*60*24;
      $tc->update('titleindex', $out, $ttl);
      echo $out;
    }

    return;
  }
  $formatter->send_header("",$options);
  $formatter->send_title("","",$options);
  print macro_TitleIndex($formatter,$options['sec'], $options);
  $formatter->send_footer($args,$options);
}

function do_titlesearch($formatter,$options) {
  global $DBInfo;

  $ret = array();
  if (isset($options['noexact'])) $ret['noexact'] = $options['noexact'];
  if (isset($options['noexpr'])) $ret['noexpr'] = $options['noexpr'];
  $out= macro_TitleSearch($formatter,$options['value'],$ret);

  if ($ret['hits']==1 and (empty($DBInfo->titlesearch_noredirect) or !empty($ret['exact']))) {
    $options['value']=$ret['value'];
    $options['redirect']=1;
    do_goto($formatter,$options);
    return true;
  }
  if (!$ret['hits'] and !empty($options['check'])) return false;

  if ($ret['hits'] == 0) {
    $ret2['form']=1;
    $out2= $formatter->macro_repl('FullSearch',$options['value'],$ret2);
  }

  $formatter->send_header("",$options);
  $options['msgtype']='search';
  $formatter->send_title($ret['msg'],$formatter->link_url("FindPage"),$options);

  if (!empty($options['check'])) {
    $page = $formatter->page->urlname;
    $button= $formatter->link_to("?action=edit",$formatter->icon['create']._
("Create this page"));
    print "<h2>".$button;
    print sprintf(_(" or click %s to fullsearch this page.\n"),$formatter->link_to("?action=fullsearch&amp;value=$page",_("title")))."</h2>";
  }

  print $ret['form'];
  
  if (!empty($ret['hits']))
    print $out;

  if ($ret['hits'])
    printf(_("Found %s matching %s out of %s total pages")."<br />",
	 $ret['hits'],
	($ret['hits'] == 1) ? _("page") : _("pages"),
	 $ret['all']);

  if ($ret['hits'] == 0) {
    print '<h2>'._("Please try to fulltext search")."</h2>\n";
    print $out2;
  } else {
    $value = _urlencode($options['value']);
    print '<h2>'.sprintf(_("You can also click %s to fulltext search.\n"),
      $formatter->link_to("?action=fullsearch&amp;value=$value",_("here")))."</h2>\n";
  }

  $args['noaction']=1;
  $formatter->send_footer($args,$options);
  return true;
}

function ajax_savepage($formatter,$options) {
  global $DBInfo;
  if ($_SERVER['REQUEST_METHOD']!="POST" or
    !$DBInfo->security->writable($options)) {
    return ajax_invalid($formatter,$options);
  }
  $savetext=$options['savetext'];
  $datestamp=$options['datestamp'];
  $hash = $options['hash'];

  $savetext=preg_replace("/\r\n|\r/", "\n", $savetext);
  $savetext=_stripslashes($savetext);
  $section_savetext='';
  if (isset($options['section'])) {
    if ($formatter->page->exists()) {
      $sections= _get_sections($formatter->page->get_raw_body());
      if ($sections[$options['section']]) {
        if (substr($savetext,-1)!="\n") $savetext.="\n";
        $sections[$options['section']]=$savetext;
      }
      $section_savetext=$savetext;
      $savetext=implode('',$sections);
    }
  }

  if ($savetext and $savetext[strlen($savetext)-1] != "\n")
    $savetext.="\n";

  $new=md5($savetext);

  if ($formatter->page->exists()) {
    # check difference
    $body=$formatter->page->get_raw_body();
    $body=preg_replace("/\r\n|\r/", "\n", $body);
    $orig=md5($body);
    # check datestamp
    if ($formatter->page->mtime() > $datestamp) {
      $options['msg']=sprintf(_("Someone else saved the page while you edited %s"),$formatter->link_tag($formatter->page->urlname,"",_html_escape($options['page'])));
      print "false\n";
      print $options['msg'];
      return;
    } else if ($datestamp > time()) {
      print _("Invalid access");
      print "false\n";
      return;
    }

    // check hash
    if (!empty($DBInfo->use_savepage_hash)) {
      $ticket = getTicket($datestamp.$DBInfo->user->id, $_SERVER['REMOTE_ADDR']);
      if ($hash != md5($ticket)) {
        print _("Invalid access");
        print "false\n";
        return;
      }
    }
  } else {
    $options['msg']=_("Section edit is not valid for non-exists page.");
    print "false\n";
    print $options['msg'];
    return;
  }
  if ($orig == $new) {
    $options['msg']=sprintf(_("Go back or return to %s"),$formatter->link_tag($formatter->page->urlname,"",_html_escape($options['page'])));
    print "false\n";
    print $options['msg'];
    return;
  }

  if ($DBInfo->spam_filter) {
    $text=$savetext;
    $fts=preg_split('/(\||,)/',$DBInfo->spam_filter);
    foreach ($fts as $ft) {
      $text=$formatter->filter_repl($ft,$text,$options);
    }
    if ($text != $savetext) {
      $options['msg'] = _("Sorry, can not save page because some messages are blocked in this wiki.");
      print "false\n";
      print $options['msg'];
      return;
    }
  }

  $comment=_stripslashes($options['comment']);
  $formatter->page->write($savetext);
  $retval = array();
  $options['retval'] = &$retval;
  $ret=$DBInfo->savePage($formatter->page,$comment,$options);

  if (($ret != -1) and $DBInfo->notify and ($options['minor'] != 1)) {
    $options['noaction']=1;
    if (!function_exists('mail')) {
      $options['msg']=sprintf(_("mail does not supported by default."))."<br />";
    } else {
      $ret2=wiki_notify($formatter,$options);
      if ($ret2)
        $options['msg']=sprintf(_("Sent notification mail."))."<br />";
      else
        $options['msg']=sprintf(_("No subscribers found."))."<br />";
    }
  }
      
  if ($ret == -1)
    $options['msg'].=sprintf(_("%s is not editable"),$formatter->link_tag($formatter->page->urlname,"",_html_escape($options['page'])));
  else
    $options['msg'].=sprintf(_("%s is saved"),$formatter->link_tag($formatter->page->urlname,"?action=show",_html_escape($options['page'])));

  print "true\n";
  print $options['msg'];
  return;
}

function do_post_savepage($formatter,$options) {
  global $DBInfo;
  if ($_SERVER['REQUEST_METHOD'] != 'POST' ||
      !$DBInfo->security->writable($options)) {
    $options['title'] = _("Page is not writable");
    $options['button_preview'] = 1; // force preview
  }

  if ((isset($_FILES['upfile']) and is_array($_FILES)) or
      (isset($options['MYFILES']) and is_array($options['MYFILES']))) {
    $retstr = false;
    $options['retval'] = &$retstr;
    include_once('plugin/UploadFile.php');
    do_uploadfile($formatter, $options);
  }

  $savetext=$options['savetext'];
  $datestamp = intval($options['datestamp']);
  $hash = $options['hash'];
  $button_preview = !empty($options['button_preview']) ? 1 : 0;

  if ($button_preview)
    $formatter->preview = 1;
  $button_merge=!empty($options['button_merge']) ? 1:0;
  $button_merge=!empty($options['manual_merge']) ? 2:$button_merge;
  $button_merge=!empty($options['force_merge']) ? 3:$button_merge;
  $button_diff = !empty($options['button_changes']) ? 1 : 0;
  if ($button_diff) $button_preview = 1;

  $savetext=preg_replace("/\r\n|\r/", "\n", $savetext);
  $savetext=_stripslashes($savetext);
  $comment=_stripslashes($options['comment']);
  $comment = trim($comment);
  $section_savetext='';
  if (isset($options['section'])) {
    if ($formatter->page->exists()) {
      if (!empty($datestamp)) {
        // get revision number by the datestamp
        $rev = $formatter->page->get_rev($datestamp);
        $opts = array();
        if (!empty($rev))
            $opts['rev'] = $rev;
        // get raw text by selected revision
        $rawbody = $formatter->page->get_raw_body($opts);
      } else {
        $rawbody = $formatter->page->get_raw_body();
      }
      $sections= _get_sections($rawbody);
      if ($sections[$options['section']]) {
        if (substr($savetext,-1)!="\n") $savetext.="\n";
        $sections[$options['section']]=$savetext;
      }
      $section_savetext=$savetext;
      $savetext=implode('',$sections);
    }
  }

  if ($savetext and $savetext[strlen($savetext)-1] != "\n")
    $savetext.="\n";

  $new=md5($savetext);

  $menu = $formatter->link_to("#editor",_("Goto Editor"), ' class="preview-anchor"');

  $diff = '';
  if ($formatter->page->exists()) {
    # check difference
    $body=$formatter->page->get_raw_body();
    $body=preg_replace("/\r\n|\r/", "\n", $body);
    $orig=md5($body);

    if ($orig == $new) {
      // same text. just update datestamp
      unset($options['datestamp']);
      $datestamp= $formatter->page->mtime();
    }
    # check datestamp
    if ($formatter->page->mtime() > $datestamp) {
      $options['msg']=sprintf(_("Someone else saved the page while you edited %s"),$formatter->link_tag($formatter->page->urlname,"",_html_escape($options['page'])));
      $options['preview']=1; 
      $options['conflict']=1; 
      if ($button_merge) {
        $options['msg']=sprintf(_("%s is merged with latest contents."),$formatter->link_tag($formatter->page->urlname,"",_html_escape($options['page'])));
        $options['title']=sprintf(_("%s is merged successfully"),_html_escape($options['page']));
        $merge=$formatter->get_merge($savetext);
        if (preg_grep('/^<<<<<<<$/',explode("\n",$merge))) {
          $options['conflict']=2; 
          $options['title']=sprintf(_("Merge conflicts are detected for %s !"),_html_escape($options['page']));
          $options['msg']=sprintf(_("Merge cancelled on %s."),$formatter->link_tag($formatter->page->urlname,"",_html_escape($options['page'])));
          $merge = preg_replace('/^>>>>>>>$/m', "=== /!\ >>>>>>> "._("NEW").' ===', $merge);
          $merge = preg_replace('/^<<<<<<<$/m', "=== /!\ <<<<<<< "._("OLD").' ===', $merge);
          $merge = preg_replace('/^=======$/m', "=== ======= ===", $merge);
      	  if ($button_merge>1) {
            unset($options['datestamp']);
            unset($options['section']);
            unset($section_savetext);
            $datestamp= $formatter->page->mtime();
            $options['conflict']=0;
            if ($button_merge==2) {
              $options['title']=sprintf(_("Get merge conflicts for %s"),_html_escape($options['page']));
              $options['msg']=sprintf(_("Please resolve conflicts manually."));
              if ($merge) $savetext=$merge;
            } else {
              $options['title']=sprintf(_("Force merging for %s !"),_html_escape($options['page']));
              $options['msg']=sprintf(_("Please be careful, you could damage useful information."));
            }
          }
	} else {
          $options['conflict']=0; 
          if ($merge) {
            // successfully merged. reset datestamp
            $savetext=$merge;
            unset($options['datestamp']); 
            $datestamp= $formatter->page->mtime();
          }
        }
        $button_preview = 1;
      } else {
        $options['title'] = _("Conflict error!");
        $button_preview = 1;
      }

      if ($options['conflict'] and !empty($merge))
        $diff = $formatter->get_diff($merge); // get diff
      else
        $diff = $formatter->get_diff($savetext); // get diff
    } else if ($datestamp > time()) {
      $options['msg'] = sprintf(_("Go back or return to %s"),
            $formatter->link_tag($formatter->page->urlname, "", _html_escape($options['page'])));
      $formatter->send_header("", $options);
      $formatter->send_title(_("Invalid access"),"",$options);
      $formatter->send_footer();
      return;
    } else if (!empty($DBInfo->use_savepage_hash)) {
      // check hash
      $ticket = getTicket($datestamp.$DBInfo->user->id, $_SERVER['REMOTE_ADDR']);
      if ($hash != md5($ticket)) {
        $formatter->send_header("", $options);
        $formatter->send_title(_("Invalid access"),"",$options);
        $formatter->send_footer();
        return;
      }
    }
  }

  if (empty($button_preview) && !empty($orig) && $orig == $new) {
    $options['msg']=sprintf(_("Go back or return to %s"),$formatter->link_tag($formatter->page->urlname,"",_html_escape($options['page'])));
    $formatter->send_header("",$options);
    $formatter->send_title(_("No difference found"),"",$options);
    $formatter->send_footer();
    return;
  }
  if ($comment && (function_exists('mb_strlen') and mb_strlen($comment, $DBInfo->charset) > 256) or (strlen($comment) > 256) ) {
    //$options['msg']=sprintf(_("Go back or return to %s"),$formatter->link_tag($formatter->page->urlname,"",_html_escape($options['page'])));
    $options['title']= _("Too long Comment");
    $button_preview = 1;
  }

  // XXX captcha
  $use_any=0;
  if (!empty($DBInfo->use_textbrowsers)) {
    if (is_string($DBInfo->use_textbrowsers))
      $use_any= preg_match('/'.$DBInfo->use_textbrowsers.'/',
        $_SERVER['HTTP_USER_AGENT']) ? 1:0;
    else
      $use_any= preg_match('/Lynx|w3m|links/',
        $_SERVER['HTTP_USER_AGENT']) ? 1:0;
  }

  $ok_ticket=0;
  if (!$button_preview and !$use_any and !empty($DBInfo->use_ticket) and $options['id'] == 'Anonymous') {
    if ($options['__seed'] and $options['check']) {
      $mycheck=getTicket($options['__seed'],$_SERVER['REMOTE_ADDR'],4);
      if ($mycheck==$options['check'])
        $ok_ticket=1;
      else {
        $options['msg']= _("Invalid ticket !");
        $button_preview=1;
      }
    } else {
      if (!$button_preview)
        $options['msg']= _("You need a ticket !");
      $button_preview=1;
    }
  } else {
    $ok_ticket=1;
  }
  // XXX

  if (!$button_preview and $DBInfo->spam_filter) {
    $text=$savetext;
    $fts=preg_split('/(\||,)/',$DBInfo->spam_filter);
    foreach ($fts as $ft) {
      $text=$formatter->filter_repl($ft,$text,$options);
    }
    if ($text != $savetext) {
      $button_preview=1;
      $options['msg'] = _("Sorry, can not save page because some messages are blocked in this wiki.");
    } else if ($options['id'] == 'Anonymous' and
        !empty($comment) and !empty($DBInfo->spam_comment_filter)) {
      // comment filter for anonymous users
      $cmt = $comment;
      $fts = preg_split('/(\||,)/',$DBInfo->spam_comment_filter);
      // bad comments file
      $options['.badcontents'] = !empty($DBInfo->comments_badcontents) ?
        $DBInfo->comments_badcontents : null;
      foreach ($fts as $ft) {
        $cmt = $formatter->filter_repl($ft, $cmt, $options);
      }
      if ($cmt != $comment) {
        $button_preview = 1;
        $options['msg'] = _("Sorry, can not save page because some messages are blocked in this wiki.");
      }
    }
  }
  $formatter->page->set_raw_body($savetext);

  // check license agreement
  $ok_agreement = true;
  if (!empty($DBInfo->use_agreement)) {
    if ($options['id'] != 'Anonymous') {
      $ok_agreement = !empty($DBInfo->user->info['join_agreement']) && $DBInfo->user->info['join_agreement'] == 'agree';
      if ($ok_agreement && !empty($DBInfo->agreement_version))
        $ok_agreement = $DBInfo->user->info['join_agreement_version'] == $DBInfo->agreement_version;
    } else {
      $ok_agreement = false;
    }
  }

  if (empty($button_preview) && !$ok_agreement && empty($options['license_agree'])) {
    $button_preview = 1;
    if ($options['id'] == 'Anonymous')
      $options['msg'] = _("Anonymous user have to agree the contribution agreement for this wiki.");
    else
      $options['msg'] = _("Sorry, you have to agree the contribution agreement or the join agreement of this wiki.");
  }

  // check full permission to edit
  $full_permission = true;
  if (!empty($DBInfo->no_full_edit_permission) or
      ($options['id'] == 'Anonymous' && !empty($DBInfo->anonymous_no_full_edit_permission)))
    $full_permission = false;

  // members always have full permission to edit
  if (in_array($options['id'], $DBInfo->members))
    $full_permission = true;

  $minorfix = false;
  $options['editinfo'] = array();
  if (!$full_permission || !empty($DBInfo->use_abusefilter)) {
    // get diff
    if (!isset($diff[0]))
      $diff = $formatter->get_diff($savetext);

    // get total line numbers
    // test \n or \r or \r\n
    $crlf = "\n";
    if (preg_match("/(\r|\r\n|\n)$/", $savetext, $match))
      $crlf = $match[1];
    // count crlf
    $nline = substr_count($savetext, $crlf);

    // count diff lines, chars
    $changes = diffcount_lines($diff, $DBInfo->charset);
    // set return values
    $added = $changes[0];
    $deleted = $changes[1];
    $added_chars = $changes[2];
    $deleted_chars = $changes[3];

    // check minorfix
    $minorfix = $changes[4];

    $editinfo = array(
      'add_lines'=>$added,
      'del_lines'=>$deleted,
      'add_chars'=>$added_chars,
      'del_chars'=>$deleted_chars,
    );

    $options['editinfo'] = $editinfo;

    if (!$button_diff)
      $diff = '';
  }

  if (!$full_permission) {
    $restricted = false;
    $delete_lines_restricted_ratio = !empty($DBInfo->allowed_max_lines_delete_ratio) ?
        $DBInfo->allowed_max_lines_delete_ratio : 0.5;

    if ($deleted > 0 && ($deleted / $nline) > $delete_lines_restricted_ratio) {
      $restricted = true;
    }

    // check the maximum number of characters allowed to add/delete
    $max_chars_add = !empty($DBInfo->allowed_max_chars_add) ?
        $DBInfo->allowed_max_chars_add : 300;
    $max_chars_del = !empty($DBInfo->allowed_max_chars_delete) ?
        $DBInfo->allowed_max_chars_delete : 180;

    if (!$restricted && ($added_chars > $max_chars_add ||
        $deleted_chars > $max_chars_del))
      $restricted = true;

    if ($restricted) {
      $options['title'] = _("You do not have full permission to edit this page on this wiki.");
      if ($options['id'] == 'Anonymous')
        $options['msg'] = _("Anonymous user is restricted to delete a lot amount of page on this wiki.");
      else
        $options['msg'] = _("You are restricted to delete a lot amount of page on this wiki.");
      $button_preview = true;
    }
  }

  if ($button_preview) {
    if (empty($options['title']))
      $options['title']=sprintf(_("Preview of %s"),_html_escape($options['page']));

    // http://stackoverflow.com/questions/1547884
    $header = '';
    if (!empty($DBInfo->preview_no_xss_protection))
      $header = 'X-XSS-Protection: 0';
    $formatter->send_header($header, $options);
    $formatter->send_title("","",$options);
     
    $options['preview']=1; 
    $options['datestamp']=$datestamp; 
    $savetext=$section_savetext ? $section_savetext:$savetext;
    $options['savetext']=$savetext;

    $formatter->preview=1;
    $has_form = false;
    $options['has_form'] = &$has_form;
    $options['.minorfix'] = $minorfix;
    print '<div id="editor_area_wrap">'.macro_EditText($formatter,'',$options);
    echo $formatter->get_javascripts();
    if ($has_form and !empty($DBInfo->use_jsbuttons)) {
      $msg = _("Save");
      $onclick=' onclick="submit_all_forms()"';
      $onclick1=' onclick="check_uploadform(this)"';
      echo "<div id='save-buttons'>\n";
      echo "<button type='button'$onclick tabindex='10'><span>$msg</span></button>\n";
      echo "<button type='button'$onclick1 tabindex='11' name='button_preview' value='1'><span>".
      	_("Preview").'</span></button>';
      if ($formatter->page->exists())
        echo "\n<button type='button'$onclick1 tabindex='12' name='button_changes' value='1'><span>".
          _("Show changes").'</span></button>';
      if ($button_preview)
        echo ' '.$formatter->link_to('#preview',_("Skip to preview"),' class="preview-anchor"');
      echo "</div>\n";
    }
    print '</div>'; # XXX
    print $DBInfo->hr;
    print $menu;
    if ($button_diff and !isset($diff[0])) {
        $diff = $formatter->get_diff($options['section'] ? implode('', $sections) : $savetext); // get diff
        // strip diff header
        if (($p = strpos($diff, '@@')) !== false) $diff = substr($diff, $p);
    }
    if (isset($diff[0])) {
        echo "<div id='wikiDiffPreview'>\n";
        echo $formatter->processor_repl('diff', $diff, $options);
        //echo $formatter->macro_repl('Diff','',array('text'=>$diff,'type'=>'fancy'));
        echo "</div>\n";
    }
    print "<div id='wikiPreview'>\n";
    #$formatter->preview=1;
    $formatter->send_page($savetext);
    $formatter->preview=0;
    print $DBInfo->hr;
    print "</div>\n";
    print $menu;
  } else {
    // check minorfix
    $options['.minorfix'] = $minorfix;
    if (empty($DBInfo->use_autodetect_minoredit))
      unset($options['.minorfix']);

    if (!empty($options['category']))
      $savetext.="----\n[[".$options['category']."]]\n";

    $options['minor'] = !empty($DBInfo->use_minoredit) ? $options['minor']:0;
    if ($options['minor']) {
      $user=$DBInfo->user; # get from COOKIE VARS
      if ($DBInfo->owners and in_array($user->id,$DBInfo->owners)) {
        $options['minor']=1;
      } else {
        $options['minor']=0;
      }
    }

    $formatter->page->write($savetext);
    $retval = array();
    $options['retval'] = &$retval;
    $ret=$DBInfo->savePage($formatter->page,$comment,$options);
    if (($ret != -1) and $DBInfo->notify and ($options['minor'] != 1)) {
      $options['noaction']=1;
      if (!function_exists('mail')) {
        $options['msg']=sprintf(_("mail does not supported by default."))."<br />";
      } else {
        $ret2=wiki_notify($formatter,$options);
        if ($ret2)
          $options['msg']=sprintf(_("Sent notification mail."))."<br />";
        else
          $options['msg']=sprintf(_("No subscribers found."))."<br />";
      }
    }
      
    if ($ret == -1) {
      if (!empty($options['retval']['msg']))
        $msg = $options['retval']['msg'];
      else
        $msg = sprintf(_("%s is not editable"),$formatter->link_tag($formatter->page->urlname,"",_html_escape($options['page'])));
      $options['title'] = $msg;
    } else {
      $options['title'] = sprintf(_("%s is saved"),$formatter->link_tag($formatter->page->urlname,"?action=show",_html_escape($options['page'])));
    }

    $myrefresh='';
    if (!empty($DBInfo->use_save_refresh)) {
      $lnk=$formatter->link_url($formatter->page->urlname,"?action=show");
      if (!empty($options['section']))
        $lnk .= '#sect-'.$options['section'];

      if ($DBInfo->use_save_refresh > 0 || $ret == -1) {
        $sec=$DBInfo->use_save_refresh - 1;
        if ($sec < 0) $sec = 3;
        $myrefresh='Refresh: '.$sec.'; url='.qualifiedURL($lnk);
      } else {
        $myrefresh = array('Status: 302', 'Location: '. qualifiedURL($lnk));
      }
    }
    $formatter->send_header($myrefresh,$options);
    if (is_array($myrefresh))
      return;

    $formatter->send_title("","",$options);
    $opt['pagelinks']=1;
    $opt['refresh'] = 1;
    $formatter->page->pi = null; // call get_instruction() again
    # re-generates pagelinks
    print "<div id='wikiContent'>\n";
    $formatter->send_page("",$opt);
    print "</div>\n";
  }
  $args['editable']=0;
  $formatter->send_footer($args,$options);
}

function wiki_notify($formatter,$options) {
  global $DBInfo;

  $from= $options['id'];

  $udb=&$DBInfo->udb;
  $subs=$udb->getPageSubscribers($options['page']);

  $reminder = '';
  if ($from == 'Anonymous') {
    if (!empty($options['cc']) and !empty($DBInfo->user->verified_email)) {
      $mail = $DBInfo->user->verified_email;
      $ticket = base64_encode(getTicket($_SERVER['REMOTE_ADDR'], $mail, 10));
      $enc = base64_encode($mail);
      $reminder = _("You have contribute this wiki as an Anonymous donor.")."\n";
      $reminder.= _("Your IP address and e-mail address are used to verify you.")."\n";
      $reminder.= qualifiedUrl($formatter->link_url($formatter->page->urlname, "?action=userform&login=$enc&verify_email=$ticket"));
      $reminder.= "\n\n";
      $subs[] = $DBInfo->user->verified_email;
    }
  }

  if (!$subs) {
    if ($options['noaction']) return 0;

    $title=_("Nobody subscribed to this page.");
    $formatter->send_header("",$options);
    $formatter->send_title($title,"",$options);
    print "Fail !";
    $formatter->send_footer("",$options);
    return;
  }

  $diff="";
  if ($DBInfo->version_class) {
    $rev=$formatter->page->get_rev();
    $version = $DBInfo->lazyLoad('version', $DBInfo);
    $diff = $version->diff($formatter->page->name,$rev);
  } else {
    $options['nodiff'];
  }

  $mailto=join(", ",$subs);
  $subject="[".$DBInfo->sitename."] ".sprintf(_("%s page is modified"),
    $options['page']);

  $subject= '=?'.$DBInfo->charset.'?B?'.rtrim(base64_encode($subject)).'?=';

  if ($DBInfo->replyto) {
    $rmail= $DBInfo->replyto;
  } else {
    $rmail= "noreply@{$_SERVER['SERVER_NAME']}";
    if(preg_match("/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/i",
      $_SERVER['SERVER_NAME']))
      $rmail= 'noreply@['.$_SERVER['SERVER_NAME'].']';
  }

  if ($options['id']) {
    $return=$options['id'].' <'.$rmail.'>';
  } else {
    $return=$DBInfo->sitename.' <'.$rmail.'>';
  }
 
  $mailheaders = "Return-Path: $return\n";
  $mailheaders.= "From: $from <$rmail>\n";
  $mailheaders.= "Reply-To: $return\n";
  $mailheaders.= "X-Mailer: MoniWiki form-mail interface\n";

  $mailheaders.= "MIME-Version: 1.0\n";
  $mailheaders.= "Content-Type: text/plain; charset=$DBInfo->charset\n";
  $mailheaders.= "Content-Transfer-Encoding: 8bit\n\n";

  $body = sprintf(_("You have subscribed to this wiki page on \"%s\" for change notification.\n\n"),$DBInfo->sitename);
  $body.= $reminder;
  $body.="-------- Page name: $options[page] ---------\n";
  
  $body.=$formatter->page->get_raw_body();
  if (!$options['nodiff']) {
    $body.="================================\n";
    $body.=$diff;
  }

  $ret=mail($mailto,$subject,$body,$mailheaders,'-f'.$rmail);

  if ($options['noaction']) return 1;

  $title=_("Send notification mails to all subscribers");
  $formatter->send_header("",$options);
  $formatter->send_title($title,"",$options);
  $msg= str_replace("@"," at ",$mailto);
  if ($ret) {
    print "<h2>".sprintf(_("Mails are sent successfully"))."</h2>";
    printf(sprintf(_("mails are sent to '%s'"),$msg));
  } else {
    print "<h2>".sprintf(_("Fail to send mail"))."</h2>";
  }
  $formatter->send_footer("",$options);
  return;
}

/**
 * Email validator
 *
 * Please see http://www.corecoding.com/php-email-validation_c15.html
 */

function verify_email($email, $timeout = 5, $debug = false) {
    list($name, $domain) = explode('@', $email, 2);
    $mxhosts = array();
    $result = false;
    if (function_exists('getmxrr'))
        $result = getmxrr($domain, $mxhosts);
    if (!$result) $mxhosts[0] = $domain;
    if ($debug) {
        foreach ($mxhosts as $i=>$mx)
            echo '['.$i.'] '.$mx."\n";
    }

    $ret = 1;
    if ($debug) echo 'Try to connect ';
    foreach ($mxhosts as $mxhost) {
        if ($debug) echo $mxhost.", ... ";
        $sock = @fsockopen($mxhost, 25, $errno, $errstr, $timeout);
        if (is_resource($sock)) break;
    }
    if (!is_resource($sock)) return -$ret;
    if ($debug) echo 'connected!'."\n";

    $code = 1;
    while (preg_match('/^220/', $out = fgets($sock, 2048))) {
        if ($debug) echo $out;

        fwrite($sock, "HELO ".$domain."\r\n");
        $out = fgets($sock, 2048);
        if ($debug) echo 'HELO => '."\n\t".$out;
        $code = substr($out, 0, 3);
        $continue = $out[3];
        if ($code != '250')
            break;

        // trash buffer
        if ($continue == '-')
            while(($out = fgets($sock, 2048)) !== false) {
                if ($debug) echo "\t".$out;
                $code = substr($out, 0, 3);
                $continue = $out[3];
                if ($continue == ' ')
                    break;
            }

        fwrite($sock, "MAIL FROM: <nobody@localhost.localdomain>\r\n");
        $from = fgets($sock, 2048);
        if ($debug) echo 'MAIL FROM: => '."\n\t".$from;
        $code = substr($from, 0, 3);
        $continue = $out[3];

        // trash buffer
        if ($continue == '-')
            while(($out = fgets($sock, 2048)) !== false) {
                if ($debug) echo "\t".$out;
                $code = substr($out, 0, 3);
                $continue = $out[3];
                if ($continue == ' ')
                    break;
            }

        if ($code == '553') {
            // some cases like as hanmail
            fwrite($sock, 'MAIL FROM: <'.$email.">\r\n");
            $from = fgets($sock, 2048);
            if ($debug) echo 'MAIL FROM: <'.$email.'> => '."\n\t".$from;
            $code = substr($from, 0, 3);
            $continue = $from[3];
        }

        // trash buffer again
        if ($continue == '-')
            while(($out = fgets($sock, 2048)) !== false) {
                if ($debug) echo "\t".$out;
                $code = substr($out, 0, 3);
                $continue = $out[3];
                if ($continue == ' ')
                    break;
            }

        fwrite($sock, 'RCPT TO: <'.$email.">\r\n");
        $to = fgets($sock, 2048);
        if ($debug) echo 'RCPT TO: => '."\n\t".$to;
        $code = substr($to, 0, 3);
        $continue = $to[3];

        // trash buffer
        if ($continue == '-')
            while(($out = fgets($sock, 2048)) !== false) {
                if ($debug) echo "\t".$out;
                $code = substr($out, 0, 3);
                $continue = $out[3];
                if ($continue == ' ')
                    break;
            }

        break;
    }

    if ($code == 1) {
        $code = substr($out, 0, 3);
        $continue = substr($out, 3, 1);
        if ($debug) echo $out;

        if ($continue == '-')
            while(($out = fgets($sock, 2048)) !== false) {
                if ($debug) echo "\t".$out;
                $code = substr($out, 0, 3);
                $continue = $out[3];
                if ($continue == ' ')
                    break;
            }
    } else {
        fwrite($sock, "RSET\r\n");
        $out = fgets($sock, 2048);
        if ($debug) echo 'RSET => '."\n\t".$out;
        fwrite($sock, "QUIT\r\n");
        $out = fgets($sock, 2048);
        if ($debug) echo 'QUIT => '."\n\t".$out;
    }

    fclose($sock);

    return $code == '250' ? '250' : -$code;
}

function wiki_sendmail($body,$options) {
  global $DBInfo;

  if (empty($DBInfo->use_sendmail)) {
    return array('msg'=>_("This wiki does not support sendmail"));
  }

  if (!empty($DBInfo->replyto)) {
    $rmail= $DBInfo->replyto;
  } else {
    // make replyto address
    $rmail= "noreply@{$_SERVER['SERVER_NAME']}";
    if(preg_match("/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/i",
      $_SERVER['SERVER_NAME']))
      $rmail= 'noreply@['.$_SERVER['SERVER_NAME'].']';
  }

  if ($options['id']) {
    $return=$options['id'].' <'.$rmail.'>';
  } else {
    $return=$DBInfo->sitename.' <'.$rmail.'>';
  }

  $from = !empty($options['from']) ? $options['from']:$return;

  $email=$options['email'];
  $subject=$options['subject'];
  $subject= '=?'.$DBInfo->charset.'?B?'.rtrim(base64_encode($subject)).'?=';

  $mailheaders = "Return-Path: $return\n";
  $mailheaders.= "From: $from\n";
  $mailheaders.= "Reply-To: $return\n";
  $mailheaders.= "X-Mailer: MoniWiki form-mail interface\n";

  $mailheaders.= "MIME-Version: 1.0\n";
  $mailheaders.= "Content-Type: text/plain; charset=$DBInfo->charset\n";
  $mailheaders.= "Content-Transfer-Encoding: 8bit\n\n";

  if (!empty($DBInfo->email_header) and file_exists($DBInfo->email_header)) {
    $header = file_get_contents($DBInfo->email_header);
    $body = $header.$body;
  }

  if (!empty($DBInfo->email_footer) and file_exists($DBInfo->email_footer)) {
    $footer = file_get_contents($DBInfo->email_footer);
    $body.= "\n".$footer;
  }

  if (!empty($DBInfo->sendmail_path)) {
    $header = "To: $email\n".
              "Subject: $subject\n";

    $handle = popen($DBInfo->sendmail_path, 'w');
    if (is_resource($handle)) {
      fwrite($handle, $header.$mailheaders.$body);
      fclose($handle);
    }
  } else {
    mail($email,$subject,$body,$mailheaders,'-f'.$rmail);
  }
  return 0;
}


function do_RandomPage($formatter,$options='') {
  global $DBInfo;

  if (!empty($options['action_mode']) and $options['action_mode'] == 'ajax') {
    $val = !empty($options['value']) ? intval($options['value']) : '';

    $params = $options;
    $params['call'] = 1;
    $ret = macro_RandomPage($formatter, $val, $params);
    if (function_exists('json_encode')) {
        echo json_encode($ret);
    } else {
        require_once('lib/JSON.php');
        $json = new Services_JSON();
        echo $json->encode($ret);
    }
    return;
  }

  $max = $DBInfo->getCounter();
  $rand = rand(1,$max);

  $indexer = $DBInfo->lazyLoad('titleindexer');
  $sel_pages = $indexer->getPagesByIds(array($rand));
  $options['value'] = $sel_pages[0];
  do_goto($formatter,$options);
  return;
}

function macro_RandomPage($formatter, $value = '', $params = array()) {
  global $DBInfo;

  $count = '';
  $mode = '';
  if (!empty($value)) {
    $vals = get_csv($value);
    if (!empty($vals)) {
      foreach ($vals as $v) {
        if (is_numeric($v)) {
          $count = $v;
        } else if (in_array($v, array('simple', 'nobr', 'js'))) {
          $mode = $v;
        }
      }
    }
  }

  if ($formatter->_macrocache and empty($options['call']) and $mode != 'js')
    return $formatter->macro_cache_repl('RandomPage', $value);
  $formatter->_dynamic_macros['@RandomPage'] = 1;

  if ($count <= 0) $count=1;
  $counter= $count;

  $max = $DBInfo->getCounter();

  if (empty($max))
    return '';

  $number=min($max,$counter);

  if ($mode == 'js') {
    static $id = 1;
    $myid = sprintf("randomPage%02d", $id);
    $id++;
    $url = $formatter->link_url('', "?action=randompage/ajax&value=".$number);
    return <<<EOF
<div id='$myid'>
</div>
<script type='text/javascript'>
/*<![CDATA[*/
(function () {
  HTTPGet("$url", function(msg) {
   var ret;
   if (msg != null && (ret = eval(msg))) {
      var div = document.getElementById("$myid");
      var ul = document.createElement('UL');
      for(var i = 0; i < ret.length; i++) {
        var li = document.createElement('LI');
        li.innerHTML = ret[i];
        ul.appendChild(li);
      }
      div.appendChild(ul);
   }
 });
})();
/*]]>*/
</script>
EOF;
  }

  // select pages
  $selected = array();
  for ($i = 0; $i < $number; $i++) {
    $selected[] = rand(0, $max - 1);   
  }
  $selected = array_unique($selected);
  
  sort($selected);
  $sel_count = count($selected);

  $indexer = $DBInfo->lazyLoad('titleindexer');
  $sel_pages = $indexer->getPagesByIds($selected);

  $selects = array();
  foreach ($sel_pages as $item) {
    $selects[]=$formatter->link_tag(_rawurlencode($item),"",_html_escape($item));
  }

  if (isset($params['call']))
    return $selects;

  if ($count > 1) {
    if (!$mode)
      return "<ul>\n<li>".join("</li>\n<li>",$selects)."</li>\n</ul>";
    if ($mode=='simple')
      return join("<br />\n",$selects)."<br />\n";
    if ($mode=='nobr')
      return join(" ",$selects);
  }
  return join("",$selects);
}

define('DEFAULT_QUOTE_PAGE','FortuneCookies');
function macro_RandomQuote($formatter,$value="",$options=array()) {
  global $DBInfo;
  #if ($formatter->preview==1) return '';

  $re='/^\s*\* (.*)$/';
  $args=explode(',',$value);

  $log = '';
  foreach ($args as $arg) {
    $arg=trim($arg);
    if (!empty($arg[0]) and in_array($arg[0],array('@','/','%')) and
      preg_match('/^'.$arg[0].'.*'.$arg[0].'[sxU]*$/',$arg)) {
      if (preg_match($arg,'',$m)===false) {
        $log=_("Invalid regular expression !");
        continue;
      }
      $re=$arg;
    } else
      $pagename=$arg;
  }

  if (!empty($pagename) and $DBInfo->hasPage($pagename))
    $fortune=$pagename;
  else
    $fortune=DEFAULT_QUOTE_PAGE;

  if (!empty($options['body'])) {
    $raw=$options['body'];
  } else {
    $page=$DBInfo->getPage($fortune);
    if (!$page->exists()) return '';
    $raw=$page->get_raw_body();
  }

  preg_match_all($re.'m',$raw,$match);
  $quotes=&$match[1];

  if (!($count=sizeof($quotes))) return '[[RandomQuote('._("No match!").')]]';
  #if ($formatter->preview==1) return '';
  if ($count<3 and preg_grep('/\[\[RandomQuote/',$quotes))
    return '[[RandomQuote('._("Infinite loop possible!").')]]';

  $quote=$quotes[rand(0,$count-1)];

  $dumb=explode("\n",$quote);
  if (sizeof($dumb)>1) {
    if (isset($formatter->preview)) $save = $formatter->preview;
    $formatter->preview=1;
    $options['nosisters']=1;
    ob_start();
    $formatter->send_page($quote,$options);
    if (isset($save))
      $formatter->preview=$save;
    $out= ob_get_contents();
    ob_end_clean();
  } else {
    $formatter->set_wordrule();
    $quote=str_replace("<","&lt;",$quote);
    $quote=preg_replace($formatter->baserule,$formatter->baserepl,$quote);
    $out = preg_replace_callback("/(".$formatter->wordrule.")/",
        array(&$formatter, 'link_repl'), $quote);
  }
#  ob_start();
#  $options['nosisters']=1;
#  $formatter->send_page($quote,$options);
#  $out= ob_get_contents();
#  ob_end_clean();
#  return $out;
  return $log.$out;
}


function macro_Date($formatter,$value) {
  global $DBInfo;

  $tz_offset=&$formatter->tz_offset;

  $fmt=&$DBInfo->date_fmt;
  if (!$value) {
    return gmdate($fmt,time()+$tz_offset);
  }
  if ($value[10]== 'T') {
    $value[10]=' ';
    $time=strtotime($value.' GMT');
    return gmdate($fmt,$time+$tz_offset);
  }
  return gmdate($fmt,time()+$tz_offset);
}

function macro_DateTime($formatter,$value) {
  global $DBInfo;

  $fmt=&$DBInfo->datetime_fmt;
  $tz_offset=&$formatter->tz_offset;

  if (!$value) {
    return gmdate($fmt,time()+$tz_offset);
  }
  if (isset($value[10]) and $value[10]== 'T') {
    $value[10]=' ';
    $value.=' GMT';
  }

  if (preg_match('/^\d{2,4}(\-|\/)\d{1,2}\\1\d{1,2}\s+\d{2}:\d{2}/',$value)) {
    $time=strtotime($value);
    return gmdate($fmt,$time+$tz_offset);
  }

  return gmdate("Y/m/d H:i:s",time()+$tz_offset).' GMT';
}

function _joinagreement_form() {
  global $Config;

  $form = '';
  if (!empty($Config['agreement_comment'])) {
    // show join agreement confirm message
    $form.= '<div class="join-agreement">';
    $form.= str_replace("\n", "<br />", $Config['agreement_comment']);
    $form.= "</div>\n";
  } else if (!empty($Config['agreement_page']) and file_exists($Config['agreement_page'])) {
    // show join agreement confirm message from a external text file
    $form.= '<div class="join-agreement">';
    $tmp = file_get_contents($Config['agreement_page']);
    if (preg_match('/\.txt$/', $Config['agreement_page']))
      $form.= nl2br($tmp);
    else
      $form.= $tmp;
    $form.= '<div>'.sprintf(_("Last modified at %s"), substr(gmdate('r', filemtime($Config['agreement_page'])), 0, -5).'GMT').'</div>';
    $form.= "</div>\n";
  }
  return $form;
}

function macro_UserPreferences($formatter,$value,$options='') {
  global $DBInfo;

  if ($formatter->_macrocache and empty($options['call']))
    return $formatter->macro_cache_repl('UserPreferences', $value);
  $formatter->_dynamic_macros['@UserPreferences'] = 1;

  $use_any=0;
  if (!empty($DBInfo->use_textbrowsers)) {
    if (is_string($DBInfo->use_textbrowsers))
      $use_any= preg_match('/'.$DBInfo->use_textbrowsers.'/',
        $_SERVER['HTTP_USER_AGENT']) ? 1:0;
    else
      $use_any= preg_match('/Lynx|w3m|links/',
        $_SERVER['HTTP_USER_AGENT']) ? 1:0;
  }

  $user=$DBInfo->user; # get from COOKIE VARS

  // User class support login method
  $login_only = false;
  if (method_exists($user, 'login'))
    $login_only = true;

  $jscript='';
  if (!empty($DBInfo->use_safelogin)) {
    $onsubmit=' onsubmit="javascript:_chall.value=challenge.value;password.value=hex_hmac_md5(challenge.value, hex_md5(password.value))"';
    $jscript.="<script src='$DBInfo->url_prefix/local/md5.js'></script>";
    $time_seed=time();
    $chall=md5(base64_encode(getTicket($time_seed,$_SERVER['REMOTE_ADDR'],10)));
    $passwd_hidden="<input type='hidden' name='_seed' value='$time_seed' />";
    $passwd_hidden.="<input type='hidden' name='challenge' value='$chall' />";
    $passwd_hidden.="<input type='hidden' name='_chall' />\n";
    $pw_length=32;
  } else {
    $passwd_hidden = '';
    $onsubmit = '';
    $pw_length=20;
  }

  $passwd_btn=_("Password");
  $url = qualifiedUrl($formatter->link_url($formatter->page->urlname));
  $return_url = $url;

  if (!empty($DBInfo->use_ssl_login))
    $url = preg_replace('@^http://@', 'https://', $url);

  # setup form
  if ($user->id == 'Anonymous') {
    if (!empty($options['login_id'])) {
      $login_id = _html_escape($options['login_id']);
      $idform = $login_id."<input type='hidden' name='login_id' value=\"$login_id\" />";
    } else
      $idform="<input type='text' size='20' name='login_id' value='' />";
  } else {
    $idform=$user->id;
    if (!empty($user->info['idtype']) and $user->info['idtype']=='openid') {
      $idform='<img src="'.$DBInfo->imgs_dir_url.'/openid.png" alt="OpenID:" style="vertical-align:middle" />'.
      '<a href="'.$idform.'">'.$idform.'</a>';
    }
  }

  $button=_("Login");
  $openid_btn=_("OpenID");
  $openid_form='';
  if ($user->id == 'Anonymous' && !empty($DBInfo->use_openid)) {
    $openid_form=<<<OPENID
  <tr>
    <th>OpenID</th>
    <td>
      <input type="text" name="openid_url" value="" style="background:url($DBInfo->imgs_dir_url/openid.png) no-repeat; padding:2px;padding-left:24px; border-width:1px" />
	    <span class="button"><input type="submit" class="button" name="login" value="$button" /></span> &nbsp;
    </td>
  </tr>
OPENID;
    }
  $id_btn=_("ID");
  $sep="<tr><td colspan='2'><hr /></td></tr>\n";
  $sep0='';
  $login = '';
  if ($user->id == 'Anonymous' and !isset($options['login_id']) and $value!="simple") {
    if (isset($openid_form) and $value != 'openid') $sep0=$sep;
    if ($value != 'openid')
      $default_form=<<<MYFORM
  <tr><th>$id_btn&nbsp;</th><td>$idform</td></tr>
  <tr>
     <th>$passwd_btn&nbsp;</th><td><input type="password" size="15" maxlength="$pw_length" name="password" value="" /></td>
  </tr>
  <tr><td></td><td>
    $passwd_hidden
    <span class="button"><input type="submit" class="button" name="login" value="$button" /></span> &nbsp;
  </td></tr>
MYFORM;
    $login=<<<FORM
<div>
<form method="post" action="$url"$onsubmit>
<div>
<input type="hidden" name="action" value="userform" />
<input type="hidden" name="return_url" value="$return_url" />
<table border="0">
$openid_form
$sep0
$default_form
</table>
</div>
</form>
</div>
FORM;
    $openid_form='';
  }

  $logout = '';
  $joinagree = empty($DBInfo->use_agreement) || !empty($options['joinagreement']);

  if (!$login_only and $user->id == 'Anonymous') {
    if (isset($options['login_id']) or !empty($_GET['join']) or $value!="simple") {
      $passwd=!empty($options['password']) ? $options['password'] : '';
      $button=_("Make profile");
      $again = '';
      if ($joinagree and empty($DBInfo->use_safelogin)) {
        $again="<b>"._("password again")."</b>&nbsp;<input type='password' size='15' maxlength='$pw_length' name='passwordagain' value='' /></td></tr>";
      }
      $email_btn=_("Mail");
      if (empty($options['agreement']) or !empty($options['joinagreement']))
      $extra=<<<EXTRA
  <tr><th>$email_btn&nbsp;</th><td><input type="text" size="40" name="email" value="" /></td></tr>
EXTRA;
      if (!empty($DBInfo->use_agreement) and !empty($options['joinagreement']))
        $extra.= '<input type="hidden" name="joinagreement" value="1" />';
      if (!$use_any and !empty($DBInfo->use_ticket)) {
        $seed=md5(base64_encode(time()));
        $ticketimg=$formatter->link_url($formatter->page->urlname,'?action=ticket&amp;__seed='.$seed);
        $extra.=<<<EXTRA
  <tr><td><img src="$ticketimg" alt="captcha" />&nbsp;</td><td><input type="text" size="10" name="check" />
<input type="hidden" name="__seed" value="$seed" /></td></tr>
EXTRA;
      }
      $tz_offset = date('Z');
    } else {
      $button=_("Login or Join");
    }
  } else if ($user->id != 'Anonymous') {
    $button=_("Save");
    $css=!empty($user->info['css_url']) ? $user->info['css_url'] : '';
    $css = _html_escape($css);
    $email=!empty($user->info['email']) ? $user->info['email'] : '';
    $email = _html_escape($email);
    $nick=!empty($user->info['nick']) ? $user->info['nick'] : '';
    $nick = _html_escape($nick);
    $check_email_again = '';
    if (!empty($user->info['eticket'])) {
      list($dummy, $em) = explode('.', $user->info['eticket'], 2);
      if (!empty($em))
        $check_email_again = ' <input type="submit" name="button_check_email_again" value="'._("Resend confirmation mail").'" />';
    }

    $tz_offset=!empty($user->info['tz_offset']) ? $user->info['tz_offset'] : date('Z');
    if (!empty($user->info['password']))
      $again="<b>"._("New password")."</b>&nbsp;<input type='password' size='15' maxlength='$pw_length' name='passwordagain' value='' /></td></tr>";
    else
      $again='';

    if (preg_match("@^https?://@",$user->id)) {
      $nick_btn=_("Nickname");
      $nick=<<<NICK
  <tr><th>$nick_btn&nbsp;</th><td><input type="text" size="40" name="nick" value="$nick" /></td></tr>
NICK;
    }

    $tz_off=date('Z');
    $opts = '';
    for ($i=-47;$i<=47;$i++) {
      $val=1800*$i;
      $tz=gmdate("Y/m/d H:i",time()+$val);
      $hour=sprintf("%02d",abs((int)($val / 3600)));
      $z=$hour . (($val % 3600) ? ":30":":00");
      if ($val < 0) $z="-".$z;
      if ($tz_offset !== '' and $val== $tz_offset)
        $selected=" selected='selected'";
      else
        $selected="";
      
      $opts.="<option value='$z'$selected>$tz [$z]</option>\n";
    }

    $jscript.="<script src='$DBInfo->url_prefix/local/tz.js'></script>";
    $email_btn=_("Mail");
    $tz_btn=_("Time Zone");
    $extra=<<<EXTRA
$nick
  <tr><th>$email_btn&nbsp;</th><td><input type="text" size="40" name="email" value="$email" />$check_email_again</td></tr>
  <tr><th>$tz_btn&nbsp;</th><td><select name="timezone">
  $opts
  </select> <span class='button'><input type='button' class='button' value='Local timezone' onclick='javascript:setTimezone()' /></span></td></tr>
  <tr><td><b>CSS URL </b>&nbsp;</td><td><input type="text" size="40" name="user_css" value="$css" /><br />("None" for disabling CSS)</td></tr>
EXTRA;
    $logout="<span class='button'><input type='submit' class='button' name='logout' value='"._("logout")."' /></span> &nbsp;";

    $show_join_agreement = false;
    if (!empty($DBInfo->use_agreement)) {
      if ($user->info['join_agreement'] != 'agree')
        $show_join_agreement = true;
      if (!empty($DBInfo->agreement_version)) {
        if ($user->info['join_agreement_version'] != $DBInfo->agreement_version)
          $show_join_agreement = true;
      }
    }

    if ($show_join_agreement) {
      $extra.= _joinagreement_form();
      $accept = _("Accept agreement");
      $extra.= <<<FORM
<div class='check-agreement'><p><input type='checkbox' name='joinagreement' />$accept</p>
FORM;
    }

  } else if ($user->id == 'Anonymous') {
    $button=_("Make profile");
    $email_btn=_("Mail");
    $tz_offset = date('Z');
  }
  $script = '';
  if ($tz_offset === '' and $jscript)
    $script=<<<EOF
<script type="text/javascript">
/*<![CDATA[*/
setTimezone();
/*]]>*/
</script>
EOF;

  $passwd = !empty($passwd) ? $passwd : '';
  $passwd_inp = '';
  if (($joinagree and empty($DBInfo->use_safelogin)) or $button==_("Save")) {
    if ($user->id == 'Anonymous' or !empty($user->info['password']))
    $passwd_inp=<<<PASS
  <tr>
     <th>$passwd_btn&nbsp;</th><td><input type="password" size="15" maxlength="$pw_length" name="password" value="$passwd" />
PASS;

  } else {
    $onsubmit='';
    $passwd_hidden='';
  }
  $emailpasswd = '';
  if (!$login_only && $button==_("Make profile")) {
    if (empty($options['agreement']) and !empty($DBInfo->use_sendmail)) {
      $button2=_("E-mail new password");
      $emailpasswd=
        "<span class='button'><input type=\"submit\" class='button' name=\"login\" value=\"$button2\" /></span>\n";

    } else if (isset($options['login_id']) and !empty($DBInfo->use_agreement) and empty($options['joinagreement'])) {
      $form = <<<FORM
<div>
<form method="post" action="$url">
<div>
<input type="hidden" name="action" value="userform" />

FORM;
      $form.= "<input type='hidden' name='login_id' ";
      if (isset($options['login_id'][0])) {
        $login_id = _html_escape($options['login_id']);
        $form.= "value=\"$login_id\"";
      }
      $form.= " />";

      $form.= _joinagreement_form();
      $accept = _("Accept agreement");
      $form.= <<<FORM
<div class='check-agreement'><p><input type='checkbox' name='joinagreement' />$accept</p>
<span class="button"><input type="submit" class="button" name="login" value="$button" /></span>
</div>
</div>
</form>
</div>

FORM;
      return $form;
    }
  }

  $emailverify = '';

  if ($user->id == 'Anonymous' && !empty($DBInfo->anonymous_friendly)) {
    $verifiedemail = isset($options['verifyemail']) ? $options['verifyemail'] :
                    (isset($user->verified_email) ? $user->verified_email : '');
    $button3 =_("Verify E-mail address");
    $button4 =_("Remove");
    $remove = '';
    if ($verifiedemail)
      $remove = "<span class='button'><input type='submit' class='button' name='emailreset' value='$button4' /></span>";
    $emailverify = <<<EOF
          $sep
          <tr><th>$email_btn&nbsp;</th><td><input type='text' size='40' name='verifyemail' value="$verifiedemail" /></td></tr>
          <tr><td></td><td>
          <span class='button'><input type="submit" class='button' name="verify" value="$button3" /></span>
          $remove
          </td></tr>
EOF;
  }
  $id_btn=_("ID");
  $sep1 = '';
  if (!empty($openid_form) or !empty($login)) $sep1=$sep;
  $all = <<<EOF
$login
$jscript
EOF;

  if (!$login_only || $user->id != 'Anonymous')
    $all.= <<<EOF
<div>
<form method="post" action="$url"$onsubmit>
<div>
<input type="hidden" name="action" value="userform" />
<table border="0">
$openid_form
$sep1
  <tr><th>$id_btn&nbsp;</th><td>$idform</td></tr>
    $passwd_inp
    $passwd_hidden
    $again
    $extra
  <tr><td></td><td>
    <span class="button"><input type="submit" class="button" name="login" value="$button" /></span> &nbsp;
    $emailpasswd
    $emailverify
    $logout
  </td></tr>
</table>
</div>
</form>
</div>
$script
EOF;
  else if ($login_only)
    $all.= <<<EOF
<div>
<form method="post" action="$url"$onsubmit>
<div>
<input type="hidden" name="action" value="userform" />
<table border="0">
  <tr><td></td><td>
    $emailverify
  </td></tr>
</table>
</div>
</form>
</div>
EOF;

  return $all;
}

function macro_InterWiki($formatter,$value,$options=array()) {
  global $DBInfo;

  while (!isset($DBInfo->interwiki) or !empty($options['init'])) {
    $cf = new Cache_text('settings', array('depth'=>0));

    // check intermap and shared_intermap
    // you can update interwiki maps by touch $intermap or edit $shared_intermap
    if (empty($formatter->refresh) and ($info = $cf->fetch('interwiki')) !== false) {
      $info = $cf->fetch('interwiki');
      $DBInfo->interwiki=$info['interwiki'];
      $DBInfo->interwikirule=$info['interwikirule'];
      $DBInfo->intericon=$info['intericon'];
      break;
    }

    $deps = array();
    $interwiki=array();
    # intitialize interwiki map
    $map = array();
    if (isset($DBInfo->intermap[0]) && file_exists($DBInfo->intermap)) {
      $map = file($DBInfo->intermap);
      $deps[] = $DBInfo->intermap;
    }
    if (!empty($DBInfo->sistermap) and file_exists($DBInfo->sistermap))
      $map=array_merge($map,file($DBInfo->sistermap));

    # read shared intermap
    if (file_exists($DBInfo->shared_intermap)) {
      $map=array_merge($map,file($DBInfo->shared_intermap));
      $deps[] = $DBInfo->shared_intermap;
    }

    $interwikirule = '';
    for ($i=0,$sz=sizeof($map);$i<$sz;$i++) {
      $line=rtrim($map[$i]);
      if (!$line || $line[0]=="#" || $line[0]==" ") continue;
      if (preg_match("/^[A-Z]+/",$line)) {
        $wiki=strtok($line,' ');$url=strtok(' ');
        $dumm=trim(strtok(''));
        if (preg_match('/^(http|ftp|attachment):/',$dumm,$match)) {
          $icon=strtok($dumm,' ');
          if ($icon[0]=='a') {
            $url=$formatter->macro_repl('Attachment',substr($icon,11),1);
            $icon=qualifiedUrl($DBInfo->url_prefix.'/'.$url);
          }
          preg_match('/^(\d+)(x(\d+))?\b/',strtok(''),$msz);
          $sx=$msz[1];$sy=$msz[3];
          $sx=$sx ? $sx:16; $sy=$sy ? $sy:16;
          $intericon[$wiki]=array($sx,$sy,trim($icon));
        }
        $interwiki[$wiki]=trim($url);
        $interwikirule.="$wiki|";
      }
    }
    $interwikirule.="Self";
    $interwiki['Self']=get_scriptname().$DBInfo->query_prefix;

    # set default TwinPages interwiki
    if (empty($interwiki['TwinPages']))
      $interwiki['TwinPages']=(($DBInfo->query_prefix == '?') ? '&amp;':'?').
        'action=twinpages&amp;value=';

    # read shared intericons
    $map=array();
    if (!empty($DBInfo->shared_intericon) and file_exists($DBInfo->shared_intericon))
      $map=array_merge($map,file($DBInfo->shared_intericon));

    $intericon = array();
    for ($i=0,$isz=sizeof($map);$i<$isz;$i++) {
      $line=rtrim($map[$i]);
      if (!$line || $line[0]=="#" || $line[0]==" ") continue;
      if (preg_match("/^[A-Z]+/",$line)) {
        $wiki=strtok($line,' ');$icon=trim(strtok(' '));
        if (!preg_match('/^(http|ftp|attachment):/',$icon,$match)) continue;
        preg_match('/^(\d+)(x(\d+))?\b/',strtok(''),$sz);
        $sx=$sz[1];$sy=$sz[3];
        $sx=$sx ? $sx:16; $sy=$sy ? $sy:16;
        if ($icon[0]=='a') {
          $url=$formatter->macro_repl('Attachment',substr($icon,11),1);
          $icon=qualifiedUrl($DBInfo->url_prefix.'/'.$url);
        }
        $intericon[$wiki]=array($sx,$sy,trim($icon));
      }
    }
    $DBInfo->interwiki=$interwiki;
    $DBInfo->interwikirule=$interwikirule;
    $DBInfo->intericon=$intericon;
    $interinfo=
      array('interwiki'=>$interwiki,'interwikirule'=>$interwikirule,'intericon'=>$intericon);
    $cf->update('interwiki', $interinfo, 0, array('deps'=>$deps));
    break;
  }
  if (!empty($options['init'])) return;

  $out="<table border='0' cellspacing='2' cellpadding='0'>";
  foreach (array_keys($DBInfo->interwiki) as $wiki) {
    $href=$DBInfo->interwiki[$wiki];
    if (strpos($href,'$PAGE') === false)
      $url=$href.'RecentChanges';
    else {
      $url=str_replace('$PAGE','index',$href);
      #$href=$url;
    }
    $icon=$DBInfo->imgs_url_interwiki.strtolower($wiki).'-16.png';
    $sx=16;$sy=16;
    if (!empty($DBInfo->intericon[$wiki])) {
      $icon=$DBInfo->intericon[$wiki][2];
      $sx=$DBInfo->intericon[$wiki][0];
      $sy=$DBInfo->intericon[$wiki][1];
    }
    $out.="<tr><td><tt><img src='$icon' width='$sx' height='$sy' ".
      "class='interwiki' alt='$wiki:' /><a href='$url'>$wiki</a></tt></td>";
    $out.="<td><tt><a href='$href'>$href</a></tt></td></tr>\n";
  }
  $out.="</table>\n";
  return $out;
}


function get_key($name) {
  global $DBInfo;
  if (preg_match('/[a-z0-9]/i',$name[0])) {
     return strtoupper($name[0]);
  }
  $utf="";
  $use_utf=strtolower($DBInfo->charset)=='utf-8';
  if (!$use_utf and function_exists ("iconv")) {
    # XXX php 4.1.x did not support unicode sting.
    $utf=iconv($DBInfo->charset,'UTF-8',$name);
    $name=$utf;
  }

  if ($utf or $use_utf) {
    if ((ord($name[0]) & 0xF0) == 0xE0) { # Now only 3-byte UTF-8 supported
       $uni=((ord($name[0]) & 0x0f) << 12) |
            ((ord($name[1]) & 0x7f) << 6) | (ord($name[2]) & 0x7f);

       # Hangul Syllables
       if ($uni>=0xac00 && $uni<=0xd7a3) {
         $ukey=0xac00 + (int)(($uni - 0xac00) / 588) * 588;
         $ukey=toutf8($ukey);
         if ($utf and !$use_utf)
           return iconv('UTF-8',$DBInfo->charset,$ukey);
         return $ukey;
       }
    }
    return 'Others';
  } else {
    # if php does not support iconv(), EUC-KR assumed
    if (strtolower($DBInfo->charset) == 'euc-kr') {
      $korean=array( // Ga,GGa,Na,Da,DDa,...
        "\xb0\xa1","\xb1\xee","\xb3\xaa","\xb4\xd9","\xb5\xfb",
        "\xb6\xf3","\xb8\xb6","\xb9\xd9","\xba\xfc","\xbb\xe7",
        "\xbd\xce","\xbe\xc6","\xc0\xda","\xc2\xa5","\xc2\xf7",
        "\xc4\xab","\xc5\xb8","\xc6\xc4","\xc7\xcf","\xca");

      $lastPosition='Others';

      $letter=substr($name,0,2);
      foreach ($korean as $position) {
        if ($position > $letter)
          return $lastPosition;
        $lastPosition=$position;
      }
    }
    return 'Others';
  }
}

/**
 * get the list of all keys and its regex
 * number + alphabet + hangul + others
 */

function get_keys() {
  global $Config;

  $keys = array();
  for ($i = 0; $i <= 9; $i++)
    $keys["$i"] = "$i";
  for ($i = 65; $i <= 90; $i++) {
    $k = chr($i);
    $keys["$k"] = "$k";
  }
  if (strtolower($Config['charset']) == 'euc-kr') {
    $korean = array( // Ga,GGa,Na,Da,DDa,...
        "\xb0\xa1","\xb1\xee","\xb3\xaa","\xb4\xd9","\xb5\xfb",
        "\xb6\xf3","\xb8\xb6","\xb9\xd9","\xba\xfc","\xbb\xe7",
        "\xbd\xce","\xbe\xc6","\xc0\xda","\xc2\xa5","\xc2\xf7",
        "\xc4\xab","\xc5\xb8","\xc6\xc4","\xc7\xcf","\xc8\xff");
    for ($i = 0; $i < count($korean) - 1; $i++) {
      $k1 = $korean[$i];
      $k2 = $korean[$i + 1];
      $k2 = $k2[0].chr(ord($k2[1]) - 1);
      $keys["$k1"] = '['.$k1.'-'.$k2.']';
    }
    $k1 = $korean[0];
    $keys['Others'] = '[^0-9A-Z'.$k1.'-'.$k2.']';

    return $keys;
  }
  for ($i = 0; $i < 19; $i++) {
    $u1 = 0xac00 + (int)(($i * 588) / 588) * 588;
    $u2 = 0xac00 + (int)(($i * 588) / 588) * 588 + 20 * 28 + 27;
    $k1 = toutf8($u1);
    $k2 = toutf8($u2);
    $keys["$k1"] = '['.$k1.'-'.$k2.']';
  }
  $k1 = toutf8(0xac00);
  $keys['Others'] = '[^0-9A-Z'.$k1.'-'.$k2.']';

  return $keys;
}

function cached_pagecount($formatter, $params) {
    global $DBInfo, $Config;

    $sc = new Cache_Text('settings');
    $counter = $sc->fetch('counter');
    if ($counter === false) {
      // update counter
      if (!$sc->exists('counter.lock')) {
        $sc->update('counter.lock', array('lock'), 20); // 20sec lock
      } else {
        $counter = $sc->_fetch('counter');
        if ($counter === false) $counter = array('redirects'=>0);
      }
      if ($counter === false) {
        $counter = array('redirects'=>0);
        $rc = new Cache_Text('redirect');
        if (method_exists($rc, 'count'))
          $redirects = $rc->count();
        else
          $redirects = 0;

        $counter = array('redirects'=>$redirects);
      }
      $sc->update('counter', $counter, 60*60*24);
      $sc->remove('counter.lock');
    }
    return $counter;
}

/**
 * Count pages and redirect pages
 */
function macro_PageCount($formatter, $value = '', $options = array()) {
  global $DBInfo;

  $mode = '';
  $use_js = false;
  if (!empty($value)) {
    $vals = get_csv($value);
    if (!empty($vals)) {
      foreach ($vals as $v) {
        if (in_array($v, array('noredirect', 'redirect'))) {
          $mode = $v;
        } else if ($v == 'js') {
          $use_js = true;
        }
      }
    }
  }

  if ($formatter->_macrocache and empty($options['call']) and !$use_js)
    return $formatter->macro_cache_repl('PageCount', $value);
  if (empty($options['call']) and !$use_js)
  $formatter->_dynamic_macros['@PageCount'] = 1;

  $js = '';
  $mid = $formatter->mid++;
  if ($use_js) {
    $url = $formatter->link_url('', '?action=pagecount/ajax');
    $js = <<<JS
<script type='text/javascript'>
/*<![CDATA[*/
(function() {
var url = "$url";
var mode = "$mode";
HTTPGet(url, function(txt) {
var ret = window["eval"]("(" + txt + ")");
var rc = document.getElementById("macro-$mid");
var out = ret['pagecount'];
if (mode == 'noredirect')
    out -= ret['redirect'];
else if (mode == 'redirect')
    out = ret['redirect'];
rc.innerHTML = out;
});
})();
/*]]>*/
</script>
JS;
  }

  $redirects = 0;
  if (!empty($mode)) {
    $counter = cached_pagecount($formatter, $options);
    $redirects = $counter['redirects'];

    if ($mode == 'redirect')
      return '<span class="macro" id="macro-'.$mid.'">'.$redirects.'<span>'.$js;
  }
  $count = $DBInfo->getCounter();
  return '<span class="macro" id="macro-'.$mid.'">'.($count - $redirects).'</span>'.$js;
}

function ajax_pagecount($formatter, $params) {
    global $DBInfo, $Config;

    $counter = cached_pagecount($formatter, $params);
    $redirects = $counter['redirects'];

    $maxage = !empty($Config['proxy_maxage']) ? ', s-maxage='.$Config['proxy_maxage'] : '';
    header('Content-Type: text/plain');
    header('Cache-Control: public'.$maxage.',must-revalidate,post-check=0, pre-check=0');
    $count = $DBInfo->getCounter();
    echo '{pagecount:'.$count.',redirect:'.$redirects.'}';
}

function _setpagekey(&$page,$k) {
  if (($p = strpos($k, '~'))) {
    $g = '('.trim(substr($k,0,$p)).')';
    $page = trim(substr($k, $p+1)).$g;
  } else {
    $page = $k;
  }
}

function macro_TitleIndex($formatter, $value, $options = array()) {
  global $DBInfo;

  $pc = !empty($DBInfo->titleindex_pagecount) ? intval($DBInfo->titleindex_pagecount) : 100;
  if ($pc < 1) $pc = 100;

  $pg = empty($options['p']) ? 1 : intval($options['p']);
  if ($pg < 1) $pg = 1;

  $group=$formatter->group;

  $key=-1;
  $keys=array();

  if ($value=='' or $value=='all') $sel='';
  else $sel = ucfirst($value);

  // get all keys
  $all_keys = get_keys();

  if (isset($sel[0])) {
    if (!isset($all_keys[$sel]))
      $sel = key($all_keys); // default
  }

  if (@preg_match('/'.$sel.'/i','')===false) $sel='';

  $titleindex = array();

  // cache titleindex
  $kc = new Cache_text('titleindex');
  $delay = !empty($DBInfo->default_delaytime) ? $DBInfo->default_delaytime : 0;

  $uid = '';
  if (function_exists('posix_getuid'))
    $uid = '.'.posix_getuid();

  $index_lock = 'titleindex'.$uid;
  $locked = $kc->exists($index_lock);

  if ($locked or ($kc->exists('key') and $DBInfo->checkUpdated($kc->mtime('key'), $delay))) {
    if (!empty($formatter->use_group) and $formatter->group) {
      $keys = $kc->fetch('key.'.$formatter->group);
      $titleindex = $kc->fetch('titleindex.'.$formatter->group);
    } else {
      $keys = $kc->fetch('key');
      $titleindex = $kc->fetch('titleindex'.$sel);
    }
    if (isset($sel[0]) and isset($titleindex[$sel])) {
      $all_pages = $titleindex[$sel];
    }
    if (empty($titleindex) and $locked) {
      // no cache found
      return _("Please wait...");
    }
  }

  if (!isset($sel[0])) {
    $indexer = $DBInfo->lazyLoad('titleindexer');
    $total = $indexer->pageCount();

    // too many pages. check $sel
    if ($total > 10000) {
      $sel = ''.key($all_keys); // select default key
    }
  }

  if (empty($all_pages)) {

    $all_pages = array();
    if (empty($indexer))
      $indexer = $DBInfo->lazyLoad('titleindexer');
    if (!empty($formatter->use_group) and $formatter->group) {
      $group_pages = $indexer->getLikePages('^'.$formatter->group);
      foreach ($group_pages as $page)
        $all_pages[]=str_replace($formatter->group,'',$page);
    } else {
      $selected = '';
      if (!empty($sel)) $selected = $all_keys[$sel];
      $all_pages = $indexer->getLikePages('^'.$selected, 0);
    }

    #natcasesort($all_pages);
    #sort($all_pages,SORT_STRING);
    //usort($all_pages, 'strcasecmp');
    $pages = array_flip($all_pages);
    if (!empty($formatter->use_group)) {
        array_walk($pages,'_setpagekey');
    } else {
      if (PHP_VERSION_ID >= 50300) {
        array_walk($pages, function(&$p, $k) { $p = $k;});
      } else {
        array_walk($pages, create_function('&$p, $k', '$p = $k;'));
      }
    }
    $all_pages = array_flip($pages);
    uksort($all_pages, 'strcasecmp');
  }

  if (empty($keys) or empty($titleindex)) {
    $kc->update($index_lock, array('dummy'), 30); // 30 sec lock
    foreach ($all_pages as $page=>$rpage) {
      $p = ltrim($page);
      $pkey = get_key("$p");
      if ($key != $pkey) {
        $key = $pkey;
        //$keys[] = $pkey;
        if (!isset($titleindex[$pkey]))
          $titleindex[$pkey] = array();
      }
      $titleindex[$pkey][$page] = $rpage;
    }

    $keys = array_keys($all_keys);
    if (!empty($tlink))
      $keys[]='all';

    if (!empty($formatter->use_group) and $formatter->group) {
      $kc->update('key.'.$formatter->group, $keys);
      $kc->update('titleindex.'.$formatter->group, $titleindex);
    } else {
      $kc->update('key', $keys);
      $kc->update('titleindex'.$sel, $titleindex);
    }

    if (isset($sel[0]) and isset($titleindex[$sel]))
      $all_pages = $titleindex[$sel];
    $kc->remove($index_lock);
  }

  $pnut = null;
  if (isset($sel[0]) and count($all_pages) > $pc) {
    $pages_number = intval(count($all_pages) / $pc);
    if (count($all_pages) % $pc)
      $pages_number++;

    $pages = array_keys($all_pages);
    $pages = array_splice($pages, ($pg - 1) * $pc, $pc);
    $selected = array();
    foreach ($pages as $p) {
        $selected[$p] = $all_pages[$p];
    }
    $pages = $selected;

    $pnut = get_pagelist($formatter, $pages_number,
      '?action=titleindex&amp;sec='.$sel.
      '&amp;p=', !empty($pg) ? $pg : 1);
  } else {
    $pages = &$all_pages;
  }
  //print count($all_pages);
  //exit;
  $out = '';
#  if ($DBInfo->use_titlecache)
#    $cache=new Cache_text('title');
  $key = '';
  foreach ($pages as $page=>$rpage) {
    $p=ltrim($page);
    $pkey=get_key("$p");
    if ($key != $pkey) {
       $key = $pkey;
       if (isset($sel[0]) and !preg_match('/^'.$sel.'/i',$pkey)) continue;
       if (!empty($out)) $out.="</ul></div>";
       $out.= "<a name='$key'></a><h3><a href='#top'>$key</a></h3>\n";
       $out.= '<div class="index-group">';
       $out.= "<ul>";
    }
    if (isset($sel[0]) and !preg_match('/^'.$sel.'/i',$pkey)) continue;
    #
#    if ($DBInfo->use_titlecache and $cache->exists($page))
#      $title=$cache->fetch($page);
#    else
      $title=get_title($rpage,$page);

    #$out.= '<li>' . $formatter->word_repl('"'.$page.'"',$title,'',0,0);
    $urlname=_urlencode($group.$rpage);
    $out.= '<li>' . $formatter->link_tag($urlname,'',_html_escape($title));
    $keyname=$DBInfo->pageToKeyname(urldecode($rpage));
    if (is_dir($DBInfo->upload_dir."/$keyname") or
        (!empty($DBInfo->use_hashed_upload_dir) and
        is_dir($DBInfo->upload_dir.'/'.get_hashed_prefix($keyname).$keyname)))
       $out.=' '.$formatter->link_tag($urlname,"?action=uploadedfiles",
         $formatter->icon['attach']);
    $out.="</li>\n";
  }
  $out.= "</ul></div>\n";
  if (!empty($pnut)) {
    $out.='<div>'. $pnut .'</div>'."\n";
  }

  $index='';
  $tlink='';
  if (isset($sel[0])) {
    $tlink=$formatter->link_url($formatter->page->urlname,'?action=titleindex&amp;sec=');
  }

  $index = array();
  foreach ($keys as $key) {
    $name = strval($key);
    $tag='#'.$key;
    $link=!empty($tlink) ? preg_replace('/sec=/','sec='._urlencode($key),$tlink):'';
    if ($name == 'Others') $name=_("Others");
    else if ($name == 'all') $name=_("Show all");
    $index[] = "<a href='$link$tag'>$name</a>";
  }
  $str = implode(' | ', $index);
  
  return "<center><a name='top'></a>$str</center>\n$out";
}


function macro_BR($formatter) {
  return "<br class='macro' />\n";
}

function macro_TableOfContents(&$formatter,$value="") {
 global $DBInfo;
 static $tocidx = 1; // FIXME

 $tocid = 'toc' . $tocidx;
 $head_num=1;
 $head_dep=0;
 $TOC='';
 $a0='</a>';$a1='';
 if (!empty($DBInfo->toc_options))
   $value=$DBInfo->toc_options.','.$value;
 $toctoggle=!empty($DBInfo->use_toctoggle) ? $DBInfo->use_toctoggle : '';
 $secdep = '';
 $prefix = '';
 $dot = '<span class="dot">.</span>';

 $attr = '';
 while($value) {
   list($arg,$value)=explode(',',$value,2);
   $key=strtok($arg,'=');
   if ($key=='title') {
     $title=strtok('');
   } else if ($key == 'align') {
     $val = strtok('');
     if (in_array($val, array('right', 'left')))
       $attr = ' '.$val;
   } else if ($key=='simple') {
     $simple=strtok('');
     if ($simple=='') $simple=1;
     if ($simple) {
       $a0='';$a1='</a>';
     }
   } else if ($key=='toggle') {
     $toctoggle=strtok('');
     if ($toctoggle=='') $toctoggle=1;
   } else if (is_numeric($arg) and $arg > 0 and $arg < 5) {
     $secdep=$arg;
   } else if ($arg) {
     $value=$value ? $arg.','.$value:$arg;
     break;
   }
 }

 if ($toctoggle) {
  $js=<<<EOS
<script type="text/javascript">
/*<![CDATA[*/
 if (window.showTocToggle) { showTocToggle('$tocid', '<span>[+]</span>','<span>[-]</span>'); }
/*]]>*/
</script>
EOS;
 }
 $TOC0="\n<div class='wikiToc$attr' id='" . $tocid . "'>";
 if (!isset($title)) $title = $formatter->macro_repl('GetText', "Contents");
 if ($title) {
  $TOC0.="<div class='toctitle'>
<h2 style='display:inline'>$title</h2>
</div>";
 }
 $TOC0.="<a name='toc' ></a><dl><dd><dl>\n";

 $formatter->toc=1;
 $baseurl='';
 if ($value and $DBInfo->hasPage($value)) {
   $p=$DBInfo->getPage($value);
   $body=$p->get_raw_body();
   $baseurl=$formatter->link_url(_urlencode($value));
   $formatter->page=&$p;
 } else {
   $body=$formatter->text;
 }

 // remove processor blocks
 $chunk = preg_split("/({{{
            (?:(?:[^{}]+|
            {[^{}]+}(?!})|
            (?<!{){{1,2}(?!{)|
            (?<!})}{1,2}(?!})|
            (?<=\\\\)[{}]{3}(?!}))|(?1)
            )++}}})/x", $body, -1, PREG_SPLIT_DELIM_CAPTURE);
 $sz = count($chunk);
 $k = 1;
 $body = '';
 foreach ($chunk as $c) {
   if ($k % 2) {
     $body.= $c;
   } else if (!strstr($c, "\n")) {
     $body.= $c;
   }
   $k++;
 }

 $formatter->nomacro = 1; // disable macros in headings
 $wordrule = $formatter->wordrule .= '|'.$formatter->footrule;
 $lines=explode("\n",$body);
 foreach ($lines as $line) {
   $line=preg_replace("/\n$/", "", $line); # strip \n
   preg_match("/^\s*(?<!=)(={1,$secdep})\s(#?)(.*)\s+\\1\s?$/",$line,$match);

   if (!$match) continue;

   $dep=strlen($match[1]);
   if ($dep > 4) $dep = 5;
   $head=str_replace("<","&lt;",$match[3]);
   # strip some basic wikitags
   # $formatter->baserepl,$head);
   #$head=preg_replace($formatter->baserule,"\\1",$head);
   # do not strip basic wikitags
   $head=preg_replace($formatter->baserule,$formatter->baserepl,$head);
   $head = preg_replace_callback("/(".$wordrule.")/",
        array(&$formatter, 'link_repl'), $head);
   if (!empty($simple))
     $head=strip_tags($head,'<b><i><img><sub><sup><del><tt><u><strong>');

   if (empty($depth_top)) { $depth_top=$dep; $depth=1; }
   else {
     $depth=$dep - $depth_top + 1;
     if ($depth <= 0) $depth=1;
   }

   $num="".$head_num;
   $odepth=$head_dep;
   $open="";
   $close="";

   if (!empty($match[2])) {
      # reset TOC numberings
      $dum=explode(".",$num);
      $i=sizeof($dum);
      for ($j=0;$j<$i;$j++) $dum[$j]=1;
      $dum[$i-1]=0;
      $num=join($dum,".");
      if ($prefix) $prefix++;
      else $prefix=1;
   }

   if ($odepth && ($depth > $odepth)) {
      $open.="<dd><dl>\n";
      $num.=".1";
   } else if ($odepth) {
      $dum=explode(".",$num);
      $i=sizeof($dum)-1;
      while ($depth < $odepth && $i > 0) {
         unset($dum[$i]);
         $i--;
         $odepth--;
         $close.="</dl></dd>\n";
      }
      $dum[$i]++;
      $num=join($dum,".");
   }
   $head_dep=$depth; # save old
   $head_num=$num;

   if ($baseurl)
     $TOC.=$close.$open."<dt><a href='$baseurl#s$prefix-$num'><span class='num'>$num$dot</span>$a0 $head $a1</dt>\n";
   else
     $TOC.=$close.$open."<dt><a id='toc$prefix-$num' href='#s$prefix-$num'><span class='num'>$num$dot</span>$a0 $head $a1</dt>\n";

  }
  $formatter->nomacro = 0; // restore

  $tocidx ++;
  if (isset($TOC[0])) {
     $close="";
     $depth=$head_dep;
     while ($depth>1) { $depth--;$close.="</dl></dd>\n"; };
     if (isset($js))
        $formatter->register_javascripts("<script type=\"text/javascript\" src=\"$DBInfo->url_prefix/local/toctoggle.js\"></script>");
     return $TOC0.$TOC.$close."</dl></dd></dl>\n</div>\n".$js;
  }
  else return "";
}

/**
 * Validate reqular expression
 *
 */
function validate_needle($needle) {
  $test = @preg_match("/($needle)/", 'ThIsIsAtEsT', $match);
  if ($test === false) return false;

  $test_count = 3;
  $ok_count = 0;
  while ($test !== false) {
    preg_match("/($needle)/", '', $match); // empty string
    if (!empty($match)) {
      return false;
    }

    for ($i = 0; $i < $test_count; $i++) {
      $str = _str_random(40);
      // random test needle
      preg_match("/($needle)/", substr($str, 0, 6), $match);
      if (!empty($match)) {
        $ok_count++;
      }
    }
    break;
  }

  // It is useless needle as it matches all pattern.
  if ($ok_count == $test_count)
    return false;
  return true;
}

function macro_TitleSearch($formatter="",$needle="",&$opts) {
  global $DBInfo;
  $type='o';

  $url=$formatter->link_url($formatter->page->urlname);
  $hneedle = _html_escape($needle);

  $msg = _("Go");
  $form="<form method='get' action='$url'>
      <input type='hidden' name='action' value='titlesearch' />
      <input name='value' size='30' value=\"$hneedle\" />
      <span class='button'><input type='submit' class='button' value='$msg' /></span>
      </form>";

  if (!isset($needle[0])) {
    $opts['msg'] = _("Use more specific text");
    if (!empty($opts['call'])) {
      $opts['form']=$form;
      return $opts;
    }
    return $form;
  }

  $opts['form'] = $form;
  $opts['msg'] = sprintf(_("Title search for \"%s\""), $hneedle);
  $cneedle=_preg_search_escape($needle);

  if (isset($opts['noexpr']))
    $needle = preg_quote($needle);
  else if (validate_needle($cneedle) === false) {
    $needle = preg_quote($needle);
  } else {
    // good expr
    $needle = $cneedle;
  }

  // return the exact page or all similar pages
  $noexact = true;
  if (isset($opts['noexact']))
    $noexact = $opts['noexact'];

  $limit = !empty($DBInfo->titlesearch_page_limit) ? $DBInfo->titlesearch_page_limit : 100;
  if (isset($opts['limit']))
    $limit = $opts['limit'];

  $indexer = $DBInfo->lazyLoad('titleindexer');
  $pages = $indexer->getLikePages($needle, $limit);

  $opts['all'] = $DBInfo->getCounter();
  if (empty($DBInfo->alias)) $DBInfo->initAlias();
  $alias = $DBInfo->alias->getAllPages();

  $pages = array_merge($pages, $alias);
  $hits=array();
  $exacts = array();

  if ($noexact) {
    // return all search results
    foreach ($pages as $page) {
      if (preg_match("/".$needle."/i", $page)) {
        $hits[]=$page;
      }
    }
  } else {
    // return exact pages
    foreach ($pages as $page) {
      if (preg_match("/^".$needle."$/i", $page)) {
        $hits[] = $page;
        $exacts[] = $page;
        if (empty($DBInfo->titlesearch_exact_all)) {
          $hits = $exacts;
          break;
        }
      }
    }
  }

  if (empty($hits) and empty($exacts)) {
    // simple title search by ignore spaces
    $needle2 = str_replace(' ', "[ ]*", $needle);
    $ws = preg_split("/([\x{AC00}-\x{D7F7}])/u", $needle2, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    $needle2 = implode("[ ]*", $ws);
    $hits = $indexer->getLikePages($needle2);
    foreach ($alias as $page) {
      if (preg_match("/".$needle2."/i", $page))
        $hits[]=$page;
    }
  }

  sort($hits);

  $idx=1;
  if (!empty($opts['linkto'])) $idx=10;
  $out='';
  foreach ($hits as $pagename) {
    $pagetext=_html_escape(urldecode($pagename));
    if (!empty($opts['linkto']))
      $out.= '<li>' . $formatter->link_to("$opts[linkto]$pagename",$pagetext,"tabindex='$idx'")."</li>\n";
    else
      $out.= '<li>' . $formatter->link_tag(_rawurlencode($pagename),"",$pagetext,"tabindex='$idx'")."</li>\n";
    $idx++;
  }

  if ($out) $out="<${type}l>$out</${type}l>\n";
  $opts['hits']= count($hits);
  if ($opts['hits']==1)
    $opts['value']=array_pop($hits);
  if (!empty($exacts)) $opts['exact'] = 1;
  if (!empty($opts['call'])) {
    $opts['out']=$out;
    return $opts;
  }
  return $out;
}

function macro_GoTo($formatter="",$value="") {
  $url=$formatter->link_url($formatter->page->urlname);
  $value = _html_escape($value);
  $msg = _("Go");
  return "<form method='get' action='$url'>
    <input type='hidden' name='action' value='goto' />
    <input name='value' size='30' value=\"$value\" />
    <span class='button'><input type='submit' class='button' value='$msg' /></span>
    </form>";
}

function processor_plain($formatter,$value, $options=array()) {
    // fix for compatible issue with 1.3.0
    if (is_array($value))
        $value = implode("\n", $value);

    if ($value[0]=='#' and $value[1]=='!')
        list($line,$value)=explode("\n",$value,2);

    $cls[] = 'wiki'; // {{{#!plain class-name
    # get parameters
    if (!empty($line)) {
        $line = substr($line,2);
        $tag = strtok($line,' ');
        $class = strtok(' ');
        $extra = strtok('');
        if ($tag != 'plain') {
            $extra = !empty($extra) ? $class.' '.$extra:$class;
            $class = $tag;
        }
        if (!empty($class)) $cls[]=$class;
    }
    $class = implode(' ',$cls);

    $pre=str_replace(array('&','<'), array("&amp;","&lt;"), $value);
    $pre=preg_replace("/&lt;(\/?)(ins|del)/","<\\1\\2",$pre); // XXX
    $out="<pre class='$class'>\n".$pre."</pre>";
    return $out;
}

function processor_php($formatter="",$value="") {
  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);
  if (substr($value,-1,1)=="\n") $value=substr($value,0,-1);
  $php=&$value;
  ob_start();
  highlight_string($php);
  $highlighted= ob_get_contents();
  ob_end_clean();
  $highlighted=preg_replace(array('@<font color="@','@</font>@'),
			array('<span style="color:','</span>'),
	$highlighted);
#  $highlighted=preg_replace("/<code>/","<code style='background-color:#c0c0c0;'>",$highlighted);
#  $highlighted=preg_replace("/<\/?code>/","",$highlighted);
#  $highlighted="<pre style='color:white;background-color:black;'>".
#               $highlighted."\n</pre>";
  return '<div class="wikiSyntax">'.$highlighted.'</div>';
}

// vim:et:sts=4:sw=4:
