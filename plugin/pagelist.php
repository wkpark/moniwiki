<?php
function macro_PageList($formatter,$arg="") {
  global $DBInfo;

  preg_match("/([^,]*)(\s*,\s*)?(.*)?$/",$arg,$match);
  if ($match[1]=='date') {
    $options['date']=1;
    $arg='';
  } else if ($match) {
    $arg=$match[1];
    $options=array();
    if ($match[3]) $options=explode(",",$match[3]);
    if (in_array('date',$options)) $options['date']=1;
    else if ($arg and (in_array('metawiki',$options) or in_array('m',$options)))
      $options['metawiki']=1;
  }
  $needle=_preg_search_escape($arg);

  $test=@preg_match("/$needle/","",$match);
  if ($test === false) {
    # show error message
    return "[[PageList(<font color='red'>Invalid \"$arg\"</font>)]]";
  }

  if ($options['date']) {
    $user=new User(); # get from COOKIE VARS
    if ($user->id != 'Anonymous') {
      $udb=new UserDB($DBInfo);
      $udb->checkUser($user);
      $tz_offset=$user->info['tz_offset'];
    } else {
      $tz_offset=date('Z');
    }
    $all_pages = $DBInfo->getPageLists($options);
  } else {
    if ($options['metawiki'])
      $all_pages = $DBInfo->metadb->getLikePages($needle);
    else
      $all_pages = $DBInfo->getPageLists();
#     $all_pages= array_unique(array_merge($meta_pages,$all_pages));
  }
#  $all_pages = $DBInfo->getPageLists($options);

#  print_r($all_pages);

  $hits=array();

  if ($options['date']) {
    if ($needle) {
      while (list($pagename,$mtime) = @each ($all_pages)) {
        preg_match("/$needle/",$pagename,$matches);
        if ($matches) $hits[$pagename]=$mtime;
      }
    } else $hits=$all_pages;
    arsort($hits);
    while (list($pagename,$mtime) = @each ($hits)) {
      $out.= '<li>'.$formatter->link_tag(_rawurlencode($pagename),"",
	htmlspecialchars($pagename)).
	". . . . [".gmdate("Y-m-d",$mtime+$tz_offset)."]</li>\n";
    }
    $out="<ol>\n".$out."</ol>\n";
  } else {
    foreach ($all_pages as $page) {
      preg_match("/$needle/",$page,$matches);
      if ($matches) $hits[]=$page;
    }
    sort($hits);
    foreach ($hits as $pagename) {
      $out.= '<li>' . $formatter->link_tag(_rawurlencode($pagename),"",
	htmlspecialchars($pagename))."</li>\n";
    }
    $out="<ul>\n".$out."</ul>\n";
  }

  return $out;
}

// vim:et:sts=4:
?>
