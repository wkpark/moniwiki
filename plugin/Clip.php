<?php
// Copyright 2005-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// Draw plugin with the Clip applet for the MoniWiki
//
// Usage: [[Clip(hello)]]
//
// $Id: Clip.php,v 1.5 2010/07/17 16:17:56 wkpark Exp $

function macro_Clip($formatter,$value) {
  global $DBInfo;
  $keyname=$DBInfo->_getPageKey($formatter->page->name);
  $_dir=str_replace("./",'',$DBInfo->upload_dir.'/'.$keyname);

  // support hashed upload dir
  if (!is_dir($_dir) and !empty($DBInfo->use_hashed_upload_dir)) {
    $prefix = get_hashed_prefix($keyname);
    $_dir = str_replace('./','',$DBInfo->upload_dir.'/'.$prefix.$keyname);
  }
  $name=_rawurlencode($value);

  $enable_edit=0;

  umask(000);
  if (!file_exists($_dir))
    _mkdir_p($_dir, 0777);

  $pngname=$name.'.png';
  $now=time();

  $url=$formatter->link_url($formatter->page->name,"?action=clip&amp;value=$name&amp;now=$now");

  if (!file_exists($_dir."/$pngname"))
    return "<a href='$url'>"._("Paste a new picture")."</a>";
  $edit='';
  $end_tag='';
  if ($enable_edit) {
    $edit="<a href='$url'>";
    $end_tag='</a>';
  }

  return "$edit<img src='$DBInfo->url_prefix/$_dir/$pngname' border='0' alt='image' />$end_tag\n";
}

function do_post_Clip($formatter,$options) {
  global $DBInfo;

  if ($_SERVER['REQUEST_METHOD'] == 'POST' &&
        !$DBInfo->security->writable($options)) {
    $options['title'] = _("Page is not writable");
    return do_invalid($formatter, $options);
  }

  $enable_replace=1;

  $keyname=$DBInfo->_getPageKey($options['page']);
  $_dir=str_replace("./",'',$DBInfo->upload_dir.'/'.$keyname);

  // support hashed upload dir
  if (!is_dir($_dir) and !empty($DBInfo->use_hashed_upload_dir)) {
    $prefix = get_hashed_prefix($keyname);
    $_dir = str_replace('./','',$DBInfo->upload_dir.'/'.$prefix.$keyname);
  }

  umask(000);
  if (!file_exists($_dir))
    _mkdir_p($_dir, 0777);

  $pagename=_urlencode($options['page']);

  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (empty($options['value']) || empty($options['name'])) {
      echo "false\n"._("No data posted");
      return;
    }

    $name = trim($options['name']);
    // simple name checker.
    $dummy = explode('/', $name);
    $name = trim($dummy[count($dummy) - 1]);
    if (empty($name)) {
      echo "false\n";
      echo _("No filename given");
      return;
    }

    // decode base64
    if (substr($options['value'], 0, 5) == "data:" and preg_match("/^data:image\/(png|jpe?g|gif);base64,/", $options['value'])) {
      $tmp = explode(";", substr($options['value'], 11));
      $type = $tmp[0];
      $base64 = substr($tmp[1], 7);
      $raw = base64_decode($base64);
      if ($raw === false) {
        $err = _("Fail to decode base64 data string.");
        echo "false\n".$err;
        return;
      }
    } else {
      echo "false\n"._("Fail to parse given base64 string");
      return;
    }
    $imgname = $name.'.'.$type;
    $fp = fopen($_dir.'/'.$imgname, 'wb');
    if (is_resource($fp)) {
      fwrite($fp, $raw);
      fclose($fp);
    } else {
      echo "false\n"._("Fail to write a image file");
      return;
    }

    echo "true\n";
    return;
  }

  $name = trim($options['value']);
  // simple name checker.
  $dummy = explode('/', $name);
  $name = trim($dummy[count($dummy) - 1]);

  if (empty($name)) {
    $title=_("Fatal error !");
    $formatter->send_header("Status: 406 Not Acceptable",$options);
    $formatter->send_title($title,"",$options);
    print "<h2>"._("No filename given")."</h2>";
    $formatter->send_footer("",$options);
    
    return;
  }

  $pngname=_rawurlencode($name);

  //$imgpath="$_dir/$pngname";
  $imgpath="$pngname";
  $js = '';
  $url = null;
  if (file_exists($_dir.'/'.$imgpath.'.png')) {
    $url=qualifiedUrl($DBInfo->url_prefix.'/'.$_dir.'/'.$imgpath.'.png');
  }

  $url_save = $formatter->link_url($pagename,"");

  $js = <<<JS
<script type="text/javascript">
/*<![CDATA[*/
(function(){
var name = "$pngname";

var url = "$url";
function showImage(url) {
  var c = $("#clipMacro")[0].getContext("2d");
  var img = new Image();
  img.src = "$url";
  img.onload = function() {
    c.drawImage(img, 0, 0, img.width, img.height);
  };
}

if (url) {
  showImage(url);
}

function postBase64(base64, name) {
    var postdata = 'action=clip&name=' + name + '&value=' + encodeURIComponent(base64);
    HTTPPost("$url_save", postdata, function(ret) {
        console.log(ret);
        showImage(url);
    });
}

$(document).ready(function() {
$("#clipPlugin").on('paste', function(e) {
  var clipData = (e.clipboardData || e.originalEvent.clipboardData)
  if (!clipData)
    return;
  var items = clipData.items;

  for (index in items) {
    var item = items[index];
    if (item.kind === 'file' && item.type.match('^image/')) {
      console.log(item.kind);
      var blob = item.getAsFile();
      var reader = new FileReader();
      reader.onload = function(e){
        console.log(e.target.result);
        postBase64(e.target.result, name);
      };
      reader.readAsDataURL(blob);
    } else if (item.kind === 'string' && item.type.match('^text/html')) {
      console.log(item.kind);
      console.log(item.getAsString(function(d){console.log(d)}));
    }
  }
});
});
})();
/*]]>*/
</script>\n
JS;

  $png_url="$imgpath.png";

  $formatter->send_header("",$options);
  $formatter->send_title(_("Clipboard"),"",$options);
  $prefix=$formatter->prefix;
  $now=time();

  $url_exit= $formatter->link_url($pagename,"?ts=$now");
  $url_help= $formatter->link_url("ClipMacro");

  print "<h2>"._("Paste a Clipboard Image")."</h2>\n";
  $placeholder = _("Paste your image...");
  print <<<APPLET
<input id="clipPlugin" size="40" placeholder="$placeholder"></input><br />
<canvas width="200" height="200" id='clipMacro' tabindex="-1" data-pngpath="$png_url" data-savepath="$url_save" data-viewpath="$url_exit" style="border:1px solid black"></canvas>
$js
APPLET;

  $formatter->send_footer("",$options);
  return;
}

// vim:et:sts=2:
