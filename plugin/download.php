<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// download action plugin for the MoniWiki
//
// $Id: download.php,v 1.28 2010/08/26 18:34:55 wkpark Exp $
//
function do_download($formatter,$options) {
  global $DBInfo;

  if (!$options['value']) {
    if (!function_exists('do_uploadedfiles'))
      include_once dirname(__FILE__).'/UploadedFiles.php';
    do_uploadedfiles($formatter,$options);
    return; 
  }
  $value=&$options['value'];
  $down_mode=(!empty($options['mode']) and $options['mode']{0}=='a') ? 'attachment':
    (!empty($DBInfo->download_mode) ? $DBInfo->download_mode:'inline');


  // check acceptable subdirs
  $acceptable_dirs=array('thumbnails');

  $ifile=explode('/',$options['value']);

  $subdir='';
  if (count($ifile) > 1) {
    $subdir=in_array($ifile[count($ifile)-2],$acceptable_dirs) ?
      $ifile[count($ifile)-2].'/':'';

    if ($subdir) {
      unset($ifile[count($ifile)-2]);
      $value=implode('/',$ifile);
    }
  }

  if (($p=strpos($value,':')) !== false or ($p=strpos($value,'/')) !== false) {
    $subpage=substr($value,0,$p);
    $file=substr($value,$p+1);
    $value=$subpage.'/'.$file; # normalize page arg
    if ($subpage and $DBInfo->hasPage($subpage)) {
      $pagename=&$subpage;
      $key=$DBInfo->pageToKeyname($subpage);
    } else {
      $pagename='';
      $key='';
    }
  } else {
    $pagename=&$formatter->page->name;
    $key=$DBInfo->pageToKeyname($formatter->page->name);
  }

  #if (!$key) {
  #  // FIXME
  #  return;
  #}
  $dir=$DBInfo->upload_dir.($key ? "/$key":"");

  if ($key == 'UploadFile')
    $dir=$DBInfo->upload_dir;

  if (file_exists($dir))
    $handle= opendir($dir);
  else {
    $dir=$DBInfo->upload_dir;
    $handle= opendir($dir);
  }

  $file=explode('/',$value);
  $file=$subdir.$file[count($file)-1];

  $_l_file=_l_filename($file);
  if (!file_exists("$dir/$_l_file")) {
    header("HTTP/1.1 404 Not Found");
    return;
  }

  $lines = @file($DBInfo->data_dir.'/mime.types');
  if ($lines) {
    foreach($lines as $line) {
      rtrim($line);
      if (preg_match('/^\#/', $line))
        continue;
      $elms = preg_split('/\s+/', $line);
      $type = array_shift($elms);
      foreach ($elms as $elm) {
       $mime[$elm] = $type;
      }
    }
  } else
    $mime=array();

  # set filename
  if (preg_match("/\.(.{1,4})$/",$file,$match)) {
    $ext = strtolower($match[1]);
    $mimetype= !empty($mime[$ext]) ? $mime[$ext] : '';
  }
  if (empty($mimetype)) $mimetype="application/x-unknown";

  if (strstr($_SERVER['HTTP_USER_AGENT'], 'MSIE')) {
    // IE: rawurlencode()
    $fn = preg_replace('/[:\\x5c\\/*?"<>|]/', '_', $file);
    $fname='filename="'.rawurlencode($fn).'"';
    // fix IE bug
    $fname = preg_replace('/\./', '%2e',
        $fname, substr_count($fname, '.') - 1);

    #header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    #header('Pragma: public');
  } else if (strstr($_SERVER['HTTP_USER_AGENT'], 'Mozilla')) {
    // Mozilla: RFC 2047
    $fname='filename="=?'.$DBInfo->charset.'?B?'.base64_encode($file).'?="';
  } else {
    // etc. Safari, Opera 9: RFC 2231
    $fn = preg_replace('/[:\\x5c\\/{?]/', '_', $file);
    $fname='filename*='.$DBInfo->charset."''".rawurlencode($fn).'';
    //$fname='filename="'.$fn.'"';
  }

  if (!empty($DBInfo->use_resume_download)) {
    $header=array("Content-Description: MoniWiki PHP Downloader");
    dl_file_resume($mimetype,$dir.'/'. $_l_file,$fname,$down_mode,$header);
    return; 
  }

  header("Content-Type: $mimetype\r\n");
  header("Content-Length: ".filesize($dir.'/'. $_l_file));
  header("Content-Disposition: $down_mode; ".$fname );
  header("Content-Description: MoniWiki PHP Downloader" );
  $mtime = filemtime($dir.'/'.$_l_file);
  $lastmod = gmdate("D, d M Y H:i:s", $mtime) . ' GMT';
  $etag = md5($lastmod);
  header("Last-Modified: " . $lastmod);
  header('ETag: "'.$etag.'"');
  header("Pragma:");
  $maxage = 60*60*24*7;
  header('Cache-Control: public, max-age='.$maxage);
  $need = http_need_cond_request($mtime, $lastmod, $etag);
  if (!$need) {
    header('X-Cache-Debug: Cached OK');
    header('HTTP/1.0 304 Not Modified');
    @ob_end_clean();
    return;
  }

  $fp=readfile("$dir/$_l_file");
  return;
}

function macro_download($formatter,$value) {
  return $formatter->link_to("?action=download&amp;value=$value",$value);
}

function dl_file_resume($ctype,$file,$fname,$mode='inline',$header='') {
   # from http://kr2.php.net/manual/en/function.fread.php#63893
   # ans some modification
  
   //Gather relevent info about file
   $size = filesize($file);
   if ($size == 0) return;
  
   //Begin writing headers
   //header("Cache-Control:");
   header("Cache-Control: public");
   if (is_array($header)) foreach($header as $h) header($h);
  
   //Use the switch-generated Content-Type
   header("Content-Type: $ctype");
   if (strstr($_SERVER['HTTP_USER_AGENT'], "MSIE")) {
       # workaround for IE filename bug with multiple periods / multiple dots
       # in filename that adds square brackets to filename
       # - eg. setup.abc.exe becomes setup[1].abc.exe
       $fname = preg_replace('/\./', '%2e',
           $fname, substr_count($fname, '.') - 1);
   }
   header("Accept-Ranges: bytes");
  
   //check if http_range is sent by browser (or download manager)
   $range = 0;
   if(isset($_SERVER['HTTP_RANGE'])) {
       list($a, $range)=explode("=",$_SERVER['HTTP_RANGE']);
       //if yes, download missing part
       str_replace($range, "-", $range);
       $size2=$size-1;
       $new_length=$size2-$range;
       header("HTTP/1.1 206 Partial Content");
       header("Content-Range: bytes $range$size2/$size");
       header("Content-Length: $new_length");
       header("Content-Disposition: $mode; $fname");
   } else {
       $size2=$size-1;
       header("Pragma:");
       $maxage = 60*60*24*7;
       header('Cache-Control: public, max-age='.$maxage);
       header("Content-Range: bytes 0-$size2/$size");
       header("Content-Length: ".$size);
       header("Content-Disposition: $mode; $fname");
       $mtime = filemtime($file);
       $lastmod = gmdate("D, d M Y H:i:s", $mtime) . " GMT";
       $etag = md5($lastmod);
       header("Last-Modified: " . $lastmod);
       header('ETag: "'.$etag.'"');
       $need = http_need_cond_request($mtime, $lastmod, $etag);
       if (!$need) {
          header('X-Cache-Debug: Cached OK');
          header('HTTP/1.0 304 Not Modified');
          @ob_end_clean();
          return;
       }
   }
   //open the file
   $fp=fopen("$file","rb");
   if (!is_resource($fp)) return;
   //seek to start of missing part
   fseek($fp,$range);
   //start buffered download
   //reset time limit for big files
   set_time_limit(0);
   $chunksize = 1*(1024*1024); // 1MB chunks
   $left = $size;

   // start output buffering
   //ob_start();
   while(!feof($fp) and $left > 0){
       $sz = $chunksize < $left ? $chunksize : $left;
       echo fread($fp, $sz);
       flush();
       @ob_flush();
       $left -= $sz;
   }
   fclose($fp);
   //ob_end_flush();
   return;
}

// vim:et:sts=4:
?>
