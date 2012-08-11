<?php
// Copyright 2003-2005 Dongsu Jang <iolo at hellocity.net>
//                     Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
//
// Firefox/Mozilla Search Plugin for MoniWiki
//
// Usage: [[SearchPlugin(<prefix>,<name>,<text>)]]
// <prefix>
//   url(location) of both descriptor(.src) and icon(.png)(no default!)
//   or search type(action) to use dynamic descritor(.src)
// <type> - search type. "fullsearch", "titlesearch", "fastsearch" or somthing.(default=fullsearch)
// <name> - identifier. if not specified, sitename will be used.(default=sitename)
// <text> - contents text of <a> tag this plugin generates.(default=Add Search Plugin)
//
// This plugin generates/uses following links:
// For static mode
// - <url>/name.src
// - <url>/name.png
// For dynamic mode
// - http://.../wiki.php/?action=SearchPlugin&name=<name>.src
// - http://.../wiki.php/?action=SearchPlugin&name=<name>.png
// Also, generate/uses #SearchPlugin css style class.
//
// FIXME:
// filename parts of update and icon url must be identical!
// and mozilla doesn't use the filename specified by disposition header,
// but use query string like IE! :@
// so, i'd append dummy extensions with '.src' and '.png' to deceive mozilla :(
//
// $Id: SearchPlugin.php,v 1.4 2006/01/05 17:33:43 wkpark Exp $

function macro_SearchPlugin($formatter,$value,$options='') {
  global $DBInfo;

  $cat='General';

  // parse value and provide defaults
  list($prefix,$name,$text)=explode(',',$value,3);
  if (!$name) {
    $name = $DBInfo->sitename;
  }
  if (!$text) {
    $text = "Add Search Plugin";
  }
  if (preg_match('/^http:\/\//',$prefix)) {
    if (substr($prefix,-1)!='/') $prefix.='/';
    $update_url = $prefix;
  } else {
    if (!$prefix) {
      $prefix="fullsearch";//by default, fullsearch 
    }
    $update_url=
      qualifiedUrl(
        $formatter->link_url('FindPage','?action=SearchPlugin&amp;type='.$prefix.'&amp;name='));
  }

  return <<<EOS
<div class="SearchPlugin">
<script type="text/javascript">
/*<![CDATA[*/
function addSearchPlugin(update_url, name)
{
  if ((typeof window.sidebar == "object") &&
      (typeof window.sidebar.addSearchEngine == "function")) {
    cat=prompt("In what category should this engine be installed?","$cat")
    window.sidebar.addSearchEngine(
      update_url + name + ".src",
      update_url + name + ".png",
      name,
      cat);
  } else {
    alert("Firefox, Mozilla or Compatible Browser is needed to install a search plugin");
  }
}
/*]]>*/
</script>
<a href="javascript:addSearchPlugin('$update_url', '$name')">$text</a>
</div>
EOS;
}

function do_SearchPlugin($formatter,$options) {
  global $DBInfo;

  if ($options['name']) {
    $name = $options['name'];
  } else {
    $name = $DBInfo->sitename;
  }
  if ($options['type']) { // fullsearch,titlesearch,fastsearch
    $type = $options['type'];
  } else {
    $type = "fullsearch"; // by default fullsearch
  }
  if (strpos($name, ".png") != false) {
    header("Content-Type: image/png\r\n");
    header("Content-Disposition: inline; filename=\"$name\"" );
    #header("Content-Disposition: attachment; filename=\"$name\"" );
    header("Content-Description: MoniWiki Search Plugin Descriptor" );
    Header("Pragma: no-cache");
    Header("Expires: 0");
    $fp = readfile("imgs/interwiki/moniwiki-16.png"); // XXX
    return;
  } if (strpos($name, ".src") == false) {
    // error! invalid options
    // name=xxx.[src|png]
    print "invalid option!";
    return;
  }

  $update_url =
    qualifiedUrl($formatter->link_url('FindPage',"?action=SearchPlugin&amp;name="));

  // FIXME: what's the valid way to get http://.../wiki.php ?
  // alternative: $_SERVER["PHP_SELF"]
  $base_url=qualifiedUrl($formatter->link_url("FindPage"));
  // FIXME: what's the valid search page name for all moniwiki sites?
  $form_url=qualifiedUrl($formatter->link_url("FindPage"));

  #header("Content-Type: application/x-wais-source\r\n");
  header("Content-Type: text/plain\r\n");
  header("Content-Disposition: inline; filename=\"$name\"" );
  #header("Content-Disposition: attachment; filename=\"$name\"" );
  header("Content-Description: MoniWiki Search Plugin Descriptor" );
  Header("Pragma: no-cache");
  Header("Expires: 0");

  // remove file extension part(should be ".src") from url
  // it's just a dummy to deceive stupid mozilla :@
  $name = substr($name, 0, -4);

  $charset=$DBInfo->charset;

  print <<<EOS
# Firefox/Mozilla Search Plugin for MoniWiki by iolo@hellocity.net
<search
  version="7.1"
  name="$name"
  description="MoniWiki Search Plugin for $name"
  method="GET"
  action="$base_url"
  searchForm="$form_url"
  queryEncoding="$charset"
  queryCharset="$charset"
  routeType="internet"
>

<input name="sourceid" value="mozilla-search">
#<inputnext name="start" factor="10">
#<inputprev name="start" factor="10">

<input name="value" user>

# TODO: support various search actions and parameters
<input name="action" value="$type">
<input name="context" value="20">
<input name="backlinks" value="0">
<input name="case" value="0">
#<input name="ie" value="utf-8">
#<input name="oe" value="utf-8">

<interpret
  browserResultType="result"
  charset = "$charset"
  resultListStart="<!-- RESULT LIST START -->"
  resultListEnd="<!-- RESULT LIST END -->"
  resultItemStart="<!-- RESULT ITEM START -->"
  resultItemEnd="<!-- RESULT ITEM END -->"
>
</search>

<browser
  update="$update_url$name.src"
  updateIcon="$update_url$name.png"
  updateCheckDays=1
>

EOS;
}
?>
