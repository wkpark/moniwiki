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
    if ($encode !== false) {
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
 * Setup default Cache-Control headers.
 *
 * @since   2015-12-22
 * @since   1.3.0
 * @return  void
 */
function http_default_cache_control($options = array()) {
    global $Config;

    // set the s-maxage for proxy
    $proxy_maxage = !empty($Config['proxy_maxage']) ? ', s-maxage='.$Config['proxy_maxage'] : '';
    // set maxage
    $user_maxage = !empty($Config['user_maxage']) ? ', max-age='.$Config['user_maxage'] : ', max-age=0';

    if ($_SERVER['REQUEST_METHOD'] != 'GET' and
            $_SERVER['REQUEST_METHOD'] != 'HEAD') {
        // always set private for POST
        // basic cache-control
        header('Cache-Control: private, max-age=0, s-maxage=0, must-revalidate, post-check=0, pre-check=0');
        if (!empty($_SERVER['HTTP_ORIGIN'])) {
            if (!empty($Config['access_control_allowed_re'])) {
                if (preg_match($Config['access_control_allowed_re'], $_SERVER['HTTP_ORIGIN']))
                    header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
            } else {
                header('Access-Control-Allow-Origin: *');
            }
        }
    } else {
        // set maxage for show action
        $act = isset($_GET['action']) ? strtolower($_GET['action']) : '';
        if (empty($act) or $act == 'show')
            $maxage = $proxy_maxage.$user_maxage;
        else
            $maxage = $user_maxage;

        if (empty($Config['no_must_revalidate']))
            $maxage.= ', must-revalidate';

        // set public or private for GET, HEAD
        // basic cache-control. will be overrided later
        if (isset($options['id']) && $options['id'] == 'Anonymous')
            $public = 'public';
        else
            $public = 'private';
        header('Cache-Control: '.$public.$maxage.', post-check=0, pre-check=0');
    }
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

    if (sizeof($chunks) > 2) {
        // get the first == blah blah == section
        $raw = $chunks[2][0];
    }

    $lines = explode("\n", $raw);

    // trash PIs
    for ($i = 0; $i < sizeof($lines); $i++) {
        if ($lines[$i][0] == '#')
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
  if ($DBInfo->use_wikiwyg>=2) {
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

  if (!$options['notmpl'] and (!empty($options['template']) or !$formatter->page->exists()) and !$preview) {
    $options['linkto']="?action=edit&amp;template=";
    $options['limit'] = -1;
    $tmpls= $formatter->macro_repl('TitleSearch', $DBInfo->template_regex, $options);
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

function do_invalid($formatter, $options) {
    global $DBInfo, $Config;

    if ($options['action_mode'] == 'ajax') {
        return ajax_invalid($formatter,$options);
    }

    if ($options['action'] == 'notfound' && isset($formatter) && !$formatter->page->exists()) {
        $header = 'Status: 404 Not found';
        $msg = _("404 Not found");
    } else {
        $header = 'Status: 406 Not Acceptable';
        $msg = sprintf(_("You are not allowed to '%s'"), $options['action']);
        if ($options['allowed'] === false)
            $msg = sprintf(_("%s action is not found."), $options['action']);
    }

    if (!isset($formatter)) {
        if (!empty($Config['nofancy_406'])) {
            header($header);
            if (!empty($Config['html_406']) && file_exists($Config['html_406'])) {
                readfile($Config['html_406']);
                return true;
            }
            $msg = _html_escape($msg);
            echo '<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">';
            echo '<html><head><title>'.$Config['sitename'].'</title>';
            echo "<meta name='viewport' content='width=device-width' />";
            echo '</head><body>';
            echo '<h1>500 Internal Server Error</h1>';
            echo '<div>',$msg,'</div>';
            echo '</body></html>';
            return true;
        } else {
            $page = $DBInfo->getPage($options['page']);
            $formatter = new Formatter($page, $options);
        }
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
        echo call_user_func(array($DBInfo->security, $options['help']), $formatter, $options);
        echo "</div>\n";
    }

    $formatter->send_footer("",$options);
    return true;
}

function ajax_invalid($formatter,$options) {
    if ($options['action'] == 'notfound' && isset($formatter) && !$formatter->page->exists()) {
        $header = 'Status: 404 Not found';
    } else {
        $header = 'Status: 406 Not Acceptable';
    }

    if (!empty($options['call'])) return false;
    if (isset($formatter))
        $formatter->send_header(array("Content-Type: text/plain",
                    $header),$options);
    else
        header($header);

    echo "false\n";
    return false;
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
     if ($options['redirect'])
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
    if (preg_match("/(\r|\r\n|\n)$/", $body, $match))
      $crlf = $match[1];
    // count crlf
    $nline = substr_count($body, $crlf);

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

  $from = $options['from'] ? $options['from']:$return;

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
       #$uni1=((ord($name[0]) & 0x0f) <<4) | ((ord($name[1]) & 0x7f) >>2);
       $uni1=((ord($name[0]) & 0x0f) <<4) | (($name[1] & 0x7f) >>2);
       $uni2=((ord($name[1]) & 0x7f) <<6) | (ord($name[2]) & 0x7f);

       $uni=($uni1<<8)+$uni2;
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
var txt = HTTPGet(url);
var ret = window["eval"]("(" + txt + ")");
var rc = document.getElementById("macro-$mid");
var out = ret['pagecount'];
if (mode == 'noredirect')
    out -= ret['redirect'];
else if (mode == 'redirect')
    out = ret['redirect'];
rc.innerHTML = out;
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
?>
