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

function get_scriptname() {
  // Return full URL of current page.
  // $_SERVER["SCRIPT_NAME"] has bad value under CGI mode
  // set 'cgi.fix_pathinfo=1' in the php.ini under
  // apache 2.0.x + php4.2.x Win32
  // check mod_rewrite
  if (isset($_SERVER['REDIRECT_URL']) and
      strpos($_SERVER['REQUEST_URI'],$_SERVER['SCRIPT_NAME'])===false) {
    return preg_replace('@/[^/]+\.php@','',$_SERVER['SCRIPT_NAME']);
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
  return preg_replace(array("@<(?=/?\s*\w+[^<>]*)@", '@"@'), array("&lt;", '&quot;'), $string);
}

function _rawurlencode($url) {
  $name=rawurlencode($url);
  $urlname = str_replace(array('%2F', '%7E', '%3A'), array('/', '~', ':'), $name);
  $urlname= preg_replace('#:+#',':',$urlname);
  return $urlname;
}

function _urlencode($url) {
  $url= preg_replace('#:+#',':',$url);
  $url = str_replace('%20', ' ', $url);
  return str_replace(array('%23', '%26', '%2F', '%3A', '%3B', '%3D', '%3F'),
            array('#', '&', '/', ':', ';', '=', '?'), rawurlencode($url));
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
    if ($if_none_match && $if_none_match != $etag) {
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

function is_mobile() {
  global $DBInfo;

  if (!empty($DBInfo->mobile_agents)) {
    $re = '/'.$DBInfo->mobile_agents.'/i';
  } else {
    $re = '/android|iphone/i';
  }
  if (preg_match($re, $_SERVER['HTTP_USER_AGENT']))
    return true;
  return false;
}

/**
 * Get the real IP address for proxy
 *
 * @author   Won-Kyu Park <wkpark@gmail.com>
 */
function realIP() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if (!empty($_SERVER['HTTP_X_REAL_IP']))
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    else
        return $_SERVER['REMOTE_ADDR'];

    if (strpos($ip, ',') === false)
        return $ip;

    $ip = explode(',', str_replace(' ', '', $ip));
    // FIXME
    $ip = array_reverse($ip);
    return $ip[0];
}

/**
 * get default cols of textarea
 *
 */
function get_textarea_cols() {
  $COLS_MSIE = 80;
  $COLS_OTHER = 85;

  if (preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT'])) {
    $cols = $COLS_MSIE;
  } else if (is_mobile()) {
    $cols = 30;
  } else {
    $cols = $COLS_OTHER;
  }
  return $cols;
}

function _fake_lock_file($tmp, $arena, $tag = '') {
    $lock = $tmp . '/' . $arena;
    if (!empty($tag))
        $lock.= $tag;
    return $lock . '.lock';
}

function _fake_locked($lockfile, $mtime = 0, $delay = 1800) {
    // prevent not to execute sum job again
    $locked = file_exists($lockfile);
    // is this lock file too old ?
    if ($locked and $mtime > filemtime($lockfile) + $delay) {
        @unlink($lockfile);
        $locked = false;
    }
    return $locked;
}

function _fake_lock($lockfile, $lock = LOCK_EX) {
    if ($lock == LOCK_EX)
        touch($lockfile);
    else
        @unlink($lockfile);
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
  global $DBInfo;
  # make the site specific ticket based on the variables in the config.php
  $configs=getConfig("config.php");
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

function isRobot($name) {
  global $Config;
  if (preg_match('/'.$Config['robots'].'/i',$name))
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
  function UserDB($WikiDB) {
    $this->user_dir=$WikiDB->user_dir;
    $this->strict = $WikiDB->login_strict;
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

  function getUserList($option='') {
    if ($this->users) return $this->users;

    $type='';
    if ($option=='del') $type='del-';
    elseif ($options=='wait') $type='wait-';

    $users = array();
    $handle = opendir($this->user_dir);
    while ($file = readdir($handle)) {
      if (is_dir($this->user_dir."/".$file)) continue;
      if (preg_match('/^'.$type.'wu\-([^\.]+)$/', $file,$match))
        #$users[$match[1]] = 1;
        $users[] = $this->_key_to_id($match[1]);
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
    if ($this->_exists($user->id))
      return false;
    $this->saveUser($user, $options);
    return true;
  }

  function isNotUser($user) {
    if ($this->_exists($user->id))
      return false;
    return true;
  }

  function saveUser($user,$options=array()) {
    $config=array("css_url","datatime_fmt","email","bookmark","language","home",
                  "name","nick","password","wikiname_add_spaces","subscribed_pages",
                  "scrapped_pages","quicklinks","theme","ticket","eticket",
	  	  "tz_offset","npassword","nticket","idtype");

    $date=gmdate('Y/m/d H:i:s', time());
    $data="# Data saved $date\n";

    if (!empty($user->ticket))
      $user->info['ticket']=$user->ticket;

    foreach ($config as $key) {
      if (isset($user->info[$key]))
        $data.="$key=".$user->info[$key]."\n";
    }
    #print $data;

    $wu="wu-".$this->_id_to_key($user->id);
    if (!empty($options['suspended'])) $wu='wait-'.$wu;
    $fp=fopen("$this->user_dir/$wu","w+");
    fwrite($fp,$data);
    fclose($fp);
  }

  function _exists($id, $suspended = false) {
    $prefix = $suspended ? 'wait-wu-' : 'wu-';
    if (file_exists("$this->user_dir/$prefix" . $this->_id_to_key($id)))
      return true;
    return false;
  }

  function checkUser(&$user) {
    $tmp=$this->getUser($user->id);
    if (!empty($tmp->info['ticket']) and $tmp->info['ticket'] != $user->ticket) {
      if ($this->strict > 0)
        $user->id='Anonymous';
      return 1;
    }
    $user=$tmp;
    return 0;
  }

  function getUser($id, $suspended = false) {
    $prefix = $suspended ? 'wait-wu-' : 'wu-';
    if ($this->_exists($id, $suspended)) {
       $data=file("$this->user_dir/$prefix" . $this->_id_to_key($id));
    } else {
       $user=new WikiUser('Anonymous');
       return $user;
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
    $user=new WikiUser($id);
    $user->info=$info;
    return $user;
  }

  function delUser($id) {
    if ($this->_exists($id)) {
      $u='wu-'. $this->_id_to_key($id);
      $du='del-'.$u;
      rename($this->user_dir.'/'.$u,$this->user_dir.'/'.$du);
    }
  }

  function activateUser($id) {
    $wu='wu-'. $this->_id_to_key($id);
    if (file_exists($this->user_dir.'/'.$wu)) return true;
    if (file_exists($this->user_dir.'/wait-'.$wu)) {
      $u='wait-'.$wu;
      rename($this->user_dir.'/'.$u,$this->user_dir.'/'.$wu);
      return true;
    }
    if (file_exists($this->user_dir.'/del-'.$wu)) {
      $u='del-'.$wu;
      rename($this->user_dir.'/'.$u,$this->user_dir.'/'.$wu);
      return true;
    }
    return false;
  }
}

class WikiUser {
  var $cookie_expires = 2592000; // 60 * 60 * 24 * 30; // default 30 days

  function WikiUser($id="") {
     global $Config;

     if (!empty($Config['cookie_expires']))
        $this->cookie_expires = $Config['cookie_expires'];

     if ($id) {
        $this->setID($id);
        return;
     }
     $id = '';
     if (isset($_COOKIE['MONI_ID'])) {
     	$this->ticket=substr($_COOKIE['MONI_ID'],0,32);
     	$id=urldecode(substr($_COOKIE['MONI_ID'],33));
     }
     $this->setID($id);

     $this->css=isset($_COOKIE['MONI_CSS']) ? $_COOKIE['MONI_CSS']:'';
     $this->theme=isset($_COOKIE['MONI_THEME']) ? $_COOKIE['MONI_THEME']:'';
     $this->bookmark=isset($_COOKIE['MONI_BOOKMARK']) ? $_COOKIE['MONI_BOOKMARK']:'';
     $this->trail=isset($_COOKIE['MONI_TRAIL']) ? _stripslashes($_COOKIE['MONI_TRAIL']):'';
     $this->tz_offset=isset($_COOKIE['MONI_TZ']) ?_stripslashes($_COOKIE['MONI_TZ']):'';
     $this->nick=isset($_COOKIE['MONI_NICK']) ?_stripslashes($_COOKIE['MONI_NICK']):'';
     $this->verified_email = isset($_COOKIE['MONI_VERIFIED_EMAIL']) ? _stripslashes($_COOKIE['MONI_VERIFIED_EMAIL']) : '';
     if ($this->tz_offset =='') $this->tz_offset=date('Z');
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
     if (strpos($name," ")) {
        $dum=explode(" ",$name);
        $new=array_map("ucfirst",$dum);
        return join($new,"");
     }
     return $name;
  }

  function setCookie() {
     if ($this->id == "Anonymous") return false;
     $ticket=getTicket($this->id,$_SERVER['REMOTE_ADDR']);
     $this->ticket=$ticket;
     # set the fake cookie
     $_COOKIE['MONI_ID']=$ticket.'.'.urlencode($this->id);
     if (!empty($this->info['nick'])) $_COOKIE['MONI_NICK']=$this->info['nick'];

     #$path=strpos($_SERVER['HTTP_USER_AGENT'],'Safari')===false ?
     #  get_scriptname():'/';
     $path = get_scriptname();
     #$path = preg_replace('@(?<=/)[^/]+$@','',$path);
     return "Set-Cookie: MONI_ID=".$ticket.'.'.urlencode($this->id).
            '; expires='.gmdate('l, d-M-Y H:i:s', time() + $this->cookie_expires).' GMT; Path='.$path;
  }

  function unsetCookie() {
     # set the fake cookie
     $_COOKIE['MONI_ID']="Anonymous";

     # check safari
     #$path=strpos($_SERVER['HTTP_USER_AGENT'],'Safari')===false ?
     #  get_scriptname():'/';
     $path = get_scriptname();
     #$path = preg_replace('@(?<=/)[^/]+$@','',$path);
     return "Set-Cookie: MONI_ID=".$this->id."; expires=Tuesday, 01-Jan-1999 12:00:00 GMT; Path=".$path;
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
    if (!$this->info['email'] or !$this->info['subscribed_pages']) return false;
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
#mycontent button.save-button { display: none; }
#mycontent button.preview-button { display: none; }
button.save-button { display: none; }
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
  return $css.$js.'<div id="all-forms">'.$form.'</div>'.$tmpls;
}

function do_edit($formatter,$options) {
  global $DBInfo;
  if (!$DBInfo->security->writable($options)) {
    $formatter->preview=0;
    $options['err']="#format wiki\n== "._("You are not allowed to edit this page !").' =='; # XXX
    return do_invalid($formatter,$options);
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
  $cols= get_textarea_cols();

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
    $raw_body.= $guide;
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
    $categories = $DBInfo->getLikePages($DBInfo->category_regex);
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
    $preview_btn='<span class="button"><input type="submit" class="button" tabindex="6" name="button_preview" class="preview-button" value="'.
      _("Preview").'" /></span>';
    $changes_btn = '';
    if ($formatter->page->exists())
      $changes_btn=' <span class="button"><input type="submit" class="button" tabindex="6" name="button_changes" class="preview-button" value="'.
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
<span id='edit-summary'><label for='input-summary'>$summary_msg</label><input name="comment" id='input-summary' value="$editlog" size="60" maxlength="128" tabindex="2" />$extra_check</span>
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
 rows="$rows" cols="$cols" class="wiki resizable">$raw_body</textarea>
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

  if ($options['action_mode'] == 'ajax') {
    return ajax_invalid($formatter,$options);
  }

  $formatter->send_header("Status: 406 Not Acceptable",$options);
  if (!empty($options['title']))
    $formatter->send_title('',"",$options);
  else
    $formatter->send_title(_("406 Not Acceptable"),"",$options);
  if (!empty($options['err'])) {
    $formatter->send_page($options['err']);
  } else {
    if (!empty($options['action']))
      $formatter->send_page("== ".sprintf(_("%s is not valid action"),$options['action'])." ==\n");
    else
      $formatter->send_page("== "._("Is it valid action ?")." ==\n");
  }

  $formatter->send_footer("",$options);
  return false;
}

function ajax_invalid($formatter,$options) {
  if (!empty($options['call'])) return false;
  $formatter->send_header(array("Content-Type: text/plain",
			"Status: 406 Not Acceptable"),$options);
  print "false\n";
  return false;
}

function do_post_DeleteFile($formatter,$options) {
  global $DBInfo;

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
  
  $page = $DBInfo->getPage($options['page']);

  if (!$page->exists()) {
    $formatter->send_header('', $options);
    $title = _("Page not found.");
    $formatter->send_title($title, '',$options);
    $formatter->send_footer('', $options);
    return;
  }

  if (isset($options['name'][0])) $options['name']=urldecode($options['name']);
  $pagename= $formatter->page->urlname;
  if (isset($options['name'][0]) and $options['name'] == $options['page']) {
    $retval = array();
    $options['retval'] = &$retval;
    $ret = $DBInfo->deletePage($page,$options);
    if ($ret == -1) {
      if (!empty($options['retval']['msg']))
        $title = $options['retval']['msg'];
      else
        $title = sprintf(_("Fail to delete \"%s\""), _html_escape($page->name));
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
  print "<form method='post'>
$btn: <input name='comment' size='80' value='' /><br />\n";
  if (!empty($DBInfo->delete_history))
    print _("with revision history")." <input type='checkbox' name='history' />\n";

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
     if ($options['redirect'])
       $url=$formatter->link_url($url,"?action=show&amp;redirect=".
          str_replace('+', '%2B', $formatter->page->urlname).$anchor);
     else
       $url=$formatter->link_url($url,"");
     $url = preg_replace('/[[:cntrl:]]/', ' ', $url); // tr control chars

     # FastCGI/PHP does not accept multiple header infos. XXX
     #$formatter->send_header("Location: ".$url,$options);
     $url = preg_replace('/&amp;/', '&', $url);
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

    $val='';
    $rule='';
    while ($DBInfo->use_hangul_search) {
      include_once("lib/unicode.php");
      $val=$options['q'];
      if (strtoupper($DBInfo->charset) != 'UTF-8' and function_exists('iconv')) {
        $val=iconv($DBInfo->charset,'UTF-8',$options['q']);
      }
      if (!$val) break;
        
      $rule=utf8_hangul_getSearchRule($val);

      $test=@preg_match("/^$rule/",'');
      if ($test === false) $rule=$options['q'];
      break;     
    }
    if (!$rule) $rule=trim($options['q']);

    $test = validate_needle('^'.$rule);
    if (!$test)
      $rule = preg_quote($rule);

    $indexer = $DBInfo->lazyLoad('titleindexer');
    $pages = $indexer->getLikePages($rule);

    sort($pages);
    //array_unshift($pages, $options['q']);
    header("Content-Type: text/plain");
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
    // all pages
    $mtime = $DBInfo->mtime();
    $lastmod = gmdate('D, d M Y H:i:s \G\M\T', $mtime);
    $etag = md5($mtime.$DBInfo->etag_seed);
    $options['etag'] = $etag;
    $options['mtime'] = $mtime;

    // set the s-maxage for proxy
    $proxy_maxage = !empty($Config['proxy_maxage']) ? ', s-maxage='.$Config['proxy_maxage'] : '';
    $header[] = 'Content-Type: text/plain';
    $header[] = 'Cache-Control: public'.$proxy_maxage.', max-age=0, must-revalidate';
    $need = http_need_cond_request($mtime, $lastmod, $etag);
    if (!$need)
      $header[] = 'HTTP/1.0 304 Not Modified';
    $formatter->send_header($header, $options);
    if (!$need) {
      @ob_end_clean();
      return;
    }
    $args = array('all'=>1);
    $pages = $DBInfo->getPageLists($args);

    sort($pages);

    print join("\n",$pages);
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
  if (!$DBInfo->security->writable($options)) {
    return do_invalid($formatter,$options);
  }

  if ((isset($_FILES['upfile']) and is_array($_FILES)) or
      (isset($options['MYFILES']) and is_array($options['MYFILES']))) {
    $retstr = false;
    $options['retval'] = &$retstr;
    include_once('plugin/UploadFile.php');
    do_uploadfile($formatter, $options);
  }

  $savetext=$options['savetext'];
  $datestamp=$options['datestamp'];
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
    }
  }
  $formatter->page->set_raw_body($savetext);

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
    if ($button_diff) {
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
    if (!empty($options['category']))
      $savetext.="----\n$options[category]\n";

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

      if ($DBInfo->use_save_refresh > 0) {
        $sec=$DBInfo->use_save_refresh - 1;
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

  $ret=mail($mailto,$subject,$body,$mailheaders,'-fnoreply');

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

function verify_email($email, $timeout = 5) {
    list($name, $domain) = explode('@', $email);
    $result = getmxrr($domain, $mxhosts);
    if (!$result) $mxhosts[0] = $domain;

    $ret = -1;
    foreach ($mxhosts as $mxhost) {
        $sock = @fsockopen($mxhost, 25, $errno, $errstr, $timeout);
        if (is_resource($sock)) break;
    }
    if (!is_resource($sock)) return $ret;

    if (preg_match("/^220/", $out = fgets($sock, 1024))) {
        fwrite($sock, "HELO 127.0.0.1\r\n");
        $out = fgets($sock, 1024);
        fwrite($sock, "MAIL FROM: <apache@localhost.localdomain>\r\n");
        $from = fgets($sock, 1024);
        list($code, $msg) = explode(' ', $from, 2);
        if ($code == '553') {
            fwrite($sock, "MAIL FROM: <".$email.">\r\n");
            $from = fgets($sock, 1024);
            list($code, $msg) = explode(' ', $from, 2);
        }
        fwrite($sock, "RCPT TO: <".$email.">\r\n");
        $to = fgets($sock, 1024);
        list($code, $msg) = explode(' ', $to, 2);
        fwrite($sock, "QUIT\r\n");
        fclose($sock);

        if ($code == '250') $ret = 0;
    }
    return $ret;
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

  if (!empty($DBInfo->sendmail_path)) {
    $header = "To: $email\n".
              "Subject: $subject\n";

    $handle = popen($DBInfo->sendmail_path, 'w');
    if (is_resource($handle)) {
      fwrite($handle, $header.$mailheaders.$body);
      fclose($handle);
    }
  } else {
    mail($email,$subject,$body,$mailheaders,'-fnoreply');
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
   var msg = HTTPGet("$url");
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
  $url=$formatter->link_url($formatter->page->urlname);
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

  if ($user->id == 'Anonymous') {
    if (isset($options['login_id']) or !empty($_GET['join']) or $value!="simple") {
      $passwd=!empty($options['password']) ? $options['password'] : '';
      $button=_("Make profile");
      if ($joinagree and empty($DBInfo->use_safelogin)) {
        $again="<b>"._("password again")."</b>&nbsp;<input type='password' size='15' maxlength='$pw_length' name='passwordagain' value='' /></td></tr>";
      }
      $mailbtn=_("Mail");
      if (empty($options['agreement']) or !empty($options['joinagreement']))
      $extra=<<<EXTRA
  <tr><th>$mailbtn&nbsp;</th><td><input type="text" size="40" name="email" value="" /></td></tr>
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
    } else {
      $button=_("Login or Join");
    }
  } else {
    $button=_("Save");
    $css=!empty($user->info['css_url']) ? $user->info['css_url'] : '';
    $css = _html_escape($css);
    $email=!empty($user->info['email']) ? $user->info['email'] : '';
    $email = _html_escape($email);
    $nick=!empty($user->info['nick']) ? $user->info['nick'] : '';
    $nick = _html_escape($nick);
    $tz_offset=!empty($user->info['tz_offset']) ? $user->info['tz_offset'] : 0;
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
  <tr><th>$email_btn&nbsp;</th><td><input type="text" size="40" name="email" value="$email" /></td></tr>
  <tr><th>$tz_btn&nbsp;</th><td><select name="timezone">
  $opts
  </select> <span class='button'><input type='button' class='button' value='Local timezone' onclick='javascript:setTimezone()' /></span></td></tr>
  <tr><td><b>CSS URL </b>&nbsp;</td><td><input type="text" size="40" name="user_css" value="$css" /><br />("None" for disabling CSS)</td></tr>
EXTRA;
    $logout="<span class='button'><input type='submit' class='button' name='logout' value='"._("logout")."' /></span> &nbsp;";
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
  if ($button==_("Make profile")) {
    if (empty($options['agreement']) and !empty($DBInfo->use_sendmail)) {
      $button2=_("E-mail new password");
      $emailpasswd=
        "<span class='button'><input type=\"submit\" class='button' name=\"login\" value=\"$button2\" /></span>\n";

      if (!empty($DBInfo->anonymous_friendly)) {
        $verifiedemail = isset($options['verifyemail']) ? $options['verifyemail'] :
                        (isset($user->verified_email) ? $user->verified_email : '');
        $button3 =_("Verify E-mail address");
        $button4 =_("Remove");
        $remove = '';
        if ($verifiedemail)
            $remove = "<span class='button'><input type='submit' class='button' name='emailreset' value='$button4' /></span>";
        $emailverify = <<<EOF
          $sep
          <tr><th>$mailbtn&nbsp;</th><td><input type='text' size='40' name='verifyemail' value="$verifiedemail" /></td></tr>
          <tr><td></td><td>
          <span class='button'><input type="submit" class='button' name="verify" value="$button3" /></span>
          $remove
          </td></tr>
EOF;
      }
    } else if (!empty($DBInfo->use_agreement) and empty($options['joinagreement'])) {
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
      if (!empty($DBInfo->agreement_comment)) {
        // show join agreement confirm message
        $form.= '<div class="join-agreement">';
        $form.= str_replace("\n", "<br />", $DBInfo->agreement_comment);
        $form.= "</div>\n";
      } else if (!empty($DBInfo->agreement_page) and file_exists($DBInfo->agreement_page)) {
        // show join agreement confirm message from a external text file
        $form.= '<div class="join-agreement">';
        $tmp = file_get_contents($DBInfo->agreement_page);
        $form.= str_replace("\n", "<br />", $tmp);
        $form.= "</div>\n";
      }
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
  $id_btn=_("ID");
  $sep1 = '';
  if (!empty($openid_form) or !empty($login)) $sep1=$sep;
  return <<<EOF
$login
$jscript
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
}

function macro_InterWiki($formatter,$value,$options=array()) {
  global $DBInfo;

  while (!isset($DBInfo->interwiki) or !empty($options['init'])) {
    $cf = new Cache_text('settings', array('depth'=>0));

    $force_init=0;
    if (!empty($DBInfo->shared_intermap) and file_exists($DBInfo->shared_intermap)
        and $cf->mtime('interwiki') < filemtime($DBInfo->shared_intermap) ) {
      $force_init=1;
    }
    if (!empty($formatter->refresh) and $cf->exists('interwiki') and !$force_init) {
      $info = $cf->fetch('interwiki');
      $DBInfo->interwiki=$info['interwiki'];
      $DBInfo->interwikirule=$info['interwikirule'];
      $DBInfo->intericon=$info['intericon'];
      break;
    }

    $interwiki=array();
    # intitialize interwiki map
    $map=file($DBInfo->intermap);
    if (!empty($DBInfo->sistermap) and file_exists($DBInfo->sistermap))
      $map=array_merge($map,file($DBInfo->sistermap));

    # read shared intermap
    if (file_exists($DBInfo->shared_intermap))
      $map=array_merge($map,file($DBInfo->shared_intermap));

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
    $cf->update('interwiki',$interinfo);
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

/**
 * Count pages and redirect pages
 */
function macro_PageCount($formatter, $value = '', $options = array()) {
  global $DBInfo;

  if ($formatter->_macrocache and empty($options['call']))
    return $formatter->macro_cache_repl('PageCount', '');
  $formatter->_dynamic_macros['@PageCount'] = 1;

  $mode = '';
  if (!empty($value)) {
    $vals = get_csv($value);
    if (!empty($vals)) {
      foreach ($vals as $v) {
        if (in_array($v, array('noredirect', 'redirect'))) {
          $mode = $v;
        }
      }
    }
  }

  $redirects = 0;
  if (!empty($mode)) {
    $rc = new Cache_Text('redirect');
    $redirects = 0;
    if (method_exists($rc, 'count'))
      $redirects = $rc->count();

    if ($mode == 'redirect')
      return $redirects;
  }
  $count = $DBInfo->getCounter();
  return $count - $redirects;
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

  $lock_file = _fake_lock_file($DBInfo->vartmp_dir, 'titleindex');
  $locked = _fake_locked($lock_file, $DBInfo->mtime());
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

  if (empty($all_pages)) {

    $all_pages = array();
    $indexer = $DBInfo->lazyLoad('titleindexer');
    if (!empty($formatter->use_group) and $formatter->group) {
      $group_pages = $indexer->getLikePages('^'.$formatter->group);
      foreach ($group_pages as $page)
        $all_pages[]=str_replace($formatter->group,'',$page);
    } else
      $all_pages = $indexer->getLikePages('^'.$all_keys[$sel], 0);

    #natcasesort($all_pages);
    #sort($all_pages,SORT_STRING);
    //usort($all_pages, 'strcasecmp');
    $pages = array_flip($all_pages);
    if (!empty($formatter->use_group)) {
        array_walk($pages,'_setpagekey');
    } else {
        array_walk($pages, create_function('&$p, $k', '$p = $k;'));
    }
    $all_pages = array_flip($pages);
    uksort($all_pages, 'strcasecmp');
  }

  if (empty($keys) or empty($titleindex)) {
    _fake_lock($lock_file);
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
    _fake_lock($lock_file, LOCK_UN);
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
       if (!empty($out)) $out.="</ul>";
       $out.= "<a name='$key'></a><h3><a href='#top'>$key</a></h3>\n";
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
  if (!empty($pnut)) {
    $out.='<li style="list-style:none">'. $pnut .'</li>'."\n";
  }
  $out.= "</ul>\n";

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

 while($value) {
   list($arg,$value)=explode(',',$value,2);
   $key=strtok($arg,'=');
   if ($key=='title') {
     $title=strtok('');
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
 if (window.showTocToggle) { showTocToggle('$tocid', '<img src="$DBInfo->imgs_dir/plugin/arrdown.png" width="10px" border="0" alt="[+]" title="[+]" />','<img src="$DBInfo->imgs_dir/plugin/arrup.png" width="10px" border="0" alt="[-]" title="[-]" />'); } 
/*]]>*/
</script>
EOS;
 }
 $TOC0="\n<div class='wikiToc' id='" . $tocid . "'>";
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
  $needle = _preg_search_escape($needle);
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

  if ($opts['noexpr'])
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

  $indexer = $DBInfo->lazyLoad('titleindexer');
  $pages = $indexer->getLikePages($needle);

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
    $needle2 = str_replace(' ', "\\s*", $needle);
    $ws = preg_split("/([\x{AC00}-\x{D7F7}])/u", $needle2, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    $needle2 = implode("\\s*", $ws);
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
