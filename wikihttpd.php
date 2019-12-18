<?php
//
//  MoniWiki Web Server by Won-kyu Park <wkpark at kldp.org>
//
//  * phpserv is a PhpLanguage based webserver
//    Copyright (C) 2002 Daniel Lorch <daniel at lorch.cc>
//
//  This program is free software; you can redistribute it and/or modify
//  it under the terms of the GNU General Public License as published by
//  the Free Software Foundation; either version 2 of the License, or
//  (at your option) any later version.
//
//  This program is distributed in the hope that it will be useful,
//  but WITHOUT ANY WARRANTY; without even the implied warranty of
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//  GNU General Public License for more details.
//
//  You should have received a copy of the GNU General Public License
//  along with this program; if not, write to the Free Software
//  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
//

/*
 *  Implementation of the HTT-protocol (also known as Webserver)
 *
 *  This is a quite simple webserver. It can serve static files but
 *  nothing more. I carefully check every request to not serve
 *  files which are outside the document root and I also try to be
 *  as "RFC-ish" as possible. For example I send back the correct
 *  MIME-type (according to the file extension), but there is not
 *  yet a support for multiple, succeding requests in one connection
 *  (Connection: Keep-Alive). Also file-resuming is not yet
 *  implemented.
 *
 */

if (!empty($_SERVER['SERVER_SOFTWARE']) || !isset($argv) || $argv[0] != 'wikihttpd.php') {
  echo '<html><head></head><body><h2>Invalid request</h2></body></html>';
  exit;
}

class simple_server {
  var $runing=false;
  var $document_root;
  var $directory_index='index.html';
  var $servername = "MoniWiki/1.1 Server";
  var $wiki_prefix = "/wiki/";

  function __construct($address=0,$port=8080,$root='htdocs') {
    @set_time_limit(0);
    $this->document_root=$root;

    if($this->pre_init() === false)
      return false;

    if(($this->socket = socket_create(AF_INET, SOCK_STREAM, 0)) < 0)
      return false;      
      
    if(@socket_bind($this->socket, $address, $port) < 0)
      return false;

    if(@socket_listen($this->socket, 5)< 0)
      return false;

#    $this->error_handle = @fopen($this->error_file, "a");
#    if(!$this->error_handle) 
#      return false;

#    $this->log_handle = @fopen($this->log_file, "a");
#    if(!$this->log_handle)
#      return false;

    if($this->post_init() === false)
      return false;

    register_shutdown_function(array(&$this,'close'));
    return $this->running = true;
  }

  /* public: this is the absolutely first function called in the constructor.
             be careful! you still have superuser privileges at this stage  */
  function pre_init() {
    return true;
  }

  /* public: this function is called after everything is set up
             (server has bind it's port etc..) */
  function post_init() {
    return true;
  }

  function read($size, $mode=PHP_BINARY_READ) {
    return socket_read($this->child, $size, $mode);
  }
  
  /* public: use this function to write to the socket */
  function write($data) {
    return socket_write($this->child, $data, strlen($data));
  }  

  function get_request() {
    // handle request
    while(($buf= $this->read(2048)) !== false) { 
      $request_headers.= $buf;

      #if(preg_match("'\r\n\r\n$'s", $request_headers))
      if(strstr($request_headers,"\r\n\r\n"))
        break;
    }
    # M$IE bug workaround XXX
    if (!preg_match("'\r\n\r\n$'s", $request_headers)) {
      list($request_headers,$content) = explode("\r\n\r\n", $request_headers);
      $this->content=$content;
    }
 
    $request_headers = explode("\r\n", $request_headers);

    // parse
    list($request,$data) = $this->parse_request($request_headers);
    return array($request,$data);
  }

  function process($request) {
    // now respond 
    $today= gmdate("D, d M Y G:i:s")." GMT";

    if(!$this->is_path_allowed($request['PATH_TRANSLATED'])) {
      $header="HTTP/1.0 403 Forbidden\r\n".
        "Date: $today\r\n".
        "Server: ".$this->servername."\r\n".
        "Connection: close\r\n".
        "Content-type: text/html\r\n\r\n";
      $this->write($header);

      $html="<!DOCTYPE HTML PUBLIC \"-//IETF//DTD HTML 2.0//EN\">
<head>
<style>
h1 {font-family:tahoma,verdana,sans-serif;}
</style>
</head>
<body><h1>Forbidden</h1>
<p>You performed an illegal request $request[REQUEST_URI]</p>
<address>$this->servername</address></body></html>\n";
      $this->write($html);
    } else {
      // if user requests a directory search for directory index file
      if(is_dir($request['PATH_TRANSLATED'])) {
        if(file_exists($this->strip_trailing_slash($request['PATH_TRANSLATED']) . '/' . $this->directory_index)) {
	  $request['PATH_TRANSLATED'].= '/'.$this->directory_index;
	}
      }

      if(file_exists($request['PATH_TRANSLATED']) && is_file($request['PATH_TRANSLATED'])) {
        $mime=$this->mime_type($this->file_ext($request['PATH_TRANSLATED']));
        $header="HTTP/1.0 200 OK\r\n".
          "Date: $today\r\n".
          "Server: ".$this->servername."\r\n".
          "Connection: close\r\n".
          "Content-type: ".$mime."\r\n\r\n";
	  /* We're not yet supporting Keep-Alive connections */ 
        $this->write($header);

        $fp= fopen($request['PATH_TRANSLATED'], "r");
	if ($fp) {
	  while(!feof($fp) && ($this->write(fread($fp, 2048)) !== false));
          fclose($fp);
	}
      } else {
        $header="HTTP/1.0 404 Not Found\r\n".
          "Date: $today\r\n".
          "Server: ".$this->servername."\r\n".
          "Connection: close\r\n".
          "Content-type: text/html\r\n\r\n";
        $this->write($header);

        $html="<!DOCTYPE HTML PUBLIC \"-//IETF//DTD HTML 2.0//EN\">
<html><head><title>404 Not Found</title>
<style>
h1 {font-family:tahoma,verdana,sans-serif;}
</style>
</head><body>
<h1>404 Not Found</h1>
<p>The request URL $request[REQUEST_URI] was not found on this server.</p>
<hr />
<address>$this->servername</address>
</body></html>\n";
        $this->write($html);
      }
    }
    
    #$this->log("[".$remote_addr."] ".$request['REQUEST_METHOD'].
    #                             " ".$request['REQUEST_URI']);    
    #print("[".$remote_addr."] ".$request['REQUEST_METHOD'].
    #                        " ".$request['REQUEST_URI']);
    #print("\n");
  }

  function parse_request($request) {
    if(preg_match("'^(GET|POST) ([^ ]+) (HTTP/[^ ]+)$'", $request[0], $matches))
      ;
    else if(preg_match("'^(GET|POST) ([^ ]+)$'", $request[0], $matches)) {
      $matches[]='HTTP/1.0';
      print_r($matches);
    } else {
      return false;
    }
    
    list(, $req['REQUEST_METHOD'], $req['REQUEST_URI'], $req['SERVER_PROTOCOL']) = $matches;
    #print $request[0]."\n";
    if(($p = $this->parse_path($req['REQUEST_URI'])) !== false) {
      $req['PATH_INFO']       = $p['PATH_INFO'];
      $req['QUERY_STRING']    = $p['QUERY_STRING'];
      $req['PATH_TRANSLATED'] = $this->condense_path($this->document_root . $req['REQUEST_URI']);      
    }

    if (substr($req['REQUEST_URI'],0,strlen($this->wiki_prefix)) ==
      $this->wiki_prefix) {
      $req['SCRIPT_NAME']= substr($this->wiki_prefix,0,-1);
    }

    $remote_ip=getenv('REMOTE_ADDR');
    if (!$remote_ip) $req['REMOTE_ADDR']="127.0.0.1";
    else $req['REMOTE_ADDR']=$remote_ip;

    unset($request[0]);
    foreach ($request as $line) {
      if (!$line) continue;
      $key=strtoupper(strtok($line,":"));
      $val=trim(strtok(""));
      $HTTP[$key]=$val;
    }

    #print $HTTP['CONTENT-LENGTH']."\n";
    if ($HTTP['CONTENT-LENGTH']) {
      if (strpos($HTTP['USER-AGENT'],'MSIE') > 0) {
        if ($this->content) {
          $content=$this->content;
          $this->content='';
        } else {
          $content= $this->read($HTTP['CONTENT-LENGTH']);
          # M$IE bug workaround XXX
          # emit trailing garbage
          $dummy= $this->read(1024);
        }
      } else {
        $content= $this->read($HTTP['CONTENT-LENGTH']);
      }
    }

    if ($req['REQUEST_METHOD']=='POST') {
      if ($HTTP['CONTENT-TYPE']!='application/x-www-form-urlencoded') {
        $GLOBALS['HTTP_RAW_POST_DATA']=$content;
      } else {
        parse_str($content,$data);
      }
    }

    $req=array_merge($HTTP,$req);
    return array($req,$data);
  }

  function parse_path(&$path) {
    if(!preg_match("'([^?]*)(?:\?([^#]*))?(?:#.*)? *'", $path, $matches))
      return false;

    list(, $p['PATH_INFO'], $p['QUERY_STRING']) = $matches;

    if (!file_exists($this->document_root.$path)) {
      $new_uri=$path;
      while(!@is_file($this->document_root.$new_uri) && $new_uri) {
        $new_uri=substr($new_uri, 0, strrpos($new_uri, "/"));
      }
    }

    if ($new_uri) {
      // Path_info found
      $p['PATH_INFO']=substr($path, strlen($new_uri));
      $path=$new_uri;
    }

    return $p;
  }

  function condense_path($path) {
    while (preg_match("'/[^/]+/\.\./'" , $path))
      $path = preg_replace("'/[^/]+/\.\./'", "/", $path);
      
    return $path;
  }

  function is_path_allowed($path) {
    return strpos($path, $this->document_root) === 0; 
  }

  function strip_trailing_slash($path) {
    if (preg_match("'(.*?)/?$'", $path, $matches))
      return $matches[1];
  }

  function file_ext($path) {
    if (!preg_match("'\.([^/.]+)$'", $path, $matches))
      return '';
        
    return $matches[1];
  }

  function mime_type($ext) {
    switch($ext) {
      case 'hqx':
        return 'application/mac-binhex40';

      case 'cpt':
        return 'application/mac-compactpro';
	
      case 'doc':
        return 'application/msword';
	
      case 'bin':
      case 'dms':
      case 'lha':
      case 'lzh':
      case 'exe':
      case 'class':
      case 'so':
      case 'dll':
        return 'application/octet-stream';
	
      case 'oda':
        return 'application/oda';
	
      case 'pdf':
        return 'application/pdf';
	
      case 'ai':
      case 'eps':
      case 'ps':
        return 'application/postscript';
	
      case 'smi':
      case 'smil':
        return 'application/smil';
	
      case 'xls':
        return 'application/ms-excel';
	
      case 'ppt':
        return 'application/vnd.ms-powerpoint';
	
      case 'wbxml':
        return 'application/vnd.wap.wbxml';
	
      case 'wmlc':
        return 'application/vnd.wap.wmlc';
	
      case 'wmlsc':
        return 'application/vnd.wap.wmlscriptc';
	
      case 'bcpio':
        return 'application/x-bcpio';
	
      case 'vcd':
        return 'application/x-cdlink';
	
      case 'pgn':
        return 'application/x-chess-pgn';
	
      case 'cpio':
        return 'application/x-cpio';
	
      case 'csh':
        return 'application/x-csh';
	
      case 'dcr':
      case 'dir':
      case 'dxr':
        return 'application/x-director';
	
      case 'dvi':
        return 'application/x-dvi';
	
      case 'spl':
        return 'application/x-futuresplash';
	
      case 'gtar':
        return 'application/x-gtar';
	
      case 'hdf':
        return 'application/x-hdf';
	
      case 'js':
        return 'application/x-javascript';
	
      case 'skp':
      case 'skd':
      case 'skt':
      case 'skm':
        return 'application/x-koan';
	
      case 'latex':
        return 'application/x-latex';
	
      case 'nc':
      case 'cdf':
        return 'application/x-netcdf';
	
      case 'sh':
        return 'application/x-sh';

      case 'shar':
        return 'application/x-shar';
	
      case 'swf':
        return 'application/x-shockwave-flash';
	
      case 'sit':
        return 'application/x-stuffit';
	
      case 'sv4cpio':
        return 'application/x-sv4cpio';
	
      case 'sv4crc':
        return 'application/x-sv4crc';
	
      case 'tar':
        return 'application/x-tar';
	
      case 'tcl':
        return 'application/x-tcl';
	
      case 'tex':
        return 'application/x-tex';

      case 'texinfo':
      case 'texi':
        return 'application/x-texinfo';
	
      case 't':
      case 'tr':
      case 'troff':
        return 'application/x-troff';
	
      case 'man':
        return 'application/x-troff-man';

      case 'me':
        return 'application/x-troff-me';
	
      case 'ms':
        return 'application/x-troff-ms';
	
      case 'ustar':
        return 'application/x-ustar';
	
      case 'src':
        return 'application/x-wais-source';

      case 'zip':
        return 'application/zip';

      case 'au':
      case 'snd':
        return 'audio/basic';
	
      case 'mid':
      case 'midi':
      case 'kar':
        return 'audio/midi';
	
      case 'mpga':
      case 'mp2':
      case 'mp3':
        return 'audio/mpeg';

      case 'aif':
      case 'aiff':
      case 'aifc':
        return 'audio/x-aiff';
	
      case 'm3u':
        return 'audio/x-mpegurl';
	
      case 'ram':
      case 'rm':
        return 'audio/x-pn-realaudio';
	
      case 'rpm':
        return 'audio/x-pn-realaudio-plugin';

      case 'ra':
        return 'audio/x-realaudio';
	
      case 'wav':
        return 'audio/x-wav';
	
      case 'pdb':
        return 'chemical/x-pdb';
	
      case 'xyz':
        return 'chemical/x-xyz';

      case 'bmp':
        return 'image/bmp';
	
      case 'gif':
        return 'image/gif';
	
      case 'ief':
        return 'image/ief';
	
      case 'jpeg':
      case 'jpg':
      case 'jpe':
        return 'image/jpeg';
	
      case 'png':
        return 'image/png';

      case 'tiff':
      case 'tif':
        return 'image/tiff';

      case 'wbmp':
        return 'image/vnd.wap.wbmp';

      case 'ras':
        return 'image/x-cmu-raster';
	
      case 'pnm':
        return 'image/x-portable-anymap';
	
      case 'pbm':
        return 'image/x-portable-bitmap';
	
      case 'pgm':
        return 'image/x-portable-graymap';
	
      case 'ppm':
        return 'image/x-portable-pixmap';

      case 'rgb':
        return 'image/x-rgb';
	
      case 'xbm':
        return 'image/x-xbitmap';

      case 'xpm':
        return 'image/xpixmap';
	
      case 'xwd':
        return 'image/xwindowdump';

      case 'igs':
      case 'iges':
        return 'model/iges';

      case 'msh':
      case 'mesh':
      case 'silo':
        return 'model/mesh';

      case 'wrl':
      case 'vrml':
        return 'model/vrml';

      case 'css':
        return 'text/css';

      case 'html':
      case 'htm':
        return 'text/html';

      case 'asc':
      case 'txt':
        return 'text/plain';
	
      case 'rtx':
        return 'text/richtext';
	
      case 'rtf':
        return 'text/rtf';
	
      case 'sgml':
      case 'sgm':
        return 'text/sgml';

      case 'tsv':
        return 'text/tab-seperated-values';

      case 'wml':
        return 'text/vnd.wap.wml';
	
      case 'wmls':
        return 'text/vnd.wap.wmlscript';
	
      case 'etx':
        return 'text/x-setext';
	
      case 'xml':
      case 'xsl':
        return 'text/xml';
	
      case 'mpeg':
      case 'mpg':
      case 'mpe':
        return 'video/mpeg';

      case 'qt':
      case 'mov':
        return 'video/quicktime';
	
      case 'mxu':
        return 'video/vnd.mpegurl';
	
      case 'avi':
        return 'video/x-msvideo';
	
      case 'movie':
        return 'video/x-sgi-movie';
	
      case 'ice':
        return 'x-conference/x-cooltalk';

      default:
        return 'text/plain';
    }
  }

  /* public: log messages */
  function log($message) {
    fputs($this->log_handle, $message . "\n");  
  }
  
  /* public: log errors */
  function log_error($message) {
    fputs($this->error_handle, $message . "\n");
  }
 
  function loop() {
    do {
      if (($child = socket_accept($this->socket)) < 0) {
        echo "socket_accept() failed: reason: ".socket_strerror($msgsock)."\n";
        break;
      }
      $this->child=$child;

      list($request,$data)=$this->get_request();
      $this->process($request);

      #socket_shutdown($this->child);
      socket_close($this->child);
    } while(true);
  }

  function close() {
    socket_close ($this->socket);
  }
} // end of simple_server

echo "MoniWiki Web Server !\r\n";
ob_implicit_flush();
#$httpd=new simple_server('localhost',8080,"c:\htdocs");
define('INC_MONIWIKI', 1);
# Start Main
require_once("wiki.php");
$Config = getConfig('config.php', array('init'=>1));
require_once("wikilib.php");
require_once("lib/win32fix.php");
require_once("lib/wikiconfig.php");
require_once("lib/cache.text.php");
require_once("lib/timer.php");

$options = array();
if (class_exists('Timer')) {
  $timing = new Timer();
  $options['timer'] = &$timing;
  $options['timer']->Check("load");
}

$ccache = new Cache_text('settings', array('depth'=>0));
if (!($conf = $ccache->fetch('config'))) {
  $Config = wikiConfig($Config);
  $ccache->update('config', $Config, 0, array('deps'=>array('config.php', 'lib/wikiconfig.php')));
} else {
  $Config = &$conf;
}

$DBInfo= new WikiDB($Config);

if (isset($options['timer']) and is_object($options['timer'])) {
  $options['timer']->Check("load");
}

$lang= set_locale($DBInfo->lang,$DBInfo->charset);
init_locale($lang);
init_requests($options);
if (!isset($options['pagename'][0])) $options['pagename']= get_frontpage($lang);
$DBInfo->lang=$lang;

$DBInfo->query_prefix='/';

if ($DBInfo->httpd_docs)
  $httpd=new simple_server('localhost',8080,$DBInfo->httpd_docs);
else
  $httpd=new simple_server('localhost',8080,"c:\htdocs");

$wiki_prefix= $httpd->wiki_prefix;

// Initialize Wiki
//$httpd->loop();
//
//exit;

do {
  if (($child = socket_accept($httpd->socket)) < 0) {
    echo "socket_accept() failed: reason: ".socket_strerror($msgsock)."\n";
    break;
  }
  $httpd->child=$child;
 
  $options['timer']=new Timer();

  list($_SERVER,$data)=$httpd->get_request();

  parse_str($_SERVER['QUERY_STRING'],$temp);
  if ($_SERVER['REQUEST_METHOD']=='GET') {
    $_GET=$temp;
  } else if ($_SERVER['REQUEST_METHOD']=='POST') {
    if ($data) $_POST=$data;
  } else {
    print "Invalid request error!\n";
  }

  if (substr($_SERVER['REQUEST_URI'],0,strlen($wiki_prefix)) == $wiki_prefix) {
    # WikiPage
    $today= gmdate("D, d M Y G:i:s")." GMT";
    $mime="text/html";
    $status="HTTP/1.0 200 OK\r\n";
    $header="Date: $today\r\n".
          "Server: ".$httpd->servername."\r\n".
          "Connection: close\r\n".
          "Content-type: ".$mime."\r\n";
	  /* We're not yet supporting Keep-Alive connections */ 
    $pagename=urldecode(substr($_SERVER['PATH_INFO'],strlen($wiki_prefix)));
    if (!$pagename) $pagename=$DBInfo->frontpage;
    $options['pagename'] = $pagename;

    ob_start();
    wiki_main($options);
    $html=ob_get_contents();
    ob_end_clean();

    # header hack XXX
    $extra_header='';
    if ($GLOBALS['http_header']) {
      $extra_header=$GLOBALS['http_header'];
      print($GLOBALS['http_header']);
    }
    if ($GLOBALS['http_status'])
      $status="HTTP/1.0 ".$GLOBALS['http_status']."\r\n";
    $GLOBALS['http_header']='';
    $GLOBALS['http_status']='';
    #
    $httpd->write($status.$header.$extra_header."\r\n");
    $httpd->write($html);
#    print $html;
  } else {
    $httpd->process($_SERVER);
  }

  socket_close($httpd->child);
  #socket_shutdown($httpd->child);
} while(true);
