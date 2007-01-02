<?php
// Copyright 2003-2004 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// download action plugin for the MoniWiki
//
// $Id$
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
  $down_mode=$options['mode']{0}=='a' ? 'attachment':
    ($DBInfo->download_mode ? $DBInfo->download_mode:'inline');


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

  if (file_exists($dir))
    $handle= opendir($dir);
  else {
    $dir=$DBInfo->upload_dir;
    $handle= opendir($dir);
  }

  $file=explode('/',$value);
  $file=$subdir.$file[count($file)-1];

  if (!file_exists("$dir/$file")) {
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
  if (preg_match("/\.(.{1,4})$/",$file,$match))
    $mimetype=strtolower($mime[$match[1]]);
  if (!$mimetype) $mimetype="application/x-unknown";

  if (strstr($_SERVER['HTTP_USER_AGENT'], 'MSIE')) {
    // IE: rawurlencode()
    $fname='filename="'.rawurlencode($file).'"';
    // fix IE bug
    $fname = preg_replace('/\./', '%2e',
        $fname, substr_count($fname, '.') - 1);

    #header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    #header('Pragma: public');
  } else if (strstr($_SERVER['HTTP_USER_AGENT'], 'Opera')) {
    // Opera 9: RFC 2231
    $fname='filename*='.$DBInfo->charset."*".rawurlencode($file).'';
  } else // Mozilla: RFC 2047
    $fname='filename="=?'.$DBInfo->charset.'?B?'.base64_encode($file).'?="';

  if ($DBInfo->use_resume_download) {
    $header=array("Content-Description: MoniWiki PHP Downloader");
    dl_file_resume($mimetype,$dir.'/'.$file,$fname,$down_mode,$header);
    return; 
  }

  header("Content-Type: $mimetype\r\n");
  header("Content-Length: ".filesize($dir.'/'.$file));
  header("Content-Disposition: $down_mode; ".$fname );
  header("Content-Description: MoniWiki PHP Downloader" );
  header("Last-Modified: " . gmdate("D, d M Y H:i:s",filemtime($dir.'/'.$file)) . " GMT");
  header("Pragma:");
  header("Cache-Control:");
  if (!preg_match('/^image\//',$mimetype)) {
    Header("Pragma: no-cache");
    Header("Cache-Control: no-cache");
    Header("Expires: 0");
  }

  $fp=readfile("$dir/$file");
  return;
}

function macro_download($formatter,$value) {
  return $formatter->link_to("?action=download&amp;value=$value",$value);
}

function dl_file_resume($ctype,$file,$fname,$mode='inline',$header='') {
   # from http://kr2.php.net/manual/en/function.fread.php#63893
   # ans some modification
  
   //Gather relevent info about file
   $len = filesize($file);
  
   //Begin writing headers
   header("Cache-Control:");
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
  
   $size=filesize($file);
   //check if http_range is sent by browser (or download manager)
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
       header("Content-Range: bytes 0-$size2/$size");
       header("Content-Length: ".$size);
       header("Content-Disposition: $mode; $fname");
       header("Last-Modified: " . gmdate("D, d M Y H:i:s",filemtime($file)) . " GMT");
       if (!preg_match('/^image\//',$ctype)) {
          Header("Pragma: no-cache");
          Header("Cache-Control: no-cache");
          Header("Expires: 0");
       }
   }
   //open the file
   $fp=fopen("$file","rb");
   //seek to start of missing part
   fseek($fp,$range);
   //start buffered download
   while(!feof($fp)){
       //reset time limit for big files
       set_time_limit(0);
       print(fread($fp,1024*8));
       flush();
       ob_flush();
   }
   fclose($fp);
   exit;
}

// vim:et:sts=4:
?>
