<?php
// Copyright 2004 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a freemind macro/action plugin for the MoniWiki
//
// $Id: freemind.php,v 1.1 2004/12/01 08:23:37 wkpark Exp $

function macro_FreeMind($formatter,$value) {
    global $DBInfo;

    $_dir=$DBInfo->upload_dir.'/FreeMind';
    $pubpath = $formatter->url_prefix.'/applets/FreeMind';
    $puburl = qualifiedUrl($formatter->url_prefix.'/'.$_dir);
    return <<<APP
  <applet code="freemind.main.FreeMindApplet.class" codebase="$pubpath"
          archive="freemindbrowser.jar" width="100%" height="100%">
  <param name="type" value="application/x-java-applet;version=1.4">
  <param name="scriptable" value="false">

  <param name="modes" value="freemind.modes.browsemode.BrowseMode">
  <param name="browsemode_initial_map"
         value="$puburl/$value.mm">
  <!--          ^ Put the path to your map here  -->
  <param name="initial_mode" value="Browse">
  </applet>
APP;

}

function do_freemind($formatter,$options) {
  #$formatter->send_header('',$options);
  print <<<HEAD
<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML//EN">
<html>
<!-- This launcher works fine with Explorer (with Javascript or without) as
     well as with Mozilla on Windows -->
<head>
  <title>Free Mind for MoniWiki</title>
<style>
  body {margin:0px;}
</style>
</head>
<body>
HEAD;
  print macro_FreeMind($formatter,$options['value']);
  print "</body></html>";
  return;
}

// vim:et:sts=4:sw=4:et:
?>
